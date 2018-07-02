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

    function export()
    {
        is_null(self::$route) && Exp::end('路由未定义或有误');
        // 访问日志
//        Runtime::action();
        if (is_string($point = $search['point'])) {
            list($controller, $actionName) = self::action($point);
            $rm = new \ReflectionMethod($controller, $actionName);
            return $rm->invokeArgs($controller, self::actionParams($rm->getParameters(), $search['args']));
        } else {
            return call_user_func_array($point, self::actionParams((new \ReflectionFunction($point))->getParameters(), $search['args']));
        }
    }

    // ----------------------------------------------------------------------
    //  解析控制器方法
    // ----------------------------------------------------------------------

    protected static function action($point)
    {
        preg_match_all('/^(((\w+\/)*)(\w+))@(\w+)$/', $point, $match);
        empty($match[0]) && Exp::end('路由指向异常');
        $module = substr($match[2][0], 0, -1);
        $action = $match[5][0];
        // 加载控制器
        $controller = Controller::controller($match[4][0], $module);
        method_exists($controller, $action) || Exp::end('控制器文件' . $match[1][0] . '.php找不到方法' . $action);
        return [$controller, $action];
    }

    // ----------------------------------------------------------------------
    //  解析路由指向方法参数
    // ----------------------------------------------------------------------

    protected static function actionParams($params, $args)
    {
        count($params) === count($args) || Exp::end('路由指向方法参数数量有误');
        foreach ($args as $k => $v) {
            $v === '{@}' && ($params[$k]->isDefaultValueAvailable() ? $args[$k] = $params[$k]->getDefaultValue() : Exp::end('路由指向方法参数' . $params[$k]->name . '无值'));
        }
        return $args;
    }

}