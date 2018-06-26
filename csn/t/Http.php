<?php

namespace csn;

class Http
{

    protected $host;        // 连接地址
    protected $file;        // 请求文件
    protected $no;          // 错误号
    protected $str;         // 错误信息
    protected $connect;    // 连接
    protected $boundary;   // 文件分隔符

    // 文件类型
    protected static $mimeType = [
        'xml' => 'application/xml,text/xml,application/x-xml',
        'json' => 'application/json,text/x-json,application/jsonrequest,text/json',
        'js' => 'text/javascript,application/javascript,application/x-javascript',
        'css' => 'text/css',
        'rss' => 'application/rss+xml',
        'yaml' => 'application/x-yaml,text/yaml',
        'atom' => 'application/atom+xml',
        'pdf' => 'application/pdf',
        'text' => 'text/plain',
        'png' => 'image/png',
        'jpg' => 'image/jpg,image/jpeg,image/pjpeg',
        'gif' => 'image/gif',
        'csv' => 'text/csv',
        'html' => 'text/html,application/xhtml+xml,*/*',
    ];

    // 网络连接对象
    function __construct($url, $time = 30)
    {
        preg_match_all('/^(https?:\/\/|ssl:\/\/)?([\w\.]+)(:([\d]+))?(\/.*?)?$/', $url, $m);
        $this->host = $m[2][0];
        $this->file = $m[5][0] ?: '/';
        $port = $m[4][0] ? (int)$m[4][0] : ($m[1][0] === 'https://' ? 443 : 80);
        try {
            $this->connect = fsockopen($this->host, $port, $this->no, $this->str, $time);
        } catch (\Exception $e) {
            $this->str = $this->str ? str_replace('', '', iconv("GB2312//IGNORE", "UTF-8", $this->str)) : $e->getMessage();
        } catch (\ErrorException $e) {
            $this->str = $this->str ? str_replace('', '', iconv("GB2312//IGNORE", "UTF-8", $this->str)) : $e->getMessage();
        }
    }

    // 支持方法
    function options($file = null)
    {
        $res = $this->exec('OPTIONS', $file);
        return $res ? preg_replace('/(.+)Allow: ([A-Z,]+)(\b.+)/us', '\2', $res) : null;
    }

    // 请求资源
    function __call($name, $args)
    {
        if (in_array($name, ['head', 'get', 'post', 'file', 'put', 'delete', 'trace', 'connect'])) {
            array_unshift($args, strtoupper($name));
            return call_user_func_array([$this, 'exec'], $args);
        } else {
            Exp::end('http方法' . $name . '不存在');
        }
    }

    // 发起请求
    protected function exec($method, $file = null, $data = [], $head = [])
    {
        is_null($file) && $file = $this->file;
        if (is_null($this->connect)) return null;
        $fsp = $this->connect;
        array_unshift($head, 'Host: ' . $this->host, 'Connection: close');
        if ($method === 'FILE') {
            $head[] = 'Content-Type: multipart/form-data, boundary=' . $this->boundary('key');
            $data = $this->parseFile($data);
            $method = 'POST';
        } else {
            $method === 'POST' ? (empty($data) || $head[] = 'Content-Type: application/x-www-form-urlencoded') : ($method === 'OPTIONS' && $file = '/' . uniqid());
            $data = implode('&', $data);
        }
        array_unshift($head, "$method $file HTTP/1.1");
        $len = strlen($data);
        $len > 0 && $head[] = 'Content-Length: ' . $len;
        array_push($head, '', $data);
        $puts = implode("\r\n", $head);
        fputs($fsp, $puts);
        $gets = '';
        while (!feof($fsp)) {
            $gets .= fgets($fsp, 1024);
        }
        fclose($fsp);
        return $gets;
    }

    // 整理上传信息
    function parseFile($data)
    {
        if (empty($data)) return '';
        $res = [];
        foreach ($data as $v) {
            $res[] = $this->formData($v);
        }
        $res[] = '--' . $this->boundary('key') . '--';
        return implode("\r\n", $res);
    }

    // 表单文件数据处理
    function formData($data)
    {
        $arr = ['--' . $this->boundary('key')];
        $isFile = key_exists('file', $data);
        $arr[] = 'content-disposition: form-data; name="' . $data['name'] . '"' . ($isFile ? '; filename="' . $data['file'] . '"' : '');
        $isFile && $arr[] = 'Content-Type: ' . strtok(self::$mimeType[$data['type']], ',');
        key_exists('binary', $data) && $arr[] = 'Content-Transfer-Encoding: binary';
        $arr[] = '';
        $arr[] = $data['content'];
        return implode("\r\n", $arr);
    }

    // 获取分隔符
    function boundary()
    {
        return is_null($this->boundary) ? $this->boundary = md5(Safe::get('key') . uniqid()) : $this->boundary;
    }

    // 简单方式抓取网页
    static function simple($url, $time = 30)
    {
        if (strpos($url, 'https') === 0) {
            $ch = curl_init();
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_TIMEOUT, $time);
            curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
            curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
            curl_setopt($ch, CURLOPT_URL, $url);
            $res = curl_exec($ch);
            curl_close($ch);
            return $res;
        } else {
            return file_get_contents($url);
        }
    }

}