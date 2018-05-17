<?php

namespace csn\t;

class Model
{

    protected static $conf;         // 配置信息
//    protected static $link;         // 数据库连接信息
    protected static $nodes;        // 节点数组
    protected static $master;       // 写节点数组

    // 获取配置信息
    private static function conf()
    {
        return is_null(self::$conf) ? self::$conf = Conf::data('model') : self::$conf;
    }

    // 解析节点数组
    private static function nodes()
    {
        if (is_null(self::$nodes)) {
            $conf = self::conf();
            list(self::$nodes, self::$master) = Node::init($conf['nodes']);
        }
        return self::$nodes;
    }



    static function test(){
        return self::nodes();
    }


//    protected static $link;     // 数据库链接
//    protected $data = [];       // 对象属性
//    protected $columns = [];    // 数据结构

//    // 获取数据库链接
//    protected static function link()
//    {
//        return is_null(self::$link) ? self::$link = Ds::db() : self::$link;
//    }
//
//    // 查询
//    static function find()
//    {
//        return self::name();
//    }
//
//    // 查询全部行
//    static function all($field = '*')
//    {
//    }
//
//    // 获取子类名
//    static function name()
//    {
//        return substr(strrchr(static::class, '\\'), 1);
//    }

}