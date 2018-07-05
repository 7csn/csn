<?php

namespace csn;

final class Exp extends Instance
{

    protected static $instance;             // 当前对象
    protected static $meta;           // 显示编码
    protected static $pre;            // 开发样式
    protected static $table;          // 生产样式
    protected static $name = [];      // 错误名称数组
    protected static $status = ['y' => 0, 'n' => 1, 'a' => 2, 'e' => 3];
    // 常见错误类型
    protected static $type = [
        'Fatal Error' => [E_ERROR, E_CORE_ERROR, E_COMPILE_ERROR, E_USER_ERROR],
        'Parse Error' => [E_PARSE, 0],
        'Warning' => [E_WARNING, E_CORE_WARNING, E_COMPILE_WARNING, E_USER_WARNING],
        'Notice' => [E_NOTICE, E_USER_NOTICE],
    ];

    // 获取错误信息
    protected static function info($error)
    {
        return self::type($error[0]) . '：[ ' . $error[1] . ' ][ ' . str_replace(APP, '', $error[2]) . ' ][ ' . $error[3] . ' ]';
    }

    // 获取错误类型
    protected static function type($code)
    {
        if (!key_exists($code, self::$name)) {
            $name = 'Other';
            foreach (self::$type as $k => $v) {
                if (in_array($code, $v)) {
                    $name = $k;
                    break;
                }
            }
            reset(self::$type);
            self::$name[$code] = $name;
        }
        return self::$name[$code];
    }

    // 错误信息
    protected static function show($info)
    {
        echo '<pre class="pre">';
        print_r($info);
        echo '</pre>';
    }

    // 开发模式调试信息
    static function open($args, $func = 'print_r')
    {
        self::pre();
        foreach ($args as $arg) {
            echo '<pre class="pre">';
            call_user_func($func, $arg);
            echo '</pre>';
        }
        return self::instance();
    }

    // 生产模式显示信息
    static function close($info, $go = '')
    {
        self::table();
        echo "<table class='table'><tr/><tr><td><div class='div'>{$info}</div></td></tr><tr/><tr/></table>$go";
        return self::instance();
    }

    // 显示调试样式
    protected static function pre()
    {
        if (self::$pre) {
            echo '';
        } else {
            self::$pre = true;
            self::meta();
            echo '<style>.pre{padding:10px;margin:15px 10px;font-size:14px;line-height:1.5;color:#333;background:#f5f5f5;border:1px solid #ccc;border-radius:4px;overflow-x:auto}</style>';
        }
    }

    // 显示生产样式
    protected static function table()
    {
        if (self::$table) {
            echo '';
        } else {
            self::$table = true;
            self::meta();
            echo '<style>.table{position:fixed;top:0;left:0;width:100%;height:100%;background:#fff;text-align:center;z-index:99999999}.table .div{color:#777;font-size:75px;font-family:宋体;padding:15px;display:inline-block;border:1px solid #777;border-radius:10px}</style>';
        }
    }

    // 显示编码
    protected static function meta()
    {
        if (self::$meta) {
            echo '';
        } else {
            self::$meta = true;
            echo '<meta charset="' . Conf::web('charset') . '">';
        }
    }

    // 根据模式报错
    static function end()
    {
        T_S ? Exp::open(func_get_args(), 'print_r')->E() : Exp::close('页面不存在')->E();
    }

    // 结束程序
    function E()
    {
        die;
    }

}