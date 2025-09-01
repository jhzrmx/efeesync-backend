<?php 

header("Content-Type: application/json");

setcookie("basta", "", time(), "/");

echo json_encode(["status" => "success", "message" => "Logout successful"]);