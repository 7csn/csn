<?php

namespace csn;

class Distribute extends Instance
{

    // ----------------------------------------------------------------------
    //  构造函数：初始化节点信息
    // ----------------------------------------------------------------------

    function construct($nodes)
    {
        // 虚节点基础倍率
        $times = Config::web('node_times');
        // 添加虚实节点
        foreach ($nodes as $node => $num) {
            $this->addNode($node, $num * $times);
        }
        // 虚拟节点覆盖率
        $this->cover = 1 - (count($this->points) / (array_sum(array_values($nodes)) * $times));
    }

    // ----------------------------------------------------------------------
    //  节点
    // ----------------------------------------------------------------------

    // 虚实节点数组
    protected $nodes = [];

    // 虚节点数组
    protected $points = [];

    // 添加虚实节点
    protected function addNode($node, $num)
    {
        if (key_exists($node, $this->nodes)) return;
        for ($i = 1; $i <= $num; $i++) {
            $point = $this->hash($node . '@' . $i);
            $this->points[$point] = $node;
            $this->nodes[$node][] = $point;
        }
        // 虚节点数组排序
        ksort($this->points, SORT_NUMERIC);
    }

    // ----------------------------------------------------------------------
    //  非负整数哈希
    // ----------------------------------------------------------------------

    protected function hash($str)
    {
        return sprintf('%u', crc32($str));
    }

    // ----------------------------------------------------------------------
    //  计算真实落点
    // ----------------------------------------------------------------------

    function getNode($key)
    {
        $hash = $this->hash($key);
        $target = current($this->points);
        foreach ($this->points as $point => $node) {
            if ($hash <= $point) {
                $target = $node;
                break;
            }
        }
        // 指针复位
        reset($this->points);
        return $target;
    }

    // ----------------------------------------------------------------------
    //  虚节点覆盖率
    // ----------------------------------------------------------------------

    private $cover;

    function cover()
    {
        return $this->cover;
    }

}