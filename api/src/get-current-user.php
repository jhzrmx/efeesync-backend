<?php
require_once "_connect_to_database.php";
require_once "_middleware.php";

header("Content-Type: application/json");

require_login();

$response = ["status" => "error"];

try {
	$current_user_id = current_jwt_payload()['user_id'];
	$current_dept_code = current_jwt_payload()['dept_code'];
	$current_role = current_jwt_payload()['role'];
	
	$sql = "SELECT u.user_id, u.institutional_email, u.last_name, u.first_name, u.middle_initial, u.picture, r.role_name 
			FROM users u 
			JOIN roles r ON r.role_id = u.role_id
			WHERE u.user_id = :user_id
			LIMIT 1";
	if ($current_role === "student") {
		$sql = "SELECT 
				u.user_id, 
				u.institutional_email, 
				u.last_name, 
				u.first_name, 
				u.middle_initial, 
				u.picture, 
				u.role_id, 
				r.role_name, 
				s.student_section,
				p.program_id,
				p.program_code,
				p.program_name,
				d.department_id,
				d.department_code,
				d.department_name,
				d.department_color,
				o.organization_id,
				o.organization_code,
				o.organization_logo,
				o.organization_name
			FROM users u
			LEFT JOIN students s ON s.user_id = u.user_id
			JOIN roles r ON r.role_id = u.role_id
			LEFT JOIN programs p ON p.program_id = s.student_current_program
			LEFT JOIN departments d ON d.department_id = p.department_id
			LEFT JOIN organizations o ON o.department_id = d.department_id
			WHERE u.user_id = :user_id
			LIMIT 1";
	} elseif ($current_role === "treasurer") { // If treasurer, point to the organization officer with treasurer designation instead
		$sql = "SELECT 
				u.user_id, 
				u.institutional_email, 
				u.last_name, 
				u.first_name, 
				u.middle_initial, 
				u.picture, 
				s.student_section,
				p.program_id,
				p.program_code,
				p.program_name,
				d.department_id,
				d.department_code,
				d.department_name,
				CASE
					WHEN o.department_id IS NULL
					THEN '#00ff00'
					ELSE d.department_color
				END AS department_color,
				o.organization_id,
				o.organization_code,
				o.organization_name,
				oo.designation AS role
			FROM users u
			LEFT JOIN students s ON s.user_id = u.user_id
			LEFT JOIN programs p ON p.program_id = s.student_current_program
			LEFT JOIN departments d ON d.department_id = p.department_id
			LEFT JOIN organization_officers oo ON oo.student_id = s.student_id
			LEFT JOIN organizations o ON o.organization_id = oo.organization_id
			WHERE u.user_id = :user_id AND oo.designation = 'treasurer'
			LIMIT 1";
	}
	$stmt = $pdo->prepare($sql);
	$stmt->bindParam(":user_id", $current_user_id);
	$stmt->execute();
	$data = $stmt->fetchAll(PDO::FETCH_ASSOC);

	$firstResult = $data[0] ?? null;
	if (!$firstResult) throw new Exception("User not found");

	// Build full_name
	if ($firstResult["middle_initial"] !== NULL) {
		$firstResult["full_name"] = $firstResult["first_name"]." ".$firstResult["middle_initial"].". ".$firstResult["last_name"];
	} else {
		$firstResult["full_name"] = $firstResult["first_name"]." ".$firstResult["last_name"];
	}

	$firstResult["university_wide_org"] = is_null($current_dept_code);
	$response["status"] = "success";
	$response["data"] = $firstResult;

} catch (Exception $e) {
	$response["message"] = $e->getMessage();
}

echo json_encode($response);
