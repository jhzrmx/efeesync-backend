<?php
function get_school_year_range($schoolYear = null, $schoolYearStartMonth = 8, $schoolYearEndMonth = 5)
{
    $today        = new DateTime();
    $currentYear  = (int)$today->format("Y");
    $currentMonth = (int)$today->format("m");

    $semester = null;

    if ($schoolYear) {
        // Allowed formats:
        //   2023-2024
        //   2023-2024-1
        //   2023-2024-2
        $parts = explode("-", $schoolYear);

        $startYear = (int)$parts[0];
        $endYear   = (int)$parts[1];

        if (isset($parts[2])) {
            $semester = (int)$parts[2]; // 1 or 2
        }

        // Build full-school-year start/end first
        $syStart = new DateTime("$startYear-$schoolYearStartMonth-01");
        $syEnd   = new DateTime("$endYear-$schoolYearEndMonth-" . 
            cal_days_in_month(CAL_GREGORIAN, $schoolYearEndMonth, $endYear)
        );

        // If semester included, override the computed range
        if ($semester === 1) {
            // First semester: Aug–Dec
            $semStart = new DateTime("$startYear-$schoolYearStartMonth-01");
            $semEnd   = new DateTime("$startYear-12-31");
            return [
                "start" => $semStart->format("Y-m-d"),
                "end"   => $semEnd->format("Y-m-d")
            ];
        }

        if ($semester === 2) {
            // Second semester: Jan–May
            $semStart = new DateTime("$endYear-01-01");
            $semEnd   = new DateTime("$endYear-$schoolYearEndMonth-" .
                cal_days_in_month(CAL_GREGORIAN, $schoolYearEndMonth, $endYear)
            );
            return [
                "start" => $semStart->format("Y-m-d"),
                "end"   => $semEnd->format("Y-m-d")
            ];
        }

    } else {
        // Keep existing logic for the default school year
        if ($currentMonth >= $schoolYearStartMonth && $currentMonth <= 12) {
            $syStart = new DateTime("$currentYear-$schoolYearStartMonth-01");
            $syEnd   = new DateTime(($currentYear + 1) . "-$schoolYearEndMonth-" .
                cal_days_in_month(CAL_GREGORIAN, $schoolYearEndMonth, $currentYear + 1)
            );
        } elseif ($currentMonth >= 1 && $currentMonth <= $schoolYearEndMonth) {
            $syStart = new DateTime(($currentYear - 1) . "-$schoolYearStartMonth-01");
            $syEnd   = new DateTime("$currentYear-$schoolYearEndMonth-" .
                cal_days_in_month(CAL_GREGORIAN, $schoolYearEndMonth, $currentYear)
            );
        } else {
            $syStart = new DateTime("$currentYear-$schoolYearStartMonth-01");
            $syEnd   = new DateTime(($currentYear + 1) . "-$schoolYearEndMonth-" .
                cal_days_in_month(CAL_GREGORIAN, $schoolYearEndMonth, $currentYear + 1)
            );
        }
    }

    return [
        "start" => $syStart->format("Y-m-d"),
        "end"   => $syEnd->format("Y-m-d")
    ];
}
