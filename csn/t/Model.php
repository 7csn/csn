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
            list(self::$ws, self::$ms) = MS::init(Config::data('model.nodes'));
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
    //  数据库连接
    // ----------------------------------------------------------------------

    // 获取数据库相关信息
    final protected static function node($address)
    {
        $link = Config::data('model.link');
        if (key_exists($address, $link)) {
            return $link[$address];
        } else {
            Csn::end('数据库' . $address . '连接信息不存在');
        }
    }

    // 数据库连接
    final protected static function connect($address)
    {
        return key_exists($address, self::$links) ? self::$links[$address] : self::$links[$address] = call_user_func(function ($address) {
            list($host, $port) = explode(':', $address);
            $node = self::node($address);
            $link = new \PDO("mysql:host=$host;port=$port", $node['du'], $node['dp']);
            self::$dbInfos[$address] = ['dbn' => $node['dbn'], 'dbn_now' => null];
            return $link;
        }, $address);
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
        self::$sqls = $sqls;
        $address = self::getTrans() ? self::master() : self::slave();
        $link = self::dbnConnect($address);
        if (is_array($sqls)) {
            $sth = $link->prepare($sqls[0]);
            $sth->execute($sqls[1]);
        } else {
            $sth = $link->query($sqls);
        }
        return self::res($sth, $rArr);
    }

    // 结果集
    final protected static function res(&$sth, $rArr = false)
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
    final static function execute($func)
    {
        $sqls = call_user_func($func, self::tbn());
        $link = self::dbnConnect(self::master());
        self::$sqls = $sqls;
        return is_array($sqls) ? $link->prepare($sqls[0])->execute($sqls[1]) : $link->exec($sqls);
    }

    // 获取SQL语句
    final static function sqls()
    {
        return self::$sqls;
    }

    // ----------------------------------------------------------------------
    //  库、表、字段信息
    // ----------------------------------------------------------------------

    private static $tbn = false;         // 当前表名
    private static $dbn = false;         // 当前库名

    // 数据库匹配;返回连接
    final protected static function dbnConnect($address)
    {
        $link = self::connect($address);
        $dbInfo = self::$dbInfos[$address];
        $dbn = self::$dbn ?: $dbInfo['dbn'];
        if ($dbn !== $dbInfo['dbn_now']) {
            in_array($dbn, self::dbns($link, $dbn)) ? $link->query(" USE `$dbn` ") : Csn::end('服务器 ' . $address . ' 不存在数据库 ' . $dbn);
            self::$dbInfos[$address]['db_now'] = $dbn;
        }
        return $link;
    }

    // 获取数据库列表
    final protected static function dbns($link, $address)
    {
        return key_exists($address, self::$dbns) ? self::$dbns[$address] : self::$dbns[$address] = call_user_func(function ($link) {
            $sth = $link->query(" SHOW DATABASES ");
            $dbns = [];
            foreach (self::res($sth) as $v) {
                $dbns[] = $v->Database;
            }
            return $dbns;
        }, $link);
    }

    // 库名及表名初始化
    final protected static function names()
    {
        $class = get_called_class();
        $class::$tbn || call_user_func(function ($class) {
            if (strpos($class, 'app\\m\\') === 0) {
                $arr = array_reverse(explode('\\', substr($class, 6)));
                $count = count($arr);
                if ($count === 0) {
                    Csn::end('数据库模型' . $class . '异常');
                } else {
                    $class::$tbn = strtolower($arr[0]);
                    $class::$dbn = key_exists(1, $arr) ? strtolower($arr[1]) : false;
                }
            } else {
                Csn::end('数据库模型' . $class . '异常');
            }
        }, $class);
        return $class;
    }

    // 获取表名
    final protected static function tbn()
    {
        $class = self::names();
        return $class::$tbn;
    }

    // 获取/设置库名
    final static function dbn($dbn = null)
    {
        $class = self::names();
        return is_null($dbn) ? $class::$dbn : $class::$dbn = $dbn;
    }

    // 查询表结构;参数为关联表名
    final protected static function desc($tbn = null)
    {
        is_null($tbn) && $tbn = self::tbn();
        $key = self::dbn() . '@' . $tbn;
        return key_exists($key, self::$descs) ? self::$descs[$key] : self::$descs[$key] = call_user_func(function ($tbn) {
            $desc = new \stdClass();
            $desc->list = new \stdClass();
            $desc->primaryKey = null;
            foreach (self::query(function ($tbn) {
                return " DESC `$tbn` ";
            }, false, $tbn) as $v) {
                $v->Key === 'PRI' && $desc->primaryKey = $v->Field;
                $desc->list->{$v->Field} = call_user_func(function ($row) {
                    unset($row->Field);
                    return $row;
                }, $v);
            }
            return $desc;
        }, $tbn);
    }

    // 查询字段结构
    final protected static function fieldStructure($field)
    {
        $desc = self::desc();
        return key_exists($field, $desc) ? $desc[$field] : null;
    }

    // 字段值处理
    final protected static function parseValue($structure, $val = null)
    {
        switch (explode('(', $structure->Type)[0]) {
            case 'char':
            case 'varchar':
            case 'tinytext':
            case 'longtext':
            case 'mediumtext':
            case 'text':
                is_null($val) ? '' : (string)$val;
                break;
            case 'int':
                is_null($val) ? 0 : floatval($val);
                break;
            default:
                is_null($val) ? 0 : floatval($val);
                break;
        }
        return (!$val && !is_null($structure->Default)) ? $structure->Default : $val;
    }

    // 主键及对象锁定
    final protected static function primaryKey($id)
    {
        $desc = self::desc();
        $primaryKey = $desc->primaryKey;
        return is_null($primaryKey) ? Csn::end((self::dbn() ? '库' . self::dbn() : '默认库') . '中表' . self::tbn() . '主键不存在') : [$primaryKey, self::parseValue($desc->list->$primaryKey, $id)];
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
        $link = self::connect(self::master());
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
        $this->parse()->where($where)->bind($bind);
    }

    // 指定条件对象
    final protected function parse()
    {
        $this->parse = new Data();
        return $this;
    }

    // 表别名
    final function alias($alias)
    {
        $this->parse->alias = $alias;
        return $this;
    }

    // 关联表
    final function join($join, $alias = null, $type = 'inner')
    {
        is_null($this->parse->join) ? $this->parse->join = [[strtoupper($type), $join, $alias]] : $this->parse->join[] = [strtoupper($type), $join, $alias];
        return $this;
    }

    // 左关联
    final function leftJoin($join, $alias = null)
    {
        return $this->join($join, $alias, 'left');
    }

    // 内联
    final function innerJoin($join, $alias = null)
    {
        return $this->join($join, $alias, 'inner');
    }

    // 右关联
    final function rightJoin($join, $alias = null)
    {
        return $this->join($join, $alias, 'right');
    }

    // 关联条件
    final function on($on)
    {
        empty($on) || $this->parse->field = $on;
        return $this;
    }

    // 条件
    final function where($where, $bind = null)
    {
        empty($where) || ($this->bind($bind)->parse->where = $where);
        return $this;
    }

    // 条件(进一步筛选)
    final function having($having, $bind = null)
    {
        empty($having) || ($this->bind($bind)->parse->having = $having);
        return $this;
    }

    // 预编译
    final function bind($bind)
    {
        empty($bind) || call_user_func(function ($obj, $bind) {
            $obj->parse->bind = is_null($b = $obj->parse->bind) ? $bind : array_merge($b, $bind);
        }, $this, $bind);
        return $this;
    }

    // 字段
    final function field($field, $bind = null)
    {
        empty($field) || ($this->bind($bind)->parse->field = $field);
        return $this;
    }

    // 归类
    final function group($group)
    {
        $this->parse->group = $group;
        return $this;
    }

    // 顺序
    final function order($order)
    {
        $this->parse->order = $order;
        return $this;
    }

    // 限制
    final function limit($from, $num = null)
    {
        if (is_null($num)) {
            $num = $from;
            $from = 0;
        }
        $this->parse->limit = [$from, $num];
        return $this;
    }

    // ----------------------------------------------------------------------
    //  表操作(对象)
    // ----------------------------------------------------------------------

    // 增
    final function insert($field = null)
    {
        $this->field($field);
        $tables = $this->parseTable();
        $fields = $this->parseField();
        $values = '';
        $bind = [];
        foreach ($fields as $k => $v) {
            $value = '';
            foreach ($v as $kk => $vv) {
                $value .= ':' . $kk . '__' . $k . ',';
                $bind[$kk . '__' . $k] = is_array($vv) ? serialize($vv) : $vv;
            }
            $values .= '(' . rtrim($value, ',') . '),';
        }
        $sql = 'INSERT INTO' . $tables . ' (`' . implode('`,`', array_keys($fields[0])) . '`) VALUES ' . rtrim($values, ',');
        return self::execute(function () use ($sql, $bind) {
            return is_null($bind) ? $sql : [$sql, $bind];
        });
    }

    // 删
    final function delete()
    {
        $sql = 'DELETE FROM' . $this->parseTable() . $this->parseSql('on') . $this->parseWhere() . $this->parseSql('group') . $this->parseSql('order') . $this->parseSql('limit');
        $bind = $this->parse->bind;
        return self::execute(function () use ($sql, $bind) {
            return is_null($bind) ? $sql : [$sql, $bind];
        });
    }

    // 改
    final function update($field = null)
    {
        $this->field($field);
        $bind = $this->parse->bind;
        $set = [];
        $tables = $this->parseTable();
        foreach (current($this->parseField()) as $k => $v) {
            $set[] = $k . ' = :' . $k . '__';
            $bind[$k . '__'] = is_array($v) ? serialize($v) : $v;
        }
        $sql = 'UPDATE' . $tables . $this->parseSql('on') . ' SET ' . implode(',', $set) . $this->parseWhere() . $this->parseSql('group') . $this->parseSql('order') . $this->parseSql('limit');
        return self::execute(function () use ($sql, $bind) {
            return is_null($bind) ? $sql : [$sql, $bind];
        });
    }

    // 查多行
    final function select($type = \PDO::FETCH_OBJ)
    {
        $sql = 'SELECT' . $this->parseSql('field') . ' FROM' . $this->parseTable() . $this->parseSql('on') . $this->parseWhere() . $this->parseHaving() . $this->parseSql('group') . $this->parseSql('order') . $this->parseSql('limit');
        $bind = $this->parse->bind;
        $arr = self::query(function () use ($sql, $bind) {
            return is_null($bind) ? $sql : [$sql, $bind];
        }, $type);
        return $arr;
    }

    // 查单行
    final function find($type = \PDO::FETCH_OBJ)
    {
        $limit = $this->parse->limit;
        $this->parse->limit = is_null($limit) ? 1 : [$limit[0], 1];
        $rm = new \ReflectionMethod($this, 'select');
        $res = $rm->invokeArgs($this, func_get_args());
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
        $desc = self::desc();
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

    // ----------------------------------------------------------------------
    //  指定条件对象处理
    // ----------------------------------------------------------------------

    // 表处理
    final protected function parseTable()
    {
        $tbs = ' `' . self::tbn() . '`' . ($this->parse->alias ? " as `{$this->parse->alias}`" : '');
        $tbArr = [self::tbn()];
        $joins = $this->parse->join;
        if (is_array($joins)) {
            foreach ($joins as $join) {
                $tbs .= " {$join[0]} JOIN `{$join[1]}`" . (is_null($join[2]) ? '' : " as `{$join[2]}`");
                $tbArr[] = $join[1];
            }
        }
        $this->parse->table = $tbArr;
        return $tbs;
    }

    // 条件数组处理
    final protected function parseWhere()
    {
        return ($where = $this->parse->where) ? ' WHERE ' . (is_array($where) ? implode(' ', $where) : $where) : '';
    }

    // 二次筛选条件数组处理
    final protected function parseHaving()
    {
        return ($having = $this->parse->having) ? ' HAVING ' . (is_array($having) ? implode(' ', $having) : $having) : '';
    }

    // 字段数组处理
    final protected function parseField()
    {
        // 获取所有表结构
        $tbInfos = [];
        foreach ($this->parse->table as $v) {
            $tbInfos[] = self::desc($v);
        }
        // 二维字段数组
        $fieldArr = [];
        $fields = is_array(current($field = $this->parse->field)) ? $field : [$field];
        foreach ($fields as $field) {
            $arr = [];
            foreach ($tbInfos as $tbInfo) {
                foreach ($tbInfo->list as $k => $v) {
                    $v->Extra === 'auto_increment' || key_exists($k, $field) && $arr[$k] = self::parseValue($v, $field[$k]);
                }
            }
            $fieldArr[] = $arr;
        }
        return $fieldArr;
    }

    // 获取指定部分SQL语句
    final protected function parseSql($key)
    {
        $val = $this->parse->$key;
        if ($val) {
            switch ($key) {
                case 'on':
                    $i = 'ON ';
                    break;
                case 'field':
                    $i = '';
                    break;
                case 'group':
                    $i = 'GROUP BY ';
                    break;
                case 'order':
                    $i = 'ORDER BY ';
                    break;
                case 'limit':
                    $i = 'LIMIT ';
                    break;
                default:
                    return '';
            }
            return ' ' . $i . (is_array($val) ? implode(',', $val) : $val);
        } else {
            return $key === 'field' ? ' *' : '';
        }
    }

}