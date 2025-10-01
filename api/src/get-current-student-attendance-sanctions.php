<?php
require_once "_connect_to_database.php";
require_once "_middleware.php";
require_once "_request.php";

header("Content-Type: application/json");
require_role(["student"]);

$response = ["status" => "error"];

try {
    $current_user_id = current_jwt_payload()['user_id'];
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

    $sanctions = [];
    $community_service = [];
    $total_balance = 0;
    $total_sanctions_paid = 0;

    $attend_sql = "
        SELECT 
            e.event_id,
            e.event_name,
            e.event_start_date,
            e.event_end_date,
            SUM(eat.event_attend_sanction_fee) AS total_due,
            e.event_end_date,
            e.event_sanction_has_comserv
        FROM events e
        INNER JOIN event_attendance_dates ead ON e.event_id = ead.event_id
        INNER JOIN event_attendance_times eat ON ead.event_attend_date_id = eat.event_attend_date_id
        LEFT JOIN attendance_made am 
            ON am.event_attend_time_id = eat.event_attend_time_id 
           AND am.student_id = ?
        LEFT JOIN attendance_excuse ae 
            ON ae.event_attend_date_id = ead.event_attend_date_id 
           AND ae.student_id = ?
           AND ae.attendance_excuse_status = 'APPROVED'
        WHERE FIND_IN_SET(LEFT(?, 1), e.event_target_year_levels) > 0
          AND am.attendance_id IS NULL
          AND ae.attendance_excuse_id IS NULL
        GROUP BY e.event_id, e.event_name, e.event_end_date
    ";
    $attend_stmt = $pdo->prepare($attend_sql);
    $attend_stmt->execute([$student_id, $student_id, $student_section]);
    $attend_rows = $attend_stmt->fetchAll(PDO::FETCH_ASSOC);

    // Get the total paid
    $attend_total_sql = "
        SELECT IFNULL(SUM(amount_paid), 0) AS total_paid
        FROM paid_attendance_sanctions
        WHERE student_id = ? AND payment_status = 'APPROVED' AND event_id = ?
    ";

    // Get missed attendance logs
    $absence_sql = "
        SELECT 
            ead.event_attend_date,
            eat.event_attend_time
        FROM event_attendance_dates ead
        INNER JOIN event_attendance_times eat ON ead.event_attend_date_id = eat.event_attend_date_id
        LEFT JOIN attendance_made am 
            ON am.event_attend_time_id = eat.event_attend_time_id
           AND am.student_id = ?
        LEFT JOIN attendance_excuse ae 
            ON ae.event_attend_date_id = ead.event_attend_date_id 
           AND ae.student_id = ?
           AND ae.attendance_excuse_status = 'APPROVED'
        WHERE ead.event_id = ?
          AND am.attendance_id IS NULL
          AND ae.attendance_excuse_id IS NULL
        ORDER BY ead.event_attend_date, eat.event_attend_time_id
    ";

    foreach ($attend_rows as $row) {
        // Only process past events
        if (!empty($row['event_end_date']) && $row['event_end_date'] >= date('Y-m-d')) continue;

        $attend_total_stmt = $pdo->prepare($attend_total_sql);
        $attend_total_stmt->execute([$student_id, $row["event_id"]]);
        $paid = (float) $attend_total_stmt->fetchColumn();

        $due = (float) $row['total_due'];
        $total_sanctions_paid += $paid;

        if ($paid < $due) {
            $balance = max(0, $due - $paid);
            $total_balance += $balance;

            // Get absence logs
            $absence_stmt = $pdo->prepare($absence_sql);
            $absence_stmt->execute([$student_id, $student_id, $row["event_id"]]);
            $absence_rows = $absence_stmt->fetchAll(PDO::FETCH_ASSOC);

            $absence_logs = [];
            foreach ($absence_rows as $ar) {
                $date = $ar['event_attend_date'];
                if (!isset($absence_logs[$date])) {
                    $absence_logs[$date] = [
                        "event_attend_date" => $date,
                        "event_attend_time" => []
                    ];
                }
                $absence_logs[$date]["event_attend_time"][] = $ar['event_attend_time'];
            }
            $absence_logs = array_values($absence_logs);

            $sanctions[] = [
                "event_id" => (int)$row["event_id"],
                "event_name" => $row["event_name"],
                "event_start_date" => $row["event_start_date"],
                "event_end_date" => $row["event_end_date"],
                "absence_logs" => $absence_logs,
                "amount" => $due,
                "paid" => $paid,
                "balance" => $balance
            ];

            if ($row["event_sanction_has_comserv"] == 1) {
                $community_service[] = [
                    "event_id" => (int)$row["event_id"],
                    "event_name" => $row["event_name"],
                    "event_start_date" => $row["event_start_date"],
                    "event_end_date" => $row["event_end_date"],
                ];
            }
        }
    }

    $response = [
        "status" => "success",
        "student_id" => (int)$student_id,
        "data" => [
            "monetary_sanctions" => $sanctions,
            "community_service" => $community_service,
            "total_sanction_balance" => $total_balance,
            "total_sanctions_paid" => $total_sanctions_paid
        ]
    ];

} catch (Exception $e) {
    $response["message"] = $e->getMessage();
}

echo json_encode($response);