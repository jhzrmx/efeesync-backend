<?php
require_once "_connect_to_database.php";
require_once "_middleware.php";
require_once "_upload_dirs.php";

header("Content-Type: application/json");

// Require student role
require_role(["student"]);

$attendance_excuse_id = $id ?? null;

try {
    if (!$attendance_excuse_id) {
        throw new Exception("Missing excuse ID");
    }

    // Fetch excuse
    $stmt = $pdo->prepare("SELECT * FROM attendance_excuse WHERE attendance_excuse_id = ?");
    $stmt->execute([$attendance_excuse_id]);
    $excuse = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$excuse) throw new Exception("Excuse not found");

    // delete file if exists
    $uploadDir = $excuse_proof_dir;
    if ($excuse['attendance_excuse_proof_file'] && file_exists($uploadDir . $excuse['attendance_excuse_proof_file'])) {
        unlink($uploadDir . $excuse['attendance_excuse_proof_file']);
    }

    // delete row
    $stmt = $pdo->prepare("DELETE FROM attendance_excuse WHERE attendance_excuse_id = ?");
    $stmt->execute([$attendance_excuse_id]);

    echo json_encode(["status" => "success", "message" => "Excuse deleted"]);
} catch (Exception $e) {
    http_response_code(400);
    echo json_encode(["status" => "error", "message" => $e->getMessage()]);
}