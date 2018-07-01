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
    //  实例列表
    // ----------------------------------------------------------------------

    private static $instances = ['csn\y\\' => [], 'csn\\' => [], 'app\m\\' => []];

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
        if (!key_exists($name, self::$instances[$type]) || !self::$instances[$type][$name]['args'] === $args) {
            $obj = new $class();
            method_exists($obj, 'construct') && call_user_func_array([$obj, 'construct'], $args);
            self::$instances[$type][$name] = ['obj' => $obj, 'args' => $args];
        } else {
            $obj = self::$instances[$type][$name]['obj'];
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