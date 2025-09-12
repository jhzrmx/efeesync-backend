<?php
require_once "_connect_to_database.php";
require_once "_middleware.php";
require_once "_request.php";

header("Content-Type: application/json");

require_role("treasurer");

$response = ["status" => "error"];

try {
    if (!isset($organization_id)) {
        throw new Exception("Missing organization identifier in URL.");
    }
    if (!isset($event_id)) {
        throw new Exception("Missing event identifier in URL.");
    }

    $json_put_data = json_request_body();
    if (!$json_put_data) {
        throw new Exception("Invalid JSON payload.");
    }
	
    $stmt = $pdo->prepare("
        UPDATE events 
        SET 
            event_name = :name,
            event_description = :description,
            event_target_year_levels = :target_years,
            event_start_date = :start_date,
            event_end_date = :end_date,
            event_sanction_has_comserv = :has_comserv
        WHERE event_id = :event_id 
        AND organization_id = :org_id
    ");

    $stmt->execute([
        ":name"        => trim($json_put_data["event_name"]),
        ":description" => trim($json_put_data["event_description"]),
        ":target_years"=> implode(",", $json_put_data["event_target_year_levels"]),
        ":start_date"  => $json_put_data["event_start_date"],
        ":end_date"    => $json_put_data["event_end_date"],
        ":has_comserv" => isset($json_put_data["event_sanction_has_comserv"]) ? (int)$json_put_data["event_sanction_has_comserv"] : 0,
        ":event_id"    => $event_id,
        ":org_id"      => $organization_id
    ]);
	
    $response["status"] = "success";
	$response["message"] = "Event updated successfully.";

} catch (Exception $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    http_response_code(400);
	$response["message"] = $e->getMessage();
}

echo json_encode($response);