<?php
require_once "_connect_to_database.php";
require_once "_middleware.php";
require_once "_upload_dirs.php";

header("Content-Type: application/json");

// Require student role
require_role(["student"]);

// Route: POST /events/:id/date/:date

try {
    // --- Parameters ---
    $event_id   = $id ?? null;
    $dateexcuse = $date ?? null; // yyyy-mm-dd

    if (!$event_id || !$dateexcuse) {
        http_response_code(400);
        echo json_encode(["status" => "error", "message" => "Missing parameters"]);
        exit;
    }

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

    $student_id = $student['student_id'];

    // validate date format (yyyy-mm-dd)
    if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $dateexcuse)) {
        throw new Exception("Invalid date format, expected yyyy-mm-dd");
    }

    // --- Check event_attend_date existence ---
    $stmt = $pdo->prepare("
        SELECT ead.event_attend_date_id
        FROM event_attendance_dates ead
        INNER JOIN events ev ON ev.event_id = ead.event_id
        WHERE ev.event_id = ? AND ead.event_attend_date = ?
        LIMIT 1
    ");
    $stmt->execute([$event_id, $dateexcuse]);
    $eventDate = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$eventDate) {
        throw new Exception("Event attendance date not found for given event and date");
    }

    $event_attend_date_id = $eventDate['event_attend_date_id'];

    // --- File upload handling ---
    if (!isset($_FILES['proof_file'])) {
        throw new Exception("No file uploaded");
    }

    $file      = $_FILES['proof_file'];
    $maxSize   = 5 * 1024 * 1024; // 5MB limit
    $allowed   = ['jpg','jpeg','png','pdf'];
    $uploadDir = $excuse_proof_dir;

    // ensure directory exists
    if (!is_dir($uploadDir)) mkdir($uploadDir, 0775, true);

    if ($file['error'] !== UPLOAD_ERR_OK) throw new Exception("File upload error");

    if ($file['size'] > $maxSize) throw new Exception("File size exceeds 5MB");

    $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
    if (!in_array($ext, $allowed)) throw new Exception("Invalid file type");

    // secure filename: studentID_eventID_date_timestamp.ext
    $newName = "excuse_" . $student_id . "_" . $event_id . "_" . $dateexcuse . "_" . time() . "." . $ext;
    $destPath = $uploadDir . $newName;

    if (!move_uploaded_file($file['tmp_name'], $destPath)) {
        throw new Exception("Failed to save file");
    }

    // --- Save excuse to DB ---
    $reason = $_POST['reason'] ?? null;
    if (!$reason) {
        throw new Exception("Reason is required");
    }

    // Prevent duplicate excuse
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM attendance_excuse WHERE student_id = ? AND event_attend_date_id = ?");
    $stmt->execute([$student_id, $event_attend_date_id]);
    if ($stmt->fetchColumn() > 0) {
        throw new Exception("Excuse already filed for this event date.");
    }

    $stmt = $pdo->prepare("
        INSERT INTO attendance_excuse
        (attendance_excuse_reason, attendance_excuse_proof_file, event_attend_date_id, student_id, attendance_excuse_submitted_at)
        VALUES (?, ?, ?, ?, NOW())
    ");
    $stmt->execute([$reason, $newName, $event_attend_date_id, $student_id]);

    echo json_encode([
        "status" => "success",
        "message" => "Excuse submitted successfully",
        "data" => [
            "excuse_id" => $pdo->lastInsertId(),
            "student_id" => $student_id,
            "event_id" => $event_id,
            "date" => $dateexcuse,
            "file" => $newName
        ]
    ]);
} catch (Exception $e) {
    http_response_code(400);
    echo json_encode(["status" => "error", "message" => $e->getMessage()]);
}