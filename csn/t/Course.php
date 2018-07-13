<?php

namespace csn;

final class Course extends Instance
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

    function args()
    {
        $this->args = func_get_args();
        return $this;
    }

    function once($args)
    {
        $this->args = $args;
        return $this;
    }

    function push()
    {
        foreach (func_get_args() as $arg) {
            $this->args[] = $arg;
        }
        return $this;
    }

    function pop()
    {
        array_pop($this->args);
        return $this;
    }

    function shift()
    {
        array_shift($this->args);
        return $this;
    }

    function unShift()
    {
        $this->args = array_merge(func_get_args(), $this->args);
        return $this;
    }

    function show()
    {
        return $this->args;
    }

    // ----------------------------------------------------------------------
    //  执行匿名函数
    // ----------------------------------------------------------------------

    function run()
    {
        return call_user_func_array($this->func, $this->args);
    }

    // ----------------------------------------------------------------------
    //  匿名函数缓冲
    // ----------------------------------------------------------------------

    function buffer()
    {
        ob_start();
        $this->run();
        $content = ob_get_contents();
        ob_end_clean();
        return $content;
    }

}