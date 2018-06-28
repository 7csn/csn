<?php

namespace csn;

class Csn
{

    // ----------------------------------------------------------------------
    //  框架初始化
    // ----------------------------------------------------------------------

    final static function init()
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

    // ----------------------------------------------------------------------
    //  启动框架
    // ----------------------------------------------------------------------

    final static function run()
    {
        // 初始化密钥文件
        Safe::secretInit();
        // 解析请求并响应
        exit(Request::parse()->response());
    }


    // ----------------------------------------------------------------------
    //  类文件自加载(框架、项目)
    // ----------------------------------------------------------------------

    // 前缀对照表：类名=>路径
    protected static $class = ['csn\y\\' => CSN_Y, 'csn\\' => CSN_T, 'app\m\\' => APP_M, 'app\\' => APP];

    // 加载类库
    protected static $load = ['csn\y\\' => [], 'csn\\' => [], 'app\m\\' => [], 'app\\' => []];

    // 类自加载
    final static function load($class)
    {
        $res = self::search($class);
        if (is_null($res)) return;
        $type = $res[0];
        $name = str_replace('\\', XG, str_replace($type, '', $class));
        $file = $res[1] . $name . '.php';
        in_array($name, self::$load[$type]) && Exp::end('类' . $class . '异常');
        is_file($file) ? include $file : Exp::end('找不到类' . $class);
        self::$load[$type][] = $name;
    }

    // 检索类名前缀与路径前缀
    final protected static function search($str)
    {
        $res = null;
        foreach (self::$class as $k => $v) {
            if (strpos($str, $k) === 0) {
                $res = [$k, $v];
                break;
            }
        }
        reset(self::$class);
        return $res;
    }

    // ----------------------------------------------------------------------
    //  引入文件
    // ----------------------------------------------------------------------

    // 加载文件
    protected static $inc = [];

    // 路径前缀与类型对照表
    protected static $file = [CSN => 'csn', APP => 'app', WEB => 'web'];

    final static function inc($file, $force = false)
    {
        return $force || !key_exists($file, self::$inc) ? self::$inc[$file] = is_file($file) ? include $file : null : self::$inc[$file];
    }

}