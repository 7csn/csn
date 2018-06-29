<?php

namespace csn;

class Request
{

    static protected $uri;
    static protected $url;
    static protected $http;
    static protected $host;
    static protected $method;
    static protected $ip;
    static protected $get;
    static protected $post;

    // ----------------------------------------------------------------------
    //  模块、控制器、方法
    // ----------------------------------------------------------------------

    static $module;                 // 模块

    static $controller;             // 控制器

    static $action;                 // 方法

    // ----------------------------------------------------------------------
    //  实例
    // ----------------------------------------------------------------------

    protected static $instance;

    static function instance()
    {
        return is_null(self::$instance) ? self::$instance = new self : self::$instance;
    }

    // ----------------------------------------------------------------------
    //  响应
    // ----------------------------------------------------------------------

    static function response()
    {
        return Controller::model('Response');
    }

    // ----------------------------------------------------------------------
    //  解析路由
    // ----------------------------------------------------------------------

    static function parse()
    {
        File::copy(CSN_X . 'route.php', APP . 'route.php');
        Csn::need(APP . 'route.php');
        $request = self::instance();
        $request->path();




        return $request;
    }

    // ----------------------------------------------------------------------
    //  定位路由
    // ----------------------------------------------------------------------

    protected static $path;

    static function path()
    {
        return is_null(self::$path) ? self::$path = preg_replace('/^(\/[^\?&#]+)?.*?$/', '\1', self::uri()) : self::$path;
    }

    // ----------------------------------------------------------------------
    //  定位路由
    // ----------------------------------------------------------------------

    // 路由(是否包含入口)
    static function uri($pre = false)
    {
        if (is_null(self::$uri)) {
            $SCRIPT_NAME = $_SERVER['SCRIPT_NAME'];
            $index = strrpos($SCRIPT_NAME, '/');
            // 网址入口目录
            define('PRE_F', substr($SCRIPT_NAME, 0, $index));
            // 网址前缀入口
            define('PRE_B', Conf::web('rewrite') ? PRE_F : $SCRIPT_NAME);
            $me = substr($SCRIPT_NAME, $index);
            $len = strlen($me);
            $uri = isset($_SERVER['REQUEST_URI']) ? $_SERVER['REQUEST_URI'] : $_SERVER['PHP_SELF'] . (isset($_SERVER['argv']) ? $_SERVER['argv'][0] : $_SERVER['QUERY_STRING']);
            substr($uri, 0, $index) === PRE_F && $uri = substr($uri, $index);
            substr($uri, 0, $len) === $me && $uri = substr($uri, $len);
            self::$uri = $uri;
        }
        return ($pre ? PRE_B : '') . self::$uri;
    }

    // ----------------------------------------------------------------------
    //  定位路由
    // ----------------------------------------------------------------------

    // 请求网址
    static function url($http = false)
    {
        return is_null(self::$url) ? self::$url = ($http ? self::http(true) : '') . self::host() . self::uri(true) : self::$url;
    }

    // ----------------------------------------------------------------------
    //  定位路由
    // ----------------------------------------------------------------------

    // 协议名(是否用作URL)
    static function http($url = false)
    {
        return (is_null(self::$http) ? self::$http = empty($_SERVER['HTTPS']) || $_SERVER['HTTPS'] === 'off' ? 'http' : 'https' : self::$http) . ($url ? '://' : '');
    }

    // ----------------------------------------------------------------------
    //  定位路由
    // ----------------------------------------------------------------------

    // 域名端口
    static function host()
    {
        return is_null(self::$host) ? self::$host = isset($_SERVER['HTTP_HOST']) ? $_SERVER['HTTP_HOST'] : '' : self::$host;
    }

    // ----------------------------------------------------------------------
    //  定位路由
    // ----------------------------------------------------------------------

    // 获取请求方法
    static function method()
    {
        return is_null(self::$method) ? self::$method = $_SERVER['REQUEST_METHOD'] : self::$method;
    }

    // ----------------------------------------------------------------------
    //  定位路由
    // ----------------------------------------------------------------------

    // 返回GET数据
    static function get($key = false)
    {
        is_null(self::$get) && self::$get = Safe::initData($_GET);
        return $key ? key_exists($key, self::$get) ? self::$get[$key] : null : self::$get;
    }

    // ----------------------------------------------------------------------
    //  定位路由
    // ----------------------------------------------------------------------

    // 返回POST数据
    static function post($key = false)
    {
        is_null(self::$post) && self::$post = Safe::initData($_POST);
        return $key ? key_exists($key, self::$post) ? self::$post[$key] : null : self::$post;
    }

    // ----------------------------------------------------------------------
    //  定位路由
    // ----------------------------------------------------------------------

    // 返回客户端IP
    static function ip()
    {
        if (is_null(self::$ip)) {
            if (getenv('HTTP_CLIENT_IP') && strcasecmp(getenv('HTTP_CLIENT_IP'), 'unknown')) {
                self::$ip = getenv('HTTP_CLIENT_IP');
            } elseif (getenv('HTTP_X_FORWARDED_FOR') && strcasecmp(getenv('HTTP_X_FORWARDED_FOR'), 'unknown')) {
                self::$ip = getenv('HTTP_X_FORWARDED_FOR');
            } elseif (getenv('REMOTE_ADDR') && strcasecmp(getenv('REMOTE_ADDR'), 'unknown')) {
                self::$ip = getenv('REMOTE_ADDR');
            } elseif (isset($_SERVER['REMOTE_ADDR']) && $_SERVER['REMOTE_ADDR'] && strcasecmp(getenv('REMOTE_ADDR'), 'unknown')) {
                self::$ip = $_SERVER['REMOTE_ADDR'];
            } else {
                self::$ip = '0.0.0.0';
            }
        }
        return self::$ip;
    }

    // ----------------------------------------------------------------------
    //  定位路由
    // ----------------------------------------------------------------------

    // 判断是否移动端
    static function mobile()
    {
        if (isset($_SERVER['HTTP_X_WAP_PROFILE'])) {
            return true;
        }
        if (isset($_SERVER['HTTP_VIA']) && stristr($_SERVER['HTTP_VIA'], 'wap')) {
            return true;
        }
        if (isset($_SERVER['HTTP_USER_AGENT'])) {
            $p = 'nokia|sony|ericsson|mot|samsung|htc|sgh|lg|sharp|sie-|philips|panasonic|alcatel|lenovo|iphone|ipod|blackberry|meizu|android|netfront|symbian|ucweb|windowsce|palm|operamini|operamobi|openwave|nexusone|cldc|midp|wap|mobile|phone';
            if (preg_match("/(" . $p . ")/i", strtolower($_SERVER['HTTP_USER_AGENT']))) {
                return true;
            }
        }
        if (isset($_SERVER['HTTP_ACCEPT'])) {
            if ((strpos($_SERVER['HTTP_ACCEPT'], 'vnd.wap.wml') !== false) && (strpos($_SERVER['HTTP_ACCEPT'], 'text/html') === false || (strpos($_SERVER['HTTP_ACCEPT'], 'vnd.wap.wml') < strpos($_SERVER['HTTP_ACCEPT'], 'text/html')))) {
                return true;
            }
        }
        return false;
    }

}