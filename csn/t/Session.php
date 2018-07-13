<?php

namespace csn;

class Session extends Instance
{

    static $init;   // session配置初始化

    // 配置信息初始化
    static function init()
    {
        if (is_null(self::$init)) {
            $session = Config::data('session');
            if ($session['memcache']) {
                ini_set('session.save_handler', 'memcache');
                ini_set('session.save_path', 'tcp://' . join(';tcp://', Config::data('memcache.nodes')));
            }
            foreach ($session['cookie'] as $set => $val) {
                ini_set('session.' . $set, $val);
            }
            self::$init = true;
        }
    }

    // 开启session
    static function start()
    {
        self::init();
        isset($_SESSION) || session_start();
    }

    // 获取session
    static function get($key = false)
    {
        self::start();
        return $key ? key_exists($key, $_SESSION) ? $_SESSION[$key] : null : $_SESSION;
    }

    // 设置session
    static function set($key, $val)
    {
        self::start();
        $_SESSION[$key] = $val;
    }

    // 删除session
    static function del($key = false)
    {
        self::start();
        if ($key) {
            if (key_exists($key, $_SESSION)) {
                unset($_SESSION[$key]);
            }
        } else {
            session_destroy();
        }
    }

    // 更新session_id
    static function renew($force = false)
    {
        self::init();
        session_regenerate_id($force);
    }

    // 获取session_id
    static function id()
    {
        self::start();
        return session_id();
    }

    // 获取/设置session名称
    static function name()
    {
        self::init();
        return call_user_func_array('session_name', func_get_args());
    }

}