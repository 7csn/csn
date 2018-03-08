<?php

namespace csn\t;

class Memcache
{

    static protected $nodes;        // 虚实节点数组
    static protected $points = [];  // 虚拟节点数组
    static protected $conf;         // 真实节点地址及对应虚拟节点数量

    // 配置信息初始化
    static function conf()
    {
        if (is_null(self::$conf)) {
            foreach (Conf::data('memcache') as $k => $v) {
                is_int($k) ? self::$conf[$v] = 1 : self::$conf[$k] = $v;
            }
        }
        return self::$conf;
    }

    // 字符串转成非负整数
    static function hash($str)
    {
        return sprintf('%u', crc32($str));
    }

    // 获取虚实落点信息
    static function getNodes()
    {
        if (is_null(self::$nodes)) {
            self::$nodes = [];
            foreach (self::conf() as $node => $num) {
                self::addNode($node, $num);
            }
        }
        return self::$nodes;
    }

    // 添加真实节点
    protected static function addNode($node, $num)
    {
        if (!key_exists($node, self::$nodes)) {
            for ($i = 1; $i <= $num; $i++) {
                $point = self::hash($node . $i);
                self::$points[$point] = $node;
                self::$nodes[$node][] = $point;
            }
            self::sort();
        }
    }

    // 虚拟节点排序
    static function sort()
    {
        ksort(self::$points, SORT_NUMERIC);
    }

    // 计算真实落点
    protected static function getNode($key)
    {
        self::getNodes();
        $hash = self::hash($key);
        $target = current(self::$points);
        foreach (self::$points as $point => $node) {
            if ($hash <= $point) {
                $target = $node;
                break;
            }
        }
        // 指针复位
        reset(self::$points);
        return $target;
    }

    // 连接memcache服务器
    protected static function connect($node)
    {
        if (!key_exists($node, Csn::$usemem)) {
            Csn::$usemem[$node] = new \Memcache;
            list($ip, $port) = explode(':', $node);
            Csn::$usemem[$node]->connect($ip, $port);
        }
        return Csn::$usemem[$node];
    }

    // memcache常规操作
    static function __callStatic($fn, $args)
    {
        $b = substr($fn, 0, 1) === '_';
        $fn = $b ? substr($fn, 1) : $fn;
        if (in_array($fn, ['set', 'get', 'add', 'replace', 'delete', 'increment', 'decrement'])) {
            $key = $b ? array_shift($args) : $args[0];
            $mem = self::connect(self::getNode($key));
            $rm = new \ReflectionMethod($mem, $fn);
            return $rm->invokeArgs($mem, $args);
        }
    }

    // 查看memcache统计信息
    static function getStats($node)
    {
        return self::connect($node)->getStats();
    }

    // 清空指定memcache服务器缓存
    static function flush($node)
    {
        return self::connect($node)->flush();
    }

    // 清空所有memcache服务器缓存
    static function flushAll()
    {
        $arr = [];
        foreach (self::getNodes() as $node => $points) {
            $arr[$node] = self::connect($node)->flush();
        }
        return $arr;
    }

    // 关闭指定连接
    static function close($node)
    {
        $b = null;
        if (key_exists($node, Csn::$usemem)) {
            $b = self::connect($node)->close();
            unset(Csn::$usemem[$node]);
        }
        return $b;
    }

    // 关闭所有连接
    static function closeAll()
    {
        $arr = [];
        foreach (self::getNodes() as $node => $connect) {
            $arr[$node] = self::close($node);
        }
        Csn::$usemem = [];
        return $arr;
    }

    // 查看虚拟节点覆盖率
    static function cover()
    {
        self::getNodes();
        $max = array_sum(array_values(self::conf()));
        return ($max - count(self::$points)) / $max;
    }

}