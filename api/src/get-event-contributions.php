<?php
require_once "_connect_to_database.php";
require_once "_middleware.php";

header("Content-Type: application/json");
require_role(["admin", "treasurer"]);

$response = ["status" => "error"];

try {
    if (!isset($organization_id) || !isset($id)) {
        throw new Exception("Organization ID and Event ID are required.");
    }

    $baseSql = "
        SELECT 
            s.student_id,
            s.student_number_id,
            CASE 
                WHEN u.middle_initial IS NOT NULL AND u.middle_initial <> '' 
                    THEN CONCAT(u.first_name, ' ', u.middle_initial, '. ', u.last_name)
                ELSE CONCAT(u.first_name, ' ', u.last_name)
            END AS full_name,
            ec.event_contri_fee,
            IFNULL(SUM(p.amount_paid), 0) AS total_paid,
            (ec.event_contri_fee - IFNULL(SUM(p.amount_paid), 0)) AS remaining_balance
        FROM students s
        JOIN users u ON u.user_id = s.user_id
        JOIN programs pr ON pr.program_id = s.student_current_program
        JOIN departments d ON d.department_id = pr.department_id
        JOIN organizations o ON o.organization_id = d.organization_id
        JOIN event_contributions ec ON ec.event_id = :event_id
        LEFT JOIN event_contribution_payments p 
            ON p.student_id = s.student_id 
           AND p.event_id = ec.event_id
        WHERE o.organization_id = :org_id
    ";

    // ---- Filters ----
    $conditions = [];
    $params = [":org_id" => $organization_id, ":event_id" => $id];

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
    $totalRows = $stmtCount->fetchColumn();

    // ---- Pagination ----
    $limit = isset($_GET["limit"]) ? max(1, (int)$_GET["limit"]) : 10;
    $page  = isset($_GET["page"]) ? max(1, (int)$_GET["page"]) : 1;
    $offset = ($page - 1) * $limit;

    $finalSql = $baseSql . " ORDER BY u.last_name, u.first_name LIMIT :limit OFFSET :offset";

    $stmt = $pdo->prepare($finalSql);
    foreach ($params as $key => $val) {
        $stmt->bindValue($key, $val);
    }
    $stmt->bindValue(":limit", $limit, PDO::PARAM_INT);
    $stmt->bindValue(":offset", $offset, PDO::PARAM_INT);

    $stmt->execute();
    $students = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $response["status"] = "success";
    $response["page"] = $page;
    $response["limit"] = $limit;
    $response["total_rows"] = $totalRows;
    $response["total_pages"] = ceil($totalRows / $limit);
    $response["data"] = $students;

} catch (Exception $e) {
    http_response_code(500);
    $response["message"] = $e->getMessage();
}

echo json_encode($response);