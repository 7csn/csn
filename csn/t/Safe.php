<?php

namespace csn\t;

class Safe
{

    protected static $key;      // 密钥
    protected static $lock;     // 密锁串(包含base64所有字符且不重复)
    protected static $secret;   // 密锁钥数组
    protected static $safe;     // 验证规则数组

    // 加载密钥
    static function get($name = null)
    {
        is_null(self::$secret) && self::$secret = parse_ini_file(App . 'secret.ini');
        return is_null($name) ? self::$secret : self::$secret[$name];
    }

    // 检查密钥
    static function secret()
    {
        $file = App . 'secret.ini';
        is_file($file) || exp::end('未检测到密钥文件，请先创建');
        $secret = parse_ini_file($file);
        key_exists('key', $secret) && key_exists('lock', $secret) && strlen($secret['key']) === 32 && strlen($secret['lock']) === 67 || exp::end('密钥文件异常');
    }

    // 初始化密钥
    static function secretInit()
    {
        File::write(App . 'secret.ini', "key='" . md5(uniqid()) . "'\nlock='" . str_shuffle('0123456789abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ/=+_-') . "'");
    }

    // 正则验证
    static function __callStatic($name, $args)
    {
        self::filter();
        if (key_exists($name, self::$safe)) {
            $point = self::$safe[$name];
            return is_string($point) ? preg_match($point, $args[0]) : call_user_func_array($point, $args);
        } else {
            exp::end('验证函数' . $name . '无效，请检查safe配置文件');
        }
    }

    // 获取验证规则
    protected static function filter()
    {
        return is_null(self::$safe) ? self::$safe = self::filterArr() : self::$safe;
    }

    // 规则数组键名处理
    protected static function filterArr()
    {
        $safe = [];
        foreach (Conf::safe() as $k => $v) {
            $safe['is' . ucfirst($k)] = $v;
        }
        return $safe;
    }

    // 生成验证码及图片
    static function imgCode($len = 4, $width = 160, $height = 50, $size = 20)
    {
        // 生成背景
        $im = imagecreatetruecolor($width, $height);
        imagefilledrectangle($im, 0, $height, $width, 0, imagecolorallocate($im, mt_rand(157, 255), mt_rand(157, 255), mt_rand(157, 255)));
        // 生成随机码
        $charset = 'abcdefghkmnprstuvwxyzABCDEFGHKMNPRSTUVWXYZ0123456789';
        $_len = strlen($charset) - 1;
        $str = '';
        $len = min([$len, 6]);
        for ($i = 0; $i < $len; $i++) {
            $str .= $charset[mt_rand(0, $_len)];
        }
        //生成线条、雪花
        for ($i = 0; $i < 6; $i++) {
            imageline($im, mt_rand(0, $width), mt_rand(0, $height), mt_rand(0, $width), mt_rand(0, $height), imagecolorallocate($im, mt_rand(0, 156), mt_rand(0, 156), mt_rand(0, 156)));
        }
        for ($i = 0; $i < 50; $i++) {
            imagestring($im, mt_rand(1, 5), mt_rand(0, $width), mt_rand(0, $height), '*', imagecolorallocate($im, mt_rand(200, 255), mt_rand(200, 255), mt_rand(200, 255)));
        }
        // 生成文字
        $_x = $width / $len;
        for ($i = 0; $i < $len; $i++) {
            imagettftext($im, $size, mt_rand(-30, 30), $_x * $i + mt_rand(1, 5), $height / 1.4, imagecolorallocate($im, mt_rand(0, 156), mt_rand(0, 156), mt_rand(0, 156)), Csn_s . 'vcode.ttf', $str[$i]);
        }
        // 保存为对象
        $obj = new \StdClass();
        $obj->str = $str;
        ob_start();
        imagepng($im);
        imagedestroy($im);
        $obj->img = 'data:image/png;base64,' . base64_encode(ob_get_contents());
        ob_end_clean();
        return $obj;
    }

