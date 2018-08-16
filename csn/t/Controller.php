<?php

namespace csn;

class Controller
{

    // ----------------------------------------------------------------------
    //  获取项目控制器对象
    // ----------------------------------------------------------------------

    protected static $controllers = [];

    final static function loader($controller, $module = '')
    {
        $name = ($module ? str_replace('/', DS, $module) . DS : '') . $controller;
        if (key_exists($name, self::$controllers)) return self::$controllers[$name];
        is_file($file = APP_CONTROLLER . $name . '.php') ? Csn::need($file) : Csn::end('控制器' . $name . '不存在');
        $class = '\app\c\\' . $name;
        self::$controllers[$name] = new $class();
        method_exists(self::$controllers[$name], 'init') && self::$controllers[$name]->init();
        return self::$controllers[$name];
    }

    // ----------------------------------------------------------------------
    //  获取项目控制器方法
    // ----------------------------------------------------------------------

    final static function action($action, $args = [])
    {
        return Response::instance()->action($action, $args);
    }

    // ----------------------------------------------------------------------
    //  获取祖先类名
    // ----------------------------------------------------------------------

    final static function ancestor($name)
    {
        $parent = get_parent_class($name);
        return $parent ? self::ancestor($parent) : $name;
    }

    // ----------------------------------------------------------------------
    //  显示信息并跳转
    // ----------------------------------------------------------------------

    final protected function redirect($url = '/', $info = false, $time = 1000)
    {
        $info && Csn::close($info, $url, $time);
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
    //  创建视图
    // ----------------------------------------------------------------------

    final function view($names, $func = null, $cacheTime = null)
    {
        return View::instance($names)->makeHtml($func, $cacheTime);
    }

    // ----------------------------------------------------------------------
    //  接口数据返回
    // ----------------------------------------------------------------------

    final static function html($data)
    {
        header('Content-Type:text/html');
        return $data;
    }

    final static function xml($data)
    {
        header('Content-Type:text/xml');
        return is_string($data) ? $data : xml_encode($data);
    }

    final static function script($str)
    {
        header('Content-Type:text/javascript');
        return $str;
    }

}