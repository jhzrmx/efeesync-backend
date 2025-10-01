<?php
function get_school_year_range($schoolYear = null, $schoolYearStartMonth = 8, $schoolYearEndMonth = 5) {

    $today        = new DateTime();
    $currentYear  = (int)$today->format("Y");
    $currentMonth = (int)$today->format("m");

    if ($schoolYear) {
        // Example input: "2024-2025"
        [$startYear, $endYear] = explode("-", $schoolYear);
        $syStart = new DateTime("$startYear-$schoolYearStartMonth-01");
        $syEnd   = new DateTime("$endYear-$schoolYearEndMonth-" . cal_days_in_month(CAL_GREGORIAN, $schoolYearEndMonth, $endYear));
    } else {
        if ($currentMonth >= $schoolYearStartMonth && $currentMonth <= 12) {
            // Aug–Dec → current SY = Aug current year → May next year
            $syStart = new DateTime("$currentYear-$schoolYearStartMonth-01");
            $syEnd   = new DateTime(($currentYear + 1) . "-$schoolYearEndMonth-" . cal_days_in_month(CAL_GREGORIAN, $schoolYearEndMonth, $currentYear + 1));
        } elseif ($currentMonth >= 1 && $currentMonth <= $schoolYearEndMonth) {
            // Jan–May → current SY = Aug last year → May current year
            $syStart = new DateTime(($currentYear - 1) . "-$schoolYearStartMonth-01");
            $syEnd   = new DateTime("$currentYear-$schoolYearEndMonth-" . cal_days_in_month(CAL_GREGORIAN, $schoolYearEndMonth, $currentYear));
        } else {
            // Jun–Jul (summer break) → default to next SY
            $syStart = new DateTime("$currentYear-$schoolYearStartMonth-01");
            $syEnd   = new DateTime(($currentYear + 1) . "-$schoolYearEndMonth-" . cal_days_in_month(CAL_GREGORIAN, $schoolYearEndMonth, $currentYear + 1));
        }
    }

    return [
        "start" => $syStart->format("Y-m-d"),
        "end"   => $syEnd->format("Y-m-d")
    ];
}
