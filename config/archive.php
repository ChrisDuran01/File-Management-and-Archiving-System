<?php

return [

    // A school year is archived as a whole this many years after it ends
    // (archives:school-years, every night). The real period should come
    // from the adviser / Office of Student Affairs, or the university's
    // records disposition schedule. The Adviser or President can also
    // archive a finished school year sooner from the Archives page.
    'retention_years' => (int) env('ARCHIVE_RETENTION_YEARS', 3),

];
