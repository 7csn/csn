<?php

namespace csn;

class MS extends Node
{

    // ----------------------------------------------------------------------
    //  返回随机数据库地址：按值大小比例随机获取键
    // ----------------------------------------------------------------------

    static function master($arr, $times = 100)
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