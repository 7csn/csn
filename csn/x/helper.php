<?php

// ----------------------------------------------------------------------
//  Db对象：指定库名、链接
// ----------------------------------------------------------------------

function db($dbn = null, $key = 0)
{
    if (func_num_args() < 2 && !is_null($dbn)) {
        $key = $dbn;
        $dbn = null;
    }
    return \csn\Db::link($key)->dbn($dbn);
}

// ----------------------------------------------------------------------
//  Db对象：指定表名、库名、链接
// ----------------------------------------------------------------------

function table()
{
    $args = func_get_args();
    $table = array_shift($args);
    return call_user_func_array('db', $args)->table($table);
}

// ----------------------------------------------------------------------
//  获取路由
// ----------------------------------------------------------------------

function url($path, $full = false)
{
    return \csn\Request::instance()->makeUrl($path, $full);
}

// ----------------------------------------------------------------------
//  获取路由
// ----------------------------------------------------------------------

function varType($var)
{
    if (is_object($var)) return "object";
    if (is_resource($var)) return "resource";
    if ((bool)$var === $var) return "bool";
    if ((float)$var === $var) return "float";
    if ((int)$var === $var) return "int";
    if ((string)$var === $var) return "string";
    if (null === $var) return "null";
    return "unknown";
}

// ----------------------------------------------------------------------
//  模板引入方法
// ----------------------------------------------------------------------

function viewInclude($path, $data, $time)
{
    echo \csn\View::instance($path)->makeHtml($data, $time);
}

// ----------------------------------------------------------------------
//  IP转化数字
// ----------------------------------------------------------------------

function intIp($ip)
{
    $str = '';
    foreach (explode('.', $ip) as $v) {
        $str .= (($v >= 16 ? '' : '0') . dechex($v));
    }
    return hexdec($str);
}

// ----------------------------------------------------------------------
//  解析数字IP
// ----------------------------------------------------------------------

function parseIp($n)
{
    $dh = dechex($n);
    while (strlen($dh) < 8) {
        $dh = '0' . $dh;
    }
    $str = '';
    for ($i = 0, $l = strlen($dh); $i < $l; $i += 2) {
        $str .= '.' . hexdec(substr($dh, $i, 2));
    }
    return ltrim($str, '.');
}

// ----------------------------------------------------------------------
//  获取IP地址
// ----------------------------------------------------------------------

function ipArea($ip = '')
{
    $area = new \stdClass();
    $json = json_decode(file_get_contents('http://ip.taobao.com/service/getIpInfo.php?ip=' . $ip));
    if ($json->code === 0) {
        $area->country = $json->data->country;
        $area->region = $json->data->region;
        $area->city = $json->data->city;
    } else {
        $area->country = 'XX';
        $area->region = 'XX';
        $area->city = 'XX';
    }
    return $area;
}

// ----------------------------------------------------------------------
//  判断是否mysql关键字
// ----------------------------------------------------------------------

