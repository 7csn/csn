<?php

// 日志：访问路由、运行故障、SQL语句
return [
    'action' => [
        'set' => true,
        'size' => 1024 * 1024 * 10
    ],
    'error' => [
        'set' => true,
        'size' => 1024 * 1024 * 10
    ],
    'sql' => [
        'set' => true,
        'size' => 1024 * 1024 * 10
    ]
];