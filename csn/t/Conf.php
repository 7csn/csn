<?php

namespace csn;

final class Conf extends Instance
{

    // ----------------------------------------------------------------------
    //  初始化项目配置文件
    // ----------------------------------------------------------------------

    private static $init;

    static function init($force = false)
    {
        if (is_null(self::$init) || $force) {
            foreach (self::files() as $file) {
                File::copy(CSN_S . $file . '.php', APP_CONFIG . $file . '.php', $force);
            }
            self::$init = true;
        }
        File::copies([CSN . '.htaccess' => PUB . '.htaccess', CSN . 'favicon.ico' => PUB . 'favicon.ico'], $force);
    }

    // ----------------------------------------------------------------------
    //  默认配置项
    // ----------------------------------------------------------------------

    private static $files;

    private static function files()
    {
        if (is_null(self::$files)) {
            $dir = dir(CSN_S);
            while (false !== ($path = $dir->read())) {
                if ($path === '.' || $path === '..') continue;
                self::$module[] = self::$files[] = substr($path, 0, -4);
            }
            $dir->close();
        }
        return self::$files;
    }

    // ----------------------------------------------------------------------
    //  注册配置项
    // ----------------------------------------------------------------------

    private static $module = [];

    static function register($name)
    {
        self::files();
        in_array($name, self::$module) || self::$module[] = $name;
    }

    // ----------------------------------------------------------------------
    //  配置信息
    // ----------------------------------------------------------------------

    private static $conf = [];

    // ----------------------------------------------------------------------
    //  项目配置
    // ----------------------------------------------------------------------

    private static $app = [];

    // ----------------------------------------------------------------------
    //  框架默认配置
    // ----------------------------------------------------------------------

    private static $csn = [];

    // ----------------------------------------------------------------------
    //  获取配置信息
    // ----------------------------------------------------------------------

    static function get($names)
    {
        $i = strpos($names, '.');
        $conf = self::load($i ? substr($names, 0, $i) : $names);
        return $i ? self::gets(explode('.', substr($names, $i + 1)), $conf, $names) : $conf;
    }

    // ----------------------------------------------------------------------
    //  获取配置项
    // ----------------------------------------------------------------------

    private static function load($name)
    {
        return key_exists($name, self::$conf) ? self::$conf[$name] : self::$conf[$name] = $name === 'data' ? self::app($name) : self::makeup(self::app($name), self::csn($name));
    }

    // ----------------------------------------------------------------------
    //  合并配置项
    // ----------------------------------------------------------------------

    private static function makeup($from, $to)
    {
        foreach ($from as $k => $v) {
            $v === $to[$k] || $to[$k] = is_array($v) ? self::makeup($v, $to[$k]) : $v;
        }
        return $to;
    }

    // ----------------------------------------------------------------------
    //  获取项目配置项数组
    // ----------------------------------------------------------------------

    private static function app($name)
    {
        return key_exists($name, self::$app) ? self::$app[$name] : self::$app[$name] = Csn::need(APP_CONFIG . $name . '.php') ?: [];
    }

    // ----------------------------------------------------------------------
    //  获取框架默认配置项数组
    // ----------------------------------------------------------------------

    private static function csn($name)
    {
        return key_exists($name, self::$csn) ? self::$csn[$name] : (self::$csn[$name] = Csn::need(CSN_S . $name . '.php'));
    }

    // ----------------------------------------------------------------------
    //  获取配置项具体信息
    // ----------------------------------------------------------------------

    private static function gets($keys, $conf, $names)
    {
        return key_exists($key = array_shift($keys), $conf) ? empty($keys) ? $conf[$key] : self::gets($keys, $conf[$key], $names) : Csn::end('配置项' . $names . '不存在');
    }

    // ----------------------------------------------------------------------
    //  加载配置函数
    // ----------------------------------------------------------------------

    static function __callStatic($name, $args)
    {
        self::files();
        if (in_array($name, self::$module)) {
            array_unshift($args, $name);
            return self::get(join('.', $args));
        } else {
            Csn::end('配置函数' . $name . '未注册');
        }
    }

}