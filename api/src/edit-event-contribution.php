<?php
require_once "_connect_to_database.php";
require_once "_middleware.php";
require_once "_request.php";

header("Content-Type: application/json");
require_role(["admin", "treasurer"]);

$response = ["status" => "error"];

try {
    $data = json_request_body();
    require_params($data, ["event_contri_due_date", "event_contri_fee", "event_contri_sanction_fee"]);

    // check if event exists
    $stmt = $pdo->prepare("SELECT event_id FROM events WHERE event_id = :id AND organization_id = :org_id LIMIT 1");
    $stmt->execute([":id" => $id, ":org_id" => $organization_id]);
    $event = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$event) {
        echo json_encode(["status" => "error", "message" => "Event not found"]);
        exit();
    }

    // check if contribution already has payments
    $stmt_check = $pdo->prepare("
        SELECT COUNT(*) FROM paid_contribution_sanctions pcs
        JOIN event_contributions ec ON pcs.event_contri_id = ec.event_contri_id
        WHERE ec.event_id = :event_id
    ");
    $stmt_check->execute([":event_id" => $id]);
    $hasPayments = $stmt_check->fetchColumn() > 0;

    if ($hasPayments) {
        echo json_encode(["status" => "error", "message" => "Cannot edit contribution, payments already recorded"]);
        exit();
    }

    // upsert contribution
    $stmt_upsert = $pdo->prepare("
        INSERT INTO event_contributions (event_id, event_contri_due_date, event_contri_fee, event_contri_sanction_fee)
        VALUES (:event_id, :due_date, :fee, :sanction_fee)
        ON DUPLICATE KEY UPDATE
            event_contri_due_date = VALUES(event_contri_due_date),
            event_contri_fee = VALUES(event_contri_fee),
            event_contri_sanction_fee = VALUES(event_contri_sanction_fee)
    ");
    $stmt_upsert->execute([
        ":event_id" => $id,
        ":due_date" => $data["event_contri_due_date"],
        ":fee" => $data["event_contri_fee"],
        ":sanction_fee" => $data["event_contri_sanction_fee"]
    ]);

    $response["status"] = "success";
    $response["message"] = "Event contribution updated successfully";
} catch (Exception $e) {
    $response["message"] = $e->getMessage();
}

echo json_encode($response);