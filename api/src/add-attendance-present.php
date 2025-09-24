<?php 
require_once "_connect_to_database.php";
require_once "_middleware.php";
require_once "_request.php";

header("Content-Type: application/json");

require_role(["treasurer"]);

$response = ["status" => "error"];

try {
    // Validate route params
    if (!isset($id) || !isset($date) || !isset($time) || !isset($inout)) {
        throw new Exception("Missing parameters in URL.");
    }

    $event_id = intval($id);
    $event_date = $date; // format YYYY-MM-DD
    $time = strtoupper($time);   // AM or PM
    $inout = strtoupper($inout); // IN or OUT

    if (!in_array($time, ["AM","PM"])) throw new Exception("Invalid time (must be AM or PM).");
    if (!in_array($inout, ["IN","OUT"])) throw new Exception("Invalid in/out (must be IN or OUT).");

    // Combine to match "AM IN", "AM OUT", etc.
    $timeLabel = $time . " " . $inout;

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

    // --- Find the event_attend_time_id ---
    $stmt = $pdo->prepare("
        SELECT event_attend_time_id 
        FROM event_attendance_times 
        WHERE event_attend_date_id = :date_id AND event_attend_time = :timeLabel
    ");
    $stmt->execute([":date_id" => $event_attend_date_id, ":timeLabel" => $timeLabel]);
    $timeRow = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$timeRow) throw new Exception("Attendance time slot not found.");
    $event_attend_time_id = $timeRow["event_attend_time_id"];

    // --- Resolve student_id (if student_number_id is used) ---
    if (isset($student_number_id)) {
        $stmt = $pdo->prepare("SELECT student_id FROM students WHERE student_number_id = :num");
        $stmt->execute([":num" => $student_number_id]);
        $studentRow = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$studentRow) throw new Exception("Student not found with number ID.");
        $student_id = $studentRow["student_id"];
    } elseif (isset($student_id)) {
        $student_id = intval($student_id);
    } else {
        throw new Exception("Student identifier missing.");
    }

    // --- Check if already attended ---
    $stmt = $pdo->prepare("
        SELECT 1 FROM attendance_made 
        WHERE event_attend_time_id = :time_id AND student_id = :student_id
    ");
    $stmt->execute([
        ":time_id" => $event_attend_time_id,
        ":student_id" => $student_id
    ]);
    if ($stmt->fetch()) throw new Exception("Student already attended.");

    // --- Insert attendance record ---
    $stmt = $pdo->prepare("
        INSERT INTO attendance_made (event_attend_time_id, student_id) 
        VALUES (:time_id, :student_id)
    ");
    $stmt->execute([
        ":time_id" => $event_attend_time_id,
        ":student_id" => $student_id
    ]);

    $response["status"] = "success";
    $response["message"] = "Attendance recorded.";
    $response["data"] = [
        "event_id" => $event_id,
        "event_attend_date_id" => $event_attend_date_id,
        "event_attend_time_id" => $event_attend_time_id,
        "student_id" => $student_id
    ];

} catch (Exception $e) {
    http_response_code(400);
    $response["message"] = $e->getMessage();
}

echo json_encode($response);