<?php

namespace csn;

class DbBase extends Data
{

    // ----------------------------------------------------------------------
    //  指定库名
    // ----------------------------------------------------------------------

    final protected static function db($address, $dbn = '')
    {
        $linkInfo = self::linkInfo($address);
        $link = $linkInfo['link'];
        $dbn || $dbn = $linkInfo['dbn'];
        if ($dbn !== $linkInfo['dbnNow']) {
            in_array($dbn, self::dbName($address)) || Csn::end('服务器 ' . $address . ' 不存在数据库 ' . $dbn);
            $link->query(" USE `$dbn` ");
            self::$linkInfo[$address]['dbnNow'] = $dbn;
        }
        return $link;
    }

    // ----------------------------------------------------------------------
    //  连接信息
    // ----------------------------------------------------------------------

    // 连接列表：连接、默认库、当前库
    private static $linkInfo = [];

    // 获取连接信息
    final protected static function linkInfo($address)
    {
        return key_exists($address, self::$linkInfo) ? self::$linkInfo[$address] : self::$linkInfo[$address] = call_user_func(function ($address) {
            $node = self::node($address);
            list($host, $port) = explode(':', $address);
            return ['link' => new \PDO("mysql:host=$host;port=$port", $node['du'], $node['dp']), 'dbn' => $node['dbn'], 'dbnNow' => null];
        }, $address);
    }

    // ----------------------------------------------------------------------
    //  获取节点信息
    // ----------------------------------------------------------------------

    final protected static function node($address)
    {
        $links = Config::data('dbs.link');
        key_exists($address, $links) || Csn::end('数据库连接配置键 ' . $address . ' 不存在');
        return $links[$address];
    }

    // ----------------------------------------------------------------------
    //  连接库名列表
    // ----------------------------------------------------------------------

    private static $dbNames = [];

    final protected static function dbName($address)
    {
        return key_exists($address, self::$dbNames) ? self::$dbNames[$address] : self::$dbNames[$address] = call_user_func(function ($address) {
            $dbns = [];
            $sth = self::linkInfo($address)['link']->query(" SHOW DATABASES ");
            foreach (self::res($sth) as $v) {
                $dbns[] = $v->Database;
            }
            return $dbns;
        }, $address);
    }

    // ----------------------------------------------------------------------
    //  获取表前缀
    // ----------------------------------------------------------------------

    final protected static function dth($address)
    {
        $node = self::node($address);
        return key_exists('dth', $node) ? $node['dth'] : '';
    }

    // ----------------------------------------------------------------------
    //  表结构
    // ----------------------------------------------------------------------

    private static $describe = [];

    protected static function desc($address, $dbn, $tbn)
    {
        return empty(self::$describe[$address][$dbn][$tbn]) ? self::$describe[$address][$dbn][$tbn] = call_user_func(function ($address, $dbn, $tbn) {
            $desc = new \stdClass();
            $desc->list = new \stdClass();
            $desc->primaryKey = null;
            foreach (self::inQuery(self::db($address, $dbn), function ($tbn) {
                return " DESC `$tbn` ";
            }, false, $tbn) as $v) {
                $v->Key === 'PRI' && $desc->primaryKey = $v->Field;
                $desc->list->{$v->Field} = call_user_func(function ($row) {
                    unset($row->Field);
                    return $row;
                }, $v);
            }
            return $desc;

        }, $address, $dbn, $tbn) : self::$describe[$address][$dbn . '@' . $tbn];
    }

    // ----------------------------------------------------------------------
    //  表SQL封装
    // ----------------------------------------------------------------------

    // 查询
    final protected static function inQuery($link, $sql, $bind = [], $rArr = false)
    {
        $sth = $link->prepare($sql);
        $sth->execute($bind);
        return self::res($sth, $rArr);
    }

    // 修改
    final protected static function modify($link, $sql, $bind = [])
    {
        return $link->prepare($sql)->execute($bind);
    }

    // ----------------------------------------------------------------------
    //  结果集
    // ----------------------------------------------------------------------

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

