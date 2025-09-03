<?php 
require_once "_connect_to_database.php";
require_once "_current_role.php";

header("Content-Type: application/json");

if (current_role() == null) {
	http_response_code(403);
	echo json_encode(["status" => "error", "message" => "Forbidden"]);
	exit();
}

$response = [];
$response["status"] = "error";

try {
	$sql = "SELECT `organization_id`, `organization_code`, `organization_name`, `organization_logo`, `organizations`.`department_id`, `department_code` FROM `organizations` LEFT JOIN `departments` ON `departments`.`department_id` = `organizations`.`department_id`";
	if (isset($id)) {
		$stmt = $pdo->prepare($sql . " WHERE `organization_id` = :organization_id");
		$stmt->bindParam(":organization_id", $id);
	} elseif (isset($code)) {
		$stmt = $pdo->prepare($sql . " WHERE `organization_code` = :organization_code");
		$stmt->bindParam(":organization_code", $code);
	} else {
		$stmt = $pdo->prepare($sql);
	}
	$stmt->execute();
	$data = $stmt->fetchAll(PDO::FETCH_ASSOC);
	
	$response["status"] = "success";
	$response["data"] = $data;
} catch (Exception $e) {
	$response["message"] = $e->getMessage();
}

echo json_encode($response);
