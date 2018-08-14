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
        $key = Query::bindKey($field);
        switch ($op = strtoupper($op)) {
            case 'IN':
                is_array($value) || $value = explode(',', $value);
                $ins = [];
                foreach ($value as $k => $v) {
                    $ins[] = $this->bind($key . '_I' . $k, $v);
                }
                $parse = '(' . join(',', $ins) . ')';
                break;
            case 'BETWEEN':
                list($start, $end) = is_array($value) ?: explode(',', $value);
                $parse = "{$this->bind($key . '_bs_', $start)} AND {$this->bind($key . '_be_', $end)}";
                break;
            case 'LIKE':
                $parse = $this->bind($key . '_l_', $value);
                break;
            default:
                $parse = $this->bind($key, $value);
        }
        return $this->whereMake('(' . Query::unquote($field) . ' ' . $op . ' ' . $parse . ')', $type);
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
        $bind = "{$key}_W{$this->id}";
        $this->bind[$bind] = trim($value);
        return $bind;
    }

    // ----------------------------------------------------------------------
    //  整合条件
    // ----------------------------------------------------------------------

    function make()
    {
        return ["({$this->where})", $this->bind];
    }

}