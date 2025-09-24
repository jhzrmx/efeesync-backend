<?php
require_once "_connect_to_database.php";
require_once "_middleware.php";

header("Content-Type: application/json");

require_role("student");

$response = ["status" => "error"];

try {
    $current_user_id = current_jwt_payload()['user_id'];

    // Get student info
    $sql = "SELECT s.student_id, s.student_current_program
            FROM users u
            LEFT JOIN students s ON s.user_id = u.user_id
            WHERE u.user_id = ?";

    $stmt = $pdo->prepare($sql);
    $stmt->execute([$current_user_id]);
    $student = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$student || !$student['student_id']) {
        throw new Exception("The user is not a student");
    }

    // Get upcoming events
    $sql = "SELECT e.event_id, e.event_name, e.event_description, e.event_end_date, e.event_target_year_levels o.organization_name
            FROM events e
            JOIN organizations o ON e.organization_id = o.organization_id
            WHERE e.event_end_date >= CURDATE()
            AND (o.department_id = ? OR o.department_id IS NULL)
            ORDER BY e.event_end_date ASC";

    $stmt = $pdo->prepare($sql);
    $stmt->execute([$student["student_current_program"]]);
    $upcoming_events = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $response["status"] = "success";
    $response["data"] = $upcoming_events;

} catch (Exception $e) {
    http_response_code(400);
    $response["message"] = $e->getMessage();
}

echo json_encode($response);