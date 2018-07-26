<?php

namespace csn;

class Memcache extends Instance
{

    // ----------------------------------------------------------------------
    //  初始化配置信息
    // ----------------------------------------------------------------------

    // 节点对象
    private $obj;

    // 节点对象
    private $nodes = [];

    // 构造函数
    function construct()
    {
        foreach (Config::data('memcache') as $k => $v) {
            is_int($k) ? $this->nodes[$v] = 1 : $this->nodes[$k] = $v;
        }
        $this->obj = Distribute::instance($this->nodes);
        return self::single();
    }

    // ----------------------------------------------------------------------
    //  数据库连接
    // ----------------------------------------------------------------------

    // 获取连接
    function link($key)
    {
        return $this->connect($this->obj->getNode($key));
    }

    // 连接列表
    private static $links = [];

    // 生成连接并返回
    protected static function connect($address)
    {
        if (key_exists($address, self::$links)) {
            $link = self::$links[$address];
        } else {
            $link = new \Memcache();
            call_user_func_array([$link, 'connect'], explode(':', $address));
            self::$links[$address] = $link;
        }
        return $link;
    }

    // ----------------------------------------------------------------------
    //  常规操作
    // ----------------------------------------------------------------------

    static function __callStatic($name, $args)
    {
        $alias = substr($name, 0, 1) === '_';
        $name = $alias ? substr($name, 1) : $name;
        if (in_array($name, ['set', 'get', 'add', 'replace', 'delete', 'increment', 'decrement'])) {
            $key = $alias ? array_shift($args) : $args[0];
            return call_user_func_array([self::instance()->link($key), $name], $args);
        }
    }

    // ----------------------------------------------------------------------
    //  查看指定数据库统计信息
    // ----------------------------------------------------------------------

    static function getStats($address)
    {
        return self::connect($address)->getStats();
    }

    // ----------------------------------------------------------------------
    //  清空指定数据库缓存
    // ----------------------------------------------------------------------

    static function flush($address)
    {
        return self::connect($address)->flush();
    }

    // ----------------------------------------------------------------------
    //  清空所有数据库缓存
    // ----------------------------------------------------------------------

    static function flushAll()
    {
        $arr = [];
        foreach (self::links as $address => $link) {
            $arr[$address] = $link->flush();
        }
        return $arr;
    }

    // ----------------------------------------------------------------------
    //  关闭指定连接
    // ----------------------------------------------------------------------

    static function close($address)
    {
        key_exists($address, self::$links) && self::$links[$address]->close();
    }

    // ----------------------------------------------------------------------
    //  关闭所有连接
    // ----------------------------------------------------------------------

    static function closeAll()
    {
        foreach (self::links as $link) {
            $link->close();
        }
    }

}