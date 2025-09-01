<?php
require_once "_connect_to_database.php";
require_once "_current_role.php";

header("Content-Type: application/json");

if (!is_current_role_in(["admin", "treasurer"])) {
	http_response_code(403);
	echo json_encode(["status" => "error", "message" => "Forbidden"]);
	exit();
}

$json = json_decode(file_get_contents("php://input"), true);

$response = [];
$response["status"] = "error";

try {
	if (isset($id)) {
		// Single delete using route param
		$stmt = $pdo->prepare("DELETE FROM students WHERE student_id = ?");
		$stmt->execute([$id]);

		$response["status"] = "success";
		$response["deleted_student_id"] = $id;

	} elseif (!empty($json["id"]) && is_array($json["id"])) {
		// Multi delete from request body
		$placeholders = implode(",", array_fill(0, count($json["id"]), "?"));
		$stmt = $pdo->prepare("DELETE FROM students WHERE student_id IN ($placeholders)");
		$stmt->execute($json["id"]);

		$response["status"] = "success";
		$response["deleted_ids"] = $json["id"];

	} else {
		http_response_code(400);
		$response["message"] = "No ID(s) provided for deletion.";
	}
} catch (Exception $e) {
	http_response_code(500);
	$response["message"] = $e->getMessage();
}

echo json_encode($response);