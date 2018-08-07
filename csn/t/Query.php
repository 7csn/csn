<?php

namespace csn;

final class Query extends Data
{

    // ----------------------------------------------------------------------
    //  主表名
    // ----------------------------------------------------------------------

    private $table;

    // ----------------------------------------------------------------------
    //  默认表前缀
    // ----------------------------------------------------------------------

    private $prefix;

    // ----------------------------------------------------------------------
    //  构造函数
    // ----------------------------------------------------------------------

    function construct($table, $prefix = '')
    {
        $this->table = $table;
        $this->prefix = $prefix;
        return false;
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
    //  关联表：左、内联、右、关联处理
    // ----------------------------------------------------------------------

    function leftJoin($table, $on, $dth = null)
    {
        return $this->join($table, $on, $dth, 'LEFT');
    }

    function innerJoin($table, $on, $dth = null)
    {
        return $this->join($table, $on, $dth, 'INNER');
    }

    function rightJoin($table, $on, $dth = null)
    {
        return $this->join($table, $on, $dth, 'RIGHT');
    }

    private function join($table, $on, $dth, $type = 'INNER')
    {
        $tables = explode(' ', $table);
        $table = (is_null($dth) ? $this->prefix : $dth) . $tables[0];
        $join = [$type, $table, key_exists(1, $tables) ? $tables[1] : $table];
        is_null($this->join) ? $this->join = [$join] : $this->join[] = $join;
        is_null($this->on) ? $this->on = [$on] : $this->on[] = $on;
        return $this;
    }

    // ----------------------------------------------------------------------
    //  条件：与、或、绑定
    // ----------------------------------------------------------------------

    function where()
    {
        if (is_callable($func = func_get_arg(0))) {
            $obj = Where::instance('AND');
            $func($obj);
            list($where, $bind) = $obj->make();
        } else {
            list($where, $bind) = call_user_func_array([Where::instance(), 'merge'], func_get_args())->make();
        }
        return $this->bindWhere($where, $bind);
    }

    function OrWhere()
    {
        if (is_callable($func = func_get_arg(0))) {
            $obj = Where::instance('OR');
            $func($obj);
            list($where, $bind) = $obj->make();
        } else {
            list($where, $bind) = call_user_func_array([Where::instance('OR'), 'merge'], func_get_args())->make();
        }
        return $this->bindWhere($where, $bind);
    }

    function bindWhere($where, $bind = null, $type = 'AND')
    {
        empty($where) || $this->where = is_null($w = $this->bind($bind)->where) ? $where : $w . ' ' . $type . ' ' . $where;
        return $this;
    }

    // ----------------------------------------------------------------------
    //  SQL因素
    // ----------------------------------------------------------------------

    // 预编译
    function bind($bind)
    {
        empty($bind) || $this->bind = is_null($b = $this->bind) ? $bind : array_merge($b, $bind);
        return $this;
    }

    // ----------------------------------------------------------------------
    //  SQL因素
    // ----------------------------------------------------------------------

    // 字段：查
    function field($field, $bind = null)
    {
        empty($field) || ($this->bind($bind)->field = is_array($field) ? $field : explode(',', $field));
        return $this;
    }

    // ----------------------------------------------------------------------
    //  SQL因素
    // ----------------------------------------------------------------------

    // 字段：改
    function set($set, $bind = null)
    {
        empty($set) || $this->set = is_null($s = $this->bind($bind)->set) ? $set : $s . ',' . $set;
        return $this;
    }

    // ----------------------------------------------------------------------
    //  SQL因素
    // ----------------------------------------------------------------------

    // 字段：增
    function values($values)
    {
        empty($values) || $this->values = $values;
        return $this;
    }

    // ----------------------------------------------------------------------
    //  SQL因素
    // ----------------------------------------------------------------------

    // 归类
    function group($group)
    {
        $this->group = $group;
        return $this;
    }

    // ----------------------------------------------------------------------
    //  SQL因素
    // ----------------------------------------------------------------------

    // 顺序
    function order($order)
    {
        $this->order = $order;
        return $this;
    }

    // ----------------------------------------------------------------------
    //  SQL因素
    // ----------------------------------------------------------------------

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

    // ----------------------------------------------------------------------
    //  SQL因素
    // ----------------------------------------------------------------------

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

    // ----------------------------------------------------------------------
    //  SQL因素
    // ----------------------------------------------------------------------

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

    // ----------------------------------------------------------------------
    //  SQL因素
    // ----------------------------------------------------------------------

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

    // ----------------------------------------------------------------------
    //  SQL因素
    // ----------------------------------------------------------------------

    // 条件数组处理
    protected function parseWhere()
    {
        return ($where = $this->where) ? ' WHERE ' . self::unquote($where) : '';
    }

    // ----------------------------------------------------------------------
    //  SQL因素
    // ----------------------------------------------------------------------

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

    // ----------------------------------------------------------------------
    //  SQL因素
    // ----------------------------------------------------------------------

    // SQL关键字辅助处理
    protected function unquote($str)
    {
        $str = preg_replace('/(?<!\:)([a-zA-Z_]+)\.([a-zA-Z_]+)/', '`\1`.`\2`', $str);
        $str = preg_replace('/(?<!\:)([a-zA-Z_]+)\.\*/', '`\1`.*', $str);
        return $str;
    }

    // ----------------------------------------------------------------------
    //  获取SQL：增、删、改、查
    // ----------------------------------------------------------------------

    function insert($values = null)
    {
        $this->values($values);
        $sql = 'INSERT INTO' . $this->parseTable() . $this->parseValues();
        $bind = $this->bind;
        $this->clear();
        return [$sql, $bind];
    }

    function delete()
    {
        $sql = 'DELETE FROM' . $this->parseTable() . $this->parseSql('on') . $this->parseWhere() . $this->parseSql('group') . $this->parseSql('order') . $this->parseSql('limit');
        $bind = $this->bind;
        $this->clear();
        return [$sql, $bind];
    }

    function update($field = null, $bind = null)
    {
        $this->set($field, $bind);
        $sql = 'UPDATE' . $this->parseTable() . $this->parseSql('on') . $this->parseSet() . $this->parseWhere() . $this->parseSql('group') . $this->parseSql('order') . $this->parseSql('limit');
        $bind = $this->bind;
        $this->clear();
        return [$sql, $bind];
    }

    function select()
    {
        $sql = 'SELECT' . $this->parseSql('field') . ' FROM' . $this->parseTable() . $this->parseSql('on') . $this->parseWhere() . $this->parseSql('group') . $this->parseSql('order') . $this->parseSql('limit');
        $bind = $this->bind;
        $this->clear();
        return [$sql, $bind];
    }

}