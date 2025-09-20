<?php
require_once "_connect_to_database.php";
require_once "_middleware.php";

header("Content-Type: text/event-stream");
header("Cache-Control: no-cache");
header("Connection: keep-alive");
header("X-Accel-Buffering: no");

set_time_limit(0);

require_login();

$current_user_id = current_jwt_payload()['user_id'];
$current_role = current_jwt_payload()['role'];

while (true) {
    $stmt = $pdo->prepare("
        SELECT n.notification_id, n.notification_type, n.notification_for,
               n.notification_scope, n.notification_content, n.url_redirect,
               n.created_at
        FROM notifications n
        WHERE 
            (
                n.notification_scope = 'global'
                OR (n.notification_scope = 'role' AND n.notification_for = :role)
                OR (n.notification_scope = 'user' AND n.user_id = :user_id AND n.notification_read = 0)
            )
        ORDER BY n.created_at DESC
        LIMIT 10
    ");

    $stmt->execute([
        ":role" => $current_role,
        ":user_id" => $current_user_id
    ]);

    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

    if ($rows) {
        echo "event: notification\n";
        echo "data: " . json_encode($rows) . "\n\n";
        ob_flush();
        flush();
    }

    sleep(5);

    if (connection_aborted()) break;
}