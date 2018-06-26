<?php

namespace csn\y;

class Wx
{

    protected static $wx = [];
    protected static $jsapi_ticket = APP . 'jsapi_ticket.json';
    protected static $access_token = APP . 'access_token.json';

    // 获取微信相关配置
    protected static function get($name = false)
    {
        self::$wx || self::$wx = \csn\Conf::wx();
        if (!$name) return self::$wx;
        return key_exists($name, self::$wx) ? self::$wx[$name] : \csn\Exp::close('微信项' . $name . '配置不正确')->E();
    }

    static function getSignPackage()
    {
        $jsapiTicket = self::getJsApiTicket();
        $url = "http://$_SERVER[HTTP_HOST]$_SERVER[REQUEST_URI]";
        $timestamp = time();
        $nonceStr = self::createNonceStr();
        // 这里参数的顺序要按照 key 值 ASCII 码升序排序
        $string = "jsapi_ticket=$jsapiTicket&noncestr=$nonceStr&timestamp=$timestamp&url=$url";
        $signature = sha1($string);
        $signPackage = [
            "appId" => self::get('APPID'),
            "nonceStr" => $nonceStr,
            "timestamp" => $timestamp,
            "url" => $url,
            "signature" => $signature,
            "rawString" => $string
        ];
        return $signPackage;
    }

    protected static function createNonceStr($length = 16)
    {
        $chars = "abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789";
        $str = "";
        for ($i = 0; $i < $length; $i++) {
            $str .= substr($chars, mt_rand(0, strlen($chars) - 1), 1);
        }
        return $str;
    }

    protected static function getJsApiTicket()
    {
        // jsapi_ticket 应该全局存储与更新，以下代码以写入到文件中做示例
        if (is_file(self::$jsapi_ticket)) {
            $data = json_decode(file_get_contents(self::$jsapi_ticket));
        } else {
            $data = new \stdClass();
            $data->expire_time = 0;
        }
        if ($data->expire_time < time()) {
            $accessToken = self::getAccessToken();
            $url = "https://api.weixin.qq.com/cgi-bin/ticket/getticket?type=jsapi&access_token=$accessToken";
            $res = json_decode(self::httpGet($url));
            $ticket = $res->ticket;
            if ($ticket) {
                $data->expire_time = time() + 7000;
                $data->jsapi_ticket = $ticket;
                $fp = fopen(self::$jsapi_ticket, "w");
                fwrite($fp, json_encode($data));
                fclose($fp);
            }
        } else {
            $ticket = $data->jsapi_ticket;
        }
        return $ticket;
    }

    protected static function getAccessToken()
    {
        // access_token 应该全局存储与更新，以下代码以写入到文件中做示例
        if (is_file(self::$access_token)) {
            $data = json_decode(file_get_contents(self::$access_token));
        } else {
            $data = new \stdClass();
            $data->expire_time = 0;
        }
        if ($data->expire_time < time()) {
            $url = "https://api.weixin.qq.com/cgi-bin/token?grant_type=client_credential&appid=" . self::get('APPID') . "&secret=" . self::get('APPSECRET');
            $res = json_decode(\csn\Http::simple($url));
            $access_token = $res->access_token;
            if ($access_token) {
                $data->expire_time = time() + 7000;
                $data->access_token = $access_token;
                $fp = fopen(self::$access_token, "w");
                fwrite($fp, json_encode($data));
                fclose($fp);
            }
        } else {
            $access_token = $data->access_token;
        }
        return $access_token;
    }

}