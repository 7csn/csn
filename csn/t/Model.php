<?php

namespace csn\t;

class Model
{

    // ------------------------------------------
    //  随机主从数据库地址
    // ------------------------------------------

    // 获取写数据地址库组
    protected static function writes()
    {
        if (is_null(DbInfo::$ws)) {
            list(DbInfo::$ws, DbInfo::$ms) = MS::init(Conf::data('model.nodes'));
        }
        return DbInfo::$ws;
    }

    // 获取写数据库地址
    protected static function master()
    {
        return is_null(DbInfo::$master) ? DbInfo::$master = MS::rand(self::writes()) : DbInfo::$master;
    }

    // 获取读数据地址库组
    protected static function reads()
    {
        is_null(DbInfo::$ms) && self::writes();
        return DbInfo::$ms[self::master()];
    }

    // 获取读数据库地址
    protected static function slave()
    {
        return is_null(DbInfo::$slave) ? DbInfo::$slave = MS::rand(self::reads()) : DbInfo::$slave;
    }

    // ------------------------------------------
    //  数据库连接
    // ------------------------------------------

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
        return key_exists($address, DbInfo::$links) ? DbInfo::$links[$address] : DbInfo::$links[$address] = (function () use ($address) {
            list($host, $port) = explode(':', $address);
            try {
                $node = self::node($address);
                $link = new \PDO("mysql:host=$host", $node['du'], $node['dp']);
                DbInfo::$dbInfos[$address] = ['dbn' => $node['dbn'], 'dbn_now' => null];
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
        $link = self::dbnConnect(self::slave());
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
        $link = self::dbnConnect(self::master());
        return is_array($sqls) ? $link->prepare($sqls[0])->execute($sqls[1]) : $link->exec($sqls);
    }

    // ------------------------------------------
    //  类操作
    // ------------------------------------------

    static $dbn = false;                // 当前库名
    static $tbn = false;                // 当前表名

    // 数据库匹配;返回连接
    protected static function dbnConnect($address)
    {
        $link = self::connect($address);
        $dbInfo = DbInfo::$dbInfos[$address];
        $dbn = self::$dbn ?: $dbInfo['dbn'];
        if ($dbn !== $dbInfo['dbn_now']) {
            in_array($dbn, self::dbns($link, $dbn)) ? $link->query(" USE `$dbn` ") : Exp::end('服务器 ' . $address . ' 不存在数据库 ' . $dbn);
            DbInfo::$dbInfos[$address]['db_now'] = $dbn;
        }
        return $link;
    }

    // 获取数据库列表
    protected static function dbns($link, $address)
    {
        return key_exists($address, DbInfo::$dbns) ? DbInfo::$dbns[$address] : DbInfo::$dbns[$address] = (function () use ($link) {
            $sth = $link->query(" SHOW DATABASES ");
            $dbns = [];
            foreach (self::res($sth) as $v) {
                $dbns[] = $v->Database;
            }
            return $dbns;
        })();
    }

    // 库名及表名初始化
    protected static function names()
    {
        $class = static::class;
        $class::$tbn || (function($class) {
            if (strpos($class, 'app\\m\\') !== 0) {
                Exp::end('数据库模型'.$class.'异常');
            } else {
                $arr = array_reverse(explode('\\', substr($class, 6)));
                $count = count($arr);
                if ($count === 0) {
                    Exp::end('数据库模型'.$class.'异常');
                } else {
                    $class::$tbn = $arr[0];
                    $class::$dbn = $arr[1] ?? false;
                }
            }
        })($class);
        return $class;
    }

    // 获取表名
    protected static function tbn()
    {
        return self::names()::$tbn;
    }

    // 获取库名
    protected static function dbn()
    {
        return self::names()::$dbn;
    }

    // 查询表结构
    protected static function desc()
    {
        $tbn = self::tbn();
        return key_exists($tbn, DbInfo::$descs) ? DbInfo::$descs[$tbn] : DbInfo::$descs[$tbn] = (function () {
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
            $fields = is_array($field) ? '`' . implode('`,`', $field) . '`' : $field;
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