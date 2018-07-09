<?php

namespace app\c;

// 获取配置信息
function C($n)
{
    return \csn\Config::get($n);
}

// 生产报错
function D($m)
{
    \csn\Csn::close($m)->E();
}

// 根据模式报错
function E()
{
    call_user_func_array('\csn\Csn::end', func_get_args());
}

// 循环
function L($o, $fn)
{
    foreach ($o as $k => $v) {
        if ($fn(array('o' => $o, 'k' => $k, 'v' => $v))) break;
    }
}

// 网络连接
function H()
{
    return \csn\Controller::core('Http', func_get_args());
}

// 数据库连接
function M($db = null, $k = 0)
{
    if (is_int($db)) {
        $k = $db;
        $db = null;
    }
    return \csn\Db::connect($k)->db($db);
}

// 调试数据
function P()
{
    return call_user_func_array('\csn\Csn::show', func_get_args());
}

// 性能测试
function R($f, $n = 100, $l = false)
{
    $t = microtime(true);
    for ($i = 0; $i < $n; $i++) {
        $f();
    }
    $time = microtime(true) - $t;
    if ($l) {
        $time -= X($n);
    }
    return $time;
}

// 指定数据库表
function T()
{
    $args = func_get_args();
    $t = array_shift($args);
    return call_user_func_array('\app\c\M', $args)->table($t);
}

// 获取路由
function U($path = null)
{
    return \csn\Request::makeUrl($path);
}

// 调试数据
function V()
{
    return call_user_func_array('\csn\Csn::dump', func_get_args());
}

// 循环用时
function X($n = 100)
{
    $t = microtime(true);
    for ($i = 0; $i < $n; $i++) {
    }
    return microtime(true) - $t;
}