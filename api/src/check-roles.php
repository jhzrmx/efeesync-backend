<?php
require_once "_connect_to_database.php";
require_once "./libs/JWTHandler.php";
require_once "_request.php";

header("Content-Type: application/json");

$data = json_request_body();
require_params($data, ["email", "password"]);

$email = trim($data["email"]);
$password = trim($data["password"]);

try {
	// Check user
	$stmt = $pdo->prepare("
		SELECT u.user_id, u.password, r.role_name, u.role_id
		FROM users u
		JOIN roles r ON u.role_id = r.role_id
		WHERE u.institutional_email = :email
		LIMIT 1
	");
	$stmt->execute([":email" => $email]);
	$user = $stmt->fetch(PDO::FETCH_ASSOC);

	if (!$user) {
		echo json_encode(["status" => "error", "message" => "User not found"]);
		exit();
	}
	
	if (!password_verify($password, $user["password"])) {
		echo json_encode(["status" => "error", "message" => "Wrong password"]);
		exit();
	}

	// Build response
	$response = [
		"status"  => "success",
		"user_id" => (int) $user["user_id"],
		"roles"   => []
	];

	// If admin → only admin role
	if ($user["role_name"] === "admin") {
		$response["roles"][] = [
			"role_name" => "admin"
		];
	} elseif ($user["role_name"] === "student") {
		$stmt = $pdo->prepare("
			SELECT d.department_code, o.organization_code
			FROM students s
			JOIN programs p ON s.student_current_program = p.program_id
			JOIN departments d ON p.department_id = d.department_id
			LEFT JOIN organizations o ON d.department_id = o.department_id
			WHERE s.user_id = :user_id
			LIMIT 1
		");
		$stmt->execute([":user_id" => $user["user_id"]]);
		$student = $stmt->fetch(PDO::FETCH_ASSOC);

		if ($student) {
			$response["roles"][] = [
				"role_name"       => "student",
				"department_code" => $student["department_code"],
				"organization_code" => $student["organization_code"]
			];
		}
		
		$stmt = $pdo->prepare("
			SELECT o.organization_code, d.department_code, oo.designation
			FROM students s
			JOIN organization_officers oo ON s.student_id = oo.student_id
			JOIN organizations o ON oo.organization_id = o.organization_id
			LEFT JOIN departments d on o.department_id = d.department_id
			WHERE s.user_id = :user_id
			LIMIT 1
		");
		$stmt->execute([":user_id" => $user["user_id"]]);
		$officer = $stmt->fetch(PDO::FETCH_ASSOC);

		if ($officer) {
			$response["roles"][] = [
				"role_name"        => strtolower($officer["designation"]),
				"department_code"  => $officer["department_code"],
				"organization_code"=> $officer["organization_code"]
			];
		}
	}
} catch (Exception $e) {
	http_response_code(400);
	$response = [
		"status"  => "error",
		"message" => $e->getMessage()
	];
}

echo json_encode($response, JSON_PRETTY_PRINT);