<?php
require_once "_connect_to_database.php";
require_once "_current_role.php";
require_once "_snake_to_capital.php";
require_once "./libs/JWTHandler.php";

header("Content-Type: application/json");

if (current_role() != null) {
	echo json_encode(["status" => "success", "message" => "You're already logged in"]);
	exit();
}

$json_post_data = json_decode(file_get_contents("php://input"), true);

$required_parameters = ["email", "password"];

foreach ($required_parameters as $param) {
	if (empty($json_post_data[$param])) {
		echo json_encode(["status" => "error", "message" => snake_to_capital($param)." is required"]);
		exit();
	}
}

$response = [];
$response["status"] = "error";

try {
	$stmt = $pdo->prepare("SELECT `users`.*, `roles`.* FROM `users`
		JOIN `roles` on `users`.`role_id` = `roles`.`role_id`
		WHERE `institutional_email` = :institutional_email
		LIMIT 1
	");
	$stmt->bindParam(":institutional_email", $json_post_data["email"]);
	$stmt->execute();
	$rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
	if (count($rows) > 0) {
		$first_result = $rows[0];
		if (password_verify($json_post_data["password"], $first_result["password"])) {
			$payload = [
				"user_id" => $first_result["user_id"],
				"role_id" => $first_result["role_id"],
				"role" => $first_result["role_name"],
				"exp" => time() + (3600 * 24 * 15), // 15 days
				"nbf" => time(),
			];
			$jwt = new JWTHandler($_ENV["EFEESYNC_JWT_SECRET"]);
			$login_token = $jwt->createToken($payload);
			setcookie(
				"basta",
				$login_token, [
					"expires" => $payload["exp"],
					"path" => "/",
					"secure" => $_ENV["EFEESYNC_IS_PRODUCTION"],
					"httponly" => true,
					"samesite" => $_ENV["EFEESYNC_IS_PRODUCTION"] ? "Strict" : "Lax"
				]
			);
			$response["status"] = "success";
			$response["message"] = "Login successful";
			$response["data"] = [
			    "current_user_id" => $first_result["user_id"],
				"current_role_id" => $first_result["role_id"],
				"current_role" => $first_result["role_name"]
			];
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
