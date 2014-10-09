<?php
/**
 * Functions
 *
 * @license MIT
 * @file
 */

/**
 * @name Array
 */
//{@
/**
 * Returns array value by $firstKey (and $secondKey)
 *
 * @param &array $array The array
 * @param string|null $firstKey First level. If `$firstKey` is null, this function will return the original array.
 * @param string $secondKey  Second level. If `$secondKey` is null, this function will return the array value at `$firstKey`.
 * @return mixed
 */
function array_get_value(&$array, $firstKey=NULL, $secondKey=NULL)
{
    if (NULL === $firstKey) {
        return $array;
    }
    if (!isset($array[$firstKey])) {
        return NULL;
    }
    $item = $array[$firstKey];
    if (NULL === $secondKey) {
        return $item;
    }
    return (isset($item[$secondKey]) ? $item[$secondKey] : NULL);
}
//@}
function array_delete_keys($array, $ignores=array())
{
    foreach($ignores as $key) {
        if (isset($array[$key])) {
            unset($array[$key]);
        }
    }
    return $array;
}
/**
 * @name FileSystem
 */
//{@
/**
 * Returns file path
 *
 * @param string $dir The folder
 * @param string $file The file name
 * @param string $suffix The suffix of file name.
 * @return string
 */
function get_file_path($dir, $file, $suffix=NULL)
{
    if ( !$file) {
        return '';
    }

    if ( NULL === $suffix) {
        return $dir . $file;
    }

    $info = pathinfo($file);
    if ( !isset($info['filename'])) {
        $info['filename'] = substr($info['basename'], 0, strlen($info['basename'])-strlen($info['extension'])-1);
    }
    return $dir . $info['filename'] . $suffix . ($info['extension'] ? '.' . $info['extension'] : '');
}

/**
 * 給人類讀的檔案大小
 *
 * @param integer file size in bytes
 * @return string in Byte/KB/MB/GB/TB/PB
 */
function formatBytes($bytes)
{
    if ( ! is_numeric($bytes) ) {
        return 'NaN';
    }

    $decr = 1024;
    $step = 0;
    $unit = array('Byte','KB','MB','GB','TB','PB');
    while (($bytes / $decr) > 0.9) {
        $bytes = $bytes / $decr;
        $step++;
    }

    return round($bytes, 2) . $unit[$step];
}
/**
 * 傳回以 bytes 單位的數字
 *
 * @param string $val example: '10G', '2M' or '6K'
 * @return integer
 */
function returnBytes($val)
{
    $val = trim($val);
    $last = strtoupper($val[strlen($val)-1]);
    switch($last) {
        // The 'G' modifier is available since PHP 5.1.0
        case 'G':
            $val *= 1024;
        case 'M':
            $val *= 1024;
        case 'K':
            $val *= 1024;
    }

    return $val;
}

//@}
/**
 * @name HTTP
 */
//{@
/**
 * Detects whether or not current request is a https request.
 *
 * @return boolean
 */
function is_ssl()
{
   if ( isset($_SERVER['HTTPS']) ) {
       if ( 'on' == strtolower($_SERVER['HTTPS']) ) {
           return true;
       }
       if ( '1' == $_SERVER['HTTPS'] ) {
           return true;
       }
   }
   if ( isset($_SERVER['SERVER_PORT']) && ( '443' == $_SERVER['SERVER_PORT'] ) ) {
       return true;
   }
   return false;
}
/**
 * Returns current user ip
 *
 * @return string
 */
function get_user_ip()
{
    if (empty($_SERVER['HTTP_X_FORWARDED_FOR'])) {
        return$_SERVER['REMOTE_ADDR'];
    }
    $ip = explode(',', $_SERVER['HTTP_X_FORWARDED_FOR']);
    return $ip[0];
}
/**
 * Returns domain name with http protocal.
 *
 * @return string
 */
