<?php
require_once "_connect_to_database.php";
require_once "_middleware.php";

header("Content-Type: application/json");

require_role("admin");

$response = ["status" => "error"];

try {
	if (!isset($id)) throw new Exception("Missing identifier in URL");
	// Fetch logo file first
	$stmt = $pdo->prepare("SELECT designation FROM organization_officers WHERE organization_officer_id = ?");
	$stmt->execute([$id]);
	$org = $stmt->fetch(PDO::FETCH_ASSOC);

	if (!$org) throw new Exception("Organization officer not found.");

	// Delete record
	$stmt = $pdo->prepare("DELETE FROM organization_officers WHERE organization_officer_id = ?");
	$stmt->execute([$id]);

	$response["status"] = "success";
	$response["message"] = "Organization officer deleted successfully.";

} catch (Exception $e) {
	http_response_code(400);
	$response["message"] = $e->getMessage();
}

echo json_encode($response);
