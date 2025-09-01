<?php
require_once "_connect_to_database.php";
require_once "_current_role.php";
require_once "_upload_dirs.php";

header("Content-Type: application/json");

if (!is_current_role_in(['admin', 'treasurer'])) {
	http_response_code(403);
	echo json_encode(["status" => "error", "message" => "Forbidden"]);
	exit();
}

$response = ["status" => "error"];

try {
	if (!isset($_FILES["logo"])) throw new Exception("No file uploaded.");

	// Get current logo
	$stmt = $pdo->prepare("SELECT organization_logo FROM organizations WHERE organization_id = ?");
	$stmt->execute([$id]);
	$org = $stmt->fetch(PDO::FETCH_ASSOC);
	if (!$org) throw new Exception("Organization not found.");
	$old_logo = $org["organization_logo"];

	// Security checks
	$file = $_FILES["logo"];
	$allowed_types = ["image/jpeg", "image/png", "image/webp"];
	if (!in_array($file["type"], $allowed_types)) throw new Exception("Invalid file type.");
	if ($file["size"] > 2 * 1024 * 1024) throw new Exception("File too large. Max 2MB.");

	$ext = pathinfo($file["name"], PATHINFO_EXTENSION);
	$filename = "org_" . time() . "." . $ext;
	$target_path = $org_logo_dir . $filename;

	if (!move_uploaded_file($file["tmp_name"], $target_path)) {
		throw new Exception("Failed to upload file.");
	}

	// Delete old logo if not default
	if ($old_logo !== "default.jpg") {
		$old_path = $org_logo_dir . $old_logo;
		if (file_exists($old_path)) unlink($old_path);
	}

	// Update DB
	$stmt = $pdo->prepare("UPDATE organizations SET organization_logo = ? WHERE organization_id = ?");
	$stmt->execute([$filename, $id]);

	$response["status"] = "success";
	$response["filename"] = $filename;

} catch (Exception $e) {
	http_response_code(400);
	$response["message"] = $e->getMessage();
}

echo json_encode($response);
