<?php
require_once "_connect_to_database.php";
require_once "_middleware.php";
require_once "_request.php";

header("Content-Type: application/json");

require_role("admin");

$json_delete_data = json_request_body();

$response = ["status" => "error"];

try {
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
	} elseif (isset($json_delete_data['department_ids']) && !empty($json_delete_data['department_ids']) && is_array($json_delete_data['department_ids'])) { // Multi delete
		$placeholders = rtrim(str_repeat('?,', count($department_ids)), ',');
		$stmt = $pdo->prepare("DELETE FROM `departments` WHERE `department_id` in ($placeholders)");
		$stmt->execute($department_ids);
		$response["status"] = "success";
	}
	
} catch (Exception $e) {
	$response["message"] = $e->getMessage();
}

echo json_encode($response);
