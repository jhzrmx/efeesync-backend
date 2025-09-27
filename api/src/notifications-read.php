<?php
require_once "_connect_to_database.php";
require_once "_middleware.php";
require_once "_request.php";

require_login();

$current_user_id = current_jwt_payload()['user_id'];


$response = ["status" => "error"];

try {
    if (isset($id)) {
        // Mark a single notification
        $notification_id = intval($id);
        $stmt = $pdo->prepare("
            INSERT INTO notification_reads (notification_id, user_id, read_at)
            VALUES (:notification_id, :user_id, NOW())
            ON DUPLICATE KEY UPDATE read_at = NOW()
        ");
        $stmt->execute([
            ":notification_id" => $notification_id,
            ":user_id" => $current_user_id
        ]);

        $response["status"] = "success";
        $response["message"] = "Notification marked as read";
    } else {
        // Mark ALL notifications for this user as read
        $stmt = $pdo->prepare("
            INSERT INTO notification_reads (notification_id, user_id, read_at)
            SELECT n.notification_id, :user_id, NOW()
            FROM notifications n
            JOIN notification_targets nt ON n.notification_id = nt.notification_id
            WHERE 
                (nt.scope = 'user' AND nt.user_id = :user_id)
                OR (nt.scope = 'role' AND nt.role_id = :role_id)
                OR (nt.scope = 'org' AND nt.organization_id = :org_id)
                OR (nt.scope = 'global')
            ON DUPLICATE KEY UPDATE read_at = NOW()
        ");

        $stmt->execute([
            ":user_id" => $current_user_id,
            ":role_id" => current_jwt_payload()['role_id'] ?? null,
            ":org_id"  => current_jwt_payload()['org_id'] ?? null
        ]);

        $response["status"] = "success";
        $response["message"] = "All notifications marked as read";
    }
} catch (Exception $e) {
    http_response_code(500);
    $response["message"] = $e->getMessage();
}

echo json_encode($response);