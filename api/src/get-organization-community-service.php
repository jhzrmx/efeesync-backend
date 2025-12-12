<?php
require_once "_connect_to_database.php";
require_once "_middleware.php";

header("Content-Type: application/json");
require_role(["treasurer"]);

$response = ["status" => "error"];

try {
    // Query params
    $search   = isset($_GET['search']) ? trim($_GET['search']) : null;
    $type     = isset($_GET['type']) ? trim($_GET['type']) : null; // 'assigned' or 'notassigned'
    $page     = isset($_GET['page']) ? max(1, intval($_GET['page'])) : 1;
    $per_page = isset($_GET['per_page']) ? max(1, intval($_GET['per_page'])) : 10;
    $offset   = ($page - 1) * $per_page;

    // Resolve organization id from route param or code
    $organization_id = null;
    if (isset($id)) {
        $organization_id = intval($id);
    } elseif (isset($code)) {
        $stmt = $pdo->prepare("SELECT organization_id FROM organizations WHERE organization_code = :code LIMIT 1");
        $stmt->execute([":code" => $code]);
        $organization_id = $stmt->fetchColumn();
    }

    if (!$organization_id) {
        throw new Exception("Organization not found.");
    }

    // Get organization details (to know department_id)
    $orgStmt = $pdo->prepare("SELECT organization_id, department_id FROM organizations WHERE organization_id = :org_id LIMIT 1");
    $orgStmt->execute([":org_id" => $organization_id]);
    $organization = $orgStmt->fetch(PDO::FETCH_ASSOC);
    if (!$organization) throw new Exception("Organization not found.");

    // Build WHERE parts for students/program filtering & search
    $whereParts = [];
    $bind = [":org_id" => $organization_id];

    // If organization is department-scoped, restrict to programs belonging to that department
    if (!is_null($organization['department_id'])) {
        $whereParts[] = "p.department_id = :dept_id";
        $bind[":dept_id"] = (int)$organization['department_id'];
    } else {
        $whereParts[] = "1=1";
    }

    // Search (student name / student number / section / program_code / event_name)
    if ($search !== null && $search !== '') {
        $whereParts[] = "(
            u.first_name LIKE :search
            OR u.last_name LIKE :search
            OR u.middle_initial LIKE :search
            OR s.student_number_id LIKE :search
            OR s.student_section LIKE :search
            OR p.program_code LIKE :search
            OR e.event_name LIKE :search
        )";
        $bind[":search"] = "%{$search}%";
    }

    // program id filter
    if (isset($_GET["pid"])) {
        if (!empty($_GET['pid'])) {
            $whereParts[] = "s.student_current_program = :pid";
            $bind[":pid"] = $_GET["pid"];
        }
    }

    $whereSql = "WHERE " . implode(" AND ", $whereParts);

    //
    // Build HAVING clause depending on type
    //
    $havingParts = ["absences_count > 0"]; // require at least 1 absence
    if ($type !== null) {
        $t = strtolower($type);
        if ($t === "assigned") {
            // student+event already has an entry in community_service_made
            $havingParts[] = "MAX(csm.comserv_id) IS NOT NULL";
        } elseif ($t === "notassigned") {
            $havingParts[] = "MAX(csm.comserv_id) IS NULL";
        }
    }
    $havingSql = "HAVING " . implode(" AND ", $havingParts);

    //
    // COUNT total matching (we must count grouped rows: student+event combos)
    //
    $countSql = "
        SELECT COUNT(*) AS total FROM (
            SELECT
                s.student_id,
                e.event_id,
                SUM(CASE WHEN am.attendance_id IS NULL AND ae.attendance_excuse_id IS NULL THEN 1 ELSE 0 END) AS absences_count,
                MAX(csm.comserv_id) AS comserv_id
            FROM students s
            JOIN users u ON s.user_id = u.user_id AND s.is_graduated = 0
            LEFT JOIN programs p ON s.student_current_program = p.program_id

            -- events belonging to this organization that require comserv and already ended
            JOIN events e
                ON e.organization_id = :org_id
                AND e.event_sanction_has_comserv = 1
                AND e.event_end_date IS NOT NULL
                AND e.event_end_date < CURDATE()

            JOIN event_attendance_dates ead ON ead.event_id = e.event_id
            JOIN event_attendance_times eat ON eat.event_attend_date_id = ead.event_attend_date_id

            LEFT JOIN attendance_made am
                ON am.event_attend_time_id = eat.event_attend_time_id
                AND am.student_id = s.student_id

            LEFT JOIN attendance_excuse ae
                ON ae.event_attend_date_id = ead.event_attend_date_id
                AND ae.student_id = s.student_id
                AND ae.attendance_excuse_status = 'APPROVED'

            LEFT JOIN community_service_made csm
                ON csm.student_id = s.student_id
                AND csm.event_id = e.event_id

            {$whereSql}
            GROUP BY s.student_id, e.event_id
            {$havingSql}
        ) t
    ";

    $countStmt = $pdo->prepare($countSql);
    // bind all named params used in where (and org)
    foreach ($bind as $k => $v) {
        if (is_int($v)) $countStmt->bindValue($k, $v, PDO::PARAM_INT);
        else $countStmt->bindValue($k, $v, PDO::PARAM_STR);
    }
    $countStmt->execute();
    $total = (int)$countStmt->fetchColumn();
    $total_pages = $total > 0 ? (int)ceil($total / $per_page) : 1;

    //
    // Main query: return flattened rows (student + event + absences_count)
    //
    $mainSql = "
        SELECT
            s.student_id,
            s.student_number_id,
            s.student_section AS year_section,
            CASE
                WHEN u.middle_initial IS NOT NULL AND u.middle_initial <> ''
                    THEN CONCAT(u.last_name, ', ', u.first_name, ' ', u.middle_initial, '.')
                ELSE CONCAT(u.last_name, ', ', u.first_name)
            END AS full_name,
            p.program_code,
            e.event_id,
            e.event_name,
            SUM(CASE WHEN am.attendance_id IS NULL AND ae.attendance_excuse_id IS NULL THEN 1 ELSE 0 END) AS absences_count,
            MAX(csm.comserv_id) AS comserv_id
        FROM students s
        JOIN users u ON s.user_id = u.user_id
        LEFT JOIN programs p ON s.student_current_program = p.program_id

        JOIN events e
            ON e.organization_id = :org_id
            AND e.event_sanction_has_comserv = 1
            AND e.event_end_date IS NOT NULL
            AND e.event_end_date < CURDATE()
            AND FIND_IN_SET(LEFT(s.student_section, 1), e.event_target_year_levels) > 0

        JOIN event_attendance_dates ead ON ead.event_id = e.event_id
        JOIN event_attendance_times eat ON eat.event_attend_date_id = ead.event_attend_date_id

        LEFT JOIN attendance_made am
            ON am.event_attend_time_id = eat.event_attend_time_id
            AND am.student_id = s.student_id

        LEFT JOIN attendance_excuse ae
            ON ae.event_attend_date_id = ead.event_attend_date_id
            AND ae.student_id = s.student_id
            AND ae.attendance_excuse_status = 'APPROVED'

        LEFT JOIN community_service_made csm
            ON csm.student_id = s.student_id
            AND csm.event_id = e.event_id

        {$whereSql}
        GROUP BY s.student_id, e.event_id
        {$havingSql}
        ORDER BY 
            CASE WHEN MAX(csm.comserv_id) IS NULL THEN 1 ELSE 0 END DESC,
            absences_count DESC,
            u.last_name,
            u.first_name
        LIMIT :limit OFFSET :offset
    ";

    $stmt = $pdo->prepare($mainSql);

    // bind dynamic params
    foreach ($bind as $k => $v) {
        if (is_int($v)) $stmt->bindValue($k, $v, PDO::PARAM_INT);
        else $stmt->bindValue($k, $v, PDO::PARAM_STR);
    }
    $stmt->bindValue(":limit", $per_page, PDO::PARAM_INT);
    $stmt->bindValue(":offset", $offset, PDO::PARAM_INT);

    $stmt->execute();
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Flatten/format response rows for table consumption
    $data = [];
    foreach ($rows as $r) {
        $data[] = [
            "student_id"      => (int)$r["student_id"],       // hidden if needed by UI
            "event_id"        => (int)$r["event_id"],         // hidden if needed by UI
            "student_number_id"  => $r["student_number_id"],
            "student_full_name"=> $r["full_name"],
            "student_section"    => $r["year_section"],
            "program_code"    => $r["program_code"],
            "event_name"      => $r["event_name"],
            "absences"        => (int)$r["absences_count"],
            "done" => $r["comserv_id"] !== null
        ];
    }

    $response["status"] = "success";
    $response["data"] = $data;
    $response["meta"] = [
        "page" => $page,
        "per_page" => $per_page,
        "total" => $total,
        "total_pages" => $total_pages
    ];

} catch (Exception $e) {
    http_response_code(400);
    $response["message"] = $e->getMessage();
}

echo json_encode($response, JSON_PRETTY_PRINT);