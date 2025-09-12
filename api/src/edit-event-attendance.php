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
    if (!isset($date)) throw new Exception("Missing attendance date in URL.");

    $json_put_data = json_request_body();
    if (!$json_put_data || !isset($json_put_data["event_attend_time"])) {
        throw new Exception("Invalid JSON payload.");
    }

    // event_attend_time is an array like ["AM IN", "AM OUT"]
    $pdo->beginTransaction();

    // Delete existing attendance records for this event and date
    $stmt = $pdo->prepare("DELETE FROM event_attendance WHERE event_id = :event_id AND event_attend_date = :date");
    $stmt->execute([
        ":event_id" => $event_id,
        ":date" => $date
    ]);

    // Insert new attendance rows based on the specified times
    $stmt = $pdo->prepare("
        INSERT INTO event_attendance (event_attend_date, event_attend_time, event_attend_sanction_fee, event_id)
        VALUES (:date, :time_type, :sanction_fee, :event_id)
    ");

    $sanction_fee = isset($json_put_data["event_attend_sanction_fee"]) ? $json_put_data["event_attend_sanction_fee"] : 0;

    foreach ($json_put_data["event_attend_time"] as $time_type) {
        $stmt->execute([
            ":date" => $date,
            ":time_type" => $time_type,
            ":sanction_fee" => $sanction_fee,
            ":event_id" => $event_id
        ]);
    }

    $pdo->commit();
    $response["status"] = "success";
    $response["message"] = "Event attendance updated successfully.";

} catch (Exception $e) {
    if ($pdo->inTransaction()) $pdo->rollBack();
    http_response_code(400);
    $response["message"] = $e->getMessage();
}

echo json_encode($response);