    // 加密
    static function encode($str)
    {
        if (mb_strlen($str) <= 1) return '';
        // 密锁串、长度、随机位及值
        $lock = self::get('lock');
        $len = strlen($lock);
        $rand = mt_rand(0, $len - 1);
        $lk = $lock[$rand];
        // 密钥结合密锁随机值MD5加密
        $md5 = strtoupper(md5(self::get('key') . $lk));
        // 字符串BASE64加密
        $str = base64_encode($str);
        $res = '';
        for ($i = $k = 0, $c = strlen($str); $i < $c; $i++) {
            $k === strlen($md5) && $k = 0;
            // 转化字符：由密锁串 原位+随机位+顺序MD5密钥字符ASCII码 决定新位，从密锁串中获取目标字符
            $res .= $lock[(strpos($lock, $str[$i]) + $rand + ord($md5[$k])) % ($len)];
            $k++;
        }
        // 返回加密结果(含随机关联)
        return $res . $lk;
    }

    // 解密
    static function decode($str)
    {
        if (mb_strlen($str) <= 1) return '';
        // 将地址栏参数被强制转换的空格替换成+号
        $str = str_replace(' ', '+', $str);
        // 密锁串、长度、随机位及值
        $lock = self::get('lock');
        $len = strlen($lock);
        // 字符串长度
        $txtLen = strlen($str);
        // 密锁随机值及位
        $lk = $str[$txtLen - 1];
        $rand = strpos($lock, $lk);
        // 密钥结合密锁随机值MD5加密
        $md5 = strtoupper(md5(self::get('key') . $lk));
        // 去除字符串随机关联
        $str = substr($str, 0, $txtLen - 1);
        $tmpStream = '';
        for ($i = $k = 0, $c = strlen($str); $i < $c; $i++) {
            $k === strlen($md5) && $k = 0;
            // 获取字符在密锁串原位：由 位-随机位-顺序MD5密钥字符ASCII码 算出
            $j = strpos($lock, $str[$i]) - $rand - ord($md5[$k]);
            while ($j < 0) {
                $j += $len;
            }
            $tmpStream .= $lock[$j];
            $k++;
        }
        // 返回BASE64解密源字符串
        return base64_decode($tmpStream);
    }

    // 生成登录ID
    static function eLoginId($str)
    {
        return self::encode(TIME . '.' . chr(mt_rand(97, 122)) . '.' . $str);
    }

    // 登录ID解码
    static function dLoginId($loginId, $time = 7200)
    {
        $arr = explode('.', self::decode($loginId));
        return count($arr) === 3 && is_numeric($t = $arr[0]) ? ($t <= TIME && $t + $time > TIME) ? $arr[2] : 0 : false;
    }

    // 添加转义字符
    static function addSlashes($data)
    {
        if (is_array($data)) {
            foreach ($data as $k => $v) {
                $data[$k] = self::addSlashes($v);
            }
        } else {
            $data = addslashes(trim($data));
        }
        return $data;
    }

    // 去除转义字符
    static function stripSlashes($data)
    {
        if (is_array($data)) {
            foreach ($data as $k => $v) {
                $data[$k] = self::stripSlashes($v);
            }
        } else {
            $data = stripSlashes(trim($data));
        }
        return $data;
    }

    // 初始化数据
    static function initData($data)
    {
        if (is_array($data)) {
            foreach ($data as $k => $v) {
                if (self::isKey($k) === false) {
                    unset($data[$k]);
                } else {
                    $data[$k] = self::initData($v);
                }
            }
            return $data;
        } else {
            return is_numeric($data) ? $data[0] === 0 || strlen($data) >= 11 ? self::addSlashes(htmlspecialchars($data)) : (strpos($data, '.') ? floatval($data) : $data) : (is_string($data) ? self::addSlashes(htmlspecialchars($data)) : (is_bool($data) ? (bool)$data : false));
        }
    }

    // 恢复数据
    static function backData($data)
    {
        if (is_array($data)) {
            foreach ($data as $k => $v) {
                $data[$k] = self::backData($v);
            }
            return $data;
        } else {
            return is_string($data) ? htmlspecialchars_decode(stripSlashes($data)) : $data;
        }
    }

}