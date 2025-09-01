<?php 
require_once "_connect_to_database.php";
require_once "_current_role.php";

header("Content-Type: application/json");

if (current_role() == null) {
	http_response_code(403);
	echo json_encode(["status" => "error", "message" => "Forbidden"]);
	exit();
}

$response = [];
$response["status"] = "error";

try {
	if (isset($id)) {
		$stmt = $pdo->prepare("SELECT * FROM `departments` WHERE `department_id` = :department_id");
		$stmt->bindParam(":department_id", $id);
	} elseif (isset($code)) {
		$stmt = $pdo->prepare("SELECT * FROM `departments` WHERE `department_code` = :department_code");
		$stmt->bindParam(":department_code", $code);
	} else {
		$stmt = $pdo->prepare("SELECT * FROM `departments`");
	}
	$stmt->execute();
	$data = $stmt->fetchAll(PDO::FETCH_ASSOC);
	$response["status"] = "success";
	$response["data"] = $data;
} catch (Exception $e) {
	$response["message"] = $e->getMessage();
}

echo json_encode($response);
