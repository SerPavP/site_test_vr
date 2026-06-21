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
        'api_key' => 'CHANGE_THIS_SECRET_KEY',
        'rate_limit_per_5_min' => 30,
    ],
];
