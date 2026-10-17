<?php

return [

    // Month (1-12) a school year starts in. SY "2026-2027" runs from the 1st
    // of this month in 2026 to the day before it in 2027. Used by the
    // school year filter on Reports & Analytics.
    'year_start_month' => (int) env('SCHOOL_YEAR_START_MONTH', 8),

];
