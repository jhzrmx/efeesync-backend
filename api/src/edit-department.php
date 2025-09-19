<?php
require_once "_connect_to_database.php";
require_once "_middleware.php";
require_once "_request.php";

header("Content-Type: application/json");

require_role("admin");

$json_put_data = json_request_body();
require_params($json_put_data, ["new_department_code", "new_department_name"]);

$response = ["status" => "error"];

try {
	if (empty($id)) throw new Exception("Department ID is required.");
	$stmt = $pdo->prepare("UPDATE `departments` SET `department_code` = :new_department_code, `department_name` = :new_department_name WHERE `department_id` = :department_id");
	$stmt->bindParam(":department_id", $id);
	$stmt->bindParam(":new_department_code", $json_put_data["new_department_code"]);
	$stmt->bindParam(":new_department_name", $json_put_data["new_department_name"]);
	$stmt->execute();
	$response["status"] = "success";
} catch (Exception $e) {
	$response["message"] = $e->getMessage();
}

echo json_encode($response);
