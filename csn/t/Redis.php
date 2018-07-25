<?php

namespace csn;

final class Redis extends Instance
{

    // ----------------------------------------------------------------------
    //  构造函数
    // ----------------------------------------------------------------------

    // 主库是否分布式
    private $distribute;

    // 密码数组
    private $auth;

    // 初始化配置信息
    function construct()
    {
        $this->distribute = Config::data('redis.distribute');
        list($this->writes, $this->reads) = $this->distribute ? DT::init(Config::data('redis.nodes')) : MS::init(Config::data('redis.nodes'));
        $this->auth = Config::data('redis.auth');
        return self::single();
    }

    // ----------------------------------------------------------------------
    //  主数据库
    // ----------------------------------------------------------------------

    // 列表
    private $writes;

    // 获取列表
    protected function writes()
    {
        return $this->writes;
    }

    // 根据
    protected function master($key = null)
    {
        return $this->distribute ? DT::rand($key, $this->writes) : MS::rand($key, $this->writes);
    }

    // ----------------------------------------------------------------------
    //  从数据库
    // ----------------------------------------------------------------------

    // 列表
    private $reads;

    // 获取列表
    protected function reads()
    {
        is_null(self::$ms) && self::writes();
        return self::$ms[self::master()];
    }

    // 获取读数据库地址
    protected function slave()
    {
        return self::getTrans() ? self::master() : (is_null(self::$slave) ? self::$slave = MS::rand(self::reads()) : self::$slave);
    }

    // ----------------------------------------------------------------------
    //
    // ----------------------------------------------------------------------


    // ----------------------------------------------------------------------
    //
    // ----------------------------------------------------------------------

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

    // ----------------------------------------------------------------------
    //
    // ----------------------------------------------------------------------

    // 密码数组
    private static function auth()
    {
        if (is_null(self::$auth)) {
            $conf = self::conf();
            self::$auth = key_exists('auth', $conf) ? $conf['auth'] : [];
        }
        return self::$auth;
    }

    // ----------------------------------------------------------------------
    //
    // ----------------------------------------------------------------------

    // Redis常用操作
    static function __callStatic($name, $args)
    {
        $redis = self::obj();
        if (method_exists($redis, $name)) {
            return call_user_func_array([$redis, $name], $args);
        }
    }

    // ----------------------------------------------------------------------
    //
    // ----------------------------------------------------------------------

    // 关闭Redis链接
    static function close()
    {
        foreach (self::$obj as $redis) {
            $redis->close();
        }
    }

}