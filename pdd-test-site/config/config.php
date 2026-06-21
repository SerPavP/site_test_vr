<?php
return [
    'app_name' => 'PDD Test',
    'base_url' => 'http://127.0.0.1:8081/public',
    'db' => [
        'driver' => 'sqlite',
        'sqlite_path' => __DIR__ . '/../database/pdd.sqlite',
        'mysql' => [
            'host' => '127.0.0.1',
            'port' => '3306',
            'database' => 'pdd_test',
            'username' => 'root',
            'password' => '1212',
            'charset' => 'utf8mb4',
        ],
    ],
    'admin' => [
        'username' => 'admin',
        'password' => 'admin123',
    ],
    'security' => [
        'api_key' => 'pdd_355a196716974af584c2aa219afdd9d543000a99d339430b85cdc2bf9ea9a1ab',
        'rate_limit_per_5_min' => 30,
    ],
];
