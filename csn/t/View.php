<?php

namespace csn\t;

class View
{

    protected $useView;                 // 当前视图文件
    protected $sectionSave = [];       // 父模板块数组
    protected $sectionChange = [];     // 子模板块数组
    protected $content = '';           // 当前静态内容
    protected static $cacheTime;        // 默认缓存时间
    protected static $viewDir;          // 视图存放目录
    protected static $templateDir = []; // 编译静态目录

    // 对外显示静态内容
    function __toString()
    {
        return $this->content;
    }

    // 获取缓存
    static function getCache($names, $time = null)
    {
        $path = self::path($names);
        is_null($time) && $time = self::cacheTime();
        $html = self::html($path);
        return is_file($html) && filemtime($html) + $time >= CSN_TIME && ($outfile = self::compileOk($path, $names)) && filemtime($html) >= filemtime($outfile) ? $html : false;
    }

    // 视图对象
    function __construct($names, $data = [], $time = null)
    {
        $path = self::path($names);
        is_null($time) && $time = self::cacheTime();
        $this->useView = self::compileOk($path, $names) ?: $this->compile($path);
        $this->content = self::content($this->useView, $data, $path, $time);
    }

    // 转换路由
    protected static function path($path)
    {
        return str_replace('.', XG, str_replace('/', XG, $path));
    }

    // 获取使用视图
    function getView()
    {
        return $this->useView;
    }

    // 获取默认缓存时间
    protected static function cacheTime()
    {
        return is_null(self::$cacheTime) ? self::$cacheTime = Conf::web('view_cache') : self::$cacheTime;
    }

    // 获取静态内容
    protected static function content($source, $data, $path, $time)
    {
        ob_start();
        extract($data);
        include $source;
        $content = ob_get_contents();
        ob_end_clean();
        $time && (!is_file($html = self::html($path)) || filemtime($html) + $time < CSN_TIME) && File::write($html, $content, true);
        return $content;
    }

    // 编译文件是否有效
    protected static function compileOk($path, $names)
    {
        return is_file($source = self::source($path)) ? (is_file($outfile = self::output($path)) && filemtime($outfile) >= filemtime($source)) ? $outfile : false : (is_file($outfile = self::source($path, false)) ? $outfile : Exp::end('找不到视图' . $names));
    }

    // 解析模板
    protected function compile($path)
    {
        File::write($outfile = self::output($path), $this->compileGo(self::source($path)), true);
        return $outfile;
    }

    // 编译模板
    protected function compileGo($path)
    {
        $content = "<?php namespace app\c; ?>" . file_get_contents($path);
        $this->compileExtends($content);
        $this->compileSectionChange($content);
        $this->compileSectionShow($content);
        self::compileAuto($content);
        self::compileNodes($content);
        self::compileEcho($content);
        self::compileArr($content);
        self::compileSelf($content);
        self::compileIf($content);
        self::compileUnless($content);
        self::compileFor($content);
        self::compileForeach($content);
        self::compileForelse($content);
        self::compileInclude($content);
        return $content;
    }

    // 继承模板
    protected function compileExtends(&$content)
    {
        $content = preg_replace_callback('/@extends\s*\(([\'"])?(.+?)\1\)/', [$this, '_compileExtends'], $content);
    }

    // 编译继承模板
    protected function _compileExtends($match)
    {
        $names = $match[2];
        $path = self::path($names);
        $source = self::source($path, true);
        is_file($source) || Exp::end('找不到视图模板' . $path);
        $parent = file_get_contents($source);
        $this->compileSectionSave($parent);
        return $parent;
    }

    // 继承模板区块解析
    protected function compileSectionSave(&$content)
    {
        $content = preg_replace_callback('/@section\s*\(([\'"])?(.+?)\1\)(.*?)@show/us', [$this, '_compileSectionSave'], $content);
    }

    // 继承模板区块保存
    protected function _compileSectionSave($match)
    {
        $this->sectionSave[$match[2]] = $match[3];
        return '@save{' . $match[2] . '}';
    }

    // 继承模板区块重写
    protected function compileSectionChange(&$content)
    {
        $content = preg_replace_callback('/@section\s*\(([\'"])?(.+?)\1\)(.*?)@endsection/us', [$this, '_compileSectionChange'], $content);
    }

    // 编译继承模板区块重写
    protected function _compileSectionChange($match)
    {
        $child = $match[3];
        $name = $match[2];
        strpos($child, '@parent') !== false && key_exists($name, $this->sectionSave) && $child = str_replace('@parent', $this->sectionSave[$name], $child);
        $this->sectionChange[$name] = $child;
        return '';
    }

