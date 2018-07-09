<?php

namespace csn;

final class File
{

    // ----------------------------------------------------------------------
    //  创建目录
    // ----------------------------------------------------------------------

    static function mkDir($dir)
    {
        if (is_array($dir)) {
            foreach ($dir as $d) {
                self::mkDir($d);
            }
        } else {
            is_dir($dir) || mkdir($dir, 0777, true);
        }
    }

    // ----------------------------------------------------------------------
    //  删除目录/文件：路径、是否包含非空目录
    // ----------------------------------------------------------------------

    static function rmDir($path, $both = false)
    {
        if (is_dir($path)) {
            $dir = dir($path);
            while (false !== ($p = $dir->read())) {
                if ($p === '.' || $p === '..') continue;
                self::rmDir($path . DS. $p, $both);
            }
            $dir->close();
            @rmdir($path);
        } elseif ($both && is_file($path)) {
            unlink($path);
        }
    }

    // ----------------------------------------------------------------------
    //  目录及文件详情
    // ----------------------------------------------------------------------

    static function lists($path)
    {
        $list = ['path' => $path];
        if (is_dir($path)) {
            $list['type'] = 'dir';
            $size = 0;
            $down = [];
            $dir = dir($path);
            while (false !== ($p = $dir->read())) {
                if ($p === '.' || $p === '..') continue;
                $l = self::lists($path . DS . $p);
                $size += $l['size'];
                $down[] = $l;
            }
            $dir->close();
            $list['size'] = $size;
            $list['down'] = $down;
        } elseif (is_file($path)) {
            $list['type'] = 'file';
            $list['size'] = filesize($path);
        } else {
            Csn::end('路径' . $path . '异常');
        }
        return $list;
    }

    // ----------------------------------------------------------------------
    //  写入内容：文件、写入文本、是否重写(默认存在不写)
    // ----------------------------------------------------------------------

    static function write($file, $text = '', $force = false)
    {
        self::mkDir(dirname($file));
        ($force || !is_file($file)) && file_put_contents($file, $text, LOCK_EX);
    }

    // ----------------------------------------------------------------------
    //  追加内容：文件、追加文本
    // ----------------------------------------------------------------------

    static function append($file, $text = '')
    {
        self::mkDir(dirname($file));
        file_put_contents($file, $text, FILE_APPEND);
    }

    // ----------------------------------------------------------------------
    //  复制文件：源文件、目标路径、是否覆盖
    // ----------------------------------------------------------------------

    static function copy($from, $to, $force = false)
    {
        self::mkDir(dirname($to));
        ($force || !is_file($to)) && copy($from, $to);
    }

    // ----------------------------------------------------------------------
    //  批量复制文件：源=>目标、是否覆盖
    // ----------------------------------------------------------------------

    static function copies($copy, $force = false)
    {
        foreach ($copy as $from => $to) {
            self::copy($from, $to, $force);
        }
    }

}