function get_domain_url()
{
    $is_ssl = isset($_REQUEST['is_ssl']) ? $_REQUEST['is_ssl'] : is_ssl();
    $protocol = $is_ssl ? 'https://' : 'http://';
    return $protocol . $_SERVER['HTTP_HOST'];
}
/**
 * 網址重定向
 *
 * @param string $location 目標網址
 * @param boolean $exit 重定向之後是否直接結束程式？
 * @param integer $code 重定向 HTTP Code
 * @param array $headerBefore 預先輸出的 http header
 * @param array $headerAfter 隨後輸出的 http header
 * @return void
 **/
function redirect($location, $exit=true, $code=303, $headerBefore=NULL, $headerAfter=NULL)
{
    if($headerBefore!==null){
        foreach($headerBefore as $h){
            header($h);
        }
    }
    if ( $_SERVER['IS_IIS'] && false === strpos($location, '://') ) {
        $location = get_domain_url() . $location;
    }

    header('Location: ' . $location, true, $code);
    if($headerAfter!==null){
        foreach($headerAfter as $h){
            header($h);
        }
    }
    if($exit)
        exit;
}
//@}
/**
 * @name String
 */
//{@
/**
 * 修正html特殊字，包含nl2br
 *
 * @param string $value 要修正的字串
 * @return string
 **/
function HtmlEncode($value) {
    return nl2br(htmlspecialchars($value, ENT_QUOTES, 'UTF-8'));
}
/**
 * 修正html特殊字，不含nl2br
 *
 * @param string $value 要修正的字串
 * @return string
 **/
function HtmlValueEncode($value) {
    return htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
}
/**
 * 過濾 html 標籤
 *
 * @param string $value 要過濾的字串
 * @param string $key htmlpurifier 設定鍵，請見 `config/config.php` 的 `htmlpurifier` 選項
 * @return string
 **/
function HtmlClean($value, $key='default')
{
    static $purifiers = array();

    if (!isset($purifiers[$key])) {
        $purifiers[$key] = __createHTMLPurifier($key);
    }

    return $purifiers[$key]->purify($value);
}
/**
 * Create a new HTMLPurifier instance according to htmlpurifier configuration set.
 *
 * @param string $key htmlpurifier 設定鍵，請見 `config/config.php` 的 `htmlpurifier` 選項
 * @return HTMLPurifier
 */
function __createHTMLPurifier($key='default')
{
    $appConf = App::conf();
    $settings = isset($appConf->htmlpurifier->{$key})
        ? (array) $appConf->htmlpurifier->{$key}
        : (array) $appConf->htmlpurifier->default;


    $pConfig = HTMLPurifier_Config::createDefault();
    $pConfig->loadArray(array_merge(array(
        'Output.FlashCompat' => true,
        'Filter.YouTube' => true,

        'HTML.SafeObject' => true,
        'HTML.SafeEmbed' => true,
        'HTML.Trusted' => true,
        'HTML.FlashAllowFullScreen' => true,

        'Attr.EnableID' => true,
        'Attr.AllowedFrameTargets' => array('_blank'),

        'HTML.AllowedModules' => array(
            'CommonAttributes', 'Text', 'Hypertext', 'List',
            'Presentation', 'Edit', 'Bdo', 'Tables', 'Image',
            'StyleAttribute',
            // Unsafe:
            //'Scripting', 'Object', 'Forms',
            'Object', 'Iframe', 'Target',
            // Sorta legacy, but present in strict:
            'Name',
        ),

        'Cache.SerializerPath' => ROOT_PATH . App::conf()->cache_dir . DIRECTORY_SEPARATOR . 'htmlpurifier'
    ), $settings));

    $def = $pConfig->getHTMLDefinition(true);
    $def->addAttribute('table', 'align', 'Enum#left,center,right');
    $def->addElement('u', 'Inline', 'Inline', 'Common');
    $def->addElement('s', 'Inline', 'Inline', 'Common');
    $def->addElement('strike', 'Inline', 'Inline', 'Common');
    $def->addElement('font', 'Inline', 'Inline', 'Common');
    $def->addAttribute('font', 'color', 'Color');
    $def->addAttribute('font', 'face', 'Text');
    $def->addAttribute('font', 'size', 'Text');

    # css property
    $def = $pConfig->getCSSDefinition();
    $def->info['page-break-after'] = new HTMLPurifier_AttrDef_Enum(array(
        'auto', 'always', 'avoid', 'left', 'right', 'inherit'
    ));


    return new HTMLPurifier($pConfig);
}
/**
 * 將字串轉為 ASCII Code 以防止爬蟲蒐集email 地址
 *
 * @param string $email
 * @return string
 */
