<?php
require_once "_connect_to_database.php";
require_once "_middleware.php";
require_once "_request.php";

header("Content-Type: application/json");
require_role(["student"]);

$response = ["status" => "error"];

try {
    $current_user_id = current_jwt_payload()['user_id'];

    // Get the student info
    $sql = "SELECT 
                s.student_id, 
                s.student_current_program, 
                s.student_section,
                p.department_id
            FROM users u
            JOIN students s ON s.user_id = u.user_id
            JOIN programs p ON s.student_current_program = p.program_id
            WHERE u.user_id = ?";
    $stmt = $pdo->prepare($sql);
    $stmt->execute([$current_user_id]);
    $student = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$student || !$student['student_id']) {
        throw new Exception("This user is not a student");
    }

    $student_id = $student["student_id"];
    $student_section = $student["student_section"];

    // Fetch contributions & classify
    $contributions_sql = "
        SELECT 
            e.event_id,
            e.event_name,
            ec.event_contri_fee,
            ec.event_contri_due_date,
            IFNULL(SUM(cm.amount_paid), 0) AS total_paid,
            CASE
                WHEN IFNULL(SUM(cm.amount_paid), 0) = 0 THEN 'UNPAID'
                WHEN IFNULL(SUM(cm.amount_paid), 0) >= ec.event_contri_fee THEN 'PAID'
                ELSE 'UNSETTLED'
            END AS payment_status
        FROM events e
        JOIN event_contributions ec ON ec.event_id = e.event_id
        LEFT JOIN contributions_made cm 
            ON cm.event_contri_id = ec.event_contri_id 
           AND cm.student_id = ?
        WHERE FIND_IN_SET(LEFT(?, 1), e.event_target_year_levels) > 0
        GROUP BY e.event_id, e.event_name, ec.event_contri_fee
    ";

    $attend_stmt = $pdo->prepare($contributions_sql);
    $attend_stmt->execute([$student_id, $student_section]);
    $attend_rows = $attend_stmt->fetchAll(PDO::FETCH_ASSOC);

    // Separate into paid/unpaid/unsettled
    $paid_events = [];
    $unpaid_events = [];
    $unsettled_events = [];
    $total_fees_paid = 0;

    foreach ($attend_rows as $row) {
        $status = $row["payment_status"];
        if ($status === "PAID") {
            $paid_events[] = $row;
            $total_fees_paid += $row["total_paid"];
        } elseif ($status === "UNPAID") {
            $unpaid_events[] = $row;
        } elseif ($status === "UNSETTLED") {
            $unsettled_events[] = $row;
            $total_fees_paid += $row["total_paid"];
        }
    }

    $response = [
        "status" => "success",
        "student_id" => (int)$student_id,
        "data" => [
            "total_fees_paid" => number_format($total_fees_paid, 2, '.', ''),
            "paid_events" => $paid_events,
            "unpaid_events" => $unpaid_events,
            "unsettled_events" => $unsettled_events
        ]
    ];

} catch (Exception $e) {
    $response["message"] = $e->getMessage();
}

echo json_encode($response);