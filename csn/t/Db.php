<?php

namespace csn;

class Db
{
    static private $dbs = [];
    static private $me;
    static private $key;
    static private $pdo;
    static private $arr = [];
    public $sql_arr = [];
    static public $sql;
    static public $tbInfo = [];

    // 增加操作
    function insert($f = null, $c = false)
    {
        is_bool($f) ? $c = $f : $this->field($f);
        $fields = $this->parseField();
        $values = '';
        $bind = [];
        foreach ($fields as $k => $v) {
            $value = '';
            foreach ($v as $kk => $vv) {
                $value .= ':' . $kk . '__' . $k . ',';
                $bind[$kk . '__' . $k] = is_array($vv) ? serialize($vv) : $vv;
            }
            $values .= '(' . rtrim($value, ',') . '),';
        }
        $sql = 'INSERT INTO' . $this->parseTable() . ' (`' . implode('`,`', keys(current($fields))) . '`) VALUES ' . rtrim($values, ',');
        $b = self::$pdo->prepare($sql)->execute($bind);
        $this->backEnd($c ? false : [$sql, $bind]);
        return $b;
    }

    // 删除操作
    function delete($w = null, $b = null, $c = false)
    {
        if (is_bool($w)) {
            $c = $w;
            $w = $b = null;
        } elseif (is_bool($b)) {
            $c = $b;
            $b = null;
        }
        $this->where($w, $b);
        $sql = 'DELETE FROM' . $this->parseTable() . $this->parseWhere() . $this->makeSql('group') . $this->makeSql('order') . $this->makeSql('limit');
        $bind = $this->parseBind();
        $b = empty($bind) ? self::$pdo->exec($sql) : self::$pdo->prepare($sql)->execute($bind);
        $this->backEnd($c ? false : [$sql, $bind]);
        return $b;
    }

    // 修改操作
    function update($f = null, $c = false)
    {
        is_bool($f) ? $c = $f : $this->field($f);
        $field = current($this->parseField());
        empty($field) && Exp::end('Sql字段元素不正确！');
        $bind = $this->parseBind();
        $set = [];
        foreach ($field as $k => $v) {
            $set[] = $k . ' = :' . $k . '__';
            $bind[$k . '__'] = is_array($v) ? serialize($v) : $v;
        }
        $sql = 'UPDATE' . $this->parseTable() . ' SET ' . implode(',', $set) . $this->parseWhere() . $this->makeSql('group') . $this->makeSql('order') . $this->makeSql('limit');
        $b = self::$pdo->prepare($sql)->execute($bind);
        $this->backEnd($c ? false : [$sql, $bind]);
        return $b;
    }

    // 查询操作
    function select($m = \PDO::FETCH_OBJ, $c = false)
    {
        if (is_bool($m)) {
            $c = $m;
            $m = 2;
        }
        $sql = 'SELECT' . $this->makeSql('field') . ' FROM' . $this->parseTable() . $this->parseWhere() . $this->makeSql('group') . $this->makeSql('order') . $this->makeSql('limit');
        $bind = $this->parseBind();
        if (empty($bind)) {
            $sth = self::$pdo->query($sql);
        } else {
            $sth = self::$pdo->prepare($sql);
            $sth->execute($bind);
        }
        $sth->setFetchMode($m);
        $arr = [];
        while ($v = $sth->fetch()) {
            $arr[] = $v;
        }
        $sth = null;
        $this->backEnd($c ? false : [$sql, $bind]);
        return $arr;
    }

    // 查询一条数据
    function find()
    {
        $this->sql_arr['limit'] = empty($this->sql_arr['limit']) ? 1 : ((($l = $this->sql_arr['limit']) && ($l = is_array($l) ? implode(',', $l) : $l)) && ($i = strpos($l, ',')) === false ? 1 : (substr($l, 0, $i + 1) . '1'));
        $rm = new \ReflectionMethod($this, 'select');
        $res = $rm->invokeArgs($this, func_get_args());
        return current($res) ?: [];
    }

    // 查询一个字段
    function one($f = null, $c = false)
    {
        if (is_bool($f)) {
            $c = $f;
            $f = null;
        }
        is_null($f) || $this->field($f);
        $find = $this->find(2, $c);
        return is_null($f) ? current($find) ?: false : (key_exists($f, $find) ? $find[$f] : false);
    }

