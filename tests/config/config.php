<?php
error_reporting(E_ALL|E_STRICT);

if ( phpversion() < 5.3) {
    require_once dirname(__FILE__) . '/../../source/sys/to_php53.php';
}

$config = array(
    'timezone' => 'Asia/Taipei',
    'encoding' => 'UTF-8',
    'language' => 'zh',
    'language_code' => 'zh-tw',
    'locale' => 'zh_TW.UTF-8',
    'enable_rewrite' => false,
    'cache_dir' => 'cache',
    'system_email' => 'noreply@localhost', // 系統的發信地址
    'service_email' => 'service@localhost', // 客服信箱
    'site_name' => 'Site Name',

    // 設定資料庫
    'db' => array(
        'host' => 'localhost',
        'name' => 'test',
        'charset' => 'utf8',
        'user' => 'dev',
        'password' => 'dev'
    ),

    // 分頁設定
    'pagination' => array(
        'rows_per_page'     => 20,      // 每頁顯示20筆資料
        'num_per_page'      => 10,      // 每次顯示10個頁碼
    ),

    // htmlpurifier
    'htmlpurifier' => array(
        'default' => array(
            'HTML.SafeIframe' => true,
            // can include YouTube and Vimeo only
            'URI.SafeIframeRegexp' => '%^(http:)?//(www.youtube(?:-nocookie)?.com/embed/|player.vimeo.com/video/)%',
        ),
        'admin' => array(
            'HTML.SafeIframe' => true,
            'CSS.AllowTricky' => true,
            // can include any page
            'URI.SafeIframeRegexp' => '%^(http(s)?:)?//%',
        )
    )

);

define('ROOT_PATH', dirname(dirname(dirname(__FILE__))) . DIRECTORY_SEPARATOR . 'source' . DIRECTORY_SEPARATOR);
define('SYS_PATH', ROOT_PATH . 'sys' . DIRECTORY_SEPARATOR);

if ( ! defined('ROOT_URL')) {
    define('ROOT_URL', rtrim(dirname($_SERVER['PHP_SELF']), '/.\\') . '/');
}
if ( ! defined('ASSETS_URL')) {
    define('ASSETS_URL', ROOT_URL . 'assets/');
    define('ASSETS_PATH', ROOT_PATH . 'assets' . DIRECTORY_SEPARATOR);
}
define('TRIM_MARKER', '⋯');
