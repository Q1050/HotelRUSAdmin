<?php

return [
    'trial_days' => (int) env('SUBSCRIPTION_TRIAL_DAYS', 14),
    'grace_days' => (int) env('SUBSCRIPTION_GRACE_DAYS', 7),
    'retention_days' => (int) env('SUBSCRIPTION_RETENTION_DAYS', 30),
];
