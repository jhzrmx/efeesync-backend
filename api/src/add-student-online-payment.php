<?php
require_once "_connect_to_database.php";
require_once "_middleware.php";
require_once "_upload_dirs.php";

header("Content-Type: application/json");
require_role(["student"]);

$response = ["status" => "error"];

try {
    // Check required params
    if (empty($_POST["student_id"])) {
        throw new Exception("Student ID is required");
    }
    if (empty($_POST["event_ids"]) || !is_array($_POST["event_ids"])) {
        throw new Exception("At least one Event ID is required");
    }
    if (!isset($_FILES["online_payment_proof"])) {
        throw new Exception("Online payment proof file is required");
    }

    $student_id = intval($_POST["student_id"]);
    $event_ids  = $_POST["event_ids"]; // array
    $file       = $_FILES["online_payment_proof"];

    // Handle file upload
    $filename = uniqid("proof_") . "_" . basename($file["name"]);
    $target   = $payment_proof_dir . $filename;

    if (!move_uploaded_file($file["tmp_name"], $target)) {
        throw new Exception("Failed to upload file");
    }

    // Insert into online_payments main table
    $stmt = $pdo->prepare("
        INSERT INTO online_payments (student_id, proof_path, status, date_uploaded) 
        VALUES (?, ?, 'PENDING', NOW())
    ");
    $stmt->execute([$student_id, $target]);
    $payment_id = $pdo->lastInsertId();

    // Process each event
    foreach ($event_ids as $event_id) {
        $event_id = intval($event_id);

        // Get event details (check attendance vs contribution)
        $stmt = $pdo->prepare("SELECT * FROM events WHERE id = ?");
        $stmt->execute([$event_id]);
        $event = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$event) {
            throw new Exception("Event $event_id not found");
        }

        // === Case 1: Attendance event ===
        $stmt = $pdo->prepare("
            SELECT ead.end_date
            FROM event_attendance_dates ead
            WHERE ead.event_id = ?
            ORDER BY ead.end_date DESC
            LIMIT 1
        ");
        $stmt->execute([$event_id]);
        $attendanceDate = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($attendanceDate) {
            $event_end_date = $attendanceDate["end_date"];

            if (strtotime($event_end_date) < time()) {
                // Already sanctioned: sum sanction amounts
                $stmt = $pdo->prepare("
                    SELECT SUM(eat.event_attend_sanction_amount) as total_sanction
                    FROM event_attendance_times eat
                    JOIN event_attendance_dates ead ON eat.date_id = ead.id
                    WHERE ead.event_id = ?
                ");
                $stmt->execute([$event_id]);
                $sanction = $stmt->fetch(PDO::FETCH_ASSOC);
                $total_sanction = $sanction["total_sanction"] ?? 0;

                if ($total_sanction > 0) {
                    // Insert sanction
                    $stmt = $pdo->prepare("
                        INSERT INTO paid_attendance_sanctions (student_id, event_id, amount, status, paid_at)
                        VALUES (?, ?, ?, 'PENDING', NOW())
                    ");
                    $stmt->execute([$student_id, $event_id, $total_sanction]);
                    $sanction_id = $pdo->lastInsertId();

                    // Link to online payment
                    $stmt = $pdo->prepare("
                        INSERT INTO online_payment_attendance_sanctions (payment_id, sanction_id)
                        VALUES (?, ?)
                    ");
                    $stmt->execute([$payment_id, $sanction_id]);
                }
            } else {
                // Not yet passed → cannot pay
                throw new Exception("Event $event_id is still ongoing, cannot pay yet.");
            }
        }

        // === Case 2: Contribution event ===
        $stmt = $pdo->prepare("
            SELECT ec.event_contri_fee
            FROM event_contributions ec
            WHERE ec.event_id = ?
        ");
        $stmt->execute([$event_id]);
        $contri = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($contri) {
            $fee = $contri["event_contri_fee"];

            $stmt = $pdo->prepare("
                INSERT INTO contributions_made (student_id, event_id, amount, status, paid_at)
                VALUES (?, ?, ?, 'PENDING', NOW())
            ");
            $stmt->execute([$student_id, $event_id, $fee]);
            $contri_id = $pdo->lastInsertId();

            // Link to online payment
            $stmt = $pdo->prepare("
                INSERT INTO online_payment_contributions (payment_id, contribution_id)
                VALUES (?, ?)
            ");
            $stmt->execute([$payment_id, $contri_id]);
        }
    }

    $response["status"]  = "success";
    $response["message"] = "Online payment submitted successfully.";
    $response["payment_id"] = $payment_id;

} catch (Exception $e) {
    http_response_code(400);
    $response["message"] = $e->getMessage();
}

echo json_encode($response);