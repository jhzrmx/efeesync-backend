<?php
require_once "_connect_to_database.php";
require_once "_middleware.php";

header("Content-Type: application/json");

require_role(["admin", "treasurer"]);

$response = ["status" => "error"];

try {
	$sql = "SELECT * FROM STUDENTS";
	$stmt = $pdo->prepare($sql);
	$stmt->execute();
	$data = $stmt->fetchAll(PDO::FETCH_ASSOC);

	$response["status"] = "success";
	$response["data"] = $data[0];
} catch (Exception $e) {
	$response["message"] = $e->getMessage();
}

echo json_encode($response);