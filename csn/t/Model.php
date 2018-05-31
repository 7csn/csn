<?php

namespace csn\t;

class Model extends Data
{

    // ----------------------------------------------------------------------
    //  随机主从数据库地址
    // ----------------------------------------------------------------------

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

    // ----------------------------------------------------------------------
    //  数据库连接
    // ----------------------------------------------------------------------

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
                $link = new \PDO("mysql:host=$host;port=$port", $node['du'], $node['dp']);
                DbInfo::$dbInfos[$address] = ['dbn' => $node['dbn'], 'dbn_now' => null];
            } catch (\PDOException $e) {
                Exp::end('[PDO]：' . str_replace("\n", '', iconv("GB2312// IGNORE", "UTF-8", $e->getMessage())));
            }
            return $link;
        })();
    }

    // ----------------------------------------------------------------------
    //  表SQL封装
    // ----------------------------------------------------------------------

    // SQL语句
    protected static $sqls;

    // 查询
    static function query($func, $rArr = false, $tbn = null)
    {
        $sqls = call_user_func($func, is_null($tbn) ? self::tbn() : $tbn);
        self::$sqls = $sqls;
        $address = DbInfo::getTransaction() ? self::master() : self::slave();
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
    static function execute($func)
    {
        $sqls = call_user_func($func, self::tbn());
        $link = self::dbnConnect(self::master());
        self::$sqls = $sqls;
        return is_array($sqls) ? $link->prepare($sqls[0])->execute($sqls[1]) : $link->exec($sqls);
    }

    // 获取SQL语句
    static function sqls()
    {
        return self::$sqls;
    }

    // ----------------------------------------------------------------------
    //  库、表、字段信息
    // ----------------------------------------------------------------------

    protected static $tbn = false;         // 当前表名
    protected static $dbn = false;         // 当前库名

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
        $class::$tbn || (function ($class) {
            if (strpos($class, 'app\\m\\') !== 0) {
                Exp::end('数据库模型' . $class . '异常');
            } else {
                $arr = array_reverse(explode('\\', substr($class, 6)));
                $count = count($arr);
                if ($count === 0) {
                    Exp::end('数据库模型' . $class . '异常');
                } else {
                    $class::$tbn = strtolower($arr[0]);
                    $class::$dbn = key_exists(1, $arr) ? strtolower($arr[1]) : false;
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

    // 获取/设置库名
    static function dbn($dbn = null)
    {
        return is_null($dbn) ? self::names()::$dbn : self::names()::$dbn = $dbn;
    }

    // 查询表结构;参数为关联表名
    protected static function desc($tbn = null)
    {
        is_null($tbn) && $tbn = self::tbn();
        $key = self::dbn() . '@' . $tbn;
        return key_exists($key, DbInfo::$descs) ? DbInfo::$descs[$key] : DbInfo::$descs[$key] = (function ($tbn) {
            $desc = new \stdClass();
            $desc->list = new \stdClass();
            $desc->primaryKey = null;
            foreach (self::query(function ($tbn) {
                return " DESC `$tbn` ";
            }, false, $tbn) as $v) {
                $v->Key === 'PRI' && $desc->primaryKey = $v->Field;
                $desc->list->{$v->Field} = (function ($row) {
                    unset($row->Field);
                    return $row;
                })($v);
            }
            return $desc;
        })($tbn);
    }

    // 查询字段结构
    protected static function fieldStructure($field)
    {
        $desc = self::desc();
        return key_exists($field, $desc) ? $desc[$field] : null;
    }

    // 字段值处理
    protected static function parseValue($structure, $val = null)
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
                is_null($val) ? 0 : floatval(intval);
                break;
            default:
                is_null($val) ? 0 : floatval($val);
                break;
        }
        return (!$val && !is_null($structure->Default)) ? $structure->Default : $val;
    }

    // 主键及对象锁定
    protected static function primaryKey($id)
    {
        $desc = self::desc();
        $primaryKey = $desc->primaryKey;
        return is_null($primaryKey) ? Exp::end((self::dbn() ? '库' . self::dbn() : '默认库') . '中表' . self::tbn() . '主键不存在') : [$primaryKey, self::parseValue($desc->list->$primaryKey, $id)];
    }

    // ----------------------------------------------------------------------
    //  表操作(类)
    // ----------------------------------------------------------------------

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

    // 删除指定行
    static function destroy($id)
    {
        list($primaryKey, $id) = self::primaryKey($id);
        return self::execute(function ($tbn) use ($primaryKey, $id) {
            return [" DELETE FROM `$tbn` WHERE `$primaryKey` = :id ", [':id' => $id]];
        });
    }

    // 事务
    static function transaction($func)
    {
        $link = self::connect(self::master());
        $link->beginTransaction();
        DbInfo::setTransaction(true);
        $link->{($res = call_user_func($func, $link)) ? 'rollBack' : 'commit'}();
        DbInfo::setTransaction();
        return $res;
    }

    // ----------------------------------------------------------------------
    //  静态指定对象
    // ----------------------------------------------------------------------

    // 条件
    static function which($where, $bind = null, $obj = null)
    {
        return (is_null($obj) || !($obj instanceof self)) ? new self($where, $bind) : $obj->where($where)->bind($bind);
    }

    static function assign($id, $obj = null)
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
    protected function parse()
    {
        $this->parse = new Data();
        return $this;
    }

    // 表别名
    function alias($alias)
    {
        $this->parse->alias = $alias;
        return $this;
    }

    // 关联表
    function join($join, $alias = null, $type = 'inner')
    {
        is_null($this->parse->join) ? $this->parse->join = [[strtoupper($type), $join, $alias]] : $this->parse->join[] = [strtoupper($type), $join, $alias];
        return $this;
    }

    // 左关联
    function leftJoin($join, $alias = null)
    {
        return $this->join($join, $alias, 'left');
    }

    // 内联
    function innerJoin($join, $alias = null)
    {
        return $this->join($join, $alias, 'inner');
    }

    // 右关联
    function rightJoin($join, $alias = null)
    {
        return $this->join($join, $alias, 'right');
    }

    // 条件
    function where($where, $bind = null)
    {
        empty($where) || ($this->bind($bind)->parse->where = $where);
        return $this;
    }

    // 预编译
    function bind($bind)
    {
        empty($bind) || (function ($obj, $bind) {
            $obj->parse->bind = is_null($b = $obj->parse->bind) ? $bind : array_merge($b, $bind);
        })($this, $bind);
        return $this;
    }

    // 字段
    function field($field, $bind = null)
    {
        empty($field) || ($this->bind($bind)->parse->field = $field);
        return $this;
    }

    // 归类
    function group($group)
    {
        $this->parse->group = $group;
        return $this;
    }

    // 顺序
    function order($order)
    {
        $this->parse->order = $order;
        return $this;
    }

    // 限制
    function limit($from, $num = null)
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
    function insert($field = null)
    {
        $this->field($field);
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
        $sql = 'INSERT INTO' . $this->parseTable() . ' (`' . implode('`,`', keys(current($fields))) . '`) VALUES ' . rtrim($values, ',');
        return self::execute(function () use ($sql, $bind) {
            return is_null($bind) ? $sql : [$sql, $bind];
        });
    }

    // 删
    function delete()
    {
        $sql = 'DELETE FROM' . $this->parseTable() . $this->parseWhere() . $this->parseSql('group') . $this->parseSql('order') . $this->parseSql('limit');
        $bind = $this->parse->bind;
        return self::execute(function () use ($sql, $bind) {
            return is_null($bind) ? $sql : [$sql, $bind];
        });
    }

    // 改
    function update($field = null)
    {
        $this->field($field);
        $bind = $this->parse->bind;
        $set = [];
        foreach (current($this->parseField()) as $k => $v) {
            $set[] = $k . ' = :' . $k . '__';
            $bind[$k . '__'] = is_array($v) ? serialize($v) : $v;
        }
        $sql = 'UPDATE' . $this->parseTable() . ' SET ' . implode(',', $set) . $this->parseWhere() . $this->parseSql('group') . $this->parseSql('order') . $this->parseSql('limit');
        return self::execute(function () use ($sql, $bind) {
            return is_null($bind) ? $sql : [$sql, $bind];
        });
    }

    // 查多行
    function select($type = \PDO::FETCH_OBJ)
    {
        $sql = 'SELECT' . $this->parseSql('field') . ' FROM' . $this->parseTable() . $this->parseWhere() . $this->parseSql('group') . $this->parseSql('order') . $this->parseSql('limit');
        $bind = $this->parse->bind;
        $arr = self::query(function () use ($sql, $bind) {
            return is_null($bind) ? $sql : [$sql, $bind];
        }, $type);
        return $arr;
    }

    // 查单行
    function find($type = \PDO::FETCH_OBJ)
    {
        $limit = $this->parse->limit;
        $this->parse->limit = is_null($limit) ? 1 : [$limit[0], 1];
        $rm = new \ReflectionMethod($this, 'select');
        $res = $rm->invokeArgs($this, func_get_args());
        return current($res) ?: [];
    }

    // 查单字段值
    function one($field = null)
    {
        is_null($field) || $this->field($field);
        $find = $this->find(\PDO::FETCH_OBJ);
        return is_null($field) ? current($find) ?: null : (key_exists($field, $find) ? $find[$field] : null);
    }

    // ----------------------------------------------------------------------
    //  表操作(AR增强)
    // ----------------------------------------------------------------------

    // 收集表单数据
    function create()
    {
        $desc = self::desc();
        $primaryKey = $desc->primaryKey;
        foreach ($desc->list as $k => $v) {
            if ($k === $primaryKey) continue;
            $this->$k = key_exists($k, $_REQUEST) ? null : $_REQUEST[$k];
        }
    }

    // 增加行
    function add($data = null)
    {
        is_null($data) && $data = $this->data;
        return $this->insert($data);
    }

    // 修改行
    function save($id = null)
    {
        is_null($id) || self::assign($id, $this);
        return $this->update($this->data);
    }

    // ----------------------------------------------------------------------
    //  指定条件对象处理
    // ----------------------------------------------------------------------

    // 表处理
    protected function parseTable()
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
    protected function parseWhere()
    {
        return ($where = $this->parse->where) ? ' WHERE ' . (is_array($where) ? implode(' ', $where) : $where) : '';
    }

    // 字段数组处理
    protected function parseField()
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
                    $v->Extra === 'auto_increment' || key_exists($k, $field) && $arr[$k] = self::parseValue($field[$k]);
                }
            }
            $fieldArr[] = $arr;
        }
        return $fieldArr;
    }

    // 获取指定部分SQL语句
    protected function parseSql($key)
    {
        $val = $this->parse->$key;
        if ($val) {
            switch ($key) {
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