<?php
require_once "_connect_to_database.php";
require_once "_middleware.php";

header("Content-Type: application/json");
require_role(["admin", "treasurer"]);

$response = ["status" => "error"];

try {
    // Router params
	if (!isset($id)) throw new Exception("Missing event id.");

	$event_id = intval($id);

	if (isset($event_attend_date_id)) {
		// Use event_attend_date_id directly
		$stmt = $pdo->prepare("
			SELECT event_attend_date_id, event_attend_date 
			FROM event_attendance_dates 
			WHERE event_attend_date_id = :date_id AND event_id = :event_id
		");
		$stmt->execute([":date_id" => $event_attend_date_id, ":event_id" => $event_id]);
		$dateRow = $stmt->fetch(PDO::FETCH_ASSOC);
		if (!$dateRow) throw new Exception("Attendance date not found for event.");
	} elseif (isset($date)) {
		// Convert YYYY-MM-DD → event_attend_date_id
		$stmt = $pdo->prepare("
			SELECT event_attend_date_id, event_attend_date 
			FROM event_attendance_dates 
			WHERE event_attend_date = :date AND event_id = :event_id
		");
		$stmt->execute([":date" => $date, ":event_id" => $event_id]);
		$dateRow = $stmt->fetch(PDO::FETCH_ASSOC);
		if (!$dateRow) throw new Exception("Attendance date not found for event.");
		$event_attend_date_id = $dateRow["event_attend_date_id"];
	} else {
		throw new Exception("Either event_attend_date_id or date must be provided.");
	}

    $event_id = intval($id);
    $date_id  = intval($event_attend_date_id);

    // Query params (filter + paginate)
    $search   = isset($_GET['search']) ? trim($_GET['search']) : "";
    $page     = isset($_GET['page']) ? max(1, intval($_GET['page'])) : 1;
    $per_page = isset($_GET['per_page']) ? max(1, intval($_GET['per_page'])) : 20;
    $offset   = ($page - 1) * $per_page;

    // --- VALIDATIONS ---
    $stmt = $pdo->prepare("SELECT organization_id, department_id, event_target_year_levels 
                           FROM events WHERE event_id = :event_id");
    $stmt->execute([":event_id" => $event_id]);
    $event = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$event) throw new Exception("Event not found.");

    $stmt = $pdo->prepare("SELECT event_attend_date 
                           FROM event_attendance_dates 
                           WHERE event_attend_date_id = :date_id AND event_id = :event_id");
    $stmt->execute([":date_id" => $date_id, ":event_id" => $event_id]);
    $dateRow = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$dateRow) throw new Exception("Attendance date not found for event.");

    // --- FETCH TIME SLOTS ---
    $stmt = $pdo->prepare("
        SELECT event_attend_time_id, event_attend_time
        FROM event_attendance_times
        WHERE event_attend_date_id = :date_id
        ORDER BY FIELD(event_attend_time, 'AM IN','AM OUT','PM IN','PM OUT'), event_attend_time_id
    ");
    $stmt->execute([":date_id" => $date_id]);
    $times = $stmt->fetchAll(PDO::FETCH_ASSOC);

    if (!$times) {
        $response["status"] = "success";
        $response["data"] = [];
        $response["meta"] = ["page" => $page, "per_page" => $per_page, "total" => 0];
        echo json_encode($response);
        exit;
    }

    // --- BUILD QUERY ---
    $timeCols = [];
    $params = [":date_id" => $date_id];

    $i = 0;
    foreach ($times as $t) {
        $i++;
        $labelParam = ":label_$i";
        $params[$labelParam] = $t['event_attend_time'];
        $colKey = strtoupper(str_replace(" ", "_", $t['event_attend_time']));
        $timeCols[] = [
            "label" => $t['event_attend_time'],
            "col"   => $colKey,
            "time_id" => (int)$t['event_attend_time_id']
        ];
    }

    $selectCols = [];
    $i = 0;
    foreach ($timeCols as $tc) {
        $i++;
        $labelParam = ":label_$i";
        $selectCols[] = "
            CASE
                WHEN EXISTS (
                    SELECT 1 FROM attendance_excuse ae
                    WHERE ae.event_attend_date_id = :date_id
                      AND ae.student_id = s.student_id
                      AND ae.attendance_excuse_status = 'APPROVED'
                ) THEN 'Excused'
                WHEN EXISTS (
                    SELECT 1 FROM attendance_made am
                    JOIN event_attendance_times eat ON eat.event_attend_time_id = am.event_attend_time_id
                    WHERE eat.event_attend_date_id = :date_id
                      AND eat.event_attend_time = {$labelParam}
                      AND am.student_id = s.student_id
                ) THEN 'Present'
                ELSE 'Absent'
            END AS `{$tc['col']}`
        ";
    }

    $dynamicCols = implode(", ", $selectCols);

    // Student filter condition
    $whereSearch = "";
    if ($search !== "") {
        $whereSearch = " AND (u.first_name LIKE :search 
                           OR u.last_name LIKE :search 
                           OR s.student_number_id LIKE :search)";
        $params[":search"] = "%$search%";
    }

    // --- Year level filter ---
    $yearFilter = "";
    if (!empty($event['event_target_year_levels'])) {
        // Example stored as: "1,2,3" → filter students whose LEFT(student_section,1) is in this list
        $yearLevels = array_map('trim', explode(",", $event['event_target_year_levels']));
        $inYears = implode(",", array_map("intval", $yearLevels));
        $yearFilter = " AND LEFT(s.student_section,1) IN ($inYears)";
    }

    if (!empty($event['department_id'])) {
        $sql = "
            SELECT SQL_CALC_FOUND_ROWS
                   s.student_id,
                   CONCAT(u.last_name, ', ', u.first_name, IFNULL(CONCAT(' ', u.middle_initial), '')) AS student_full_name,
                   s.student_number_id,
                   $dynamicCols
            FROM students s
            JOIN users u ON s.user_id = u.user_id
            JOIN programs p ON s.student_current_program = p.program_id
            WHERE p.department_id = :dept_id $yearFilter $whereSearch
            ORDER BY u.last_name, u.first_name
            LIMIT :offset, :per_page
        ";
        $params[":dept_id"] = $event['department_id'];
    } else {
        $sql = "
            SELECT SQL_CALC_FOUND_ROWS
                   s.student_id,
                   CONCAT(u.last_name, ', ', u.first_name, IFNULL(CONCAT(' ', u.middle_initial), '')) AS student_full_name,
                   s.student_number_id,
                   $dynamicCols
            FROM students s
            JOIN users u ON s.user_id = u.user_id
            WHERE 1=1 $yearFilter $whereSearch
            ORDER BY u.last_name, u.first_name
            LIMIT :offset, :per_page
        ";
    }

    $stmt = $pdo->prepare($sql);
    foreach ($params as $k => $v) {
        if ($k === ":offset" || $k === ":per_page") continue;
        $stmt->bindValue($k, $v);
    }
    $stmt->bindValue(":offset", $offset, PDO::PARAM_INT);
    $stmt->bindValue(":per_page", $per_page, PDO::PARAM_INT);
    $stmt->execute();
    $students = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Total count
    $total = $pdo->query("SELECT FOUND_ROWS()")->fetchColumn();

    // --- TRANSFORM TO NESTED STRUCTURE ---
    $data = [];
    foreach ($students as $s) {
        $attendanceMap = [];
        foreach ($timeCols as $tc) {
            $attendanceMap[$tc['label']] = $s[$tc['col']];
            unset($s[$tc['col']]);
        }
        $data[] = [
            "student_id" => $s["student_id"],
            "student_full_name" => $s["student_full_name"],
            "student_number_id" => $s["student_number_id"],
            "attendance" => $attendanceMap
        ];
    }

    $response["status"] = "success";
    $response["event_attend_date_id"] = $date_id;
    $response["event_attend_date"] = $dateRow["event_attend_date"];
    $response["data"] = $data;
    $response["meta"] = [
        "page" => $page,
        "per_page" => $per_page,
        "total" => (int)$total,
        "total_pages" => ceil($total / $per_page)
    ];

} catch (Exception $e) {
	http_response_code(400);
    $response["message"] = $e->getMessage();
}

echo json_encode($response);