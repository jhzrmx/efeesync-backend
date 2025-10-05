<?php
require_once "_connect_to_database.php";
require_once "_middleware.php";
require_once "_request.php";

header("Content-Type: application/json");

require_role(["treasurer"]); // Only treasurer can approve/reject

$response = ["status" => "error"];

try {
    $excuse_id = intval($id); // Get :id from route

    if (!$excuse_id) {
        throw new Exception("Invalid excuse ID");
    }

    // Check if excuse exists
    $check_sql = "SELECT attendance_excuse_id, attendance_excuse_status 
                  FROM attendance_excuse 
                  WHERE attendance_excuse_id = ?";
    $check_stmt = $pdo->prepare($check_sql);
    $check_stmt->execute([$excuse_id]);
    $excuse = $check_stmt->fetch(PDO::FETCH_ASSOC);

    if (!$excuse) {
        throw new Exception("Attendance excuse not found");
    }

    // Decide new status
    if ($action === "approve") {
        $new_status = "APPROVED";
    } elseif ($action === "reject") {
        $new_status = "REJECTED";
    } else {
        throw new Exception("Invalid action");
    }

    // Update excuse
    $update_sql = "UPDATE attendance_excuse 
                   SET attendance_excuse_status = ?
                   WHERE attendance_excuse_id = ?";
    $update_stmt = $pdo->prepare($update_sql);
    $update_stmt->execute([$new_status, $excuse_id]);

    $response["status"] = "success";
    $response["message"] = "Attendance excuse has been {$new_status}.";
    $response["data"] = [
        "attendance_excuse_id" => $excuse_id,
        "new_status" => $new_status
    ];

} catch (Exception $e) {
    http_response_code(400);
    $response["message"] = $e->getMessage();
}

echo json_encode($response);