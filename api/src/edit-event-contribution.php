<?php
require_once "_connect_to_database.php";
require_once "_middleware.php";
require_once "_request.php";

header("Content-Type: application/json");

require_role("treasurer");

$response = ["status" => "error"];

try {
    if (!isset($organization_id)) throw new Exception("Missing organization identifier in URL.");
    if (!isset($event_id)) throw new Exception("Missing event identifier in URL.");
    if (!isset($date)) throw new Exception("Missing contribution date in URL.");

    $json_put_data = json_request_body();
    if (!$json_put_data || !isset($json_put_data["event_contri_fee"])) {
        throw new Exception("Invalid JSON payload.");
    }

    $pdo->beginTransaction();

    // Delete existing contribution for this date
    $stmt = $pdo->prepare("DELETE FROM event_contributions WHERE event_id = :event_id AND event_contri_due_date = :date");
    $stmt->execute([
        ":event_id" => $event_id,
        ":date" => $date
    ]);

    // Insert new contribution
    $stmt = $pdo->prepare("
        INSERT INTO event_contributions (event_contri_due_date, event_contri_fee, event_contri_sanction_fee, event_id)
        VALUES (:date, :fee, :sanction_fee, :event_id)
    ");

    $sanction_fee = isset($json_put_data["event_contri_sanction_fee"]) ? $json_put_data["event_contri_sanction_fee"] : 0;

    $stmt->execute([
        ":date" => $date,
        ":fee" => $json_put_data["event_contri_fee"],
        ":sanction_fee" => $sanction_fee,
        ":event_id" => $event_id
    ]);

    $pdo->commit();
    $response["status"] = "success";
    $response["message"] = "Event contribution updated successfully.";

} catch (Exception $e) {
    if ($pdo->inTransaction()) $pdo->rollBack();
    http_response_code(400);
    $response["message"] = $e->getMessage();
}

echo json_encode($response);