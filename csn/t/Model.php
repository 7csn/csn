<?php

namespace csn;

abstract class Model extends DbBase
{

    // ----------------------------------------------------------------------
    //  随机主从数据库地址
    // ----------------------------------------------------------------------

    // 写数据库地址数组
    private static $ws;

    // 写读数据库地址关联数组
    private static $ms;

    // 获取写数据地址库组
    final protected static function writes()
    {
        if (is_null(self::$ws)) {
            list(self::$ws, self::$ms) = MS::init(Config::data('mysql.model.nodes'));
        }
        return self::$ws;
    }

    // 获取读数据地址库组
    final protected static function reads()
    {
        is_null(self::$ms) && self::writes();
        return self::$ms[self::master()];
    }

    // 写数据库地址
    private static $master;

    // 获取写数据库地址
    final protected static function master()
    {
        return is_null(self::$master) ? self::$master = MS::rand(self::writes()) : self::$master;
    }

    // 读数据库地址
    private static $slave;

    // 获取读数据库地址
    final protected static function slave()
    {
        return self::getTrans() ? self::master() : (is_null(self::$slave) ? self::$slave = MS::rand(self::reads()) : self::$slave);
    }

    // ----------------------------------------------------------------------
    //  获取节点信息
    // ----------------------------------------------------------------------

    final protected static function node($address)
    {
        $links = Config::data('mysql.model.link');
        key_exists($address, $links) || Csn::end('数据库连接配置键 ' . $address . ' 不存在');
        $node = $links[$address];
        $node['dbn'] = self::dbn();
        return $node;
    }

    // ----------------------------------------------------------------------
    //  库、表、字段信息
    // ----------------------------------------------------------------------

    // 库表名数组
    protected static $names;

    // 初始化库表名
    final protected static function names()
    {
        $class = get_called_class();
        if (is_null($class::$names)) {
            strpos($class, 'app\\m\\') === 0 || Csn::end('数据库模型' . $class . '异常');
            ($name = substr($class, 6)) || Csn::end('数据库模型' . $class . '异常');
            $arr = array_reverse(explode('\\', $name));
            $class::$names = ['dbn' => key_exists(1, $arr) ? strtolower($arr[1]) : Config::data('mysql.model.dbn'), 'tbn' => $class::$dth . strtolower($arr[0])];
        }
        return $class::$names;
    }

    // 获取库名
    final protected static function dbn()
    {
        return self::names()['dbn'];
    }

    // 获取表名
    final protected static function tbn()
    {
        return self::names()['tbn'];
    }

    // 表前缀
    protected static $dth = '';

    // 获取表前缀
    final protected static function dth()
    {
        $class = get_called_class();
        return $class::$dth;
    }

    // 表结构
    private static $desc = [];

    // 获取表结构
    final protected static function desc($tbn)
    {
        $dbn = self::dbn();
        return empty(self::$desc[$dbn][$tbn]) ? self::describe(self::slave(), $dbn, $tbn) : self::$desc[$dbn][$tbn];
    }

    // 主键及对象锁定
    final protected static function primaryKey($id)
    {
        $desc = self::desc(self::tbn());
        $primaryKey = $desc->primaryKey;
        return is_null($primaryKey) ? Csn::end('库 ' . self::dbn() . ' 中表 ' . self::tbn() . ' 主键不存在') : [$primaryKey, self::parseValue($desc->list->$primaryKey, $id)];
    }

    // ----------------------------------------------------------------------
    //  表SQL封装
    // ----------------------------------------------------------------------

    // 查询
    final static function query($sql, $bind = [], $rArr = false)
    {
        $link = self::setDbn(self::slave(), self::dbn());
        return self::inQuery($link, $sql, $bind, $rArr);
    }

    // 修改
    final static function execute($sql, $bind = [], $insert = false)
    {
        $link = self::setDbn(self::master(), self::dbn());
        return self::modify($link, $sql, $bind, $insert);
    }

