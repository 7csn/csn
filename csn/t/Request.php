<?php


namespace csn;

final class Request extends Instance
{

    // ----------------------------------------------------------------------
    //  构造函数：初始化路由
    // ----------------------------------------------------------------------

    function construct()
    {
        $scriptName = $_SERVER['SCRIPT_NAME'];
        $index = strrpos($scriptName, '/');
        // 前缀URL目录：前端、后端
        define('WEB_PRE', substr($scriptName, 0, $index));
        define('PHP_PRE', Config::web('rewrite') ? WEB_PRE : $scriptName);
        $this->query = $_SERVER['QUERY_STRING'];
        $this->uri = isset($_SERVER['REQUEST_URI']) ? $_SERVER['REQUEST_URI'] : $_SERVER['PHP_SELF'] . ($_SERVER['QUERY_STRING'] ? '?' . $_SERVER['QUERY_STRING'] : '');
        $uri = preg_replace('/\.html$/', '', preg_replace("/^(\/[^\?&#]+)?.*?$/", '\1', $this->uri));
        $len = strlen($scriptName);
        $start = substr($uri, 0, $len) === $scriptName ? $len : $index + 1;
        $this->path = substr($uri, $start) ?: '/';
        $this->protocol = empty($_SERVER['HTTPS']) || $_SERVER['HTTPS'] === 'off' ? 'http' : 'https';
        $this->host = isset($_SERVER['HTTP_HOST']) ? $_SERVER['HTTP_HOST'] : '';
        $this->url = $this->protocol . '://' . $this->host . $this->uri;
        $this->method = $_SERVER['REQUEST_METHOD'];
        $this->isAjax = (isset($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) == 'xmlhttprequest') || key_exists(Config::web('ajax'), $_POST) || key_exists(Config::web('ajax'), $_GET);
        $this->ip = $this->ip();
        $this->mobile = $this->mobile();
        return self::single();
    }

    // ----------------------------------------------------------------------
    //  协议头：是否用作网址
    // ----------------------------------------------------------------------

    private $protocol;

    function protocol($url = false)
    {
        return $this->protocol . ($url ? '://' : '');
    }

    // ----------------------------------------------------------------------
    //  主机名
    // ----------------------------------------------------------------------

    private $host;

    function host()
    {
        return $this->host;
    }

    // ----------------------------------------------------------------------
    //  基本路由
    // ----------------------------------------------------------------------

    private $uri;

    function uri()
    {
        return $this->uri;
    }

    // ----------------------------------------------------------------------
    //  关键路由
    // ----------------------------------------------------------------------

    private $path;

    function path()
    {
        return $this->path;
    }

    // ----------------------------------------------------------------------
    //  当前网址：是否对外
    // ----------------------------------------------------------------------

    private $url;

    function url($head = false)
    {
        return $head ? $this->protocol(true) . $this->host() : $this->url;
    }

    // ----------------------------------------------------------------------
    //  客户端IP
    // ----------------------------------------------------------------------

    private $ip;

    function ip()
    {
        if (is_null($this->ip)) {
            if (getenv('HTTP_CLIENT_IP') && strcasecmp(getenv('HTTP_CLIENT_IP'), 'unknown')) {
                $this->ip = getenv('HTTP_CLIENT_IP');
            } elseif (getenv('HTTP_X_FORWARDED_FOR') && strcasecmp(getenv('HTTP_X_FORWARDED_FOR'), 'unknown')) {
                $this->ip = getenv('HTTP_X_FORWARDED_FOR');
            } elseif (getenv('REMOTE_ADDR') && strcasecmp(getenv('REMOTE_ADDR'), 'unknown')) {
                $this->ip = getenv('REMOTE_ADDR');
            } elseif (isset($_SERVER['REMOTE_ADDR']) && $_SERVER['REMOTE_ADDR'] && strcasecmp(getenv('REMOTE_ADDR'), 'unknown')) {
                $this->ip = $_SERVER['REMOTE_ADDR'];
            } else {
                $this->ip = '0.0.0.0';
            }
        }
        return $this->ip;
    }

    // ----------------------------------------------------------------------
    //  判断是否移动端
    // ----------------------------------------------------------------------

    private $mobile;

    function mobile()
    {
        if (is_null($this->mobile)) {
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
        return $this->mobile;
    }

    // ----------------------------------------------------------------------
    //  请求方式
    // ----------------------------------------------------------------------

    private $method;

    function method()
    {
        return $this->method;
    }

    // ----------------------------------------------------------------------
    //  是否POST提交
    // ----------------------------------------------------------------------

    function isPost()
    {
        return $this->method() === 'POST';
    }

    // ----------------------------------------------------------------------
    //  是否AJAX提交
    // ----------------------------------------------------------------------

    private $isAjax;

    function isAjax()
    {
        return $this->isAjax;
    }

    // ----------------------------------------------------------------------
    //  参数值：QueryString
    // ----------------------------------------------------------------------

    private $query;

    function query($name, $default = null)
    {
        return preg_match("/(^|&)$name=([^&]*)(&|$)/", $this->query, $match) ? urldecode($match[2]) : $default;
    }

    // ----------------------------------------------------------------------
    //  参数值：POST
    // ----------------------------------------------------------------------

    function post($key = false)
    {
        is_null(self::$post) && self::$post = Safe::initData($_POST);
        return $key ? key_exists($key, self::$post) ? self::$post[$key] : null : self::$post;
    }

    function param($key = false)
    {

    }

    // ----------------------------------------------------------------------
    //  生成URL
    // ----------------------------------------------------------------------

    function makeUrl($url, $full = false)
    {
        return ($full ? $this->url(true) : '') . PHP_PRE . '/' . ltrim($url, '/');
    }

    // ----------------------------------------------------------------------
    //  响应
    // ----------------------------------------------------------------------

    function response()
    {
        return Response::instance();
    }

}