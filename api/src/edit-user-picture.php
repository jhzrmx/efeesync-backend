<?php
require_once "_connect_to_database.php";
require_once "_middleware.php";
require_once "_request.php";
require_once "_upload_dirs.php";

header("Content-Type: application/json");

require_login();

$response = ["status" => "error"];

try {
	if (!isset($_FILES["picture"])) throw new Exception("No file uploaded.");

	$current_user_id = current_jwt_payload()['user_id'];

	$stmt = $pdo->prepare("SELECT picture FROM users WHERE user_id = ?");
	$stmt->execute([$current_user_id]);
	$user = $stmt->fetch(PDO::FETCH_ASSOC);
	if (!$user) throw new Exception("User not found.");
	$old_picture = $user["picture"];

	$file = $_FILES["picture"];
	$allowed_types = ["image/jpeg", "image/png", "image/webp"];
	if (!in_array($file["type"], $allowed_types)) throw new Exception("Invalid file type.");
	if ($file["size"] > 2 * 1024 * 1024) throw new Exception("File too large. Max 2MB.");

	$ext = pathinfo($file["name"], PATHINFO_EXTENSION);
	$filename = "user_" . $current_user_id . "_" . time() . "." . $ext;
	$target_path = $user_pic_dir . $filename;

	if (!move_uploaded_file($file["tmp_name"], $target_path)) {
		throw new Exception("Failed to upload file.");
	}

	if ($old_picture !== "default.jpg") {
		$old_path = $user_pic_dir . $old_picture;
		if (file_exists($old_path)) unlink($old_path);
	}

	$stmt = $pdo->prepare("UPDATE users SET picture = ? WHERE user_id = ?");
	$stmt->execute([$filename, $current_user_id]);

	$response["status"] = "success";
	$response["filename"] = $filename;

} catch (Exception $e) {
	http_response_code(400);
	$response["message"] = $e->getMessage();
}

echo json_encode($response);