    // ----------------------------------------------------------------------
    //  表操作(类)
    // ----------------------------------------------------------------------

    // 重置表结构
    final static function truncate()
    {
        $tbn = self::tbn();
        return self::execute(" TRUNCATE TABLE `$tbn` ");
    }

    // 查询全部行
    final static function all($field = '*', $rArr = false)
    {
        $fields = is_array($field) ? '`' . implode('`,`', $field) . '`' : $field;
        $tbn = self::tbn(self::slave());
        return self::query(" SELECT $fields FROM `$tbn` ", [], $rArr);
    }

    // 删除指定行
    final static function destroy($id)
    {
        list($primaryKey, $id) = self::primaryKey($id);
        $tbn = self::tbn(self::master());
        return self::execute(" DELETE FROM `$tbn` WHERE `$primaryKey` = :id ", [':id' => $id]);
    }

    // 事务
    final static function transaction($func)
    {
        $link = self::linkInfo(self::master());
        $link->beginTransaction();
        self::setTrans(true);
        echo '1111111';
        $link->{($res = call_user_func($func, $link)) ? 'rollBack' : 'commit'}();
        self::setTrans();
        echo '2222222';
        return $res;
    }

    // ----------------------------------------------------------------------
    //  静态指定对象
    // ----------------------------------------------------------------------

    // 条件
    final static function which($where, $bind = null, $obj = null)
    {
        $class = get_called_class();
        return is_null($obj) ? $class::instance()->where($where)->bind($bind) : $obj->where($where)->bind($bind);
    }

    // 主键
    final static function assign($id, $obj = null)
    {
        list($primaryKey, $id) = self::primaryKey($id);
        return self::which(" `$primaryKey` = :id ", [':id' => $id], $obj);
    }

    // ----------------------------------------------------------------------
    //  单例对象
    // ----------------------------------------------------------------------

    function construct()
    {
        $this->component()->position(self::tbn(), self::dth());
        return self::single();
    }

    // ----------------------------------------------------------------------
    //  表操作(对象)
    // ----------------------------------------------------------------------

    // 增
    final function insert($field = null)
    {
        list($sql, $bind) = $this->insertSql($field);
        return self::execute($sql, $bind, true);
    }

    // 删
    final function delete()
    {
        list($sql, $bind) = $this->deleteSql();
        return self::execute($sql, $bind);
    }

    // 改
    final function update($field = null)
    {
        list($sql, $bind) = $this->updateSql($field);
        return self::execute($sql, $bind);
    }

    // 查多行
    final function select($rArr = false)
    {
        list($sql, $bind) = $this->selectSql();
        return self::query($sql, $bind, $rArr);
    }

    // 查单行
    final function find($rArr = false)
    {
        $limit = $this->components->limit;
        $this->components->limit = is_null($limit) ? 1 : [$limit[0], 1];
        $rm = new \ReflectionMethod($this, 'select');
        $res = $rm->invokeArgs($this, [$rArr]);
        return current($res) ?: [];
    }

    // 查单字段值
    final function one($field = null)
    {
        is_null($field) || $this->field($field);
        $find = $this->find(\PDO::FETCH_OBJ);
        return current($find) ?: null;
    }

    // ----------------------------------------------------------------------
    //  表操作(AR增强)
    // ----------------------------------------------------------------------

    // 收集表单数据
    final function collect()
    {
        $desc = self::desc(self::tbn());
        $primaryKey = $desc->primaryKey;
        foreach ($desc->list as $k => $v) {
            if ($k === $primaryKey) continue;
            $this->$k = key_exists($k, $_REQUEST) ? null : $_REQUEST[$k];
        }
    }

    // 增加行
    final function add($data = null)
    {
        is_null($data) && $data = $this->data;
        return $this->insert($data);
    }

    // 修改行
    final function save($id = null)
    {
        is_null($id) || self::assign($id, $this);
        return $this->update($this->data);
    }

}