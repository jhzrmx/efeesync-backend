<?php
require_once "_connect_to_database.php";
require_once "_generate_email.php";
require_once "_middleware.php";
require_once "_request.php";

header("Content-Type: application/json");
require_role(["admin", "treasurer"]);

$json = json_request_body();

try {
    $student_id = isset($id) ? $id : null;
    if (!$student_id) throw new Exception("Missing student ID");

    // Get current student and user info
    $stmt = $pdo->prepare("
        SELECT s.*, u.user_id, u.institutional_email 
        FROM students s
        JOIN users u ON u.user_id = s.user_id
        WHERE s.student_id = ?
    ");
    $stmt->execute([$student_id]);
    $student = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$student) {
        http_response_code(404);
        echo json_encode(["status" => "error", "message" => "Student not found"]);
        exit();
    }

    $user_id = $student["user_id"];

    // Normalize incoming data
    $student_number_id = $json["student_number_id"] ?? null;
    $first_name        = $json["first_name"] ?? null;
    $last_name         = $json["last_name"] ?? null;
    $mid               = $json["middle_initial"] ?? null;
    $sec               = $json["student_section"] ?? null;
    $prog              = $json["student_current_program"] ?? null;

    $is_graduated = null;
    if (isset($json["is_graduated"])) {
        $is_graduated = in_array(strtolower($json["is_graduated"]), ["1","yes","true"]) ? 1 : 0;
    }

    $pdo->beginTransaction();

    // Update users table if name fields provided
    if ($first_name && $last_name) {
        $new_email = generate_email($first_name, $last_name);

        // Ensure email is unique (excluding this user)
        $stmt = $pdo->prepare("SELECT user_id FROM users WHERE institutional_email = ? AND user_id != ?");
        $stmt->execute([$new_email, $user_id]);

        if ($stmt->rowCount() > 0) {
            throw new Exception("Email already exists: $new_email");
        }

        $stmt = $pdo->prepare("
            UPDATE users 
            SET first_name=?, last_name=?, middle_initial=?, institutional_email=? 
            WHERE user_id=?
        ");
        $stmt->execute([$first_name, $last_name, $mid, $new_email, $user_id]);
    } else {
        $new_email = $student["institutional_email"]; // unchanged
    }

    // Prepare student update dynamically
    $updateFields = [];
    $params = [];

    if ($student_number_id) {
        $updateFields[] = "student_number_id=?";
        $params[] = $student_number_id;
    }
    if ($sec) {
        $updateFields[] = "student_section=?";
        $params[] = $sec;
    }
    if ($prog) {
        $updateFields[] = "student_current_program=?";
        $params[] = $prog;
    }
    if ($is_graduated !== null) {
        $updateFields[] = "is_graduated=?";
        $params[] = $is_graduated;
    }

    if ($updateFields) {
        $params[] = $student_id;
        $sql = "UPDATE students SET " . implode(", ", $updateFields) . " WHERE student_id=?";
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
    }

    $pdo->commit();

    echo json_encode([
        "status" => "success",
        "updated_student_id" => $student_id,
        "new_email" => $new_email
    ]);

} catch (Exception $e) {
    $pdo->rollBack();
    http_response_code(500);
    echo json_encode(["status" => "error", "message" => $e->getMessage()]);
}