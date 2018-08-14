<?php

namespace csn;

final class Where extends Instance
{

    // ----------------------------------------------------------------------
    //  公共标记
    // ----------------------------------------------------------------------

    private static $sign = 0;

    // ----------------------------------------------------------------------
    //  绑定标记
    // ----------------------------------------------------------------------

    private $id;

    // ----------------------------------------------------------------------
    //  条件数组
    // ----------------------------------------------------------------------

    private $where;

    // ----------------------------------------------------------------------
    //  绑定数据
    // ----------------------------------------------------------------------

    private $bind = [];

    // ----------------------------------------------------------------------
    //  构造函数
    // ----------------------------------------------------------------------

    function construct()
    {
        $this->id = ++self::$sign;
        return false;
    }

    // ----------------------------------------------------------------------
    //  AND条件
    // ----------------------------------------------------------------------

    function where($field, $value = null, $op = '=')
    {
        return $this->whereModel(func_get_args());
    }

    // ----------------------------------------------------------------------
    //  OR条件
    // ----------------------------------------------------------------------

    function whereOr($field, $value = null, $op = '=')
    {
        return $this->whereModel(func_get_args(), 'OR');
    }

    // ----------------------------------------------------------------------
    //  条件模型
    // ----------------------------------------------------------------------

    private function whereModel($args, $type = 'AND')
    {
        $field = $args[0];
        if (is_callable($field)) {
            $obj = self::instance();
            call_user_func($field, $obj);
            list($where, $bind) = $obj->make();
            $this->whereMake($where, $type);
            $this->bind = array_merge($this->bind, $bind);
        } else {
            if (is_array($field)) {
                foreach ($field as $k => $v) {
                    is_array($v) ? $this->parse($type, $k, $v[0], key_exists(1, $v) ? $v[1] : '=') : $this->parse($type, $k, $v, '=');
                }
            } else {
                array_unshift($args, $type);
                call_user_func_array([$this, 'parse'], $args);
            }
        }
        return $this;
    }

    // ----------------------------------------------------------------------
    //  条件解析
    // ----------------------------------------------------------------------

    private function parse($type, $field, $value = null, $op = '=')
    {
        switch ($op = strtoupper($op)) {
            case 'IN':
                is_array($value) || $value = explode(',', $value);
                $after = " IN (";
                for ($i = 0, $c = count($value); $i < $c; $i++) {
                    $after .= $this->bind($field . '_I_' . $i, $value[$i]) . ",";
                }
                $after = rtrim($after, ",") . ")";
                break;
            case 'BETWEEN':
                list($start, $end) = is_array($value) ?: explode(',', $value);
                $after = " BETWEEN {$this->bind($field . '_BS', $start)} AND {$this->bind($field . '_BE', $end)}";
                break;
            case 'LIKE':
                $after = " $op {$this->bind($field.'_L', $value)}";
                break;
            default:
                $after = " $op {$this->bind($field, $value)}";
        }
        return $this->whereMake('(' . $this->unquote($field) . $after . ')', $type);
    }

    // ----------------------------------------------------------------------
    //  记录条件
    // ----------------------------------------------------------------------

    private function whereMake($where, $type = 'AND')
    {
        is_null($this->where) ? $this->where = $where : ($this->where .= ' ' . $type . ' ' . $where);
        return $this;
    }

    // ----------------------------------------------------------------------
    //  参数绑定
    // ----------------------------------------------------------------------

    private function bind($key, $value)
    {
        $bind = ":{$key}_W_{$this->id}";
        $this->bind[$bind] = trim($value);
        return $bind;
    }

    // ----------------------------------------------------------------------
    //  字段反引
    // ----------------------------------------------------------------------

    private function unquote($field)
    {
        return '`' . (strpos($field, '.') === false ? $field : str_replace('.', '`.`', $field)) . '`';
    }

    // ----------------------------------------------------------------------
    //  整合条件
    // ----------------------------------------------------------------------

    function make()
    {
        return ["({$this->where})", $this->bind];
    }

}