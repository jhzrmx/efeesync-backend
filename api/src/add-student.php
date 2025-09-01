<?php
require_once "_connect_to_database.php";
require_once "_current_role.php";
require_once "_generate_email.php";
require_once "_snake_to_capital.php";

header("Content-Type: application/json");

if (!is_current_role_in(["admin", "treasurer"])) {
	http_response_code(403);
	echo json_encode(["status" => "error", "message" => "Forbidden"]);
	exit();
}

$json = json_decode(file_get_contents("php://input"), true);

$required = [
	"student_number_id",
	"user_id",
	"student_section",
	"first_name",
	"last_name",
	"student_current_program"
];

foreach ($required as $field) {
	if (empty($json[$field])) {
		http_response_code(400);
		echo json_encode(["status" => "error", "message" => snake_to_capital($field)." is required"]);
		exit();
	}
}

$email = generate_email($json["first_name"], $json["last_name"]);
$password_raw = "cbsua-" . $json["student_number_id"];
$hashed_password = password_hash($password_raw, PASSWORD_DEFAULT);

try {
	// Check for duplicate email
	$stmt = $pdo->prepare("SELECT user_id FROM users WHERE institutional_email = ?");
	$stmt->execute([$email]);
	if ($stmt->rowCount() > 0) {
		echo json_encode(["status" => "error", "message" => "Email already exists: $email"]);
		exit();
	}

	$pdo->beginTransaction();

	// Insert into users
	$stmt = $pdo->prepare("INSERT INTO users (institutional_email, password, role_id, last_name, first_name, middle_initial) VALUES (?, ?, ?, ?, ?, ?)");
	$stmt->execute([
		$email,
		$hashed_password,
		103, // student
		$json["last_name"],
		$json["first_name"],
		$json["middle_initial"]
	]);

	$user_id = $pdo->lastInsertId();

	// Insert into students
	$stmt = $pdo->prepare("INSERT INTO students (student_number_id, user_id, student_section, student_current_program) VALUES (?, ?, ?, ?)");
	$stmt->execute([
		$json["student_number_id"],
		$user_id,
		$json["student_section"],
		$json["student_current_program"]
	]);

	$student_id = $pdo->lastInsertId();

	// Insert into student_programs_taken
	$stmt = $pdo->prepare("INSERT INTO student_programs_taken (student_id, program_id, start_date, shift_status) VALUES (?, ?, CURDATE(), 'Approved')");
	$stmt->execute([
		$student_id,
		$json["student_current_program"]
	]);

	$pdo->commit();

	echo json_encode([
		"status" => "success",
		"student_id" => $student_id,
		"user_id" => $user_id,
		"generated_email" => $email
	]);
} catch (Exception $e) {
	$pdo->rollBack();
	http_response_code(500);
	echo json_encode(["status" => "error", "message" => $e->getMessage()]);
}
