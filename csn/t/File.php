<?php

namespace csn\t;

class File
{

    // 创建目录
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

    // 删除目录
    static function rmDir($path, $both = false)
    {
        if (is_dir($path)) {
            $dir = dir($path);
            while (false !== ($p = $dir->read())) {
                if ($p === '.' || $p === '..') continue;
                self::rmDir($path . XG. $p, $both);
            }
            $dir->close();
            rmdir($path);
        } elseif ($both && is_file($path)) {
            unlink($path);
        }
    }

    // 目录及文件详情
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
                $l = self::lists($path . XG . $p);
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
            exp::end('路径' . $path . '异常');
        }
        return $list;
    }

    // 写入内容
    static function write($file, $text = '', $force = false)
    {
        self::mkDir(dirname($file));
        ($force || !is_file($file)) && file_put_contents($file, $text, LOCK_EX);
    }

    // 追加内容
    static function append($file, $text = '')
    {
        self::mkDir(dirname($file));
        file_put_contents($file, $text, FILE_APPEND);
    }

    // 复制文件
    static function copy($from, $to, $force = false)
    {
        self::mkDir(dirname($to));
        ($force || !is_file($to)) && copy($from, $to);
    }

    // 批量复制文件
    static function copies($copy, $force = false)
    {
        foreach ($copy as $from => $to) {
            self::copy($from, $to, $force);
        }
    }

}