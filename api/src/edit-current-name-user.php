<?php
require_once "_connect_to_database.php";
require_once "_generate_email.php";
require_once "_middleware.php";
require_once "_request.php";

header("Content-Type: application/json");

require_login();

$json = json_request_body();

$response = ["status" => "error"];

try {
	$user_id = current_jwt_payload()["user_id"];
	if (!$user_id) throw new Exception("Missing User ID");

	$first_name = $json["first_name"] ?? null;
	$last_name = $json["last_name"] ?? null;
	$mid = $json["middle_initial"] ?? null;

	$pdo->beginTransaction();

	// Update users table
	if ($first_name && $last_name) {
		$email = generate_email($first_name, $last_name);

		// Check if new email already exists (and not from this user)
		$stmt = $pdo->prepare("SELECT user_id FROM users WHERE institutional_email = ? AND user_id != ?");
		$stmt->execute([$email, $user_id]);

		if ($stmt->rowCount() > 0) {
			echo json_encode(["status" => "error", "message" => "Email already exists: $email"]);
			exit();
		}

		$stmt = $pdo->prepare("UPDATE users SET first_name=?, last_name=?, middle_initial=?, institutional_email=? WHERE user_id=?");
		$stmt->execute([$first_name, $last_name, $mid, $email, $user_id]);
	}

	$pdo->commit();

	echo json_encode([
		"status" => "success",
		"new_email" => isset($email) ? $email : "unchanged"
	]);

} catch (Exception $e) {
	$pdo->rollBack();
	http_response_code(500);
	echo json_encode(["status" => "error", "message" => $e->getMessage()]);
}