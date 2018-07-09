<?php

namespace csn;

class DbBase extends Data
{

    // ------------------------------------------
    //  数据库连接
    // ------------------------------------------

    // 连接数组
    protected static $links = [];

    // 连接库信息数组
    protected static $dbInfos = [];

    // 获取连接
    final protected static function connect($address)
    {
        return key_exists($address, self::$links) ? self::$links[$address] : self::$links[$address] = call_user_func(function ($address) {
            $link = Config::data('dbs.link');
            key_exists($address, $link) || Csn::end('数据库 ' . $address . ' 连接信息不存在');
            $node = $link[$address];
            list($host, $port) = explode(':', $address);
            $link = new \PDO("mysql:host=$host;port=$port", $node['du'], $node['dp']);
            self::$dbInfos[$address] = ['dbn' => $node['dbn'], 'dbn_now' => null, 'dth' => $node['dth']];
            return $link;
        }, $address);
    }

    // ------------------------------------------
    //  库表信息
    // ------------------------------------------

    // 表数据结构
    protected static $descs = [];

    // 库名列表
    protected static $dbns = [];

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