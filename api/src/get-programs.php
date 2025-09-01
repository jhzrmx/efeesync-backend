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
	$sql = "SELECT * FROM `programs` JOIN `departments` ON
			(`programs`.`department_id` = `departments`.`department_id`)";
	if (isset($id)) {
		$sql .= " WHERE `program_id` = :program_id";
		$stmt = $pdo->prepare($sql);
		$stmt->bindParam(":program_id", $id);
	} elseif (isset($code)) {
		$sql .= " WHERE `program_code` = :program_code";
		$stmt = $pdo->prepare($sql);
		$stmt->bindParam(":program_code", $code);
	} elseif (isset($department_id)) {
		$sql .= " WHERE `programs`.`department_id` = :department_id";
		$stmt = $pdo->prepare($sql);
		$stmt->bindParam(":department_id", $department_id);
	} elseif (isset($department_code)) {
		$sql .= " WHERE `department_code` = :department_code";
		$stmt = $pdo->prepare($sql);
		$stmt->bindParam(":department_code", $department_code);
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
