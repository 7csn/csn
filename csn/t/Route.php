<?php

namespace csn;

class Route
{

    static $define;     // 当前定义路由
    static $path;       // 当前访问路由
    static $tap = [];   // 路由控制数组

    // 设置GET路由
    static function get($path, $point)
    {
        return self::set('GET', $path, $point);
    }

    // 设置POST路由
    static function post($path, $point)
    {
        return self::set('POST', $path, $point);
    }

    // 设置多方法路由
    static function match($methods, $path, $point)
    {
        return self::set($methods, $path, $point);
    }

    // 设置全方法路由
    static function any($path, $point)
    {
        return self::set('ANY', $path, $point);
    }

    // 设置过渡
    protected static function set($method, $path, $point)
    {
        $path = self::path($path);
        $method = is_array($method) ? $method : [$method];
        self::save($method, $path, $point);
        return self::tap($method, $path);
    }

    // 规范路由定位
    static function path($path, $self = false)
    {
        $path = '@' . str_replace(SP, '@', trim(ltrim(preg_replace('/\.html$/', '', $path), '/'), SP));
        $self && self::$path = $path;
        return $path === '@' ? '' : $path;
    }

    // 保存设置
    protected static function save($method, $path, $point)
    {
        if (is_array($method)) {
            foreach ($method as $m) {
                self::save($m, $path, $point);
            }
        } else {
            key_exists($method, self::$tap) || self::$tap[$method] = [];
            self::$tap[$method][$path] = ['point' => $point];
        }
    }

    // 路由控制对象
    protected static function tap()
    {
        return Csn::obj('Tap', func_get_args());
    }

    // 执行控制器方法
    static function run($path)
    {
        $search = self::find($path);
        is_null($search) && Exp::end('路由未定义或有误');
        // 检测密钥
        Safe::secret();
        // 访问日志
        Runtime::act();
        $point = $search['point'];
        if (is_string($point)) {
            list($controller, $actionName) = self::action($point);
            $rm = new \ReflectionMethod($controller, $actionName);
            return $rm->invokeArgs($controller, self::actParams($rm->getParameters(), $search['args']));
        } else {
            return call_user_func_array($point, self::actParams((new \ReflectionFunction($point))->getParameters(), $search['args']));
        }
    }

    // 获取路由
    static function find($path)
    {
        return self::search($path, Request::method()) ?: self::search($path);
    }

    // 按方法搜索路由
    static function search($path, $method = 'ANY')
    {
        $search = null;
        if (key_exists($method, self::$tap)) {
            foreach (self::$tap[$method] as $key => $route) {
                $match = key_exists('where', $route) ? preg_match($preg = '/^' . (key_exists('parse', $route) ? $route['preg'] : self::parse($method, $key)) . '$/', $path) : false;
                if ($match || $path === $key) {
                    $args = [];
                    if ($match) {
                        preg_match_all($preg, $path, $m);
                        for ($i = 1, $c = count($m); $i < $c; $i++) {
                            $args[] = $m[$i][0] ? ltrim($m[$i][0], '@') : '{@}';
                        }
                    }
                    if (!key_exists('input', $route) || $method !== 'POST' || self::input($route['input'])) {
                        $search = ['point' => $route['point'], 'args' => $args];
                        is_null(self::$define) && self::$define = str_replace('/', '@', str_replace(SP, '@', str_replace('?', '#', $key)));
                        break;
                    }
                }
            }
            reset(self::$tap[$method]);
        }
        return $search;
    }

    // 解析路由正则
    protected static function parse($method, $path)
    {
        $p = str_replace('/', '\\/', $path);
        foreach (self::$tap[$method][$path]['where'] as $name => $preg) {
            $p = str_replace(['{' . $name . '}', '@{' . $name . '?}'], ['(' . $preg . ')', '(@' . $preg . ')?'], $p);
        }
        self::$tap[$method][$path]['parse'] = true;
        return self::$tap[$method][$path]['preg'] = $p;
    }

    // POST数据验证
    static function input($input)
    {
        $res = true;
        foreach ($input as $name => $preg) {
            if (!key_exists($name, $_POST) || !preg_match('/^' . $preg . '$/', $_POST[$name])) {
                $res = false;
                break;
            }
        }
        return $res;
    }

    // 解析控制器方法
    protected static function action($point)
    {
        preg_match_all('/^(\w+\/)?(\w+)@(\w+)$/', $point, $match);
        empty($match[0]) && Exp::end('路由指向异常');
        Request::$m = substr($match[1][0], 0, -1);
        Request::$c = $match[2][0];
        Request::$a = $match[3][0];
        // 加载控制器
        $c = Csn::act(Request::$c, Request::$m);
        method_exists($c, Request::$a) || Exp::end('控制器' . Request::$m . Request::$c . '找不到方法' . Request::$a);
        return [$c, Request::$a];
    }

    // 解析路由指向方法参数
    protected static function actParams($params, $args)
    {
        count($params) === count($args) || Exp::end('路由指向方法参数数量有误');
        foreach ($args as $k => $v) {
            $v === '{@}' && ($params[$k]->isDefaultValueAvailable() ? $args[$k] = $params[$k]->getDefaultValue() : Exp::end('路由指向方法参数' . $params[$k]->name . '无值'));
        }
        return $args;
    }

}