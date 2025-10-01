<?php
require_once "_connect_to_database.php";
require_once "_middleware.php";
require_once "_request.php";

header("Content-Type: application/json");

require_role("admin");

$json_post_data = json_request_body();
require_params($json_post_data, ["organization_code", "organization_name" /*, "department_code"*/]);

$response = ["status" => "error"];

try {
	$dept_code = strtoupper(trim($json_post_data["department_code"]));
	$org_code = strtoupper(trim($json_post_data["organization_code"]));
	$org_name = ucwords(trim($json_post_data["organization_name"]));

	$stmt = $pdo->prepare("SELECT * FROM `departments` WHERE `department_code` = :department_code");
	$stmt->bindParam(":department_code", $dept_code);
	$stmt->execute();
	$rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
	if (count($rows) == 0) {
		// Allow null values for departments
		$json_post_data["department_id"] = NULL;
		/*$response["message"] = "Department does not exists";
		echo json_encode($response);
		exit();
		*/
	} else {
		$json_post_data["department_id"] = $rows[0]["department_id"];
	}

	$stmt = $pdo->prepare("INSERT INTO organizations (organization_code, organization_name, department_id) VALUES (?, ?, ?)");
	$stmt->execute([$org_code, $org_name, $json_post_data["department_id"]]);

	$response["status"] = "success";
	$response["organization_id"] = $pdo->lastInsertId();
	
} catch (Exception $e) {
	http_response_code(400);
	if (strpos($e->getMessage(), "Duplicate entry")) {
		$response["message"] = "Organization Code or Name already exists";
	} else {
		$response["message"] = $e->getMessage();
	}
}

echo json_encode($response);
