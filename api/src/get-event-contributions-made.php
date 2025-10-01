<?php
require_once "_connect_to_database.php";
require_once "_middleware.php";

header("Content-Type: application/json");
require_role(["admin", "treasurer"]);

$response = ["status" => "error"];

try {
    if (!isset($id)) {
        throw new Exception("Event ID is required.");
    }

    // --- Get event info (so we can filter by target year levels) ---
    $stmt = $pdo->prepare("
        SELECT e.event_id, e.event_target_year_levels, d.department_id
        FROM events e
        JOIN organizations o ON e.organization_id = o.organization_id
        LEFT JOIN departments d  ON o.department_id = d.department_id 
        WHERE event_id = :event_id
    ");
    $stmt->execute([":event_id" => $id]);
    $event = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$event) throw new Exception("Event not found.");

    $targetYears = [];
    if (!empty($event["event_target_year_levels"])) {
        // Assume it's stored like "1,2,3" or "1|2|3"
        $targetYears = preg_split("/[,\|]/", $event["event_target_year_levels"]);
        $targetYears = array_map("trim", $targetYears);
    }

    // ---- Base SQL ----
    $baseSql = "
        SELECT 
            s.student_id,
            s.student_number_id,
            s.student_section,
            CASE 
                WHEN u.middle_initial IS NOT NULL AND u.middle_initial <> '' 
                    THEN CONCAT(u.last_name, ', ', u.first_name, ' ', u.middle_initial, '.')
                ELSE CONCAT(u.last_name, ', ', u.first_name)
            END AS full_name,
            ec.event_contri_fee,
            IFNULL(SUM(cm.amount_paid), 0) AS total_paid,
            (ec.event_contri_fee - IFNULL(SUM(cm.amount_paid), 0)) AS remaining_balance
        FROM students s
        JOIN users u ON u.user_id = s.user_id
        JOIN programs pr ON pr.program_id = s.student_current_program
        JOIN event_contributions ec ON ec.event_id = :event_id
        LEFT JOIN contributions_made cm 
            ON cm.student_id = s.student_id 
           AND cm.event_contri_id = ec.event_contri_id
           AND cm.payment_status = 'APPROVED'
    ";

    // ---- Filters ----
    $conditions = [];
    $params = [];
    $params[":event_id"] = $id;

    // Check if the event is university wide
    if (!empty($event['department_id'])) {
        $baseSql .= " WHERE pr.department_id = :dept_id";
        $params[":dept_id"]  = $event["department_id"];
    } else {
        $baseSql .= " WHERE 1=1";
    }

    // Filter by event target year levels
    if (!empty($targetYears)) {
        $placeholders = [];
        foreach ($targetYears as $i => $year) {
            $ph = ":year" . $i;
            $placeholders[] = "LEFT(s.student_section,1) = $ph";
            $params[$ph] = $year;
        }
        $conditions[] = "(" . implode(" OR ", $placeholders) . ")";
    }

    if (isset($_GET["student_id"])) {
        $conditions[] = "s.student_id = :student_id";
        $params[":student_id"] = $_GET["student_id"];
    }

    if (isset($_GET["student_number"])) {
        $conditions[] = "s.student_number_id = :student_number";
        $params[":student_number"] = $_GET["student_number"];
    }

    if (isset($_GET["search"])) {
        $conditions[] = "(u.first_name LIKE :search
                        OR u.last_name LIKE :search
                        OR u.middle_initial LIKE :search
                        OR s.student_number_id LIKE :search
                        OR s.student_section LIKE :search
                        OR u.institutional_email LIKE :search)";
        $params[":search"] = "%" . $_GET["search"] . "%";
    }

    if (!empty($conditions)) {
        $baseSql .= " AND " . implode(" AND ", $conditions);
    }

    $baseSql .= " GROUP BY s.student_id, ec.event_contri_fee";

    // ---- Count total rows for pagination ----
    $countSql = "SELECT COUNT(*) FROM (" . $baseSql . ") AS total_count";
    $stmtCount = $pdo->prepare($countSql);
    $stmtCount->execute($params);
    $total = $stmtCount->fetchColumn();

    // ---- Pagination ----
    $limit = isset($_GET["per_page"]) ? max(1, (int)$_GET["per_page"]) : 10;
    $page  = isset($_GET["page"]) ? max(1, (int)$_GET["page"]) : 1;
    $offset = ($page - 1) * $limit;

    $finalSql = $baseSql . " ORDER BY remaining_balance DESC LIMIT :limit OFFSET :offset"; //can be ORDER BY remaining_balance

    $stmt = $pdo->prepare($finalSql);
    foreach ($params as $key => $val) {
        $stmt->bindValue($key, $val);
    }
    $stmt->bindValue(":limit", $limit, PDO::PARAM_INT);
    $stmt->bindValue(":offset", $offset, PDO::PARAM_INT);

    $stmt->execute();
    $students = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $response["status"] = "success";
	$response["data"] = $students;
	$response["meta"] = [
        "page" => $page,
        "per_page" => $limit,
        "total" => (int)$total,
        "total_pages" => ceil($total / $limit)
    ];

} catch (Exception $e) {
    http_response_code(500);
    $response["message"] = $e->getMessage();
}

echo json_encode($response);