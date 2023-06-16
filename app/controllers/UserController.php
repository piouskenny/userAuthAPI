<?php

namespace App\controllers;

use App\database\Connection;

class UserController
{

    public $database;

    public function profile()
    {
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            die(json_encode("THE GET REQUEST IS NOT SUPPORTED FOR THIS ROUTE"));
        }

        $database = new Connection;

        echo json_encode(['success' => "Profile Pages"]);
    }

    public function signup()
    {
        if ($_SERVER['REQUEST_METHOD'] === 'GET') {
            die(json_encode("THE GET REQUEST IS NOT SUPPORTED FOR THIS ROUTE"));
        }

        $json = file_get_contents('php://input');

        $data = json_decode($json, true);

        if ($data === null) {
            echo json_encode(['error' => "Data cannot be empty"]);
            return;
        }

        $name = $data['name'];
        $email = $data['email'];

        echo json_encode($name . " " . $email);
    }

    public function login()
    {
        if ($_SERVER['REQUEST_METHOD'] === 'GET') {
            echo json_encode("THE GET REQUEST IS NOT SUPPORTED FOR THIS ROUTE");
        }
        // var_dump("This is the Login");

        echo json_encode("login");
    }
}
