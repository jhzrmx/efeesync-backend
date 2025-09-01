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

$required_parameters = ["organization_code", "organization_name", "organization_color", "department_code"];

foreach ($required_parameters as $param) {
	if (empty($json_post_data[$param])) {
		echo json_encode(["status" => "error", "message" => snake_to_capital($param)." is required"]);
		exit();
	}
}

$response = [];
$response["status"] = "error";

try {
	$stmt = $pdo->prepare("SELECT * FROM `departments` WHERE `department_code` = :department_code");
	$stmt->bindParam(":department_code", $json_post_data["department_code"];);
	$stmt->execute();
	$rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
	if (count($rows) == 0) {
		$response["message"] = "Department does not exists";
		echo json_encode($response);
		exit();
	}s
	$json_post_data["department_id"] = $rows[0]["department_id"];

	$stmt = $pdo->prepare("INSERT INTO organizations (organization_code, organization_name, organization_color, department_id) VALUES (?, ?, ?, ?)");
	$stmt->execute([$json_post_data["organization_code"], $json_post_data["organization_name"], $json_post_data["organization_color"], $json_post_data["department_id"]]);

	$response["status"] = "success";
	$response["message"] = "Organization created successfully.";

} catch (Exception $e) {
	http_response_code(400);
	$response["message"] = $e->getMessage();
}

echo json_encode($response);
