<?php

namespace csn;

final class Query extends Instance
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
    //  数据对象
    // ----------------------------------------------------------------------

    private $query;

    // ----------------------------------------------------------------------
    //  构造函数
    // ----------------------------------------------------------------------

    function construct($table, $prefix = '')
    {
        $this->table = $table;
        $this->prefix = $prefix;
        $this->query = Data::instance();
    }

    // ----------------------------------------------------------------------
    //  主表别名
    // ----------------------------------------------------------------------

    function alias($alias)
    {
        $this->query->alias = $alias;
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
        $join = [$type, $table, key_exists(1, $tables) ? $tables[1] : $table, $on];
        is_null($this->query->join) ? $this->query->join = [$join] : $this->query->join[] = $join;
        return $this;
    }

    // ----------------------------------------------------------------------
    //  条件：与、或、绑定、模型
    // ----------------------------------------------------------------------

    function where()
    {
        return $this->whereModel(func_get_args());
    }

    function whereOr()
    {
        return $this->whereModel(func_get_args(), 'OR', 'whereOr');
    }

    function whereBind($where, $bind = null, $type = 'AND')
    {
        empty($where) || $this->query->where = is_null($w = $this->bind($bind)->query->where) ? $where : ($w . ' ' . $type . ' ' . $where);
        return $this;
    }

    private function whereModel($args, $type = 'AND', $where = 'where')
    {
        if (is_callable($func = $args[0])) {
            $obj = Where::instance();
            call_user_func($func, $obj);
            list($where, $bind) = $obj->make();
        } else {
            list($where, $bind) = call_user_func_array([Where::instance(), $where], $args)->make();
        }
        return $this->whereBind($where, $bind, $type);
    }

    // ----------------------------------------------------------------------
    //  参数绑定
    // ----------------------------------------------------------------------

    function bind($bind)
    {
        empty($bind) || $this->query->bind = is_null($this->query->bind) ? $bind : array_merge($this->query->bind, $bind);
        return $this;
    }

    // ----------------------------------------------------------------------
    //  字段：增、查、改、绑定
    // ----------------------------------------------------------------------

    function field($field)
    {
        $fields = is_array($field) ? $field : explode(',', $field);
        $fieldArr = [];
        foreach ($fields as $field) {
            $fieldArr[] = $this->unquote(trim($field));
        }
        $this->query->field = join(',', $fieldArr);
        return $this;
    }

    function set($field, $value)
    {
        if (is_array($field)) {

        }
        empty($set) || $this->query->set = is_null($s = $this->query->set) ? $set : $s . ',' . $set;
        return $this;
    }

    function values($values)
    {
        empty($values) || $this->query->values = $values;
        return $this;
    }

    function setBind($set, $bind = null)
    {
        empty($set) || $this->set = is_null($s = $this->bind($bind)->set) ? $set : $s . ',' . $set;
        return $this;
    }

    // ----------------------------------------------------------------------
    //  分组
    // ----------------------------------------------------------------------

    function group($group)
    {
        $this->query->group = $group;
        return $this;
    }

    // ----------------------------------------------------------------------
    //  顺序
    // ----------------------------------------------------------------------

    function order($order)
    {
        $this->query->order = $order;
        return $this;
    }

    // ----------------------------------------------------------------------
    //  限制
    // ----------------------------------------------------------------------

    function limit($from, $num = null)
    {
        if (is_null($num)) {
            $num = $from;
            $from = 0;
        }
        $this->query->limit = [$from, $num];
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
    //   获取指定部分SQL
    // ----------------------------------------------------------------------

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
    //  表名、字段名反引
    // ----------------------------------------------------------------------

    protected function unquote($name)
    {
        $index = strpos($name, '.');
        if ($index === false) return "`$name`";
        $field = substr($name, $index + 1);
        return '`' . substr($name, 0, $index) . '`.' . ($field === '*' ? $field : ('`' . $field . '`'));
//        $str = preg_replace('/(?<!\:)([a-zA-Z_]+)\.([a-zA-Z_]+)/', '`\1`.`\2`', $str);
//        $str = preg_replace('/(?<!\:)([a-zA-Z_]+)\.\*/', '`\1`.*', $str);
    }

    // ----------------------------------------------------------------------
    //  获取SQL：增、删、改、查
    // ----------------------------------------------------------------------

    function insert($values = null)
    {
        $this->values($values);
        return $this->queryModel(function ($obj) {
            return 'INSERT INTO' . $obj->parseTable() . $obj->parseValues();
        });
    }

    function delete()
    {
        return $this->queryModel(function ($obj) {
            return 'DELETE FROM' . $obj->parseTable() . $obj->parseSql('on') . $obj->parseWhere() . $obj->parseSql('group') . $obj->parseSql('order') . $obj->parseSql('limit');
        });
    }

    function update($set = null, $bind = null)
    {
        $this->set($set, $bind);
        return $this->queryModel(function ($obj) {
            return 'UPDATE' . $obj->parseTable() . $obj->parseSql('on') . $obj->parseSet() . $obj->parseWhere() . $obj->parseSql('group') . $obj->parseSql('order') . $obj->parseSql('limit');
        });
    }

    function select($field = null)
    {
        $this->field($field);
        return $this->queryModel(function ($obj) {
            return 'SELECT' . $obj->parseSql('field') . ' FROM' . $obj->parseTable() . $obj->parseSql('on') . $obj->parseWhere() . $obj->parseSql('group') . $obj->parseSql('order') . $obj->parseSql('limit');
        });
    }

    function queryModel($func)
    {
        $sql = call_user_func($func, $this);
        $bind = $this->query->bind;
        $this->query->clear();
        return [$sql, $bind];
    }

    // ----------------------------------------------------------------------
    //  修改字段：加、减、乘、除
    // ----------------------------------------------------------------------

}