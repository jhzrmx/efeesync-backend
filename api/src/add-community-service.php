<?php
require_once "_connect_to_database.php";
require_once "_middleware.php";

header("Content-Type: application/json");

require_role(["treasurer", "admin"]);

$response = ["status" => "error"];

try {
    if (!isset($event_id)) {
        throw new Exception("Missing event_id");
    }

    // find student (by id or number)
    $student = null;
    if (isset($id)) {
        $stmt = $pdo->prepare("SELECT student_id FROM students WHERE student_id = ?");
        $stmt->execute([$id]);
        $student = $stmt->fetch(PDO::FETCH_ASSOC);
    } elseif (isset($student_number)) {
        $stmt = $pdo->prepare("SELECT student_id FROM students WHERE student_number_id = ?");
        $stmt->execute([$student_number]);
        $student = $stmt->fetch(PDO::FETCH_ASSOC);
    }

    if (!$student) {
        throw new Exception("Student not found");
    }

    $student_id = $student["student_id"];

    // check if event exists
    $stmt = $pdo->prepare("SELECT event_id FROM events WHERE event_id = ?");
    $stmt->execute([$event_id]);
    $event = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$event) {
        throw new Exception("Event not found");
    }

    // check for duplicate assignment
    $stmt = $pdo->prepare("
        SELECT comserv_id 
        FROM community_service_made
        WHERE student_id = :student_id AND event_id = :event_id
    ");
    $stmt->execute([
        ":student_id" => $student_id,
        ":event_id"   => $event_id
    ]);

    $exists = $stmt->fetch(PDO::FETCH_ASSOC);
    if ($exists) {
        throw new Exception("This student already has community service assigned for this event.");
    }

    // insert into community service table
    $stmt = $pdo->prepare("
        INSERT INTO community_service_made (student_id, event_id, created_at) 
        VALUES (:student_id, :event_id, NOW())
    ");
    $stmt->execute([
        ":student_id" => $student_id,
        ":event_id"   => $event_id
    ]);

    $response["status"] = "success";
    $response["message"] = "Community service assigned successfully.";
    $response["data"] = [
        "student_id" => $student_id,
        "event_id"   => $event_id
    ];

} catch (Exception $e) {
    http_response_code(400);
    $response["message"] = $e->getMessage();
}

echo json_encode($response, JSON_PRETTY_PRINT);