<?php
require_once "_connect_to_database.php";
require_once "_middleware.php";
require_once "_request.php";

header("Content-Type: application/json");
require_role(["student"]);

$response = ["status" => "error"];

try {
    $current_user_id = current_jwt_payload()['user_id'];

    // Get the student_id of this user
    $sql = "SELECT s.student_id
            FROM users u
            JOIN students s ON s.user_id = u.user_id
            WHERE u.user_id = ?";
    $stmt = $pdo->prepare($sql);
    $stmt->execute([$current_user_id]);
    $student = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$student || !$student['student_id']) {
        throw new Exception("This user is not a student");
    }

    $student_id = $student["student_id"];

    // Now fetch all excuses for this student
    $sql_excuses = "
        SELECT 
            ae.attendance_excuse_id,
            ae.attendance_excuse_reason,
            ae.attendance_excuse_proof_file,
            ae.attendance_excuse_status,
            ae.attendance_excuse_submitted_at,
            e.event_id,
            e.event_name,
            ead.event_attend_date
        FROM attendance_excuse ae
        JOIN event_attendance_dates ead 
            ON ae.event_attend_date_id = ead.event_attend_date_id
        JOIN events e 
            ON ead.event_id = e.event_id
        WHERE ae.student_id = ? AND ae.attendance_excuse_proof_file IS NOT NULL
        ORDER BY ae.attendance_excuse_submitted_at DESC
    ";
    $stmt_excuses = $pdo->prepare($sql_excuses);
    $stmt_excuses->execute([$student_id]);
    $student_excuses = $stmt_excuses->fetchAll(PDO::FETCH_ASSOC);

    $response["status"] = "success";
    $response["data"] = $student_excuses;

} catch (Exception $e) {
    http_response_code(400);
    $response["message"] = $e->getMessage();
}

echo json_encode($response, JSON_PRETTY_PRINT);