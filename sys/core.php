<?php
class NotFoundException extends Exception {}

class App
{
    public static $id = 'frontend';
    private static $_urlsList = array();

    private static function _array2Obj($array)
    {
        foreach ( $array as $k => $value ) {
            if ( is_array($value)) {
                $keys = array_keys($value);
                if ( ! is_numeric($keys[0])) {
                    $array[$k] = self::_array2Obj($value);
                }
            }
        }
        return (object) $array;
    }
    /**
     * 啟動應用程式
     *
     * @param array $routeConfig
     * @param array $aclConfig
     * @param string $aclRole
     * @return void
     */
    public static function run($routeConfig=NULL, $aclConfig=NULL, $aclRole='anonymous')
    {
        self::prepare();
        self::route($routeConfig)->parse();
        self::acl($aclConfig, $aclRole)->check();
        self::doAction($_REQUEST['controller'], $_REQUEST['action']);
    }

    public static function prepare()
    {
        $conf = App::conf();

        date_default_timezone_set($conf->timezone);

        setlocale(LC_ALL, $conf->locale);

        if ( function_exists('mb_internal_encoding')) {
            mb_internal_encoding($conf->encoding);
        }

        // REQUEST_URI in IIS
        $_SERVER['IS_IIS'] = (false !== strpos(strtolower($_SERVER['SERVER_SOFTWARE']), 'microsoft-iis'));
        if ( ! isset($_SERVER['REQUEST_URI']) ) {
            $_SERVER['REQUEST_URI'] = $_SERVER['PHP_SELF'];
            if ( isset($_SERVER['QUERY_STRING'])) {
                $_SERVER['REQUEST_URI'] .= '?' . $_SERVER['QUERY_STRING'];
            }
        }

        // php5 - stripslashes_gpc
        if ( get_magic_quotes_gpc()) {
            $stripslashes_gpc = create_function('&$value', '$value = stripslashes($value);');
            array_walk_recursive($_GET, $stripslashes_gpc);
            array_walk_recursive($_POST, $stripslashes_gpc);
            array_walk_recursive($_COOKIE, $stripslashes_gpc);
            array_walk_recursive($_REQUEST, $stripslashes_gpc);
        }

        $_REQUEST['is_ajax'] = (isset($_SERVER['HTTP_X_REQUESTED_WITH']) && $_SERVER['HTTP_X_REQUESTED_WITH'] == "XMLHttpRequest");
        $_REQUEST['is_ssl'] = is_ssl();
    }
    public static function doAction($controllerName, $actionName)
    {
        // load file
        if ( '_' === $actionName{0} ) {
            throw new NotFoundException(_e('頁面不存在'));
        }
        $class = camelize($controllerName);
        $action = camelize($actionName, false);

        $file = ROOT_PATH . self::$id . '/controllers/' . $class . '.php';
        if ( ! file_exists($file)) {
            throw new NotFoundException(_e('頁面不存在'));
        }
        require $file;

        $class .= 'Controller';
        $controller = new $class();

        if ( ! is_callable(array($controller, $action))) {
            if ( ! $controller->defaultAction ) {
                throw new NotFoundException(_e('頁面不存在'));
            }
            $action = $controller->defaultAction;
        }

        if ( method_exists($controller, 'preAction')) {
            $controller->preAction();
        }

        $controller->{$action}();

        if ( method_exists($controller, 'postAction')) {
            $controller->postAction();
        }
    }


    public static function route($routeConfig=NULL)
    {
        static $route;

        if ( NULL === $route ) {
            $route = new Route($routeConfig);
            $route->appendDefaultRoute();
        } elseif ( NULL !== $routeConfig ) {
            $route->setRoutes($routeConfig);
            $route->appendDefaultRoute();
        }
        return $route;
    }
    public static function acl($aclConfig=NULL, $aclRole=NULL)
    {
        static $acl;

        if ( NULL === $acl ) {
            $acl = new Acl($aclConfig, $aclRole);
        }
        return $acl;
    }
    public static function conf()
    {
        static $conf = NULL;
        if ( $conf === NULL) {
            $conf = self::_array2Obj($GLOBALS['config']);
        }
        return $conf;
    }
    public static function db()
    {
       static $db;

        if ( $db === NULL ) {
            $conf = App::conf();
            $dsn = 'mysql:host=' . $conf->db->host
                    . ';dbname=' . $conf->db->name
                    . ';charset=' . $conf->db->charset;
            $db = new PDO($dsn, $conf->db->user, $conf->db->password, array(
                PDO::MYSQL_ATTR_INIT_COMMAND => "SET NAMES '".$conf->db->charset."';"
            ));
            $db->exec("SET time_zone = '" . $conf->timezone . "'");
            $db->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_OBJ);
            $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        }
        return $db;
    }
    public static function urls($id='primary', $urlBase=NULL, $paramName='q')
    {
        if ( isset(self::$_urlsList[$id])) {
            return self::$_urlsList[$id];
        }
        $urlBase = rtrim($urlBase, '/') . '/';
        return (self::$_urlsList[$id] = new Urls( $urlBase, $paramName ));
    }


    public static function view($appId=NULL)
    {
        return new View($appId ? $appId : self::$id);
    }

    public static function loadHelper($class, $createObj=false, $appId=NULL)
    {
        require_once ROOT_PATH . ($appId ? $appId : self::$id) . '/helpers/' . $class . '.php';
        if ( $createObj ) {
            return new $class;
        }
    }
    public static function loadModel($model, $createObj=false, $appId=NULL)
    {
        require_once ROOT_PATH . ($appId ? $appId : self::$id) . '/models/' . $model. '.php';

        if ( $createObj ) {
            return new $model;
        }
    }
    public static function loadViewModel($viewModelName, $createObj=false, $baseUrl=NULL, $appId=NULL)
    {
        require_once ROOT_PATH . ($appId ? $appId : self::$id) . '/viewModels/' . $viewModelName . '.php';
        if ( $createObj ) {
            $vm = $viewModelName . 'ViewModel';
            return new $vm($createObj, $baseUrl);
        }
    }
} // END class
abstract class Controller
{
    public $defaultAction;
    public $data = array();

    protected $_baseUrl;

