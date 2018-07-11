<?php

namespace csn;

class Model extends DbBase
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
            list(self::$ws, self::$ms) = MS::init(Config::data('dbs.model.nodes'));
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
    //  表SQL封装
    // ----------------------------------------------------------------------

    // SQL语句
    private static $sqls;

    // 查询
    final static function query($func, $rArr = false, $tbn = null)
    {
        $sqls = call_user_func($func, is_null($tbn) ? self::tbn() : $tbn);
        if (is_array($sqls)) {
            list($sql, $bind) = $sqls;
        } else {
            $sql = $sqls;
            $bind = [];
        }
        return self::inQuery(self::db(self::getTrans() ? self::master() : self::slave(), self::dbn()), $sql, $bind, $rArr);
    }

    // 修改
    final static function execute($func)
    {
        $sqls = call_user_func($func, self::tbn());
        if (is_array($sqls)) {
            list($sql, $bind) = $sqls;
        } else {
            $sql = $sqls;
            $bind = [];
        }
        return self::modify(self::db(self::master(), self::dbn()), $sql, $bind);
    }

    // 获取SQL语句
    final static function sqls()
    {
        return self::$sqls;
    }

    // ----------------------------------------------------------------------
    //  库、表、字段信息
    // ----------------------------------------------------------------------

    protected static $tbn;                  // 当前表名
    protected static $dbn;                  // 当前库名
    protected static $dth;                  // 当前表前缀

    // 库名、表名、表前缀初始化
    final protected static function names()
    {
        $class = get_called_class();
        is_null($class::$tbn) && call_user_func(function ($class) {
            strpos($class, 'app\\m\\') === 0 || Csn::end('数据库模型' . $class . '异常');
            $arr = array_reverse(explode('\\', substr($class, 6)));
            $count = count($arr);
            $count === 0 && Csn::end('数据库模型' . $class . '异常');
            $class::$dbn = key_exists(1, $arr) ? strtolower($arr[1]) : self::dn(self::slave());
            is_null($class::$dth) && ($class::$dth = self::dth(self::slave()));
            $class::$tbn = $class::$dth . strtolower($arr[0]);
        }, $class);
        return $class;
    }

    // 获取表名
    final protected static function tbn()
    {
        $class = self::names();
        return $class::$tbn;
    }

    // 获取表前缀
    final protected static function th()
    {
        $class = self::names();
        return $class::$dth;
    }

    // 获取/设置库名
    final static function dbn($dbn = null)
    {
        $class = self::names();
        return is_null($dbn) ? $class::$dbn : $class::$dbn = $dbn;
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
        return self::query(function ($tbn) use ($field) {
            $fields = is_array($field) ? '`' . implode('`,`', $field) . '`' : $field;
            return " SELECT $fields FROM `$tbn` ";
        }, $rArr);
    }

    // 删除指定行
    final static function destroy($id)
    {
        list($primaryKey, $id) = self::primaryKey($id);
        return self::execute(function ($tbn) use ($primaryKey, $id) {
            return [" DELETE FROM `$tbn` WHERE `$primaryKey` = :id ", [':id' => $id]];
        });
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
        return (is_null($obj) || !($obj instanceof self)) ? new self($where, $bind) : $obj->where($where)->bind($bind);
    }

    // 主键
    final static function assign($id, $obj = null)
    {
        list($primaryKey, $id) = self::primaryKey($id);
        return self::which(" `$primaryKey` = :id ", [':id' => $id], $obj);
    }

    // ----------------------------------------------------------------------
    //  对象配置
    // ----------------------------------------------------------------------

    // 创建对象
    function __construct($where = null, $bind = null)
    {
        $this->component()->where($where)->bind($bind);
    }

    // ----------------------------------------------------------------------
    //  指定表
    // ----------------------------------------------------------------------

    function table($table, $address)
    {
        return $this->tb($table, self::dth($address))->position($address, self::th());
        return $this;
    }

    // ----------------------------------------------------------------------
    //  表操作(对象)
    // ----------------------------------------------------------------------

    // 增
    final function insert($field = null)
    {
        list($sql, $bind) = $this->table(self::tbn(), self::master())->insertSql($field);
        return self::execute(function () use ($sql, $bind) {
            return is_null($bind) ? $sql : [$sql, $bind];
        });
    }

    // 删
    final function delete()
    {
        list($sql, $bind) = $this->table(self::tbn(), self::master())->deleteSql();
        return self::execute(function () use ($sql, $bind) {
            return is_null($bind) ? $sql : [$sql, $bind];
        });
    }

    // 改
    final function update($field = null)
    {
        list($sql, $bind) = $this->table(self::tbn(), self::master())->updateSql($field);
        return self::execute(function () use ($sql, $bind) {
            return is_null($bind) ? $sql : [$sql, $bind];
        });
    }

    // 查多行
    final function select($rArr = false)
    {
        list($sql, $bind) = $this->table(self::tbn(), self::getTrans() ? self::master() : self::slave())->updateSql();
        $arr = self::query(function () use ($sql, $bind) {
            return is_null($bind) ? $sql : [$sql, $bind];
        }, $rArr);
        return $arr;
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
        return is_null($field) ? current($find) ?: null : (key_exists($field, $find) ? $find[$field] : null);
    }

    // ----------------------------------------------------------------------
    //  表操作(AR增强)
    // ----------------------------------------------------------------------

    // 收集表单数据
    final function create()
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