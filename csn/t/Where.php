<?php

namespace csn;

class Where extends Instance
{

    // ----------------------------------------------------------------------
    //  构造函数
    // ----------------------------------------------------------------------

    private $type;

    function construct($type = 'AND')
    {
        return false;
    }

    // ----------------------------------------------------------------------
    //  构造函数
    // ----------------------------------------------------------------------

    private $merge;

    private $bind = [];

    function merge($field, $value = null, $op = '=')
    {
        if (is_array($field)) {
            foreach ($field as $k => $v) {

            }
        } else {

        }
        return $this;
        $args = func_get_args();
        $size = count($args);
        $size > 0 || Csn::end('');
        return false;
    }

    // ----------------------------------------------------------------------
    //  构造函数
    // ----------------------------------------------------------------------

    function merges($field, $value = null, $op = '=')
    {
        switch ($op = strtoupper($op))
        {
            case 'IN':
                $after = ' IN '.(is_array($value) ? join(',', $value) : "($value)");
                break;
            case 'BETWEEN':
                $after = ' BETWEEN '.(is_array($value) ? "{$value}  "join(',', $value) : "($value)");
                break;
        }
        return $this;
    }

    // ----------------------------------------------------------------------
    //  构造函数
    // ----------------------------------------------------------------------

    function construct()
    {
        return false;
    }

}