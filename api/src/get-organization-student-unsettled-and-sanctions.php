<?php
require_once "_connect_to_database.php";
require_once "_middleware.php";

header("Content-Type: application/json");
require_role(["treasurer", "admin"]);

$response = ["status" => "error"];

try {
    $organization_id = isset($organization_id) ? intval($organization_id) : null;
    $organization_code = isset($organization_code) ? $organization_code : null;
    $page = isset($_GET["page"]) ? max(1, intval($_GET["page"])) : 1;
    $per_page = isset($_GET["per_page"]) ? max(1, intval($_GET["per_page"])) : 10;
    $offset = ($page - 1) * $per_page;

    // Resolve org id if code is given
    if (!$organization_id && $organization_code) {
        $stmt = $pdo->prepare("SELECT organization_id FROM organizations WHERE organization_code = ?");
        $stmt->execute([$organization_code]);
        $organization_id = $stmt->fetchColumn();
    }
    if (!$organization_id) {
        throw new Exception("Organization not found.");
    }

    // Get department for org
    $stmt = $pdo->prepare("SELECT department_id FROM organizations WHERE organization_id = ?");
    $stmt->execute([$organization_id]);
    $dept_id = $stmt->fetchColumn();

    // ----------------------------
    // SEARCH + CONDITIONS
    // ----------------------------
    $conditions = ["(:dept_id IS NULL OR p.department_id = :dept_id)"];
    $params = [":dept_id" => $dept_id];

    if (isset($_GET["search"]) && trim($_GET["search"]) !== "") {
        $conditions[] = "(
            u.first_name LIKE :search
            OR u.last_name LIKE :search
            OR u.middle_initial LIKE :search
            OR s.student_number_id LIKE :search
            OR s.student_section LIKE :search
            OR u.institutional_email LIKE :search
            OR p.program_code LIKE :search
            OR d.department_code LIKE :search
        )";
        $params[":search"] = "%" . $_GET["search"] . "%";
    }

    if (isset($_GET["pid"])) {
        if (!empty($_GET['pid'])) {
            $conditions[] = "s.student_current_program = :pid";
            $params[":pid"] = $_GET["pid"];
        }
    }

    $whereClause = "WHERE " . implode(" AND ", $conditions);

    // ----------------------------
    // COUNT STUDENTS
    // ----------------------------
    $count_sql = "
        SELECT COUNT(*)
        FROM students s
        INNER JOIN programs p ON s.student_current_program = p.program_id
        INNER JOIN departments d ON p.department_id = d.department_id
        JOIN users u ON s.user_id = u.user_id
        $whereClause
    ";
    $stmt = $pdo->prepare($count_sql);
    $stmt->execute($params);
    $total = (int)$stmt->fetchColumn();

    // ----------------------------
    // PAGINATED STUDENTS
    // ----------------------------
    $student_sql = "
        SELECT s.student_id, s.student_number_id, s.student_section,
            CASE 
                WHEN u.middle_initial IS NOT NULL AND u.middle_initial <> '' 
                    THEN CONCAT(u.last_name, ', ', u.first_name, ' ', u.middle_initial, '.')
                ELSE CONCAT(u.last_name, ', ', u.first_name)
            END AS full_name
        FROM students s
        INNER JOIN programs p ON s.student_current_program = p.program_id
        INNER JOIN departments d ON p.department_id = d.department_id
        JOIN users u ON s.user_id = u.user_id
        $whereClause
        ORDER BY full_name
        LIMIT :limit OFFSET :offset
    ";
    $stmt = $pdo->prepare($student_sql);
    foreach ($params as $key => $val) {
        $stmt->bindValue($key, $val);
    }
    $stmt->bindValue(":limit", $per_page, PDO::PARAM_INT);
    $stmt->bindValue(":offset", $offset, PDO::PARAM_INT);
    $stmt->execute();
    $students = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $student_ids = array_column($students, "student_id");
    if (!$student_ids) {
        $response["status"] = "success";
        $response["data"] = [];
        $response["meta"] = [
            "page" => $page,
            "per_page" => $per_page,
            "total" => $total,
            "total_pages" => ceil($total / $per_page)
        ];
        echo json_encode($response);
        exit;
    }

    // ----------------------------
    // CONTRIBUTIONS BREAKDOWN
    // ----------------------------
    $contri_sql = "
        SELECT 
            s.student_id,
            e.event_id,
            e.event_name,
            ec.event_contri_fee,
            ec.event_contri_sanction_fee,
            IFNULL(SUM(cm.amount_paid),0) AS total_paid,
            (ec.event_contri_fee + ec.event_contri_sanction_fee) AS total_due,
            e.event_end_date
        FROM students s
        INNER JOIN programs p ON s.student_current_program = p.program_id
        INNER JOIN event_contributions ec ON TRUE
        INNER JOIN events e ON ec.event_id = e.event_id
        LEFT JOIN contributions_made cm 
            ON cm.event_contri_id = ec.event_contri_id 
           AND cm.student_id = s.student_id
        WHERE s.student_id IN (" . implode(",", $student_ids) . ")
          AND FIND_IN_SET(LEFT(s.student_section, 1), e.event_target_year_levels) > 0
          AND e.organization_id = $organization_id
        GROUP BY s.student_id, e.event_id, ec.event_contri_fee, ec.event_contri_sanction_fee, e.event_end_date
    ";
    $stmt = $pdo->query($contri_sql);
    $contri_rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // ----------------------------
    // ATTENDANCE SANCTIONS
    // ----------------------------
    $attend_sql = "
        SELECT 
            s.student_id,
            e.event_id,
            e.event_name,
            SUM(eat.event_attend_sanction_fee) AS total_due,
            e.event_end_date
        FROM students s
        INNER JOIN programs p ON s.student_current_program = p.program_id
        INNER JOIN events e ON TRUE
        INNER JOIN event_attendance_dates ead ON e.event_id = ead.event_id
        INNER JOIN event_attendance_times eat ON ead.event_attend_date_id = eat.event_attend_date_id
        LEFT JOIN attendance_made am 
            ON am.event_attend_time_id = eat.event_attend_time_id 
           AND am.student_id = s.student_id
        LEFT JOIN attendance_excuse ae 
            ON ae.event_attend_date_id = ead.event_attend_date_id 
           AND ae.student_id = s.student_id
           AND ae.attendance_excuse_status = 'APPROVED'
        WHERE s.student_id IN (" . implode(",", $student_ids) . ")
          AND FIND_IN_SET(LEFT(s.student_section, 1), e.event_target_year_levels) > 0
          AND am.attendance_id IS NULL
          AND ae.attendance_excuse_id IS NULL
          AND e.organization_id = $organization_id
        GROUP BY s.student_id, e.event_id, e.event_name, e.event_end_date
    ";
    $stmt = $pdo->query($attend_sql);
    $attend_rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // ----------------------------
    // PAID SANCTIONS
    // ----------------------------
    $paid_attend_sql = "
        SELECT student_id, event_id, SUM(amount_paid) AS total_paid
        FROM paid_attendance_sanctions
        WHERE payment_status = 'APPROVED'
          AND student_id IN (" . implode(",", $student_ids) . ")
        GROUP BY student_id, event_id
    ";
    $stmt = $pdo->query($paid_attend_sql);
    $paid_attend = $stmt->fetchAll(PDO::FETCH_ASSOC);
    $paid_map = [];
    foreach ($paid_attend as $r) {
        $paid_map[$r["student_id"]][$r["event_id"]] = (float)$r["total_paid"];
    }

    // ----------------------------
    // ASSEMBLE DATA
    // ----------------------------
    $student_map = [];
    foreach ($students as $s) {
        $student_map[$s["student_id"]] = [
            "student_id" => (int)$s["student_id"],
            "student_number_id" => $s["student_number_id"],
            "full_name" => $s["full_name"],
            "student_section" => $s["student_section"],
            "contributions_needed" => [],
            "attendance_sanctions" => [],
            "total_balance" => 0
        ];
    }

    foreach ($contri_rows as $row) {
        if ($row["event_end_date"] >= date("Y-m-d")) continue;
        $due = (float)$row["total_due"];
        $paid = (float)$row["total_paid"];
        if ($paid < $due) {
            $balance = $due - $paid;
            $student_map[$row["student_id"]]["contributions_needed"][] = [
                "event_id" => (int)$row["event_id"],
                "event_name" => $row["event_name"],
                "amount" => number_format($due, 2, '.', ''),
                "paid" => number_format($paid, 2, '.', ''),
                "balance" => number_format($balance, 2, '.', '')
            ];
            $student_map[$row["student_id"]]["total_balance"] += $balance;
        }
    }

    foreach ($attend_rows as $row) {
        if ($row["event_end_date"] >= date("Y-m-d")) continue;
        $due = (float)$row["total_due"];
        $paid = isset($paid_map[$row["student_id"]][$row["event_id"]]) ? $paid_map[$row["student_id"]][$row["event_id"]] : 0;
        if ($paid < $due) {
            $balance = $due - $paid;
            $student_map[$row["student_id"]]["attendance_sanctions"][] = [
                "event_id" => (int)$row["event_id"],
                "event_name" => $row["event_name"],
                "amount" => number_format($due, 2, '.', ''),
                "paid" => number_format($paid, 2, '.', ''),
                "balance" => number_format($balance, 2, '.', '')
            ];
            $student_map[$row["student_id"]]["total_balance"] += $balance;
        }
    }

    $data = array_values($student_map);

    $data = array_values(array_filter($data, function($s) {
        return $s["total_balance"] > 0;
    }));

    // ----------------------------
    // RESPONSE
    // ----------------------------
    $response["status"] = "success";
    $response["data"] = $data;
    $response["meta"] = [
        "page" => $page,
        "per_page" => $per_page,
        "total" => count($data), // old code $total
        "total_pages" => ceil(count($data) / $per_page) // old code ceil($total / $per_page)
    ];

} catch (Exception $e) {
    http_response_code(400);
    $response["message"] = $e->getMessage();
}

echo json_encode($response);