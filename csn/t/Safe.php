<?php

namespace csn;

final class Safe
{

    // ----------------------------------------------------------------------
    //  密锁
    // ----------------------------------------------------------------------

    // 数组
    protected static $secret;

    // 读取
    static function get($name = null)
    {
        if (is_null(self::$secret)) {
            $file = APP . 'secret.ini';
            is_file($file) || Csn::end('未检测到密钥文件，请先创建');
            self::$secret = parse_ini_file($file);
        }
        return is_null($name) ? self::$secret : self::$secret[$name];
    }

    // 初始化文件：是否重置
    static function secretInit($anew = false)
    {
        File::write(APP . 'secret.ini', "key='" . md5(Conf::web('safe_key')) . "'\nlock='" . str_shuffle(Conf::web('base_code')) . "'", $anew);
    }

    // ----------------------------------------------------------------------
    //  正则验证
    // ----------------------------------------------------------------------

    // 验证规则数组
    protected static $safe;

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

    //
    static function __callStatic($name, $args)
    {
        self::filter();
        if (key_exists($name, self::$safe)) {
            $point = self::$safe[$name];
            return is_string($point) ? preg_match($point, $args[0]) : call_user_func_array($point, $args);
        } else {
            Csn::end('验证函数' . $name . '无效，请检查safe配置文件');
        }
    }

    // ----------------------------------------------------------------------
    //  生成验证码及图片
    // ----------------------------------------------------------------------

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
            imagettftext($im, $size, mt_rand(-30, 30), $_x * $i + mt_rand(1, 5), $height / 1.4, imagecolorallocate($im, mt_rand(0, 156), mt_rand(0, 156), mt_rand(0, 156)), CSN_S . 'vcode.ttf', $str[$i]);
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

    // ----------------------------------------------------------------------
    //  加密、解密
    // ----------------------------------------------------------------------

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
        // 字符串基础加密
        $str = self::en($str);
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

    static function decode($str)
    {
        if (mb_strlen($str) <= 1) return '';
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
        // 返回基础解密源字符串
        return self::de($tmpStream);
    }

    // ----------------------------------------------------------------------
    //  基础加密、基础解密
    // ----------------------------------------------------------------------

    static function en($str)
    {
        // 加密条件：非空字符串
        if (is_string($str) === false || strlen($str) === 0) return $str;
        // 不重复密锁串
        $lock = self::get('lock');
        $len = strlen($lock);
        // 字符串无符号解包数组值数组
        $bytes = array_values(unpack('C*', $str));
        // 数组元素256倍升幂
        $num256 = $bytes[0];
        for ($i = 1, $l = count($bytes); $i < $l; $i++) {
            // 大数字操作：和、积
            $num256 = bcadd(bcmul($num256, 256), $bytes[$i]);
        }
        $res = '';
        // 密锁长度倍降幂字符串
        while ($num256 >= $len) {
            // 大数字操作：取余
            $res = $lock[bcmod($num256, $len)] . $res;
            // 大数字操作：商(保留0位小数)
            $num256 = bcdiv($num256, $len, 0);
        }
        $res = $lock[$num256] . $res;
        // 数组前0元素补充
        foreach ($bytes as $byte) {
            if ($byte !== 0) break;
            $res = $lock[0] . $res;
        }
        // $lock[0]... $lock[...]...
        return (string)$res;
    }

    static function de($str)
    {
        // 解密条件：非空字符串
        if (is_string($str) === false || strlen($str) === 0) return $str;
        // 密锁串分割数组并交换键值
        $lock = array_flip(str_split(self::get('lock')));
        $len = count($lock);
        // 字符串分割数组
        $chars = str_split($str);
        // 验证加密字符串合法性
        foreach ($chars as $char) {
            if (!key_exists($char, $lock)) return false;
        }
        // 字符组元素密钥长度升幂
        $num256 = $lock[$chars[0]];
        for ($i = 1, $l = count($chars); $i < $l; $i++) {
            // 大数字操作：和、积
            $num256 = bcadd(bcmul($num256, $len), $lock[$chars[$i]]);
        }
        $res = '';
        // 256倍降幂还原字符串
        while ($num256 > 0) {
            $res = pack('C', bcmod($num256, 256)) . $res;
            $num256 = bcdiv($num256, 256, 0);
        }
        // 字符组还原前0数还原ASCII码
        foreach ($chars as $char) {
            if ($lock[$char] === 0) {
                $res = "\x00" . $res;
                continue;
            }
            break;
        }
        return $res;
    }

    // ----------------------------------------------------------------------
    //  转义('、"、\、NULL)：增、减
    // ----------------------------------------------------------------------

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

    // ----------------------------------------------------------------------
    //  HTML实体转换：&(&amp;)、"(&quot;)、'(&#039;|&apos;)、<(&lt;)、>(&gt;)
    // ----------------------------------------------------------------------

    static function htmlspecialchars($data)
    {
        if (is_array($data)) {
            foreach ($data as $k => $v) {
                $data[$k] = self::htmlspecialchars($v);
            }
        } else {
            $data = htmlspecialchars($data);
        }
        return $data;
    }

    static function htmlspecialchars_decode($data)
    {
        if (is_array($data)) {
            foreach ($data as $k => $v) {
                $data[$k] = self::htmlspecialchars_decode($v);
            }
        } else {
            $data = htmlspecialchars_decode($data);
        }
        return $data;
    }

    // ----------------------------------------------------------------------
    //  初始化数据
    // ----------------------------------------------------------------------

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

    // ----------------------------------------------------------------------
    //  恢复数据
    // ----------------------------------------------------------------------

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