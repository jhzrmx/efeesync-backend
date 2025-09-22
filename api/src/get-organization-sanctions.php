<?php
require_once "_connect_to_database.php";
require_once "_middleware.php";

header("Content-Type: application/json");
require_role(["treasurer", "admin"]);

$response = ["status" => "error"];

try {
    if (isset($organization_id)) {
        $org_id = intval($organization_id);
    } elseif (isset($organization_code)) {
        $stmt_org = $pdo->prepare("SELECT organization_id FROM organizations WHERE organization_code = :code LIMIT 1");
        $stmt_org->execute([":code" => $organization_code]);
        $org = $stmt_org->fetch(PDO::FETCH_ASSOC);
        if (!$org) throw new Exception("Organization not found");
        $org_id = (int) $org["organization_id"];
    } else {
        throw new Exception("Missing organization identifier in route");
    }

    $sql = "
        SELECT 
            s.student_id,
            s.student_number_id,
            u.first_name,
            u.last_name,
			CASE 
                WHEN u.middle_initial IS NOT NULL AND u.middle_initial <> '' 
                    THEN CONCAT(u.first_name, ' ', u.middle_initial, '. ', u.last_name)
                ELSE CONCAT(u.first_name, ' ', u.last_name)
            END AS full_name,
            o.organization_id,
            o.organization_name,
            ec.event_contri_id,
            ec.event_contri_fee,
            ec.event_contri_sanction_fee,
            cm.amount_paid,
            CASE
                WHEN cm.contribution_id IS NULL 
                     AND CURDATE() > ec.event_contri_due_date
                THEN ec.event_contri_fee + ec.event_contri_sanction_fee
                WHEN cm.amount_paid < ec.event_contri_fee 
                     AND CURDATE() > ec.event_contri_due_date
                THEN (ec.event_contri_fee + ec.event_contri_sanction_fee) - cm.paid_amount
                ELSE 0
            END AS contri_sanction_balance
        FROM students s
        JOIN users u ON u.user_id = s.user_id
        LEFT JOIN programs p ON p.program_id = s.student_current_program
        LEFT JOIN departments d ON d.department_id = p.department_id
        JOIN organizations o 
            ON (o.department_id = d.department_id OR o.department_id IS NULL)
        LEFT JOIN events e ON e.organization_id = o.organization_id
        LEFT JOIN event_contributions ec ON ec.event_id = e.event_id
        LEFT JOIN contributions_made cm 
            ON cm.student_id = s.student_id AND cm.event_contri_id = ec.event_contri_id
        WHERE o.organization_id = ?
        GROUP BY s.student_id, ec.event_contri_id
        ORDER BY u.last_name, u.first_name
    ";

    $stmt = $pdo->prepare($sql);
    $stmt->execute([$org_id]);
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $response = [
        "status" => "success",
        "organization_id" => $org_id,
        "students" => $rows
    ];
} catch (Exception $e) {
	http_response_code(400);
    $response["message"] = $e->getMessage();
}

echo json_encode($response);