<?php
require_once "_connect_to_database.php";
require_once "_current_role.php";

header("Content-Type: application/json");

if (!is_current_role_in(['admin', 'treasurer'])) {
	http_response_code(403);
	echo json_encode(["status" => "error", "message" => "Forbidden"]);
	exit();
}

$response = ["status" => "error"];

try {
	$json_put_data = json_decode(file_get_contents("php://input"), true);
	if (!$json_put_data) throw new Exception("Invalid JSON body.");

	$code = $json_put_data["organization_code"];
	$name = $json_put_data["organization_name"];
	$color = $json_put_data["organization_color"];
	$dept_id = $json_put_data["department_id"];

	// Ensure organization exists
	$stmt = $pdo->prepare("SELECT * FROM organizations WHERE organization_id = ?");
	$stmt->execute([$id]);
	if (!$stmt->fetch()) throw new Exception("Organization not found.");

	$stmt = $pdo->prepare("UPDATE organizations SET organization_code = ?, organization_name = ?, organization_color = ?, department_id = ? WHERE organization_id = ?");
	$stmt->execute([$code, $name, $color, $dept_id, $id]);

	$response["status"] = "success";
	$response["message"] = "Organization updated successfully.";

} catch (Exception $e) {
	http_response_code(400);
	$response["message"] = $e->getMessage();
}

echo json_encode($response);
