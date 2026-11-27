<?php

return [
    'hosts' => array_values(array_filter(array_map('trim', explode(',', env('MONITOR_ALLOWED_HOSTS', 'example.com'))))),
    'demo_password' => env('DEMO_PASSWORD'),
];
