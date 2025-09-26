<?php
require_once "_connect_to_database.php";
require_once "_middleware.php";
require_once "_request.php";

header("Content-Type: application/json");

require_role(["admin", "treasurer"]);

$input = json_request_body();
require_params($input, ["amount_paid"]);

$response = ["status" => "error"];

try {
    if (!isset($id) || (!isset($student_id) && !isset($student_number_id))) {
        throw new Exception("Event ID and Student identifier are required.");
    }

    // ---- Get student_id if student_number_id is used ----
    if (isset($student_number_id)) {
        $stmt = $pdo->prepare("SELECT student_id FROM students WHERE student_number_id = :student_number_id");
        $stmt->execute([":student_number_id" => $student_number_id]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$row) throw new Exception("Student not found.");
        $student_id = $row["student_id"];
    }

    // ---- Get event_contri_id ----
    $stmt = $pdo->prepare("SELECT event_contri_id, event_contri_fee FROM event_contributions WHERE event_id = :event_id");
    $stmt->execute([":event_id" => $id]);
    $eventContri = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$eventContri) throw new Exception("Event contribution not found.");

    $event_contri_id = $eventContri["event_contri_id"];
    $event_contri_fee = $eventContri["event_contri_fee"];

    // ---- Validate input ----
    if ($input["amount_paid"] <= 0) {
        throw new Exception("Paid amount must be greater than zero.");
    }

    $amount_paid = (float)$input["amount_paid"];
    $payment_type = isset($input["payment_type"]) ? strtoupper($input["payment_type"]) : "CASH";
    $online_payment_proof = isset($input["online_payment_proof"]) ? $input["online_payment_proof"] : null;

    // ---- Optional: Prevent overpayment ----
    $stmt = $pdo->prepare("SELECT IFNULL(SUM(amount_paid),0) AS total_paid 
                           FROM contributions_made 
                           WHERE event_contri_id = :event_contri_id AND student_id = :student_id");
    $stmt->execute([":event_contri_id" => $event_contri_id, ":student_id" => $student_id]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    $totalPaid = $row["total_paid"];

    if (($totalPaid + $amount_paid) > $event_contri_fee) {
        throw new Exception("Payment exceeds required contribution fee.");
    }

    // ---- Insert payment ----
    $stmt = $pdo->prepare("
        INSERT INTO contributions_made 
            (event_contri_id, student_id, amount_paid, payment_type, online_payment_proof) 
        VALUES (:event_contri_id, :student_id, :amount_paid, :payment_type, :online_payment_proof)
    ");
    $stmt->execute([
        ":event_contri_id" => $event_contri_id,
        ":student_id" => $student_id,
        ":amount_paid" => $amount_paid,
        ":payment_type" => $payment_type,
        ":online_payment_proof" => $online_payment_proof
    ]);

    $contribution_id = $pdo->lastInsertId();

    $response["status"] = "success";
    $response["message"] = "Contribution added successfully.";
    $response["contribution_id"] = (int)$contribution_id;

} catch (Exception $e) {
    http_response_code(400);
    $response["message"] = $e->getMessage();
}

echo json_encode($response);