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
    //  实例性质
    // ----------------------------------------------------------------------

    // 单例(single)、同参缓存(true)、不缓存(false)
    public static $instanceCache = true;

    // 列表
    private static $cacheTypes = [];

    // 获取
    final static function getCacheTypes($class)
    {
        return key_exists($class, self::$cacheTypes) ? self::$cacheTypes[$class] : self::$cacheTypes[$class] = (new \ReflectionProperty($class, 'instanceCache'))->getValue();
    }

    // ----------------------------------------------------------------------
    //  创建实例
    // ----------------------------------------------------------------------

    final static function instance()
    {
        $class = get_called_class();
        if (is_null($search = Csn::search($class))) return;
        list($type, $name) = $search;
        $args = func_get_args();
        return self::create($type, $name, $args);
    }

    final static function create($type, $name, $args)
    {
        $class = $type . $name;
        if (key_exists($name, self::$instances[$type]) && self::$instances[$type][$name]['cache'] && (self::$instances[$type][$name]['cache'] === self::single() || self::$instances[$type][$name]['args'] === $args)) {
            $obj = self::$instances[$type][$name]['obj'];
        } else {
            $obj = new $class();
            method_exists($obj, 'construct') && $cache = call_user_func_array([$obj, 'construct'], $args);
            if (!isset($cache) || is_null($cache)) $cache = self::getCacheTypes($class);
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