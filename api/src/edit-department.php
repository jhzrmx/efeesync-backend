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

$json_put_data = json_decode(file_get_contents("php://input"), true);

$required_parameters = ["new_department_code", "new_department_name"];

foreach ($required_parameters as $param) {
	if (empty($json_put_data[$param])) {
		echo json_encode(["status" => "error", "message" => snake_to_capital($param)." is required"]);
		exit();
	}
}

$response = [];
$response["status"] = "error";

try {
	if ($empty($id)) throw new Exception("Department ID is required.");
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
