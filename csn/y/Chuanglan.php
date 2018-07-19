<?php

namespace csn\y;

use csn\Config;
use csn\Data;
use csn\Instance;

class Chuanglan extends Instance
{

    // ----------------------------------------------------------------------
    //  短信对象
    // ----------------------------------------------------------------------

    // 配置对象
    private $config;

    // 构造函数
    function construct()
    {
        $this->config = new Data();
        $this->config->usr = Config::chuanglan('usr');
        $this->config->pwd = Config::chuanglan('pwd');
        $this->config->url = Config::chuanglan('url');
        $this->config->var_url = Config::chuanglan('var_url');
        $this->config->balance_url = Config::chuanglan('balance_url');
        return self::single();
    }

    // ----------------------------------------------------------------------
    //  发送短信
    // ----------------------------------------------------------------------

    function sendSMS($tel, $msg)
    {
        return $this->curlPost($this->config->url, ['account' => $this->config->usr, 'password' => $this->config->pwd, 'msg' => urlencode($msg), 'phone' => $tel, 'report' => 'true']);
    }

    // ----------------------------------------------------------------------
    //  发送变量短信
    // ----------------------------------------------------------------------

    function sendVariableSMS($msg, $params)
    {
        return $this->curlPost($this->chuanglan_config['API_VARIABLE_URL'], ['account' => $this->config->usr, 'password' => $this->config->pwd, 'msg' => $msg, 'params' => $params, 'report' => 'true']);
    }

    // ----------------------------------------------------------------------
    //  查询额度
    // ----------------------------------------------------------------------

    function queryBalance()
    {
        return $this->curlPost($this->config->balance_url, ['account' => $this->config->usr, 'password' => $this->config->pwd]);;
    }

    // ----------------------------------------------------------------------
    //  数据抓取
    // ----------------------------------------------------------------------

    private function curlPost($url, $data)
    {
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json; charset=utf-8']);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($ch, CURLOPT_POST, 1);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
        curl_setopt($ch, CURLOPT_TIMEOUT, 1);
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 0);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, 0);
        $res = curl_exec($ch);
        if (false == $res) {
            $result = curl_error($ch);
        } else {
            $rsp = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            $result = $rsp == 200 ? $res : ("请求状态 " . $rsp . " " . curl_error($ch));
        }
        curl_close($ch);
        return $result;
    }

}