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

$current_role = current_role();
try {
	$sql = "SELECT s.student_id, s.student_number_id, s.student_section, s.last_active,
		u.user_id, u.institutional_email, u.first_name, u.last_name, u.middle_initial, u.picture,
		r.role_id, r.role_name FROM students s
		JOIN users u ON u.user_id = s.user_id
		JOIN roles r ON r.role_id = u.role_id";

	if (isset($id)) {
		$sql .= " WHERE s.student_id = :student_id";
		$stmt = $pdo->prepare($sql);
		$stmt->bindParam(":student_id", $id);
	} elseif (isset($student_number)) {
		$sql .= " WHERE s.student_number_id = :student_number";
		$stmt = $pdo->prepare($sql);
		$stmt->bindParam(":student_number", $student_number);
	} else {
		$stmt = $pdo->prepare($sql);
	}
	
	$stmt->execute();
	$data = $stmt->fetchAll(PDO::FETCH_ASSOC);
	
	$response["status"] = "success";
	$response["data"] = $data;
} catch (Exception $e) {
	http_response_code(500);
	$response["message"] = $e->getMessage();
}

echo json_encode($response);
