<?php


namespace csn;

final class Request extends Instance
{

    // ----------------------------------------------------------------------
    // 构造方法：解析路由
    // ----------------------------------------------------------------------

    function construct()
    {
        $SCRIPT_NAME = $_SERVER['SCRIPT_NAME'];
        $index = strrpos($SCRIPT_NAME, '/');
        // 网址入口目录
        define('PRE_F', substr($SCRIPT_NAME, 0, $index));
        // 网址前缀入口
        define('PRE_B', Conf::web('rewrite') ? PRE_F : $SCRIPT_NAME);
        $file = substr($SCRIPT_NAME, $index);
        $len = strlen($file);
        $uri = isset($_SERVER['REQUEST_URI']) ? $_SERVER['REQUEST_URI'] : $_SERVER['PHP_SELF'] . $_SERVER['QUERY_STRING'];
        substr($uri, 0, $index) === PRE_F && $uri = substr($uri, $index);
        substr($uri, 0, $len) === $file && $uri = substr($uri, $len);
        // 路由
        self::$uri = $uri;
        self::$path = preg_replace('/^(\/[^\?&#]+)?.*?$/', '\1', $uri);
        self::$protocol = empty($_SERVER['HTTPS']) || $_SERVER['HTTPS'] === 'off' ? 'protocol' : 'https';
        self::$host = isset($_SERVER['HTTP_HOST']) ? $_SERVER['HTTP_HOST'] : '';
        self::$url = self::$host . PRE_B . self::$uri;
        return self::single();
    }

    // ----------------------------------------------------------------------
    //  响应
    // ----------------------------------------------------------------------

    function response()
    {
        return Response::instance();
    }

    // ----------------------------------------------------------------------
    //  协议头：是否用作网址
    // ----------------------------------------------------------------------

    private static $protocol;

    function protocol($url = false)
    {
        return self::$protocol . ($url ? '://' : '');
    }

    // ----------------------------------------------------------------------
    //  主机名
    // ----------------------------------------------------------------------

    private static $host;

    function host()
    {
        return self::$host;
    }

    // ----------------------------------------------------------------------
    //  基本路由：是否包含入口
    // ----------------------------------------------------------------------

    private static $uri;

    function uri($pre = false)
    {
        return ($pre ? PRE_B : '') . self::$uri;
    }

    // ----------------------------------------------------------------------
    //  关键路由
    // ----------------------------------------------------------------------

    private static $path;

    function path()
    {
        return self::$path;
    }

    // ----------------------------------------------------------------------
    //  当前网址：是否包含协议头
    // ----------------------------------------------------------------------

    private static $url;

    function url($protocol = false)
    {
        return ($protocol ? $this->protocol(true) : '') . self::$url;
    }

    // ----------------------------------------------------------------------
    //  请求方式
    // ----------------------------------------------------------------------

    // 请求方法
    function method()
    {
        return $_SERVER['REQUEST_METHOD'];
    }

    // 是否POST提交
    function isPost()
    {
        return $this->method() === 'POST';
    }

    // 是否AJAX提交
    function isAjax()
    {

    }

    // ----------------------------------------------------------------------
    //  参数：GET、POST、REQUEST
    // ----------------------------------------------------------------------

    function get($key = false)
    {
//        return $key ?
        is_null(self::$get) && self::$get = Safe::initData($_GET);
        return $key ? key_exists($key, self::$get) ? self::$get[$key] : null : self::$get;
    }

    function post($key = false)
    {
        is_null(self::$post) && self::$post = Safe::initData($_POST);
        return $key ? key_exists($key, self::$post) ? self::$post[$key] : null : self::$post;
    }

    function param($key = false)
    {

    }

    // ----------------------------------------------------------------------
    //  客户端IP
    // ----------------------------------------------------------------------

    private static $ip;

    function ip()
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
    //  判断是否移动端
    // ----------------------------------------------------------------------

    function mobile()
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