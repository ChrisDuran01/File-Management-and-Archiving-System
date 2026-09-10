<?php

return [

    // How long soft-deleted files/folders/documents stay in the Trash
    // before trash:purge-expired removes them (and their stored bytes)
    // permanently.
    'retention_days' => (int) env('TRASH_RETENTION_DAYS', 30),

];
