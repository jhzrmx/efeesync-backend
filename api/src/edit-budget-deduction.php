<?php 
require_once "_connect_to_database.php";
require_once "_middleware.php";
require_once "_request.php";

header("Content-Type: application/json");

require_role(["treasurer"]);

$json_post_data = json_request_body();
require_params($json_post_data, ["description", "amount_deducted"]);

$response = ["status" => "error"];

try {
    $budget_deduction_id = isset($id) ? intval($id) : null;

    if (!$budget_deduction_id) throw new Exception("Missing identifier in URL");

    $amount_deducted = trim($json_post_data["amount_deducted"]);
    $description = trim($json_post_data["description"]);
    $stmt = $pdo->prepare("UPDATE `budget_deductions` SET `budget_deduction_title` = :description, `budget_deduction_amount` = :amount_deducted, `budget_deducted_at` = NOW() WHERE `budget_deduction_id` = :budget_deduction_id");
    $stmt->bindParam(":description", $description);
    $stmt->bindParam(":amount_deducted", $amount_deducted);
    $stmt->bindParam(":budget_deduction_id", $budget_deduction_id);
    $stmt->execute();
    $response["status"] = "success";
} catch (Exception $e) {
    http_response_code(400);
    $response["message"] = $e->getMessage();
}

echo json_encode($response);