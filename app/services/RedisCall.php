<?php


namespace App\Services;

use Predis\Client;

class RedisCall
{
    public $redis;


    public function __invoke()
    {
        $redisOptions = [
            'scheme' => 'tcp',
            'host' => '127.0.0.1',
            'port' => 6379,
        ];

        $this->redis = new Client($redisOptions);
    }
}
