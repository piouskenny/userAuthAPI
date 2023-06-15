<?php

namespace App\database;


class Connection
{

    private $conn;
    private $host;
    private $dbname;

    private $username;

    private $password;


    public function __construct()
    {
        $this->host = "localhost";
        $this->username = "root";
        $this->password = "";
        $this->dbname = "user_auth_api";

        $this->conn = mysqli_connect($this->host, $this->username, $this->password, $this->dbname);

        if ($this->conn->connect_error) {
            die("Connection failed: " . $this->conn->connect_error);
        }
    }


    public function base_query($sql)
    {
        $result = $this->conn->query($sql);

        if (!$result) {
            die("Query failed: " . $this->conn->error);
        } else {
            return $result;
        }
    }
}
