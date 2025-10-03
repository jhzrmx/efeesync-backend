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
    $organization_id = isset($organization_id) ? intval($organization_id) : null;
    $organization_code = isset($organization_code) ? $organization_code : null;

    if (!$organization_id && $organization_code) {
        $stmt = $pdo->prepare("SELECT organization_id FROM organizations WHERE organization_code = ?");
        $stmt->execute([$organization_code]);
        $organization_id = $stmt->fetchColumn();
    }

    if (!$organization_id) {
        throw new Exception("Organization not found.");
    }

    $amount_deducted = trim($json_post_data["amount_deducted"]);
    $description = trim($json_post_data["description"]);
    $stmt = $pdo->prepare("INSERT INTO `budget_deductions` (`budget_deduction_title`, `budget_deduction_amount`, `budget_deducted_at`, `organization_id`) VALUES (:description, :amount_deducted, NOW(), :organization_id)");
    $stmt->bindParam(":description", $description);
    $stmt->bindParam(":amount_deducted", $amount_deducted);
    $stmt->bindParam(":organization_id", $organization_id);
    $stmt->execute();
    $response["status"] = "success";
} catch (Exception $e) {
    http_response_code(400);
    $response["message"] = $e->getMessage();
}

echo json_encode($response);