<?php
require_once "_connect_to_database.php";
require_once "_middleware.php";
require_once "_request.php";

header("Content-Type: application/json");

require_login();

$json_put_data = json_request_body();
require_params($json_put_data, ["institutional_email", "first_name", "last_name"]);

$response = ["status" => "error"];

try {
	$current_user_id = current_jwt_payload()['user_id'];

	$sql = "UPDATE users 
	        SET institutional_email = :email,
	            first_name = :first_name,
	            last_name = :last_name,
	            middle_initial = :middle_initial,
	            picture = :picture
	        WHERE user_id = :user_id";

	$stmt = $pdo->prepare($sql);
	$stmt->execute([
		":email" => $json_put_data["institutional_email"],
		":first_name" => $json_put_data["first_name"],
		":last_name" => $json_put_data["last_name"],
		":middle_initial" => $json_put_data["middle_initial"] ?? null,
		":picture" => $json_put_data["picture"] ?? "default.jpg",
		":user_id" => $current_user_id
	]);

	if ($stmt->rowCount() > 0) {
		$response["status"] = "success";
		$response["message"] = "User updated successfully";
	} else {
		$response["message"] = "No changes made";
	}
} catch (Exception $e) {
	http_response_code(500);
	$response["message"] = $e->getMessage();
}

echo json_encode($response);