<?php

namespace csn;

class StdClass extends Instance
{

    // ----------------------------------------------------------------------
    //  匿名函数
    // ----------------------------------------------------------------------

    protected $func;

    // ----------------------------------------------------------------------
    //  参数列表
    // ----------------------------------------------------------------------

    protected $args = [];

    // ----------------------------------------------------------------------
    //  构造函数：匿名函数、默认参数列表为空
    // ----------------------------------------------------------------------

    function construct($func)
    {
        $this->func = $func;
        return false;
    }

    // ----------------------------------------------------------------------
    //  设置参数
    // ----------------------------------------------------------------------

    // 模拟传参
    function args()
    {
        $this->args = func_get_args();
        return $this;
    }

    // 参数列表
    function onceArgs($args)
    {
        $this->args = $args;
        return $this;
    }

    // ----------------------------------------------------------------------
    //  修改参数
    // ----------------------------------------------------------------------

    // 追加参数
    function push()
    {
        $args = func_get_args();
        array_unshift($args, $this->args);
        call_user_func_array('array_push', $args);
        return $this;
    }

    // 移除最后参数
    function pop()
    {
        array_pop($this->args);
        return $this;
    }

    // 开头插入参数
    function unShift()
    {
        $args = func_get_args();
        array_unshift($args, $this->args);
        call_user_func_array('array_unshift', $args);
        return $this;
    }

    // 移除开头参数
    function shift()
    {
        array_shift($this->args);
        return $this;
    }

    // ----------------------------------------------------------------------
    //  执行匿名函数
    // ----------------------------------------------------------------------

    function run()
    {
        return call_user_func_array($this->func, $this->args);
    }

}