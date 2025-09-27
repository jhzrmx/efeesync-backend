<?php
require_once "_connect_to_database.php";
require_once "_middleware.php";
require_once "_request.php";

header("Content-Type: application/json");

// Only admins or treasurers can revoke contributions
require_role(["admin", "treasurer"]);

$response = ["status" => "error"];

try {
    if (!isset($id) || (!isset($student_id) && !isset($student_number_id))) {
        throw new Exception("Event ID and Student identifier are required.");
    }

    // ---- Get student_id if student_number_id is used ----
    if (isset($student_number_id)) {
        $stmt = $pdo->prepare("SELECT student_id FROM students WHERE student_number_id = :student_number_id");
        $stmt->execute([":student_number_id" => $student_number_id]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$row) throw new Exception("Student not found.");
        $student_id = $row["student_id"];
    }

    // ---- Get event contribution ----
    $stmt = $pdo->prepare("SELECT event_contri_id FROM event_contributions WHERE event_id = :event_id");
    $stmt->execute([":event_id" => $id]);
    $eventContri = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$eventContri) throw new Exception("Event contribution not found.");

    $event_contri_id = $eventContri["event_contri_id"];

    // ---- Check if contribution exists ----
    $stmt = $pdo->prepare("SELECT * FROM contributions_made WHERE event_contri_id = :event_contri_id AND student_id = :student_id");
    $stmt->execute([":event_contri_id" => $event_contri_id, ":student_id" => $student_id]);
    $contribution = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$contribution) throw new Exception("No contribution found for this student.");

    // ---- Delete contribution ----
    $stmt = $pdo->prepare("DELETE FROM contributions_made WHERE contribution_id = :contribution_id");
    $stmt->execute([":contribution_id" => $contribution["contribution_id"]]);

    $response["status"] = "success";
    $response["message"] = "Contribution revoked successfully.";

} catch (Exception $e) {
    http_response_code(400);
    $response["message"] = $e->getMessage();
}

echo json_encode($response);