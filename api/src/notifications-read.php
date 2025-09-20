<?php
require_once "_connect_to_database.php";
require_once "_middleware.php";
require_once "_request.php";

require_login();

$current_user_id = current_jwt_payload()['user_id'];

if (!isset($id)) {
    http_response_code(400);
    echo json_encode(["status" => "error", "message" => "Missing notification_id"]);
    exit;
}

$stmt = $pdo->prepare("UPDATE notifications SET notification_read = 1 WHERE notification_id = :id AND user_id = :user_id");
$stmt->execute([
    ":id" => $_POST["notification_id"],
    ":user_id" => $user_id
]);

echo json_encode(["status" => "success"]);