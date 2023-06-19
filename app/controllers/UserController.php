<?php

namespace App\controllers;

require __DIR__ . "/../../vendor/autoload.php";

use App\database\Connection;
use App\Services\RateLimitServices;
use Predis\Client;
use App\Services\RedisCall;

class UserController
{
    private $RateLimitService;
    private $redis;


    public function __construct()
    {
        $redisOptions = [
            'scheme' => 'tcp',
            'host' => '127.0.0.1',
            'port' => 6379,
        ];

        $this->redis = new Client($redisOptions);
    }

    public function profile()
    {

        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            die(json_encode("The GET request is not supported for this route"));
        }


        $username = $_GET['username'] ?? "";
        $token = $_GET['token'] ?? "";

        if ($username == "" || $token == "") {
            echo json_encode(["error" => "sorry you have to login to access this page"]);
            return;
        }

        $database = new Connection;

        $sql = "SELECT * FROM users WHERE username = '$username' LIMIT 1";

        $query = $database->base_query($sql);

        $user = $query->fetch_assoc();

        $this->RateLimitService = new RateLimitServices;


        $username = $user['username'] ?? '';
        $email = $user['email'] ?? '';
        $storedToken = $this->redis->get('user_token:' . $username);


        if ($this->RateLimitService->isRateLimitExceeded($username)) {
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

        $this->RateLimitService = new RateLimitServices;

        if ($this->RateLimitService->isRateLimitExceeded($username)) {
            return;
        }

        if ($password !== $user['password']) {
            $this->RateLimitService->incrementRateLimit($username);
            echo json_encode(['error' => "Invalid username or password"]);
            return;
        }

        $token = $this->RateLimitService->generateToken($username);
        $rateLimitKey = 'login_attempts:' . $username;

        $this->redis->del($rateLimitKey);
        $this->redis->del('last_failed_login_time:' . $username);

        echo json_encode(['success' => "Login successful"]);

        return header('Location: /api/v1/user-profile?username=' . urlencode($username) . '&token=' . urlencode($token));
    }
}
