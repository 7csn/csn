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

    function construct()
    {
        return self::single();
    }

    // ----------------------------------------------------------------------
    //  服务器地址：存取
    // ----------------------------------------------------------------------

    private $address;

    protected function address($key)
    {
        $dbs = Config::data('mysql.db');
        key_exists($key, $dbs) || Csn::end('数据库配置 db 不存在 ' . $key . ' 键');
        $this->address = $dbs[$key];
        $this->dbn = self::dbnDefault($dbs[$key]);
        return $this;
    }

    // ----------------------------------------------------------------------
    //  指定数据库
    // ----------------------------------------------------------------------

    private $dbn;

    function dbn($dbn)
    {
        is_null($dbn) && $dbn = self::dbnDefault($this->address);
        $this->dbn === $dbn || $this->dbn = $dbn;
        return $this;
    }

    // ----------------------------------------------------------------------
    //  获取默认表前缀
    // ----------------------------------------------------------------------

    final protected static function dthDefault($address)
    {
        $node = self::node($address);
        return key_exists('dth', $node) ? $node['dth'] : '';
    }

    // ----------------------------------------------------------------------
    //  获取默认库名
    // ----------------------------------------------------------------------

    final protected static function dbnDefault($address)
    {
        return self::node($address)['dbn'];
    }

    // ----------------------------------------------------------------------
    //  指定表
    // ----------------------------------------------------------------------

    function table($table, $th = null)
    {
        $dth = self::dthDefault($this->address);
        $table = (is_null($th) ? $dth : $th) . $table;
        return $this->component($table, $dth);
    }

    // ----------------------------------------------------------------------
    //  基础操作
    // ----------------------------------------------------------------------

    // 增删改
    function execute($sql, $bind = null, $insert = false)
    {
        return self::modify(self::setDbn($this->address, $this->dbn), $sql, $bind, $insert);
    }

    // 查询
    function query($sql, $bind = null, $rArr = false)
    {
        return self::inQuery(self::setDbn($this->address, $this->dbn), $sql, $bind, $rArr);
    }

    // ----------------------------------------------------------------------
    //  初始化表
    // ----------------------------------------------------------------------

    function truncate($table, $th = null)
    {
        is_null($th) && $th = self::dthDefault($this->address);
        return $this->execute(" TRUNCATE TABLE `{$th}{$table}` ");
    }

    // ----------------------------------------------------------------------
    //  事务处理
    // ----------------------------------------------------------------------

    function transaction($action, $error)
    {
        return DbBase::beginTrans(self::setDbn($this->address, $this->dbn), $action, $error);
    }

    // ----------------------------------------------------------------------
    //  表常规操作
    // ----------------------------------------------------------------------

    // 增
    function insert()
    {
        list($sql, $bind) = $this->insertSql($this->address, $this->dbn);
        return $this->execute($sql, $bind, true);
    }

    // 删
    function delete()
    {
        list($sql, $bind) = $this->deleteSql($this->address, $this->dbn);
        Csn::dump($sql, $bind);
        return $this->execute($sql, $bind);
    }

    // 改
    function update()
    {
        list($sql, $bind) = $this->updateSql($this->address, $this->dbn);
        return $this->execute($sql, $bind);
    }

    // 查多行
    function select($rArr = false)
    {
        list($sql, $bind) = $this->selectSql($this->address, $this->dbn);
        return $this->query($sql, $bind, $rArr);
    }

    // 查单行
    function find($rArr = false)
    {
        $limit = $this->query->limit;
        $this->query->limit = is_null($limit) ? 1 : [$limit[0], 1];
        $rm = new \ReflectionMethod($this, 'select');
        $res = $rm->invokeArgs($this, [$rArr]);
        return current($res) ?: [];
    }

    // 查单字段值
    function one($field = null)
    {
        is_null($field) || $this->field($field);
        $find = $this->find();
        return current($find) ?: null;
    }

}