<?php

// 日志：访问路由、运行故障、SQL语句
return [
    'act' => [
        'set' => true,
        'dir' => 'act',
        'size' => 1024 * 1024 * 10
    ],
    'bug' => [
        'set' => true,
        'dir' => 'bug',
        'size' => 1024 * 1024 * 10
    ],
    'sql' => [
        'set' => true,
        'dir' => 'sql',
        'size' => 1024 * 1024 * 10
    ]
];