<?php 
require_once "_connect_to_database.php";
require_once "_middleware.php";
require_once "_request.php";

header("Content-Type: application/json");

require_role("admin");

$json_post_data = json_request_body();
require_params($json_post_data, ["department_code", "department_name", "department_color"]);

$response = ["status" => "error"];

try {
	$dept_code = strtoupper(trim($json_post_data["department_code"]));
	$dept_name = ucwords(trim($json_post_data["department_name"]));
	$dept_color = trim($json_post_data["department_color"]);
	$stmt = $pdo->prepare("INSERT INTO `departments` (`department_code`, `department_name`, `department_color`) VALUES (:department_code, :department_name, :department_color)");
	$stmt->bindParam(":department_code", $dept_code);
	$stmt->bindParam(":department_name", $dept_name);
	$stmt->bindParam(":department_color", $dept_color);
	$stmt->execute();
	$response["status"] = "success";
} catch (Exception $e) {
	if (strpos($e->getMessage(), "Duplicate entry")) {
		$response["message"] = "Department Code or Name already exists";
	} else {
		$response["message"] = $e->getMessage();
	}
}

echo json_encode($response);
