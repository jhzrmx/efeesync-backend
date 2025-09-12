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

$required_parameters = ["new_program_code", "new_program_name", "new_department_code"];

foreach ($required_parameters as $param) {
	if (empty($json_put_data[$param])) {
		http_response_code(400);
		echo json_encode(["status" => "error", "message" => snake_to_capital($param)." is required"]);
		exit();
	}
}

$response = [];
$response["status"] = "error";

try {
	if (!isset($id)) throw new Exception("ID is required");
	
	$stmt = $pdo->prepare("SELECT * FROM `departments` WHERE `department_code` = :department_code");
	$stmt->bindParam(":department_code", $json_put_data["new_department_code"]);
	$stmt->execute();
	$rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
	if (count($rows) == 0) {
		$response["message"] = "Department does not exists";
		echo json_encode($response);
		exit();
	}
	$json_put_data["new_department_id"] = $rows[0]["department_id"];
	
	$stmt = $pdo->prepare("UPDATE `programs` SET `program_code` = :new_program_code, `program_name` = :new_program_name, `department_id` = :new_department_id WHERE `program_id` = :program_id");
	$stmt->bindParam(":program_id", $id);
	$stmt->bindParam(":new_program_code", $json_put_data["new_program_code"]);
	$stmt->bindParam(":new_program_name", $json_put_data["new_program_name"]);
	$stmt->bindParam(":new_department_id", $json_put_data["new_department_id"]);
	$stmt->execute();
	$response["status"] = "success";
} catch (Exception $e) {
	$response["message"] = $e->getMessage();
}

echo json_encode($response);
