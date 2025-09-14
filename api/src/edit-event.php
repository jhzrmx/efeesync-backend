<?php
require_once "_connect_to_database.php";
require_once "_middleware.php";
require_once "_request.php";

header("Content-Type: application/json");

require_role(["admin", "treasurer"]);

$response = ["status" => "error"];

try {
    $input = json_request_body();
	
    if (isset($organization_id)) {
        $org_id = intval($organization_id);
    } elseif (isset($organization_code)) {
        $stmt_org = $pdo->prepare("SELECT organization_id FROM organizations WHERE organization_code = :code LIMIT 1");
        $stmt_org->execute([":code" => $organization_code]);
        $org = $stmt_org->fetch(PDO::FETCH_ASSOC);
        if (!$org) throw new Exception("Organization not found");
        $org_id = (int) $org["organization_id"];
    } else {
        throw new Exception("Missing organization identifier in route");
    }

    if (!isset($event_id)) {
        throw new Exception("Missing event identifier in route");
    }
    $event_id = intval($event_id);

    // Verify event belongs to organization
    $stmt = $pdo->prepare("SELECT * FROM events WHERE event_id = :event_id AND organization_id = :org_id LIMIT 1");
    $stmt->execute([":event_id" => $event_id, ":org_id" => $org_id]);
    $event = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$event) throw new Exception("Event not found for the provided organization");

    $pdo->beginTransaction();

    // Prepare values (use provided values or fall back to current DB values)
    $event_name = $input['event_name'] ?? $event['event_name'];
    $event_description = $input['event_description'] ?? $event['event_description'];
    $event_target_year_levels = isset($input['event_target_year_levels']) 
        ? implode(",", $input['event_target_year_levels']) 
        : $event['event_target_year_levels'];
    $event_start_date = $input['event_start_date'] ?? $event['event_start_date'];
    $event_end_date = array_key_exists('event_end_date', $input) ? $input['event_end_date'] : $event['event_end_date'];
    $event_sanction_has_comserv = isset($input['event_sanction_has_comserv']) ? ($input['event_sanction_has_comserv'] ? 1 : 0) : (int)$event['event_sanction_has_comserv'];

    // Update core event fields
    $stmt_update = $pdo->prepare("
        UPDATE events SET
            event_name = :event_name,
            event_description = :event_description,
            event_target_year_levels = :event_target_year_levels,
            event_start_date = :event_start_date,
            event_end_date = :event_end_date,
            event_sanction_has_comserv = :event_sanction_has_comserv
        WHERE event_id = :event_id AND organization_id = :org_id
    ");
    $stmt_update->execute([
        ":event_name" => $event_name,
        ":event_description" => $event_description,
        ":event_target_year_levels" => $event_target_year_levels,
        ":event_start_date" => $event_start_date,
        ":event_end_date" => $event_end_date,
        ":event_sanction_has_comserv" => $event_sanction_has_comserv,
        ":event_id" => $event_id,
        ":org_id" => $org_id
    ]);

    // Contribution handling (if present in payload)
    if (isset($input['contribution'])) {
        // Check payments already made against this event's contributions:
        $stmt_check_payments = $pdo->prepare("
            SELECT 
                (SELECT COUNT(*) FROM contributions_made cm JOIN event_contributions ec ON cm.event_contri_id = ec.event_contri_id WHERE ec.event_id = :event_id) +
                (SELECT COUNT(*) FROM paid_contribution_sanctions pcs JOIN event_contributions ec2 ON pcs.event_contri_id = ec2.event_contri_id WHERE ec2.event_id = :event_id)
            AS total_payments
        ");
        $stmt_check_payments->execute([":event_id" => $event_id]);
        $payments_count = (int) $stmt_check_payments->fetchColumn();

        if ($payments_count > 0) {
            throw new Exception("Cannot edit contribution: payments already exist for this event.");
        }

        // Safe to replace contribution: delete old and insert new (if provided)
        $pdo->prepare("DELETE FROM event_contributions WHERE event_id = :event_id")->execute([":event_id" => $event_id]);

        $c = $input['contribution'];
        $stmt_insert_contri = $pdo->prepare("
            INSERT INTO event_contributions (event_contri_due_date, event_contri_fee, event_contri_sanction_fee, event_id)
            VALUES (:due_date, :fee, :sanction_fee, :event_id)
        ");
        $stmt_insert_contri->execute([
            ":due_date" => $c['event_contri_due_date'] ?? null,
            ":fee" => isset($c['event_contri_fee']) ? $c['event_contri_fee'] : 0,
            ":sanction_fee" => isset($c['event_contri_sanction_fee']) ? $c['event_contri_sanction_fee'] : 0,
            ":event_id" => $event_id
        ]);
    }

    // Attendance handling (if present in payload)
    if (isset($input['attendance'])) {
        // Check whether attendance (actual logs) exist for this event
        $stmt_check_attendance = $pdo->prepare("
            SELECT COUNT(*) FROM attendance_made am
            JOIN event_attendance_times eat ON am.event_attend_time_id = eat.event_attend_time_id
            JOIN event_attendance_dates ead ON eat.event_attend_date_id = ead.event_attend_date_id
            WHERE ead.event_id = :event_id
        ");
        $stmt_check_attendance->execute([":event_id" => $event_id]);
        $attendance_logs_count = (int) $stmt_check_attendance->fetchColumn();

        if ($attendance_logs_count > 0) {
            throw new Exception("Cannot edit attendance: attendance records already exist for this event.");
        }

        // Safe to replace dates/times: delete old then insert new
        // Delete times linked to this event
        $pdo->prepare("
            DELETE eat FROM event_attendance_times eat
            JOIN event_attendance_dates ead ON eat.event_attend_date_id = ead.event_attend_date_id
            WHERE ead.event_id = :event_id
        ")->execute([":event_id" => $event_id]);

        // Delete dates
        $pdo->prepare("DELETE FROM event_attendance_dates WHERE event_id = :event_id")->execute([":event_id" => $event_id]);

        // Insert new attendance structure
        $allowed_time_values = ['AM IN', 'AM OUT', 'PM IN', 'PM OUT'];
        foreach ($input['attendance'] as $att) {
            if (empty($att['event_attend_date'])) continue;
            $stmt_date = $pdo->prepare("INSERT INTO event_attendance_dates (event_attend_date, event_id) VALUES (:date, :event_id)");
            $stmt_date->execute([":date" => $att['event_attend_date'], ":event_id" => $event_id]);
            $date_id = $pdo->lastInsertId();

            // Each time can optionally include event_attend_sanction_fee (if present in the same object)
            // Expected shape in payload: { "event_attend_date": "...", "event_attend_time": ["AM IN","PM IN"], "event_attend_sanction_fee": 0 }
            foreach ($att['event_attend_time'] as $time_label) {
                if (!in_array($time_label, $allowed_time_values)) {
                    throw new Exception("Invalid event_attend_time value: {$time_label}");
                }

                $stmt_time = $pdo->prepare("
                    INSERT INTO event_attendance_times (event_attend_time, event_attend_sanction_fee, event_attend_date_id)
                    VALUES (:time, :sanction_fee, :date_id)
                ");
                $stmt_time->execute([
                    ":time" => $time_label,
                    ":sanction_fee" => isset($att['event_attend_sanction_fee']) ? $att['event_attend_sanction_fee'] : 0,
                    ":date_id" => $date_id
                ]);
            }
        }
    }

    $pdo->commit();

    $response["status"] = "success";
    $response["message"] = "Event updated successfully";
    $response["event_id"] = $event_id;
    $response["organization_id"] = $org_id;

} catch (Exception $e) {
    if ($pdo->inTransaction()) $pdo->rollBack();
    $response["message"] = $e->getMessage();
}

echo json_encode($response);