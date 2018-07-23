<?php

namespace csn;

class Upload extends Instance
{

    // ----------------------------------------------------------------------
    //  构造函数
    // ----------------------------------------------------------------------

    private $file;

    function construct($name)
    {
        $this->file = Request::instance()->files($name);
        is_null($this->file) && Csn::end('上传文件 ' . $name . ' 不存在');
        $this->error($this->file['error'])->post($this->file['tmp_name']);
        return true;
    }

    // ----------------------------------------------------------------------
    //  错误处理
    // ----------------------------------------------------------------------

    private $error = [
        UPLOAD_ERR_INI_SIZE => '文件过大',              // max_upload_filesize
        UPLOAD_ERR_FORM_SIZE => '文件过大',             // MAX_FILE_SIZE
        UPLOAD_ERR_PARTIAL => '文件仅部分被上传',
        UPLOAD_ERR_NO_FILE => '没有上传文件',
        UPLOAD_ERR_NO_TMP_DIR => '上传失败',            // 找不到临时文件夹
        UPLOAD_ERR_CANT_WRITE => '上传失败',            // 临时文件夹写入权限
        UPLOAD_ERR_EXTENSION => '上传失败'              // 上传扩展未开
    ];

    private function error($error)
    {
        $error > 0 && (key_exists($error, $this->error) ? Csn::end($this->error[$error]) : Csn::end('未知错误'));
        return $this;
    }

    // ----------------------------------------------------------------------
    //  验证是否http post方式上传
    // ----------------------------------------------------------------------

    private function post($name)
    {
        is_uploaded_file($name) || Csn::end('异常上传');
    }

    // ----------------------------------------------------------------------
    //  大小限制
    // ----------------------------------------------------------------------

    function size($size, $info = false)
    {
        $this->file['size'] > $size && ($info ? Csn::end($info) : die);
        return $this;
    }

    // ----------------------------------------------------------------------
    //  类型限制
    // ----------------------------------------------------------------------

    function type($exts, $info = false)
    {
        is_array($exts) || $exts = [$exts];
        in_array($this->ext(), $exts) || ($info ? Csn::end($info) : die);
        return $this;
    }

    // ----------------------------------------------------------------------
    //  文件处理
    // ----------------------------------------------------------------------

    function move($dir, $name, $rand = false)
    {
        File::mkDir($dir);
        $from = $this->file['tmp_name'];
        $ext = $this->ext();
        $ext || Csn::end('文件类型不支持');
        $to = $dir . $name . '.' . $ext;
        return @move_uploaded_file($from, $to) ? $rand ? $to . '?v=' . CSN_TIME : $to : false;
    }

    // ----------------------------------------------------------------------
    //  文件后缀
    // ----------------------------------------------------------------------

    private $extCode = [7790 => 'exe', 7784 => 'midi', 8075 => 'zip', 8297 => 'rar', 255216 => 'jpg', 7173 => 'gif', 6677 => 'bmp', 13780 => 'png'];

    private $ext;

    private function ext()
    {
        if (is_null($this->ext)) {
            // 读取文件前2字节
            $fp = fopen($this->file['tmp_name'], "rb");
            $bin = fread($fp, 2);
            fclose($fp);
            $info = @unpack("C2chars", $bin);
            $code = intval($info['chars1'] . $info['chars2']);
            $this->ext = key_exists($code, $this->extCode) ? $this->extCode[$code] : false;
        }
        return $this->ext;
    }

}