    // ----------------------------------------------------------------------
    //  表结构
    // ----------------------------------------------------------------------

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
        return is_null($primaryKey) ? Csn::end((self::db() ? '库' . self::db() : '默认库') . '中表' . self::tbn() . '主键不存在') : [$primaryKey, self::parseValue($desc->list->$primaryKey, $id)];
    }

    // ----------------------------------------------------------------------
    //  事务状态
    // ----------------------------------------------------------------------

    // 是否处于事务
    private static $transaction = false;

    // 获取事务状态
    final static function getTrans()
    {
        return self::$transaction;
    }

    // 修改事务状态
    final static function setTrans($status = false)
    {
        self::$transaction = $status;
    }

    // ----------------------------------------------------------------------
    //  初始化SQL因素对象
    // ----------------------------------------------------------------------

    protected $components;

    final protected function component()
    {
        $this->components = new Data();
        return $this;
    }

    // ----------------------------------------------------------------------
    //  SQL因素
    // ----------------------------------------------------------------------

    // 指定表
    function table($table, $th = null)
    {
        is_null($th) && $th = self::dth($this->address);
        $this->components->table = $th . $table;
        return $this;
    }

    // 表别名
    final function alias($alias)
    {
        $this->components->alias = $alias;
        return $this;
    }

    // 关联表
    final function join($join, $alias = null, $type = 'inner')
    {
        is_null($this->components->join) ? $this->components->join = [[strtoupper($type), $join, $alias]] : $this->components->join[] = [strtoupper($type), $join, $alias];
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
        empty($on) || $this->components->on = $on;
        return $this;
    }

    // 条件
    final function where($where, $bind = null)
    {
        empty($where) || ($this->bind($bind)->components->where = $where);
        return $this;
    }

    // 条件(进一步筛选)
    final function having($having, $bind = null)
    {
        empty($having) || ($this->bind($bind)->components->having = $having);
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
        empty($field) || ($this->bind($bind)->components->field = $field);
        return $this;
    }

    // 归类
    final function group($group)
    {
        $this->components->group = $group;
        return $this;
    }

    // 顺序
    final function order($order)
    {
        $this->components->order = $order;
        return $this;
    }

    // 限制
    final function limit($from, $num = null)
    {
        if (is_null($num)) {
            $num = $from;
            $from = 0;
        }
        $this->components->limit = [$from, $num];
        return $this;
    }

    // ----------------------------------------------------------------------
    //  获取SQL
    // ----------------------------------------------------------------------

    // 增
    final function insertSql($field = null)
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
        return [$sql, $bind];
    }

    // 删
    final function deleteSql()
    {
        $sql = 'DELETE FROM' . $this->parseTable() . $this->parseSql('on') . $this->parseWhere() . $this->parseSql('group') . $this->parseSql('order') . $this->parseSql('limit');
        return [$sql, $this->components->bind];
    }

    // 改
    final function updateSql($field = null)
    {
        $this->field($field);
        $bind = $this->components->bind;
        $set = [];
        $tables = $this->parseTable();
        foreach (current($this->parseField()) as $k => $v) {
            $set[] = $k . ' = :' . $k . '__';
            $bind[$k . '__'] = is_array($v) ? serialize($v) : $v;
        }
        $sql = 'UPDATE' . $tables . $this->parseSql('on') . ' SET ' . implode(',', $set) . $this->parseWhere() . $this->parseSql('group') . $this->parseSql('order') . $this->parseSql('limit');
        return [$sql, $bind];
    }

    // 查
    final function selectSql()
    {
        $sql = 'SELECT' . $this->parseSql('field') . ' FROM' . $this->parseTable() . $this->parseSql('on') . $this->parseWhere() . $this->parseHaving() . $this->parseSql('group') . $this->parseSql('order') . $this->parseSql('limit');
        return [$sql, $this->components->bind];
    }

    // ----------------------------------------------------------------------
    //  SQL因素处理
    // ----------------------------------------------------------------------

    // 表处理
    final protected function parseTable()
    {
        $tbs = " `{$this->components->table}`" . ($this->components->alias ? " as `{$this->components->alias}`" : "");
        $tbArr = [$this->components->table];
        $joins = $this->components->join;
        if (is_array($joins)) {
            foreach ($joins as $join) {
                $tbs .= " {$join[0]} JOIN `{$join[1]}`" . (is_null($join[2]) ? '' : " as `{$join[2]}`");
                $tbArr[] = $join[1];
            }
        }
        $this->components->table = $tbArr;
        return $tbs;
    }

    // 字段数组处理
    final protected function parseField()
    {
        // 获取所有表结构
        $tbInfos = [];
        foreach ($this->components->table as $v) {
            $tbInfos[] = self::desc($v);
        }
        // 二维字段数组
        $fieldArr = [];
        $fields = is_array(current($field = $this->components->field)) ? $field : [$field];
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

    // 条件数组处理
    final protected function parseWhere()
    {
        return ($where = $this->components->where) ? ' WHERE ' . (is_array($where) ? implode(' ', $where) : $where) : '';
    }

    // 二次筛选条件数组处理
    final protected function parseHaving()
    {
        return ($having = $this->components->having) ? ' HAVING ' . (is_array($having) ? implode(' ', $having) : $having) : '';
    }

    // 获取指定部分SQL语句
    final protected function parseSql($key)
    {
        $val = $this->components->$key;
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