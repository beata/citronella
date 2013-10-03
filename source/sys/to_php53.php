<?php
/**
 * Add missing methods which upgradephp doesn't have.
 *
 * @author Beata Lin
 * @package Core
 */

if (!function_exists('get_called_class')) {
    /**
     * @group 5.3 back compatibility
     */
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

if(!function_exists('date_diff')) {
    /**
     * @group 5.3 back compatibility
     */
    class DateInterval
    {
        public $y;
        public $m;
        public $d;
        public $h;
        public $i;
        public $s;
        public $invert;

        public function format($format)
        {
            $format = str_replace('%R%y', ($this->invert ? '-' : '+') . $this->y, $format);
            $format = str_replace('%R%m', ($this->invert ? '-' : '+') . $this->m, $format);
            $format = str_replace('%R%d', ($this->invert ? '-' : '+') . $this->d, $format);
            $format = str_replace('%R%h', ($this->invert ? '-' : '+') . $this->h, $format);
            $format = str_replace('%R%i', ($this->invert ? '-' : '+') . $this->i, $format);
            $format = str_replace('%R%s', ($this->invert ? '-' : '+') . $this->s, $format);

            $format = str_replace('%y', $this->y, $format);
            $format = str_replace('%m', $this->m, $format);
            $format = str_replace('%d', $this->d, $format);
            $format = str_replace('%h', $this->h, $format);
            $format = str_replace('%i', $this->i, $format);
            $format = str_replace('%s', $this->s, $format);

            return $format;
        }
    }

    /**
     * @group 5.3 back compatibility
     */
    function date_diff(DateTime $date1, DateTime $date2)
    {
        $diff = new DateInterval();
        if($date1 > $date2) {
            $tmp = $date1;
            $date1 = $date2;
            $date2 = $tmp;
            $diff->invert = true;
        }

        $diff->y = ((int) $date2->format('Y')) - ((int) $date1->format('Y'));
        $diff->m = ((int) $date2->format('n')) - ((int) $date1->format('n'));
        if($diff->m < 0) {
            $diff->y -= 1;
            $diff->m = $diff->m + 12;
        }
        $diff->d = ((int) $date2->format('j')) - ((int) $date1->format('j'));
        if($diff->d < 0) {
            $diff->m -= 1;
            $diff->d = $diff->d + ((int) $date1->format('t'));
        }
        $diff->h = ((int) $date2->format('G')) - ((int) $date1->format('G'));
        if($diff->h < 0) {
            $diff->d -= 1;
            $diff->h = $diff->h + 24;
        }
        $diff->i = ((int) $date2->format('i')) - ((int) $date1->format('i'));
        if($diff->i < 0) {
            $diff->h -= 1;
            $diff->i = $diff->i + 60;
        }
        $diff->s = ((int) $date2->format('s')) - ((int) $date1->format('s'));
        if($diff->s < 0) {
            $diff->i -= 1;
            $diff->s = $diff->s + 60;
        }

        return $diff;
    }
}
