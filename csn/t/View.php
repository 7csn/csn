<?php

namespace csn;

final class View extends Instance
{

    // ----------------------------------------------------------------------
    //  视图对象
    // ----------------------------------------------------------------------

    function construct($names)
    {
        $this->path = $this->path($names);
        $this->html = RUN_HTML . $this->path . DS . Safe::en(Request::instance()->path()) . '.html';
        is_file($this->view()) || Csn::end('找不到视图：' . $names);
        if (!is_file($this->php()) || filemtime($this->php()) < filemtime($this->view())) {
            File::write($this->php(), $this->compileGo(), true);
            File::rmDir(RUN_HTML . $this->path, true);
        }
    }

    // ----------------------------------------------------------------------
    //  当前路由
    // ----------------------------------------------------------------------

    private $path;

    // ----------------------------------------------------------------------
    //  转换路由
    // ----------------------------------------------------------------------

    private function path($path)
    {
        return str_replace('.', DS, str_replace('/', DS, $path));
    }

    // ----------------------------------------------------------------------
    //  当前静态页路径
    // ----------------------------------------------------------------------

    private $html;

    // ----------------------------------------------------------------------
    //  获取视图文件路径
    // ----------------------------------------------------------------------

    private static $views = [];

    private function view()
    {
        return key_exists($this->path, self::$views) ? self::$views[$this->path] : self::$views[$this->path] = APP_VIEW . $this->path . '.php';
    }

    // ----------------------------------------------------------------------
    //  获取编译文件路径
    // ----------------------------------------------------------------------

    private static $phps = [];

    private function php()
    {
        return key_exists($this->path, self::$phps) ? self::$phps[$this->path] : self::$phps[$this->path] = RUN_PHP . $this->path . '.php';
    }

    // ----------------------------------------------------------------------
    //  静态缓存是否有效
    // ----------------------------------------------------------------------

    private function htmlOK($time = null)
    {
        return is_file($this->html) && filemtime($this->html) + (is_null($time) ? Conf::web('view_cache') : $time) > CSN_START;
    }

    // ----------------------------------------------------------------------
    //  获取静态内容
    // ----------------------------------------------------------------------

    function makeHtml($stdClass, $time)
    {
        return $this->htmlOK($time) ? file_get_contents($this->html) : call_user_func(function ($data) {
            ob_start();
            extract($data);
            include $this->php();
            $content = ob_get_contents();
            ob_end_clean();
            File::write($this->html, $content, true);
            return $content;
        }, $stdClass->run());
    }

    // ----------------------------------------------------------------------
    //  编译模板
    // ----------------------------------------------------------------------

    private function compileGo()
    {
        $content = "<?php namespace app\c; ?>" . file_get_contents($this->view());
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

    // ----------------------------------------------------------------------
    //  继承模板编译
    // ----------------------------------------------------------------------

    // 父模板块数组
    protected $sectionSave = [];

    // 子模板块数组
    protected $sectionChange = [];

    // 继承模板
    protected function compileExtends(&$content)
    {
        $content = preg_replace_callback('/@extends\s*\(([\'"])?(.+?)\1\)/', [$this, '_compileExtends'], $content);
    }

    // 编译继承模板
    protected function _compileExtends($match)
    {
        $names = $match[2];
        $path = $this->path($names);
        $source = self::source($path, true);
        is_file($source) || Csn::end('找不到视图模板' . $path);
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

    // ----------------------------------------------------------------------
    //  常用模板编译
    // ----------------------------------------------------------------------

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
        $content = preg_replace_callback('/{\$([^$}]+)}/', function ($match) {
            $arr = explode('.', $match[1]);
            $str = '$' . $arr[0];
            for ($i = 1, $c = count($arr); $i < $c; $i++) {
                $str .= "['{$arr[$i]}']";
            }
            return '<?php echo ' . $str . '; ?>';
        }, $content);
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

    // 不解析模板
    protected static function compileSelf(&$content)
    {
        $content = preg_replace('/@({{.*?}})/', '\1', $content);
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

    // ----------------------------------------------------------------------
    //  引入模板编译
    // ----------------------------------------------------------------------

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

}