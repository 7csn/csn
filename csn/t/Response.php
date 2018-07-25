<?php

namespace csn;

final class Response extends Instance
{

    // ----------------------------------------------------------------------
    //  构造方法
    // ----------------------------------------------------------------------

    function construct()
    {
        Route::instance();
        return self::single();
    }

    // ----------------------------------------------------------------------
    //  当前路由：设置、获取
    // ----------------------------------------------------------------------

    private static $route;

    public static function route($route = false)
    {
        return $route === false ? self::$route : self::$route = $route;
    }

    // ----------------------------------------------------------------------
    //  输出
    // ----------------------------------------------------------------------

    private $export;

    function export()
    {
        is_null($route = self::$route) && Csn::end('路由未定义或有误');
        if (is_null($this->export)) {
            $this->export = true;
            // 访问日志
            Runtime::action();
            // 静态化路径
            $html = $this->html($route['path'], $route['args']);
            if ($this->htmlOK($html, $route['cache'])) {
                $run = file_get_contents($html);
            } else {
                $run = Course::instance(function ($obj) use ($route) {
                    return is_callable($point = $route['point']) ? call_user_func_array($point, self::actionParams((new \ReflectionFunction($point))->getParameters(), $route['args'])) : $obj->action($point, $route['args']);
                })->args($this)->run();
                $route['cache'] > 0 && File::write($html, $run, true);
            }
            is_null($run) || exit(is_string($run) ? $run : json_encode($run));
        }
    }

    // ----------------------------------------------------------------------
    //  获取路由静态化文件
    // ----------------------------------------------------------------------

    function html($path, $args)
    {
        return WEB_ROUTE . Safe::en(serialize($path)) . DS . Safe::en(serialize($args)) . '.html';
    }

    // ----------------------------------------------------------------------
    //  验证路由静态化文件有性
    // ----------------------------------------------------------------------

    function htmlOK($html, $time)
    {
        return $time > 0 && (is_file($html) && filemtime($html) + $time > CSN_START);
    }

    // ----------------------------------------------------------------------
    //  解析控制器方法
    // ----------------------------------------------------------------------

    function action($point, $args)
    {
        preg_match_all('/^(((\w+\/)*)(\w+))@(\w+)$/', $point, $match);
        empty($match[0]) && Csn::end("路由 $point 指向异常");
        $module = substr($match[2][0], 0, -1);
        $action = $match[5][0];
        // 加载控制器
        $controller = Controller::controller($match[4][0], $module);
        method_exists($controller, $action) || Csn::end('控制器文件' . $match[1][0] . '.php找不到方法' . $action);
        $rm = new \ReflectionMethod($controller, $action);
        return $rm->invokeArgs($controller, $this->actionParams($rm->getParameters(), $args));
    }

    // ----------------------------------------------------------------------
    //  解析路由指向方法参数
    // ----------------------------------------------------------------------

    private function actionParams($params, $args)
    {
        $paramsCount = count($params);
        $num = $paramsCount - count($args);
        if ($num !== 0 && $num !== 1) Csn::end('路由指向方法参数数量有误');
        if ($num === 1) {
            is_null($class = $params[0]->getClass()) && Csn::end('路由指向方法首参需为对象');
            $class->name === 'csn\Request' || Csn::end('路由指向方法首参需为Request对象');
            array_unshift($args, Request::instance());
        }
        for ($i = $num; $i < $paramsCount; $i++) {
            $args[$i] === '{@}' && ($params[$i]->isDefaultValueAvailable() ? $args[$i] = $params[$i]->getDefaultValue() : Csn::end('路由指向方法参数' . $params[$i]->name . '无值'));
        }
        return $args;
    }

}