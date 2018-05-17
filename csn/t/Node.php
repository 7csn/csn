<?php

namespace csn\t;

class Node
{

    // 初始化节点数组
    static function init($data)
    {
        $all = [];
        $write = [];
        foreach ($data as $k => $v) {
            if (is_array($v)) {
                $all[$k]['weight'] = key_exists('weight', $v) ? $v['weight'] : 1;
                foreach ($v['slave'] as $kk => $vv) {
                    is_int($vv) ? $all[$k]['slave'][$kk] = $vv : $all[$k]['slave'][$vv] = 1;
                }
            } else {
                if (is_int($v)) {
                    $all[$k] = ['weight' => $v, 'slave' => [$k => 1]];
                } else {
                    $all[$v] = ['weight' => 1, 'slave' => [$v => 1]];
                }
            }
        }
        return $all;
    }

}