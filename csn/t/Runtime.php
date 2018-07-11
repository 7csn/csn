<?php

namespace csn;

final class Runtime
{

    // ----------------------------------------------------------------------
    //  记录日志
    // ----------------------------------------------------------------------

    // 访问
    static function action()
    {
        self::set('action', function () {
            $time = date('His');
            if (is_file($file = RUN . 'action' . DS . date('Ymd') . DS . Request::instance()->path() . '.json')) {
                $action = json_decode(file_get_contents($file), true);
                $action['count']++;
                key_exists($time, $action['details']) ? $action['details'][$time]++ : $action['details'][$time] = 1;
            } else {
                $action = ['count' => 1, 'details' => [$time => 1]];
            }
            File::write($file, json_encode($action), true);
        });
    }

    // 错误
    static function error($info)
    {
        self::set('error', function () use ($info) {
            list($date, $hour, $minute) = explode(' ', date('Ymd H i:s'));
            File::append(RUN . 'error' . DS . $date . DS . $hour . '.log', $minute . ' ' . $info . ENTER);
        });
    }

    // SQL
    static function sql($info)
    {
        self::set('sql', function () use ($info) {
            list($date, $hour, $minute) = explode(' ', date('Ymd H i:s'));
            File::append(RUN . 'sql' . DS . $date . DS . $hour . '.log', $minute . ' ' . $info . ENTER);
        });
    }

    // ----------------------------------------------------------------------
    //  记录日志模板
    // ----------------------------------------------------------------------

    private static function set($type, $func)
    {
        if (Config::runtime($type . '.set')) {
            $func();
            $size = Config::runtime($type . '.size');
            is_int($size) && $size > 0 ? self::limit(RUN . $type . DS, $size) : Csn::end("日志项 $type 配置 size 不正确");
        }
    }

    // ----------------------------------------------------------------------
    //  保持日志存储上限[删除旧版]
    // ----------------------------------------------------------------------

    private static function limit($dir, $limit)
    {
        $list = File::lists($dir);
        $size = $list['size'];
        $down = $list['down'];
        $i = 0;
        while ($size > $limit) {
            File::rmDir($down[$i]['path'], true);
            $size -= $down[$i]['size'];
            $i++;
        }
    }

}