function HtmlEncodeEmail($email)
{
    $output = '';
    $length = strlen($email);
    for ($i = 0; $i < $length; $i++) { $output .= '&#'.ord($email[$i]).';'; }
    return $output;
}
/**
 * 修正JavaScript特殊字
 *
 * @param string $value 要修正的字串
 * @return string
 **/
function JsEncode($value) {
    return addcslashes($str,"\\\'\"&\n\r\t<>");
}

/**
 * camelize 字串
 *
 * @param string 要轉換的字串
 * @param boolean 首字母是否大寫？
 * @return string
 */
function camelize($str, $upper_first = true)
{
    $str = str_replace(' ', '', ucwords(str_replace(array('-', '_'), ' ', $str)));

    if ( ! $upper_first ) {
        return lcfirst($str);
    }
    return $str;
}
/**
 * 傳回台灣貨幣格式
 *
 * @param integer|float|decimal $number
 * @param string $sign dollar sign
 * @return string
 */
function currencyTW($number, $sign='NT$')
{
    return $sign . number_format($number);
}
//@}
/**
 * @name DateTime
 */
//{@
/**
 * 時間簡潔顯示
 *
 * 今日的只顯示時間，昨日以前僅顯示日期
 *
 * @param string $date 可被 strtotime 成功轉換成時間的時間字串
 * @param function $callback 昨日前的時間，將會呼叫此回呼函式並將日期字串帶入作為參數
 * @param string $date_format http://www.php.net/manual/en/function.date.php
 * @param string $time_format http://www.php.net/manual/en/function.date.php
 * @return string
 */
function shorten_date($date, $callback=NULL, $date_format='Y/m/d', $time_format='H:i:s')
{
    if ( ! $date) {
        return '';
    }
    $time = strtotime($date);
    $date = date($date_format, $time);
    if ( date($date_format) === $date) { // return only time if the date is today
        return date($time_format, $time);
    }
    if ( $callback ) {
        $date = $callback($date);
    }
    return $date;
}

/**
 * split datetime string into associative array.
 *
 * @param string $time datetime string
 * @return array
 */
function datetime_info($time)
{
    if ( ! $time = strtotime($time)) {
        return array( 'date' => NULL, 'time' => NULL);
    }
    return array(
        'date' => date('Y/m/d', $time),
        'time' => date('H:i:s', $time)
    );
}
/**
 * concat datetime array into sql datetime format.
 *
 * @param array $time
 * @return string
 */
function sql_datetime($time)
{
    if ( ! is_array($time)) {
        return $time;
    }
    if ( ! isset($time['date'])) {
        return '';
    }
    $time = trim($time['date'] . ' ' . (isset($time['time']) ? $time['time'] : ''));
    return $time;
}

function timezone_offset($timezone)
{
    $now = new DateTime('now', new DateTimeZone($timezone));
    $mins = $now->getOffset() / 60;
    $sgn = ($mins < 0 ? -1 : 1);
    $mins = abs($mins);
    $hrs = floor($mins / 60);
    $mins -= $hrs * 60;
    $offset = sprintf('%+d:%02d', $hrs*$sgn, $mins);
    return $offset;
}
//@}
/**
 * @name UI
 */
//{@
/**
 * 顯示 session 訊息
 *
 * @param boolean $showDismiss
 * @return void
 **/
function fresh_message($showDismiss=true)
{
    if (isset($_SESSION['fresh_error'])) {
        fresh_error_message($showDismiss);
    }
    if ( empty($_SESSION['fresh'])) {
        return;
    }
    block_message($_SESSION['fresh'], 'success', $showDismiss);
    unset($_SESSION['fresh']);
}
/**
 * 顯示 session error 訊息
 *
 * @param boolean $showDismiss
 * @return void
 **/
