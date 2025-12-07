<?php
require_once "_connect_to_database.php";
require_once "_middleware.php";
require_once "_request.php";

header("Content-Type: application/json");

require_role(['admin', 'treasurer']);

$json_put_data = json_request_body();
require_params($json_put_data, ["new_cash_on_bank"]);

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
	
	$calibrated_budget = floatval($json_put_data["new_cash_on_bank"]);
	$stmt = $pdo->prepare("UPDATE organizations SET budget_initial_calibration = ? WHERE organization_id = ?");
	$stmt->execute([$calibrated_budget, $organization_id]);

	$response["status"] = "success";
	$response["message"] = "Cash on bank updated successfully.";

} catch (Exception $e) {
	http_response_code(400);
	$response["message"] = $e->getMessage();
}

echo json_encode($response);
