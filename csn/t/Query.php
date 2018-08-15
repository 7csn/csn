<?php

namespace csn;

class Query extends Instance
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

    private $data;

    // ----------------------------------------------------------------------
    //  公共标记
    // ----------------------------------------------------------------------

    private static $sign = 0;

    // ----------------------------------------------------------------------
    //  绑定标记
    // ----------------------------------------------------------------------

    private $id;

    // ----------------------------------------------------------------------
    //  构造函数
    // ----------------------------------------------------------------------

    function construct($table, $prefix = '')
    {
        $this->id = ++self::$sign;
        $this->table = $table;
        $this->prefix = $prefix;
        $this->data = Data::instance();
    }

    // ----------------------------------------------------------------------
    //  主表别名
    // ----------------------------------------------------------------------

    function alias($alias)
    {
        $this->data->alias = $alias;
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

    function join($table, $on, $dth = null, $type = 'INNER')
    {
        $tables = explode(' ', $table);
        $table = (is_null($dth) ? $this->prefix : $dth) . $tables[0];
        $alias = key_exists(1, $tables) ? $tables[1] : $table;
        $join = [$type, $table, $alias, $on];
        is_null($this->data->join) ? $this->data->join = [$join] : $this->data->join[] = $join;
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
        empty($where) || $this->data->where = is_null($w = $this->bind($bind)->data->where) ? $where : "$w $type $where";
        return $this;
    }

    private function whereModel($args, $type = 'AND', $fn = 'where')
    {
        $obj = Where::instance();
        is_callable($func = $args[0]) ? call_user_func($func, $obj) : call_user_func_array([$obj, $fn], $args);
        list($where, $bind) = $obj->make();
        return $this->whereBind($where, $bind, $type);
    }

    // ----------------------------------------------------------------------
    //  参数绑定
    // ----------------------------------------------------------------------

    function bind($bind)
    {
        empty($bind) || $this->data->bind = is_null($this->data->bind) ? $bind : array_merge($this->data->bind, $bind);
        return $this;
    }

    // ----------------------------------------------------------------------
    //  字段：查
    // ----------------------------------------------------------------------

    function field($field)
    {
        if (!empty($field)) {
            $fields = is_array($field) ? $field : explode(',', $field);
            $fieldArr = [];
            foreach ($fields as $f) {
                $fieldArr[] = self::unquote(trim($f));
            }
            $this->data->field = (is_null($this->data->field) ? '' : ($this->data->field . ',')) . join(',', $fieldArr);
        }
        return $this;
    }

    // ----------------------------------------------------------------------
    //  字段：增
    // ----------------------------------------------------------------------

    function values($values)
    {
        empty($values) || $this->data->values = $values;
        return $this;
    }

    // ----------------------------------------------------------------------
    //  字段(改)：常规、自增、自减、自修改、绑定、模型
    // ----------------------------------------------------------------------

    function set($field, $value = null)
    {
        return $this->setModel($field, $value, function ($field) {
            return "$field = ";
        });
    }

    function setInc($field, $value = null)
    {
        return $this->setMore($field, '+', $value);
    }

    function setDec($field, $value = null)
    {
        return $this->setMore($field, '-', $value);
    }

    function setMore($field, $op, $value = null)
    {
        return $this->setModel($field, $value, function ($field) use ($op) {
            return "$field = $field $op ";
        });
    }

    function setBind($set, $bind = null)
    {
        empty($set) || $this->data->set = is_null($s = $this->bind($bind)->data->set) ? $set : "$s,$set";
        return $this;
    }

    private function setModel($field, $value, $func)
    {
        if (is_array($field)) {
            foreach ($field as $k => $v) {
                $this->setModel($k, $v, $func);
            }
        } else {
            $key = self::bindKey($field) . '_S_Q' . $this->id;
            $field = self::unquote($field);
            $set = call_user_func($func, $field) . ' ' . $key;
            $this->setBind($set, [$field => $value]);
        }
        return $this;
    }

    // ----------------------------------------------------------------------
    //  分组：$group1,...
    // ----------------------------------------------------------------------

    function group($group)
    {
        $group = '`' . join('`,`', func_get_args()) . '`';
        $this->data->group = is_null($g = $this->data->group) ? $group : "$g,$group";
        return $this;
    }

    // ----------------------------------------------------------------------
    //  顺序：$field,$order | [$field1 => $order1,...]
    // ----------------------------------------------------------------------

    function order($field, $orders = null)
    {
        if (is_array($field)) {
            foreach ($field as $k => $v) {
                $this->order($k, $v);
            }
        } else {
            $orders = "`$field` $orders";
            $this->data->order = is_null($o = $this->data->order) ? $orders : "$o,$orders";
        }
        return $this;
    }

    // ----------------------------------------------------------------------
    //  限制：$from,$num | $num
    // ----------------------------------------------------------------------

    function limit($from, $num = null)
    {
        $limit = [$from];
        is_null($num) ? array_unshift($limit, 0) : array_push($limit, $num);
        $this->data->limit = $limit;
        return $this;
    }

    // ----------------------------------------------------------------------
    //  获取SQL：增、删、改、查、模型
    // ----------------------------------------------------------------------

    function insert()
    {
        return $this->queryModel(function () {
            return 'INSERT INTO' . $this->parseTable() . $this->parseValues();
        });
    }

    function delete()
    {
        return $this->queryModel(function () {
            return 'DELETE FROM' . $this->parseTable() . $this->parseSql('where') . $this->parseSql('group') . $this->parseSql('order') . $this->parseSql('limit');
        });
    }

    function update()
    {
        return $this->queryModel(function () {
            return 'UPDATE' . $this->parseTable() . $this->parseSet() . $this->parseSql('where') . $this->parseSql('group') . $this->parseSql('order') . $this->parseSql('limit');
        });
    }

    function select()
    {
        return $this->queryModel(function () {
            return 'SELECT' . $this->parseSql('field') . ' FROM' . $this->parseTable() . $this->parseSql('where') . $this->parseSql('group') . $this->parseSql('order') . $this->parseSql('limit');
        });
    }

    private function queryModel($func)
    {
        $sql = call_user_func($func, $this);
        $bind = $this->data->bind;
        $this->data->clear();
        return [$sql, $bind];
    }

    // ----------------------------------------------------------------------
    //  解析SQL：表、表结构、字段修改、字段增加、条件、指定SQL
    // ----------------------------------------------------------------------

    protected function parseTable($rArr = false)
    {
        if (is_null($this->data->tableArr) && is_null($this->data->tableStr)) {
            $tableStr = " `{$this->table}`" . (is_null($this->data->alias) ? " AS `{$this->data->alias}`" : "");
            $tableArr = [$this->table => $this->data->alias];
            $joins = $this->data->join;
            if (is_array($joins)) {
                foreach ($joins as $join) {
                    $tableStr .= " {$join[0]} JOIN `{$join[1]}` AS `{$join[2]}` ON `" . preg_replace('/(\w+)\.(\w+)/', '`\1`.`\2`', $join[3]) . "`";
                    $tableArr[$join[1]] = $join[2];
                }
            }
            $this->data->tableArr = $tableArr;
            $this->data->tableStr = $tableStr;
        }
        return $rArr ? $this->data->tableArr : $this->data->tableStr;
    }

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

    protected function parseWhere()
    {
        return ($where = $this->data->where) ? ' WHERE ' . $where : '';
    }

    protected function parseSql($type)
    {
        $val = $this->data->$type;
        if ($val) {
            switch ($type) {
                case 'field':
                case 'where':
                case 'order':
                case 'limit':
                    return ' '.strtoupper($type).' '.$val;
                case 'group':
                    return ' GROUP BY '.$val;
                default:
                    return '';
            }
        } else {
            return $type === 'field' ? ' *' : '';
        }
    }

    // ----------------------------------------------------------------------
    //  绑定键名
    // ----------------------------------------------------------------------

    static function bindKey($key)
    {
        $index = strpos($key, '.');
        return ':' . ($index === false ? $key : (substr($key, $index + 1) . '_' . substr($key, 0, $index)));
    }

    // ----------------------------------------------------------------------
    //  表名、字段名反引
    // ----------------------------------------------------------------------

    static function unquote($name)
    {
        $index = strpos($name, '.');
        if ($index === false) return "`$name`";
        $field = substr($name, $index + 1);
        return '`' . substr($name, 0, $index) . '`.' . ($field === '*' ? $field : ('`' . $field . '`'));
    }

}