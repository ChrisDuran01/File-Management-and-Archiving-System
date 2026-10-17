<?php

return [

    // How long activity_logs rows (user name, action, IP) are kept before
    // logs:prune deletes them. This is the audit trail, so it is kept longer
    // than the other logs.
    'activity_log_days' => (int) env('ACTIVITY_LOG_RETENTION_DAYS', 730),

    // How long help_bot_logs rows (user + question/answer text) are kept.
    'help_bot_log_days' => (int) env('HELP_BOT_LOG_RETENTION_DAYS', 180),

    // How long anonymous student-page engagement events (engagement_events)
    // are kept. Two years, so a school year can still be compared with the
    // one before it.
    'engagement_event_days' => (int) env('ENGAGEMENT_EVENT_RETENTION_DAYS', 730),

];
