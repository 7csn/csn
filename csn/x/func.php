<?php

namespace app\c;
// 缓存
function system($name, $func = null) {
    static $system = [];
    return key_exists($name, $system) ? $system[$name] : $system[$name] = call_user_func_array($func, (new \ReflectionFunction($func))->getParameters());
}

// 导入视图
function view()
{
    return \csn\Csn::obj('View', func_get_args());
}

// 获取静态页
function cache()
{
    return call_user_func_array('\csn\View::getCache', func_get_args());
}

// 模板引入方法
function viewInclude($path, $data, $time)
{
    $cache = cache($path, $time) ?: view($path, $data, $time)->getView();
    is_null($data) || extract($data);
    include $cache;
}

// IP转化数字
function intIp($ip)
{
    $str = '';
    foreach (explode('.', $ip) as $v) {
        $str .= (($v >= 16 ? '' : '0') . dechex($v));
    }
    return hexdec($str);
}

// 解析数字IP
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

// 获取IP地址
function ipArea($ip = '')
{
    $area = new \stdClass();
    $json = json_decode(file_get_contents('http://int.dpool.sina.com.cn/iplookup/iplookup.php?format=json&ip=' . $ip));
    if (is_object($json)) {
        $area->province = isset($json->province) ? $json->province : '未知';
        $area->city = isset($json->city) ? $json->city : '未知';
    } else {
        $area->province = '未知';
        $area->city = '未知';
    }
    return $area;
}

// 判断是否mysql关键字
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

// 密码加密
function pwdEn($pwd, $l = 20)
{
    $l = $l > 32 ? 32 : $l;
    return substr(md5(substr(md5($pwd), 0, $l)), 0, $l);
}

// 微信二维码
function qrcode($url, $size = 4)
{
    \csn\y\QRcode::png($url, false, QR_ECLEVEL_L, $size, 2, false, 0xFFFFFF, 0x000000);
}

// 返回二维码
function ewm($url)
{
    return 'http://qr.topscan.com/api.php? bg=f3f3f3& fg=000& gc=222222& el=l& w=200& m=10& text=' . $url;
}

// 转化字节
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

// 文本处理
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