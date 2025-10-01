<?php
require_once "_connect_to_database.php";
require_once "_middleware.php";
require_once "_request.php";

header("Content-Type: application/json");
require_role(["student"]);

$response = ["status" => "error"];

try {
    $current_user_id = current_jwt_payload()['user_id'];
    $sql = "SELECT 
                s.student_id, 
                s.student_current_program, 
                s.student_section,
                p.department_id
            FROM users u
            JOIN students s ON s.user_id = u.user_id
            JOIN programs p ON s.student_current_program = p.program_id
            WHERE u.user_id = ?";
    $stmt = $pdo->prepare($sql);
    $stmt->execute([$current_user_id]);
    $student = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$student || !$student['student_id']) {
        throw new Exception("This user is not a student");
    }

    $student_id = $student["student_id"];
    $student_section = $student["student_section"];

    // =========================
    // ATTENDED EVENTS
    // =========================
    $attended_sql = "
        SELECT 
            e.event_id,
            e.event_name,
            ead.event_attend_date,
            eat.event_attend_time
        FROM attendance_made am
        INNER JOIN event_attendance_times eat 
            ON am.event_attend_time_id = eat.event_attend_time_id
        INNER JOIN event_attendance_dates ead 
            ON eat.event_attend_date_id = ead.event_attend_date_id
        INNER JOIN events e 
            ON ead.event_id = e.event_id
        WHERE am.student_id = ?
          AND FIND_IN_SET(LEFT(?, 1), e.event_target_year_levels) > 0
        ORDER BY ead.event_attend_date, eat.event_attend_time
    ";
    $attended_stmt = $pdo->prepare($attended_sql);
    $attended_stmt->execute([$student_id, $student_section]);
    $attended_rows = $attended_stmt->fetchAll(PDO::FETCH_ASSOC);

    // Group attendances by event -> then by date
    $attended_events = [];
    foreach ($attended_rows as $row) {
        $eventId = $row['event_id'];
        $date = $row['event_attend_date'];
        $time = $row['event_attend_time'];

        if (!isset($attended_events[$eventId])) {
            $attended_events[$eventId] = [
                "event_id" => (int)$row["event_id"],
                "event_name" => $row["event_name"],
                "attendance_logs" => []
            ];
        }

        if (!isset($attended_events[$eventId]["attendance_logs"][$date])) {
            $attended_events[$eventId]["attendance_logs"][$date] = [
                "event_attend_date" => $date,
                "event_attend_time" => []
            ];
        }

        $attended_events[$eventId]["attendance_logs"][$date]["event_attend_time"][] = $time;
    }

    // Re-index date groups
    foreach ($attended_events as &$ev) {
        $ev["attendance_logs"] = array_values($ev["attendance_logs"]);
    }
    $attended_events = array_values($attended_events);

    // =========================
    // EXCUSED EVENTS
    // =========================
    $excused_sql = "
        SELECT 
            e.event_id,
            e.event_name,
            ead.event_attend_date,
            eat.event_attend_time,
            ae.attendance_excuse_reason,
            ae.attendance_excuse_status
        FROM attendance_excuse ae
        INNER JOIN event_attendance_dates ead 
            ON ae.event_attend_date_id = ead.event_attend_date_id
        INNER JOIN event_attendance_times eat 
            ON eat.event_attend_date_id = ead.event_attend_date_id
        INNER JOIN events e 
            ON ead.event_id = e.event_id
        WHERE ae.student_id = ? 
          AND ae.attendance_excuse_status = 'APPROVED'
          AND FIND_IN_SET(LEFT(?, 1), e.event_target_year_levels) > 0
        ORDER BY ead.event_attend_date, eat.event_attend_time
    ";
    $excused_stmt = $pdo->prepare($excused_sql);
    $excused_stmt->execute([$student_id, $student_section]);
    $excused_rows = $excused_stmt->fetchAll(PDO::FETCH_ASSOC);

    // Group excused attendances by event -> then by date
    $excused_events = [];
    foreach ($excused_rows as $row) {
        $eventId = $row['event_id'];
        $date = $row['event_attend_date'];
        $time = $row['event_attend_time'];

        if (!isset($excused_events[$eventId])) {
            $excused_events[$eventId] = [
                "event_id" => (int)$row["event_id"],
                "event_name" => $row["event_name"],
                "event_attend_date" => $row['event_attend_date'],
                "excuse_reason" => $row["attendance_excuse_reason"],
                "attendance_logs" => []
            ];
        }

        if (!isset($excused_events[$eventId]["attendance_logs"][$date])) {
            $excused_events[$eventId]["attendance_logs"][$date] = [
                "event_attend_date" => $date,
                "event_attend_time" => []
            ];
        }

        $excused_events[$eventId]["attendance_logs"][$date]["event_attend_time"][] = $time;
    }

    // Re-index date groups
    foreach ($excused_events as &$ev) {
        $ev["attendance_logs"] = array_values($ev["attendance_logs"]);
    }
    $excused_events = array_values($excused_events);

    // =========================
    // RESPONSE
    // =========================
    $response = [
        "status" => "success",
        "student_id" => (int)$student_id,
        "data" => [
            "attended_events" => $attended_events,
            "excused_events" => $excused_events
        ]
    ];

} catch (Exception $e) {
    $response["message"] = $e->getMessage();
}

echo json_encode($response);