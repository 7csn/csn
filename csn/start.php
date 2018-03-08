<?php

// 初始内存
define('S_MEM', memory_get_usage());

// 当前时间戳
define('TIME', $_SERVER['REQUEST_TIME']);

// 初始时间
define('START', $_SERVER['REQUEST_TIME_FLOAT']);

// 目录分隔符
define('XG', DIRECTORY_SEPARATOR);

// 框架相关目录
define('Csn', __DIR__ . XG);
define('Csn_t', Csn . 't' . XG);
define('Csn_s', Csn . 's' . XG);
define('Csn_y', Csn . 'y' . XG);
define('Csn_x', Csn . 'x' . XG);

// 项目相关目录
define('Pub', realpath('.') . XG);
define('App', dirname(Pub) . XG);
define('App_c', App . 'controllers' . XG);
define('App_m', App . 'models' . XG);
define('App_v', App . 'views' . XG);
define('App_r', App . 'runtime' . XG);
define('App_t', App . 'template' . XG);

// 基于框架项目根目录
define('Web', dirname(App) . XG);

// 引入框架核心类文件
include Csn_t . 'csn.php';

// 自加载composer类库
\csn\t\Csn::inc(Web . 'vendor' . XG . 'autoload.php');

// 自加载框架、项目类
spl_autoload_register('\csn\t\Csn::load');

// 自定义错误、异常
set_error_handler('\csn\t\Exp::error');
set_exception_handler('\csn\t\Exp::exception');

// 框架初始化
\csn\t\Csn::init();