<?php
require_once "_connect_to_database.php";
require_once "_middleware.php";
require_once "_request.php";

header("Content-Type: application/json");
require_role(["treasurer", "admin"]);

$response = ["status" => "error"];

try {
    $student_id = null;
    if (isset($id)) {
        $student_id = intval($id);
    } elseif (isset($student_number)) {
        $stmt = $pdo->prepare("SELECT student_id FROM students WHERE student_number_id = ?");
        $stmt->execute([$student_number]);
        $student_id = $stmt->fetchColumn();
    }

    if (!$student_id) {
        throw new Exception("Student not found.");
    }

    // Get student section for year-level filtering
    $stmt = $pdo->prepare("SELECT student_section FROM students WHERE student_id = ?");
    $stmt->execute([$student_id]);
    $student_section = $stmt->fetchColumn();
    if (!$student_section) throw new Exception("Student not found.");

    $sanctions = [];
    $total_balance = 0;

    // =====================
    // UNSETTLED/UNPAID CONTRIBUTION
    // =====================
    $contri_sql = "
        SELECT 
            e.event_id,
            e.event_name,
            ec.event_contri_fee,
            ec.event_contri_sanction_fee,
            IFNULL(SUM(cm.amount_paid), 0) AS total_paid,
            (ec.event_contri_fee + ec.event_contri_sanction_fee) AS total_due,
            e.event_end_date
        FROM event_contributions ec
        INNER JOIN events e ON ec.event_id = e.event_id
        LEFT JOIN contributions_made cm 
            ON cm.event_contri_id = ec.event_contri_id 
           AND cm.student_id = ?
		-- LEFT JOIN paid_contribution_sanctions pcs
        --     ON pcs.event_contri_id = ec.event_contri_id
		--    AND pcs.payment_status = 'APPROVED'
        --    AND pcs.student_id = ?
        WHERE FIND_IN_SET(LEFT(?, 1), e.event_target_year_levels) > 0
        GROUP BY e.event_id, e.event_name, ec.event_contri_fee, ec.event_contri_sanction_fee, e.event_end_date
    ";
    $contri_stmt = $pdo->prepare($contri_sql);
    $contri_stmt->execute([$student_id, /*$student_id,*/ $student_section]);
    $contri_rows = $contri_stmt->fetchAll(PDO::FETCH_ASSOC);

    foreach ($contri_rows as $row) {
        if ($row['event_end_date'] >= date('Y-m-d')) continue; // skip ongoing events

        $due = (float) $row['total_due'];
        $paid = (float) $row['total_paid'];

        // sanction applies only if due date passed and still unsettled
        if ($paid < $due) {
            $balance = $due - $paid;
            $total_balance += $balance;

            $sanctions["contributions_needed"][] = [
                "event_id" => (int)$row["event_id"],
                "event_name" => $row["event_name"],
                "amount" => number_format($due, 2, '.', ''),
                "paid" => number_format($paid, 2, '.', ''),
                "balance" => number_format($balance, 2, '.', '')
            ];
        }
    }

    // =====================
    // ATTENDANCE SANCTIONS
    // =====================
    $attend_sql = "
        SELECT 
            e.event_id,
            e.event_name,
            SUM(eat.event_attend_sanction_fee) AS total_due,
            e.event_end_date
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
    foreach ($attend_rows as $row) {
        if (!empty($row['event_end_date']) && $row['event_end_date'] >= date('Y-m-d')) continue;

        $attend_total_stmt = $pdo->prepare($attend_total_sql);
        $attend_total_stmt->execute([$student_id, $row["event_id"]]);
        $paid = (float) $attend_total_stmt->fetchColumn();

        $due = (float) $row['total_due'];

        if ($paid < $due) {
            $balance = max(0, $due - $paid);
            $total_balance += $balance;

            $sanctions["attendance_sanctions"][] = [
                "event_id" => (int)$row["event_id"],
                "event_name" => $row["event_name"],
                "amount" => number_format($due, 2, '.', ''),
                "paid" => number_format($paid, 2, '.', ''),
                "balance" => number_format($balance, 2, '.', '')
            ];
        }
    }

    $response = [
        "status" => "success",
        "student_id" => (int)$student_id,
        "data" => $sanctions,
        "total_sanction_balance" => number_format($total_balance, 2, '.', '')
    ];

} catch (Exception $e) {
    $response["message"] = $e->getMessage();
}

echo json_encode($response);