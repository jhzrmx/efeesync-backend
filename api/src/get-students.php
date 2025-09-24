<?php
require_once "_connect_to_database.php";
require_once "_middleware.php";

header("Content-Type: application/json");

require_role(["admin", "treasurer"]);

$response = ["status" => "error"];

try {
    $baseSql = "SELECT 
            s.student_id, 
            s.student_number_id, 
            s.student_section, 
            s.last_active,
            u.user_id, 
            u.institutional_email, 
            u.first_name, 
            u.last_name, 
            u.middle_initial, 
            u.picture,
            p.program_code,
            d.department_id,
            d.department_code,
            d.department_name,
            CASE 
                WHEN u.middle_initial IS NOT NULL AND u.middle_initial <> '' 
                    THEN CONCAT(u.first_name, ' ', u.middle_initial, '. ', u.last_name)
                ELSE CONCAT(u.first_name, ' ', u.last_name)
            END AS full_name
        FROM students s
        JOIN users u ON u.user_id = s.user_id
        JOIN programs p ON p.program_id = s.student_current_program
        JOIN departments d ON d.department_id = p.department_id";

    $conditions = [];
    $params = [];

    // student_id filter
    if (isset($id)) {
        $conditions[] = "s.student_id = :id";
        $params[":id"] = $id;
    }

    // student_number_id filter
    if (isset($student_number)) {
        $conditions[] = "s.student_number_id = :student_number";
        $params[":student_number"] = $student_number;
    }

    // department_id filter
    if (isset($department_id)) {
        $conditions[] = "d.department_id = :dept_id";
        $params[":dept_id"] = $department_id;
    }

    // department_code filter
    if (isset($department_code)) {
        $conditions[] = "d.department_code = :dept_code";
        $params[":dept_code"] = $department_code;
    }

    // search filter
    if (isset($_GET["search"])) {
        $conditions[] = "(u.first_name LIKE :search
                        OR u.last_name LIKE :search
                        OR u.middle_initial LIKE :search
                        OR s.student_number_id LIKE :search
                        OR u.institutional_email LIKE :search
                        OR p.program_code LIKE :search
                        OR d.department_code LIKE :search)";
        $params[":search"] = "%".$_GET["search"]."%";
    }

    if (!empty($conditions)) {
        $baseSql .= " WHERE " . implode(" AND ", $conditions);
    }

    // Pagination setup
    $page     = isset($_GET["page"]) ? max(1, intval($_GET["page"])) : 1;
    $per_page = isset($_GET["per_page"]) ? max(1, intval($_GET["per_page"])) : 10;
    $offset   = ($page - 1) * $per_page;

    // Count total before LIMIT
    $countSql = "SELECT COUNT(*) FROM ($baseSql) AS total_count";
    $stmtCount = $pdo->prepare($countSql);
    foreach ($params as $param => $value) {
        $stmtCount->bindValue($param, $value);
    }
    $stmtCount->execute();
    $total = (int)$stmtCount->fetchColumn();

    // Final query with LIMIT + OFFSET
    $sql = $baseSql . " ORDER BY u.last_name, u.first_name LIMIT :offset, :per_page";
    $stmt = $pdo->prepare($sql);

    foreach ($params as $param => $value) {
        $stmt->bindValue($param, $value);
    }
    $stmt->bindValue(":offset", $offset, PDO::PARAM_INT);
    $stmt->bindValue(":per_page", $per_page, PDO::PARAM_INT);

    $stmt->execute();
    $data = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $response["status"] = "success";
    $response["data"] = $data;
    $response["meta"] = [
        "page" => $page,
        "per_page" => $per_page,
        "total" => $total,
        "total_pages" => ceil($total / $per_page)
    ];

} catch (Exception $e) {
    http_response_code(500);
    $response["message"] = $e->getMessage();
}

echo json_encode($response);