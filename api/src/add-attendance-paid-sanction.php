<?php  
require_once "_connect_to_database.php";
require_once "_middleware.php";
require_once "_request.php";

header("Content-Type: application/json");

require_role(["treasurer"]);

$response = ["status" => "error"];

try {
    // --- Validate route params ---
    if (!isset($id)) {
        throw new Exception("Missing event_id parameter in URL.");
    }

    $event_id = intval($id);

    // --- Resolve student_id (support both :student_id and :student_number_id) ---
    if (isset($student_number_id)) {
        $stmt = $pdo->prepare("SELECT student_id FROM students WHERE student_number_id = :num");
        $stmt->execute([":num" => $student_number_id]);
        $studentRow = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$studentRow) throw new Exception("Student not found with number ID.");
        $student_id = (int) $studentRow["student_id"];
    } elseif (isset($student_id)) {
        $student_id = intval($student_id);
    } else {
        throw new Exception("Student identifier missing.");
    }

    // --- Compute the total sanction due ---
    $due_sql = "
        SELECT 
            SUM(eat.event_attend_sanction_fee) AS total_due
        FROM events e
        INNER JOIN event_attendance_dates ead ON e.event_id = ead.event_id
        INNER JOIN event_attendance_times eat ON ead.event_attend_date_id = eat.event_attend_date_id
        LEFT JOIN attendance_made am 
            ON am.event_attend_time_id = eat.event_attend_time_id 
           AND am.student_id = :sid
        LEFT JOIN attendance_excuse ae 
            ON ae.event_attend_date_id = ead.event_attend_date_id 
           AND ae.student_id = :sid
           AND ae.attendance_excuse_status = 'APPROVED'
        WHERE e.event_id = :eid
          AND am.attendance_id IS NULL
          AND ae.attendance_excuse_id IS NULL
        GROUP BY e.event_id
    ";
    $stmt = $pdo->prepare($due_sql);
    $stmt->execute([":sid" => $student_id, ":eid" => $event_id]);
    $dueRow = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$dueRow || !$dueRow["total_due"]) {
        throw new Exception("No sanction found for this student in the given event.");
    }

    $total_due = (float) $dueRow["total_due"];

    // --- Check already paid ---
    $stmt = $pdo->prepare("
        SELECT IFNULL(SUM(amount_paid),0) AS total_paid
        FROM paid_attendance_sanctions
        WHERE student_id = :sid AND event_id = :eid AND payment_status = 'APPROVED'
    ");
    $stmt->execute([":sid" => $student_id, ":eid" => $event_id]);
    $already_paid = (float) $stmt->fetchColumn();

    if ($already_paid >= $total_due) {
        throw new Exception("Student already settled full sanction for this event.");
    }

    $balance = $total_due - $already_paid;

    // --- Insert payment (full remaining balance) ---
    $stmt = $pdo->prepare("
        INSERT INTO paid_attendance_sanctions (event_id, student_id, amount_paid, payment_status) 
        VALUES (:eid, :sid, :amount_paid, 'APPROVED')
    ");
    $stmt->execute([
        ":eid" => $event_id,
        ":sid" => $student_id,
        ":amount_paid" => $balance
    ]);

    $response["status"] = "success";
    $response["message"] = "Attendance sanction fully paid.";
    $response["data"] = [
        "event_id" => $event_id,
        "student_id" => $student_id,
        "amount_paid" => number_format($balance, 2, '.', ''),
        "total_due" => number_format($total_due, 2, '.', ''),
        "already_paid_before" => number_format($already_paid, 2, '.', ''),
        "fully_settled" => ($already_paid + $balance) >= $total_due
    ];

} catch (Exception $e) {
    http_response_code(400);
    $response["message"] = $e->getMessage();
}

echo json_encode($response);