function fresh_error_message($showDismiss=true)
{
    if ( empty($_SESSION['fresh_error'])) {
        return;
    }
    block_message($_SESSION['fresh_error'], 'danger', $showDismiss);
    unset($_SESSION['fresh_error']);
}

/**
 * 顯示訊息區塊
 *
 * @param string $message
 * @param string $type  bootstrap `.alert-$type`
 * @param boolean $showDismiss
 * @return void
 **/
function block_message($message, $type = 'danger', $showDismiss=false) {
    echo '<div class="alert alert-', $type,'">';
    if ( $showDismiss ) {
        echo '<a class="close" data-dismiss="alert" aria-hidden="true" href="#">&times;</a>';
    }
    echo  $message, '</div>';
}
/**
 * 顯示頁籤 UI
 *
 * @param array $list
 * @param string $current
 * @param array $options
 *      'urlPrefix' => '',      // 網址位於 key 之前的部份
 *      'urlSuffix' => '',      // 網址位於 key 之後的部份
 *      'urlParams' => NULL,    // 附加網址參數
 *      'appendLi' => '',       // 附加頁籤
 *      'nameKey' => NULL,      // 如果 $list value 是 array 的話，將採用這裡的 namekey 的值作為顯示文字
 *      'tabStyle' => 'nav nav-tabs'    // css class
 * @return void
 */
function ui_tabs($list, $current, $options=array())
{
    $options = array_merge(array(
        'urlPrefix' => '',
        'urlSuffix' => '',
        'urlParams' => NULL,
        'appendLi' => '',
        'nameKey' => NULL,
        'tabStyle' => 'nav nav-tabs'
    ), $options);

    $urls = App::urls();

    echo '<ul class="', $options['tabStyle'],' clearfix">';
    foreach ( $list as $key => $name ) {
      if (is_array($name) && NULL !== $options['nameKey']) {
        $name = $name[$options['nameKey']];
      }
      echo '<li'
        . ( $key == $current ? ' class="active"' : '')
        . '><a href="' . $urls->urlto($options['urlPrefix'] . $key . $options['urlSuffix'], $options['urlParams']) .'">' . HtmlValueEncode($name) . '</a></li>';
    }
    echo $options['appendLi'], '</ul>';
}

/**
 * 顯示麵包屑
 *
 * @param array $path           路徑列表，格式為
 *      path => display         或
 *      path => {
 *          name => 'display name'
 *          params => 'url params'
 *      }
 * @param string $linkCurrent   目前位置
 * @param string $beforeText    設定位於麵包屑開始處的文字
 * @return void
 */
function breadcrumbs($path, $linkCurrent=false, $beforeText=NULL)
{
    $urls = App::urls();

    if ( ! $linkCurrent ) {
        $current = array_pop($path);
        if (is_array($current)) {
            $current = $current['name'];
        }
    }

    echo '<ul class="breadcrumb">';
    if ( $beforeText) {
        echo '<li>', HtmlValueEncode($beforeText), '</li>';
    }
    if ( ! empty($path)) {
        $size = count($path);
        $count = 0;
        foreach ( $path as $node => $name) {
            $params = NULL;
            if ( is_array($name)) {
                $params = empty($name['params']) ? NULL : $name['params'];
                $name = $name['name'];
            }
            $count++;
            echo '<li><a href="' . $urls->urlto($node, $params) . '" data-pjax>' . HtmlValueEncode($name) . '</a></li>';
        }
    }
    if ( ! $linkCurrent ) {
        echo '<li>' . HtmlValueEncode($current) . '</li>';
    }
    echo '</ul>';
}
//@}
/**
 * @name Form
 */
//{@
/**
 * 根據 $field 的 'required' 選項，視情況傳回 'required="required"' html 屬性
 *
 * @param array $field
 * @return string
 */
