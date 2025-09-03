<?php
require_once "_connect_to_database.php";
require_once "_current_role.php";
require_once "_snake_to_capital.php";

header("Content-Type: application/json");

if (!is_current_role_in(['admin', 'treasurer'])) {
	http_response_code(403);
	echo json_encode(["status" => "error", "message" => "Forbidden"]);
	exit();
}

$json_put_data = json_decode(file_get_contents("php://input"), true);

$required_parameters = ["new_organization_code", "new_organization_name" /*, "new_department_code"*/];

foreach ($required_parameters as $param) {
	if (empty($json_put_data[$param])) {
		echo json_encode(["status" => "error", "message" => snake_to_capital($param)." is required"]);
		exit();
	}
}

$response = ["status" => "error"];

try {
	if (!isset($id)) throw new Exception("ID is required");

	$dept_code = isset($json_put_data["new_department_code"]) ? strtoupper(trim($json_put_data["new_department_code"])) : NULL;
	$org_code = strtoupper(trim($json_put_data["new_organization_code"]));
	$org_name = ucwords(trim($json_put_data["new_organization_name"]));

	$stmt = $pdo->prepare("SELECT * FROM `departments` WHERE `department_code` = :department_code");
	$stmt->bindParam(":department_code", $dept_code);
	$stmt->execute();
	$rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
	if (count($rows) == 0) {
		// Allow null values for departments
		$json_put_data["department_id"] = NULL;
		/*$response["message"] = "Department does not exists";
		echo json_encode($response);
		exit();
		*/
	} else {
		$json_put_data["department_id"] = $rows[0]["department_id"];
	}

	$stmt = $pdo->prepare("UPDATE organizations SET organization_code = ?, organization_name = ?, department_id = ? WHERE organization_id = ?");
	$stmt->execute([$org_code, $org_name, $json_put_data["department_id"], $id]);

	$response["status"] = "success";
	$response["message"] = "Organization updated successfully.";

} catch (Exception $e) {
	http_response_code(400);
	$response["message"] = $e->getMessage();
}

echo json_encode($response);