    // 增删改
    function exec($sql, $bind = [], $close = false)
    {
        if (is_bool($bind)) {
            $close = $bind;
            $bind = [];
        }
        $b = $bind ? self::$pdo->prepare($sql)->execute($bind) : self::$pdo->exec($sql);
        $close ? self::close() : self::$sql = $sql;
        return $b;
    }

    // 查询
    function query($sql, $bind = [], $close = false)
    {
        if (is_bool($bind)) {
            $close = $bind;
            $bind = [];
        }
        if (empty($bind)) {
            $sth = self::$pdo->query($sql);
        } else {
            $sth = self::$pdo->prepare($sql);
            $sth->execute($bind);
        }
        $sth->setFetchMode(\PDO::FETCH_OBJ);
        $arr = [];
        while ($v = $sth->fetch()) {
            $arr[] = $v;
        }
        $sth = null;
        $close ? self::close() : self::$sql = $sql;
        return $arr;
    }

    // 获取一条数据
    function getOne()
    {
        $rm = new \ReflectionMethod($this, 'query');
        $res = $rm->invokeArgs($this, func_get_args());
        return current($res) ?: new \stdClass();
    }

    // 获取单个字段值
    function getField()
    {
        $rm = new \ReflectionMethod($this, 'getOne');
        $res = $rm->invokeArgs($this, func_get_args());
        return current($res) ?: null;
    }

    // 事务处理
    function commit()
    {
        $pdo = self::$pdo;
        $pdo->beginTransaction();
        DbInfo::setTransaction(true);
        $args = func_get_args();
        $fn = $args[0];
        $args[0] = $pdo;
        $b = call_user_func_array($fn, $args);
        $pdo->{$b ? 'rollBack' : 'commit'}();
        DbInfo::setTransaction();
        return $b;
    }

    // 单例对象
    static function me()
    {
        if (is_null(self::$me)) {
            self::$me = new self();
        }
        return self::$me;
    }

    // 获取连接
    static function connect($k = 0)
    {
        if (self::$key !== $k) {
            if (!isset(self::$dbs[$k])) {
                $db = Conf::data('db');
                if (key_exists($k, $db)) {
                    $con = $db[$k];
                    try {
                        $pdo = new \PDO("mysql:host={$con['dh']}", $con['du'], $con['dp']);
                        $pdo->query('SET NAMES utf8');
                        self::$arr[$k] = ['db' => $con['db'], '_db' => null, 'th' => $con['dth']];
                        self::$dbs[$k] = $pdo;
                        self::$key = $k;
                    } catch (\PDOException $e) {
                        Exp::end('[PDO]：' . str_replace("\n", '', iconv("GB2312// IGNORE", "UTF-8", $e->getMessage())));
                    }
                } else {
                    Exp::end('找不到数据库配置键' . $k);
                }
            }
            self::$pdo = self::$dbs[$k];
        }
        return self::me();
    }

    // 指定数据库
    function db($db = null)
    {
        is_null($db) && $db = self::$arr[self::$key]['db'];
        if ($db === self::$arr[self::$key]['_db']) {
            self::$tbInfo[self::$key . '@' . $db] = [];
        } else {
            self::$arr[self::$key]['_db'] = $db;
            self::$tbInfo[self::$key . '@' . $db] = [];
            self::$pdo->query('use ' . $db);
        }
        return $this;
    }

    // 清空表;自增初始化
    function truncate($tb, $th = null)
    {
        if (is_null($th)) {
            $th = self::$arr[self::$key]['th'];
        }
        self::$pdo->query('TRUNCATE TABLE ' . $th . $tb);
        return $this;
    }

    // 连接表
    function table($t, $th = null)
    {
        is_array($t) || $t = is_null($th) ? [$t] : [$t => $th];
        $this->sql_arr['table'] = $t;
        return $this;
    }

    // 字段
    function field($f, $b = null)
    {
        empty($f) || ($this->bind($b)->sql_arr['field'] = $f);
        return $this;
    }

    // 条件
    function where($w, $b = null)
    {
        empty($w) || ($this->bind($b)->sql_arr['where'] = $w);
        return $this;
    }

    // 子条件
    function having($h)
    {
        $this->sql_arr['having'] = $h;
        return $this;
    }

