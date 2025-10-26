<?php
require_once "_connect_to_database.php";
require_once "_middleware.php";
require_once "_request.php";

header("Content-Type: application/json");

require_role(["treasurer"]); // Only treasurer can approve/reject

$response = ["status" => "error"];

try {
    $online_payment_id = intval($id); // :id from route
    $action = strtolower($action ?? ""); // :action from route

    if (!$online_payment_id) {
        throw new Exception("Invalid payment ID.");
    }

    // --- Validate action ---
    if (!in_array($action, ["approve", "reject"])) {
        throw new Exception("Invalid action. Must be 'approve' or 'reject'.");
    }

    $new_status = strtoupper($action) === "APPROVE" ? "APPROVED" : "REJECTED";

    // --- Check if online payment exists ---
    $check_sql = "SELECT * FROM online_payments WHERE online_payment_id = ?";
    $check_stmt = $pdo->prepare($check_sql);
    $check_stmt->execute([$online_payment_id]);
    $payment = $check_stmt->fetch(PDO::FETCH_ASSOC);

    if (!$payment) {
        throw new Exception("Online payment not found.");
    }

    if ($payment["status"] !== "PENDING") {
        throw new Exception("This payment is already {$payment['status']}.");
    }

    // --- Start transaction ---
    $pdo->beginTransaction();

    // --- Update online payment status ---
    $update_payment_sql = "UPDATE online_payments SET status = ?, payment_date = NOW() WHERE online_payment_id = ?";
    $update_payment_stmt = $pdo->prepare($update_payment_sql);
    $update_payment_stmt->execute([$new_status, $online_payment_id]);

    // --- Get all related contributions ---
    $get_contri_sql = "
        SELECT cm.contribution_id 
        FROM online_payment_contributions opc
        JOIN contributions_made cm ON cm.contribution_id = opc.contribution_id
        WHERE opc.online_payment_id = ?
    ";
    $get_contri_stmt = $pdo->prepare($get_contri_sql);
    $get_contri_stmt->execute([$online_payment_id]);
    $contributions = $get_contri_stmt->fetchAll(PDO::FETCH_COLUMN);

    if (!empty($contributions)) {
        // --- Update related contributions_made payment_status ---
        $in_placeholders = implode(",", array_fill(0, count($contributions), "?"));
        $update_contri_sql = "UPDATE contributions_made SET payment_status = ? WHERE contribution_id IN ($in_placeholders)";
        $update_contri_stmt = $pdo->prepare($update_contri_sql);
        $update_contri_stmt->execute(array_merge([$new_status], $contributions));
    }

    $pdo->commit();

    // --- Success response ---
    $response["status"] = "success";
    $response["message"] = "Online contribution payment has been {$new_status}.";
    $response["data"] = [
        "online_payment_id" => $online_payment_id,
        "new_status" => $new_status
    ];

} catch (Exception $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    http_response_code(400);
    $response["message"] = $e->getMessage();
}

echo json_encode($response, JSON_PRETTY_PRINT);