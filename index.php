<?php

require "vendor/autoload.php";

use App\controllers\UserController;

use App\Router;

$usercontroller = new UserController;

// $usercontroller->profile();


$router = new Router();

$usercontroller = new UserController;

$router->get('/api/v1/', [new UserController, 'profile']);

$router->post('/api/v1/signup/', [new UserController, 'signup']);

$router->post('/api/v1/login/', [new UserController, 'login']);
$router->resolve();
