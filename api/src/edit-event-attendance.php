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

    // fetch existing attendance setup
    $stmt_existing = $pdo->prepare("
        SELECT ead.event_attend_date_id, ead.event_attend_date,
               eat.event_attend_time_id, eat.event_attend_time, eat.event_attend_sanction_fee
        FROM event_attendance_dates ead
        LEFT JOIN event_attendance_times eat 
            ON ead.event_attend_date_id = eat.event_attend_date_id
        WHERE ead.event_id = :event_id
    ");
    $stmt_existing->execute([":event_id" => $id]);
    $existing = $stmt_existing->fetchAll(PDO::FETCH_ASSOC);

    // Build lookup
    $existingDates = [];
    foreach ($existing as $row) {
        $date_val = $row["event_attend_date"];
        if (!isset($existingDates[$date_val])) {
            $existingDates[$date_val] = [
                "date_id" => $row["event_attend_date_id"],
                "times" => []
            ];
        }
        if ($row["event_attend_time"]) {
            $existingDates[$date_val]["times"][$row["event_attend_time"]] = [
                "id" => $row["event_attend_time_id"],
                "fee" => $row["event_attend_sanction_fee"]
            ];
        }
    }

    $allowed_time_values = ["AM IN", "AM OUT", "PM IN", "PM OUT"];
    $payloadDates = [];

    // Process payload
    foreach ($data["attendance"] as $att) {
        if (empty($att["event_attend_date"])) continue;
        $date_val = $att["event_attend_date"];
        $payloadDates[] = $date_val;

        // check if date exists
        if (isset($existingDates[$date_val])) {
            $date_id = $existingDates[$date_val]["date_id"];
        } else {
            // insert new date
            $stmt_date = $pdo->prepare("INSERT INTO event_attendance_dates (event_id, event_attend_date) VALUES (:event_id, :date)");
            $stmt_date->execute([":event_id" => $id, ":date" => $date_val]);
            $date_id = $pdo->lastInsertId();
            $existingDates[$date_val] = ["date_id" => $date_id, "times" => []];
        }

        // process times for this date
        $payloadTimes = [];
        foreach ($att["event_attend_time"] as $time_label) {
            if (!in_array($time_label, $allowed_time_values)) {
                throw new Exception("Invalid event_attend_time value: {$time_label}");
            }
            $payloadTimes[] = $time_label;

            if (isset($existingDates[$date_val]["times"][$time_label])) {
                // already exists → update sanction fee if changed
                $time_id = $existingDates[$date_val]["times"][$time_label]["id"];
                $pdo->prepare("UPDATE event_attendance_times SET event_attend_sanction_fee = :fee WHERE event_attend_time_id = :id")
                    ->execute([
                        ":fee" => $att["event_attend_sanction_fee"] ?? 0,
                        ":id" => $time_id
                    ]);
                unset($existingDates[$date_val]["times"][$time_label]); // mark handled
            } else {
                // insert new time
                $stmt_time = $pdo->prepare("INSERT INTO event_attendance_times (event_attend_date_id, event_attend_time, event_attend_sanction_fee) VALUES (:date_id, :time, :fee)");
                $stmt_time->execute([
                    ":date_id" => $date_id,
                    ":time" => $time_label,
                    ":fee" => $att["event_attend_sanction_fee"] ?? 0
                ]);
            }
        }

        // delete leftover times not in payload
        foreach ($existingDates[$date_val]["times"] as $old_time => $info) {
            $pdo->prepare("DELETE FROM event_attendance_times WHERE event_attend_time_id = :id")
                ->execute([":id" => $info["id"]]);
        }

        // mark date handled
        unset($existingDates[$date_val]);
    }

    // delete leftover dates not in payload
    foreach ($existingDates as $date_val => $info) {
        $pdo->prepare("DELETE FROM event_attendance_dates WHERE event_attend_date_id = :id")
            ->execute([":id" => $info["date_id"]]);
    }

    $response["status"] = "success";
    $response["message"] = "Event attendance updated successfully";

} catch (Exception $e) {
    $response["message"] = $e->getMessage();
}

echo json_encode($response);