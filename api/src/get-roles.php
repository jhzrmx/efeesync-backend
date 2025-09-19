<?php
require_once "_connect_to_database.php";
require_once "_middleware.php";

header("Content-Type: application/json");

require_login();

$response = ["status" => "error"];

try {
	$stmt = $pdo->prepare("SELECT * from `roles`");
	$stmt->execute();
	$data = $stmt->fetchAll(PDO::FETCH_ASSOC);
	$response["status"] = "success";
	$response["data"] = $data;
} catch (Exception $e) {
	$response["message"] = $e->getMessage();
}

echo json_encode($response);
