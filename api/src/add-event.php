<?php
require_once "_connect_to_database.php";
require_once "_middleware.php";
require_once "_request.php";

header("Content-Type: application/json");

require_role(["admin", "treasurer"]);

$json_post_data = json_request_body();
require_params($json_post_data, [
    "event_name",
    "event_description",
    "event_start_date",
    "event_target_year_levels"
]);

$response = ["status" => "error"];

try {
    if (isset($organization_id)) {
        $organization_id = intval($organization_id);
    } elseif (isset($organization_code)) {
        $stmt_org = $pdo->prepare("SELECT organization_id FROM organizations WHERE organization_code = ?");
        $stmt_org->execute([$organization_code]);
        $org = $stmt_org->fetch(PDO::FETCH_ASSOC);
        if (!$org) throw new Exception("Organization not found");
        $organization_id = $org["organization_id"];
    } else {
        throw new Exception("Missing organization identifier");
    }

    $pdo->beginTransaction();

    // Insert into events
    $stmt = $pdo->prepare("
        INSERT INTO events 
            (organization_id, event_name, event_description, 
             event_target_year_levels, event_start_date, event_end_date, 
             event_sanction_has_comserv) 
        VALUES (?, ?, ?, ?, ?, ?, ?)
    ");
    $stmt->execute([
        $organization_id,
        $json_post_data["event_name"],
        $json_post_data["event_description"],
        implode(",", $json_post_data["event_target_year_levels"]),
        $json_post_data["event_start_date"],
        $json_post_data["event_end_date"],
        $json_post_data["event_sanction_has_comserv"] ? 1 : 0
    ]);
    $event_id = $pdo->lastInsertId();

    // Contribution (optional)
    if (isset($json_post_data["contribution"])) {
        $c = $json_post_data["contribution"];
        $stmt_contri = $pdo->prepare("
            INSERT INTO event_contributions 
                (event_id, event_contri_due_date, event_contri_fee, event_contri_sanction_fee) 
            VALUES (?, ?, ?, ?)
        ");
        $stmt_contri->execute([
            $event_id,
            $c["event_contri_due_date"],
            $c["event_contri_fee"],
            $c["event_contri_sanction_fee"]
        ]);
    }

    // Attendance (optional)
    if (isset($json_post_data["attendance"])) {
        foreach ($json_post_data["attendance"] as $att) {
            $stmt_date = $pdo->prepare("
                INSERT INTO event_attendance_dates (event_id, event_attend_date) 
                VALUES (?, ?)
            ");
            $stmt_date->execute([$event_id, $att["event_attend_date"]]);
            $date_id = $pdo->lastInsertId();

            foreach ($att["event_attend_time"] as $time) {
                $sanctionFee = isset($att["event_attend_sanction_fee"]) && $att["event_attend_sanction_fee"] !== "" ? $att["event_attend_sanction_fee"] : 0;

                $stmt_time = $pdo->prepare("
                    INSERT INTO event_attendance_times (event_attend_date_id, event_attend_time, event_attend_sanction_fee) 
                    VALUES (?, ?, ?)
                ");
                $stmt_time->execute([$date_id, $time, $sanctionFee]);
            }
        }
    }

    $pdo->commit();

    $response["status"] = "success";
    $response["message"] = "Event created successfully";
    $response["event_id"] = $event_id;

} catch (Exception $e) {
    $pdo->rollBack();
    $response["message"] = $e->getMessage();
}

echo json_encode($response);