<?php

namespace csn;

class Instance
{

    // ----------------------------------------------------------------------
    //  构造函数
    // ----------------------------------------------------------------------

    final protected function __construct()
    {
    }

    // ----------------------------------------------------------------------
    //  单例调用
    // ----------------------------------------------------------------------

    final protected static function single()
    {
        return 'single';
    }

    // ----------------------------------------------------------------------
    //  实例列表
    // ----------------------------------------------------------------------

    private static $instances = ['csn\y\\' => [], 'csn\\' => [], 'app\m\\' => []];

    // ----------------------------------------------------------------------
    //  创建实例
    // ----------------------------------------------------------------------

    final static function instance()
    {
        if (is_null($search = Csn::search(get_called_class()))) return;
        list($type, $name) = $search;
        return self::create($type, $name, func_get_args());
    }

    final static function create($type, $name, $args)
    {
        $class = $type . $name;
        if (key_exists($name, self::$instances[$type]) && self::$instances[$type][$name]['cache'] && (self::$instances[$type][$name]['cache'] === self::single() || self::$instances[$type][$name]['args'] === $args)) {
            $obj = self::$instances[$type][$name]['obj'];
        } else {
            $obj = new $class();
            method_exists($obj, 'construct') && $cache = call_user_func_array([$obj, 'construct'], $args);
            if (!isset($cache) || is_null($cache)) $cache = true;
            self::$instances[$type][$name] = ['obj' => $obj, 'args' => $args, 'cache' => $cache];
        }
        return $obj;

    }

    // ----------------------------------------------------------------------
    //  查看实例列表
    // ----------------------------------------------------------------------

    final static function instances()
    {
        return self::$instances;
    }

}