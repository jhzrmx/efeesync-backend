<?php 
require_once "_connect_to_database.php";
require_once "_current_role.php";

header("Content-Type: application/json");

if (!is_current_role_in(['admin'])) {
	http_response_code(403);
	echo json_encode(["status" => "error", "message" => "Forbidden"]);
	exit();
}

$json_delete_data = json_decode(file_get_contents("php://input"), true);

$response = [];
$response["status"] = "error";

try {
	$department_ids = $json_delete_data['department_ids'];
	if (isset($id)) { // Single delete
		$stmt = $pdo->prepare("SELECT * FROM `departments` WHERE `department_id` = :department_id");
		$stmt->bindParam(":department_id", $id);
		$stmt->execute();
		$rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
		if (count($rows) == 0) {
			$response["message"] = "Department does not exists";
			echo json_encode($response);
			exit();
		}
		$stmt = $pdo->prepare("DELETE FROM `departments` WHERE `department_id` = :department_id");
		$stmt->bindParam(":department_id", $id);
		$stmt->execute();
		$response["status"] = "success";
	} elseif (!empty($department_ids) && is_array($department_ids)) { // Multi delete
		$placeholders = rtrim(str_repeat('?,', count($department_ids)), ',');
		$stmt = $pdo->prepare("DELETE FROM `departments` WHERE `department_id` in ($placeholders)");
		$stmt->execute($department_ids);
		$response["status"] = "success";
	}
	
} catch (Exception $e) {
	$response["message"] = $e->getMessage();
}

echo json_encode($response);
