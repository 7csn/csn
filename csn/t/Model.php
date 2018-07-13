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
        return is_null(self::$slave) ? self::$slave = MS::rand(self::reads()) : self::$slave;
    }

    // ----------------------------------------------------------------------
    //  获取数据库地址
    // ----------------------------------------------------------------------

    final protected static function address($read = false)
    {
        return ($read && !self::getTrans()) ? self::slave() : self::master();
    }

    // ----------------------------------------------------------------------
    //  获取节点信息
    // ----------------------------------------------------------------------

    final protected static function node($address)
    {
        $links = Config::data('mysql.model.link');
        key_exists($address, $links) || Csn::end('数据库连接配置键 ' . $address . ' 不存在');
        $node = $links[$address];
        $node['dbn'] = $links['dbn'];
        $node['dth'] = key_exists('dth', $links) ? $links['dth'] : '';
        return $node;
    }

    // ----------------------------------------------------------------------
    //  表SQL封装
    // ----------------------------------------------------------------------

    // 查询
    final static function query($sql, $bind = [], $rArr = false)
    {
        $address = self::address(true);
        $link = self::setDbn($address, self::dbn($address));
        return self::inQuery($link, $sql, $bind, $rArr);
    }

    // 修改
    final static function execute($sql, $bind = [], $insert = false)
    {
        $link = self::setDbn(self::master(), self::dbn(self::master()));
        $bool = self::modify($link, $sql, $bind);
        if ($bool && $insert) $bool = $link->lastInsertId();
        return $bool;
    }

    // ----------------------------------------------------------------------
    //  库、表、字段信息
    // ----------------------------------------------------------------------

    protected static $dbn;                  // 当前库名
    protected static $tbn;                  // 当前表名
    protected static $dth;                  // 当前表前缀

    // 库名、表名、表前缀初始化
    final protected static function names($address)
    {
        $class = get_called_class();
        is_null($class::$tbn) && call_user_func(function ($class) use ($address) {
            strpos($class, 'app\\m\\') === 0 || Csn::end('数据库模型' . $class . '异常');
            ($name = substr($class, 6)) || Csn::end('数据库模型' . $class . '异常');
            $arr = array_reverse(explode('\\', $name));
            $class::$dbn = key_exists(1, $arr) ? strtolower($arr[1]) : self::dbnBase($address);
            is_null($class::$dth) && ($class::$dth = self::dthBase($address));
            $class::$tbn = $class::$dth . strtolower($arr[0]);
        }, $class);
        return $class;
    }

    // 获取表名
    final protected static function tbn($address)
    {
        $class = self::names($address);
        return $class::$tbn;
    }

    // 获取/设置库名
    final protected static function dbn($address)
    {
        $class = self::names($address);
        return $class::$dbn;
    }

    // ----------------------------------------------------------------------
    //  表操作(类)
    // ----------------------------------------------------------------------

    // 重置表结构
    final static function truncate()
    {
        return self::execute(function ($tbn) {
            return " TRUNCATE TABLE `$tbn` ";
        });
    }

    // 查询全部行
    final static function all($field = '*', $rArr = false)
    {
        $fields = is_array($field) ? '`' . implode('`,`', $field) . '`' : $field;
        $tbn = self::tbn(self::address(true));
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
        return (is_null($obj) ? (get_called_class())::instance() : $obj)->where($where)->bind($bind);
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
        $this->component();
        return true;
    }

    // ----------------------------------------------------------------------
    //  指定表
    // ----------------------------------------------------------------------

    final protected function table($address)
    {
        return $this->position(self::tbn($address), $address, self::dbn($address));
    }

    // ----------------------------------------------------------------------
    //  表操作(对象)
    // ----------------------------------------------------------------------

    // 增
    final function insert($field = null)
    {
        list($sql, $bind) = $this->table(self::master())->insertSql($field);
        return self::execute($sql, $bind, true);
    }

    // 删
    final function delete()
    {
        list($sql, $bind) = $this->table(self::master())->deleteSql();
        return self::execute($sql, $bind);
    }

    // 改
    final function update($field = null)
    {
        list($sql, $bind) = $this->table(self::master())->updateSql($field);
        return self::execute($sql, $bind);
    }

    // 查多行
    final function select($rArr = false)
    {
        list($sql, $bind) = $this->table(self::address(true))->selectSql();
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
        $desc = self::describe();
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