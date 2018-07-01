<?php

namespace csn;

class Runtime
{

    static $log = [];

    // 获取日志项
    static function get($name)
    {
        key_exists($name, self::$log) || self::$log[$name] = Conf::runtime($name);
        if (!key_exists($name, self::$log)) {
            $get = Conf::runtime($name);
            foreach (['set', 'file', 'size'] as $v) {
                key_exists($v, $get) || Exp::end('日志项' . $name . '配置不正确');
            }
            self::$log[$name] = $get;
        }
        return self::$log[$name];
    }

    // 访问日志
    static function act()
    {
        self::set('act');
    }

    // 错误日志
    static function bug($info)
    {
        self::set('bug', date('H:i:s') . ' ' . Request::instance()->uri() . "\n\t" . $info . "\n");
    }

    // SQL日志
    static function sql($info)
    {
        self::set('sql', $info);
    }

    // 记录日志
    protected static function set($name, $info = '')
    {
        $log = self::get($name);
        if (!$log['set']) return;
        $dir = RUN . str_replace('.', DS, $log['dir']) . DS;
        if ($name === 'act') {
            $file = $dir . date('Ymd') . DS . Route::$define . DS . Route::$path . '.json';
            self::actSave($file);
        } else {
            $file = $dir . date('Ymd') . DS . date('H') . '.log';
            File::append($file, $info);
        }
        $size = $log['size'];
        is_int($size) && $size > 0 ? self::limit($dir, $size) : Exp::end('日志项' . $name . '配置size不正确');
    }

    // 记录访问日志
    protected static function actSave($file)
    {
        $time = date('His');
        if (is_file($file)) {
            $act = json_decode(file_get_contents($file), true);
            $act['count']++;
            key_exists($time, $act['details']) ? $act['details'][$time]++ : $act['details'][$time] = 1;
        } else {
            $act = ['count' => 1, 'details' => [$time => 1]];
        }
        File::write($file, json_encode($act), true);
    }

    // 保持日志上限[删除旧版]
    protected static function limit($dir, $limit)
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