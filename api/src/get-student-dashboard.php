<?php
require_once "_connect_to_database.php";
require_once "_middleware.php";

header("Content-Type: application/json");

require_role("student");

$response = ["status" => "error"];

try {
    $current_user_id = current_jwt_payload()['user_id'];

    // Get student info
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
        throw new Exception("The user is not a student");
    }

    $student_id = $student["student_id"];
    $student_year = substr($student["student_section"], 0, 1);
    $dept_id = $student["department_id"];

    // ===============================
    // Upcoming events 
    // ===============================
    $sql = "SELECT 
                e.event_id, 
                e.event_name, 
                e.event_description, 
                e.event_end_date, 
                e.event_target_year_levels, 
                o.organization_name,
                CASE 
                    WHEN EXISTS (SELECT 1 FROM event_contributions ec WHERE ec.event_id = e.event_id) 
                         AND EXISTS (SELECT 1 FROM event_attendance_dates ead WHERE ead.event_id = e.event_id) 
                    THEN 'Attendance and Contribution'
                    WHEN EXISTS (SELECT 1 FROM event_contributions ec WHERE ec.event_id = e.event_id) 
                    THEN 'Contribution'
                    WHEN EXISTS (SELECT 1 FROM event_attendance_dates ead WHERE ead.event_id = e.event_id) 
                    THEN 'Attendance'
                    ELSE 'None'
                END AS event_type
            FROM events e
            JOIN organizations o ON e.organization_id = o.organization_id
            WHERE e.event_end_date >= CURDATE()
              AND (
                    o.department_id IS NULL -- university-wide
                    OR o.department_id = :dept_id -- department-based
                  )
              AND FIND_IN_SET(:student_year, e.event_target_year_levels)
            ORDER BY e.event_end_date ASC";
    $stmt = $pdo->prepare($sql);
    $stmt->execute([
        ":dept_id" => $dept_id,
        ":student_year" => $student_year
    ]);
    $upcoming_events = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // ===============================
    // Paid / Unpaid / Unsettled Contributions
    // ===============================
    $contri_sql = "
        SELECT 
            ec.event_id,
            ec.event_contri_fee,
            IFNULL(SUM(cm.amount_paid),0) AS total_paid
        FROM event_contributions ec
        LEFT JOIN contributions_made cm
            ON cm.event_contri_id = ec.event_contri_id
           AND cm.student_id = ?
        GROUP BY ec.event_id, ec.event_contri_fee
    ";
    $contri_stmt = $pdo->prepare($contri_sql);
    $contri_stmt->execute([$student_id]);
    $contri_rows = $contri_stmt->fetchAll(PDO::FETCH_ASSOC);

    $num_paid_contributions = 0;
    $num_unsettled_contributions = 0;
    $num_unpaid_contributions = 0;

    foreach ($contri_rows as $row) {
        $fee = (float) $row["event_contri_fee"];
        $paid = (float) $row["total_paid"];

        if ($paid >= $fee && $fee > 0) {
            $num_paid_contributions++;
        } elseif ($paid > 0 && $paid < $fee) {
            $num_unsettled_contributions++;
        } elseif ($paid == 0 && $fee > 0) {
            $num_unpaid_contributions++;
        }
    }

    // ===============================
    // Active Sanctions (contribution + attendance)
    // ===============================
    $num_active_sanctions = 0;

    // Contribution sanctions
    $contri_sanction_sql = "
        SELECT 
            ec.event_contri_sanction_fee,
            (SELECT IFNULL(SUM(amount_paid),0) 
             FROM paid_contribution_sanctions pcs
             WHERE pcs.student_id = :sid 
               AND pcs.event_contri_id = ec.event_contri_id 
               AND pcs.payment_status = 'APPROVED') AS sanction_paid
        FROM event_contributions ec
        WHERE ec.event_contri_sanction_fee > 0
    ";
    $cs_stmt = $pdo->prepare($contri_sanction_sql);
    $cs_stmt->execute([":sid" => $student_id]);
    foreach ($cs_stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
        if ($row["sanction_paid"] < $row["event_contri_sanction_fee"]) {
            $num_active_sanctions++;
        }
    }

    // Attendance sanctions
    $attend_sanction_sql = "
        SELECT 
            eat.event_attend_sanction_fee,
            e.event_id,
            (SELECT IFNULL(SUM(amount_paid),0)
             FROM paid_attendance_sanctions pas
             WHERE pas.student_id = :sid
               AND pas.event_id = e.event_id
               AND pas.payment_status = 'APPROVED') AS sanction_paid
        FROM events e
        JOIN event_attendance_dates ead ON e.event_id = ead.event_id
        JOIN event_attendance_times eat ON ead.event_attend_date_id = eat.event_attend_date_id
        WHERE eat.event_attend_sanction_fee > 0
        GROUP BY e.event_id
    ";
    $as_stmt = $pdo->prepare($attend_sanction_sql);
    $as_stmt->execute([":sid" => $student_id]);
    foreach ($as_stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
        if ($row["sanction_paid"] < $row["event_attend_sanction_fee"]) {
            $num_active_sanctions++;
        }
    }

    // ===============================
    // Final response
    // ===============================
    $response["status"] = "success";
    $response["data"] = [
        "num_paid_contributions" => $num_paid_contributions,
        "num_unpaid_contributions" => $num_unpaid_contributions,
        "num_unsettled_contributions" => $num_unsettled_contributions,
        "num_active_sanctions" => $num_active_sanctions,
        "upcoming_events" => $upcoming_events
    ];

} catch (Exception $e) {
    http_response_code(400);
    $response["message"] = $e->getMessage();
}

echo json_encode($response);
