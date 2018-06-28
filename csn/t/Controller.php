<?php

namespace csn;

class Controller
{

    // ----------------------------------------------------------------------
    //  获取模型(框架核心、框架外调、项目)
    // ----------------------------------------------------------------------

    protected static $model = [];

    final static function model($class, $args = [], $type = '')
    {
        in_array($type, ['', 'y', 'm']) || Exp::end('框架模型类不合格');
        $name = ($type ? $type === 'm' ? 'app\\m' : 'csn\\y' : 'csn') . '\\' . $class;
        key_exists($name, self::$model) && self::$model[$name]['args'] === $args || self::$model[$name] = ['class' => (new \ReflectionClass($name))->newInstanceArgs($args), 'args' => $args];
        return self::$model[$name]['class'];
    }

    // ----------------------------------------------------------------------
    //  获取项目控制器对象
    // ----------------------------------------------------------------------

    protected static $action = [];

    final static function action($controller, $module = false)
    {
        $name = ($module ? XG . $module : '') . XG . $controller;
        key_exists($controller, self::$action) && self::$action[$controller]['name'] === $name && Exp::end('不能跨模块引入同名控制器');
        self::inc(APP_C . $name . '.php');
        $class = '\app\c\\' . $controller;
        self::$action[$controller] = ['name' => $name, 'class' => new $class()];
        return self::$action[$controller]['class'];
    }

    // ----------------------------------------------------------------------
    //  显示信息并跳转
    // ----------------------------------------------------------------------

    final static function go($url, $info = false, $time = 1000)
    {
        $url = Request::makeUrl($url);
        $info && Exp::close($info, self::href($url, $time))->E();
        usleep($time * 1000);
        header('Location:' . $url);
    }

    // ----------------------------------------------------------------------
    //  页面跳转
    // ----------------------------------------------------------------------

    final protected static function href($url, $time = 1000)
    {
        return "<script>setTimeout(function() { window.location.href = '$url'; }, $time);</script>";
    }

    // ----------------------------------------------------------------------
    //  接口返回值
    // ----------------------------------------------------------------------

    final static function back($back, $type = 'json')
    {
        switch ($type) {
            case 'xml':
                header('Content-Type:text/xml');
                return is_string($back) ? $back : xml_encode($back);
            case 'jsonp':
                header('Content-Type:application/json');
                return '(' . (is_string($back) ? $back : json_encode($back)) . ');';
            case 'html':
                header('Content-Type:text/html');
                return $back;
            default :
                header('Content-Type:application/json');
                return is_string($back) ? $back : json_encode($back);
        }
    }

}