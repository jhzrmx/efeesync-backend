<?php
require_once "_connect_to_database.php";
require_once "_middleware.php";

header("Content-Type: application/json");

require_login();

$response = ["status" => "error"];

try {
	$sql = "SELECT
				oo.organization_officer_id,
				u.first_name, 
	            u.last_name, 
	            u.middle_initial,
				CASE 
                    WHEN u.middle_initial IS NOT NULL AND u.middle_initial <> '' 
                        THEN CONCAT(u.last_name, ', ', u.first_name, ' ', u.middle_initial, '.')
                    ELSE CONCAT(u.last_name, ', ', u.first_name)
                END AS full_name,
				s.student_number_id,
				oo.designation,
				o.organization_code
			FROM organization_officers oo
			JOIN organizations o ON oo.organization_id = o.organization_id
			JOIN students s ON oo.student_id = s.student_id
			JOIN users u ON u.user_id = s.user_id
			WHERE 1=1
	";

	$params = [];

	if (isset($_GET['designation']) && $_GET['designation'] !== "") {
		$sql .= " AND oo.designation = :designation";
		$params[":designation"] = $_GET['designation'];
	}

	if (isset($id)) {
		$sql .= " AND oo.organization_officer_id = :organization_officer_id";
		$params[":organization_officer_id"] = $id;
	}

	$stmt = $pdo->prepare($sql);
	$stmt->execute($params);
	$data = $stmt->fetchAll(PDO::FETCH_ASSOC);
	
	$response["status"] = "success";
	$response["data"] = $data;
} catch (Exception $e) {
	$response["message"] = $e->getMessage();
}

echo json_encode($response);