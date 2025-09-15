<?php
require_once "_connect_to_database.php";
require_once "_middleware.php";
require_once "_upload_dirs.php";
require_once "_request.php";

header("Content-Type: application/json");

require_role(["admin", "treasurer"]));

$json_post_data = json_request_body();

$response = ["status" => "error"];

try {
	// Get current logo
	$stmt = $pdo->prepare("SELECT organization_logo FROM organizations WHERE organization_id = ?");
	$stmt->execute([$id]);
	$org = $stmt->fetch(PDO::FETCH_ASSOC);
	if (!$org) throw new Exception("Organization not found.");

	$logo = $org["organization_logo"];
	if ($logo === "default.jpg") throw new Exception("Cannot delete default logo.");

	$path = $org_logo_dir . $logo;
	if (file_exists($path)) unlink($path);

	// Revert to default
	$stmt = $pdo->prepare("UPDATE organizations SET organization_logo = 'default.jpg' WHERE organization_id = ?");
	$stmt->execute([$id]);

	$response["status"] = "success";
	$response["message"] = "Logo deleted and reset to default.";

} catch (Exception $e) {
	http_response_code(400);
	$response["message"] = $e->getMessage();
}

echo json_encode($response);
