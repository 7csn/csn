<?php

namespace csn;

final class Tap extends Instance
{

    // ----------------------------------------------------------------------
    //  构造方法：标记方法与路由
    // ----------------------------------------------------------------------

    protected $method;

    protected $path;

    function construct($method, $path)
    {
        $this->method = $method;
        $this->path = $path;
    }

    // ----------------------------------------------------------------------
    //  正则条件：参数=>正则
    // ----------------------------------------------------------------------

    // 路由正则数组
    protected $where = [];

    // 路由正则条件
    function where($key, $preg = null)
    {
        $where = is_array($key) ? $key : [$key => $preg];
        foreach ($where as $key => $preg) {
            $this->where[$key] = $preg;
        }
        foreach ($this->method as $method) {
            Route::$taps[$method][$this->path]['where'] = $this->where;
        }
        return $this;
    }

    // POST正则数组
    protected $input = [];

    // POST正则条件
    function input($key, $preg = null)
    {
        if (in_array('POST', $this->method)) {
            $input = is_array($key) ? $key : [$key => $preg];
            foreach ($input as $key => $preg) {
                $this->input[$key] = $preg;
            }
            Route::$taps['POST'][$this->path]['input'] = $this->input;
        }
        return $this;
    }

    // ----------------------------------------------------------------------
    //  正则条件解析
    // ----------------------------------------------------------------------

    function parse()
    {
        $path = str_replace('/', '\\/', $this->path);
        foreach ($this->where as $key => $preg) {
            $path = str_replace(['{' . $key . '}', '@{' . $key . '?}'], ['(' . $preg . ')', '(@' . $preg . ')?'], $path);
        }
        foreach ($this->method as $method) {
            Route::$taps[$method][$this->path]['preg'] = $path;
            Route::$taps[$method][$this->path]['parse'] = true;
        }
        return $this;
    }

    // ----------------------------------------------------------------------
    //  静态页缓存时效：默认配置web.view_cache
    // ----------------------------------------------------------------------

    protected $cache;

    function cache($time = 0)
    {
        $this->cache = $time;
        return $this;
    }

}