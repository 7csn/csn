<?php

return [
    // session配置
    'session' => [
        'memcache' => false,
        'cookie' => [
            'save_path' => RUN_S,
            'use_cookies' => 1,
            'use_only_cookies' => 1,
            'cookie_httponly' => 1,
            'use_trans_sid' => 0
        ]
    ],
    /**
     * memcache 分布式
     * 1. 地址 => 虚节点数
     * 2. 地址    //虚节点数默认为1
     */
    'memcache' => [
        '127.0.0.1:11211' => 64,
        '127.0.0.1:11212' => 64,
        '127.0.0.1:11213' => 64
    ],
    /**
     * redis配置 分布式(伪键名)/主主(权重)->主从(权重)
     */
    'redis' => [
        'distribute' => false,  // 是否分布式
        'nodes' => [
            '127.0.0.1:6379',
//            '127.0.0.1:6380' => 9,
//            '127.0.0.1:6381' => [
//                'weight' => 3,
//                'slave' => [
//                    '127.0.0.1:6382',
//                    '127.0.0.1:6383' => 2,
//                ]
//            ]
        ],
        'auth' => []    // 地址 => 密码
    ],
    /**
     * mysql配置 主主(权重)->主从(权重)
     */
    'model' => [
        'nodes' => [
            '127.0.0.1:3306',
            '127.0.0.1:6379',
            '127.0.0.1:6380' => 9,
            '127.0.0.1:6381' => [
                'weight' => 3,
                'slave' => [
                    '127.0.0.1:6382',
                    '127.0.0.1:6383' => 2,
                ]
            ]
        ],
        'link' => [
            '127.0.0.1:3306' => [
                'du' => 'root',
                'dp' => 'root',
                'dbn' => 'test'
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
    // 跨库配置
    'db' => [
        [
            'dh' => '127.0.0.1',
            'db' => 'test',
            'du' => 'root',
            'dp' => 'root',
            'dth' => 'ls_'
        ]
    ]
];