<?php
require_once "_connect_to_database.php";
require_once "_middleware.php";
require_once "_request.php";

header("Content-Type: application/json");

require_role("treasurer");

$response = ["status" => "error"];

try {	
    $deleted = 0;

    if (isset($id)) {
        // --- Single delete ---
        $sql = "DELETE FROM events WHERE event_id = :event_id";
        if (isset($organization_id)) {
            $sql .= "AND organization_id = :org_id";
        }
        $stmt = $pdo->prepare($sql);
        $stmt->bindParam(":event_id", $id);
        if (isset($organization_id)) {
            $stmt->bindParam(":org_id", $organization_id);
        }
        $stmt->execute();
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
	
	$response["status"] = "success";
	$response["deleted"] = $deleted;
	$response["message"] = $deleted > 0 ? "Successfully deleted $deleted event(s)." : "No events deleted.";

} catch (Exception $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    http_response_code(400);
	$response["message"] = $e->getMessage();
}

echo json_encode($response);