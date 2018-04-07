<?php

namespace csn\t;

class Model
{

    protected static $link;     // 数据库链接
    protected $data = [];       // 对象属性
    protected $columns = [];    // 数据结构

    // 获取数据库链接
    protected static function link()
    {
        return is_null(self::$link) ? self::$link = Ds::db() : self::$link;
    }

    // 查询
    static function find()
    {
        return self::name();
    }

    // 查询全部行
    static function all($field = '*')
    {
    }

    // 获取子类名
    static function name()
    {
        return substr(strrchr(static::class, '\\'), 1);
    }

}