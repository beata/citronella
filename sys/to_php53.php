<?php
if (!function_exists('lcfirst')) {
    function lcfirst( $str )  {
        return (string)(strtolower(substr($str,0,1)).substr($str,1));
    }
}
if (!function_exists('get_called_class')) {
    function get_called_class()
    {
        static $cache = array();
        $class = '';
        foreach (debug_backtrace() as $bt) {
            if (isset($bt['class']) && ($bt['type'] == '->' ||$bt['type'] == '::') && isset($bt['file'])) {
                extract($bt);
                if (!isset($cache["{$file}_{$line}"])) {
                    $lines = file($file);
                    $expr  = '/([a-z0-9\_]+)::'.$function.'/i';
                    $line  = $lines[$line-1];
                    preg_match_all($expr, $line, $matches);
                    $cache["{$file}_{$line}"] = $matches[1][0];
                }
                if ($cache["{$file}_{$line}"] != 'self' && !empty($cache["{$file}_{$line}"])) {
                    $class = $cache["{$file}_{$line}"];
                    break;
                }
            }
        }
        return $class;
    }
}
