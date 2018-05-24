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

    protected static $links = [];   // 数据库连接信息
    protected static $dbInfos = []; // 数据库当前库及默认库数组
    static $dbn = false;             // 当前数据库

    // 获取数据库相关信息
    protected static function node($address)
    {
        $link = Conf::data('model.link');
        if (key_exists($address, $link)) {
            return $link[$address];
        } else {
            Exp::end('数据库' . $address . '连接信息不存在');
        }
    }

    // 数据库连接
    protected static function connect($address)
    {
        return key_exists($address, self::$links) ? self::$links[$address] : self::$links[$address] = (function () use ($address) {
            list($host, $port) = explode(':', $address);
            try {
                $node = self::node($address);
                $link = new \PDO("mysql:host=$host", $node['du'], $node['dp']);
                self::$dbInfos[$address] = ['dbn' => $node['dbn'], 'dbn_now' => null];
            } catch (\PDOException $e) {
                Exp::end('[PDO]：' . str_replace("\n", '', iconv("GB2312// IGNORE", "UTF-8", $e->getMessage())));
            }
            return $link;
        })();
    }

    // ------------------------------------------
    //  表SQL封装
    // ------------------------------------------

    // 查询
    protected static function query($func, $rArr = false)
    {
        $sqls = call_user_func($func, self::tbn());
        $link = self::dbn(self::slave());
        if (is_array($sqls)) {
            $sth = $link->prepare($sqls[0]);
            $sth->execute($sqls[1]);
        } else {
            $sth = $link->query($sqls);
        }
        return self::res($sth, $rArr);
    }

    // 结果集
    protected static function res(&$sth, $rArr = false)
    {
        $sth->setFetchMode($rArr ? \PDO::FETCH_ASSOC : \PDO::FETCH_OBJ);
        $res = [];
        while ($v = $sth->fetch()) {
            $res[] = $v;
        }
        $sth = null;
        return $res;
    }

    // 修改
    protected static function execute($func)
    {
        $sqls = call_user_func($func, self::tbn());
        $link = self::dbn(self::master());
        return is_array($sqls) ? $link->prepare($sqls[0])->execute($sqls[1]) : $link->exec($sqls);
    }

    // ------------------------------------------
    //  类操作
    // ------------------------------------------

    protected static $descs = [];   // 数据结构
    protected static $dbns = [];    // 数据库名列表

    // 数据库匹配;返回连接
    protected static function dbn($address)
    {
        $link = self::connect($address);
        $dbInfo = self::$dbInfos[$address];
        $dbn = self::$dbn ?: $dbInfo['dbn'];
        if ($dbn !== $dbInfo['dbn_now']) {
            in_array($dbn, self::dbns($link, $dbn)) ? $link->query(" USE `$dbn` ") : Exp::end('服务器 ' . $address . ' 不存在数据库 ' . $dbn);
            self::$dbInfos[$address]['db_now'] = $dbn;
        }
        return $link;
    }

    // 获取数据库列表
    protected static function dbns($link, $address)
    {
        return key_exists($address, self::$dbns) ? self::$dbns[$address] : self::$dbns[$address] = (function () use ($link) {
            $sth = $link->query(" SHOW DATABASES ");
            $dbns = [];
            foreach (self::res($sth) as $v) {
                $dbns[] = $v->Database;
            }
            return $dbns;
        })();
    }

    // 获取表名
    protected static function tbn()
    {
        return substr(strrchr(static::class, '\\'), 1);
    }

    // 查询表结构
    protected static function desc()
    {
        $tbn = self::tbn();
        return key_exists($tbn, self::$descs) ? self::$descs[$tbn] : self::$descs[$tbn] = (function () {
            $desc = new \stdClass();
            $desc->list = new \stdClass();
            foreach (self::query(function ($tbn) {
                return " DESC `$tbn` ";
            }) as $v) {
                $v->Key === 'PRI' && $primaryKey = $v->Field;
                $desc->list->{$v->Field} = (function ($row) {
                    unset($row->Field);
                    return $row;
                })($v);
            }
            $desc->primaryKey = $primaryKey ?? null;
            return $desc;
        })();
    }

    // 重置表结构
    static function truncate()
    {
        return self::execute(function ($tbn) {
            return " TRUNCATE TABLE `$tbn` ";
        });
    }

    // 查询全部行
    static function all($field = '*', $rArr = false)
    {
        return self::query(function ($tbn) use ($field) {
            $fields = '`' . (is_array($field) ? implode('`,`', $field) : $field) . '`';
            return " SELECT $fields FROM `$tbn` ";
        }, $rArr);
    }

    // 条件
    static function where($where)
    {
    }

    // ------------------------------------------
    //  确定对象
    // ------------------------------------------


    // ------------------------------------------
    //  对象操作
    // ------------------------------------------

    protected $data = [];           // 对象属性

    // 设置字段值
    function __set($key, $value)
    {
        $this->data[$key] = $value;
    }

    // 获取字段值
    function __get($key)
    {
        return key_exists($key, $this->data) ? $this->data[$key] : null;
    }


    static function test()
    {
        return substr(strrchr(static::class, '\\'), 1);
    }

//
//    // 查询
//    static function find()
//    {
//        return self::name();
//    }

}