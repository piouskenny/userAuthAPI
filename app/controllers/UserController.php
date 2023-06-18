<?php

namespace App\controllers;

require __DIR__ . "/../../vendor/autoload.php";

use Predis\Client;
use App\database\Connection;

class UserController
{
    private $redis;
    private $allowedAttempts = 5;
    private $lockoutDuration = 180; // 3 minutes in seconds

    private $database;

    public function __construct()
    {
        $redisOptions = [
            'scheme' => 'tcp',
            'host' => '127.0.0.1',
            'port' => 6379,
        ];
        $this->redis = new Client($redisOptions);
    }

    private function isRateLimitExceeded($username)
    {
        $rateLimitKey = 'profile_attempts:' . $username;
        $attemptCount = (int) $this->redis->get($rateLimitKey);

        if ($attemptCount >= $this->allowedAttempts) {
            $remainingTime = $this->lockoutDuration - (time() - (int) $this->redis->get('last_failed_profile_time:' . $username));
            if ($remainingTime > 0) {
                header("HTTP/1.1 429 Too Many Requests");
                echo json_encode(['error' => "Too many failed profile attempts. Please try again in $remainingTime seconds."]);
                return true;
            } else {
                $this->redis->del($rateLimitKey);
                $this->redis->del('last_failed_profile_time:' . $username);
            }
        }

        return false;
    }

    private function incrementRateLimit($username)
    {
        $rateLimitKey = 'profile_attempts:' . $username;

        if ($this->redis->exists($rateLimitKey)) {
            $this->redis->incr($rateLimitKey);
        } else {
            $this->redis->set($rateLimitKey, 1, 'EX', $this->lockoutDuration);
        }

        $this->redis->set('last_failed_profile_time:' . $username, time());
    }

    private function generateToken($username)
    {
        // Generate a custom token here (e.g., using a library like JWT)
        $token = bin2hex(random_bytes(16));

        $this->redis->set('user_token:' . $username, $token, 'EX', $this->lockoutDuration);

        return $token;
    }

    public function profile()
    {
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            die(json_encode("The GET request is not supported for this route"));
        }


        $username = $_GET['username'];
        $token = $_GET['token'];


        $database = new Connection;

        $sql = "SELECT * FROM users WHERE username = '$username' LIMIT 1";

        $query = $database->base_query($sql);

        $user = $query->fetch_assoc();


        $username = $user['username'] ?? '';
        $email = $user['email'] ?? '';
        $storedToken = $this->redis->get('user_token:' . $username);


        if ($this->isRateLimitExceeded($username)) {
            return;
        }

        if ($token !== $storedToken) {
            echo json_encode(['failed' => "Invalid Token"]);
            return;
        }

        echo json_encode(['success' => "Login successful", 'userToken' => $storedToken, 'username' => $username, 'email' => $email]);
    }

    public function signup()
    {
        if ($_SERVER['REQUEST_METHOD'] === 'GET') {
            die(json_encode("The GET request is not supported for this route"));
        }

        $json = file_get_contents('php://input');
        $data = json_decode($json, true);

        if ($data === null) {
            echo json_encode(['error' => "Invalid JSON data"]);
            return;
        }

        $username = $data['username'] ?? '';
        $email = $data['email'] ?? '';
        $password = md5($data['password']) ?? '';


        if ($username === '' || $email === '' || $password === '') {
            echo json_encode(['error' => "Username, email, and password are required"]);
            return;
        }

        $database = new Connection;

        $sql = "INSERT INTO users (username, email, password) VALUES ('$username', '$email', '$password')";

        $database->base_query($sql);
        if ($database) {
            echo json_encode(['success' => "Account created successfully"]);
        } else {
            echo json_encode(['failed' => "Account created failed"]);
        }
    }

    public function login()
    {
        if ($_SERVER['REQUEST_METHOD'] === 'GET') {
            die(json_encode("The GET request is not supported for this route"));
        }

        $json = file_get_contents('php://input');
        $data = json_decode($json, true);

        if ($data === null) {
            echo json_encode(['error' => "Invalid JSON data"]);
            return;
        }

        $username = $data['username'] ?? '';
        $password = md5($data['password']) ?? '';

        $database = new Connection;

        $sql = "SELECT * FROM users WHERE username = '$username' LIMIT 1";

        $query = $database->base_query($sql);

        $user = $query->fetch_assoc();


        if (!$user) {
            echo json_encode(['failed' => "No Username foundd for $username"]);
            return;
        }

        if ($this->isRateLimitExceeded($username)) {
            return;
        }

        if ($password !== $user['password']) {
            $this->incrementRateLimit($username);
            echo json_encode(['error' => "Invalid username or password"]);
            return;
        }

        $token = $this->generateToken($username);
        $rateLimitKey = 'login_attempts:' . $username;
        $this->redis->del($rateLimitKey);
        $this->redis->del('last_failed_login_time:' . $username);

        echo json_encode(['success' => "Login successful"]);

        return header('Location: /api/v1/user-profile?username=' . urlencode($username) . '&token=' . urlencode($token));
    }
}