    public function _prepareLayout($type=NULL)
    {
        $this->data = array_merge($this->data, array(
            'conf' => App::conf(),
            'urls' => App::urls(),
            'baseUrl' => $this->_baseUrl
        ));
    }
    public function _show404($backLink=false, $layout='layout')
    {
        header('404 Not Found');
        $this->data['page_title'] = $this->data['window_title'] = _e('404 頁面不存在!');
        $this->_showError(_e('您所要檢視的頁面不存在！'), $backLink, $layout);
    }
    public function _showError($content, $backLink=true, $layout='layout')
    {
        if ( $backLink ) {
            $content = '<div>' . $content . '</div>'
                . '<div class="alert-actions"><a href="javascript:window.history.back(-1)" class="btn btn-medium">' . _e('返回上一頁') . '</a></div>';
        }
        $this->data['content'] = $content;
        $this->_prepareLayout();
        $appId = 'common';
        $layoutAppId = null;
        App::view()->render('error', $this->data, compact('layout', 'appId', 'layoutAppId'));
    }
    public function _loadModule($module, $createObj=true, $appId=NULL)
    {
        require_once ROOT_PATH . ($appId ? $appId : App::$id) . '/modules/' . $module . '.php';
        if ( $createObj ) {
            $module = $module . 'Module';
            if ( is_array($createObj)) {
                return new $module($this, $createObj);
            }
            return new $module($this, NULL);
        }
    }
    public function _setTitle($title)
    {
        $this->data['page_title'] = $this->data['window_title'] = $title;
    }
    public function _appendTitle($title, $sep = array())
    {
        $sep = array_merge(array( 'pageTitleSep' => '-', 'windowTitleSep' => ' | '), $sep);
        $this->data['page_title'] .= ' ' . $sep['pageTitleSep'] . ' ' . $title;
        $this->data['window_title'] = $title . $sep['windowTitleSep'] . $this->data['window_title'];
    }

} // END class
abstract class BaseController extends Controller
{
    public function api($segment=2, $module=NULL)
    {
        try {
            $obj = ($module === NULL ? $this : $module);
            if (
                ! ($method = App::urls()->segment($segment)) ||
                ! ($method = '_api' . camelize($method, true)) ||
                ! is_callable(array($obj, $method))
            ) {
                throw new Exception(_e('未定義的方法'));
            }
            $response = $obj->{$method}();
        } catch ( Exception $ex ) {
            $response = array( 'error' => array( 'message' => $ex->getMessage() ) );
        }
        header('Content-Type: text/plain; charset="' . App::conf()->encoding . '"');
        echo json_encode($response);
    }
    public function _doSelectedAction($module=NULL)
    {
        if ( ! empty($_POST['action'])) {
            try {
                $obj = $module === NULL ? $this : $module;
                if ( empty($_POST['select']) || ! is_array($_POST['select'])) {
                    throw new Exception(_e('尚未選擇資料'));
                }
                $method = '_selected' . $_POST['action'];
                if ( ! method_exists($obj, $method)) {
                    throw new Exception(_e('沒有這個動作'));
                }
                $obj->{$method}();
                redirect($_SERVER['REQUEST_URI']);
            } catch ( Exception $ex ) {
                $this->data['error'] = $ex->getMessage();
            }
        }
    }
} // END class
abstract class Module
{
    protected $c;

    public function __construct($controller, $members=NULL)
    {
        $this->c = $controller;
        if ( $members !== NULL) {
            foreach ( $members as $key => $value) {
                $this->{$key} = $value;
            }
        }
    }
}
abstract class Model
{
    protected $_config = array(
        'primaryKey' => 'id',
        'table' => null
    );

    /*
    public function beforeVerify(&$fields, &$input=NULL, $args) {}
    public function beforeSave(&$fields, $args) {}
    public function afterSave(&$fields, &$input, $args) {}
    */

    public function verify(&$fields, &$input=NULL, $args=NULL)
    {
        if ( method_exists($this, 'beforeVerify')) {
            $this->beforeVerify($fields, $input, $args);
        }
        Validator::verify($fields, $this, $input);
    }
    public function save(&$fields, $verify=true, &$input=NULL, $args=NULL)
    {
        if ( NULL === $input) {
            $input = &$_POST;
        }

        if ( $verify ) {
            $this->verify($fields, $input, $args);
        }

        if ( method_exists($this, 'beforeSave')) {
            $this->beforeSave($fields, $args);
        }

        if ( ! $this->hasPrimaryKey()) {
            $this->insert(array_keys($fields));
        } else {
            $this->update(array_keys($fields));
        }

        if ( method_exists($this, 'afterSave')) {
            $this->afterSave($fields, $input, $args);
        }
    }
    public function insert($fields)
    {
        $db = App::db();

        if ( is_array($this->_config['primaryKey'])) {
            foreach ( $this->_config['primaryKey'] as $key) {
                if ( ! isset($fields[$key]) && $this->{$key} !== NULL) {
                    $fields[$key] = true;
                }
            }
        }

        $params = array();
        if ( isset($this->rawValueFields)) {
            $sql = DBHelper::toKeyInsertSql($this, $fields, $params, $this->rawValueFields);
        } else {
            $sql = DBHelper::toKeyInsertSql($this, $fields, $params);
        }
        $stmt = $db->prepare('INSERT INTO `' . $this->_config['table'] . '` ' . $sql);

        DBHelper::bindArrayValue($stmt, $params);
        $stmt->execute();

        if ( ! is_array($this->_config['primaryKey'])) {
            $this->{$this->_config['primaryKey']} = $db->lastInsertId();
            return;
        }
        if ( in_array('id', $this->_config['primaryKey']) && ! $this->id) {
            $this->id = $db->lastInsertId();
        }
    }
    public function update($fields)
    {
        $params = array();
        if ( isset($this->rawValueFields)) {
            $sql = DBHelper::toKeySetSql($this, $fields, $params, $this->rawValueFields);
        } else {
            $sql = DBHelper::toKeySetSql($this, $fields, $params);
        }
        if ( ! is_array($this->_config['primaryKey'])) {
            $where = array( $this->_config['primaryKey'] => $this->{$this->_config['primaryKey']} );
        } else {
            $where = array();
            foreach ( $this->_config['primaryKey'] as $key) {
                $where[$key] = $this->{$key};
            }
        }
        $stmt = App::db()->prepare('UPDATE `' . $this->_config['table'] . '` SET ' . $sql . ' WHERE ' . DBHelper::where($where));

        DBHelper::bindArrayValue($stmt, $params);
        $stmt->execute();
    }
    public function delete()
    {
        if ( ! is_array($this->_config['primaryKey'])) {
            $where = array( $this->_config['primaryKey'] => $this->{$this->_config['primaryKey']} );
        } else {
            $where = array();
            foreach ( $this->_config['primaryKey'] as $key) {
                $where[$key] = $this->{$key};
            }
        }

        App::db()->exec('DELETE FROM `' . $this->_config['table'] . '` WHERE ' . DBHelper::where($where) );
    }

    public function hasPrimaryKey()
    {
        if ( ! is_array($this->_config['primaryKey'])) {
            return !empty($this->{$this->_config['primaryKey']});
        }
        foreach ($this->_config['primaryKey'] as $key ) {
            if ( $this->{$key} === NULL ) {
                return false;
            }
        }
        return true;
    }



    // Model Config
    public function getConfig($key=null)
    {
        if ( NULL !== $key) {
            return isset($this->_config[$key]) ? $this->_config[$key] : null;
        }

        return $this->_config;
    }

    public function setConfig($props, $value=NULL)
    {
        if ( !is_array($props)) {
            $this->_config[$props] = $value;
            return;
        }
        foreach ($props as $key => $value) {
            $this->_config[$key] = $value;
        }
    }
}
abstract class ViewModel
{
    protected $m;
    protected $baseUrl;

