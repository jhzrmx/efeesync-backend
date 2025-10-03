<?php
require_once "_connect_to_database.php";
require_once "_middleware.php";
require_once "_get_school_year_range.php";

header("Content-Type: application/json");

require_role("admin");

$response = ["status" => "error"];

try {
    // Get current school year range
    $sy = get_school_year_range();
    $syStart = $sy["start"];
    $syEnd   = $sy["end"];

    $sql = "SELECT 
            o.organization_id,
            o.organization_code,
            o.organization_name,
            o.organization_logo,
            o.department_id,
            d.department_color,
            d.department_code,

            -- Budget initial
            o.budget_initial_calibration AS budget_initial,

            -- Deductions
            IFNULL(bd.total_deductions,0) AS total_deductions,

            -- Payments
            IFNULL(paid.total_paid,0) AS total_paid,

            -- Compute total budget
            (
                o.budget_initial_calibration
                + IFNULL(paid.total_paid,0)
                - IFNULL(bd.total_deductions,0)
            ) AS total_budget,

            -- Cash on hand (SY limited)
            IFNULL(paid_sy.cash_on_hand,0) AS cash_on_hand

        FROM organizations o
        LEFT JOIN departments d 
            ON d.department_id = o.department_id

        -- Subquery: deductions
        LEFT JOIN (
            SELECT organization_id, SUM(budget_deduction_amount) AS total_deductions
            FROM budget_deductions
            GROUP BY organization_id
        ) bd ON bd.organization_id = o.organization_id

        -- Subquery: total paid
        LEFT JOIN (
            SELECT e.organization_id, 
                   SUM(pas.amount_paid) + SUM(cm.amount_paid) AS total_paid
            FROM events e
            LEFT JOIN paid_attendance_sanctions pas 
                ON pas.event_id = e.event_id AND pas.payment_status='APPROVED'
            LEFT JOIN event_contributions ec 
                ON ec.event_id = e.event_id
            LEFT JOIN contributions_made cm 
                ON cm.event_contri_id = ec.event_contri_id AND cm.payment_status='APPROVED'
            GROUP BY e.organization_id
        ) paid ON paid.organization_id = o.organization_id

        -- Subquery: cash on hand (SY only)
        LEFT JOIN (
            SELECT e.organization_id, 
                   SUM(CASE WHEN e.event_start_date BETWEEN :syStart AND :syEnd THEN pas.amount_paid ELSE 0 END)
                   + SUM(CASE WHEN e.event_start_date BETWEEN :syStart AND :syEnd THEN cm.amount_paid ELSE 0 END) 
                   AS cash_on_hand
            FROM events e
            LEFT JOIN paid_attendance_sanctions pas 
                ON pas.event_id = e.event_id AND pas.payment_status='APPROVED'
            LEFT JOIN event_contributions ec 
                ON ec.event_id = e.event_id
            LEFT JOIN contributions_made cm 
                ON cm.event_contri_id = ec.event_contri_id AND cm.payment_status='APPROVED'
            GROUP BY e.organization_id
        ) paid_sy ON paid_sy.organization_id = o.organization_id
        ";

    if (isset($id)) {
        $sql .= " HAVING organization_id = :organization_id";
        $stmt = $pdo->prepare($sql);
        $stmt->bindParam(":organization_id", $id);
    } elseif (isset($code)) {
        $sql .= " HAVING organization_code = :organization_code";
        $stmt = $pdo->prepare($sql);
        $stmt->bindParam(":organization_code", $code);
    } else {
        $stmt = $pdo->prepare($sql);
    }

    $stmt->bindParam(":syStart", $syStart);
    $stmt->bindParam(":syEnd", $syEnd);

    $stmt->execute();
    $data = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $response["status"] = "success";
    $response["data"] = $data;
} catch (Exception $e) {
    $response["message"] = $e->getMessage();
}

echo json_encode($response);