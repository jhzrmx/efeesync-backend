<?php 
require_once "_connect_to_database.php";
require_once "_current_role.php";

header("Content-Type: application/json");

if (!is_current_role_in(['admin'])) {
	http_response_code(403);
	echo json_encode(["status" => "error", "message" => "Forbidden"]);
	exit();
}

$response = [];
$response["status"] = "error";

try {
	$stmt = $pdo->prepare("
		SELECT 
	    (SELECT COUNT(*) FROM departments) AS `total_departments`,
	    (SELECT COUNT(*) FROM organizations) AS `total_organizations`,
	    (SELECT COUNT(*) FROM programs) AS `total_programs`,
	    (SELECT COUNT(*) FROM students) AS `total_students`,
	    ((SELECT COALESCE(SUM(paid_sanction_amount), 0) FROM paid_attendance_sanctions) + (SELECT COALESCE(SUM(paid_sanction_amount), 0) FROM paid_contribution_sanctions)) AS `total_sanctions_collected`
	");
	$stmt->execute();
	$data = $stmt->fetchAll(PDO::FETCH_ASSOC);
	$response["status"] = "success";
	$response["data"] = $data;
} catch (Exception $e) {
	$response["message"] = $e->getMessage();
}

echo json_encode($response);