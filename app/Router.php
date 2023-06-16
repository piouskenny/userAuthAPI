<?php

namespace App;

class Router
{
    public array $getRoutes = [];
    public array $postRoutes = [];

    public function get($url, $fn)
    {
        $this->getRoutes[$url] = $fn;
    }

    public function post($url, $fn)
    {
        $this->postRoutes[$url] = $fn;
    }

    public function  resolve()
    {
        $current_url = $_SERVER['PATH_INFO'] ?? '/';

        $method = $_SERVER['REQUEST_METHOD'];

        if ($method === 'GET') {
            $fn = $this->getRoutes[$current_url] ?? null;
        } elseif ($method === 'POST') {
            $fn = $this->postRoutes[$current_url] ?? null;
        }


        if ($fn) {
            call_user_func($fn);
        } else {
            echo "Page Not found";
        }
    }
}
