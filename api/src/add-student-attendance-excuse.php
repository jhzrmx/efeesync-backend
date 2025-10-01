<?php
require_once "_connect_to_database.php";
require_once "_middleware.php";
require_once "_upload_dirs.php";

header("Content-Type: application/json");

// Require student role
require_role(["student"]);

$response = ["status" => "error"];

try {
    // Validate fields
    if (empty($_POST["student_id"]) || empty($_POST["event_attend_date_id"]) || empty($_POST["attendance_excuse_reason"])) {
        throw new Exception("Student ID, Event Attendance Date, and Reason are required.");
    }

    $student_id = intval($_POST["student_id"]);
    $event_attend_date_id = intval($_POST["event_attend_date_id"]);
    $reason = trim($_POST["attendance_excuse_reason"]);

    // File upload
    $proof_file = null;
    if (!empty($_FILES["attendance_excuse_proof_file"]["name"])) {
        $file_name = time() . "_" . basename($_FILES["attendance_excuse_proof_file"]["name"]);
        $target_file = $payment_proof_dir . $file_name;

        if (!move_uploaded_file($_FILES["attendance_excuse_proof_file"]["tmp_name"], $target_file)) {
            throw new Exception("Failed to upload proof file.");
        }

        $proof_file = $file_name;
    }

    // Prevent duplicate excuse
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM attendance_excuse WHERE student_id = ? AND event_attend_date_id = ?");
    $stmt->execute([$student_id, $event_attend_date_id]);
    if ($stmt->fetchColumn() > 0) {
        throw new Exception("Excuse already filed for this event date.");
    }

    // Insert excuse
    $stmt = $pdo->prepare("INSERT INTO attendance_excuse 
        (attendance_excuse_reason, attendance_excuse_proof_file, student_id, event_attend_date_id, attendance_excuse_status) 
        VALUES (?, ?, ?, ?, 'PENDING')");
    $stmt->execute([$reason, $proof_file, $student_id, $event_attend_date_id]);

    $response["status"] = "success";
    $response["message"] = "Excuse submitted successfully.";

} catch (Exception $e) {
    http_response_code(400);
    $response["message"] = $e->getMessage();
}

echo json_encode($response);