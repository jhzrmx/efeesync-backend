<?php
require_once "_connect_to_database.php";
require_once "_middleware.php";
require_once "_request.php";

header("Content-Type: application/json");

require_role(["admin", "treasurer"]);

$response = ["status" => "error"];

try {
    $sql = "SELECT 
                e.event_id,
                e.event_name,
                e.event_description,
                e.event_start_date,
                e.event_end_date,
                e.event_target_year_levels,
                e.event_picture,
                e.event_sanction_has_comserv
            FROM events e
            JOIN organizations o ON o.organization_id = e.organization_id";

    if (isset($organization_id)) {
        $sql .= " WHERE o.organization_id = :organization_id";
        if (isset($id)) {
            $sql .= " AND e.event_id = :event_id";
        }
        $stmt = $pdo->prepare($sql);
        $stmt->bindParam(":organization_id", $organization_id);
        if (isset($id)) {
            $stmt->bindParam(":event_id", $id);
        }
    } elseif (isset($organization_code)) {
        $sql .= " WHERE o.organization_code = :organization_code";
        if (isset($id)) {
            $sql .= " AND e.event_id = :event_id";
        }
        $stmt = $pdo->prepare($sql);
        $stmt->bindParam(":organization_code", $organization_code);
        if (isset($id)) {
            $stmt->bindParam(":event_id", $id);
        }
    } else {
        echo json_encode(["status" => "error", "message" => "No organization provided"]);
        exit();
    }

    $stmt->execute();
    $events = $stmt->fetchAll(PDO::FETCH_ASSOC);

    for ($i = 0; $i < count($events); $i++) {
        $event_id = $events[$i]["event_id"];

        // normalize flags/arrays
        $events[$i]["event_sanction_has_comserv"] = $events[$i]["event_sanction_has_comserv"] === 1;
        $events[$i]["event_target_year_levels"] = array_map('intval', explode(",", $events[$i]["event_target_year_levels"]));

        // --- get contribution ---
        $stmt_contri = $pdo->prepare("
            SELECT event_contri_due_date, event_contri_fee, event_contri_sanction_fee
            FROM event_contributions
            WHERE event_id = :event_id
            LIMIT 1
        ");
        $stmt_contri->bindParam(":event_id", $event_id);
        $stmt_contri->execute();
        $contri = $stmt_contri->fetch(PDO::FETCH_ASSOC);
        $events[$i]["contribution"] = $contri ?: null;

        // --- get attendance (dates + times) ---
        $stmt_att_dates = $pdo->prepare("
            SELECT event_attend_date_id, event_attend_date
            FROM event_attendance_dates
            WHERE event_id = :event_id
            ORDER BY event_attend_date ASC
        ");
        $stmt_att_dates->bindParam(":event_id", $event_id);
        $stmt_att_dates->execute();
        $dates = $stmt_att_dates->fetchAll(PDO::FETCH_ASSOC);

        $attendance = [];
        foreach ($dates as $d) {
            $stmt_times = $pdo->prepare("
                SELECT event_attend_time, event_attend_sanction_fee
                FROM event_attendance_times
                WHERE event_attend_date_id = :date_id
                ORDER BY event_attend_time_id ASC
            ");
            $stmt_times->bindParam(":date_id", $d["event_attend_date_id"]);
            $stmt_times->execute();
            $times = $stmt_times->fetchAll(PDO::FETCH_ASSOC);

            // build array of just times
            $timeLabels = array_column($times, "event_attend_time");

            // calculate average sanction fee (if needed)
            $fees = array_column($times, "event_attend_sanction_fee");
            $avgFee = !empty($fees) ? array_sum($fees) / count($fees) : null;

            $attendance[] = [
                "event_attend_date" => $d["event_attend_date"],
                "event_attend_time" => $timeLabels,
                "event_attend_sanction_fee" => $avgFee
            ];
        }
        $events[$i]["attendance"] = $attendance;
    }

    $response["status"] = "success";
    $response["data"] = $events;
} catch (Exception $e) {
    $response["message"] = $e->getMessage();
}

echo json_encode($response);