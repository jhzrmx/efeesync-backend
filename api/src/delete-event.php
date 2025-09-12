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

    $deleted = 0;

    if (isset($event_id)) {
        // --- Single delete ---
        $stmt = $pdo->prepare("DELETE FROM events WHERE event_id = :event_id AND organization_id = :org_id");
        $stmt->execute([
            ":event_id" => $event_id,
            ":org_id"   => $organization_id
        ]);
        $deleted = $stmt->rowCount();
    } else {
        // --- Multi delete ---
        $json_delete_data = json_request_body();
        if (empty($json_delete_data["event_ids"]) || !is_array($json_delete_data["event_ids"])) {
            throw new Exception("Missing or invalid 'event_ids' in request body.");
        }

        $ids = array_map("intval", $json_delete_data["event_ids"]);
        $placeholders = implode(",", array_fill(0, count($ids), "?"));

        $stmt = $pdo->prepare("DELETE FROM events WHERE event_id IN ($placeholders) AND organization_id = ?");
        $ids[] = $organization_id;
        $stmt->execute($ids);
        $deleted = $stmt->rowCount();
    }
	
	$response["message"] = "success";
	$response["deleted"] = $deleted;
	$response["message"] = $deleted > 0 ? "Successfully deleted $deleted event(s)." : "No events deleted.";

} } catch (Exception $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    http_response_code(400);
	$response["message"] = $e->getMessage();
}

echo json_encode($response);