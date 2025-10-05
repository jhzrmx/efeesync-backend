<?php
require_once "_connect_to_database.php";
require_once "_middleware.php";
require_once "_upload_dirs.php";

header("Content-Type: application/json");

// Require student role
require_role(["student"]);

$attendance_excuse_id = $id ?? null;

try {
    if (!$attendance_excuse_id) throw new Exception("Missing excuse ID");

    // Fetch existing excuse
    $stmt = $pdo->prepare("SELECT * FROM attendance_excuse WHERE attendance_excuse_id = ?");
    $stmt->execute([$attendance_excuse_id]);
    $excuse = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$excuse) throw new Exception("Excuse not found");

    // New reason (fallback to old if not provided)
    $reason = $_POST['reason'] ?? $excuse['attendance_excuse_reason'];

    // File handling (optional)
    $newFileName = $excuse['attendance_excuse_proof_file'];
    if (isset($_FILES['proof_file']) && $_FILES['proof_file']['error'] === UPLOAD_ERR_OK) {
        $file    = $_FILES['proof_file'];
        $maxSize = 5 * 1024 * 1024; // 5MB
        $allowed = ['jpg','jpeg','png','pdf'];
        $uploadDir = $excuse_proof_dir;

        if (!is_dir($uploadDir)) mkdir($uploadDir, 0775, true);
        if ($file['size'] > $maxSize) throw new Exception("File exceeds 5MB");
        $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
        if (!in_array($ext, $allowed)) throw new Exception("Invalid file type");

        // delete old file if exists
        if ($newFileName && file_exists($uploadDir . $newFileName)) {
            unlink($uploadDir . $newFileName);
        }

        // secure new filename
        $newFileName = "excuse_" . $attendance_excuse_id . "_" . time() . "." . $ext;
        move_uploaded_file($file['tmp_name'], $uploadDir . $newFileName);
    }

    // Update DB
    $stmt = $pdo->prepare("
        UPDATE attendance_excuse 
        SET attendance_excuse_reason = ?, attendance_excuse_proof_file = ?, attendance_excuse_submitted_at = NOW()
        WHERE attendance_excuse_id = ?
    ");
    $stmt->execute([$reason, $newFileName, $attendance_excuse_id]);

    echo json_encode(["status" => "success", "message" => "Excuse updated"]);
} catch (Exception $e) {
    http_response_code(400);
    echo json_encode(["status" => "error", "message" => $e->getMessage()]);
}