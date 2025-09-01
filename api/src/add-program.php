<?php 
require_once "_connect_to_database.php";
require_once "_current_role.php";
require_once "_snake_to_capital.php";

header("Content-Type: application/json");

if (!is_current_role_in(['admin'])) {
	http_response_code(403);
	echo json_encode(["status" => "error", "message" => "Forbidden"]);
	exit();
}

$json_post_data = json_decode(file_get_contents("php://input"), true);

$required_parameters = ["program_code", "program_name", "department_code"];

foreach ($required_parameters as $param) {
	if (empty($json_post_data[$param])) {
		http_response_code(400);
		echo json_encode(["status" => "error", "message" => snake_to_capital($param)." is required"]);
		exit();
	}
}

$response = [];
$response["status"] = "error";

try {
	$stmt = $pdo->prepare("SELECT * FROM `departments` WHERE `department_code` = :department_code");
	$stmt->bindParam(":department_code", $json_post_data["department_code"]);
	$stmt->execute();
	$rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
	if (count($rows) == 0) {
		$response["message"] = "Department does not exists";
		echo json_encode($response);
		exit();
	}
	$json_post_data["department_id"] = $rows[0]["department_id"];
	
	$stmt = $pdo->prepare("INSERT INTO `programs` (`program_code`, `program_name`, `department_id`) VALUES (:program_code, :program_name, :department_id)");
	$stmt->bindParam(":program_code", $json_post_data["program_code"]);
	$stmt->bindParam(":program_name", $json_post_data["program_name"]);
	$stmt->bindParam(":department_id", $json_post_data["department_id"]);
	$stmt->execute();
	$response["status"] = "success";
} catch (Exception $e) {
	if (strpos($e->getMessage(), "Duplicate entry")) {
		$response["message"] = "Department Code or Name already exists";
	}
}

echo json_encode($response);