function dbKey($field)
{
    $keys = ',ADD,ALL,ALTER,ANALYZE,AND,AS,ASC,ASENSITIVE,BEFORE,BETWEEN,BIGINT,BINARY,BLOB,BOTH,BY,CALL,CASCADE,CASE,CHANGE,CHAR,CHARACTER,CHECK,COLLATE,COLUMN,CONDITION,CONNECTION,CONSTRAINT,CONTINUE,CONVERT,CREATE,CROSS,CURRENT_DATE,CURRENT_TIME,CURRENT_TIMESTAMP,CURRENT_USER,CURSOR,DATABASE,DATABASES,DAY_HOUR,DAY_MICROSECOND,DAY_MINUTE,DAY_SECOND,DEC,DECIMAL,DECLARE,DEFAULT,DELAYED,DELETE,DESC,DESCRIBE,DETERMINISTIC,DISTINCT,DISTINCTROW,DIV,DOUBLE,DROP,DUAL,EACH,ELSE,ELSEIF,ENCLOSED,ESCAPED,EXISTS,EXIT,EXPLAIN,FALSE,FETCH,FLOAT,FLOAT4,FLOAT8,FOR,FORCE,FOREIGN,FROM,FULLTEXT,GOTO,GRANT,GROUP,HAVING,HIGH_PRIORITY,HOUR_MICROSECOND,HOUR_MINUTE,HOUR_SECOND,IF,IGNORE,IN,INDEX,INFILE,INNER,INOUT,INSENSITIVE,INSERT,INT,INT1,INT2,INT3,INT4,INT8,INTEGER,INTERVAL,INTO,IS,ITERATE,JOIN,KEY,KEYS,KILL,LABEL,LEADING,LEAVE,LEFT,LIKE,LIMIT,LINEAR,LINES,LOAD,LOCALTIME,LOCALTIMESTAMP,LOCK,LONG,LONGBLOB,LONGTEXT,LOOP,LOW_PRIORITY,MATCH,MEDIUMBLOB,MEDIUMINT,MEDIUMTEXT,MIDDLEINT,MINUTE_MICROSECOND,MINUTE_SECOND,MOD,MODIFIES,NATURAL,NOT,NO_WRITE_TO_BINLOG,NULL,NUMERIC,ON,OPTIMIZE,OPTION,OPTIONALLY,OR,ORDER,OUT,OUTER,OUTFILE,PRECISION,PRIMARY,PROCEDURE,PURGE,RAID0,RANGE,READ,READS,REAL,REFERENCES,REGEXP,RELEASE,RENAME,REPEAT,REPLACE,REQUIRE,RESTRICT,RETURN,REVOKE,RIGHT,RLIKE,SCHEMA,SCHEMAS,SECOND_MICROSECOND,SELECT,SENSITIVE,SEPARATOR,SET,SHOW,SMALLINT,SPATIAL,SPECIFIC,SQL,SQLEXCEPTION,SQLSTATE,SQLWARNING,SQL_BIG_RESULT,SQL_CALC_FOUND_ROWS,SQL_SMALL_RESULT,SSL,STARTING,STRAIGHT_JOIN,TABLE,TERMINATED,THEN,TINYBLOB,TINYINT,TINYTEXT,TO,TRAILING,TRIGGER,TRUE,UNDO,UNION,UNIQUE,UNLOCK,UNSIGNED,UPDATE,USAGE,USE,USING,UTC_DATE,UTC_TIME,UTC_TIMESTAMP,VALUES,VARBINARY,VARCHAR,VARCHARACTER,VARYING,WHEN,WHERE,WHILE,WITH,WRITE,X509,XOR,YEAR_MONTH,ZEROFILL,';
    $field = is_array($field) ? $field : explode(',', $field);
    $arr = [];
    foreach ($field as $v) {
        if (strpos($keys, ',' . strtoupper($v) . ',') !== false) {
            $arr[] = $v;
        }
    }
    return count($arr) === 0 ? false : $arr;
}

// ----------------------------------------------------------------------
//  生成登录ID
// ----------------------------------------------------------------------

function eLoginId($str)
{
    return \csn\Safe::encode(CSN_TIME . '.' . chr(mt_rand(97, 122)) . '.' . $str);
}

// ----------------------------------------------------------------------
//  登录ID解码
// ----------------------------------------------------------------------

function dLoginId($loginId, $time = 7200)
{
    $arr = explode('.', \csn\Safe::decode($loginId));
    return count($arr) === 3 && is_numeric($t = $arr[0]) ? ($t <= CSN_TIME && $t + $time > CSN_TIME) ? $arr[2] : 0 : false;
}

// ----------------------------------------------------------------------
//  转化字节
// ----------------------------------------------------------------------

function getSize($size, $unit = 'B', $decimals = 1, $targetUnit = 'auto')
{
    $units = array('B', 'KB', 'MB', 'GB', 'TB', 'PB', 'EB');
    $theUnit = array_search(strtoupper($unit), $units); //初始单位是哪个
    //判断是否自动计算
    $targetUnit === 'auto' || $targetUnit = array_search(strtoupper($targetUnit), $units);
    //循环计算
    while ($size >= 1024) {
        $size /= 1024;
        $theUnit++;
        if ($theUnit == $targetUnit)//已符合给定则退出循环吧！
            break;
    }
    return sprintf("%1\$.{$decimals}f", $size) . $units[$theUnit];
}

// ----------------------------------------------------------------------
//  文本处理
// ----------------------------------------------------------------------

function cut_html($str, $len)
{
    $str = strip_tags($str);
    $str = preg_replace('/\n/is', '', $str);
    $str = preg_replace('/ |　/is', '', $str);
    $str = preg_replace('/&nbsp;/is', '', $str);
    preg_match_all("/[\x01-\x7f]|[\xc2-\xdf][\x80-\xbf]|\xe0[\xa0-\xbf][\x80-\xbf]|[\xe1-\xef][\x80-\xbf][\x80-\xbf]|\xf0[\x90-\xbf][\x80-\xbf][\x80-\xbf]|[\xf1-\xf7][\x80-\xbf][\x80-\xbf][\x80-\xbf]/", $str, $t_string);
    if (count($t_string[0]) > $len) {
        $str = join('', array_slice($t_string[0], 0, $len)) . '...';
    } else {
        $str = join('', array_slice($t_string[0], 0, $len));
    }
    return $str;
}