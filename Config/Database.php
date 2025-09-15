<?php
// Archivo: Config/Database.php

class Database {
    private $host = "localhost";
    private $user = "root";
    private $password = "";
    private $dbname = "citas_medicas";
    public $conn;

    public function __construct() {
        $this->conn = new mysqli($this->host, $this->user, $this->password, $this->dbname);

        if ($this->conn->connect_error) {
            die(json_encode(["success" => false, "message" => "Error de conexión: " . $this->conn->connect_error]));
        }
    }

    public function closeConnection() {
        $this->conn->close();
    }
}
?>