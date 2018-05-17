<?php

namespace csn\t;

class Model
{

    // ------------------------------------------
    //  随机主从数据库地址
    // ------------------------------------------

    protected static $ws;           // 写数据库地址数组
    protected static $ms;           // 写读数据库地址关联数组
    protected static $master;       // 写数据库地址
    protected static $slave;        // 读数据库地址

    // 获取写数据地址库组
    protected static function writes()
    {
        if (is_null(self::$ws)) {
            list(self::$ws, self::$ms) = MS::init(Conf::data('model.nodes'));
        }
        return self::$ws;
    }

    // 获取写数据库地址
    protected static function master()
    {
        return is_null(self::$master) ? self::$master = MS::rand(self::writes()) : self::$master;
    }

    // 获取读数据地址库组
    protected static function reads()
    {
        is_null(self::$ms) && self::writes();
        return self::$ms[self::master()];
    }

    // 获取读数据库地址
    protected static function slave()
    {
        return is_null(self::$slave) ? self::$slave = MS::rand(self::reads()) : self::$slave;
    }

    // ------------------------------------------
    //  数据库连接
    // ------------------------------------------

    protected static $link = [];    // 数据库连接信息
    protected static $dbns = [];    // 数据库当前库及默认库数组

    // 获取数据库相关信息
    protected static function node($address)
    {
        $link = Conf::data('model.link');
        if (key_exists($address, $link)) {
            return $link;
        } else {
            Exp::end('数据库' . $address . '连接信息不存在');
        }
    }

    // 数据库连接
    protected static function connect($address)
    {
        if (!key_exists($address, self::$link)) {
            list($host, $port) = explode(':', $address);
            try {
                $node = self::node($address);
                self::$link[$address] = new \PDO("mysql:host=$host;port=$port;dbname={$node['dbn']}", $node['du'], $node['dp']);
                self::$link[$address]->query('SET NAMES utf8');
                self::$dbns[$address] = ['dbn' => $node['dbn'], 'dbn_now' => null];
            } catch (\PDOException $e) {
                Exp::end('[PDO]：' . str_replace("\n", '', iconv("GB2312// IGNORE", "UTF-8", $e->getMessage())));
            }
        }
        return self::$link[$address];
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