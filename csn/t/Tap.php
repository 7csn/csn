<?php

namespace csn;

class Tap extends Instance
{

    protected $method;      // 路由方法
    protected $path;        // 路由标记
    protected $where = [];  // 参数正则
    protected $input = [];  // POST验证
    protected $cache;       // 静态页缓存时效

    // 开关对象
    function construct($method, $path)
    {
        $this->method = $method;
        $this->path = $path;
    }

    // 路由正则条件
    function where($key, $preg = null)
    {
        $where = is_array($key) ? $key : [$key => $preg];
        foreach ($where as $key => $preg) {
            $this->where[$key] = $preg;
        }
        foreach ($this->method as $method) {
            Route::$tap[$method][$this->path]['where'] = $this->where;
        }
        return $this;
    }

    // POST正则条件
    function input($key, $preg = null)
    {
        if (in_array('POST', $this->method)) {
            $input = is_array($key) ? $key : [$key => $preg];
            foreach ($input as $key => $preg) {
                $this->input[$key] = $preg;
            }
            Route::$tap['POST'][$this->path]['input'] = $this->input;
        }
        return $this;
    }

    // 路由正则条件解析
    function parse($show = false)
    {
        $path = str_replace('/', '\\/', $this->path);
        foreach ($this->where as $key => $preg) {
            $path = str_replace(['{' . $key . '}', '@{' . $key . '?}'], ['(' . $preg . ')', '(@' . $preg . ')?'], $path);
        }
        foreach ($this->method as $method) {
            Route::$tap[$method][$this->path]['preg'] = $path;
            Route::$tap[$method][$this->path]['parse'] = true;
        }
        return $show ? $path : $this;
    }

    // 静态页缓存时效
    function cache($time = 0)
    {
        $this->cache = $time;
        return $this;
    }

}