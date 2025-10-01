<?php
require_once "_connect_to_database.php";
require_once "_middleware.php";
require_once "_request.php";

header("Content-Type: application/json");

require_role("admin");

$json_post_data = json_request_body();
require_params($json_post_data, ["organization_id"]); 

$response = ["status" => "error"];

try {
    // Ensure org exists
    $stmt = $pdo->prepare("SELECT organization_id FROM organizations WHERE organization_id = :org_id");
    $stmt->execute([":org_id" => $json_post_data["organization_id"]]);
    $org = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$org) {
        throw new Exception("Organization not found.");
    }

    // Get student_id either from student_id or student_number_id
    $student_id = null;
    if (isset($json_post_data["student_id"])) {
        $student_id = $json_post_data["student_id"];
        $stmt = $pdo->prepare("SELECT student_id FROM students WHERE student_id = :student_id");
        $stmt->execute([":student_id" => $student_id]);
        if (!$stmt->fetch()) {
            throw new Exception("Student not found.");
        }
    } elseif (isset($json_post_data["student_number_id"])) {
        $stmt = $pdo->prepare("SELECT student_id FROM students WHERE student_number_id = :student_number_id");
        $stmt->execute([":student_number_id" => $json_post_data["student_number_id"]]);
        $student = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$student) {
            throw new Exception("Student with number {$json_post_data["student_number_id"]} not found.");
        }
        $student_id = $student["student_id"];
    } else {
        throw new Exception("Either student_id or student_number_id is required.");
    }

    // Insert into organization_officers
    $stmt = $pdo->prepare("INSERT INTO organization_officers (organization_id, student_id, designation) 
                           VALUES (:org_id, :student_id, :designation)");
    $stmt->execute([
        ":org_id" => $json_post_data["organization_id"],
        ":student_id" => $student_id,
        ":designation" => $json_post_data["designation"] ?? "treasurer"
    ]);

    $response["status"] = "success";
    $response["message"] = "Organization officer added successfully.";
} catch (Exception $e) {
    http_response_code(400);
    if (strpos($e->getMessage(), "Duplicate entry") !== false) {
        $response["message"] = "This student is already an officer in the organization.";
    } else {
        $response["message"] = $e->getMessage();
    }
}

echo json_encode($response);