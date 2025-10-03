<?php
require_once "_connect_to_database.php";
require_once "_generate_email.php";
require_once "_middleware.php";

header("Content-Type: application/json");
require_role(["admin", "treasurer"]);

$response = ["status" => "error"];

if (!isset($_FILES['csv_file']) || $_FILES['csv_file']['error'] !== UPLOAD_ERR_OK) {
    http_response_code(400);
    echo json_encode(["status" => "error", "message" => "CSV file is required."]);
    exit();
}

$csvFile = $_FILES['csv_file']['tmp_name'];

try {
    $pdo->beginTransaction();

    $handle = fopen($csvFile, "r");
    if ($handle === false) {
        throw new Exception("Unable to open CSV file.");
    }

    $header = fgetcsv($handle); // read header row
    $imported = [];
    $skipped = [];

    while (($row = fgetcsv($handle)) !== false) {
        $data = array_combine($header, $row);

        $student_number_id = trim($data["student_number_id"]);
        $first_name        = ucwords(trim($data["first_name"]));
        $last_name         = ucwords(trim($data["last_name"]));
        $middle_initial    = strtoupper(trim($data["middle_initial"] ?? ""));
        $student_section   = trim($data["student_section"]);
        $program_code      = trim($data["program_code"]);

        // Resolve program_code → program_id
        $stmt = $pdo->prepare("SELECT program_id FROM programs WHERE program_code = ?");
        $stmt->execute([$program_code]);
        $program_id = $stmt->fetchColumn();

        if (!$program_id) {
            $skipped[] = ["student_number_id" => $student_number_id, "reason" => "Invalid program_code"];
            continue;
        }

        // Generate email + password
        $email = generate_email($first_name, $last_name);
        $password_raw = "cbsua-" . $student_number_id;
        $hashed_password = password_hash($password_raw, PASSWORD_DEFAULT);

        try {
            // Insert into users
            $stmt = $pdo->prepare("
                INSERT INTO users (institutional_email, password, role_id, last_name, first_name, middle_initial) 
                VALUES (?, ?, ?, ?, ?, ?)
            ");
            $stmt->execute([$email, $hashed_password, 102, $last_name, $first_name, $middle_initial]);
            $user_id = $pdo->lastInsertId();

            // Insert into students
            $stmt = $pdo->prepare("
                INSERT INTO students (student_number_id, user_id, student_section, student_current_program) 
                VALUES (?, ?, ?, ?)
            ");
            $stmt->execute([$student_number_id, $user_id, $student_section, $program_id]);
            $student_id = $pdo->lastInsertId();
            
            // Insert into student_programs_taken
            $stmt = $pdo->prepare("
                INSERT INTO student_programs_taken (student_id, program_id, start_date) 
                VALUES (?, ?, CURDATE())
            ");
            $stmt->execute([$student_id, $program_id]);

            $imported[] = [
                "student_id" => $student_id,
                "user_id" => $user_id,
                "generated_email" => $email,
                "program_code" => $program_code
            ];

        } catch (PDOException $e) {
            if ($e->getCode() == 23000) {
                $skipped[] = ["student_number_id" => $student_number_id, "reason" => "Duplicate student/email"];
            } else {
                $skipped[] = ["student_number_id" => $student_number_id, "reason" => $e->getMessage()];
            }
        }
    }

    fclose($handle);
    $pdo->commit();

    echo json_encode([
        "status" => "success",
        "imported" => $imported,
        "skipped" => $skipped
    ]);

} catch (Exception $e) {
    $pdo->rollBack();
    http_response_code(500);
    echo json_encode(["status" => "error", "message" => $e->getMessage()]);
}