    public function __construct($model, $baseUrl=NULL)
    {
        $this->m = $model;
        $this->baseUrl = $baseUrl;
    }
}
class View
{
    private $_appId;
    private $_currentAddon;
    private $_addons = array();

    public function __construct($appId)
    {
        $this->_appId = $appId;
    }
    private function getDir($appId=NULL)
    {
        return ROOT_PATH . ($appId ? $appId : $this->_appId) . '/views/';
    }
    public function content($viewFile, $appId=NULL)
    {
        return file_get_contents( $this->getDir($appId) . $viewFile);
    }
    public function load($viewFile, $appId=NULL)
    {
        extract($this->data);
        if ( false === strpos($viewFile, '.')) {
            include $this->getDir($appId) . $viewFile . '.html';
        } else {
            include $this->getDir($appId) . $viewFile;
        }
    }
    public function render($viewFile, $data=array(), $options=array())
    {
        $layout = 'layout';
        $appId = App::$id;
        $layoutAppId = App::$id;
        $return = false;
        extract($options, EXTR_IF_EXISTS);

        $this->data = $data;

        if ( file_exists($this->getDir($layoutAppId) . $layout . '_functions.php')) {
            include $this->getDir($layoutAppId) . $layout . '_functions.php';
        }

        ob_start();
        $this->load($viewFile, $appId);
        $content_html = ob_get_contents();
        ob_end_clean();

        if ( ! $layout ) {
            if ( $return ) {
                return $content_html;
            }
            echo $content_html;
            return null;
        }

        ob_start();
        $this->load($layout, $layoutAppId);
        $layout_html = explode('{{content_html}}', ob_get_contents());
        ob_end_clean();

        if ( ! $return ) {
            echo $layout_html[0];
            echo $content_html;
            if ( isset($layout_html[1]) ) {
                echo $layout_html[1];
            }
            return;
        }
        return $layout_html[0] . $content_html . $layout_html[1];
    }
    public function startAddon($hook)
    {
        $this->_currentAddon = $hook;
        if ( ! isset($this->_addons[$hook])) {
            $this->_addons[$hook] = array();
        }
        ob_start();
    }
    public function endAddon()
    {
        $this->_addons[$this->_currentAddon][] = ob_get_contents();
        ob_end_clean();
    }
    public function showAddon($hook)
    {
        if ( isset($this->_addons[$hook])) {
            echo implode("\n", $this->_addons[$hook]);
        }
    }
    public function hasAddon($hook)
    {
        return isset($this->_addons[$hook]);
    }
} // END class
class Search {

    public $where = array();
    public $params = array();

    public function sqlWhere()
    {
        return sizeof($this->where) ? ' WHERE ' . implode(' AND ', $this->where) : '';
    }
}
class DBHelper
{
    public static function isUniqIn($table, $field, $value, $id=NULL)
    {
        $sql = 'SELECT COUNT(*) = 0 FROM ' . $table . ' WHERE ';
        if ( $id ) {
            $sql .= ' `id` != ? AND ';
            $params[] = $id;
        }
        $sql .= ' `'.$field.'` = ?';
        $params[] = $value;

        $stmt = App::db()->prepare($sql);
        $stmt->execute($params);

        return (bool)$stmt->fetchColumn();
    }
    public static function clearNewFiles($data)
    {
        if ( empty($data->_new_files)) {
            return;
        }
        foreach ( $data->_new_files as $file) {
            if ( file_exists($file)) {
                unlink($file);
            }
        }
    }
    public static function in($array, $field='')
    {
        if ( sizeof($array) === 0) {
            return;
        }
        return $field . ' IN (' . implode(',', array_map(array(App::db(), 'quote'), $array)) . ')';
    }

    public static function orWhere($array)
    {
        $db = App::db();
        $sql = array();

        if ( empty($array)) {
            return '1=1';
        }

        foreach ( $array as $key => $value) {
            if ( is_array($value) ) {
                if ( count($value) > 1) {
                    $sql[] = '(' . self::where($value) . ')';
                } else {
                    foreach ( $value as $k => $v) {
                        $sql[] = '`' . $k . '` = ' . $db->quote($v);
                    }
                }
            } else {
                $sql[] = '`' . $key . '` = ' . $db->quote($value);
            }
        }
        return '(' . implode(' OR ', $sql) . ')';
    }
    public static function where($array)
    {
        $db = App::db();
        $sql = array();
        foreach ( $array as $key => $value) {
            $sql[] = '`' . $key . '` = ' . $db->quote($value);
        }
        return implode(' AND ', $sql);
    }
    public static function toSetSql($data, $fields, &$params, $rawValueFields=NULL)
    {
        $sql = array();
        foreach ( $fields as $field ) {
            $sql[] = '`'.$field.'` = ?';
            $params[] = $data->{$field};
        }
        if ( ! empty($rawValueFields)) {
            foreach ( $rawValueFields as $field => $value) {
                $sql[] = '`'.$field.'` = ' . $value;
            }
        }
        return implode(',', $sql);
    }
    public static function toKeySetSql($data, $fields, &$params, $rawValueFields=NULL)
    {
        $sql = array();
        foreach ( $fields as $field ) {
            $sql[] = '`'.$field.'` = :'.$field;
            $params[':'.$field] = $data->{$field};
        }
        if ( ! empty($rawValueFields)) {
            foreach ( $rawValueFields as $field => $value) {
                $sql[] = '`'.$field.'` = ' . $value;
            }
        }
        return implode(',', $sql);
    }
    public static function toInsertSql($data, $fields, &$params, $rawValueFields=NULL)
    {
        $columns = $values = array();
        foreach ( $fields as $field ) {
            $columns[] = $field;
            $values[] = '?';
            $params[] = $data->{$field};
        }
        if ( ! empty($rawValueFields)) {
            foreach ( $rawValueFields as $field => $value) {
                $columns[] = $field;
                $values[] = $value;
            }
        }
        return '(`'.implode('`,`', $columns).'`) VALUES ('.implode(',', $values).')';
    }
    public static function toKeyInsertSql($data, $fields, &$params, $rawValueFields=NULL)
    {
        $columns = $values = array();
        foreach ( $fields as $field ) {
            $columns[] = $field;
            $values[] = ':'.$field;
            $params[':'.$field] = $data->{$field};
        }
        if ( ! empty($rawValueFields)) {
            foreach ( $rawValueFields as $field => $value) {
                $columns[] = $field;
                $values[] = $value;
            }
        }
        return '(`'.implode('`,`', $columns).'`) VALUES ('.implode(',', $values).')';
    }
    /**
     * @param string $req : the query on which link the values
     * @param array $array : associative array containing the values to bind
     * @param array $typeArray : associative array with the desired value for its corresponding key in $array
     * */
    public static function bindArrayValue($req, $array, $typeArray = false)
    {
        if(is_object($req) && ($req instanceof PDOStatement))
        {
            foreach($array as $key => $value)
            {
                if($typeArray)
                    $req->bindValue("$key",$value,(isset($typeArray[$key])?$typeArray[$key]:PDO::PARAM_STR));
                else
                {
                    if(is_int($value))
                        $param = PDO::PARAM_INT;
                    elseif(is_bool($value))
                        $param = PDO::PARAM_BOOL;
                    elseif(is_null($value))
                        $param = PDO::PARAM_NULL;
                    elseif(is_string($value))
                        $param = PDO::PARAM_STR;
                    else
                        $param = FALSE;

                    $req->bindValue($key,$value,$param);
                }
            }
        }
    }
    public static function deleteColumnFile($options)
    {
        extract($options);


        $columns = $options['columns'];

        if ( empty($options['params'])) {
            $stmt = App::db()->query('SELECT `' . implode('`,`', $columns) . '` FROM `' . $options['table'] . '` a WHERE ' . $options['where']);
        } else {
            $stmt = App::db()->prepare('SELECT `' . implode('`,`', $columns) . '` FROM `' . $options['table'] . '` a WHERE ' . $options['where']);
            $stmt->execute($options['params']);
        }
        if ( ! $stmt->rowCount()) {
            return;
        }
        while ( $item = $stmt->fetch()) {
            foreach ( $columns as $column) {
                if ( ! $item->{$column}) {
                    continue;
                }
                if ( file_exists($dir . $item->{$column})) {
                    unlink($dir . $item->{$column});
                }
                if ( isset($options['suffixes'][$column])) {
                    $info = pathinfo($item->{$column});
                    $fdir = '';
                    if ( '.' !== $info['dirname']) {
                        $fdir = $info['dirname'] . DIRECTORY_SEPARATOR . $filename;
                    }
                    foreach ($options['suffixes'][$column] as $suffix) {
                        $filename = $fdir . $info['filename'] . $suffix . '.' . $info['extension'];
                        if ( file_exists($dir . $filename)) {
                            unlink($dir . $filename);
                        }
                    }
                }
            }
        }

    }
    public static function splitCommaList($str)
    {
        if ( is_array($str)) {
            return $str;
        }
        if ( empty($str)) {
            return array();
        }
        $array = explode(',', $str);
        return array_combine($array, $array);
    }

