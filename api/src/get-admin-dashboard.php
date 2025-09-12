<?php 
require_once "_connect_to_database.php";
require_once "_current_role.php";

header("Content-Type: application/json");

if (!is_current_role_in(["admin"])) {
	http_response_code(403);
	echo json_encode(["status" => "error", "message" => "Forbidden"]);
	exit();
}

$response = [];
$response["status"] = "error";

try {
	$sql_total_sanction_each_org = "SELECT 
	    o.organization_id,
	    o.organization_code,
	    o.organization_name,
	    (
	        COALESCE(SUM(pas.paid_sanction_amount), 0) 
	        + COALESCE(SUM(pcs.paid_sanction_amount), 0)
	    ) AS total_sanctions_collected
	FROM organizations o
	LEFT JOIN events e ON e.organization_id = o.organization_id
	LEFT JOIN event_attendance ea ON ea.event_id = e.event_id
	LEFT JOIN paid_attendance_sanctions pas ON pas.event_attend_id = ea.event_attend_id
	LEFT JOIN event_contributions ec ON ec.event_id = e.event_id
	LEFT JOIN paid_contribution_sanctions pcs ON pcs.event_contri_id = ec.event_contri_id
	GROUP BY o.organization_id, o.organization_code, o.organization_name
	ORDER BY total_sanctions_collected DESC;
	";
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

	$stmt_sanctions_per_org = $pdo->prepare("
		SELECT
		    o.organization_code,
		    o.organization_name,
		    COALESCE(SUM(a.paid_amount), 0) AS total_sanctions_collected
		FROM organizations o
		LEFT JOIN (
		    SELECT e.organization_id, SUM(pas.paid_sanction_amount) AS paid_amount
		    FROM paid_attendance_sanctions pas
		    JOIN event_attendance ea ON pas.event_attend_id = ea.event_attend_id
		    JOIN events e ON ea.event_id = e.event_id
		    GROUP BY e.organization_id
		) a ON a.organization_id = o.organization_id
		LEFT JOIN (
		    SELECT e.organization_id, SUM(pcs.paid_sanction_amount) AS paid_amount
		    FROM paid_contribution_sanctions pcs
		    JOIN event_contributions ec ON pcs.event_contri_id = ec.event_contri_id
		    JOIN events e ON ec.event_id = e.event_id
		    GROUP BY e.organization_id
		) c ON c.organization_id = o.organization_id
		GROUP BY o.organization_id, o.organization_name;
	");
	$stmt_sanctions_per_org->execute();
	$data_sanctions = $stmt_sanctions_per_org->fetchAll(PDO::FETCH_ASSOC);

	$stmt_total_students_per_org = $pdo->prepare("
		SELECT 
		    d.department_code,
		    d.department_name,
		    COUNT(s.student_id) AS total_students
		FROM departments d
		LEFT JOIN programs p ON d.department_id = p.department_id
		LEFT JOIN students s ON s.student_current_program = p.program_id
		GROUP BY d.department_id, d.department_code, d.department_name
		ORDER BY total_students DESC;
	");
	$stmt_total_students_per_org->execute();
	$data_students = $stmt_total_students_per_org->fetchAll(PDO::FETCH_ASSOC);

	$response["status"] = "success";
	$response["data"] = $data[0];
	$response["data"]["sanctions_collected_per_org"] = $data_sanctions;
	$response["data"]["total_population_per_department"] = $data_students;
} catch (Exception $e) {
	$response["message"] = $e->getMessage();
}

echo json_encode($response);