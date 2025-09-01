<?php

try {
    $pdo = new PDO("mysql:host=".$_ENV["EFEESYNC_MYSQL_HOST"].";dbname=".$_ENV["EFEESYNC_MYSQL_DBNAME"], $_ENV["EFEESYNC_MYSQL_USERNAME"], $_ENV["EFEESYNC_MYSQL_PASSWORD"]);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch (PDOException $e) {
    echo json_encode(["status" => "error", "message" => "Database connection failed"]);
    exit();
}