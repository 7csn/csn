<?php

namespace csn;

class Controller
{

    // ----------------------------------------------------------------------
    //  获取模型(框架核心、框架外调、项目)
    // ----------------------------------------------------------------------

    protected static $models = [];

    final static function model($name, $args = [], $type = '')
    {
        in_array($type, ['', 'y', 'm']) || Exp::end('框架模型类不合格');
        $class = ($type ? $type === 'm' ? 'app\\m' : 'csn\\y' : 'csn') . '\\' . $name;
        key_exists($class, self::$models) && self::$models[$class]['args'] === $args || self::$models[$class] = ['obj' => (new \ReflectionClass($class))->newInstanceArgs($args), 'args' => $args];
        return self::$models[$class]['obj'];
    }

    // ----------------------------------------------------------------------
    //  获取项目控制器对象
    // ----------------------------------------------------------------------

    protected static $actions = [];

    final static function action($controller, $module = false)
    {
        $name = ($module ? DS . str_replace('/', DS, $module) : '') . DS . $controller;
        key_exists($controller, self::$actions) && self::$actions[$controller]['name'] === $name && Exp::end('禁止跨模块引入同名控制器');
        Csn::need(APP_CONTROLLER . $name . '.php');
        $class = '\app\c\\' . $controller;
        self::$actions[$controller] = ['name' => $name, 'obj' => new $class()];
        return self::$actions[$controller]['obj'];
    }

    // ----------------------------------------------------------------------
    //  显示信息并跳转
    // ----------------------------------------------------------------------

    final static function redirect($url, $info = false, $time = 1000)
    {
        $info && Exp::close($info, "<script>setTimeout(function() { window.location.href = '$url'; }, $time);</script>")->E();
        usleep($time * 1000);
        header('Location:' . $url);
        die;
    }

    // ----------------------------------------------------------------------
    //  引入文件
    // ----------------------------------------------------------------------

    final static function need()
    {
        return call_user_func_array('\csn\Csn::need', func_get_args());
    }

    // ----------------------------------------------------------------------
    //  接口数据返回
    // ----------------------------------------------------------------------

    final static function text($data)
    {
        header('Content-Type:text/pain');
        return $data;
    }

    final static function html($data)
    {
        header('Content-Type:text/pain');
        return $data;
    }

    final static function json($data)
    {
        header('Content-Type:application/json');
        return $data;
    }

    final static function xml($data)
    {
        header('Content-Type:text/pain');
        return $data;
    }

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