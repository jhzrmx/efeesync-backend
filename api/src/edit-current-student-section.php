<?php
require_once "_connect_to_database.php";
require_once "_generate_email.php";
require_once "_middleware.php";
require_once "_request.php";

header("Content-Type: application/json");

require_login();

$json = json_request_body();

$response = ["status" => "error"];

try {
	$current_user_id = current_jwt_payload()['user_id'];

    $sql = "SELECT 
                s.student_id, 
                s.student_current_program, 
                s.student_section
            FROM users u
            JOIN students s ON s.user_id = u.user_id
            WHERE u.user_id = ?";
    $stmt = $pdo->prepare($sql);
    $stmt->execute([$current_user_id]);
    $student = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$student || !$student['student_id']) {
        throw new Exception("This user is not a student");
    }

    $student_id = $student["student_id"];

    $student_section = $json["student_section"] ?? null;

    if ($student_section) {
    	$stmt = $pdo->prepare("UPDATE students SET student_section=? WHERE student_id=?");
		$stmt->execute([$student_section, $student_id]);
    }

    echo json_encode([
		"status" => "success",
		"new_section" => $student_section
	]);

} catch (Exception $e) {
	http_response_code(500);
	echo json_encode(["status" => "error", "message" => $e->getMessage()]);
}