function input_required($field)
{
    if ( $field['required']) {
        return ' required="required"';
    }
    return '';
}
/**
 * 根據傳入的陣列印出 <option> 標籤
 *
 * @param array $array 要印出 <option> 標籤的鍵值對
 * @param string $default 預設選取的鍵
 * @return void
 */
function html_options($array, $default=NULL, $attrs=array())
{
    foreach ( $array as $value => $label ) {
        echo '<option value="', HtmlValueEncode($value),'"',
            ($value == $default ? ' selected="selected"' : ''),
            (isset($attrs[$value]) ? ' ' . $attrs[$value] : ''),
            '>', HtmlValueEncode($label),'</option>';
    }
}
/**
 * 根據傳入的陣列印出 input:checkbox, input:radio 標籤
 *
 * @param string $type radio or checkbox
 * @param array $array 要印出的顯示文字鍵值對
 * @param string $name input name
 * @param string $default 預設選取的鍵
 * @param string $attrs 其他要附加到 input 的 html 屬性
 * @param integer $breakEvery 多少個項目後斷行
 * @return void
 */
function html_checkboxes($type, $array, $name, $default=NULL, $attrs='', $breakEvery=NULL)
{
    $inline = ($breakEvery !== 'block');
    $i = 0;

    $input_attrs = $attrs;
    foreach ( $array as $value => $label)
    {
        if ( is_array($attrs) ) {
            $input_attrs = isset($attrs[$value]) ? $attrs[$value] : '';
        }
        $i++;
        $value_enc = HtmlValueEncode($value);
        echo '<label class="',
            ( $inline ? 'inline ' : ''),
            $type, ' nowrap margin-right"><input type="', $type, '" name="', $name,
            ($type === 'checkbox' ? '[' . $value_enc . ']' : ''),
            '" value="', $value_enc, '"',
            (
                ( $type === 'checkbox' && isset($default[$value])) ||
                ( $type === 'radio' && $value == $default )
                ? ' checked="checked"' : ''
            ),
            ' ' . $input_attrs . ' />', HtmlEncode($label), '</label>';
        if ( $inline && $breakEvery && 0 === ($i%$breakEvery)) {
            echo '<br />';
        }
    }
}
/**
 * 巢狀 html checkbox 選項
 *
 * @param string $type radio or checkbox
 * @param array $array 要印出的顯示文字鍵值對
 * @param string $name input name
 * @param string $default 預設選取的鍵
 * @param string $attrs 其他要附加到 input 的 html 屬性
 * @return void
 */
function nested_html_checkboxes($type, $array, $name, $default=NULL, $attrs='')
{
    $i = 0;

    $input_attrs = $attrs;
    echo '<ul class="list-unstyled margin-left">';
    foreach ( $array as $item)
    {
        $value = $item->id;
        $label = $item->name;

        if ( is_array($attrs) ) {
            $input_attrs = isset($attrs[$value]) ? $attrs[$value] : '';
        }
        $i++;
        $value_enc = HtmlValueEncode($value);
        echo '<li><label class="nowrap margin-right-half"><input type="', $type, '" name="', $name,
            ($type === 'checkbox' ? '[' . $value_enc . ']' : ''),
            '" value="', $value_enc, '"',
            (
                ( $type === 'checkbox' && isset($default[$value])) ||
                ( $type === 'radio' && $value == $default )
                ? ' checked="checked"' : ''
            ),
            ' ' . $input_attrs . ' /> ', HtmlEncode($label), '</label>';
        if (!empty($item->items)) {
            nested_html_checkboxes($type, $item->items, $name, $default, $attrs);
        }
        echo '</li>';
    }
    echo '</ul>';
}

/**
 * 根據傳入的陣列印出 input:checkbox, input:radio 標籤 for Twitter Bootstrap 3
 *
 * @param string $type radio or checkbox
 * @param array $array 要印出標籤的鍵值對
 * @param string $name input name
 * @param string $default 預設選取的鍵
 * @param string $attrs 其他要附加到 input 的 html 屬性
 * @param integer $breakEvery 多少個項目後斷行
 * @return void
 */
