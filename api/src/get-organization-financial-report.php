<?php
require_once "_connect_to_database.php";
require_once "_middleware.php";
require_once "_get_school_year_range.php";

header("Content-Type: application/json");

require_login();

$response = ["status" => "error"];

try {
    // Get current school year range
    $sy = get_school_year_range();
    $syStart = $sy["start"];
    $syEnd   = $sy["end"];

    // Input params
    $id   = $organization_id ?? null;
    $code = $organization_code ?? null;

    if (!$id && !$code) {
        throw new Exception("Organization identifier required.");
    }

    // --- Resolve organization_id if code is provided ---
    if ($code) {
        $stmt = $pdo->prepare("SELECT organization_id FROM organizations WHERE organization_code = ?");
        $stmt->execute([$code]);
        $id = $stmt->fetchColumn();
        if (!$id) throw new Exception("Organization not found.");
    }

    // --- CASH-IN: Contributions per event ---
    $sqlContri = "
        SELECT e.event_id, e.event_name, e.event_end_date,
               COALESCE(SUM(cm.amount_paid), 0) AS total_contributions
        FROM events e
        LEFT JOIN event_contributions ec ON ec.event_id = e.event_id
        LEFT JOIN contributions_made cm ON cm.event_contri_id = ec.event_contri_id 
            AND cm.payment_status = 'APPROVED'
        WHERE e.organization_id = :org_id
          AND e.event_start_date BETWEEN :syStart AND :syEnd
        GROUP BY e.event_id, e.event_name
        ORDER BY e.event_end_date DESC
    ";
    $stmt = $pdo->prepare($sqlContri);
    $stmt->execute([
        ":org_id" => $id,
        ":syStart" => $syStart,
        ":syEnd" => $syEnd
    ]);
    $contriData = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // --- CASH-IN: Attendance sanctions per event ---
    $sqlSanctions = "
        SELECT e.event_id, e.event_name, e.event_end_date,
               COALESCE(SUM(pas.amount_paid), 0) AS total_sanctions
        FROM events e
        LEFT JOIN paid_attendance_sanctions pas 
            ON pas.event_id = e.event_id AND pas.payment_status = 'APPROVED'
        WHERE e.organization_id = :org_id
          AND e.event_start_date BETWEEN :syStart AND :syEnd
        GROUP BY e.event_id, e.event_name
        ORDER BY e.event_end_date DESC
    ";
    $stmt = $pdo->prepare($sqlSanctions);
    $stmt->execute([
        ":org_id" => $id,
        ":syStart" => $syStart,
        ":syEnd" => $syEnd
    ]);
    $sanctionData = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Merge cash-in per event (contri + sanctions)
    $cashIn = [];
    foreach ($contriData as $row) {
        if ((float)$row["total_contributions"] == 0) continue;
        $cashIn[$row["event_id"]] = [
            "event_id" => $row["event_id"],
            "event_name" => $row["event_name"],
            "event_end_date" => $row["event_end_date"],
            "total_contributions" => (float)$row["total_contributions"],
            "total_cash_in" => (float)$row["total_contributions"]
        ];
    }
    foreach ($sanctionData as $row) {
        if ((float)$row["total_sanctions"] == 0 ) continue;
        if (!isset($cashIn[$row["event_id"]])) {
            $cashIn[$row["event_id"]] = [
                "event_id" => $row["event_id"],
                "event_name" => $row["event_name"],
                "event_end_date" => $row["event_end_date"],
                "total_sanctions" => (float)$row["total_sanctions"],
                "total_cash_in" => (float)$row["total_sanctions"]
            ];
        } else {
            $cashIn[$row["event_id"]]["total_sanctions"] = (float)$row["total_sanctions"];
            $cashIn[$row["event_id"]]["total_cash_in"] += (float)$row["total_sanctions"];
        }
    }

    // --- CASH-OUT: Budget deductions ---
    $sqlDeduct = "
        SELECT budget_deduction_id, budget_deduction_title, budget_deduction_amount, budget_deducted_at
        FROM budget_deductions
        WHERE organization_id = :org_id AND budget_deducted_at BETWEEN :syStart AND :syEnd
    ";
    $stmt = $pdo->prepare($sqlDeduct);
    $stmt->execute([":org_id" => $id, ":syStart" => $syStart, ":syEnd" => $syEnd]);
    $cashOut = $stmt->fetchAll(PDO::FETCH_ASSOC);

    foreach ($cashOut as &$row) {
        $row["budget_deduction_amount"] = (float)$row["budget_deduction_amount"];
    }
    unset($row);

    // --- Summaries ---
    $totalCashIn = array_sum(array_column($cashIn, "total_cash_in"));
    $totalCashOut = array_sum(array_column($cashOut, "budget_deduction_amount"));
    $netBalance = $totalCashIn - $totalCashOut;

    $response["status"] = "success";
    $response["data"] = [
        "organization_id" => $id,
        "school_year" => "$syStart - $syEnd",
        "cash_in" => array_values($cashIn),
        "cash_out" => $cashOut,
        "summary" => [
            "total_cash_in" => $totalCashIn,
            "total_cash_out" => $totalCashOut,
            "net_balance" => $netBalance
        ]
    ];

} catch (Exception $e) {
    http_response_code(400);
    $response["message"] = $e->getMessage();
}

echo json_encode($response);