    // Table Operations

    public static function deleteAll($model, $ids)
    {
        $model = new $model;
        $mconf = $model->getConfig();

        if ( is_object($ids) && is_a($ids, 'Search')) {
            $where = $search->sqlWhere();
            $stmt = App::db()->prepare('DELETE FROM `' . $mconf['table'] . '` ' . $where);
            $stmt->execute($search->params);
        } else {
            $where = 'WHERE `' . $mconf['primaryKey'] . '` ' . DBHelper::in($ids);
            App::db()->exec('DELETE FROM `' . $mconf['table'] . '` ' . $where);
        }
    }
    public static function updateAll($model, $data, $ids, $rawValueFields=NULL)
    {
        $model = new $model;
        $mconf = $model->getConfig();

        $params = array();

        $fields = array_keys($data);
        $data = (object)$data;
        $sql = DBHelper::toKeySetSql($data, $fields, $params, $rawValueFields);
        $stmt = App::db()->prepare('UPDATE `' . $mconf['table'] . '` SET ' . $sql . ' WHERE `' . $mconf['primaryKey'] . '` ' . DBHelper::in($ids));
        $stmt->execute($params);
    }

    public static function count($model, Search $search)
    {
        $model = new $model;
        $mconf = $model->getConfig();

        $stmt = App::db()->prepare('SELECT COUNT(*) FROM `' . $mconf['table'] . '` a ' . $search->sqlWhere() . ' LIMIT 1');
        $stmt->execute($search->params);
        return $stmt->fetchColumn();
    }

    public static function getList($model, Search $search, Pagination $pager=null, $for='list')
    {
        $model = new $model;
        $mconf = $model->getConfig();

        $pagerInfo = null;
        if ( NULL !== $pager) {
            $pagerInfo = ' ORDER BY `' . $pager->sortField . '` ' . $pager->sortDir . $pager->getSqlLimit();
        }

        $stmt = App::db()->prepare('SELECT ' . $model->selectInfo($for) . ' FROM `' . $mconf['table'] . '` a ' . $search->sqlWhere() . $pagerInfo);
        $stmt->execute($search->params);
        return $stmt;
    }

    public static function getOne($model, $search, $for=NULL, $fetchIntoObject=NULL)
    {
        $model = new $model;
        $mconf = $model->getConfig();

        if ( is_object($search) && $search instanceof Search ) {
            $where = $search->sqlWhere();
            $params = $search->params;
        } elseif ( is_array($search)) {
            $where = ' WHERE ' . DBHelper::where($search);
            $params = array();
        } else {
            $where = ' WHERE `' . $mconf['primaryKey'] . '` = ?';
            $params = array($search);
        }
        $stmt = App::db()->prepare('SELECT ' . $model->selectInfo($for) . ' FROM `' . $mconf['table'] . '` a ' . $where . ' LIMIT 1');
        $stmt->execute($params);

        if ( NULL !== $fetchIntoObject ) {
            $stmt->setFetchMode(PDO::FETCH_INTO, $fetchIntoObject);
            $stmt->fetch();
            return $fetchIntoObject;
        }

        return $stmt->fetchObject(get_class($model));
    }