function bs3_html_checkboxes($type, $array, $name, $default=NULL, $attrs='', $breakEvery=NULL)
{
    $inline = ($breakEvery !== 'block');
    $i = 0;

    $input_attrs = $attrs;
    foreach ( $array as $value => $label)
    {
        if ( is_array($attrs) ) {
            $input_attrs = isset($attrs[$value]) ? $attrs[$value] : '';
        }
        $i++;
        $value_enc = HtmlValueEncode($value);
        echo '<label class="',
            ( $inline ? $type.'-inline ' : ''),
            ' nowrap margin-right-half"><input type="', $type, '" name="', $name,
            ($type === 'checkbox' ? '[' . $value_enc . ']' : ''),
            '" value="', $value_enc, '"',
            (
                ( $type === 'checkbox' && isset($default[$value])) ||
                ( $type === 'radio' && $value == $default )
                ? ' checked="checked"' : ''
            ),
            ' ' . $input_attrs . ' />', HtmlEncode($label), '</label>';
        if ( $inline && $breakEvery && 0 === ($i%$breakEvery)) {
            echo '<br />';
        }
    }
}

/**
 * 輸出 hidden inputs
 *
 * @param array $params 要輸出的隱藏欄位鍵值對
 * @param array $ignores $params 的忽略清單，以鍵做值
 * @return void
 */
function html_hidden_inputs($params, $ignores=NULL)
{
    if ( NULL !== $ignores ) {
        foreach ( $ignores as $key) {
            if (isset($params[$key])) {
                unset($params[$key]);
            }
        }
    }
    foreach ( $params as $name => $value) {
        if ( !is_array($value)) {
            echo '<input type="hidden" name="', HtmlValueEncode($name), '" value="', HtmlValueEncode($value), '" />';
            continue;
        }
        foreach ( $value as $vName => $vValue) {
            echo '<input type="hidden" name="', HtmlValueEncode($name), '[', HtmlValueEncode($vName), ']" value="', HtmlValueEncode($vValue), '" />';
        }
    }
}
/**
 * 根據傳入的陣列印出 btn-group radio 標籤
 *
 * @param array $array 要印出標籤的鍵值對
 * @param string $name input name
 * @param string $default 預設選取的鍵
 * @param string $btn_class Button 的 class attr value
 * @param string $group_attrs `div.btn-group` 的 html 屬性
 * @param string|array $button_attrs Button 的 html 屬性，類型為 string 時套用到所有button上
 * @param string $encoded 顯示文字是否不用再次 HtmlEncode
 * @return void
 */
function btn_group_radios($array, $name, $default=NULL, $btn_class=NULL, $group_attrs=NULL, $button_attrs=NULL, $encoded=false)
{
    $active_class = 'btn-primary';
    echo '<div class="btn-group inline-block" data-toggle-name="' . $name . '" data-toggle="buttons-radio" data-active-class="', $active_class, '"'
        . ( $group_attrs ? ' ' . $group_attrs : '')
        . '>';

    $attrs = '';
    if ( NULL === $button_attrs ) {
        $attrs = '';
        $has_attrs = true;
    } else if ( is_string($button_attrs)) {
        $attrs = ' ' . $button_attrs;
        $has_attrs = true;
    } else {
        $has_attrs = false;
    }

    foreach ( $array as $value => $label) {
        if ( ! $has_attrs && is_array($button_attrs) && isset($button_attrs[$value])) {
            $attrs = ' ' . $button_attrs[$value];
        }
        echo '<button type="button" value="' . HtmlValueEncode($value) . '" class="btn'
            . ( $btn_class ? ' ' . $btn_class : '') . ( $value == $default ? ' active ' . $active_class : '') . '"'
            . $attrs
            . '>' . ( $encoded ? $label : HtmlValueEncode($label)) . '</button>';
    }
    echo '</div><input type="hidden" name="' . $name . '" value="' . $default . '" />';
}

/**
 * 顯示列表選擇項目的動作
 *
 * @param array $actions
 * @return void
 */
