<?php
require_once "_connect_to_database.php";
require_once "_middleware.php";

header("Content-Type: application/json");

require_role(["admin", "treasurer"]);

$response = [];
$response["status"] = "error";

try {
	$sql = "SELECT 
		    s.student_id, 
		    s.student_number_id, 
		    s.student_section, 
		    s.last_active,
		    u.user_id, 
		    u.institutional_email, 
		    u.first_name, 
		    u.last_name, 
		    u.middle_initial, 
		    u.picture,
		    p.program_code,
		    d.department_code,
		    -- build full name in SQL
		    CASE 
		        WHEN u.middle_initial IS NOT NULL AND u.middle_initial <> '' 
		            THEN CONCAT(u.first_name, ' ', u.middle_initial, '. ', u.last_name)
		        ELSE CONCAT(u.first_name, ' ', u.last_name)
		    END AS full_name
		FROM students s
		JOIN users u ON u.user_id = s.user_id
		JOIN programs p ON p.program_id = s.student_current_program
		JOIN departments d ON d.department_id = p.department_id";

	if (isset($id)) {
		$sql .= " WHERE s.student_id = :student_id";
		$stmt = $pdo->prepare($sql);
		$stmt->bindParam(":student_id", $id);
	} elseif (isset($student_number)) {
		$sql .= " WHERE s.student_number_id = :student_number";
		$stmt = $pdo->prepare($sql);
		$stmt->bindParam(":student_number", $student_number);
	} elseif (isset($search)) {
		$sql .= " WHERE u.first_name LIKE :search
	           OR u.last_name LIKE :search
	           OR u.middle_initial LIKE :search
	           OR s.student_number_id LIKE :search
	           OR u.institutional_email LIKE :search
	           OR p.program_code LIKE :search
	           OR d.department_code LIKE :search";
	    $stmt = $pdo->prepare($sql);
	    $searchParam = "%".$search."%";
	    $stmt->bindParam(":search", $searchParam);
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
