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

$required_parameters = ["department_code", "department_name", "department_color"];

foreach ($required_parameters as $param) {
	if (empty($json_post_data[$param])) {
		echo json_encode(["status" => "error", "message" => snake_to_capital($param)." is required"]);
		exit();
	}
}

$response = [];
$response["status"] = "error";

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
