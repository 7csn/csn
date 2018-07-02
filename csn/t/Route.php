<?php

namespace csn;

final class Route extends Instance
{

    // ----------------------------------------------------------------------
    //  构造方法
    // ----------------------------------------------------------------------

    function construct()
    {
        // 加载路由文件
        self::loadFile();
        // 响应路由
        Response::route(self::find(Request::instance()->path()));
        // 单例
        return self::single();
    }

    // ----------------------------------------------------------------------
    //  加载路由文件
    // ----------------------------------------------------------------------

    static function loadFile()
    {
        File::copy(CSN_X . 'route.php', APP . 'route.php');
        Csn::need(APP . 'route.php');
    }

    // ----------------------------------------------------------------------
    //  规范路由定位
    // ----------------------------------------------------------------------

    static function path($path)
    {
        $path = '@' . str_replace(SP, '@', trim(ltrim($path, '/'), SP));
        return $path === '@' ? '' : $path;
    }

    // ----------------------------------------------------------------------
    //  定义路由：GET、POST、多方法、全方法
    // ----------------------------------------------------------------------

    static function get($path, $point)
    {
        return self::tap('GET', $path, $point);
    }

    static function post($path, $point)
    {
        return self::tap('POST', $path, $point);
    }

    static function match($methods, $path, $point)
    {
        return self::tap($methods, $path, $point);
    }

    static function any($path, $point)
    {
        return self::tap('ANY', $path, $point);
    }

    // ----------------------------------------------------------------------
    //  路由设置
    // ----------------------------------------------------------------------

    static $taps = [];

    private static function tap($method, $path, $point)
    {
        $path = self::path($path);
        $method = is_array($method) ? $method : [$method];
        foreach ($method as $k => $m) {
            $method[$k] = $m = strtoupper($m);
            key_exists($m, self::$taps) || self::$taps[$m] = [];
            self::$taps[$m][$path] = ['point' => $point];
        }
        return Tap::instance($method, $path);
    }

    // ----------------------------------------------------------------------
    //  获取路由
    // ----------------------------------------------------------------------

    static function find($path)
    {
        return self::search($path, Request::instance()->method()) ?: self::search($path);
    }

    // ----------------------------------------------------------------------
    //  按方法搜索路由
    // ----------------------------------------------------------------------

    static function search($path, $method = 'ANY')
    {
        $search = null;
        // 检索方法
        if (key_exists($method, self::$taps)) {
            // 遍历所属方法路由
            foreach (self::$taps[$method] as $key => $route) {
                // 路由正则匹配结果(无路由正则false)
                $match = key_exists('where', $route) ? preg_match($preg = '/^' . (key_exists('parse', $route) ? $route['preg'] : self::parse($method, $key)) . '$/', $path) : false;
                // 检索路由和路由正则：路由正则匹配或路由相同
                if ($match || $path === $key) {
                    $args = [];
                    // 补充路由参数
                    if ($match) {
                        preg_match_all($preg, $path, $matches);
                        for ($i = 1, $c = count($matches); $i < $c; $i++) {
                            // 剔除可选参数前@符号;无参则标记{@}
                            $args[] = $matches[$i][0] ? ltrim($matches[$i][0], '@') : '{@}';
                        }
                    }
                    // POST参数验证过滤
                    if (!$method === 'POST' || !key_exists('input', $route) || self::input($route['input'])) {
                        $search = ['point' => $route['point'], 'args' => $args, 'path' => $key];
                        is_null(self::$define) && self::$define = str_replace('/', '@', str_replace(SP, '@', str_replace('?', '#', $key)));
                        break;
                    }
                }
            }
            reset(self::$taps[$method]);
        }
        return $search;
    }

    // ----------------------------------------------------------------------
    //  解析路由正则
    // ----------------------------------------------------------------------

    protected static function parse($method, $path)
    {
        // 当分隔符不为斜杠时转义
        $p = str_replace('/', '\\/', $path);
        foreach (self::$taps[$method][$path]['where'] as $name => $preg) {
            // 替换路由参数：不可选及可选
            $p = str_replace(['{' . $name . '}', '@{' . $name . '?}'], ['(' . $preg . ')', '(@' . $preg . ')?'], $p);
        }
        // 记录解析状态
        self::$taps[$method][$path]['parse'] = true;
        // 更新正则并返回
        return self::$taps[$method][$path]['preg'] = $p;
    }

    // ----------------------------------------------------------------------
    //  POST数据验证
    // ----------------------------------------------------------------------

    static function input($input)
    {
        $res = true;
        foreach ($input as $name => $preg) {
            if (key_exists($name, $_POST) && preg_match('/^' . $preg . '$/', $_POST[$name])) continue;
            $res = false;
            break;
        }
        return $res;
    }

}