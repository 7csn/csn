<?php

namespace csn;

final class Csn
{

    // ----------------------------------------------------------------------
    //  框架初始化
    // ----------------------------------------------------------------------

    static function init()
    {
        // 引入框架助手函数
        self::need(CSN_X . 'helper.php');
        // 是否调试模式
        defined('T_S') || define('T_S', Config::web('debug'));
        // 设置编码
        header('Content-Type:text/html;charset=' . Config::web('charset'));
        // 设置时区
        date_default_timezone_set(Config::web('timezone'));
        // 路由分隔符
        define('SP', Config::web('separator'));
        // 项目配置初始化
        defined('CT') && Config::init();
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
        die('The function is temporarily not on-line.');
        print_r($_SERVER['argc']);
        echo "\n";
        print_r($_SERVER['argv']);
    }

    // ----------------------------------------------------------------------
    //  类(模型：框架外调、框架核心、项目)文件自加载
    // ----------------------------------------------------------------------

    // 对照表：类名前缀=>路径前缀
    private static $load = ['csn\y\\' => CSN_Y, 'csn\\' => CSN_T, 'app\m\\' => APP_MODEL];

    // 类自加载
    static function load($class)
    {
        if (is_null($search = self::search($class))) return;
        $file = self::$load[$search[0]] . str_replace('\\', DS, $search[1]) . '.php';
        is_file($file) ? self::need($file) : Csn::end('找不到类' . $class);
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
        return $res;
    }

    // ----------------------------------------------------------------------
    //  引入文件
    // ----------------------------------------------------------------------

    // 文件库
    private static $needs = [];

    // 引入文件：文件路径
    static function need($file)
    {
        return key_exists($file, self::$needs) ? self::$needs[$file] : self::$needs[$file] = is_file($file) ? include $file : null;
    }

    // ----------------------------------------------------------------------
    //  自定义错误、异常
    // ----------------------------------------------------------------------

    // 类型数组
    private static $errorTypes = [
        'Fatal Error' => [E_ERROR, E_CORE_ERROR, E_COMPILE_ERROR, E_USER_ERROR],
        'Parse Error' => [E_PARSE],
        'Warning' => [E_WARNING, E_CORE_WARNING, E_COMPILE_WARNING, E_USER_WARNING],
        'Notice' => [E_NOTICE, E_USER_NOTICE],
    ];

    // 类型名称对照表
    private static $errorName = [];

    // 错误处理
    static function error($code, $msg, $file, $line)
    {
        DbBase::getTrans() || self::closure($code, $msg, $file, $line);
    }

    // 致命错误处理
    static function fatal()
    {
        if ($e = error_get_last()) self::error($e['type'], $e['message'], $e['file'], $e['line']);
    }

    // 异常处理
    static function exception($e)
    {
        self::error($e->getCode(), $e->getMessage(), $e->getFile(), $e->getLine());
    }

    // 结束程序
    private static function closure($code, $msg, $file, $line)
    {
        $info = self::info($code, $msg, $file, $line);
        Runtime::error($info);
        self::show($info);
    }

    // 获取错误信息
    private static function info($code, $msg, $file, $line)
    {
        return self::type($code) . '：[ ' . $msg . ' ][ ' . str_replace(WEB, '', $file) . ' ][ ' . $line . ' ]';
    }

    // 获取错误类型
    private static function type($code)
    {
        if (!key_exists($code, self::$errorName)) {
            $name = 'Other';
            foreach (self::$errorTypes as $k => $v) {
                if (in_array($code, $v)) {
                    $name = $k;
                    break;
                }
            }
            self::$errorName[$code] = $name;
        }
        return self::$errorName[$code];
    }

    // ----------------------------------------------------------------------
    //  输出
    // ----------------------------------------------------------------------

    // 调试样式初始化
    private static $pre;

    // 显示调试样式
    private static function pre()
    {
        if (self::$pre) {
            echo '';
        } else {
            self::$pre = true;
            self::charset();
            echo '<style>.pre{padding:10px;margin:15px 10px;font-size:14px;line-height:1.5;color:#333;background:#f5f5f5;border:1px solid #ccc;border-radius:4px;overflow-x:auto}</style>';
        }
    }

    // 生产样式
    private static $table;

    // 显示生产样式
    private static function table()
    {
        if (self::$table) {
            echo '';
        } else {
            self::$table = true;
            self::charset();
            echo '<style>.table{position:fixed;top:0;left:0;width:100%;height:100%;background:#fff;text-align:center;z-index:99999999}.table .div{color:#777;font-size:75px;font-family:宋体;padding:15px;display:inline-block;border:1px solid #777;border-radius:10px}</style>';
        }
    }

    // 显示编码初始化
    private static $charset;

    // 显示编码
    private static function charset()
    {
        if (self::$charset) {
            echo '';
        } else {
            self::$charset = true;
            echo '<meta charset="' . Config::web('charset') . '">';
        }
    }

    // 调试信息(不含类型)
    static function show()
    {
        self::pre();
        foreach (func_get_args() as $info) {
            echo '<pre class="pre">';
            print_r($info);
            echo '</pre>';
        }
        die;
    }

    // 调试信息(含类型)
    static function dump()
    {
        self::pre();
        foreach (func_get_args() as $info) {
            echo '<pre class="pre">';
            var_dump($info);
            echo '</pre>';
        }
        die;
    }

    // 生产模式显示信息
    static function close($info, $url = '', $time = 0)
    {
        self::table();
        $info = "<table class='table'><tr/><tr><td><div class='div'>{$info}</div></td></tr><tr/><tr/></table>";
        echo $url ? $info : "<meta http-equiv='refresh' content = '$time;url=\"{$url}\"'>{$info}<script>setTimeout(function() { window.location.href = '$url'; }, $time);</script>";
        die;
    }

    // 根据模式报错
    static function end($msg)
    {
        Request::instance()->isAjax() ? Api::instance('n', '非法调用')->run() : (T_S ? Csn::show($msg) : Csn::close('页面不存在'));
    }

}