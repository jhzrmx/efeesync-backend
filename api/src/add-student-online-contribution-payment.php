<?php
require_once "_connect_to_database.php";
require_once "_middleware.php";
require_once "_upload_dirs.php";

header("Content-Type: application/json");
require_role(["student"]);

$response = ["status" => "error"];

try {
    // --- GET EVENT ID FROM ROUTE PARAM ---
    $event_id = $id ?? null;
    if (!$event_id) {
        http_response_code(400);
        echo json_encode(["status" => "error", "message" => "Missing event ID"]);
        exit;
    }

    // --- GET CURRENT USER (STUDENT) ---
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

    // --- GET EVENT CONTRIBUTION FEE (one-to-one relationship) ---
    $sql = "SELECT ec.event_contri_id, ec.event_contri_fee 
            FROM event_contributions ec
            WHERE ec.event_id = ?";
    $stmt = $pdo->prepare($sql);
    $stmt->execute([$event_id]);
    $eventContri = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$eventContri) {
        throw new Exception("No contribution found for this event.");
    }

    $event_contribution_id = $eventContri["event_contri_id"];
    $amount = $eventContri["event_contri_fee"];

    // --- HANDLE FILE UPLOAD ---
    if (!isset($_FILES["proof"]) || $_FILES["proof"]["error"] !== UPLOAD_ERR_OK) {
        throw new Exception("Please upload a valid proof image.");
    }

    $file = $_FILES["proof"];
    $allowedExts = ["jpg", "jpeg", "png"];
    $maxSize = 2 * 1024 * 1024; // 2MB
    $ext = strtolower(pathinfo($file["name"], PATHINFO_EXTENSION));

    if (!in_array($ext, $allowedExts)) {
        throw new Exception("Invalid file type. Allowed: JPG, PNG.");
    }
    if ($file["size"] > $maxSize) {
        throw new Exception("File too large. Max 2MB allowed.");
    }

    // --- ENSURE UPLOAD DIRECTORY EXISTS ---
    if (!file_exists($payment_proof_dir)) {
        mkdir($payment_proof_dir, 0777, true);
    }

    // --- GENERATE UNIQUE FILE NAME ---
    $uniqueName = uniqid("proof_", true) . "." . $ext;
    $uploadPath = $payment_proof_dir . $uniqueName;

    if (!move_uploaded_file($file["tmp_name"], $uploadPath)) {
        throw new Exception("Failed to upload file.");
    }

    $proofUrl = $uniqueName;

    // --- START TRANSACTION ---
    $pdo->beginTransaction();

    // --- 1️⃣ INSERT INTO contributions_made (status = PENDING) ---
    $sql = "INSERT INTO contributions_made 
            (student_id, event_contri_id, amount_paid, payment_status) 
            VALUES (?, ?, ?, 'PENDING')";
    $stmt = $pdo->prepare($sql);
    $stmt->execute([$student_id, $event_contribution_id, $amount]);
    $contribution_id = $pdo->lastInsertId();

    // --- 2️⃣ INSERT INTO online_payments (status = PENDING) ---
    $sql = "INSERT INTO online_payments (student_id, method, image_proof, payment_date, status)
            VALUES (?, 'GCASH', ?, NOW(), 'PENDING')";
    $stmt = $pdo->prepare($sql);
    $stmt->execute([$student_id, $proofUrl]);
    $online_payment_id = $pdo->lastInsertId();

    // --- 3️⃣ LINK THEM IN online_payment_contributions ---
    $sql = "INSERT INTO online_payment_contributions (online_payment_id, contribution_id)
            VALUES (?, ?)";
    $stmt = $pdo->prepare($sql);
    $stmt->execute([$online_payment_id, $contribution_id]);

    // --- COMMIT TRANSACTION ---
    $pdo->commit();

    // --- SUCCESS RESPONSE ---
    $response["status"] = "success";
    $response["message"] = "Online payment submitted successfully. Waiting for treasurer approval.";
    $response["payment_id"] = $online_payment_id;
    $response["proof_url"] = $proofUrl;

} catch (Exception $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    http_response_code(400);
    $response["message"] = $e->getMessage();
}

echo json_encode($response);