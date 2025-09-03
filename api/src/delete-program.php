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
	if (isset($id)) { // Single delete
		$stmt = $pdo->prepare("SELECT * FROM `programs` WHERE `program_id` = :program_id");
		$stmt->bindParam(":program_id", $id);
		$stmt->execute();
		$rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
		if (count($rows) == 0) {
			$response["message"] = "Program does not exists";
			echo json_encode($response);
			exit();
		}
		$stmt = $pdo->prepare("DELETE FROM `programs` WHERE `program_id` = :program_id");
		$stmt->bindParam(":program_id", $id);
		$stmt->execute();
		$response["status"] = "success";
	} elseif (!empty($json_delete_data['program_ids']) && is_array($json_delete_data['program_ids'])) { // Multi delete
		$placeholders = rtrim(str_repeat('?,', count($program_ids)), ',');
		$stmt = $pdo->prepare("DELETE FROM `programs` WHERE `program_id` in ($placeholders)");
		$stmt->execute($program_ids);
		$response["status"] = "success";
	}
} catch (Exception $e) {
	$response["message"] = $e->getMessage();
}

echo json_encode($response);
