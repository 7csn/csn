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
                $val = is_null($val) ? $structure->Default : (is_array($val) ? json_encode($val) : (string)$val);
                break;
            case 'tinyint':
            case 'smallint':
            case 'mediumint':
            case 'int':
            case 'bigint':
                $val = is_null($val) ? $structure->Default : (int)$val;
                break;
            default:
                $val = is_null($val) ? $structure->Default : floatval($val);
                break;
        }
        return (is_null($val) && !is_null($structure->Default)) ? $structure->Default : $val;
    }

    // ----------------------------------------------------------------------
    //  表SQL封装：查询、增删改
    // ----------------------------------------------------------------------

    private static $lastSql;

    final static function lastSql()
    {
        return self::$lastSql;
    }

    final static function inQuery($link, $sql, $bind = [], $rArr = false)
    {
        self::$lastSql = [$sql, $bind];
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
        self::$lastSql = [$sql, $bind];
        $bool = $link->prepare($sql)->execute($bind);
        return $insert ? $bool ? $link->lastInsertId() : 0 : $bool;
    }

    // ----------------------------------------------------------------------
    //  事务处理
    // ----------------------------------------------------------------------

    // 事务连接
    private static $transLink;

    // 事务失败回调
    private static $transFail;

    // 获取事务状态
    final static function getTrans()
    {
        return !is_null(self::$transLink);
    }

    // 开始事务
    final static function beginTrans($link, $action, $fail)
    {
        $action instanceof Course || $action instanceof Api || Csn::end('事务函数须为 Course 或 Api 对象');
        $fail instanceof Course || $fail instanceof Api || Csn::end('事务故障函数须为 Course 或 Api 对象');
        $link->beginTransaction();
        self::$transLink = $link;
        self::$transFail = $fail;
        $func = $action->run();
        self::$transLink->commit();
        self::$transLink = null;
        self::$transFail = null;
        return $func;
    }

    // 结束事务
    final static function transEnd()
    {
        if (is_null(self::$transLink)) return;
        self::$transLink->rollBack();
        $func = self::$transFail;
        self::$transFail = null;
        self::$transLink = null;
        return $func;
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
        empty($where) || $this->components->where = is_null($w = $this->bind($bind)->components->where) ? $where : $w . ' AND ' . $where;
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

    // 字段：查
    final function field($field, $bind = null)
    {
        empty($field) || ($this->bind($bind)->components->field = is_array($field) ? $field : explode(',', $field));
        return $this;
    }

    // 字段：改
    final function set($set, $bind = null)
    {
        empty($set) || $this->components->set = is_null($s = $this->bind($bind)->components->set) ? $set : $s . ',' . $set;
        return $this;
    }

    // 字段：增
    final function values($values)
    {
        empty($values) || $this->components->values = $values;
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

    final function insertSql($values = null)
    {
        $this->values($values);
        $sql = 'INSERT INTO' . $this->parseTable() . $this->parseValues();
        $bind = $this->components->bind;
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

    final function updateSql($field = null, $bind = null)
    {
        $this->set($field, $bind);
        $sql = 'UPDATE' . $this->parseTable() . $this->parseSql('on') . $this->parseSet() . $this->parseWhere() . $this->parseSql('group') . $this->parseSql('order') . $this->parseSql('limit');
        $bind = $this->components->bind;
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
    final protected function parseTable($rArr = false)
    {
        if (is_null($this->components->tableArr) && is_null($this->components->tableStr)) {
            $tableStr = " `{$this->components->table}`" . ($this->components->alias ? " AS `{$this->components->alias}`" : "");
            $tableArr = [$this->components->table => $this->components->alias];
            $joins = $this->components->join;
            if (is_array($joins)) {
                foreach ($joins as $join) {
                    $tableStr .= " {$join[0]} JOIN `{$join[1]}`" . (is_null($join[2]) ? '' : " AS `{$join[2]}`");
                    $tableArr[$join[1]] = $join[2];
                }
            }
            $this->components->tableArr = $tableArr;
            $this->components->tableStr = $tableStr;
        }
        return $rArr ? $this->components->tableArr : $this->components->tableStr;
    }

    // 获取所有表结构
    final protected function tableDesc()
    {
        if (is_null($this->components->tableDesc)) {
            $tableDesc = [];
            $class = get_called_class();
            foreach ($this->parseTable(true) as $table => $alias) {
                $tableDesc[is_null($alias) ? $table : $alias] = $class::desc($table);
            }
            $this->components->tableDesc = $tableDesc;
        }
        return $this->components->tableDesc;
    }

    // 字段处理：改
    final protected function parseSet()
    {
        // 过滤直接条件
        if (!is_array($set = $this->components->set)) return ' SET ' . $set;
        $bind = $this->components->bind;
        $setArr = [];
        foreach ($this->tableDesc() as $tbn => $desc) {
            foreach ($desc->list as $name => $field) {
                // 过滤自增字段
                if ($field->Extra === 'auto_increment') continue;
                if (key_exists($name, $set)) {   // 无别名表
                    $setArr[] = "`$name` = :{$name}__";
                    $bind[":{$name}__"] = self::parseValue($field, $set[$name]);
                } else {
                    if (key_exists($key = $tbn . '.' . $name, $set)) {    // 别名表
                        $setArr[] = "`$tbn`.`$name` = :{$name}__{$tbn}__";
                        $bind[":{$name}__{$tbn}__"] = self::parseValue($field, $set[$name]);
                    }
                }
            }
        }
        // 更新绑定数据
        $this->components->bind = $bind;
        // 返回修改字符串
        return ' SET ' . join(',', $setArr);
    }

    // 字段处理：增
    final protected function parseValues()
    {
        // 表结构
        $tableDesc = $this->tableDesc();
        // 二维数组：批处理
        $values = is_array(current($values = $this->components->values)) ? $values : [$values];
        // 绑定数组
        $bind = [];
        // 字段名称数组
        $valueBefore = [];
        // 字段绑定名数组
        $valueAfter = [];
        for ($i = 0, $c = count($values); $i < $c; $i++) {
            // 单次绑定名数组
            $after = [];
            $value = $values[$i];
            foreach ($tableDesc as $tbn => $desc) {
                foreach ($desc->list as $name => $fieldObj) {
                    // 过滤自增字段
                    if ($fieldObj->Extra === 'auto_increment') continue;
                    // 过滤不存在字段
                    if (!key_exists($name, $value)) continue;
                    $i > 0 || $valueBefore[] = "`$name`";
                    $after[] = ":{$name}__$i";
                    $bind[":{$name}__$i"] = self::parseValue($fieldObj, $value[$name]);
                }
            }
            $valueAfter[] = '(' . join(',', $after) . ')';
        }
        $this->components->bind = $bind;
        return ' (' . join(',', $valueBefore) . ') VALUES ' . join(',', $valueAfter);
    }

    // 条件数组处理
    final protected function parseWhere()
    {
        return ($where = $this->components->where) ? ' WHERE ' . self::unquote($where) : '';
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