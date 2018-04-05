<?php

namespace csn\t;

class Model
{

    protected static $link;     // 数据库链接

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

    static function all($field = '*')
    {
//        return self::link()->query('')
    }

    // 获取子类名
    static function name()
    {
        return substr(strrchr(static::class, '\\'), 1);
    }

}