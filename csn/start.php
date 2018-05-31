<?php

// 初始内存
define('CSN_MEM_START', memory_get_usage());

// 当前时间戳
define('CSN_TIME', $_SERVER['REQUEST_TIME']);

// 初始时间
define('CSN_START', $_SERVER['REQUEST_TIME_FLOAT']);

// 目录分隔符
define('XG', DIRECTORY_SEPARATOR);

// 框架相关目录
define('CSN', __DIR__ . XG);
define('CSN_T', CSN . 't' . XG);
define('CSN_S', CSN . 's' . XG);
define('CSN_Y', CSN . 'y' . XG);
define('CSN_X', CSN . 'x' . XG);

// 项目相关目录
define('PUB', realpath('.') . XG);
define('APP', dirname(PUB) . XG);
define('APP_C', APP . 'controllers' . XG);
define('APP_M', APP . 'models' . XG);
define('APP_V', APP . 'views' . XG);
define('APP_S', APP . 'session' . XG);
define('APP_R', APP . 'runtime' . XG);
define('APP_T', APP . 'template' . XG);

// 基于框架项目根目录
define('WEB', dirname(APP) . XG);

// 引入框架核心类文件
include CSN_T . 'Csn.php';

// 自加载composer类库
\csn\t\Csn::inc(WEB . 'vendor' . XG . 'autoload.php');

// 自加载框架、项目类
spl_autoload_register('\csn\t\Csn::load');

// 自定义错误、异常
set_error_handler('\csn\t\Exp::error');
set_exception_handler('\csn\t\Exp::exception');

// 框架初始化
\csn\t\Csn::init();