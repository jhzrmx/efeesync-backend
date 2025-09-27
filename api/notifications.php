<?php
require_once "libs/EnvLoader.php";
EnvLoader::loadFromFile("../.env");

require_once "src/_connect_to_database.php";
require_once "src/_middleware.php";

header("Content-Type: text/event-stream");
header("Cache-Control: no-cache");
header("Connection: keep-alive");
header("X-Accel-Buffering: no");

set_time_limit(0);

require_login();

// Define logged-in user
$current_user_id   = current_jwt_payload()['user_id'];   // numeric user_id
$current_role_name = current_jwt_payload()['role'];      // "admin" | "student" | "treasurer"
$current_org_code  = current_jwt_payload()['org_code'];  // org code if logged in as org/treasurer (null if admin)
$current_dept_code = current_jwt_payload()['dept_code']; // dept code (nullable)

// resolve role_id + organization_id
$stmt = $pdo->prepare("SELECT role_id FROM roles WHERE role_name = ?");
$stmt->execute([$current_role_name]);
$current_role_id = $stmt->fetchColumn();

$current_org_id = null;
if ($current_org_code) {
    $stmt = $pdo->prepare("SELECT organization_id FROM organizations WHERE organization_code = ?");
    $stmt->execute([$current_org_code]);
    $current_org_id = $stmt->fetchColumn();
}

// Get current student's year level
$stmt = $pdo->prepare("SELECT LEFT(student_section, 1) AS year_level FROM students WHERE user_id = ?");
$stmt->execute([$current_user_id]);
$student = $stmt->fetch(PDO::FETCH_ASSOC);
$current_year_level = $student ? $student['year_level'] : null;

while (true) {
    // Fetch notifications for this user
    // Include those notification if already read or not
    $stmt = $pdo->prepare("
        SELECT n.notification_id, n.notification_type, n.notification_content,
               n.url_redirect, n.created_at,
               CASE WHEN nr.read_id IS NULL THEN 0 ELSE 1 END AS is_read
        FROM notifications n
        JOIN notification_targets nt ON n.notification_id = nt.notification_id
        LEFT JOIN notification_reads nr
               ON n.notification_id = nr.notification_id
              AND nr.user_id = :user_id
        WHERE (
            (nt.scope = 'user' AND nt.user_id = :user_id)
            OR (nt.scope = 'role' AND nt.role_id = :role_id)
            OR (nt.scope = 'org' AND nt.organization_id = :org_id
                AND FIND_IN_SET(:year_level, nt.year_levels))
            OR (nt.scope = 'global' AND FIND_IN_SET(:year_level, nt.year_levels))
        )
        ORDER BY n.created_at DESC
        LIMIT 20
    ");
    $stmt->execute([
        ":user_id"    => $current_user_id,
        ":role_id"    => $current_role_id ?? null,
        ":org_id"     => $current_org_id ?? null,
        ":year_level" => $current_year_level
    ]);
    $notifications = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Unread count
    $sqlUnread = "
        SELECT COUNT(*) AS unread_count
        FROM notifications n
        JOIN notification_targets nt 
             ON n.notification_id = nt.notification_id
        LEFT JOIN notification_reads nr 
             ON n.notification_id = nr.notification_id
            AND nr.user_id = :uid
        WHERE 
            (nt.scope = 'user' AND nt.user_id = :uid)
            OR (nt.scope = 'role' AND nt.role_id = :rid)
            OR (nt.scope = 'org'  AND nt.organization_id = :oid)
            OR (nt.scope = 'global')
          AND nr.read_id IS NULL
    ";
    $stmtUnread = $pdo->prepare($sqlUnread);
    $stmtUnread->execute([
        ":uid" => $current_user_id,
        ":rid" => $current_role_id,
        ":oid" => $current_org_id
    ]);
    $unread = $stmtUnread->fetch(PDO::FETCH_ASSOC);

    // Send payload via SSE
    $payload = [
        "notifications" => $notifications,
        "unread_count"  => $unread['unread_count'] ?? 0
    ];

    echo "event: notification\n";
    echo "data: " . json_encode($payload) . "\n\n";
    ob_flush();
    flush();

    sleep(3);

    if (connection_aborted()) break;
}