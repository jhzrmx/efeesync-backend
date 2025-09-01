<?php 
require_once "_connect_to_database.php";
require_once "_current_role.php";

header("Content-Type: application/json");

if (current_role() == null) {
	http_response_code(403);
	echo json_encode(["status" => "error", "message" => "Forbidden"]);
	exit();
}

$response = [];
$response["status"] = "error";

try {
	$current_user_id = current_jwt_payload()['user_id'];
	$stmt = $pdo->prepare("SELECT `users`.`user_id`, `institutional_email`, `last_name`, `first_name`, `middle_initial`, `picture`, `role_name`
		FROM `users`
		-- LEFT JOIN `students` ON `students`.`user_id` = `users`.`user_id`
		JOIN `roles` ON `roles`.`role_id` = `users`.`role_id`
		WHERE `users`.`user_id` = :user_id
		LIMIT 1
	");
	$stmt->bindParam(":user_id", $current_user_id);
	$stmt->execute();
	$data = $stmt->fetchAll(PDO::FETCH_ASSOC);
	$response["status"] = "success";
	$response["data"] = $data;
} catch (Exception $e) {
	$response["message"] = $e->getMessage();
}

echo json_encode($response);
