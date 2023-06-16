<?php

namespace App\controllers;

use App\database\Connection;

class UserController
{

    public $database;

    public function profile()
    {
        $database = new Connection;
        echo json_encode("profile Page");
    }

    public function signup()
    {
        echo json_encode("Signup");

        $jsonPayload = file_get_contents('php://input');

        $data = json_decode($jsonPayload, true);

        echo $data;
    }

    public function login()
    {
        // var_dump("This is the Login");

        echo json_encode("login");
    }
}
