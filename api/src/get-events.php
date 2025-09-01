<?php

require_once "_connect_to_database.php";
require_once "_current_role.php";

header("Content-Type: application/json");

if (!is_current_role_in(["admin", "treasurer"])) {
	http_response_code(403);
	echo json_encode(["status" => "error", "message" => "Forbidden"]);
	exit();
}

$response = [];
$response["status"] = "error";

try {
	$sql = "SELECT `event_id`, `event_name`, `event_description`, `event_target_year_levels`, `event_picture`, `event_sanction_has_comserv`
			FROM `events`
			JOIN `organizations` ON
			(`organizations`.`organization_id` = `events`.`organization_id`)";

	if (isset($organization_id)) {
		$sql .= " WHERE `organizations`.`organization_id` = :organization_id";
		if (isset($id)) {
			$sql .= " AND `events`.`event_id` = :event_id";
		}
		$stmt = $pdo->prepare($sql);
		$stmt->bindParam(":organization_id", $organization_id);
		if (isset($id)) {
			$stmt->bindParam(":event_id", $id);
		}
	} elseif (isset($organization_code)) {
		$sql .= " WHERE `organizations`.`organization_code` = :organization_code";
		if (isset($id)) {
			$sql .= " AND `events`.`event_id` = :event_id";
		}
		$stmt = $pdo->prepare($sql);
		$stmt->bindParam(":organization_code", $organization_code);
		if (isset($id)) {
			$stmt->bindParam(":event_id", $id);
		}
	} else {
		echo json_encode(["status" => "error", "message" => "No ogranization provided"]);
		exit();
	}
	$stmt->execute();
	$data = $stmt->fetchAll(PDO::FETCH_ASSOC);
	for ($i=0; $i<count($data); $i++) {
		$data[$i]["event_target_year_levels"] = array_map('intval', explode(",", $data[$i]["event_target_year_levels"]));
	}

	$response["status"] = "success";
	$response["data"] = $data;
} catch (Exception $e) {
	$response["message"] = $e->getMessage();
}

echo json_encode($response);
