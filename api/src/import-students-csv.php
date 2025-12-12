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

function splitFullName($fullName) {
    $commonPrefixes = ["de","del","dela","san","sta","sto","santa"];
    $parts = explode(",", $fullName);
    if (count($parts) < 2) return ["last"=>"","first"=>"","middle_initial"=>""];

    $last = trim($parts[0]);
    $right = trim($parts[1]);
    $names = preg_split('/\s+/', $right);

    $first = $names[0] ?? "";
    if (count($names) < 2) {
        return ["last"=>ucwords(strtolower($last)), "first"=>ucwords(strtolower($first)), "middle_initial"=>""];
    }

    $firstMid = strtolower($names[1]);
    $secondMid = isset($names[2]) ? strtolower($names[2]) : "";
    if ($secondMid && (in_array("$firstMid $secondMid",$commonPrefixes) || in_array($firstMid,$commonPrefixes))) {
        $middleName = $names[1] . " " . $names[2];
    } else {
        $middleName = $names[1];
    }

    $initials = "";
    foreach (explode(" ", $middleName) as $m) {
        if ($m !== "") $initials .= strtoupper($m[0]);
    }

    return [
        "last" => ucwords(strtolower($last)),
        "first" => ucwords(strtolower($first)),
        "middle_initial" => $initials
    ];
}

function extractYearAndSection($sectionStr) {
    if (preg_match('/(\d+[A-Za-z]+)/', $sectionStr, $match)) {
        return strtoupper($match[1]);
    }
    return "";
}

try {
    $pdo->beginTransaction();

    $handle = fopen($csvFile, "r");
    if (!$handle) throw new Exception("Unable to open CSV file.");

    $header = fgetcsv($handle);
    $header = array_map('strtolower', $header);

    $imported = [];
    $updated = [];
    $skipped = [];

    while (($row = fgetcsv($handle)) !== false) {
        $data = array_combine($header, $row);

        $student_number_id = trim($data["student_number_id"] ?? "");

        /* ------------ PARSE NAME -------------- */
        if (!empty($data["full_name"] ?? "")) {
            $parsed = splitFullName($data["full_name"]);
            $last_name      = $parsed["last"];
            $first_name     = $parsed["first"];
            $middle_initial = $parsed["middle_initial"];
        } else {
            $first_name     = ucwords(trim($data["first_name"] ?? ""));
            $last_name      = ucwords(trim($data["last_name"] ?? ""));
            $middle_initial = strtoupper(trim($data["middle_initial"] ?? ""));
        }

        $student_section = extractYearAndSection(trim($data["student_section"] ?? ""));
        $program_code    = trim($data["program_code"] ?? "");

        /* Graduation column */
        $is_graduated = 0;
        if (isset($data["is_graduated"])) {
            if (strtolower(trim($data["is_graduated"])) === "yes") {
                $is_graduated = 1;
            }
        }

        /* ------------- VALIDATION --------------- */
        if (!$student_number_id || !$first_name || !$last_name || !$program_code) {
            $skipped[] = [
                "student_number_id" => $student_number_id ?: "(missing)",
                "reason" => "Missing required fields"
            ];
            continue;
        }

        /* Resolve program_code → program_id */
        $stmt = $pdo->prepare("SELECT program_id FROM programs WHERE program_code = ?");
        $stmt->execute([$program_code]);
        $program_id = $stmt->fetchColumn();

        if (!$program_id) {
            $skipped[] = ["student_number_id"=>$student_number_id, "reason"=>"Invalid program_code"];
            continue;
        }

        /* -------------------------------------------------------
		   CHECK IF STUDENT ALREADY EXISTS (student_number_id)
		-------------------------------------------------------- */
		$stmt = $pdo->prepare("
			SELECT s.student_id, s.student_current_program, s.student_section, s.is_graduated,
				   u.user_id
			FROM students s 
			JOIN users u ON u.user_id = s.user_id
			WHERE s.student_number_id = ?
		");
		$stmt->execute([$student_number_id]);
		$existing = $stmt->fetch(PDO::FETCH_ASSOC);

		/* ======================================================
		   CASE 1: STUDENT EXISTS → UPDATE RECORD
		====================================================== */
		$update_names = false;
		if ($existing) {
			$student_id = $existing["student_id"];
			$user_id    = $existing["user_id"];

			/*
			// Update users (name fields only) [make this optional]
			$new_email_from_name = generate_email($first_name, $last_name);
			$stmt = $pdo->prepare("
				UPDATE users 
				SET institutional_email=?, first_name=?, last_name=?, middle_initial=?
				WHERE user_id=?
			");
			$stmt->execute([$new_email_from_name, $first_name, $last_name, $middle_initial, $user_id]);
			*/

			// Update students
			$stmt = $pdo->prepare("
				UPDATE students
				SET student_current_program = ?, 
					student_section = ?, 
					is_graduated = ?
				WHERE student_id = ?
			");
			$stmt->execute([$program_id, $student_section, $is_graduated, $student_id]);

			$changedFields = [];

			if (strtoupper($student_section) !== strtoupper($existing['student_section'])) {
				$changedFields[] = "student_section ({$existing['student_section']} → {$student_section})";
			}

			if ($program_id != $existing['student_current_program']) {
				$stmtProg = $pdo->prepare("SELECT program_code FROM programs WHERE program_id = ?");
				$stmtProg->execute([$existing['student_current_program']]);
				$oldProgramCode = $stmtProg->fetchColumn();
				$changedFields[] = "program_code ({$oldProgramCode} → {$program_code})";
			}

			if ($is_graduated != $existing['is_graduated']) {
				$changedFields[] = "is_graduated → yes";
			}

			$reasonText = empty($changedFields) ? "No changes detected" : "Updated fields: " . implode(", ", $changedFields);

			$updated[] = [
				"student_number_id" => $student_number_id,
				"reason"            => $reasonText
			];

			continue;
		}

        /* ======================================================
           CASE 2: NEW STUDENT → INSERT
        ====================================================== */

        /* Create user */
        $email = generate_email($first_name, $last_name);
        $password_raw = "cbsua-" . $student_number_id;
        $password_hash = password_hash($password_raw, PASSWORD_BCRYPT);

        $stmt = $pdo->prepare("
            INSERT INTO users (first_name, last_name, middle_initial, institutional_email, password_hash, role_id)
            VALUES (?, ?, ?, ?, ?, 3)
        ");
        $stmt->execute([$first_name, $last_name, $middle_initial, $email, $password_hash]);

        $user_id = $pdo->lastInsertId();

        /* Insert student */
        $stmt = $pdo->prepare("
            INSERT INTO students (user_id, student_number_id, student_current_program, student_section, is_graduated)
            VALUES (?, ?, ?, ?, ?)
        ");
        $stmt->execute([$user_id, $student_number_id, $program_id, $student_section, $is_graduated]);

        $imported[] = [
            "student_number_id" => $student_number_id,
            "generated_email" => $email,
            "program_code" => $program_code
        ];
    }

    fclose($handle);
    $pdo->commit();

    echo json_encode([
        "status" => "success",
        "imported" => $imported,
        "updated" => $updated,
        "skipped" => $skipped
    ]);

} catch (Exception $e) {
    $pdo->rollBack();
    echo json_encode(["status" => "error", "message" => $e->getMessage()]);
}
