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
        if (is_array($field)) {
            foreach ($field as $k => $v) {
                is_array($v) ? $this->merges($k, $v[0], key_exists(1, $v) ? $v[1] : '=', 'AND') : $this->merges($k, $v, '=', 'AND');
            }
        } else {
            $this->merges($field, $value, $op);
        }
        return $this;
    }

    // ----------------------------------------------------------------------
    //  OR条件
    // ----------------------------------------------------------------------

    function whereOr($field, $value = null, $op = '=')
    {
        is_array($field) ? call_user_func(function ($obj) use ($field) {
            foreach ($field as $k => $v) {
                is_array($v) ? $obj->merges($k, $v[0], key_exists(1, $v) ? $v[1] : '=', 'OR') : $obj->merges($k, $v, '=', 'OR');
            }
        }, $this) : $this->merges($field, $value, $op);
        return $this;
    }

    // ----------------------------------------------------------------------
    //  条件模型
    // ----------------------------------------------------------------------

    private function whereModel()
    {

    }

    // ----------------------------------------------------------------------
    //  条件解析
    // ----------------------------------------------------------------------

    private function merges($field, $value = null, $op = '=', $type = 'AND')
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
        $this->where[] = "({$this->unquote($field)}{$after})";
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
        return [$this->where, $this->bind];
        return ['(' . join(' AND ', $this->where) . ')', $this->bind];
    }

}