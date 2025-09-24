<?php 
require_once "_connect_to_database.php";
require_once "_middleware.php";
require_once "_request.php";

header("Content-Type: application/json");

require_role(["treasurer"]);

$response = ["status" => "error"];

try {
    // Route params (adjust depending on router implementation)
    $event_id = isset($id) ? intval($id) : null;
    $event_date = $date ?? null; // format YYYY-MM-DD
    $student_id_param = $student_id ?? null;
    $student_number_id_param = $student_number_id ?? null;

    if (!$event_id || !$event_date) {
        throw new Exception("Missing event ID or date.");
    }

    // --- Find the event_attend_date_id ---
    $stmt = $pdo->prepare("
        SELECT event_attend_date_id 
        FROM event_attendance_dates 
        WHERE event_id = :event_id AND event_attend_date = :event_date
    ");
    $stmt->execute([":event_id" => $event_id, ":event_date" => $event_date]);
    $dateRow = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$dateRow) throw new Exception("Attendance date not found for event.");
    $event_attend_date_id = $dateRow["event_attend_date_id"];

    // --- Resolve student_id ---
    if ($student_number_id_param) {
        $stmt = $pdo->prepare("SELECT student_id FROM students WHERE student_number_id = :num");
        $stmt->execute([":num" => $student_number_id_param]);
        $studentRow = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$studentRow) throw new Exception("Student not found with number ID.");
        $student_id = $studentRow["student_id"];
    } elseif ($student_id_param) {
        $student_id = intval($student_id_param);
    } else {
        throw new Exception("Student identifier missing.");
    }

    // --- Delete any existing attendance/absence entries for that student/date ---
    $stmt = $pdo->prepare("
        DELETE FROM attendance_made 
        WHERE event_attend_time_id IN (
            SELECT event_attend_time_id
            FROM event_attendance_dates
            WHERE event_attend_date_id = :date_id AND student_id = :student_id
        )
    ");
    $stmt->execute([
        ":date_id" => $event_attend_date_id,
        ":student_id" => $student_id
    ]);

    $stmt = $pdo->prepare("
        DELETE FROM attendance_excuse
        WHERE event_attend_date_id = :date_id AND student_id = :student_id
    ");
    $stmt->execute([
        ":date_id" => $event_attend_date_id,
        ":student_id" => $student_id
    ]);

    // --- Insert excuse ---
    $stmt = $pdo->prepare("
        INSERT INTO attendance_excuse (event_attend_date_id, student_id, attendance_excuse_status)
        VALUES (:date_id, :student_id, 'APPROVED')
    ");
    $stmt->execute([
        ":date_id" => $event_attend_date_id,
        ":student_id" => $student_id
    ]);

    $response["status"] = "success";
    $response["message"] = "Excuse recorded.";
    $response["data"] = [
        "event_id" => $event_id,
        "event_attend_date_id" => $event_attend_date_id,
        "student_id" => $student_id
    ];

} catch (Exception $e) {
    http_response_code(400);
    $response["message"] = $e->getMessage();
}

echo json_encode($response);