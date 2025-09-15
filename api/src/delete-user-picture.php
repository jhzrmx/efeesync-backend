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
	// Get current logo
	$stmt = $pdo->prepare("SELECT picture FROM users WHERE user_id = ?");
	$stmt->execute([$id]);
	$user = $stmt->fetch(PDO::FETCH_ASSOC);
	if (!$user) throw new Exception("User not found.");

	$logo = $user["organization_logo"];
	if ($logo === "default.jpg") throw new Exception("Cannot delete default logo.");

	$path = $user_pic_dir . $logo;
	if (file_exists($path)) unlink($path);

	// Revert to default
	$stmt = $pdo->prepare("UPDATE users SET picture = 'default.jpg' WHERE user_id = ?");
	$stmt->execute([$id]);

	$response["status"] = "success";
	$response["message"] = "Logo deleted and reset to default.";

} catch (Exception $e) {
	http_response_code(400);
	$response["message"] = $e->getMessage();
}

echo json_encode($response);
