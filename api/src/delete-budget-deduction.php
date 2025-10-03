<?php
require_once "_connect_to_database.php";
require_once "_middleware.php";

header("Content-Type: application/json");

require_role(["treasurer"]);

$response = ["status" => "error"];

try {
	if (!isset($id)) throw new Exception("Missing identifier in URL");

	$stmt = $pdo->prepare("DELETE FROM budget_deductions WHERE budget_deduction_id = ?");
	$stmt->execute([$id]);

	$response["status"] = "success";
	$response["message"] = "Deduction deleted successfully.";
	
} catch (Exception $e) {
	http_response_code(400);
	$response["message"] = $e->getMessage();
}

echo json_encode($response);
