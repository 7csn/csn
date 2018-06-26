<?php

namespace csn;

class Csn
{

    protected static $inc = [];              // 加载文件
    protected static $obj = [];              // 模型文件
    protected static $act = [];              // 控制器文件
    // 加载类库
    protected static $load = ['app\m\\' => [], 'app\\' => [], 'csn\y\\' => [], 'csn\\' => []];
    // 文件引入相关信息
    protected static $file = [CSN => 'csn', APP => 'app', WEB => 'web'];
    // 自动加载类相关信息
    protected static $class = ['app\m\\' => APP_M, 'app\\' => APP, 'csn\y\\' => CSN_Y, 'csn\\' => CSN_T];

    // 框架初始化
    static function init()
    {
        // 引入框架字母方法
        self::inc(CSN_X . 'latin.php');
        // 引入框架常用方法
        self::inc(CSN_X . 'func.php');
        // 是否调试模式
        defined('T_S') || define('T_S', Conf::web('debug'));
        // 设置编码
        header('Content-Type:text/html;charset=' . Conf::web('charset'));
        // 设置时区
        date_default_timezone_set(Conf::web('timezone'));
        // 路由分隔符
        define('SP', Conf::web('separator'));
        // 项目配置初始化
        defined('CT') && Conf::init();
        // 获取浏览器信息
        define('UA', isset($_SERVER['HTTP_USER_AGENT']) ? $_SERVER['HTTP_USER_AGENT'] : null);
    }

    // 启动框架
    static function run()
    {
        // 初始化密钥文件
        Safe::secretInit();
        // 解析请求并响应
        exit(Request::parse()->response());
    }

    // 获取框架模型、外调模型、项目模型
    static function obj($class, array $args = [], $type = '')
    {
        in_array($type, ['', 'y', 'm']) || Exp::end('框架模型类不合格');
        $class = ($type === 'm' ? 'app\\m\\' : 'csn\\' . ($type === '' ? '' : ($type . '\\'))) . $class;
        key_exists($class, self::$obj) && self::$obj[$class]['args'] === $args || self::$obj[$class] = ['class' => (new \ReflectionClass($class))->newInstanceArgs($args), 'args' => $args];
        return self::$obj[$class]['class'];
    }

    // 获取项目控制器对象
    static function act($c, $m = false)
    {
        $name = ($m ? XG . $m : '') . XG . $c;
        key_exists($c, self::$act) && self::$act[$c]['name'] === $name && Exp::end('不能跨模块引入同名控制器');
        self::inc(APP_C . $name . '.php');
        $class = '\app\c\\' . $c;
        self::$act[$c] = ['name' => $name, 'class' => new $class()];
        return self::$act[$c]['class'];
    }

    // 自动加载框架、项目类文件
    static function load($class)
    {
        $res = self::search($class, self::$class);
        if (is_null($res)) return;
        $type = $res[0];
        $name = str_replace('\\', XG, str_replace($type, '', $class));
        $file = $res[1] . $name . '.php';
        in_array($name, self::$load[$type]) && Exp::end('类' . $class . '异常');
        is_file($file) ? include $file : Exp::end('找不到类' . $class);
        self::$load[$type][] = $name;
    }

    // 引入文件
    static function inc($file, $force = false)
    {
        return $force || !key_exists($file, self::$inc) ? self::$inc[$file] = is_file($file) ? include $file : null : self::$inc[$file];
    }

    // 检索文件名或类名
    protected static function search($str, $where)
    {
        $res = null;
        foreach ($where as $k => $v) {
            if (strpos($str, $k) === 0) {
                $res = [$k, $v];
                break;
            }
        }
        reset($where);
        return $res;
    }

    // 显示信息并跳转
    static function go($url, $info = false, $time = 1000)
    {
        $url = Request::makeUrl($url);
        if ($info) {
            Exp::close($info, self::href($url, $time))->E();
        } else {
            usleep($time);
            header('Location:' . $url);
        }
    }

    // 页面跳转
    protected static function href($url, $time = 1000)
    {
        return '<script>setTimeout(function () {location = "' . $url . '";}, ' . $time . ');</script>';
    }

    // 接口返回值
    static function back($back, $type = 'json')
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