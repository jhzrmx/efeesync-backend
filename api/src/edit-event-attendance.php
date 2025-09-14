<?php
require_once "_connect_to_database.php";
require_once "_middleware.php";
require_once "_request.php";

header("Content-Type: application/json");
require_role(["admin", "treasurer"]);

$response = ["status" => "error"];

try {
    $data = json_request_body();
    require_params($data, ["attendance"]);

    // check if event exists
    $stmt = $pdo->prepare("SELECT event_id FROM events WHERE event_id = :id AND organization_id = :org_id LIMIT 1");
    $stmt->execute([":id" => $id, ":org_id" => $organization_id]);
    $event = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$event) {
        echo json_encode(["status" => "error", "message" => "Event not found"]);
        exit();
    }

    // check if attendance already has logs
    $stmt_check = $pdo->prepare("
        SELECT COUNT(*) FROM attendance_made am
        JOIN event_attendance_times eat ON am.event_attend_time_id = eat.event_attend_time_id
        JOIN event_attendance_dates ead ON eat.event_attend_date_id = ead.event_attend_date_id
        WHERE ead.event_id = :event_id
    ");
    $stmt_check->execute([":event_id" => $id]);
    $hasLogs = $stmt_check->fetchColumn() > 0;

    if ($hasLogs) {
        echo json_encode(["status" => "error", "message" => "Cannot edit attendance, logs already recorded"]);
        exit();
    }

    // delete existing attendance setup
    $stmt_delete = $pdo->prepare("DELETE FROM event_attendance_dates WHERE event_id = :event_id");
    $stmt_delete->execute([":event_id" => $id]);

    // reinsert attendance dates + times
    foreach ($data["attendance"] as $att) {
        $stmt_date = $pdo->prepare("INSERT INTO event_attendance_dates (event_id, event_attend_date) VALUES (:event_id, :att_date)");
        $stmt_date->execute([":event_id" => $id, ":att_date" => $att["event_attend_date"]]);
        $date_id = $pdo->lastInsertId();

        foreach ($att["event_attend_time"] as $time) {
            $stmt_time = $pdo->prepare("INSERT INTO event_attendance_times (event_attend_date_id, event_attend_time) VALUES (:date_id, :time)");
            $stmt_time->execute([":date_id" => $date_id, ":time" => $time]);
        }
    }

    $response["status"] = "success";
    $response["message"] = "Event attendance updated successfully";
} catch (Exception $e) {
    $response["message"] = $e->getMessage();
}

echo json_encode($response);