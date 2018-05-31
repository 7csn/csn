<?php

namespace csn\t;

class DbInfo
{

    // ------------------------------------------
    //  随机主从数据库地址
    // ------------------------------------------

    static $ws;           // 写数据库地址数组
    static $ms;           // 写读数据库地址关联数组
    static $master;       // 写数据库地址
    static $slave;        // 读数据库地址

    // ------------------------------------------
    //  数据库连接
    // ------------------------------------------

    static $links = [];   // 数据库连接信息
    static $dbInfos = []; // 数据库当前库及默认库数组

    // ------------------------------------------
    //  库表信息
    // ------------------------------------------

    static $descs = [];   // 数据结构
    static $dbns = [];    // 数据库名列表

    // ------------------------------------------
    //  事务状态
    // ------------------------------------------

    // 是否处于事务
    protected static $transaction = false;

    // 获取事务状态
    static function getTransaction()
    {
        return self::$transaction;
    }

    // 修改事务状态
    static function setTransaction($status = false)
    {
        self::$transaction = $status;
    }

}