    public static function getFields($model, $for=null)
    {
        $model = new $model;
        return $model->fields($for);
    }
    public static function toKeyPair(PDOStatement $resource, $keyColumn='id', $valueColumn='name')
    {
        $result = array();
        while ( $item = $resource->fetch()) {
            $result[$item->{$keyColumn}] = $item->{$valueColumn};
        }
        return $result;
    }
} // END class
class Validator
{
    /**
     * 儲存圖片，若為圖片更新則會刪除舊圖片之後再存新圖
     *
     * @return void
     * @author Me
     */
    private static function __verifySaveImage($data, $key, $opt, &$input)
    {
        $label = $opt['label'];

        if ( ! isset($data->_new_files)) {
            $data->_new_files = array();
        }
        App::loadHelper('GdImage', false, 'common');

        $fileKey = ! empty($opt['fileKey']) ? $opt['fileKey'] : $key;
        if ( empty($_FILES[$fileKey]['name'])) {
            $input[$key] = $data->{$key};
            return;
        }
        // file upload
        $gd = new GdImage( $opt['dir'], $opt['dir'] );
        $gd->generatedType = strtolower(pathinfo($_FILES[$fileKey]['name'], PATHINFO_EXTENSION));

        // check upload error
        App::loadHelper('Upload', false, 'common');
        $uploader = new Upload(null, null);
        $uploader->checkError($_FILES[$fileKey]['error']);
        unset($uploader);

        if ( ! $gd->checkImageExtension($fileKey)) {
            throw new Exception(sprintf(
                    _e('%s: 圖片類型只能是 jpg, png, gif'),
                    '<strong>' . HtmlValueEncode($label) . '</strong>' ));
        }

        if ( ! $gd->checkImageType($fileKey)) {
            throw new Exception(sprintf(
                    _e('%s: 圖片類型只能是 jpg, png, gif'),
                    '<strong>' . HtmlValueEncode($label) . '</strong>' ));

        }
        if ( ! $gd->checkImageSize($fileKey, $opt['max_size'])) {
            throw new Exception(sprintf(
                    _e('%s: 圖片檔案大小不能超過 %sK'),
                    '<strong>' . HtmlValueEncode($label) . '</strong>',
                    $opt['max_size'] ));
        }
        if ( ! $source = $gd->uploadImage($fileKey)) {
            throw new Exception(sprintf(
                    _e('%s: 圖片上傳失敗，請稍後再上傳一次'),
                    '<strong>' . HtmlValueEncode($label) . '</strong>'));
        }
        if ( $data->{$key}) { // 移除舊檔案
            $file = $gd->processPath . $data->{$key};
            if (file_exists($file)) {
                unlink($file);
            }
            if ( isset($opt['thumbnails'])) {
                $oldinfo = pathinfo($data->{$key});
                foreach ($opt['thumbnails'] as $suffix => $sOpt)  {
                    $file = $gd->processPath . $oldinfo['dirname'] . DIRECTORY_SEPARATOR . $oldinfo['filename'] . $suffix . '.' . $oldinfo['extension'];
                    if ( file_exists($file)) {
                        unlink($file);
                    }
                }
            }
            $data->{$key} = $input[$key] = '';
        }
        $method = empty($opt['crop']) ? 'createThumb' : 'adaptiveResizeCropExcess';
        $info = pathinfo($source);
        $filename = $info['filename'];
        $ext = strtolower($info['extension']);
        if (
            $gd->{$method}(
                $source,
                $opt['resize'][0], $opt['resize'][1],
                $filename,
                (isset($opt['cropFrom']) ? $opt['cropFrom'] : null)
            )
        ) {
            $data->{$key} = $input[$key] = $source;
            $data->_new_files[] = $opt['dir'] . $source;
        }

        if ( isset($opt['thumbnails'])) {
            foreach ( $opt['thumbnails'] as $suffix => $sOpt) {
                $method = empty($sOpt['crop']) ? 'createThumb' : 'adaptiveResizeCropExcess';
                if (
                    $gd->{$method}(
                        $source,
                        $sOpt['size'][0], $sOpt['size'][1],
                        $filename . $suffix,
                        (isset($sOpt['cropFrom']) ? $sOpt['cropFrom'] : null)
                    )
                ) {
                    $data->_new_files[] = $opt['dir'] . $filename . $suffix . '.' . $ext;
                }
            }
        }
    }
    private static function __verifySaveFile($data, $key, $opt, &$input)
    {
        $label = $opt['label'];

        $fileKey = ! empty($opt['fileKey']) ? $opt['fileKey'] : $key;
        if ( ! isset($data->_new_files)) {
            $data->_new_files = array();
        }
        if ( empty($_FILES[$fileKey]['tmp_name'])) {
            $input[$key] = $data->{$key};
            return;
        }

        App::loadHelper('Upload', false, 'common');

        $uploader = new Upload($fileKey, $opt['dir']);

        if ( isset($opt['rename'])) {
            $uploader->rename = $opt['rename'];
        }
        if ( isset($opt['max_size'])) {
            $uploader->maxSize = $opt['max_size'];
        }
        if ( isset($opt['allow_types'])) {
            $uploader->allowTypes = $opt['allow_types'];
        }
        if ( isset($opt['deny_files'])) {
            $uploader->denyFiles = $opt['deny_files'];
        }

        try {
            if ( $data->{$key}) { // 移除舊檔案
                unlink($opt['dir'] . $data->{$key});
                $data->{$key} = $input[$key] = '';
            }

            $data->{$key} = $input[$key] = $uploader->save();
            $data->{$key.'_type'} = $input[$key.'_type'] = $uploader->getFileType();
            $data->{$key.'_orignal_name'} = $input[$key.'_orignal_name'] = $uploader->getOrignalName();

            if ( ! is_array($data->{$key})) {
                $data->_new_files[] = $opt['dir'] . DIRECTORY_SEPARATOR . $data->{$key};
            } else {
                foreach ( $data->{$key}['successed'] as $info) {
                    $data->_new_files[] = $opt['dir'] . DIRECTORY_SEPARATOR . $info['rename'];
                }
            }
        } catch ( Exception $ex ) {
            throw new Exception( '<strong>'.HtmlValueEncode($label).'</strong>: '. $ex->getMessage() );
        }
    }

