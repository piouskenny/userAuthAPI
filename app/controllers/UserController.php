<?php

namespace App\controllers;

use App\database\Connection;

class UserController
{
    private $database;
    private $allowedAttempts = 5;
    private $lockoutDuration = 180; // 3 minutes in seconds

    private function isRateLimitExceeded($username)
    {
        $rateLimitKey = 'profile_attempts:' . $username;
        $attemptCount = (int) redis_get($rateLimitKey);

        if ($attemptCount >= $this->allowedAttempts) {
            $remainingTime = $this->lockoutDuration - (time() - redis_get('last_failed_profile_time:' . $username));
            if ($remainingTime > 0) {
                header("HTTP/1.1 429 Too Many Requests");
                echo json_encode(['error' => "Too many failed profile attempts. Please try again in $remainingTime seconds."]);
                return true;
            } else {
                redis_del($rateLimitKey);
                redis_del('last_failed_profile_time:' . $username);
            }
        }

        return false;
    }

    private function incrementRateLimit($username)
    {
        $rateLimitKey = 'profile_attempts:' . $username;

        if (redis_exists($rateLimitKey)) {
            redis_incr($rateLimitKey);
        } else {
            redis_set($rateLimitKey, 1, $this->lockoutDuration);
        }

        redis_set('last_failed_profile_time:' . $username, time());
    }

    private function generateToken($username)
    {
        // Generate a custom token here (e.g., using a library like JWT)
        $token = bin2hex(random_bytes(16));

    
        redis_set('user_token:' . $username, $token, $this->lockoutDuration);

        return $token;
    }

    public function profile()
    {
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            die(json_encode("THE GET REQUEST IS NOT SUPPORTED FOR THIS ROUTE"));
        }

        $database = new Connection;

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
            die(json_encode("THE GET REQUEST IS NOT SUPPORTED FOR THIS ROUTE"));
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

        $database = new Connection;

        $sql = "INSERT INTO users (username, email, password) VALUES ('$username', '$email', '$password')";
        $query =  $database->base_query($sql);

        if ($query) {
            echo json_encode(['success' => "Account created successfully"]);
        } else {
            echo json_encode(['failed' => "Failed to send data to the database"]);
        }
    }

    public function login()
    {
        if ($_SERVER['REQUEST_METHOD'] === 'GET') {
            die(json_encode("THE GET REQUEST IS NOT SUPPORTED FOR THIS ROUTE"));
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
        redis_del($rateLimitKey);
        redis_del('last_failed_login_time:' . $username);

        echo json_encode(['success' => "Login successful"]);
    }
}