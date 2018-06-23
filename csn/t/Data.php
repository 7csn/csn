<?php

namespace csn\t;

class Data
{

    // 数据集合
    protected $data = [];

    // 获取全部数据
    function whole()
    {
        return $this->data;
    }

    // 清空数据
    function clear()
    {
        $this->data = [];
    }

    // 数据储存
    function __set($key, $val) {
        $this->data[$key] = $val;
    }

    // 数据读取
    function __get($key)
    {
        return key_exists($key, $this->data) ? $this->data[$key] : null;
    }

}