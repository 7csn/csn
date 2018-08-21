<?php

namespace csn;

final class Config extends Instance
{

    // ----------------------------------------------------------------------
    //  初始化应用配置文件
    // ----------------------------------------------------------------------

    private static $init;

    static function init($force = false)
    {
        if ($force || is_null(self::$init)) {
            foreach (self::files() as $file) {
                File::copy(CSN_S . $file . '.php', APP_CONFIG . $file . '.php', $force);
            }
            self::$init = true;
        }
    }

    // ----------------------------------------------------------------------
    //  配置项：作为函数名调用文件配置
    // ----------------------------------------------------------------------

    // 默认配置项列表
    private static $files;

    // 初始化配置项
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

    // 全配置项列表
    private static $module = [];

    // 注册配置项：开发者自定义配置文件
    static function register($name)
    {
        self::files();
        in_array($name, self::$module) || self::$module[] = $name;
    }

    // ----------------------------------------------------------------------
    //  加载配置：调用配置函数
    // ----------------------------------------------------------------------

    static function __callStatic($name, $args)
    {
        self::files();
        in_array($name, self::$module) || Csn::end('配置函数' . $name . '未注册');
        $names = join('.', $args);
        return $names ? self::gets(explode('.', $names), self::load($name), $name . '.' . $names) : self::load($name);
    }

    // ----------------------------------------------------------------------
    //  获取配置项具体信息
    // ----------------------------------------------------------------------

    private static function gets($keys, $config, $names)
    {
        return key_exists($key = array_shift($keys), $config) ? empty($keys) ? $config[$key] : self::gets($keys, $config[$key], $names) : Csn::end('配置项' . $names . '不存在');
    }

    // ----------------------------------------------------------------------
    //  获取配置项信息
    // ----------------------------------------------------------------------

    // 全配置
    private static $config = [];

    // 加载配置项内容
    private static function load($name)
    {
        return key_exists($name, self::$config) ? self::$config[$name] : self::$config[$name] = $name === 'data' ? self::app($name) : self::makeup(self::app($name), self::csn($name));
    }

    // 应用配置
    private static $app = [];

    // 获取应用配置项内容
    private static function app($name)
    {
        return key_exists($name, self::$app) ? self::$app[$name] : self::$app[$name] = Csn::need(APP_CONFIG . $name . '.php') ?: [];
    }

    // 框架配置
    private static $csn = [];

    // 获取框架配置项内容
    private static function csn($name)
    {
        return key_exists($name, self::$csn) ? self::$csn[$name] : self::$csn[$name] = Csn::need(CSN_S . $name . '.php') ?: [];
    }

    // ----------------------------------------------------------------------
    //  合并配置项内容
    // ----------------------------------------------------------------------

    private static function makeup($from, $to)
    {
        foreach ($from as $k => $v) {
            $v === $to[$k] || $to[$k] = is_array($v) ? self::makeup($v, $to[$k]) : $v;
        }
        return $to;
    }

}