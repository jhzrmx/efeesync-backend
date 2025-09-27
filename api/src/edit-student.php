<?php
require_once "_connect_to_database.php";
require_once "_generate_email.php";
require_once "_middleware.php";
require_once "_request.php";

header("Content-Type: application/json");

require_role(["admin", "treasurer"]);

$json = json_request_body();

$response = ["status" => "error"];

try {
	$student_id = isset($id) ? $id : null;
	if (!$student_id) throw new Exception("Missing student ID");
	// Get current student and user_id
	$stmt = $pdo->prepare("SELECT * FROM students WHERE student_id = ?");
	$stmt->execute([$student_id]);
	$student = $stmt->fetch(PDO::FETCH_ASSOC);

	if (!$student) {
		http_response_code(404);
		echo json_encode(["status" => "error", "message" => "Student not found"]);
		exit();
	}

	$user_id = $student["user_id"];

	$student_number_id = $json["student_number_id"] ?? null;
	$first_name = $json["first_name"] ?? null;
	$last_name = $json["last_name"] ?? null;
	$mid = $json["middle_initial"] ?? null;
	$sec = $json["student_section"] ?? null;
	$prog = $json["student_current_program"] ?? null;

	$pdo->beginTransaction();

	// Update users table
	if ($first_name && $last_name) {
		$email = generate_email($first_name, $last_name);

		// Check if new email already exists (and not from this user)
		$stmt = $pdo->prepare("SELECT user_id FROM users WHERE institutional_email = ? AND user_id != ?");
		$stmt->execute([$email, $user_id]);

		if ($stmt->rowCount() > 0) {
			echo json_encode(["status" => "error", "message" => "Email already exists: $email"]);
			exit();
		}

		$stmt = $pdo->prepare("UPDATE users SET first_name=?, last_name=?, middle_initial=?, institutional_email=? WHERE user_id=?");
		$stmt->execute([$first_name, $last_name, $mid, $email, $user_id]);
	}

	// Update student record
	if ($sec) {
		$stmt = $pdo->prepare("UPDATE students SET student_number_id=?, student_section=? WHERE student_id=?");
		$stmt->execute([$student_number_id, $sec, $student_id]);
	}
	if ($prog) {
		$stmt = $pdo->prepare("UPDATE students SET student_current_program=? WHERE student_id=?");
		$stmt->execute([$prog, $student_id]);
	}

	// Update program history
	/*if ($prog) {
		$stmt = $pdo->prepare("UPDATE student_programs_taken SET program_id = ? WHERE student_id = ?");
		$stmt->execute([$prog, $student_id]);
	}*/

	$pdo->commit();

	echo json_encode([
		"status" => "success",
		"updated_student_id" => $student_id,
		"new_email" => isset($email) ? $email : "unchanged"
	]);

} catch (Exception $e) {
	$pdo->rollBack();
	http_response_code(500);
	echo json_encode(["status" => "error", "message" => $e->getMessage()]);
}
