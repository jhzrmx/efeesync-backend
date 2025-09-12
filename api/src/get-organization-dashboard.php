<?php 
require_once "_connect_to_database.php";
require_once "_current_role.php";

header("Content-Type: application/json");

if (!is_current_role_in(["admin", "treasurer"])) {
	http_response_code(403);
	echo json_encode(["status" => "error", "message" => "Forbidden"]);
	exit();
}

$response = [];
$response["status"] = "error";

try {
	$sql = "SELECT * FROM STUDENTS";
	$stmt = $pdo->prepare($sql);
	$stmt->execute();
	$data = $stmt->fetchAll(PDO::FETCH_ASSOC);

	$response["status"] = "success";
	$response["data"] = $data[0];
} catch (Exception $e) {
	$response["message"] = $e->getMessage();
}

echo json_encode($response);