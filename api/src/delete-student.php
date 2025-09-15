<?php
require_once "_connect_to_database.php";
require_once "_middleware.php";
require_once "_request.php";

header("Content-Type: application/json");

require_role(["admin", "treasurer"]);

$json = json_request_body();

$response = ["status" => "error"];

try {
	if (isset($id)) {
		// Single delete using route param
		$stmt = $pdo->prepare("DELETE FROM users WHERE user_id IN (SELECT user_id FROM students WHERE student_id = ?)");
		$stmt->execute([$id]);

		$response["status"] = "success";
		$response["deleted_student_id"] = $id;

	} elseif (!empty($json["id"]) && is_array($json["id"])) {
		// Multi delete from request body
		$placeholders = implode(",", array_fill(0, count($json["id"]), "?"));
		$stmt = $pdo->prepare("DELETE FROM users WHERE user_id IN (SELECT user_id FROM students WHERE student_id IN ($placeholders))");
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