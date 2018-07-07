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
//            Runtime::action();
            $run = is_string($point = $route['point']) ? $this->action($point, $route['args']) : call_user_func_array($point, self::actionParams((new \ReflectionFunction($point))->getParameters(), $route['args']));
            is_null($run) || exit(is_string($run) ? $run : json_encode($run));
        }
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