    public static function verify($fields, $data, &$input=NULL)
    {
        $errors = array();

        if ( $input === NULL) {
            $input = &$_POST;
        }
        // verify data
        foreach ( $fields as $key  => $opt) {
            try {

                $label = $opt['label'];

                if ( ! isset($opt['type'])) {
                    $opt['type'] = null;
                }

                // upload files
                switch ( $opt['type']) {
                    case 'image':
                        self::__verifySaveImage($data, $key, $opt, $input);
                        break;
                    case 'file':
                        self::__verifySaveFile($data, $key, $opt, $input);
                        break;
                    default:
                        break;
                }

                // assign data
                if ( 'boolean' === $opt['type']) {
                    $data->{$key} = isset($input[$key]) ? (int)(boolean)$input[$key] : '0';
                    $is_empty = false;
                } elseif ( 'multiple' === $opt['type']) {
                    $data->{$key} = isset($input[$key]) && is_array($input[$key]) ? $input[$key] : array();
                    $is_empty = empty($data->{$key});
                } else {
                    $data->{$key} = isset($input[$key]) ? trim($input[$key]) : '';
                    $is_empty = $data->{$key} === '';
                }
                if ( ! $is_empty ) {
                    switch  ($opt['type']) {
                        case 'date':
                        case 'datetime':
                            if ( ! ( $timestamp = strtotime($data->{$key}))) {
                                throw new Exception(sprintf(
                                        _e('%s: 非正確的時間格式'),
                                        '<strong>' . HtmlValueEncode($label) . '</strong>' ));
                            }
                            if ( 'date' === $opt['type']) {
                                $format = isset($opt['format']) ? $opt['format'] : 'Y-m-d';
                                $data->{$key} = date($format, $timestamp);
                            } else {
                                $format = isset($opt['format']) ? $opt['format'] : 'Y-m-d H:i:s';
                                $data->{$key} = date($format, $timestamp);
                            }
                            break;
                        default:
                            break;
                    }
                    if ( isset($opt['pattern']) && ! preg_match($opt['pattern'], $data->{$key})) {
                        throw new Exception( '<strong>'.HtmlValueEncode($label).'</strong>: '. $opt['title'] );
                    }

                    if ( isset($opt['list'])) {

                        if ( is_array($data->{$key})) {
                            foreach ( $data->{$key} as $k => $v) {
                                if ( ! isset($opt['list'][$v])) {
                                    unset($data->{$key}[$k]);
                                    unset($input[$key][$k]);
                                }
                            }
                            if ( empty($data->{$key})) {
                                if ( array_key_exists('default', $opt) ) {
                                    $data->{$key} = $opt['default'];
                                }
                                if ( ! empty($opt['required'])) {
                                    throw new Exception(sprintf(
                                            _e('%s: 必須選取'),
                                            '<strong>' . HtmlValueEncode($label) . '</strong>' ));
                                }
                            }
                        } else {
                            if ( ! isset($opt['list'][$data->{$key}])) {
                                throw new Exception(sprintf(
                                        _e('%s: 不在允許的清單中'),
                                        '<strong>' . HtmlValueEncode($label) . '</strong>' ));
                            }
                        }
                    }
                } else {
                    if ( array_key_exists('default', $opt) ) {
                        $data->{$key} = $opt['default'];
                    }
                    if ( ! empty($opt['required']) ) {
                        throw new Exception(sprintf(
                                _e('%s: 不能為空'),
                                '<strong>' . HtmlValueEncode($label) . '</strong>'));
                    }

                }
                self::_doCallbacks($opt, $data, $key);

            } catch ( Exception $ex ) {
                $errors[] = '<li>' . $ex->getMessage() . '</li>';
                continue;
            }
        }

        if ( isset($errors[0])) {
            throw new Exception('<ul>'.implode("\n", $errors).'</ul>');
        }
    }
    private static function _doCallbacks(&$opt, $data, $key)
    {
        if ( !isset($opt['callbacks'])) {
            return;
        }
        foreach ( $opt['callbacks'] as $ck => $func ) {
            if ( is_array($func)) {
                $params = $func;
                $func = $ck;
                array_unshift($params, $data->{$key});
            } else {
                $params = array($data->{$key});
            }
            try {
                if ( method_exists($data, $func)) { // $data->$func()
                    $data->{$key} = call_user_func_array(array($data, $func), $params);
                } else if ( method_exists(__CLASS__, $func)) { // Validator::$func()
                    $data->{$key} = call_user_func_array(array(__CLASS__, $func), $params);
                } else {
                    $data->{$key} = call_user_func_array($func, $params);
                }
            } catch ( Exception $ex ) {
                throw new Exception( '<strong>'.HtmlValueEncode($opt['label']).'</strong>: '. $ex->getMessage() );
            }
        }
    }
    public static function requiredText($value)
    {
        if ( '' === trim(strip_tags($value))) {
            throw new Exception(_e('文字必須填寫'));
        }
        return $value;
    }
    public function numeric($value)
    {
        if ( ! is_numeric($value)) {
            throw new Exception(_e('必須是數字'));
        }
        return $value;
    }
    public static function between($value, $min, $max)
    {
        if ( ($value < $min) || ($value > $max)) {
            throw new Exception(sprintf(_e('數字範圍為%s~%s'), $min, $max));
        }
        return $value;
    }
    public static function date($value, $format='yyyy/mm/dd', $forceYearLength=false)
    {
        //Date yyyy-mm-dd, yyyy/mm/dd, yyyy.mm.dd
        //1900-01-01 through 2099-12-31

        $yearFormat = "(19|20)?[0-9]{2}";
        if ($forceYearLength == true) {
            if (strpos($format, 'yyyy') !== false) {
                $yearFormat = "(19|20)[0-9]{2}";
            } else {
                $yearFormat = "[0-9]{2}";
            }
        }

        switch($format){
            case 'dd/mm/yy':
                $pattern = "/^\b(0?[1-9]|[12][0-9]|3[01])[- \/.](0?[1-9]|1[012])[- \/.]{$yearFormat}\b$/";
                break;
            case 'mm/dd/yy':
                $pattern = "/^\b(0?[1-9]|1[012])[- \/.](0?[1-9]|[12][0-9]|3[01])[- \/.]{$yearFormat}\b$/";
                break;
            case 'mm/dd/yyyy':
                $pattern = "/^(0[1-9]|1[012])[- \/.](0[1-9]|[12][0-9]|3[01])[- \/.]{$yearFormat}$/";
                break;
            case 'dd/mm/yyyy':
                $pattern = "/^(0[1-9]|[12][0-9]|3[01])[- \/.](0[1-9]|1[012])[- \/.]{$yearFormat}$/";
                break;
            case 'yy/mm/dd':
                $pattern = "/^\b{$yearFormat}[- \/.](0?[1-9]|1[012])[- \/.](0?[1-9]|[12][0-9]|3[01])\b$/";
                break;
            case 'yyyy/mm/dd':
            default:
                $pattern = "/^\b{$yearFormat}[- \/.](0?[1-9]|1[012])[- \/.](0?[1-9]|[12][0-9]|3[01])\b$/";
        }

        if (!preg_match($pattern, $value)) {
            throw new Exception( sprintf('日期格式不正確!請使用%s格式', $format));
        }
        return $value;
    }
    public static function datetime($value)
    {
        $rs = strtotime($value);

        if ($rs===false || $rs===-1){
            throw new Exception('時間格式不正確');
        }
        return $value;
    }

    public static function idNumber($value, $identityType='id')
    {
        if ( ! self::_idNumber($value, $identityType)) {
            throw new Exception('請輸入有效的證件編號');
        }
        return $value;
    }

    private static function _idNumber($value, $identityType='id')
    {
        if ( 'passport' === $identityType ) {
            return strlen($value) <= 20;
        }
        $number = strtoupper($value);
        $isArcNumber = 'arc' === $identityType;

        $pattern = !$isArcNumber ? "/^[A-Z][12][0-9]{8}$/" : "/^[A-Z][A-D][0-9]{8}$/";
        if ( ! preg_match($pattern, $number)) {
            return false;
        }
        unset($pattern);

        $cities = array(
            'A'=>10, 'B'=>11, 'C'=>12, 'D'=>13, 'E'=>14, 'F'=>15, 'G'=>16, 'H'=>17, 'I'=>34, 'J'=>18,
            'K'=>19, 'L'=>20, 'M'=>21, 'N'=>22, 'O'=>35, 'P'=>23, 'Q'=>24, 'R'=>25, 'S'=>26, 'T'=>27,
            'U'=>28, 'V'=>29, 'W'=>32, 'X'=>30, 'Y'=>31, 'Z'=>33
        );
        $sum = 0;

        // 計算縣市加權
        $city = $cities[$number{0}];
        $sum += floor($city / 10) + ($city % 10 * 9);

        // 計算性別加權
        if ( !$isArcNumber ) {
            $sum +=  (int)$number{1} * 8;
        } else {
            $gender = $cities[$number{1}];
            $sum += ($gender % 10 * 8);
            unset($gender);
        }

        // 計算中間值的加權
        for ( $i=2; $i<=8; $i++) {
            $sum +=  (int)$number{$i} * (9-$i);
        }

        // 加上檢查碼
        $sum += (int)$number{9};

        return ($sum % 10 === 0);
    }
}
class Urls
{
    /**
     * 網址計算的基本位置 eg: /subfolder/
     *
     * @var string
     **/
    private $_urlBase;
    /**
     * mod rewrite 是否已啟用
     *
     * @var string
     **/
    private $_modRewriteEnabled = null;
    /**
     * 路徑的網址參數名稱
     *
     * @var string
     **/
    public $_paramName = 'q';
    /**
     * 路徑陣列
     *
     * @var array
     **/
    private $_segments;
    private $_queryString;
    /**
     * route 的基本路徑(如用於語系切換)
     *
     * @var string
     **/
    private $_queryStringPrefix;


