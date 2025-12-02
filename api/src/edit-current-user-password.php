<?php
require_once "_connect_to_database.php";
require_once "_middleware.php";
require_once "_request.php";

header("Content-Type: application/json");

require_login();

$json_put_data = json_request_body();
require_params($json_put_data, ["old_password", "new_password"]);

$response = ["status" => "error"];

if($json_put_data["old_password"] == $json_put_data["new_password"]) {
	echo json_encode(["status" => "error", "New password is the same as old password"]);
	exit();
}

try {
	$current_user_id = current_jwt_payload()['user_id'];
	$stmt = $pdo->prepare("SELECT `users`.*, `roles`.* FROM `users`
		JOIN `roles` on `users`.`role_id` = `roles`.`role_id`
		WHERE `users`.`user_id` = :current_user_id
		LIMIT 1
	");

	$stmt->bindParam(":current_user_id", $current_user_id);
	$stmt->execute();
	$rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

	if (count($rows) > 0) {
		$first_result = $rows[0];
		if (password_verify($json_put_data["old_password"], $first_result["password"])) {
			$stmt_update = $pdo->prepare("UPDATE `users` SET `password` = :new_password WHERE `user_id` = :current_user_id");
			$hashed_password = password_hash($json_put_data["new_password"], PASSWORD_DEFAULT);
			$stmt_update->bindParam(":new_password", $hashed_password);
			$stmt_update->bindParam(":current_user_id", $current_user_id);
			$stmt_update->execute();
			$response["status"] = "success";
		} else {
			$response["message"] = "Wrong password";
		}
	} else {
		$response["message"] = "User not found";
	}
} catch (Exception $e) {
	$response["message"] = $e->getMessage();
}

echo json_encode($response);