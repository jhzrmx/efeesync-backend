<?php
require_once "_connect_to_database.php";
require_once "_middleware.php";

header("Content-Type: application/json");

require_role(["admin", "treasurer"]);

$response = [];
$response["status"] = "error";

try {
    $sql = "SELECT 
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

    // department_id filter
    if (isset($id)) {
        $conditions[] = "d.department_id = :dept_id";
        $params[":dept_id"] = $id;
    }

    // department_code filter
    if (isset($code)) {
        $conditions[] = "d.department_code = :dept_code";
        $params[":dept_code"] = $code;
    }

    // search filter
    if (isset($search)) {
        $conditions[] = "(u.first_name LIKE :search
                        OR u.last_name LIKE :search
                        OR u.middle_initial LIKE :search
                        OR s.student_number_id LIKE :search
                        OR u.institutional_email LIKE :search
                        OR p.program_code LIKE :search
                        OR d.department_code LIKE :search)";
        $params[":search"] = "%".$search."%";
    }

    if (!empty($conditions)) {
        $sql .= " WHERE " . implode(" AND ", $conditions);
    }

    $stmt = $pdo->prepare($sql);
    foreach ($params as $param => $value) {
        $stmt->bindValue($param, $value);
    }

    $stmt->execute();
    $data = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $response["status"] = "success";
    $response["data"] = $data;
} catch (Exception $e) {
    http_response_code(500);
    $response["message"] = $e->getMessage();
}

echo json_encode($response);