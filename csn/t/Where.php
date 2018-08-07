<?php

namespace csn;

final class Where extends Instance
{

    // ----------------------------------------------------------------------
    //  绑定标记
    // ----------------------------------------------------------------------

    private $id;

    // ----------------------------------------------------------------------
    //  连接类型
    // ----------------------------------------------------------------------

    private $type;

    // ----------------------------------------------------------------------
    //  条件数组
    // ----------------------------------------------------------------------

    private $merge = [];

    // ----------------------------------------------------------------------
    //  绑定数据
    // ----------------------------------------------------------------------

    private $bind = [];

    // ----------------------------------------------------------------------
    //  构造函数
    // ----------------------------------------------------------------------

    function construct($type = 'AND', $id = null)
    {
        $this->type = $type;
        $this->id = is_null($id) ? Safe::en(mt_rand()) : $id;
        return false;
    }

    // ----------------------------------------------------------------------
    //  复合条件
    // ----------------------------------------------------------------------

    function merge($field, $value = null, $op = '=')
    {
        is_array($field) ? call_user_func(function ($obj) use ($field) {
            foreach ($field as $k => $v) {
                is_array($v) ? $obj->merges($k, $v[0], key_exists(1, $v) ? $v[1] : '=') : $obj->merges($k, $v);
            }
        }, $this) : $this->merges($field, $value, $op);
        return $this;
    }

    // ----------------------------------------------------------------------
    //  条件解析
    // ----------------------------------------------------------------------

    private function merges($field, $value = null, $op = '=')
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
        $this->merge[] = "({$this->unquote($field)}{$after})";
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
        return ['(' . join(' AND ', $this->merge) . ')', $this->bind];
    }

}