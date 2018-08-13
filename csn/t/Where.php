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
        if (is_array($field = $args[0])) {
            foreach ($field as $k => $v) {
                is_array($v) ? $this->merges($type, $k, $v[0], key_exists(1, $v) ? $v[1] : '=') : $this->merges($type, $k, $v, '=');
            }
        } else {
            array_unshift($args, $type);
            call_user_func_array([$this, 'merges'], $args);
        }
        return $this;
    }

    // ----------------------------------------------------------------------
    //  条件解析
    // ----------------------------------------------------------------------

    private function merges($type, $field, $value = null, $op = '=')
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
        is_null($this->where) ? $this->where = "({$this->unquote($field)}{$after})" : ($this->where .= " $type ({$this->unquote($field)}{$after})");
        return $this;
    }

    // ----------------------------------------------------------------------
    //  绑定操作
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

    private function unquote($str)
    {
        return '`' . (strpos($str, '.') === false ? $str : str_replace('.', '`.`', $str)) . '`';
    }

    // ----------------------------------------------------------------------
    //  整合条件
    // ----------------------------------------------------------------------

    function make()
    {
        return ["({$this->where})", $this->bind];
    }

}