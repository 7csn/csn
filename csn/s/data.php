<?php

return [
    // session配置
    'session' => [
        'memcache' => false,
        'cookie' => [
            'use_cookies' => 1,
            'use_only_cookies' => 1,
            'cookie_httponly' => 1,
            'use_trans_sid' => 0
        ]
    ],
    // memcache配置
    'memcache' => [
        '127.0.0.1:11211' => 64,
        '127.0.0.1:11212' => 64,
        '127.0.0.1:11213' => 64
    ],
    // redis配置
    'redis' => [
        'distribute' => false,
        'nodes' => [
            '127.0.0.1:6379',
            '127.0.0.1:6380' => 9,
            '127.0.0.1:6381' => [
                'num' => 3,
                'slave' => [
                    '127.0.0.1:6382',
                    '127.0.0.1:6383' => 2,
                ]
            ]
        ]
    ],
    // mongodb配置
    'mongodb' => [
        'num' => 1,
        'nodes' => [
            '127.0.0.1:27017'
        ]
    ],
    // 数据库配置
    'database' => [
        'num' => 1,
        'nodes' => [
            '127.0.0.1:6379'
        ]
    ],
    // 跨库配置
    'db' => [
        [
            'host' => '127.0.0.1',
            'port' => '3306',
            'dbn' => 'test',
            'usr' => 'root',
            'pwd' => 'root',
            'dth' => ''
        ]
    ]
];