    // 继承模板区块展示
    protected function compileSectionShow(&$content)
    {
        foreach ($this->sectionSave as $k => $v) {
            $replace = key_exists($k, $this->sectionChange) ? $this->sectionChange[$k] : $v;
            $content = str_replace('@save{' . $k . '}', $replace, $content);
        }
    }

    // 自然模板
    protected static function compileAuto(&$content)
    {
        $content = preg_replace('/{:([^}]+)}/', '<?php \1; ?>', $content);
    }

    // 注释模板
    protected static function compileNodes(&$content)
    {
        $content = preg_replace('/{\*([^}]+)\*}/', '<!--\1-->', $content);
    }

    // echo模板
    protected static function compileEcho(&$content)
    {
        $content = preg_replace('/(?<!@){{([^}]+)}}/', '<?php echo \1; ?>', $content);
    }

    // 数组模板
    protected static function compileArr(&$content)
    {
        $content = preg_replace_callback('/{\$([^$}]+)}/', 'self::_compileArr', $content);
    }

    // 不解析模板
    protected static function compileSelf(&$content)
    {
        $content = preg_replace('/@({{.*?}})/', '\1', $content);
    }

    // 编译数组元素模板
    protected static function _compileArr($match)
    {
        $arr = explode('.', $match[1]);
        $str = '$' . $arr[0];
        for ($i = 1, $c = count($arr); $i < $c; $i++) {
            $str .= "['{$arr[$i]}']";
        }
        return '<?php echo ' . $str . '; ?>';
    }

    // if模板
    protected static function compileIf(&$content)
    {
        $content = preg_replace('/@if\s*\((.+?)\)(.*?)@endif/us', '<?php if (\1) { ?>\2<?php } ?>', $content);
        $content = preg_replace('/@elseif\s*\((.+?)\)/', '<?php } elseif (\1) { ?>', $content);
        $content = preg_replace('/@else/', '<?php } else { ?>', $content);
    }

    // unless模板
    protected static function compileUnless(&$content)
    {
        $content = preg_replace('/@unless\s*\((.+?)\)(.*?)@endunless/us', '<?php if (\1) { ?>\2<?php } ?>', $content);
    }

    // if模板
    protected static function compileFor(&$content)
    {
        $content = preg_replace('/@for\s*\((.+?)\)(.*?)@endfor/us', '<?php for (\1) { ?>\2<?php } ?>', $content);
    }

    // if模板
    protected static function compileForeach(&$content)
    {
        $content = preg_replace('/@foreach\s*\((.+?)\)(.*?)@endforeach/us', '<?php foreach (!(\1)) { ?>\2<?php } ?>', $content);
    }

    // if模板
    protected static function compileForelse(&$content)
    {
        $content = preg_replace('/@forelse\s*\(((.+?) as .+?)\)(.*?)@empty(.*?)@endforelse/us', '<?php if (empty(\2)) { ?>\4<?php } else { foreach (\1) { ?>\3<?php } } ?>', $content);
    }

    // include模板
    protected static function compileInclude(&$content)
    {
        $content = preg_replace_callback('/<include\s+file=(["\'])([\w\.]+)\1\s*(data=(["\'])(\w+)\4)?\s*(time=(["\'])(\d+)\7)?\s*\/?>/', 'self::_compileInclude', $content);
    }

    // 编译include模板
    protected static function _compileInclude($match)
    {
        return '<?php viewInclude("' . $match[2] . '", ' . (empty($match[5]) ? '[]' : '["' . $match[5] . '"=>$' . $match[5] . ']') . ', ' . (empty($match[8]) ? 'null' : (int)$match[8]) . ');?>';
    }

    // 模板源文件
    protected static function source($path, $tpl = true)
    {
        return self::viewDir() . $path . ($tpl ? '.tpl' : '.php');
    }

    // 编译文件
    protected static function output($path)
    {
        return self::templateDir('php') . $path . '.php';
    }

    // 静态页文件
    protected static function html($path)
    {
        return self::templateDir('html') . md5(Request::path() . Safe::get('key') . $path) . '.html';
    }

    // 视图目录
    protected static function viewDir()
    {
        return is_null(self::$viewDir) ? self::$viewDir = APP_V : self::$viewDir;
    }

    // 编译静态目录
    protected static function templateDir($which)
    {
        return key_exists($which, self::$templateDir) ? self::$templateDir[$which] : self::$templateDir[$which] = APP_T . $which . XG;
    }

}