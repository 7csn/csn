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
    protected static $dbn;          // 当前数据库

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

    // ------------------------------------------
    //  增删改查封装
    // ------------------------------------------

    // 查询封装
    protected static function query($func, $style = \PDO::FETCH_OBJ)
    {
        $sqls = call_user_func($func, self::tbname());
        $link = self::connect(self::slave());
        if (is_array($sqls)) {
            $sth = $link->prepare($sqls[0]);
            $sth->execute($sqls[1]);
        } else {
            $sth = $link->query($sqls);
        }
        $sth->setFetchMode($style);
        $res = [];
        while ($v = $sth->fetch()) {
            $res[] = $v;
        }
        $sth = null;
        return $res;
    }

    // 修改封装
    protected static function execute($func)
    {
        $sqls = call_user_func($func, self::tbname());
        $link = self::connect(self::master());
        return is_array($sqls) ? $link->prepare($sqls[0])->execute($sqls[1]) : $link->exec($sqls);
    }

    // ------------------------------------------
    //  数据库操作
    // ------------------------------------------

    protected static $desc = [];    // 数据结构

    // 获取表名
    protected static function tbname()
    {
        return substr(strrchr(static::class, '\\'), 1);
    }

    // 查询表结构
    static function desc()
    {
        $tbn = self::tbname();
        return key_exists($tbn, self::$desc) ? self::$desc[$tbn] : self::$desc[$tbn] = (function () {
            $desc = new \stdClass();
            foreach (self::query(function ($tbn) {
                return " DESC `$tbn`; ";
            }) as $v) {
                $desc->{$v->Field} = (function($row){
                    unset($row->Field);
                    return $row;
                })($v);
            }
            return $desc;
        })();
    }

    // 重置表结构
    static function truncate()
    {
        return self::execute(function ($tbn) {
            return " TRUNCATE TABLE `$tbn`; ";
        });
    }

    // 查询全部行
    static function all($field = '*')
    {
        return self::query(function ($tbn) use ($field) {
            $fields = '`' . (is_array($field) ? implode('`,`', $field) : $field) . '`';
            return " SELECT $fields FROM `$tbn`; ";
        });
    }

    // 条件
    static function where($where)
    {
    }

    // ------------------------------------------
    //  对象
    // ------------------------------------------

    protected $data = [];           // 对象属性

    // 设置字段值
    function __set($key, $value)
    {
        $this->data[$key] = $value;
    }

    // 获取字段值
    public function __get($key)
    {
        return key_exists($key, $this->data) ? $this->data[$key] : null;
    }




//
//    // 查询
//    static function find()
//    {
//        return self::name();
//    }

}