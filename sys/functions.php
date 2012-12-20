<?php
// HTTP Functions
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
function get_user_ip()
{
    if (empty($_SERVER['HTTP_X_FORWARDED_FOR'])) {
        return$_SERVER['REMOTE_ADDR'];
    }
    $ip = explode(',', $_SERVER['HTTP_X_FORWARDED_FOR']);
    return $ip[0];
}
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
function redirect($location, $exit=true, $code=302, $headerBefore=NULL, $headerAfter=NULL)
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
// String Functions
/**
 * 修正html特殊字，包含nl2br
 *
 * @param string 要修正的字串
 * @return string
 **/
function HtmlEncode($value) {
    return nl2br(htmlspecialchars($value, ENT_QUOTES, 'UTF-8'));
}
/**
 * 修正html特殊字，不含nl2br
 *
 * @param string 要修正的字串
 * @return string
 **/
function HtmlValueEncode($value) {
    return htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
}
/**
 * 過濾 html 標籤
 *
 * @param string 要過濾的字串
 * @return string
 **/
function HtmlClean($value)
{
    static $purifier;

    if ( $purifier === null ) {
        App::loadHelper('htmlpurifier/HTMLPurifier.standalone', false, 'common');
        App::loadHelper('htmlpurifier/standalone/HTMLPurifier/Filter/YouTube', false, 'common');
        $config = HTMLPurifier_Config::createDefault();

        $config->set('Core.EscapeInvalidTags', true);
        $config->set('Output.FlashCompat', true);
        $config->set('Filter.YouTube', true);

        $config->set('HTML.SafeObject', true);
        $config->set('HTML.SafeEmbed', true);
        $config->set('HTML.Trusted', true);
        $config->set('HTML.FlashAllowFullScreen', true);
        $config->set('HTML.AllowedModules', array(
            'CommonAttributes', 'Text', 'Hypertext', 'List',
            'Presentation', 'Edit', 'Bdo', 'Tables', 'Image',
            'StyleAttribute',
            // Unsafe:
            //'Scripting', 'Object', 'Forms',
            'Object', 'Iframe', 'Target',
            // Sorta legacy, but present in strict:
            'Name',
        ));

        $config->set('Attr.AllowedFrameTargets', array('_blank'));
        $config->set('Cache.SerializerPath', ROOT_PATH . App::conf()->cache_dir . DIRECTORY_SEPARATOR . 'htmlpurifier');

		$def = $config->getHTMLDefinition(true);
		$def->addAttribute('table', 'align', 'Enum#left,center,right');

        $purifier = new HTMLPurifier($config);
    }
    return $purifier->purify($value);
}
/**
 * 將字串轉為 ASCII Code 以防止爬蟲蒐集email 地址
 *
 * @return string
 */
function HtmlEncodeEmail($e)
{
    $output = '';
    $length = strlen($e);
    for ($i = 0; $i < $length; $i++) { $output .= '&#'.ord($e[$i]).';'; }
    return $output;
}
/**
 * 修正JavaScript特殊字
 *
 * @param string 要修正的字串
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
function shorten_date($date, $date_format='Y-m-d', $time_format='H:i:s')
{
    if ( ! $date) {
        return '';
    }
    $time = strtotime($date);
    $date = date($date_format, $time);
    if ( date($date_format) === $date) {
        return date($time_format, $time);
    }
    return $date;
}
function input_required($field)
{
    if ( $field['required']) {
        return ' required="required"';
    }
    return '';
}
// System View Functions
/**
 * 顯示 session 訊息
 *
 * @return void
 **/
function fresh_message($showDismiss=true)
{
    if ( empty($_SESSION['fresh'])) {
        return;
    }
    block_message($_SESSION['fresh'], 'success', $showDismiss);
    unset($_SESSION['fresh']);
}
/**
 * 顯示訊息區塊
 *
 * @return void
 **/
function block_message($message, $type = 'error', $showDismiss=false) {
    echo '<div class="alert alert-', $type,'">';
    if ( $showDismiss ) {
        echo '<a class="close" data-dismiss="alert" href="#">&times;</a>';
    }
    echo  $message, '</div>';
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
 * @param array $array 要印出標籤的鍵值對
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
    foreach ( $array as $value => $label)
    {
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
            ' ' . $attrs . ' />', HtmlEncode($label), '</label>';
        if ( $inline && $breakEvery && 0 === ($i%$breakEvery)) {
            echo '<br />';
        }
    }
}
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
        echo '<input type="hidden" name="', HtmlValueEncode($name), '" value="', HtmlValueEncode($value), '" />';
    }
}
/**
 * 根據傳入的陣列印出 btn-group radio 標籤
 *
 * @param array $array 要印出標籤的鍵值對
 * @param string $name input name
 * @param string $default 預設選取的鍵
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

    echo '選擇的項目: <select class="' . $class . '" name="action" id="selActions">';
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

function breadcrumbs($path, $linkCurrent=false, $beforeText=NULL)
{
    $urls = App::urls();

    if ( ! $linkCurrent ) {
        $current = array_pop($path);
    }

    echo '<ul class="breadcrumb">';
    if ( $beforeText) {
        echo '<li>', HtmlValueEncode($beforeText), '<span class="divider">:</span></li>';
    }
    if ( ! empty($path)) {
        $size = count($path);
        $count = 0;
        foreach ( $path as $node => $name) {
            $count++;
            echo '<li><a href="' . $urls->urlto($node) . '">' . HtmlValueEncode($name) . '</a>';
            if ( !$linkCurrent || $count !== $size) {
                echo ' <span class="divider">/</span>';
            }
            echo '</li>';
        }
    }
    if ( ! $linkCurrent ) {
        echo '<li>' . HtmlValueEncode($current) . '</li>';
    }
    echo '</ul>';
}
function search_input($searchby, $unsets=array())
{
    $urls = App::urls();
?>
  <?php if (count($searchby) > 1): ?>
  <select class="input-medium" name="by">
    <option value="">搜尋欄位</option>
    <?php html_options($searchby, (isset($_GET['by']) ? $_GET['by'] : null)); ?>
  </select>
  <?php else: ?>
  <input name="by" type="hidden" value="<?php echo current(array_keys($searchby)) ?>" />
  <?php endif; ?>

  <div class="input-append">
      <input class="search-query input-medium" type="search" name="keyword" placeholder="Search..." value="<?php if ( isset($_GET['keyword'])): echo HtmlValueEncode($_GET['keyword']); endif; ?>" /><button type="submit" class="btn btn-success">搜尋</button>
  </div>
<?php
  $unsets[] = 'by';
  $unsets[] = 'keyword';
  html_hidden_inputs($_GET, $unsets);
} // search_form

// App View Functions
function visitors_count($counter_id=1)
{
    $id = (int)$counter_id;
    $visted_key = 'visited_' . $id;
    $counter = new stdclass;
    $counter->total = 0;

    $db = App::db();
    if ( ! isset($_SESSION[$visted_key]) || ! $_SESSION[$visted_key]) {
        $_SESSION[$visted_key] = 1;
        $db->exec('UPDATE `counter` SET `counts` = `counts` + 1 WHERE `id` = ' . $id);
    }
    return $db->query('SELECT `counts` FROM `counter` WHERE `id` = ' . $id)->fetchColumn();
}
