<?php

namespace App\Services;


require __DIR__ . "/../../vendor/autoload.php";

use Predis\Client;

class RateLimitServices
{
    public $redis;
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

    public function isRateLimitExceeded($username)
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

    public function incrementRateLimit($username)
    {
        $rateLimitKey = 'profile_attempts:' . $username;

        if ($this->redis->exists($rateLimitKey)) {
            $this->redis->incr($rateLimitKey);
        } else {
            $this->redis->set($rateLimitKey, 1, 'EX', $this->lockoutDuration);
        }

        $this->redis->set('last_failed_profile_time:' . $username, time());
    }

    public function generateToken($username)
    {
        // Generate a custom token here (e.g., using a library like JWT)
        $token = bin2hex(random_bytes(16));

        $this->redis->set('user_token:' . $username, $token, 'EX', $this->lockoutDuration);

        return $token;
    }
}
