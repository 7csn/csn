<?php

// 版本验证
version_compare(PHP_VERSION, '5.4', '>=') || die('Require PHP version >= 5.4');

// 框架入口
include __DIR__ . DIRECTORY_SEPARATOR . 'start.php';

// 启动框架
\csn\csn::run();