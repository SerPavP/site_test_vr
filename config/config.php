<?php
return [
    'app_name' => 'Тест ПДД',
    'base_url' => 'http://127.0.0.1:8016/public',
    'db' => [
        'host' => '127.0.0.1',
        'port' => '3306',
        'database' => 'pdd_test',
        'username' => 'root',
        'password' => '1212',
        'charset' => 'utf8mb4',
    ],
    'security' => [
        'api_key' => 'pdd_live_6f2a9d1c4e8b7a5f0d3c9e2b1a4f6d8c7b9e0a2d5f1c3b6a8e4d7f0c2b5a9',
        'rate_limit_per_5_min' => 30,
    ],
];
