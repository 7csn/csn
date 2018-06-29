<?php

namespace csn;

class StdClass
{

    // ----------------------------------------------------------------------
    //  单例
    // ----------------------------------------------------------------------

    static protected $single;

    static function single()
    {
        return is_null(self::$instance) ? self::$instance = new self : self::$instance;
    }

    // ----------------------------------------------------------------------
    //  实例
    // ----------------------------------------------------------------------

    static protected $instance;

    static function instance()
    {
        return new self;
    }

}