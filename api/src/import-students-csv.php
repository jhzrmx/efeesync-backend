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

/**
 * Split full name: "LastName, FirstName MiddleName"
 * Detects 1-word or 2-word middle names based on common patterns.
 */
function splitFullName($fullName) {
    $commonPrefixes = ["de", "del", "dela", "san", "sta", "sto", "santa"];

    $parts = explode(",", $fullName);
    if (count($parts) < 2) {
        return ["last" => "", "first" => "", "middle_initial" => ""];
    }

    $last = trim($parts[0]);
    $right = trim($parts[1]);  // First + Middle

    $names = preg_split('/\s+/', $right);
    $first = $names[0] ?? "";

    // No middle name
    if (count($names) < 2) {
        return [
            "last" => ucwords(strtolower($last)),
            "first" => ucwords(strtolower($first)),
            "middle_initial" => ""
        ];
    }

    // Try detecting 2-word middle names
    $firstMid = strtolower($names[1]);
    $secondMid = isset($names[2]) ? strtolower($names[2]) : "";

    if ($secondMid && (in_array("$firstMid $secondMid", $commonPrefixes) || in_array($firstMid, $commonPrefixes))) {
        $middleName = $names[1] . " " . $names[2];
    } else {
        $middleName = $names[1];
    }

    // Convert full middle name → initials
    $initials = "";
    foreach (explode(" ", $middleName) as $m) {
        if (strlen($m) > 0) {
            $initials .= strtoupper($m[0]);
        }
    }

    return [
        "last" => ucwords(strtolower($last)),
        "first" => ucwords(strtolower($first)),
        "middle_initial" => $initials
    ];
}

function extractYearAndSection($sectionStr) {
    // Finds "digit + letter(s)" pattern such as 1A, 2B, 3C, 4D, 1AA
    if (preg_match('/(\d+[A-Za-z]+)/', $sectionStr, $match)) {
        return strtoupper($match[1]);
    }
    return "";
}

try {
    $pdo->beginTransaction();

    $handle = fopen($csvFile, "r");
    if ($handle === false) {
        throw new Exception("Unable to open CSV file.");
    }

    $header = fgetcsv($handle);
    $header = array_map('strtolower', $header); // normalize headers

    $imported = [];
    $skipped = [];

    while (($row = fgetcsv($handle)) !== false) {
        $data = array_combine($header, $row);

        $student_number_id = trim($data["student_number_id"] ?? "");

        // FULL NAME MODE
        if (!empty($data["full_name"] ?? "")) {
            $parsed = splitFullName($data["full_name"]);

            $last_name      = $parsed["last"];
            $first_name     = $parsed["first"];
            $middle_initial = $parsed["middle_initial"];

        } else {
            // FALLBACK MODE: separate fields exist
            $first_name     = ucwords(trim($data["first_name"] ?? ""));
            $last_name      = ucwords(trim($data["last_name"] ?? ""));
            $middle_initial = strtoupper(trim($data["middle_initial"] ?? ""));
        }

        $student_section = extractYearAndSection(trim($data["student_section"] ?? ""));
        $program_code    = trim($data["program_code"] ?? "");

        if (!$student_number_id || !$first_name || !$last_name || !$program_code) {
            $skipped[] = [
                "student_number_id" => $student_number_id ?: "(missing)",
                "reason" => "Missing required fields"
            ];
            continue;
        }

        // Resolve program_code → program_id
        $stmt = $pdo->prepare("SELECT program_id FROM programs WHERE program_code = ?");
        $stmt->execute([$program_code]);
        $program_id = $stmt->fetchColumn();

        if (!$program_id) {
            $skipped[] = ["student_number_id" => $student_number_id, "reason" => "Invalid program_code"];
            continue;
        }

        // Generate email + default password
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
                "student_number" => $student_id,
				"student_number_id" => $student_number_id,
                "user_id" => $user_id,
                "generated_email" => $email,
                "program_code" => $program_code
            ];

        } catch (PDOException $e) {
            if ($e->getCode() == 23000) {
                $skipped[] = [
                    "student_number_id" => $student_number_id,
                    "reason" => "Duplicate student id/name/email"
                ];
            } else {
                $skipped[] = [
                    "student_number_id" => $student_number_id,
                    "reason" => $e->getMessage()
                ];
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
    echo json_encode([
        "status" => "error",
        "message" => $e->getMessage()
    ]);
}