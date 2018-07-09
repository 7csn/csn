<?php

namespace csn;

class DbBase extends Data
{

    // ------------------------------------------
    //  数据库连接
    // ------------------------------------------

    protected static $links = [];   // 数据库连接信息
    protected static $dbInfos = []; // 数据库当前库及默认库数组

    // ------------------------------------------
    //  库表信息
    // ------------------------------------------

    protected static $descs = [];   // 数据结构
    protected static $dbns = [];    // 数据库名列表

    // ------------------------------------------
    //  事务状态
    // ------------------------------------------

    // 是否处于事务
    private static $transaction = false;

    // 获取事务状态
    final static function getTrans()
    {
        return self::$transaction;
    }

    // 修改事务状态
    final static function setTrans($status = false)
    {
        self::$transaction = $status;
    }

}