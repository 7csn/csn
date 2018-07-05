<?php

namespace csn;

class Controller
{

    // ----------------------------------------------------------------------
    //  获取模型(框架核心、框架外调、项目)对象
    // ----------------------------------------------------------------------

    final static function core($name, $args = [])
    {
        return Instance::create('csn\\', $name, $args);
    }

    final static function other($name, $args = [])
    {
        return Instance::create('csn\y\\', $name, $args);
    }

    final protected function model($name, $args = [])
    {
        return Instance::create('app\m\\', $name, $args);
    }

    // ----------------------------------------------------------------------
    //  获取项目控制器对象
    // ----------------------------------------------------------------------

    protected static $controllers = [];

    final static function controller($controller, $module = '')
    {
        $name = ($module ? str_replace('/', DS, $module) . DS : '') . $controller;
        if (key_exists($controller, self::$controllers)) {
            self::$controllers[$controller]['name'] === $name || Exp::end('禁止跨模块引入同名控制器');
            return self::$controllers[$controller]['obj'];
        }
        is_file($file = APP_CONTROLLER . $name . '.php') ? Csn::need($file) : Exp::end('控制器' . $name . '不存在');
        $class = '\app\c\\' . $controller;
        self::$controllers[$controller] = ['name' => $name, 'obj' => new $class()];
        return self::$controllers[$controller]['obj'];
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
    //  创建视图
    // ----------------------------------------------------------------------

    final protected function view($names, $stdClass, $cacheTime = null)
    {
        return View::instance($names)->makeHtml($stdClass, $cacheTime);
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