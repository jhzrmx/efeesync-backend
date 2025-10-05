<?php
require_once "_connect_to_database.php";
require_once "_generate_email.php";
require_once "_middleware.php";
require_once "_request.php";

header("Content-Type: application/json");

require_role(["admin", "treasurer"]);

$response = ["status" => "error"];

$json = json_request_body();
require_params($json,  [
    "student_number_id",
    "student_section",
    "first_name",
    "last_name",
    "student_current_program"
]);

$email = generate_email($json["first_name"], $json["last_name"]);
$password_raw = "cbsua-" . $json["student_number_id"];
$hashed_password = password_hash($password_raw, PASSWORD_DEFAULT);

try {
    $pdo->beginTransaction();

    // Insert into users
    $stmt = $pdo->prepare("
        INSERT INTO users (institutional_email, password, role_id, last_name, first_name, middle_initial) 
        VALUES (?, ?, ?, ?, ?, ?)
    ");
    $last_name = ucwords(trim($json["last_name"]));
    $first_name = ucwords(trim($json["first_name"]));
    $middle_initial = strtoupper(trim($json["middle_initial"]));
    $stmt->execute([
        $email,
        $hashed_password,
        102, // student role
        $last_name,
        $first_name,
        $middle_initial
    ]);

    $user_id = $pdo->lastInsertId();

    // Insert into students
    $stmt = $pdo->prepare("
        INSERT INTO students (student_number_id, user_id, student_section, student_current_program) 
        VALUES (?, ?, ?, ?)
    ");
    $stmt->execute([
        $json["student_number_id"],
        $user_id,
        $json["student_section"],
        $json["student_current_program"]
    ]);

    $student_id = $pdo->lastInsertId();

    // Insert into student_programs_taken
    $stmt = $pdo->prepare("
        INSERT INTO student_programs_taken (student_id, program_id, start_date) 
        VALUES (?, ?, CURDATE())
    ");
    
    $stmt->execute([
        $student_id,
        $json["student_current_program"]
    ]);

    $pdo->commit();

    echo json_encode([
        "status" => "success",
        "student_id" => $student_id,
        "user_id" => $user_id,
        "generated_email" => $email
    ]);

} catch (PDOException $e) {
    $pdo->rollBack();

    if ($e->getCode() == 23000) {
        if (strpos($e->getMessage(), "institutional_email") !== false) {
            $msg = "Email already exists: $email";
        } elseif (strpos($e->getMessage(), "student_number_id") !== false) {
            $msg = "Student number already exists: ".$json["student_number_id"];
        } else {
            $msg = "Duplicate entry detected";
        }

        http_response_code(409); // Conflict
        echo json_encode(["status" => "error", "message" => $msg]);
        exit();
    }

    http_response_code(500);
    echo json_encode(["status" => "error", "message" => $e->getMessage()]);
}