    /**
     * 建構式
     *
     * @param string $urlBase 簡潔網址的起始位置
     * @param string $paramName 網址路徑的 GET 參數名稱
     * @return void
     */
    function __construct($urlBase, $paramName='q')
    {
        $this->_urlBase = rtrim($urlBase, '/') . '/';
        $this->_modRewriteEnabled = App::conf()->enable_rewrite && self::_isModRewriteEnabled();
        $this->_paramName = $paramName;

        $this->_queryString = isset($_GET[$this->_paramName]) ? trim($_GET[$this->_paramName], '/') : '';
        $this->_segments = explode('/', $this->_queryString);
    }
    public function setQueryStringPrefix($prefix)
    {
        $this->_queryStringPrefix = $prefix;
    }
    /**
     * 偵測伺服器是否有啟用 mod rewrite
     *
     * @return boolean
     */
    private static function _isModRewriteEnabled()
    {
        if (function_exists('apache_get_modules')) {
            return in_array('mod_rewrite', apache_get_modules());
        }
        return (getenv('HTTP_MOD_REWRITE') === 'On');
    }
    /**
     * 傳回網址的路由參數值
     *
     * @return string
     */
    public function getQueryString()
    {
        return $this->_queryString;
    }
    /**
     * 傳回指定部位的網址路徑
     *
     * @param integer $index 網址路徑的部位，從0開始
     * @param string $default 若指定的部分無值時，傳回此值
     * @return string
     **/
    public function segment($index, $default='')
    {
        if ( isset($this->_segments[$index]) && $this->_segments[$index] !== '') {
            return $this->_segments[$index];
        }
        return $default;
    }
    private function ___urltoIdReplacer($matches)
    {
        $paramName = $matches['name'];
        if (isset($this->tmpRouteParams[$paramName])) {
            return $this->tmpRouteParams[$paramName];
        }
        return App::route()->getDefault($paramName);
    }
    public function urltoId($routeId, $routeParams=null, $urlParams=null, $options=array( 'fullurl' => false, 'argSeparator' => '&amp;'))
    {
        $route = App::route();

        if ( !$rule = $route->getNamedRoutes($routeId)) {
            return null;
        }

        $this->tmpRouteParams = $routeParams;

        $parsePattern = '#(\(\?P<(?P<name>[^>]+)>(?P<pattern>[^)]+)\)(?P<optional>[?])?)#';
        $url = preg_replace_callback($parsePattern, array($this, '___urltoIdReplacer'), $rule['pattern']);

        unset($this->tmpRouteParams);

        if ('default' !== $routeId) {
            $url = str_replace(array('(/)?', '(', ')?', '\\'), '', $url);
            return $this->urlto($url, $urlParams, $options);
        }

        $strips = array('(/)?');
        if ( !isset($routeParams['action'])) {
            $strips[] = '(/' . $route->getDefault('action') . ')?';
        }
        if ( !isset($routeParams['format'])) {
            $strips[] = '(\.' . $route->getDefault('format') . ')?';
        }
        array_push($strips, '(', ')?', '\\');
        $url = str_replace($strips, '', $url);
        if ( !isset($routeParams['controller']) && $url === $route->getDefault('controller')) {
            $url = '';
        }
        return $this->urlto($url, $urlParams, $options);
    }
    /**
     * 傳回網址
     *
     * @return string
     **/
    public function urlto( $url, $urlParams = null, $options=array( 'fullurl' => false, 'argSeparator' => '&amp;') )
    {
        $fullurl = false;
        $argSeparator = '&amp;';
        extract($options, EXTR_IF_EXISTS);

        if ( !is_array($urlParams) || empty($urlParams)) {
            $urlParams = null;
        }

        if ( ! $url = trim($url, '/')) {
            $url = $this->_queryStringPrefix;
        } else {
            $url = ($this->_queryStringPrefix ? $this->_queryStringPrefix . '/' : '') . $url;
        }

        if ( $this->_modRewriteEnabled ) {
            $url = $this->_urlBase . $url;
            if ( !empty($urlParams)) {
                $url .= '?' . http_build_query($urlParams, '', $argSeparator);
            }

        } else {
            $params = array( $this->_paramName => $url );
            if ( !empty($urlParams)) {
                $params = array_merge($urlParams, $params);
            }
            $url = http_build_query($params, '', $argSeparator);
            if ( $url ) {
                $url = str_replace('%2F', '/', $url);
                $url = $this->_urlBase . '?' . $url;
            } else {
                $url = $this->_urlBase;
            }
        }
        if ( $fullurl ) {
            return get_domain_url() . $url;
        }
        return  $url;
    }
    /**
     * Redirect to an external URL with HTTP 302 header sent by default
     *
     * @param string $routeuri URL of the redirect location
     * @param bool $exit to end the application
     * @param code $code HTTP status code to be sent with the header
     * @param array $headerBefore Headers to be sent before header("Location: some_url_address");
     * @param array $headerAfter Headers to be sent after header("Location: some_url_address");
     */
    public function redirect($routeuri, $exit=true, $code=303, $headerBefore=NULL, $headerAfter=NULL)
    {
        redirect($this->urlto($routeuri), $exit, $code, $headerBefore, $headerAfter);
    }
    public function exceptionResponse($statusCode, $message)
    {
        header('HTTP/1.0 ' . $statusCode . ' ' . $message);
        echo $statusCode, ' ', $message;
        exit;
    }

} // END class
class Route
{
    private $_routes = array();
    private $_namedRoutes = array();
    private $_defaultParams = array();
    private $_history = array();

