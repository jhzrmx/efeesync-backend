<?php
require_once "_connect_to_database.php";
require_once "_middleware.php";

header("Content-Type: application/json");

require_role("admin");

$response = ["status" => "error"];

try {
	// Fetch logo file first
	$stmt = $pdo->prepare("SELECT organization_logo FROM organizations WHERE organization_id = ?");
	$stmt->execute([$id]);
	$org = $stmt->fetch(PDO::FETCH_ASSOC);

	if (!$org) throw new Exception("Organization not found.");

	$logo = $org["organization_logo"];
	$logo_path = "uploads/organization_logos/" . $logo;

	// Delete file if not default
	if ($logo !== "default.jpg" && file_exists($logo_path)) {
		unlink($logo_path);
	}

	// Delete record
	$stmt = $pdo->prepare("DELETE FROM organizations WHERE organization_id = ?");
	$stmt->execute([$id]);

	$response["status"] = "success";
	$response["message"] = "Organization deleted successfully.";

} catch (Exception $e) {
	http_response_code(400);
	$response["message"] = $e->getMessage();
}

echo json_encode($response);
