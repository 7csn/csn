<?php

namespace csn;

abstract class DbBase extends Data
{

    // ----------------------------------------------------------------------
    //  指定库名
    // ----------------------------------------------------------------------

    final protected static function setDbn($address, $dbn)
    {
        $linkInfo = self::linkInfo($address);
        $link = $linkInfo['link'];
        if ($dbn !== $linkInfo['dbn']) {
            in_array($dbn, self::dbName($address)) || Csn::end('服务器 ' . $address . ' 不存在数据库 ' . $dbn);
            $link->query(" USE `$dbn` ");
            self::$linkInfo[$address]['dbn'] = $dbn;
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
        return key_exists($address, self::$linkInfo) ? self::$linkInfo[$address] : self::$linkInfo[$address] = call_user_func(function () use ($address) {
            $class = get_called_class();
            $node = $class::node($address);
            list($host, $port) = explode(':', $address);
            return ['link' => new \PDO("mysql:host=$host;port=$port", $node['du'], $node['dp']), 'dbn' => null];
        });
    }

    // ----------------------------------------------------------------------
    //  连接库名列表
    // ----------------------------------------------------------------------

    private static $dbNames = [];

    final protected static function dbName($address)
    {
        return key_exists($address, self::$dbNames) ? self::$dbNames[$address] : self::$dbNames[$address] = call_user_func(function () use ($address) {
            $dbNames = [];
            foreach (self::inQuery(self::linkInfo($address)['link'], " SHOW DATABASES ") as $v) {
                $dbNames[] = $v->Database;
            }
            return $dbNames;
        });
    }

    // ----------------------------------------------------------------------
    //  表结构
    // ----------------------------------------------------------------------

    private static $describe = [];

    final protected static function describe($address, $dbn, $tbn)
    {
        return empty(self::$describe[$address][$dbn][$tbn]) ? self::$describe[$address][$dbn][$tbn] = call_user_func(function () use ($address, $dbn, $tbn) {
            $desc = new \stdClass();
            $desc->list = new \stdClass();
            $desc->primaryKey = null;
            foreach (self::inQuery(self::setDbn($address, $dbn), " DESC `$tbn` ") as $v) {
                $v->Key === 'PRI' && $desc->primaryKey = $v->Field;
                $desc->list->{$v->Field} = call_user_func(function ($row) {
                    unset($row->Field);
                    return $row;
                }, $v);
            }
            return $desc;
        }) : self::$describe[$address][$dbn][$tbn];
    }

    // ----------------------------------------------------------------------
    //  字段值处理
    // ----------------------------------------------------------------------

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

    // ----------------------------------------------------------------------
    //  表SQL封装：查询、增删改
    // ----------------------------------------------------------------------

    final static function inQuery($link, $sql, $bind = [], $rArr = false)
    {
        $sth = $link->prepare($sql);
        $sth->execute($bind);
        $sth->setFetchMode($rArr ? \PDO::FETCH_ASSOC : \PDO::FETCH_OBJ);
        $res = [];
        while ($v = $sth->fetch()) {
            $res[] = $v;
        }
        $sth = null;
        return $res;
    }

    final static function modify($link, $sql, $bind = [], $insert = false)
    {
        $bool = $link->prepare($sql)->execute($bind);
        return $insert ? $bool ? $link->lastInsertId() : 0 : $bool;
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
        $this->components = Data::instance();
        return $this;
    }

    // ----------------------------------------------------------------------
    //  SQL因素
    // ----------------------------------------------------------------------

    // 主表及默认表前缀
    final function position($table, $dth)
    {
        $this->components->table = $table;
        $this->components->dth = $dth;
        return $this;
    }

    // 表别名
    final function alias($alias)
    {
        $this->components->alias = $alias;
        return $this;
    }

    // 关联表
    final protected function join($table, $dth, $alias = null, $type)
    {
        $table = (is_null($dth) ? $this->components->dth : $dth) . $table;
        $join = [$type, $table, $alias];
        is_null($this->components->join) ? $this->components->join = [$join] : $this->components->join[] = $join;
        return $this;
    }

    // 左关联
    final function leftJoin($table, $alias = null, $dth = null)
    {
        return $this->join($table, $dth, $alias, 'LEFT');
    }

    // 内联
    final function innerJoin($table, $alias = null, $dth = null)
    {
        return $this->join($table, $dth, $alias, 'INNER');
    }

    // 右关联
    final function rightJoin($table, $alias = null, $dth = null)
    {
        return $this->join($table, $dth, $alias, 'RIGHT');
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
        empty($bind) || $this->components->bind = is_null($b = $this->components->bind) ? $bind : array_merge($b, $bind);
        return $this;
    }

    // 字段
    final function field($field, $bind = null)
    {
        empty($field) || ($this->bind($bind)->components->field = is_array($field) ? $field : explode(',', $field));
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
    //  获取SQL：增、删、改、查
    // ----------------------------------------------------------------------

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
        $this->components->clear();
        return [$sql, $bind];
    }

    final function deleteSql()
    {
        $sql = 'DELETE FROM' . $this->parseTable() . $this->parseSql('on') . $this->parseWhere() . $this->parseSql('group') . $this->parseSql('order') . $this->parseSql('limit');
        $bind = $this->components->bind;
        $this->components->clear();
        return [$sql, $bind];
    }

    final function updateSql($field = null)
    {
        $this->field($field);
        $bind = $this->components->bind;
        $set = [];
        $tables = $this->parseTable();
        foreach (current($this->parseField()) as $k => $v) {
            $lock = join('__', array_reverse(explode('.', $k)));
            $key = strpos($k, '.') === false ? "`$k`" : $this->unquote($k);
            $set[] = "$key = :{$lock}__";
            $bind[":{$lock}__"] = is_array($v) ? serialize($v) : $v;
        }
        $sets = implode(',', $set);
        $sql = 'UPDATE' . $tables . $this->parseSql('on') . ' SET ' . $sets . $this->parseWhere() . $this->parseSql('group') . $this->parseSql('order') . $this->parseSql('limit');
        $this->components->clear();
        return [$sql, $bind];
    }

    final function selectSql()
    {
        $sql = 'SELECT' . $this->parseSql('field') . ' FROM' . $this->parseTable() . $this->parseSql('on') . $this->parseWhere() . $this->parseHaving() . $this->parseSql('group') . $this->parseSql('order') . $this->parseSql('limit');
        $bind = $this->components->bind;
        $this->components->clear();
        return [$sql, $bind];
    }

    // ----------------------------------------------------------------------
    //  SQL因素处理
    // ----------------------------------------------------------------------

    // 表处理
    final protected function parseTable()
    {
        $tbs = " `{$this->components->table}`" . ($this->components->alias ? " AS `{$this->components->alias}`" : "");
        $tbArr = [$this->components->table => $this->components->alias];
        $joins = $this->components->join;
        if (is_array($joins)) {
            foreach ($joins as $join) {
                $tbs .= " {$join[0]} JOIN `{$join[1]}`" . (is_null($join[2]) ? '' : " AS `{$join[2]}`");
                $tbArr[$join[1]] = $join[2];
            }
        }
        $this->components->table = $tbArr;
        return $tbs;
    }

    // 字段数组处理
    final protected function parseField()
    {
        $tbArr = $this->components->table;
        // 获取所有表结构
        $tbInfos = [];
        $class = get_called_class();
        foreach ($tbArr as $k => $v) {
            $tbInfos[is_null($v) ? $k : $v] = $class::desc($k);
        }
        // 二维字段数组
        $fieldArr = [];
        $fields = is_array(current($field = $this->components->field)) ? $field : [$field];
        foreach ($fields as $field) {
            $arr = [];
            foreach ($tbInfos as $alias => $tbInfo) {
                foreach ($tbInfo->list as $k => $v) {
                    if ($v->Extra === 'auto_increment') continue;
                    if (key_exists($k, $field)) {
                        $arr[$k] = self::parseValue($v, $field[$k]);
                    } else {
                        $key = $alias . '.' . $k;
                        if (key_exists($key, $field)) {
                            $arr[$key] = self::parseValue($v, $field[$key]);
                        }
                    }
                }
            }
            $fieldArr[] = $arr;
        }
        return $fieldArr;
    }

    // 条件数组处理
    final protected function parseWhere()
    {
        return ($where = $this->components->where) ? ' WHERE ' . self::unquote(is_array($where) ? implode(' ', $where) : $where) : '';
    }

    // 二次筛选条件数组处理
    final protected function parseHaving()
    {
        return ($having = $this->components->having) ? ' HAVING ' . (is_array($having) ? implode(' ', $having) : $having) : '';
    }

    // 获取指定部分SQL语句
    final protected function parseSql($type)
    {
        $val = $this->components->$type;
        if ($val) {
            switch ($type) {
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
            return ' ' . $i . self::unquote(is_array($val) ? implode(',', $val) : $val);
        } else {
            return $type === 'field' ? ' *' : '';
        }
    }

    // SQL关键字辅助处理
    final protected function unquote($str)
    {
        $str = preg_replace('/(?<!\:)([a-zA-Z_]+)\.([a-zA-Z_]+)/', '`\1`.`\2`', $str);
        $str = preg_replace('/(?<!\:)([a-zA-Z_]+)\.\*/', '`\1`.*', $str);
        return $str;
    }

}