    // 归类
    function group($g)
    {
        $this->sql_arr['group'] = $g;
        return $this;
    }

    // 顺序
    function order($o)
    {
        $this->sql_arr['order'] = $o;
        return $this;
    }

    // 限制
    function limit($l)
    {
        $this->sql_arr['limit'] = $l;
        return $this;
    }

    // 编译
    private function bind($b = null)
    {
        is_array($b) && $this->sql_arr['bind'] = key_exists('bind', $this->sql_arr) ? array_merge($this->sql_arr['bind'], $b) : $b;
        return $this;
    }

    // 条件处理
    private function parseTable()
    {
        $tbs = ' ';
        $th = self::$arr[self::$key]['th'];
        $tb = $this->tablePart();
        if (count($tb) > 1) {
            foreach ($tb as $k => $v) {
                $tbs .= is_int($k) ? "`{$th}{$v}` AS `{$v}`," : "`{$v}{$k}` AS `{$k}`,";
            }
        } else {
            foreach ($tb as $k => $v) {
                $tbs .= is_int($k) ? "`{$th}{$v}`," : "`{$v}{$k}`,";
            }
        }
        return rtrim($tbs, ',');
    }

    // 字段数组处理
    private function parseField()
    {
        $tbInfo = [];
        $th = self::$arr[self::$key]['th'];
        $tbk = self::$key . '@' . self::$arr[self::$key]['_db'];
        $tbi = self::$tbInfo[$tbk];
        foreach ($this->tablePart() as $k => $v) {
            $tbn = is_int($k) ? "{$th}{$v}" : "{$v}{$k}";
            key_exists($tbn, $tbi) || $tbInfo[] = self::$tbInfo[$tbk][$tbn] = self::$pdo->query(" SHOW COLUMNS FROM `$tbn` ")->fetchAll(\PDO::FETCH_ASSOC);
        }
        $arr = [];
        $fd = $this->fieldPart();
        foreach (is_array(current($fd)) ? $fd : [$fd] as $v) {
            $_arr = [];
            foreach ($tbInfo as $vv) {
                foreach ($vv as $vvv) {
                    $vvv['Extra'] === 'auto_increment' || key_exists($f = $vvv['Field'], $v) && $_arr[$f] = $v[$f];
                }
            }
            $arr[] = $_arr;
        }
        return $arr;
    }

    // 条件数组处理
    private function parseWhere()
    {
        return key_exists('where', $this->sql_arr) && ($w = $this->sql_arr['where']) ? (' WHERE ' . (is_array($w) ? implode(' ', $w) : $w)) : '';
    }

    // 获取编译数组
    private function parseBind()
    {
        return key_exists('bind', $this->sql_arr) ? $this->sql_arr['bind'] : [];
    }

    // 获取表条件
    private function tablePart()
    {
        key_exists('table', $this->sql_arr) || Exp::end('Sql表元素不存在！');
        return $this->sql_arr['table'];
    }

    // 获取字段条件
    private function fieldPart()
    {
        key_exists('field', $this->sql_arr) || Exp::end('Sql表字段不存在！');
        return $this->sql_arr['field'];
    }

    // 获取指定部分sql语句
    private function makeSql($k)
    {
        if (isset($this->sql_arr[$k])) {
            switch ($k) {
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
            }
            $m = $this->sql_arr[$k];
            return ' ' . $i . (is_array($m) ? implode(',', $m) : $m);
        } else {
            return $k === 'field' ? ' *' : '';
        }
    }

    // 后续操作
    private function backEnd($o)
    {
        $this->sql_arr = [];
        if ($o) {
            list($sql, $bind) = $o;
            foreach ($bind as $k => $v) {
                is_string($v) && $v = "'$v'";
                $sql = str_replace($k, $v, str_replace(':' . $k, $v, $sql));
            }
            self::$sql = $sql;
        } else {
            self::close();
        }
    }

    // 关闭当前连接
    static function close()
    {
        self::$pdo = self::$dbs[self::$key] = self::$key = self::$sql = null;
    }

    // 关闭所有连接
    static function closeAll()
    {
        is_null(self::$key) || self::close();
        foreach (self::$dbs as &$v) {
            $v = null;
        }
        self::$dbs = [];
    }

}