<?php
require_once "_connect_to_database.php";
require_once "./libs/JWTHandler.php";
require_once "_current_role.php";
require_once "_request.php";

header("Content-Type: application/json");

$data = json_request_body();
require_params($data, ["email", "password"]);

if (current_role()) {
	echo json_encode([
		"status"  => "success",
		"message" => "You're already logged in",
		"data"    => [
			"current_user_id"	=> current_jwt_payload()["user_id"],
			"current_dept_code"	=> current_jwt_payload()["dept_code"],
			"current_role"		=> current_jwt_payload()["role"]
		]
	]);
	exit();
}

$email = trim($data["email"]);
$password = trim($data["password"]);
$requestedRole = isset($data["role"]) ? strtolower(trim($data["role"])) : null;

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

	// Build list of roles
	$roles = [];

	if ($user["role_name"] === "admin") {
		$roles[] = [
			"role_name" => "admin"
		];
	} elseif ($user["role_name"] === "student") {
		// Base student role with department_code
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
			$roles[] = [
				"role_name"         => "student",
				"department_code"   => $student["department_code"],
				"organization_code" => $student["organization_code"]
			];
		}

		// Org officer roles
		$stmt = $pdo->prepare("
			SELECT o.organization_code, d.department_code, oo.designation
			FROM students s
			JOIN organization_officers oo ON s.student_id = oo.student_id
			JOIN organizations o ON oo.organization_id = o.organization_id
			LEFT JOIN departments d on o.department_id = d.department_id
			WHERE s.user_id = :user_id
		");
		$stmt->execute([":user_id" => $user["user_id"]]);
		$officers = $stmt->fetchAll(PDO::FETCH_ASSOC);

		foreach ($officers as $officer) {
			$roles[] = [
				"role_name"         => strtolower($officer["designation"]),
				"department_code"   => $officer["department_code"],
				"organization_code" => $officer["organization_code"]
			];
		}
	}

	// Determine active role
	if (count($roles) > 1 && !$requestedRole) {
		echo json_encode([
			"status" => "error",
			"message" => "Multiple roles available. Please specify 'role' in request.",
			"available_roles" => $roles
		]);
		exit();
	}

	// If only one role, auto-pick it
	$activeRole = $roles[0];
	if ($requestedRole) {
		$found = false;
		foreach ($roles as $role) {
			if ($role["role_name"] === $requestedRole) {
				$activeRole = $role;
				$found = true;
				break;
			}
		}
		if (!$found) {
			echo json_encode([
				"status" => "error",
				"message" => "Invalid role selected",
				"available_roles" => $roles
			]);
			exit();
		}
	}

	// Create JWT
	$jwt = new JWTHandler($_ENV["EFEESYNC_JWT_SECRET"]);
	$payload = [
		"user_id" 	=> $user["user_id"],
		"role"    	=> $activeRole["role_name"],
		"dept_code"	=> $activeRole["department_code"],
		"exp"		=> time() + (3600 * 24 * 15), // 15 days
		"nbf"		=> time(),
	];
	$token = $jwt->createToken($payload);

	// Set cookie
	setcookie("basta", $token, [
		"expires"  => $payload["exp"],
		"path"     => "/",
		"secure"   => $_ENV["EFEESYNC_IS_PRODUCTION"],
		"httponly" => true,
		"samesite" => $_ENV["EFEESYNC_IS_PRODUCTION"] ? "Strict" : "Lax"
	]);
	
	$response = [
		"status"  => "success",
		"message" => "Login successful",
		"data"    => [
			"current_user_id" => $user["user_id"],
			"current_role"    => $activeRole
		]
	];
} catch (Exception $e) {
	http_response_code(400);
	$response = [
		"status"  => "error",
		"message" => $e->getMessage()
	];
}

echo json_encode($response);
