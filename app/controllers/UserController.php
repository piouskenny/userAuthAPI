<?php

namespace App\controllers;

use Predis\Client;
use App\database\Connection;

class UserController
{
    private $redis;
    private $allowedAttempts = 5;
    private $lockoutDuration = 180; // 3 minutes in seconds

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

        $json = file_get_contents('php://input');
        $data = json_decode($json, true);

        if ($data === null) {
            echo json_encode(['error' => "Invalid JSON data"]);
            return;
        }

        $username = $data['username'] ?? '';
        $password = $data['password'] ?? '';

        if ($username === '' || $password === '') {
            echo json_encode(['error' => "Username and password are required"]);
            return;
        }

        if ($this->isRateLimitExceeded($username)) {
            return;
        }

        if ($password !== 'correct_password') {
            $this->incrementRateLimit($username);
            echo json_encode(['error' => "Invalid username or password"]);
            return;
        }

        $token = $this->generateToken($username);

        echo json_encode(['success' => "Login successful", 'token' => $token]);
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
        $password = $data['password'] ?? '';

        if ($username === '' || $email === '' || $password === '') {
            echo json_encode(['error' => "Username, email, and password are required"]);
            return;
        }


        echo json_encode(['success' => "Account created successfully"]);
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
        $password = $data['password'] ?? '';

        if ($username === '' || $password === '') {
            echo json_encode(['error' => "Username and password are required"]);
            return;
        }

        if ($this->isRateLimitExceeded($username)) {
            return;
        }

        if ($password !== 'correct_password') {
            $this->incrementRateLimit($username);
            echo json_encode(['error' => "Invalid username or password"]);
            return;
        }

        $rateLimitKey = 'login_attempts:' . $username;
        $this->redis->del($rateLimitKey);
        $this->redis->del('last_failed_login_time:' . $username);

        echo json_encode(['success' => "Login successful"]);
    }
}
