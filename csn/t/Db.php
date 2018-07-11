<?php

namespace csn;

final class Db extends DbBase
{

    // ----------------------------------------------------------------------
    //  指定连接
    // ----------------------------------------------------------------------

    static function link($key)
    {
        return self::instance()->address($key);
    }

    // ----------------------------------------------------------------------
    //  单例对象
    // ----------------------------------------------------------------------

    private static $instance;

    private static function instance()
    {
        return is_null(self::$instance) ? self::$instance = (new self)->component() : self::$instance;
    }

    // ----------------------------------------------------------------------
    //  服务器地址
    // ----------------------------------------------------------------------

    private $address;

    protected function address($key = null)
    {
        return is_null($key) ? $this->address : call_user_func(function($obj) use ($key) {
            $dbs = Config::data('dbs.db');
            key_exists($key, $dbs) || Csn::end('数据库配置 db 不存在 ' . $key . ' 键');
            $obj->address = $dbs[$key];
            return $obj;
        }, $this);
    }

    // ----------------------------------------------------------------------
    //  指定数据库
    // ----------------------------------------------------------------------

    private $dbn;

    function dbn($dbn)
    {
        $this->dbn = $dbn;

        return $this;
    }

    // ----------------------------------------------------------------------
    //  指定表
    // ----------------------------------------------------------------------

    function table($table, $th = null)
    {
        $dth = self::dth($this->address);
        $table = (is_null($th) ? $dth : $th) . $table;
        return $this->tb($table, $dth)->position($this->address, $this->dbn);
    }

    // ----------------------------------------------------------------------
    //  基础操作
    // ----------------------------------------------------------------------

    // 增删改
    function execute($sql, $bind = null)
    {
        Csn::dump($this->address, $this->dbn);
        $bool = self::modify(self::db($this->address, $this->dbn), $sql, $bind);
        $this->components->clear();
        return $bool;
    }

    // 查询
    function query($sql, $bind = null, $rArr = false)
    {
        $res = self::inQuery(self::db($this->address, $this->dbn), $sql, $bind, $rArr);
        $this->components->clear();
        return $res;
    }

    // ----------------------------------------------------------------------
    //  初始化表
    // ----------------------------------------------------------------------

    function truncate($table, $th = null)
    {
        is_null($th) && $th = self::dth($this->address);
        return $this->execute(" TRUNCATE TABLE `{$th}{$table}` ");
    }

    // ----------------------------------------------------------------------
    //  事务处理
    // ----------------------------------------------------------------------

    function commit()
    {
        $pdo = self::$link;
        $pdo->beginTransaction();
        DbBase::setTrans(true);
        $args = func_get_args();
        $fn = $args[0];
        $args[0] = $pdo;
        $b = call_user_func_array($fn, $args);
        $pdo->{$b ? 'rollBack' : 'commit'}();
        DbBase::setTrans();
        return $b;
    }

    // ----------------------------------------------------------------------
    //  表常规操作
    // ----------------------------------------------------------------------

    // 增
    function insert($field = null)
    {
        list($sql, $bind) = $this->insertSql($field);
        Csn::dump($sql, $bind);
        return $this->execute($sql, $bind);
    }

    // 删
    function delete()
    {
        list($sql, $bind) = $this->deleteSql();
        Csn::dump($sql, $bind);
        return $this->execute($sql, $bind);
    }

    // 改
    function update($field = null)
    {
        list($sql, $bind) = $this->updateSql($field);
//        Csn::dump($sql, $bind);
        return $this->execute($sql, $bind);
    }

    // 查多行
    function select($rArr = false)
    {
        list($sql, $bind) = $this->selectSql();
        Csn::dump($sql, $bind);
        return $this->query($sql, $bind, $rArr);
    }

    // 查单行
    function find($rArr = false)
    {
        $limit = $this->components->limit;
        $this->components->limit = is_null($limit) ? 1 : [$limit[0], 1];
        $rm = new \ReflectionMethod($this, 'select');
        $res = $rm->invokeArgs($this, [$rArr]);
        return current($res) ?: [];
    }

    // 查单字段值
    function one($field = null)
    {
        is_null($field) || $this->field($field);
        $find = $this->find();
        return is_null($field) ? current($find) ?: null : (key_exists($field, $find) ? $find[$field] : null);
    }

}