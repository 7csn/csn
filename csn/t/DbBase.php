<?php

namespace csn;

abstract class DbBase extends Data
{

    // ----------------------------------------------------------------------
    //  指定库名
    // ----------------------------------------------------------------------

    final protected static function setDbn($address, $dbn)
    {
        $linkInfo = self::linkInfo($address);
        $link = $linkInfo['link'];
        if ($dbn !== $linkInfo['dbn']) {
            in_array($dbn, self::dbName($address)) || Csn::end('服务器 ' . $address . ' 不存在数据库 ' . $dbn);
            $link->query(" USE `$dbn` ");
            self::$linkInfo[$address]['dbn'] = $dbn;
        }
        return $link;
    }

    // ----------------------------------------------------------------------
    //  连接信息
    // ----------------------------------------------------------------------

    // 连接列表：连接、当前库
    private static $linkInfo = [];

    // 获取连接信息
    final protected static function linkInfo($address)
    {
        return key_exists($address, self::$linkInfo) ? self::$linkInfo[$address] : self::$linkInfo[$address] = call_user_func(function () use ($address) {
            $node = self::node($address);
            list($host, $port) = explode(':', $address);
            return ['link' => new \PDO("mysql:host=$host;port=$port", $node['du'], $node['dp']), 'dbn' => null];
        });
    }

    // ----------------------------------------------------------------------
    //  获取节点信息
    // ----------------------------------------------------------------------

    private static $nodes = [];

    final protected static function node($address)
    {
        return key_exists($address, self::$nodes) ? self::$nodes[$address] : call_user_func(function () use ($address) {
        $links = Config::data('mysql.link');
        key_exists($address, $links) || Csn::end('数据库连接配置地址 ' . $address . ' 不存在');
        return $links[$address];
    });
    }

    // ----------------------------------------------------------------------
    //  连接库名列表
    // ----------------------------------------------------------------------

    private static $dbNames = [];

    final protected static function dbName($address)
    {
        return key_exists($address, self::$dbNames) ? self::$dbNames[$address] : self::$dbNames[$address] = call_user_func(function () use ($address) {
            $dbNames = [];
            foreach (self::inQuery(self::linkInfo($address)['link'], " SHOW DATABASES ") as $v) {
                $dbNames[] = $v->Database;
            }
            return $dbNames;
        });
    }

    // ----------------------------------------------------------------------
    //  表结构
    // ----------------------------------------------------------------------

    private static $describe = [];

    final static function describe($address, $dbn, $tbn)
    {
        return empty(self::$describe[$address][$dbn][$tbn]) ? self::$describe[$address][$dbn][$tbn] = call_user_func(function () use ($address, $dbn, $tbn) {
            $desc = new \stdClass();
            $desc->list = new \stdClass();
            $desc->primaryKey = null;
            foreach (self::inQuery(self::setDbn($address, $dbn), " DESC `$tbn` ") as $v) {
                $v->Key === 'PRI' && $desc->primaryKey = $v->Field;
                $desc->list->{$v->Field} = call_user_func(function ($row) {
                    unset($row->Field);
                    return $row;
                }, $v);
            }
            return $desc;
        }) : self::$describe[$address][$dbn][$tbn];
    }

    // ----------------------------------------------------------------------
    //  字段值处理
    // ----------------------------------------------------------------------

    final static function parseValue($structure, $val = null)
    {
        switch (explode('(', $structure->Type)[0]) {
            case 'char':
            case 'varchar':
            case 'tinytext':
            case 'longtext':
            case 'mediumtext':
            case 'text':
                $val = is_null($val) ? $structure->Default : (is_array($val) ? json_encode($val) : (string)$val);
                break;
            case 'tinyint':
            case 'smallint':
            case 'mediumint':
            case 'int':
            case 'bigint':
                $val = is_null($val) ? $structure->Default : (int)$val;
                break;
            default:
                $val = is_null($val) ? $structure->Default : floatval($val);
                break;
        }
        return (is_null($val) && !is_null($structure->Default)) ? $structure->Default : $val;
    }

    // ----------------------------------------------------------------------
    //  表SQL封装：查询、增删改
    // ----------------------------------------------------------------------

    private static $lastSql;

    final static function lastSql()
    {
        return self::$lastSql;
    }

    final static function inQuery($link, $sql, $bind = [], $rArr = false)
    {
        self::$lastSql = [$sql, $bind];
        $sth = $link->prepare($sql);
        $sth->execute($bind);
        $sth->setFetchMode($rArr ? \PDO::FETCH_ASSOC : \PDO::FETCH_OBJ);
        $res = [];
        while ($v = $sth->fetch()) {
            $res[] = $v;
        }
        $sth = null;
        return $res;
    }

    final static function modify($link, $sql, $bind = [], $insert = false)
    {
        self::$lastSql = [$sql, $bind];
        $bool = $link->prepare($sql)->execute($bind);
        return $insert ? $bool ? $link->lastInsertId() : 0 : $bool;
    }

    // ----------------------------------------------------------------------
    //  事务处理
    // ----------------------------------------------------------------------

    // 事务连接
    private static $transLink;

    // 事务失败回调
    private static $transFail;

    // 获取事务状态
    final static function getTrans()
    {
        return !is_null(self::$transLink);
    }

    // 开始事务
    final static function beginTrans($link, $action, $fail)
    {
        $action instanceof Course || Csn::end('事务函数须为 Course 对象');
        $fail instanceof Course || Csn::end('事务故障函数须为 Course 对象');
        $link->beginTransaction();
        self::$transLink = $link;
        self::$transFail = $fail;
        $func = $action->run();
        self::$transLink->commit();
        self::$transLink = null;
        self::$transFail = null;
        return $func;
    }

    // 结束事务
    final static function transEnd($error = false)
    {
        if (is_null(self::$transLink)) return;
        self::$transLink->rollBack();
        $func = self::$transFail->run();
        self::$transFail = null;
        self::$transLink = null;
        $error && Csn::dump(self::lastSql(), $error);
        return $func;
    }

    // ----------------------------------------------------------------------
    //  初始化SQL因素对象
    // ----------------------------------------------------------------------

    protected $components;

    final protected function component()
    {
        $this->components = Data::instance();
        return $this;
    }

    // ----------------------------------------------------------------------
    //  SQL因素
    // ----------------------------------------------------------------------

    // 主表及默认表前缀
    final function position($table, $dth)
    {
        $this->components->table = $table;
        $this->components->dth = $dth;
        return $this;
    }

    // ----------------------------------------------------------------------
    //  SQL因素
    // ----------------------------------------------------------------------

    function __call($fn, $args)
    {

    }

}