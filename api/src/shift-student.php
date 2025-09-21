<?php
require_once "_connect_to_database.php";
require_once "_generate_email.php";
require_once "_middleware.php";
require_once "_request.php";

header("Content-Type: application/json");

require_login();

$json = json_request_body();
require_params($json_post_data, ["program_id"]);

$response = ["status" => "error"];

try {
    $pdo->beginTransaction();
	// insert into student_programs_taken shift_status 'PENDING'
} catch (PDOException $e) {
    $pdo->rollBack();
	$response["message"] = $e->getMessage();
}

echo json_encode($response);