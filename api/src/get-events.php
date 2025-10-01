<?php
require_once "_connect_to_database.php";
require_once "_middleware.php";
require_once "_get_school_year_range.php";

header("Content-Type: application/json");

require_role(["admin", "treasurer"]);

$response = ["status" => "error"];

try {
    $filter_type = $_GET['type'] ?? null;
    $search      = $_GET['search'] ?? null;

    // pagination
    $page     = isset($_GET['page']) ? max(1, intval($_GET['page'])) : 1;
    $per_page = isset($_GET['per_page']) ? max(1, intval($_GET['per_page'])) : 10;
    $offset   = ($page - 1) * $per_page;

    $baseSql = "FROM events e
                JOIN organizations o ON o.organization_id = e.organization_id";

    $where = [];
    $params = [];

    if (isset($organization_id)) {
        $where[] = "o.organization_id = :organization_id";
        $params[":organization_id"] = $organization_id;
        if (isset($id)) {
            $where[] = "e.event_id = :event_id";
            $params[":event_id"] = $id;
        }
    } elseif (isset($organization_code)) {
        $where[] = "o.organization_code = :organization_code";
        $params[":organization_code"] = $organization_code;
        if (isset($id)) {
            $where[] = "e.event_id = :event_id";
            $params[":event_id"] = $id;
        }
    } else {
        echo json_encode(["status" => "error", "message" => "No organization provided"]);
        exit();
    }

    $syParam = $_GET["school_year"] ?? null;
    $syRange = get_school_year_range($syParam);
    $where[] = "e.event_start_date >= :syStart AND e.event_end_date <= :syEnd";
    $params[":syStart"] = $syRange["start"];
    $params[":syEnd"] = $syRange["end"];

    if ($search) {
        $where[] = "(e.event_name LIKE :search OR e.event_description LIKE :search OR e.event_start_date LIKE :search or e.event_end_date LIKE :search)";
        $params[":search"] = "%" . $search . "%";
    }

    $whereClause = $where ? "WHERE " . implode(" AND ", $where) : "";

    // --- total count ---
    $countSql = "SELECT COUNT(*) $baseSql $whereClause";
    $stmt = $pdo->prepare($countSql);
    foreach ($params as $k => $v) $stmt->bindValue($k, $v);
    $stmt->execute();
    $total = (int) $stmt->fetchColumn();

    // --- fetch data with LIMIT ---
    $sql = "SELECT 
                e.event_id,
                e.event_name,
                e.event_description,
                e.event_start_date,
                e.event_end_date,
                e.event_target_year_levels,
                e.event_picture,
                e.event_sanction_has_comserv
            $baseSql
            $whereClause
            ORDER BY e.event_start_date DESC
            LIMIT :offset, :per_page";

    $stmt = $pdo->prepare($sql);
    foreach ($params as $k => $v) $stmt->bindValue($k, $v);
    $stmt->bindValue(":offset", $offset, PDO::PARAM_INT);
    $stmt->bindValue(":per_page", $per_page, PDO::PARAM_INT);
    $stmt->execute();
    $events = $stmt->fetchAll(PDO::FETCH_ASSOC);

    for ($i = 0; $i < count($events); $i++) {
        $event_id = $events[$i]["event_id"];

        $events[$i]["event_sanction_has_comserv"] = $events[$i]["event_sanction_has_comserv"] === 1;
        $events[$i]["event_target_year_levels"] = array_map('intval', explode(",", $events[$i]["event_target_year_levels"]));

        // contribution
        $stmt_contri = $pdo->prepare("
            SELECT event_contri_due_date, event_contri_fee, event_contri_sanction_fee
            FROM event_contributions
            WHERE event_id = :event_id
            LIMIT 1
        ");
        $stmt_contri->execute([":event_id" => $event_id]);
        $events[$i]["contribution"] = $stmt_contri->fetch(PDO::FETCH_ASSOC) ?: null;

        // attendance dates + times
        $stmt_att_dates = $pdo->prepare("
            SELECT event_attend_date_id, event_attend_date
            FROM event_attendance_dates
            WHERE event_id = :event_id
            ORDER BY event_attend_date ASC
        ");
        $stmt_att_dates->execute([":event_id" => $event_id]);
        $dates = $stmt_att_dates->fetchAll(PDO::FETCH_ASSOC);

        $attendance = [];
        $day_num = 1;
        foreach ($dates as $d) {
            $stmt_times = $pdo->prepare("
                SELECT event_attend_time, event_attend_sanction_fee
                FROM event_attendance_times
                WHERE event_attend_date_id = :date_id
                ORDER BY event_attend_time_id ASC
            ");
            $stmt_times->execute([":date_id" => $d["event_attend_date_id"]]);
            $times = $stmt_times->fetchAll(PDO::FETCH_ASSOC);

            $attendance[] = [
                "day_num" => $day_num,
                "event_attend_date" => $d["event_attend_date"],
                "event_attend_time" => array_column($times, "event_attend_time"),
                "event_attend_sanction_fee" => !empty($times) ? array_sum(array_column($times, "event_attend_sanction_fee")) / count($times) : null
            ];
            $day_num++;
        }
        $events[$i]["attendance"] = $attendance;
    }

    if ($filter_type) {
        $events = array_filter($events, function ($e) use ($filter_type) {
            $hasContri = !is_null($e["contribution"]);
            $hasAttend = !empty($e["attendance"]);

            if ($filter_type === "attendance") return $hasAttend && !$hasContri;
            if ($filter_type === "contribution") return $hasContri && !$hasAttend;
            if ($filter_type === "both") return $hasContri && $hasAttend;
            return true;
        });

        $events = array_values($events);
    }

    $response["status"] = "success";
    $response["data"] = $events;
    $response["meta"] = [
        "page" => $page,
        "per_page" => $per_page,
        "total" => $total,
        "total_pages" => ceil($total / $per_page)
    ];
} catch (Exception $e) {
    http_response_code(400);
    $response["message"] = $e->getMessage();
}

echo json_encode($response);