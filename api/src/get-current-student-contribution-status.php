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

    // Fetch contributions, including partial payments
    $contributions_sql = "
        SELECT 
            e.event_id,
            e.event_name,
            ec.event_contri_fee,
            ec.event_contri_due_date,
            IFNULL(SUM(cm.amount_paid), 0) AS total_paid,
            (ec.event_contri_fee - IFNULL(SUM(cm.amount_paid), 0)) AS remaining_balance,
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

    $stmt = $pdo->prepare($contributions_sql);
    $stmt->execute([$student_id, $student_section]);
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Classify & compute totals
    $paid_events = [];
    $unpaid_events = [];
    $unsettled_events = [];
    $total_fees_paid = 0;
    $total_fees_unpaid = 0;
    $total_fees_unsettled = 0;

    foreach ($rows as $row) {
        $status = $row["payment_status"];
        $fee = (float)$row["event_contri_fee"];
        $paid = (float)$row["total_paid"];
        $balance = (float)$row["remaining_balance"];

        switch ($status) {
            case "PAID":
                $paid_events[] = [
                    ...$row,
                    "display_amount" => number_format($paid, 2, '.', ''),
                    "remarks" => "Fully paid"
                ];
                $total_fees_paid += $paid;
                break;

            case "UNPAID":
                $unpaid_events[] = [
                    ...$row,
                    "display_amount" => number_format($fee, 2, '.', ''),
                    "remarks" => "No payment made"
                ];
                $total_fees_unpaid += $fee;
                break;

            case "UNSETTLED":
                $unsettled_events[] = [
                    ...$row,
                    "display_amount" => number_format($balance, 2, '.', ''),
                    "remarks" => "Remaining balance"
                ];
                $total_fees_unsettled += $balance;
                break;
        }
    }

    $response = [
        "status" => "success",
        "student_id" => (int)$student_id,
        "data" => [
            "total_fees_paid" => number_format($total_fees_paid, 2, '.', ''),
            "total_fees_unpaid" => number_format($total_fees_unpaid, 2, '.', ''),
            "total_fees_unsettled" => number_format($total_fees_unsettled, 2, '.', ''),
            "paid_events" => $paid_events,
            "unpaid_events" => $unpaid_events,
            "unsettled_events" => $unsettled_events
        ]
    ];

} catch (Exception $e) {
    $response["message"] = $e->getMessage();
}

echo json_encode($response);