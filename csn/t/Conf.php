<?php

namespace csn\t;

class Conf
{

    protected static $init;             // 初始化站点配置文件
    protected static $conf = [];        // 配置信息
    protected static $me = [];          // 站点配置信息
    protected static $csn = [];         // 框架默认配置信息
    protected static $files;            // 默认配置项数组
    protected static $register;         // 配置文件名方法注册数组

    // 初始化站点配置文件
    static function init($force = false)
    {
        if (is_null(self::$init) || $force) {
            foreach (self::files() as $file) {
                File::copy(Csn_s . $file . '.php', App . $file . '.php', $force);
            }
            self::$init = true;
        }
        File::copies([Csn . '.htaccess' => Pub . '.htaccess', Csn . 'favicon.ico' => Pub . 'favicon.ico'], $force);
    }

    // 初始化默认项数组
    protected static function files()
    {
        if (is_null(self::$files)) {
            $dir = dir(Csn_s);
            while (false !== ($path = $dir->read())) {
                if ($path === '.' || $path === '..') continue;
                self::$register[] = self::$files[] = substr($path, 0, -4);
            }
            $dir->close();
        }
        return self::$files;
    }

    // 获取配置信息
    static function get($names)
    {
        $i = strpos($names, '.');
        $conf = self::load($i ? substr($names, 0, $i) : $names);
        return $i ? self::gets(explode('.', substr($names, $i + 1)), $conf, $names) : $conf;
    }

    // 获取配置项
    protected static function load($name)
    {
        return key_exists($name, self::$conf) ? self::$conf[$name] : self::$conf[$name] = self::makeup(self::me($name), self::csn($name));
    }

    // 合并配置项
    protected static function makeup($from, $to)
    {
        foreach ($from as $k => $v) {
            $v === $to[$k] || $to[$k] = is_array($v) ? self::makeup($v, $to[$k]) : $v;
        }
        return $to;
    }

    // 获取站点配置项数组
    protected static function me($name)
    {
        return key_exists($name, self::$me) ? self::$me[$name] : (self::$me[$name] = Csn::inc(App . $name . '.php') ?: []);
    }

    // 获取框架默认配置项数组
    protected static function csn($name)
    {
        return key_exists($name, self::$csn) ? self::$csn[$name] : (self::$csn[$name] = Csn::inc(Csn_s . $name . '.php'));
    }

    // 获取配置项具体信息
    protected static function gets($keys, $conf, $names)
    {
        return key_exists($key = array_shift($keys), $conf) ? empty($keys) ? $conf[$key] : self::gets($keys, $conf[$key], $names) : Exp::end('配置项' . $names . '不存在');
    }

    // 注册配置函数
    static function register($name)
    {
        self::files();
        in_array($name, self::$register) || self::$register[] = $name;
    }

    // 加载配置函数
    static function __callStatic($name, $args)
    {
        self::files();
        if (in_array($name, self::$register)) {
            array_unshift($args, $name);
            return self::get(join('.', $args));
        } else {
            Exp::end('配置函数' . $name . '未注册');
        }
    }

}