<?php

namespace csn;

final class Redis extends Instance
{

    // ----------------------------------------------------------------------
    //  初始化配置信息
    // ----------------------------------------------------------------------

    // 主节点对象
    private $node;

    // 构造函数
    function construct()
    {
        list($this->writes, $this->ms) = Node::init(Config::data('redis.nodes'));
        $this->node = Config::data('redis.distribute') ? Distribute::instance($this->writes) : Random::instance($this->writes);
        return self::single();
    }

    // ----------------------------------------------------------------------
    //  主数据库
    // ----------------------------------------------------------------------

    // 列表
    private $writes;

    // 获取列表
    protected function writes()
    {
        return $this->writes;
    }

    // 获取连接
    function master($key = null)
    {
        return $this->connect($this->node->getNode($key));
    }

    // ----------------------------------------------------------------------
    //  主从数据库对照表
    // ----------------------------------------------------------------------

    private $ms;

    // ----------------------------------------------------------------------
    //  从数据库
    // ----------------------------------------------------------------------

    // 获取列表
    protected function reads($key = null)
    {
        return $this->ms[$this->master($key)];
    }

    // 获取连接
    function slave($key = null)
    {
        return $this->connect(Random::rand($this->reads($key)));
    }

    // ----------------------------------------------------------------------
    //  数据库连接
    // ----------------------------------------------------------------------

    // 列表
    private static $links = [];

    // 获取
    protected static function connect($address)
    {
        if (key_exists($address, self::$links)) {
            $link = self::$links[$address];
        } else {
            $link = new \Redis();
            call_user_func_array([$link, 'connect'], explode(':', $address));
            $auth = Config::data('redis.auth');
            key_exists($address, $auth) && call_user_func([$link, 'auth'], $auth[$address]);
            self::$links[$address] = $link;
        }
        return $link;
    }

    // ----------------------------------------------------------------------
    //  常规操作
    // ----------------------------------------------------------------------

    static function __callStatic($name, $args)
    {
        $alias = substr($name, 0, 1) === '_';
        $name = $alias ? substr($name, 1) : $name;
        if (in_array(strtoupper($name), ['DEL,DUMP,EXISTS,EXPIRE,EXPIREAT,PEXPIRE,PEXPIREAT,MOVE,PERSIST,RENAME,RENAMENX,SET,GETSET,SETBIT,SETEX,SETNX,SETRANGE,MSET,MSETNX,PSETEX,INCR,INCRBY,INCRBYFLOAT,
        DECR,DECRBY,APPEND,HDEL,HINCRBY,HINCRBYFLOAT,HMSET,HSET,HSETNX,HSCAN,BLPOP,
        BRPOP,BRPOPLPUSH,LINDEX,LINSERT,LPOP,LPUSH,LPUSHX,LREM,LSET,LTRIM,RPOP,RPOPLPUSH,RPUSH,RPUSHX,
        SADD,SDIFFSTORE,SINTERSTORE,SMOVE,SPOP,SREM,SUNIONSTORE,SSCAN,ZADD,ZINCRBY,ZINTERSTORE,ZREM,
        ZREMRANGEBYLEX,ZREMRANGEBYRANK,ZREMRANGEBYSCORE,ZUNIONSTORE,ZSCAN,PFADD,PFMERGE,PSUBSCRIBE,,,,,,,,,,,,,,,,,,,,,,,,,,,,,,,,,,,,,,,,,,,,,,,,,,,,,,,,,,,,,,,,,,,,,,,,,,,,,,,,,,,,,,,,,,,,,,,,,,,,,,,,,,,,,,,,,,,,,,,,,,,,,,,,,,,'])) {
            $node = 'master';
        } elseif (in_array(strtoupper($name), ['EXISTS,KEYS,PTTL,TTL,RANDOMKEY,TYPE,GET,GETRANGE,GETBIT,MGET,STRLEN,HEXISTS,HGET,HGETALL,HKEYS,HLEN,HMGET,HVALS,LLEN,
        LRANGE,SCARD,SDIFF,SINTER,SISMEMBER,SMEMBERS,SRANDMEMBER,SUNION,ZCARD,ZCOUNT,ZLEXCOUNT,ZRANGE,ZRANGEBYLEX,ZRANGEBYSCORE,ZRANK,
        ZREVRANGE,ZREVRANGEBYSCORE,ZREVRANK,ZSCORE,PFCOUNT,PUBSUB,,,,,,,,,,,,,,,,,,,,,,,,,,,,,,,,,,,,,,,,,,,,,,,,,,,,,,,,,,,,,,,,,,,,,,,,,,,,,,,,,,,,,,,,,,,,,,,,,,,,,,,,,,,,,,,,,,,,,,,,,,'])) {
            $node = 'slave';
        } else return;
        $key = $alias ? array_shift($args) : $args[0];
        $redis = self::instance()->{$node}($key);
        return call_user_func_array([$redis, $name], $args);
    }

    // ----------------------------------------------------------------------
    //  关闭指定连接
    // ----------------------------------------------------------------------

    static function keys()
    {

    }

    // ----------------------------------------------------------------------
    //  关闭指定连接
    // ----------------------------------------------------------------------

    static function close($address)
    {
        key_exists($address, self::$links) && self::$links[$address]->close();
    }

    // ----------------------------------------------------------------------
    //  关闭所有连接
    // ----------------------------------------------------------------------

    static function closeAll()
    {
        foreach (self::links as $link) {
            $link->close();
        }
    }

}