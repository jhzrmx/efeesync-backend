<?php 
require_once "_connect_to_database.php";
require_once "_middleware.php";
require_once "_request.php";

header("Content-Type: application/json");

require_role("treasurer");

$response = ["status" => "error"];

try {
    $organization_id = null;
    if (isset($id)) {
        $organization_id = intval($id);
    } elseif (isset($code)) {
        $stmt = $pdo->prepare("SELECT organization_id, department_id FROM organizations WHERE organization_code = ?");
        $stmt->execute([$code]);
        $organization = $stmt->fetch(PDO::FETCH_ASSOC);
        $organization_id = $organization ? $organization["organization_id"] : null;
    }

    if (!$organization_id) {
        throw new Exception("Organization not found.");
    }

    // Check if the event is university wide
    $params = [":dept_id" => empty($organization["department_id"]) ? null : $organization["department_id"]];

    $stmt = $pdo->prepare("
        WITH 
        event_count AS (
            SELECT COUNT(*) AS total_events
            FROM events e
            JOIN organizations o ON e.organization_id = o.organization_id
            WHERE (o.organization_id = $organization_id OR o.department_id = :dept_id)
        ),
        student_count AS (
            SELECT COUNT(*) AS total_students
            FROM students s
            JOIN programs p ON s.student_current_program = p.program_id
            JOIN departments d ON p.department_id = d.department_id
            WHERE (:dept_id IS NULL OR d.department_id = :dept_id)
        ),
        fees_collected AS (
            SELECT COALESCE(SUM(cm.amount_paid),0) AS total_fees_collected
            FROM contributions_made cm
            JOIN event_contributions ec ON cm.event_contri_id = ec.event_contri_id
            JOIN events e ON ec.event_id = e.event_id
            JOIN organizations o ON e.organization_id = o.organization_id
            JOIN departments d ON o.department_id = d.department_id
            WHERE (:dept_id IS NULL OR d.department_id = :dept_id) AND o.department_id = :dept_id
        ),
        sanctions_collected AS (
            SELECT 
              COALESCE((
                SELECT SUM(pcs.amount_paid)
                FROM paid_contribution_sanctions pcs
                JOIN event_contributions ec ON pcs.event_contri_id = ec.event_contri_id
                JOIN events e ON ec.event_id = e.event_id
                JOIN organizations o ON e.organization_id = o.organization_id
                JOIN departments d ON o.department_id = d.department_id
                WHERE (:dept_id IS NULL OR d.department_id = :dept_id) AND o.department_id = :dept_id
              ),0)
              +
              COALESCE((
                SELECT SUM(pas.amount_paid)
                FROM paid_attendance_sanctions pas
                JOIN events e ON pas.event_id = e.event_id
                JOIN organizations o ON e.organization_id = o.organization_id
                JOIN departments d ON o.department_id = d.department_id
                WHERE (:dept_id IS NULL OR d.department_id = :dept_id) AND o.department_id = :dept_id
              ),0) AS total_sanctions_collected
        )
        SELECT 
          e.total_events,
          st.total_students,
          f.total_fees_collected,
          sn.total_sanctions_collected
        FROM event_count e
        CROSS JOIN student_count st
        CROSS JOIN fees_collected f
        CROSS JOIN sanctions_collected sn;
    ");
    $stmt->execute($params);
    $data = $stmt->fetch(PDO::FETCH_ASSOC);

    $stmt_student_population = $pdo->prepare("
        SELECT 
            SUM(CASE WHEN LEFT(s.student_section, 1) = '1' THEN 1 ELSE 0 END) AS total_first_year,
            SUM(CASE WHEN LEFT(s.student_section, 1) = '2' THEN 1 ELSE 0 END) AS total_second_year,
            SUM(CASE WHEN LEFT(s.student_section, 1) = '3' THEN 1 ELSE 0 END) AS total_third_year,
            SUM(CASE WHEN LEFT(s.student_section, 1) = '4' THEN 1 ELSE 0 END) AS total_fourth_year
        FROM students s
        JOIN programs p ON s.student_current_program = p.program_id
        JOIN departments d ON p.department_id = d.department_id
        WHERE (:dept_id IS NULL OR d.department_id= :dept_id);
    ");
    $stmt_student_population->execute($params);
    $data_student_population = $stmt_student_population->fetch(PDO::FETCH_ASSOC);

    $event_sql = "
        SELECT e.event_id, e.event_name, e.event_target_year_levels
        FROM events e
        WHERE e.organization_id = ?
        ORDER BY e.event_start_date DESC
    ";
    $event_stmt = $pdo->prepare($event_sql);
    $event_stmt->execute([$organization_id]);
    $events = $event_stmt->fetchAll(PDO::FETCH_ASSOC);

    // Start summary here
    $summary = [];
    foreach ($events as $event) {
        $event_id   = (int)$event['event_id'];
        $event_name = $event['event_name'];
        $target_levels = $event['event_target_year_levels'];

        // Initialize structure
        $student_summary = [
            "first_year"  => ["total_paid" => 0, "total_unsettled" => 0, "total_unpaid" => 0],
            "second_year" => ["total_paid" => 0, "total_unsettled" => 0, "total_unpaid" => 0],
            "third_year"  => ["total_paid" => 0, "total_unsettled" => 0, "total_unpaid" => 0],
            "fourth_year" => ["total_paid" => 0, "total_unsettled" => 0, "total_unpaid" => 0],
        ];

        // === Fetch targeted students
        $student_sql = "
            SELECT s.student_id, s.student_section
            FROM students s
            JOIN programs p ON s.student_current_program = p.program_id
            LEFT JOIN departments d ON p.department_id = d.department_id
            WHERE FIND_IN_SET(LEFT(s.student_section, 1), :target_levels)
              AND (:dept_id IS NULL OR d.department_id = :dept_id)
        ";
        $student_stmt = $pdo->prepare($student_sql);
        $student_stmt->execute([
            ":target_levels" => $target_levels,
            ":dept_id"       => empty($organization["department_id"]) ? null : $organization["department_id"],
        ]);
        $students = $student_stmt->fetchAll(PDO::FETCH_ASSOC);

        foreach ($students as $student) {
            $student_id = (int)$student["student_id"];
            $year = substr($student["student_section"], 0, 1);
            $year_key = match ($year) {
                "1" => "first_year",
                "2" => "second_year",
                "3" => "third_year",
                "4" => "fourth_year",
                default => null,
            };
            if (!$year_key) continue;

            $total_due  = 0;
            $total_paid = 0;

            // =======================
            // Contribution Obligation
            // =======================
            $contri_sql = "
                SELECT ec.event_contri_id,
                       (ec.event_contri_fee + ec.event_contri_sanction_fee) AS total_due,
                       IFNULL(SUM(cm.amount_paid), 0) AS total_paid
                FROM event_contributions ec
                LEFT JOIN contributions_made cm 
                    ON cm.event_contri_id = ec.event_contri_id
                   AND cm.student_id = ?
                LEFT JOIN paid_contribution_sanctions pcs
                    ON pcs.event_contri_id = ec.event_contri_id
                   AND pcs.student_id = ?
                   AND pcs.payment_status = 'APPROVED'
                WHERE ec.event_id = ?
                GROUP BY ec.event_contri_id, ec.event_contri_fee, ec.event_contri_sanction_fee
            ";
            $contri_stmt = $pdo->prepare($contri_sql);
            $contri_stmt->execute([$student_id, $student_id, $event_id]);
            $contri_rows = $contri_stmt->fetchAll(PDO::FETCH_ASSOC);

            foreach ($contri_rows as $row) {
                $total_due  += (float)$row["total_due"];
                $total_paid += (float)$row["total_paid"];
            }

            // =====================
            // Attendance Obligation
            // =====================
            $attend_sql = "
                SELECT eat.event_attend_time_id,
                       eat.event_attend_sanction_fee AS sanction_fee,
                       am.attendance_id,
                       ae.attendance_excuse_id
                FROM event_attendance_dates ead
                INNER JOIN event_attendance_times eat ON ead.event_attend_date_id = eat.event_attend_date_id
                LEFT JOIN attendance_made am 
                    ON am.event_attend_time_id = eat.event_attend_time_id
                   AND am.student_id = ?
                LEFT JOIN attendance_excuse ae
                    ON ae.event_attend_date_id = ead.event_attend_date_id
                   AND ae.student_id = ?
                   AND ae.attendance_excuse_status = 'APPROVED'
                WHERE ead.event_id = ?
            ";
            $attend_stmt = $pdo->prepare($attend_sql);
            $attend_stmt->execute([$student_id, $student_id, $event_id]);
            $attend_rows = $attend_stmt->fetchAll(PDO::FETCH_ASSOC);

            foreach ($attend_rows as $row) {
                if ($row["attendance_id"] === null && $row["attendance_excuse_id"] === null) {
                    $total_due += (float)$row["sanction_fee"];
                }
            }

            // Add paid attendance sanctions
            $attend_paid_sql = "
                SELECT IFNULL(SUM(amount_paid), 0) 
                FROM paid_attendance_sanctions
                WHERE student_id = ? AND event_id = ? AND payment_status = 'APPROVED'
            ";
            $attend_paid_stmt = $pdo->prepare($attend_paid_sql);
            $attend_paid_stmt->execute([$student_id, $event_id]);
            $attend_paid = (float)$attend_paid_stmt->fetchColumn();

            $total_paid += $attend_paid;

            // ====================
            // Classification
            // ====================
            if ($total_due > 0) {
                if ($total_paid >= $total_due) {
                    $student_summary[$year_key]["total_paid"]++;
                } elseif ($total_paid > 0 && $total_paid < $total_due) {
                    $student_summary[$year_key]["total_unsettled"]++;
                } elseif ($total_paid == 0) {
                    $student_summary[$year_key]["total_unpaid"]++;
                }
            }
        }

        $summary[] = [
            "event_id" => $event_id,
            "event_name" => $event_name,
            "student_summary" => $student_summary
        ];
    }

    $response = [
        "status" => "success",
        "data" => $summary
    ];

    $response["status"] = "success";
    $response["data"] = $data;
    $response["data"]["student_population"] = $data_student_population;
    $response["data"]["event_summary"] = $summary;

} catch (Exception $e) {
	http_response_code(400);
    $response["message"] = $e->getMessage();
}

echo json_encode($response);