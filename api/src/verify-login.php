<?php
require_once "./libs/JWTHandler.php";

header("Content-Type: application/json");

$response = ["status" => "error"];

$login_token = empty($_COOKIE["basta"]) ? "" : trim($_COOKIE["basta"]);

$jwt = new JWTHandler($_ENV["EFEESYNC_JWT_SECRET"]);
$validation = $jwt->validateToken($login_token);

if ($validation["is_valid"]) {
	$response["status"] = "success";
	$response["current_user_id"] = $validation["payload"]["user_id"];
	$response["current_dept_code"] = $validation["payload"]["dept_code"];
	$response["current_org_code"] = $validation["payload"]["org_code"];
	$response["current_role"] = $validation["payload"]["role"];
} else {
	$response["current_user_id"] = 0;
	$response["current_dept_code"] = null;
	$response["current_org_code"] = null;
	$response["current_role"] = null;
}

echo json_encode($response);