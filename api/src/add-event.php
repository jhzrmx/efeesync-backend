<?php
require_once "_connect_to_database.php";
require_once "_middleware.php";
require_once "_request.php";

header("Content-Type: application/json");

require_role("treasurer");

$json_post_data = json_request_body();
require_params($json_post_data, [
    "event_name",
    "event_description",
    "event_start_date",
    "event_end_date",
    "event_target_year_levels"
]);

$response = ["status" => "error"];

try {
    if (!empty($organization_id)) {
        $org_id = $organization_id;
    } elseif (!empty($organization_code)) {
        $stmt = $pdo->prepare("SELECT organization_id FROM organizations WHERE organization_code = :code LIMIT 1");
        $stmt->execute([":code" => $organization_code]);
        $org = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$org) {
            throw new Exception("Invalid organization code");
        }
        $org_id = $org["organization_id"];
    } else {
        throw new Exception("Organization identifier required (id or code)");
    }

    $stmt = $pdo->prepare("
        INSERT INTO events (
            event_name, event_description, event_target_year_levels,
            event_start_date, event_end_date, event_sanction_has_comserv, organization_id
        ) VALUES (
            :name, :description, :target_years, :start_date, :end_date, :has_comserv, :org_id
        )
    ");
    $stmt->execute([
        ":name" => trim($json_post_data["event_name"]),
        ":description" => trim($json_post_data["event_description"]),
        ":target_years" => implode(",", $json_post_data["event_target_year_levels"]),
        ":start_date" => $json_post_data["event_start_date"],
        ":end_date" => $json_post_data["event_end_date"],
        ":has_comserv" => isset($json_post_data["event_sanction_has_comserv"]) ? (int)$json_post_data["event_sanction_has_comserv"] : 0,
        ":org_id" => $org_id
    ]);
    $event_id = $pdo->lastInsertId();

    if (!empty($json_post_data["contribution"])) {
        $contri = $json_post_data["contribution"];
        $stmt = $pdo->prepare("
            INSERT INTO event_contributions (event_contri_due_date, event_contri_fee, event_contri_sanction_fee, event_id)
            VALUES (:due, :fee, :sanction, :event_id)
        ");
        $stmt->execute([
            ":due" => $contri["event_contri_due_date"],
            ":fee" => $contri["event_contri_fee"],
            ":sanction" => $contri["event_contri_sanction_fee"] ?? 0,
            ":event_id" => $event_id
        ]);
    }

    if (!empty($json_post_data["attendance"])) {
        foreach ($json_post_data["attendance"] as $att) {
            $date = $att["event_attend_date"];
            foreach ($att["event_attend_time"] as $time) {
                $stmt = $pdo->prepare("
                    INSERT INTO event_attendance (event_attend_date, event_attend_time, event_id)
                    VALUES (:date, :time, :event_id)
                ");
                $stmt->execute([
                    ":date" => $date,
                    ":time" => $time,
                    ":event_id" => $event_id
                ]);
            }
        }
    }

    $response["status"] = "success";
    $response["event_id"] = $event_id;

} catch (Exception $e) {
    http_response_code(400);
    $response["message"] = $e->getMessage();
}

echo json_encode($response);