<?php
require_once "_connect_to_database.php";
require_once "_middleware.php";

header("Content-Type: application/json");

require_role(["treasurer"]);

$response = ["status" => "error"];

try {
    // --- Query Params ---
    $search   = $_GET['search'] ?? null;
    $status   = $_GET['status'] ?? null; // APPROVED, PENDING, REJECTED
    $page     = isset($_GET['page']) ? max(1, intval($_GET['page'])) : 1;
    $per_page = isset($_GET['per_page']) ? max(1, intval($_GET['per_page'])) : 10;
    $offset   = ($page - 1) * $per_page;

    // --- Identify Organization ---
    $organization_id = null;
    $department_id   = null;

    if (isset($id)) {
        $organization_id = intval($id);
        $orgStmt = $pdo->prepare("SELECT organization_id, department_id FROM organizations WHERE organization_id = ?");
        $orgStmt->execute([$organization_id]);
        $organization = $orgStmt->fetch(PDO::FETCH_ASSOC);
    } elseif (isset($code)) {
        $orgStmt = $pdo->prepare("SELECT organization_id, department_id FROM organizations WHERE organization_code = ?");
        $orgStmt->execute([$code]);
        $organization = $orgStmt->fetch(PDO::FETCH_ASSOC);
        $organization_id = $organization ? $organization['organization_id'] : null;
    }

    if (!$organization_id) {
        throw new Exception("Organization not found.");
    }

    // --- Base SQL ---
    $baseSql = "
        FROM online_payments op
        JOIN online_payment_contributions opc ON opc.online_payment_id = op.online_payment_id
        JOIN contributions_made cm ON cm.contribution_id = opc.contribution_id
        JOIN event_contributions ec ON ec.event_contri_id = cm.event_contri_id
        JOIN events e ON e.event_id = ec.event_id
        JOIN students s ON s.student_id = cm.student_id
        JOIN users u ON u.user_id = s.user_id
        JOIN programs p ON p.program_id = s.student_current_program
        JOIN organizations o ON o.organization_id = e.organization_id
        WHERE o.organization_id = :organization_id
    ";

    $params = [":organization_id" => $organization_id];

    // --- Optional Filters ---
    if ($status) {
        $baseSql .= " AND op.status = :status";
        $params[":status"] = $status;
    }

    if ($search) {
        $baseSql .= " AND (u.first_name LIKE :search OR u.last_name LIKE :search OR e.event_name LIKE :search)";
        $params[":search"] = "%$search%";
    }

    // --- Count Total ---
    $countSql = "SELECT COUNT(*) " . $baseSql;
    $countStmt = $pdo->prepare($countSql);
    $countStmt->execute($params);
    $total = (int)$countStmt->fetchColumn();
    $total_pages = $total > 0 ? ceil($total / $per_page) : 1;

    // --- Main Query ---
    $sql = "
        SELECT
            op.online_payment_id,
            op.method,
            op.image_proof,
            op.payment_date,
            op.status AS payment_status,
            cm.contribution_id,
            cm.payment_status AS contribution_status,
            e.event_id,
            e.event_name,
            ec.event_contri_fee,
            u.user_id,
            u.first_name,
            u.last_name,
            CASE 
                WHEN u.middle_initial IS NOT NULL AND u.middle_initial <> '' 
                    THEN CONCAT(u.last_name, ', ', u.first_name, ' ', u.middle_initial, '.')
                ELSE CONCAT(u.last_name, ', ', u.first_name)
            END AS full_name,
            s.student_id,
            s.student_number_id,
            s.student_section,
            p.program_name,
            o.organization_name,
            o.organization_code
        " . $baseSql . "
        ORDER BY op.payment_date DESC
        LIMIT :limit OFFSET :offset
    ";

    $stmt = $pdo->prepare($sql);
    foreach ($params as $key => $val) {
        $stmt->bindValue($key, $val);
    }
    $stmt->bindValue(":limit", $per_page, PDO::PARAM_INT);
    $stmt->bindValue(":offset", $offset, PDO::PARAM_INT);
    $stmt->execute();

    $data = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // --- Response ---
    $response["status"] = "success";
    $response["data"] = $data;
    $response["meta"] = [
        "page"        => $page,
        "per_page"    => $per_page,
        "total"       => $total,
        "total_pages" => $total_pages
    ];

} catch (Exception $e) {
    http_response_code(400);
    $response["message"] = $e->getMessage();
}

echo json_encode($response, JSON_PRETTY_PRINT);