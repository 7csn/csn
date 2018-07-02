<?php

namespace csn;

final class Csn
{

    // ----------------------------------------------------------------------
    //  框架初始化
    // ----------------------------------------------------------------------

    static function init()
    {
        // 引入框架字母方法
        self::need(CSN_X . 'latin.php');
        // 引入框架常用方法
        self::need(CSN_X . 'func.php');
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

    static function run()
    {
        // 初始化密钥文件
        Safe::secretInit();
        // 解析请求并响应
        Request::instance()->response()->export();
    }

    // ----------------------------------------------------------------------
    //  启动指令集
    // ----------------------------------------------------------------------

    static function cmd()
    {
        print_r($_SERVER['argc']);
        echo "\n";
        print_r($_SERVER['argv']);
    }

    // ----------------------------------------------------------------------
    //  类(模型：框架外调、框架核心、项目)文件自加载
    // ----------------------------------------------------------------------

    // 对照表：类名前缀=>路径前缀
    protected static $load = ['csn\y\\' => CSN_Y, 'csn\\' => CSN_T, 'app\m\\' => APP_MODEL];

    // 类自加载
    static function load($class)
    {
        if (is_null($search = self::search($class))) return;
        $file = self::$load[$search[0]] . str_replace('\\', DS, $search[1]) . '.php';
        is_file($file) ? self::need($file) : Exp::end('找不到类' . $class);
    }

    // 检索类名前缀与路径前缀
    static function search($class)
    {
        $res = null;
        foreach (self::$load as $k => $v) {
            if (strpos($class, $k) === 0) {
                $res = [$k, str_replace($k, '', $class)];
                break;
            }
        }
        reset(self::$load);
        return $res;
    }

    // ----------------------------------------------------------------------
    //  引入文件
    // ----------------------------------------------------------------------

    // 文件库
    protected static $needs = [];

    // 引入文件：文件路径
    static function need($file)
    {
        return key_exists($file, self::$needs) ? self::$needs[$file] : self::$needs[$file] = is_file($file) ? include $file : null;
    }

}