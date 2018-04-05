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

    }

    static function all($field = '*')
    {

    }

    // 获取子类名
    static function name()
    {
        return substr(strrchr(get_called_class(), '\\'), 1);
    }

}