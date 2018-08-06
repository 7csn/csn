<?php

namespace csn;

class Query extends Data
{

    // ----------------------------------------------------------------------
    //  构造函数
    // ----------------------------------------------------------------------

    function construct()
    {
        return false;
    }

    // ----------------------------------------------------------------------
    //  主表及默认表前缀
    // ----------------------------------------------------------------------

    function main($table, $prefix = '')
    {
        $this->table = $table;
        $this->prefix = $prefix;
        return $this;
    }

    // ----------------------------------------------------------------------
    //  主表别名
    // ----------------------------------------------------------------------

    function alias($alias)
    {
        $this->alias = $alias;
        return $this;
    }

    // ----------------------------------------------------------------------
    //  SQL因素
    // ----------------------------------------------------------------------

    // ----------------------------------------------------------------------
    //  SQL因素
    // ----------------------------------------------------------------------

    // ----------------------------------------------------------------------
    //  SQL因素
    // ----------------------------------------------------------------------

    // 关联表
    function join($table, $on, $dth, $type = 'INNER')
    {
        $tables = explode(' ', $table);
        $table = key_exists([1, $tables]) ?
        $table = (is_null($dth) ? $this->dth : $dth) . $table;
        $join = [$type, $table, $alias];
        is_null($this->join) ? $this->join = [$join] : $this->join[] = $join;
        return $this;
    }

    // 左关联
    function leftJoin($table, $alias = null, $dth = null)
    {
        return $this->join($table, $dth, $alias, 'LEFT');
    }

    // 内联
    function innerJoin($table, $alias = null, $dth = null)
    {
        return $this->join($table, $dth, $alias, 'INNER');
    }

    // 右关联
    function rightJoin($table, $alias = null, $dth = null)
    {
        return $this->join($table, $dth, $alias, 'RIGHT');
    }

    // 条件
    function where($where, $bind = null)
    {
        empty($where) || $this->where = is_null($w = $this->bind($bind)->where) ? $where : $w . ' AND ' . $where;
        return $this;
    }

    // 预编译
    function bind($bind)
    {
        empty($bind) || $this->bind = is_null($b = $this->bind) ? $bind : array_merge($b, $bind);
        return $this;
    }

    // 字段：查
    function field($field, $bind = null)
    {
        empty($field) || ($this->bind($bind)->field = is_array($field) ? $field : explode(',', $field));
        return $this;
    }

    // 字段：改
    function set($set, $bind = null)
    {
        empty($set) || $this->set = is_null($s = $this->bind($bind)->set) ? $set : $s . ',' . $set;
        return $this;
    }

    // 字段：增
    function values($values)
    {
        empty($values) || $this->values = $values;
        return $this;
    }

    // 归类
    function group($group)
    {
        $this->group = $group;
        return $this;
    }

    // 顺序
    function order($order)
    {
        $this->order = $order;
        return $this;
    }

    // 限制
    function limit($from, $num = null)
    {
        if (is_null($num)) {
            $num = $from;
            $from = 0;
        }
        $this->limit = [$from, $num];
        return $this;
    }

    // ----------------------------------------------------------------------
    //  SQL因素处理
    // ----------------------------------------------------------------------

    // 表处理
    protected function parseTable($rArr = false)
    {
        if (is_null($this->tableArr) && is_null($this->tableStr)) {
            $tableStr = " `{$this->table}`" . ($this->alias ? " AS `{$this->alias}`" : "");
            $tableArr = [$this->table => $this->alias];
            $joins = $this->join;
            if (is_array($joins)) {
                foreach ($joins as $join) {
                    $tableStr .= " {$join[0]} JOIN `{$join[1]}`" . (is_null($join[2]) ? '' : " AS `{$join[2]}`");
                    $tableArr[$join[1]] = $join[2];
                }
            }
            $this->tableArr = $tableArr;
            $this->tableStr = $tableStr;
        }
        return $rArr ? $this->tableArr : $this->tableStr;
    }

    // 获取所有表结构
    protected function tableDesc()
    {
        if (is_null($this->tableDesc)) {
            $tableDesc = [];
            $class = get_called_class();
            foreach ($this->parseTable(true) as $table => $alias) {
                $tableDesc[is_null($alias) ? $table : $alias] = $class::desc($table);
            }
            $this->tableDesc = $tableDesc;
        }
        return $this->tableDesc;
    }

    // 字段处理：改
    protected function parseSet()
    {
        // 过滤直接条件
        if (!is_array($set = $this->set)) return ' SET ' . $set;
        $bind = $this->bind;
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
        $this->bind = $bind;
        // 返回修改字符串
        return ' SET ' . join(',', $setArr);
    }

    // 字段处理：增
    protected function parseValues()
    {
        // 表结构
        $tableDesc = $this->tableDesc();
        // 二维数组：批处理
        $values = is_array(current($values = $this->values)) ? $values : [$values];
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
        $this->bind = $bind;
        return ' (' . join(',', $valueBefore) . ') VALUES ' . join(',', $valueAfter);
    }

    // 条件数组处理
    protected function parseWhere()
    {
        return ($where = $this->where) ? ' WHERE ' . self::unquote($where) : '';
    }

    // 获取指定部分SQL语句
    protected function parseSql($type)
    {
        $val = $this->$type;
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
    protected function unquote($str)
    {
        $str = preg_replace('/(?<!\:)([a-zA-Z_]+)\.([a-zA-Z_]+)/', '`\1`.`\2`', $str);
        $str = preg_replace('/(?<!\:)([a-zA-Z_]+)\.\*/', '`\1`.*', $str);
        return $str;
    }

}