    public function __construct($routeConfig)
    {
        if ( NULL === $routeConfig) {
            $file = ROOT_PATH . 'config' . DIRECTORY_SEPARATOR . 'route.' . App::$id . '.php';
        } elseif (is_string($routeConfig)) {
            $file = ROOT_PATH . 'config' . DIRECTORY_SEPARATOR . 'route.' . $routeConfig . '.php';
        }

        if ( isset($file)) {
            if ( ! file_exists($file)) {
                throw new Exception('$routeConfig is required');
            }
            $routeConfig = require_once($file);
        }

        $this->setRoutes($routeConfig);
    }
    public function setRoutes($routeConfig)
    {
        if ( isset($routeConfig['__defaultParams'])) {
            $this->_defaultParams = $routeConfig['__defaultParams'];
            $_REQUEST = array_merge($_REQUEST, $routeConfig['__defaultParams']);
            unset($routeConfig['__defaultParams']);
        }

        $this->_routes = $routeConfig;
        $this->_namedRoutes = self::_filterNamedRoutes($this->_routes);
    }
    public function appendDefaultRoute()
    {
        // remember that if you changed default route pattern, you have to modify the Route::urltoId method
        // default route: {{controller}}/{{action}}/{{id}}.{{format}}

        $pattern = '(?P<controller>[^./]+)?(/(?P<action>[^./]+)(/(?P<id>[^./]+))?)?(\.(?P<format>[^/]+))?';
        $config = array('__id' => 'default');

        $this->_routes[$pattern] = $config;

        // update named routes
        $named = self::_filterNamedRoutes(array( $pattern => $config ));
        $this->_namedRoutes['default'] = $named['default'];
    }
    private static function _filterNamedRoutes($routes)
    {
        $namedRoutes = array();
        foreach ($routes as $pattern => $config) {
            if ( isset($config['__id']) && ($id = $config['__id']) && !isset($namedRoutes[$id])) {
                unset($config['__id']);
                $config['pattern'] = $pattern;
                $namedRoutes[$id] = $config;
            }
        }
        return $namedRoutes;
    }
    public function getNamedRoutes($key=NULL)
    {
        if ( NULL === $key) {
            return $this->_namedRoutes;
        }
        return !isset($this->_namedRoutes[$key]) ? NULL : $this->_namedRoutes[$key];
    }
    public function getDefault($key)
    {
        return isset($this->_defaultParams[$key]) ? $this->_defaultParams[$key] : null;
    }
    public function parse()
    {
        $queryString = App::urls()->getQueryString();
        foreach ($this->_routes as $pattern => $config) {
            if ( !preg_match('#^' . $pattern . '#', $queryString, $matches)) {
                continue;
            }

            // pass route parameters to $_REQUEST array
            unset($config['__id']);
            $_REQUEST = array_merge($_REQUEST, $config);
            foreach ( $matches as $name => $value) {
                if ( is_string($name)) {
                    if ('' === $value && isset($this->_defaultParams[$name])) {
                        $_REQUEST[$name] = $this->_defaultParams[$name];
                    } elseif (isset($config[$name])) {
                        $_REQUEST[$name] = $config[$name];
                    } else {
                        $_REQUEST[$name] = $value;
                    }
                }
            }
            break;
        }
    }

    public function forwardTo($controller, $action)
    {
        $this->addHistory($controller, $action);
        self::acl()->check();
        self::doAction($_REQUEST['controller'], $_REQUEST['action']);
    }
    public function addHistory($controller, $action)
    {
        $this->_history[] = array(
            'controller' => $_REQUEST['controller'],
            'action' => $_REQUEST['action']
        );
        $_REQUEST['controller'] = $controller;
        $_REQUEST['action'] = $action;
    }
    public function getHistory($offset=null)
    {
        if ('last' === $offset) {
            $length = sizeof($this->_history);
            return !$length ? array() : $this->_history[$length-1];
        }
        if ('first' === $offset) {
            return !isset($this->_history[0]) ? array() : $this->_history[0];
        }
        return $this->_history;
    }
} // END class

class Acl
{
    private $_rules = array();
    private $_role = 'anonymous';

    public function __construct($aclConfig, $aclRole=NULL)
    {
        if ( NULL === $aclConfig) {
            $file = ROOT_PATH . 'config' . DIRECTORY_SEPARATOR . 'acl.' . App::$id . '.php';
        } elseif ( is_string($aclConfig)) {
            $file = ROOT_PATH . 'config' . DIRECTORY_SEPARATOR . 'acl.' . $aclConfig . '.php';
        }

        if ( isset($file)) {
            if ( ! file_exists($file)) {
                throw new Exception('$aclConfig is required');
            }
            $aclConfig = require_once($file);
        }

        $this->_rules = $aclConfig;
        $this->_role = $aclRole;
    }

    private function _getRoleRules($role)
    {
        $defaultRule = !isset($this->_rules['__default']) ? array() : $this->_rules['__default'];
        $rule = $this->_rules[$role];

        foreach ($rule as $key => $value) {
            if ( isset($defaultRule[$key])) {
                unset($defaultRule[$key]);
            }
        }
        return array_merge($defaultRule, $rule);
    }

    public function check()
    {
        $rule = $this->_getRoleRules($this->_role);

        if ( ! $this->_isAccessible($rule)) {
            if ( '404' === $rule['__failRoute']) {
                throw new NotFoundException(_e('頁面不存在'));
            }

            if ( false === strpos($rule['__failRoute'], '/')) {
                $controller = $rule['__failRoute'];
                $action = App::route()->getDefault('action');
            } else {
                $route = explode('/', $rule['__failRoute']);
                $controller = $route[0];
                $action = $route[1];
            }
            App::route()->addHistory($controller, $action);
        }
    }
    private function _isAccessible($rule)
    {
        $keys = array_keys($rule);
        $allowPrior = array_search('allow', $keys);
        $denyPrior = array_search('deny', $keys);
        unset($keys);

        $path = $_REQUEST['controller'] . '/' . $_REQUEST['action'];
        if ( $denyPrior > $allowPrior ) {
            return ( !$this->_inList($path, $rule['deny']) && $this->_inList($path, $rule['allow']) );
        }
        return ( $this->_inList($path, $rule['allow']) || !$this->_inList($path, $rule['deny']));
    }
    private function _inList($path, $rule)
    {
        if ( is_string($rule)) {
            return $this->_matchRule($path, $rule);
        }

        if ( is_array($rule)) {
            foreach ($rule as $r) {
                if ( $this->_matchRule($path, $r)) {
                    return true;
                }
            }
        }
        return false;
    }
    private function _matchRule($path, $rule)
    {
        if ('*' === $rule) {
            return true;
        }
        if ( false === strpos($rule, '/')) {
            return ($path === $rule);
        }

        list($rController, $rAction) = explode('/', $rule);
        if ( '*' !== $rAction) {
            return ($path === $rule);
        }

        list($controller) = explode('/', $path);
        return ($controller === $rController);
    }

    public function getRole()
    {
        return $this->_role;
    }
    public function setRole($role)
    {
        $this->_role = $role;
    }

} // END class

function __($message) {
    return $message;
}
/**
 * 單數、複數訊息
 *
 * @param string $msgid1 單數訊息
 * @param string $msgid2 複數訊息
 * @return string
 */
function _n($msgid1, $msgid2, $n)
{
    if ( $n == 1) {
        return __($msgid1);
    }
    return __($msgid2);
}
function _e($message)
{
    return HtmlValueEncode(__($message));
}
