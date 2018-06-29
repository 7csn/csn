<?php

namespace csn;

class Node
{

    // ----------------------------------------------------------------------
    //  初始化节点数组
    // ----------------------------------------------------------------------

    static function init($nodes)
    {
        $ws = [];
        $ms = [];
        foreach ($nodes as $k => $v) {
            if (is_array($v)) {
                $ws[$k] = key_exists('weight', $v) ? $v['weight'] : 1;
                foreach ($v['slave'] as $kk => $vv) {
                    is_int($vv) ? $ms[$k][$kk] = $vv : $ms[$k][$vv] = 1;
                }
            } else {
                if (is_int($v)) {
                    $ws[$k] = $v;
                    $ms[$k] = [$k => 1];
                } else {
                    $ws[$v] = 1;
                    $ms[$v] = [$v => 1];
                }
            }
        }
        return [$ws, $ms];
    }

}