function show_actions($actions, $default=NULL, $class='input-medium')
{
    if ( empty($actions)) {
        return;
    }

    echo '<label class="control-label padding-right">', _e('Selected Items'), ': </label><select class="form-control auto-width ' . $class . '" name="action" id="selActions">';
    foreach ( $actions as $action => $info) {
        echo '<option value="'.$action.'"';
        if ( $action === $default) {
            echo ' selected="selected"';
        }
        if ( !empty($info['data'])) {
            foreach ( $info['data'] as $key => $value) {
                echo ' data-'.$key.'="' . HtmlValueEncode($value) . '"';
            }
        }
        echo '>'.HtmlValueEncode($info['name']).'</option>';
    }
    echo '</select>';
}

/**
 * 顯示含下拉選單的搜尋輸入框
 *
 * @param Search $search 必須有以下屬性
 *  ->byList:   搜尋項目清單
 *  ->by:       目前的搜尋項目
 *  ->keyword   目前的搜尋關鍵字
 * @param array $unsets 表單提交時，不提交的舊GET名稱清單
 * @param array $inputClass
 *  [by] = 'input-medium'       搜尋項目下拉選單的 input class
 *  [query] = 'input-medium'    搜尋關鍵字輸入框的 input class
 *
 *
 * @return void
 */
function search_input($search, $unsets=array(), $inputClass=array('by' => 'input-medium', 'query' => 'input-medium'))
{
    $urls = App::urls();
?>
  <?php if (count($search->byList) > 1): ?>
  <div class="col-xs-3">
      <select class="form-control <?php echo $inputClass['by'] ?>" name="by">
      <option value=""><?php echo _e('Search By') ?></option>
        <?php html_options($search->byList, $search->by); ?>
      </select>
  </div>
  <?php else: ?>
  <input name="by" type="hidden" value="<?php echo current(array_keys($search->byList)) ?>" />
  <?php endif; ?>

  <div class="input-group col-xs-4 no-padding">
      <input class="form-control <?php echo $inputClass['query'] ?>" type="search" name="keyword" placeholder="<?php echo (count($search->byList) > 1 ? 'Search...' : HtmlValueEncode(current($search->byList))) ?>" value="<?php echo HtmlValueEncode($search->keyword) ?>" />
      <span class="input-group-btn"><button type="submit" class="btn btn-primary"><?php echo _e('Search') ?></button></span>
  </div>
<?php
  $unsets[] = 'by';
  $unsets[] = 'keyword';
  html_hidden_inputs($_GET, $unsets);
} // search_form

//@}
/**
 * @name App
 */
//{@
/**
 * 輸出 google_analytics 追蹤 code
 *
 * @param string $account
 * @param string $domain
 * @return void
 */
function google_analytics($account, $domain)
{
    if (empty($account)) {
        return;
    }
?>
<script type="text/javascript">

  var _gaq = _gaq || [];
  _gaq.push(['_setAccount', '<?php echo HtmlValueEncode($account) ?>']);
<?php if (!empty($domain)): ?>
  _gaq.push(['_setDomainName', '<?php echo HtmlValueEncode($domain) ?>']);
<?php endif; ?>
  _gaq.push(['_trackPageview']);

  (function() {
    var ga = document.createElement('script'); ga.type = 'text/javascript'; ga.async = true;
    ga.src = ('https:' == document.location.protocol ? 'https://ssl' : 'http://www') + '.google-analytics.com/ga.js';
    var s = document.getElementsByTagName('script')[0]; s.parentNode.insertBefore(ga, s);
  })();
</script>
<?php
}
function meridiem_time($time)
{
    return date('A h:i', strtotime($time));
}
function language_links()
{
    $urls = App::urls();
    $links = array();
    foreach (App::conf()->locales as $key => $lang) {
        $q = (isset($_GET['q']) ? $_GET['q'] : '');
        $params = array_merge($_GET, array('lang' => $key));
        $links[] = '<a href="' . $urls->urlto($q, $params) . '">' . HtmlValueEncode($lang->name) . '</a>';
    }
    return $links;
}
//@}
