<?php 
require_once "_connect_to_database.php";
require_once "_middleware.php";
require_once "_request.php";

header("Content-Type: application/json");

require_role("treasurer");

$response = ["status" => "error"];

try {
    $current_dept_code = current_jwt_payload()['dept_code'];
    $params = [":dept_code" => $current_dept_code];

    $stmt = $pdo->prepare("
        WITH 
        event_count AS (
            SELECT COUNT(*) AS total_events
            FROM events e
            JOIN organizations o ON e.organization_id = o.organization_id
            JOIN departments d ON o.department_id = d.department_id
            WHERE (:dept_code IS NULL OR d.department_code = :dept_code)
        ),
        student_count AS (
            SELECT COUNT(*) AS total_students
            FROM students s
            JOIN programs p ON s.student_current_program = p.program_id
            JOIN departments d ON p.department_id = d.department_id
            WHERE (:dept_code IS NULL OR d.department_code = :dept_code)
        ),
        fees_collected AS (
            SELECT COALESCE(SUM(cm.paid_amount),0) AS total_fees_collected
            FROM contributions_made cm
            JOIN event_contributions ec ON cm.event_contri_id = ec.event_contri_id
            JOIN events e ON ec.event_id = e.event_id
            JOIN organizations o ON e.organization_id = o.organization_id
            JOIN departments d ON o.department_id = d.department_id
            WHERE (:dept_code IS NULL OR d.department_code = :dept_code)
        ),
        sanctions_collected AS (
            SELECT 
              COALESCE((
                SELECT SUM(pcs.paid_sanction_amount)
                FROM paid_contribution_sanctions pcs
                JOIN event_contributions ec ON pcs.event_contri_id = ec.event_contri_id
                JOIN events e ON ec.event_id = e.event_id
                JOIN organizations o ON e.organization_id = o.organization_id
                JOIN departments d ON o.department_id = d.department_id
                WHERE (:dept_code IS NULL OR d.department_code = :dept_code)
              ),0)
              +
              COALESCE((
                SELECT SUM(pas.paid_sanction_amount)
                FROM paid_attendance_sanctions pas
                JOIN event_attendance_times eat ON pas.event_attend_time_id = eat.event_attend_time_id
                JOIN event_attendance_dates ead ON eat.event_attend_date_id = ead.event_attend_date_id
                JOIN events e ON ead.event_id = e.event_id
                JOIN organizations o ON e.organization_id = o.organization_id
                JOIN departments d ON o.department_id = d.department_id
                WHERE (:dept_code IS NULL OR d.department_code = :dept_code)
              ),0) AS total_sanctions_collected
        ),
        student_summary AS (
            SELECT 
              SUM(CASE WHEN LEFT(s.student_section, 1) = '1' THEN 1 ELSE 0 END) AS total_first_year,
              SUM(CASE WHEN LEFT(s.student_section, 1) = '2' THEN 1 ELSE 0 END) AS total_second_year,
              SUM(CASE WHEN LEFT(s.student_section, 1) = '3' THEN 1 ELSE 0 END) AS total_third_year,
              SUM(CASE WHEN LEFT(s.student_section, 1) = '4' THEN 1 ELSE 0 END) AS total_fourth_year
            FROM students s
            JOIN programs p ON s.student_current_program = p.program_id
            JOIN departments d ON p.department_id = d.department_id
            WHERE (:dept_code IS NULL OR d.department_code = :dept_code)
        )
        SELECT 
          e.total_events,
          st.total_students,
          f.total_fees_collected,
          sn.total_sanctions_collected,
          ss.total_first_year,
          ss.total_second_year,
          ss.total_third_year,
          ss.total_fourth_year
        FROM event_count e
        CROSS JOIN student_count st
        CROSS JOIN fees_collected f
        CROSS JOIN sanctions_collected sn
        CROSS JOIN student_summary ss;
    ");
    $stmt->execute($params);
    $data = $stmt->fetch(PDO::FETCH_ASSOC);

    $response["status"] = "success";
    $response["data"] = $data;

} catch (Exception $e) {
	http_response_code(400);
    $response["message"] = $e->getMessage();
}

echo json_encode($response);