<?php

namespace csn;

class Redis
{

    private static $obj = [];       // 对象
    private static $auth;           // 密码
    private static $conf;           // 配置信息

    // 配置信息
    private static function conf()
    {
        return is_null(self::$conf) ? self::$conf = Config::data('redis') : self::$conf;
    }

    // Redis对象
    private static function obj($address = '127.0.0.1:6379')
    {
        if (!key_exists($address, self::$obj)) {
            $obj = new \Redis();
            call_user_func_array([$obj, 'connect'], explode(':', $address));
            $auth = self::auth();
            key_exists($address, $auth) && call_user_func([$obj, 'auth'], $auth[$address]);
            self::$obj[$address] = $obj;
        } else {
            $obj = self::$obj[$address];
        }
        return $obj;
    }

    // 密码数组
    private static function auth()
    {
        if (is_null(self::$auth)) {
            $conf = self::conf();
            self::$auth = key_exists('auth', $conf) ? $conf['auth'] : [];
        }
        return self::$auth;
    }

    // Redis常用操作
    static function __callStatic($name, $args)
    {
        $redis = self::obj();
        if (method_exists($redis, $name)) {
            return call_user_func_array([$redis, $name], $args);
        }
    }

    // 关闭Redis链接
    static function close()
    {
        foreach (self::$obj as $redis) {
            $redis->close();
        }
    }

}