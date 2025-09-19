<?php
require_once "_connect_to_database.php";
require_once "_middleware.php";

header("Content-Type: application/json");

require_login();

$response = ["status" => "error"];

try {
	$sql = "SELECT 
				d.department_id,
			    d.department_code,
			    d.department_name,
			    COUNT(DISTINCT s.student_id) AS `student_population`,
			    COUNT(DISTINCT p.program_id) AS `program_count`
			FROM departments d
			LEFT JOIN programs p 
			    ON d.department_id = p.department_id
			LEFT JOIN students s 
			    ON s.student_current_program = p.program_id";
	$sql_group = " GROUP BY d.department_id, d.department_code, d.department_name ORDER BY d.department_code";
	if (isset($id)) {
		$sql .= " WHERE d.department_id = :department_id" . $sql_group;
		$stmt = $pdo->prepare($sql);
		$stmt->bindParam(":department_id", $id);
	} elseif (isset($code)) {
		$sql .= " WHERE d.department_code = :department_code" . $sql_group;
		$stmt = $pdo->prepare($sql);
		$stmt->bindParam(":department_code", $code);
	} else {
		$stmt = $pdo->prepare($sql.$sql_group);
	}
	$stmt->execute();
	$data = $stmt->fetchAll(PDO::FETCH_ASSOC);
	$response["status"] = "success";
	$response["data"] = $data;
} catch (Exception $e) {
	$response["message"] = $e->getMessage();
}

echo json_encode($response);
