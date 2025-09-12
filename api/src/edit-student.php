<?php
require_once "_connect_to_database.php";
require_once "_current_role.php";
require_once "_generate_email.php";

header("Content-Type: application/json");

if (!is_current_role_in(["admin", "treasurer"])) {
	http_response_code(403);
	echo json_encode(["status" => "error", "message" => "Forbidden"]);
	exit();
}

$student_id = isset($id) ? $id : null;
if (!$student_id) {
	http_response_code(400);
	echo json_encode(["status" => "error", "message" => "Missing student ID"]);
	exit();
}

$json = json_decode(file_get_contents("php://input"), true);

try {
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
	if ($sec || $prog) {
		$stmt = $pdo->prepare("UPDATE students SET student_section=?, student_current_program=? WHERE student_id=?");
		$stmt->execute([$sec, $prog, $student_id]);
	}

	// Insert new program to `student_programs_taken`
	if ($prog) {
		$stmt = $pdo->prepare("INSERT INTO student_programs_taken (student_id, program_id, start_date, shift_status) VALUES (?, ?, CURDATE(), 'Approved')");
		$stmt->execute([$student_id, $prog]);
	}

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
