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
        if (is_null(self::$instance)) {
            self::$instance = (new self)->component();
        }
        return self::$instance;
    }

    // ----------------------------------------------------------------------
    //  服务器地址
    // ----------------------------------------------------------------------

    private $address;

    private function address($key)
    {
        $dbs = Config::data('dbs.db');
        key_exists($key, $dbs) || Csn::end('数据库配置 db 不存在 ' . $key . ' 键');
        $this->address = $dbs[$key];
        return $this;
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
    //  基础操作
    // ----------------------------------------------------------------------

    // 增删改
    function execute($sql, $bind = [])
    {
        $bool = self::modify(self::db($this->address, $this->dbn), $sql, $bind);
        $this->components->clear();
        return $bool;
    }

    // 查询
    function query($sql, $bind = [], $rArr = false)
    {
        return self::inQuery(self::db($this->address, $this->dbn), $sql, $bind, $rArr);
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
        return $this->execute($sql, $bind);
    }

    // 删
    function delete()
    {
        list($sql, $bind) = $this->deleteSql();
        return $this->execute($sql, $bind);
    }

    // 改
    function update($field = null)
    {
        list($sql, $bind) = $this->updateSql($field);
        return $this->execute($sql, $bind);
    }

    // 查多行
    function select($rArr = false)
    {
        list($sql, $bind) = $this->selectSql();
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