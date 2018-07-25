<?php

namespace csn;

class DT extends Node
{

    // ----------------------------------------------------------------------
    //  字符串转成非负整数
    // ----------------------------------------------------------------------

    static function hash($str)
    {
        return sprintf('%u', crc32($str));
    }

    // ----------------------------------------------------------------------
    //  计算真实落点
    // ----------------------------------------------------------------------

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

    // ----------------------------------------------------------------------
    //  返回随机数据库地址：按值大小比例随机获取键
    // ----------------------------------------------------------------------

    static function rand($arr, $times = 100)
    {
        $count = count($arr);
        if ($count === 1) return key($arr);
        $range = 0;
        foreach ($arr as $v) {
            $range += $v;
        }
        $rand = rand(1, $range * $times) % $range;
        foreach ($arr as $k => $v) {
            if ($v > $rand) {
                return $k;
            } else {
                $rand -= $v;
            }
        }
    }

    // ----------------------------------------------------------------------
    //  按权重以指定倍数随机从列表中选取从库
    // ----------------------------------------------------------------------

    static function slave($arr, $times = 100)
    {
        $count = count($arr);
        if ($count === 1) return key($arr);
        $range = 0;
        foreach ($arr as $v) {
            $range += $v;
        }
        $rand = mt_rand(1, $range * $times) % $range;
        foreach ($arr as $k => $v) {
            if ($v > $rand) {
                return $k;
            } else {
                $rand -= $v;
            }
        }
    }

}