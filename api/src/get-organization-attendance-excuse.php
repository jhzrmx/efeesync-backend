<?php
require_once "_connect_to_database.php";
require_once "_middleware.php";

header("Content-Type: application/json");

require_role(["treasurer"]);

$response = ["status" => "error"];

try {
    // query params
    $search   = $_GET['search'] ?? null;
    $page     = isset($_GET['page']) ? max(1, intval($_GET['page'])) : 1;
    $per_page = isset($_GET['per_page']) ? max(1, intval($_GET['per_page'])) : 10;
    $offset   = ($page - 1) * $per_page;

    // get organization
    $organization_id = null;
    if (isset($id)) {
        $organization_id = intval($id);
    } elseif (isset($code)) {
        $stmt = $pdo->prepare("SELECT organization_id, department_id FROM organizations WHERE organization_code = ?");
        $stmt->execute([$code]);
        $organization = $stmt->fetch(PDO::FETCH_ASSOC);
        $organization_id = $organization ? $organization["organization_id"] : null;
    }

    $orgSql = "SELECT organization_id, department_id 
               FROM organizations 
               WHERE organization_id = :organization_id";
    $orgStmt = $pdo->prepare($orgSql);
    $orgStmt->execute([":organization_id" => $organization_id]);
    $organization = $orgStmt->fetch(PDO::FETCH_ASSOC);

    if (!$organization) {
        throw new Exception("Organization not found");
    }

    // base SQL
    $baseSql = "FROM attendance_excuse ae
            INNER JOIN students s 
                ON s.student_id = ae.student_id 
                -- AND ae.attendance_excuse_proof_file IS NOT NULL
            INNER JOIN users u 
                ON s.user_id = u.user_id
            INNER JOIN event_attendance_dates ed 
                ON ed.event_attend_date_id = ae.event_attend_date_id
            INNER JOIN events e 
                ON e.event_id = ed.event_id
            INNER JOIN organizations o 
                ON o.organization_id = e.organization_id
            LEFT JOIN departments d 
                ON d.department_id = o.department_id
            WHERE (e.organization_id = :organization_id OR o.department_id IS NULL)";

    // apply search
    $params = [":organization_id" => $organization_id];
    if ($search) {
        $baseSql .= " AND (u.first_name LIKE :search 
                        OR u.last_name LIKE :search 
                        OR e.event_name LIKE :search)";
        $params[":search"] = "%$search%";
    }

    // total count
    $countSql = "SELECT COUNT(*) " . $baseSql;
    $countStmt = $pdo->prepare($countSql);
    $countStmt->execute($params);
    $total = (int)$countStmt->fetchColumn();

    $total_pages = $total > 0 ? ceil($total / $per_page) : 1;

    // main query with limit/offset
    $sql = "SELECT 
                ae.attendance_excuse_id,
                ae.attendance_excuse_reason,
                ae.attendance_excuse_status,
                ae.attendance_excuse_proof_file,
                DATE_FORMAT(ae.attendance_excuse_submitted_at, '%m/%d/%Y') AS submitted_at,
                ae.student_id,
                s.student_number_id,
                s.student_section,
                CASE 
                    WHEN u.middle_initial IS NOT NULL AND u.middle_initial <> '' 
                        THEN CONCAT(u.last_name, ', ', u.first_name, ' ', u.middle_initial, '.')
                    ELSE CONCAT(u.last_name, ', ', u.first_name)
                END AS full_name,
                e.event_id,
                e.event_name,
                DATE_FORMAT(ed.event_attend_date, '%m/%d/%Y') AS event_date,
                ed.event_attend_date_id

            $baseSql
            ORDER BY e.event_id, ed.event_attend_date, ae.attendance_excuse_submitted_at
            LIMIT :limit OFFSET :offset";

    $stmt = $pdo->prepare($sql);

    // bind params
    foreach ($params as $key => $val) {
        $stmt->bindValue($key, $val);
    }
    $stmt->bindValue(":limit", $per_page, PDO::PARAM_INT);
    $stmt->bindValue(":offset", $offset, PDO::PARAM_INT);

    $stmt->execute();
    $data = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $response["status"] = "success";
    $response["data"] = $data;
    $response["meta"] = [
        "page"        => $page,
        "per_page"    => $per_page,
        "total"       => $total,
        "total_pages" => $total_pages
    ];

} catch (Exception $e) {
    $response["message"] = $e->getMessage();
}

echo json_encode($response, JSON_PRETTY_PRINT);