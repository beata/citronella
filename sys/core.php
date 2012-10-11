<?php
class NotFoundException extends Exception {}

class App
{
    public static $id = 'frontend';
    public static $defaultController = 'home';
    public static $defaultAction = 'index';
    public static $userRole = 'anonymous';

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
    public static function view($appId=NULL)
    {
        return new View($appId ? $appId : self::$id);
    }
    public static function urls($id='_primary', $urlBase=NULL, $paramName='q')
    {
        if ( isset(self::$_urlsList[$id])) {
            return self::$_urlsList[$id];
        }
        return (self::$_urlsList[$id] = new Urls( $urlBase, $paramName ));
    }
    public static function loadClass($class, $createObj=false, $appId=NULL)
    {
        require_once ROOT_PATH . ($appId ? $appId : self::$id) . '/classes/' . $class . '.php';
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
    /**
     * 路由轉換
     *
     * @param mixed 可以是 $acl 陣列檔案路徑，或陣列，如: array( 'anonymous' => '*')
     * @return void
     */
    public static function route($aclConfigFile)
    {
        $urls = App::urls();

        $_REQUEST['is_ajax'] = (isset($_SERVER['HTTP_X_REQUESTED_WITH']) && $_SERVER['HTTP_X_REQUESTED_WITH'] == "XMLHttpRequest");

        if ( ! $page = $urls->segment(0)) {
            $page = $urls->segments[0] = self::$defaultController;
        }
        if ( ! $action = $urls->segment(1)) {
            $action = $urls->segments[1] = self::$defaultAction;
        }
        if ( is_array($aclConfigFile)) {
            $acl = $aclConfigFile;
            unset($aclConfigFile);
        } else {
            require $aclConfigFile;
        }
        if (
            '*' !== $acl[App::$userRole] &&
            ! isset($acl[App::$userRole][$page]) &&
            ! isset($acl[App::$userRole][$page.'/'.$action])
        ) {
            $defaultRoute = $acl[App::$userRole]['__defaultRoute'];
            if ( $defaultRoute === '404') {
                $_REQUEST['controller'] = null;
                throw new NotFoundException(_e('頁面不存在'));
            }
            if ( false === strpos($defaultRoute, '/')) {
                $page = $urls->segments[0] = $defaultRoute;
            } else {
                $defaultRoute = explode('/', $defaultRoute);
                $page = $urls->segments[0] = $defaultRoute[0];
                $action = $urls->segments[1] = $defaultRoute[1];
            }
        }

        $_REQUEST['controller'] = $page;
        $_REQUEST['action'] = $action;
        if (  '*' !== $acl[App::$userRole] ) {
            if ( isset($acl[App::$userRole][$page.'/'.$action])) {
                $page_name = $acl[App::$userRole][$page.'/'.$action];
            } else {
                $page_name = $acl[App::$userRole][$page];
            }
        } else {
            $page_name = null;
        }

        if ( '_' === $action{0} ) {
            throw new NotFoundException(_e('頁面不存在'));
        }
        $class = camelize($page);
        $action = camelize($action, false);

        $controller_path = ROOT_PATH . ($appId ? $appId : self::$id) . '/controllers/' . $class . '.php';

        if ( ! file_exists($controller_path)) {
            throw new NotFoundException(_e('頁面不存在'));
        }
        require $controller_path;

        $class .= 'Controller';
        $controller = new $class();
        $controller->setTitle($page_name);

        if ( ! is_callable(array($controller, $action))) {
            if ( ! $controller->defaultAction ) {
                throw new NotFoundException(_e('頁面不存在'));
            }
            $_REQUEST['action'] = $action = $controller->defaultAction;
        }

        if ( method_exists($controller, 'preAction')) {
            $controller->preAction();
        }

        $controller->{$action}();

        if ( method_exists($controller, 'postAction')) {
            $controller->postAction();
        }
    }
} // END class
abstract class Controller
{
    public $defaultAction;
    public $data = array();

    public function _prepareLayout($type=NULL)
    {
        $this->data = array_merge($this->data, array(
            'conf' => App::conf(),
            'urls' => App::urls()
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
        App::view()->render('error.html', $this->data, compact('layout'));
    }
    public function _loadModule($module, $createObj=true, $appId=NULL)
    {
        require_once ROOT_PATH . ($appId ? $appId : self::$id) . '/modules/' . $module . '.php';
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
        $sep = array_merge(array( 'pageTitleSep' => '-', 'windowTitleSep' => ' / '), $sep);
        $this->data['page_title'] .= ' ' . $sep['pageTitleSep'] . ' ' . $title;
        $this->data['window_title'] = $title . $sep['windowTitleSep'] . $this->data['window_title'];
    }

} // END class
class BaseController extends Controller
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
                throw new Exception(_e('未定義的呼叫接口'));
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
    protected static $_primaryKey;
    protected static $_table;

    /*
    public function beforeVerify(&$field, &$input=NULL, $args) {}
    public function beforeSave(&$fields, $args) {}
    public function afterSave(&$fields, $args) {}
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
            $this->afterSave($fields, $args);
        }
    }
    public function insert($fields)
    {
        $db = App::db();
        $model = get_class($this);

        if ( is_array($model::$_primaryKey)) {
            foreach ( $model::$_primaryKey as $key) {
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
        $stmt = $db->prepare('INSERT INTO `' . $model::_table . '` ' . $sql);

        DBHelper::bindArrayValue($stmt, $params);
        $stmt->execute();

        if ( ! is_array($model::$_primaryKey)) {
            $this->{$model::$_primaryKey} = $db->lastInsertId();
            return;
        }
        if ( in_array('id', $model::$_primaryKey) && ! $this->id) {
            $this->id = $db->lastInsertId();
        }
    }
    public function update($fields)
    {
        $model = get_class($this);

        $params = array();
        if ( isset($this->rawValueFields)) {
            $sql = DBHelper::toKeySetSql($this, $fields, $params, $this->rawValueFields);
        } else {
            $sql = DBHelper::toKeySetSql($this, $fields, $params);
        }
        if ( ! is_array($model::$_primaryKey)) {
            $where = array( $model::$_primaryKey => $this->{$model::$_primaryKey} );
        } else {
            $where = array();
            foreach ( $model::$_primaryKey as $key) {
                $where[$key] = $this->{$key};
            }
        }
        $stmt = App::db()->prepare('UPDATE `' . $model::$_table . '` SET ' . $sql . ' WHERE ' . DBHelper::where($where));

        DBHelper::bindArrayValue($stmt, $params);
        $stmt->execute();
    }
    public function delete()
    {
        $model = get_class($this);
        $db = App::db();

        if ( ! is_array($model::$_primaryKey)) {
            $where = array( $model::$_primaryKey => $this->{$model::$_primaryKey} );
        } else {
            $where = array();
            foreach ( $model::$_primaryKey as $key) {
                $where[$key] = $this->{$key};
            }
        }

        App::db()->exec('DELETE FROM `' . $model::$_table . '` WHERE ' . DBHelper::where($where) );
    }
    public function hasPrimaryKey()
    {
        $model = get_class($this);
        if ( ! is_array($model::$_primaryKey)) {
            return !empty($this->{$model::$_primaryKey});
        }
        foreach ($model::$_primaryKey as $key ) {
            if ( $this->{$key} === NULL ) {
                return false;
            }
        }
        return true;
    }

    public static function getTable()
    {
        $model = get_called_class();
        return $model::$_table;
    }
    public static function count(Search $search)
    {
        $model = get_called_class();

        $stmt = App::db()->prepare('SELECT COUNT(*) FROM `' . $model::$_table . '` a ' . $search->sqlWhere() . ' LIMIT 1');
        $stmt->execute($search->params);
        return $stmt->fetchColumn();
    }

    public static function getList(Search $search, Pagination $pager)
    {
        $model = get_called_class();

        $stmt = App::db()->prepare('SELECT ' . $model::selectInfo('list') . ' FROM `' . $model::$_table . '` a ' . $search->sqlWhere() . ' ORDER BY `id` DESC LIMIT ' . $pager->rowStart . ',' . $pager->rowsPerPage);
        $stmt->execute($search->params);
        return $stmt;
    }

    public static function getOne($search, $for=NULL)
    {
        $model = get_called_class();

        if ( is_subclass_of($searchOrId, 'Search')) {
            $where = $search->sqlWhere();
            $params = $search->params();
        } elseif ( is_array($search)) {
            $where = ' WHERE ' . DBHelper::where($where);
            $params = array();
        } else {
            $where = ' WHERE `' . $model::$_primaryKey . '` = ?';
            $params = array($id);
        }
        $stmt = App::db()->prepare('SELECT ' . $model::selectInfo($for) . ' FROM `' . $model::$_table . '` a ' . $where . ' LIMIT 1');
        $stmt->execute($params);
        return $stmt->fetchObject($model);
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
        $appId = NULL;
        $return = false;
        extract($options, EXTR_IF_EXISTS);

        $this->data = $data;

        if ( file_exists($this->getDir($appId) . $layout . '_functions.php')) {
            include $this->getDir($appId) . $layout . '_functions.php';
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
        $this->load($layout, $appId);
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
        return $field . " IN ('".implode("', '", $array)."')";
    }
    public static function orWhere($array)
    {
        $db = App::db();
        $sql = array();
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
} // END class
class Validator
{
    private static function __verifySaveImage($data, $key, $opt, &$input)
    {
        $label = $opt['label'];

        if ( ! isset($data->_new_files)) {
            $data->_new_files = array();
        }
        App::loadClass('GdImage', false, 'common');

        $fileKey = ! empty($opt['fileKey']) ? $opt['fileKey'] : $key;
        if ( empty($_FILES[$fileKey]['tmp_name'])) {
            $input[$key] = $data->{$key};
            return;
        }
        // file upload
        $gd = new GdImage( $opt['dir'], $opt['dir'] );
        $gd->generatedType = pathinfo($_FILES[$fileKey]['name'], PATHINFO_EXTENSION);
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
            $gd->removeImage($data->{$key});
            $data->{$key} = $input[$key] = '';
        }
        $method = empty($opt['crop']) ? 'createThumb' : 'adaptiveResizeCropExcess';
        $filename = pathinfo($source, PATHINFO_FILENAME );
        if (
            $gd->{$method}(
                $source,
                $opt['resize'][0], $opt['resize'][1],
                $filename
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
                        $filename . $suffix
                    )
                ) {
                    $data->_new_files[] = $opt['dir'] . $filename . $suffix;
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

        App::loadClass('Upload', false, 'common');

        $uploader = new Upload($fileKey, $opt['dir']);

        if ( isset($opt['rename'])) {
            $uploader->rename = $opt['rename'];
        }
        if ( isset($opt['max_size'])) {
            $uploader->maxSize = $opt['max_size'];
        }
        if ( isset($opt['allow_types'])) {
            $uploader->allowedTypes = $opt['allow_types'];
        }

        try {
            if ( $data->{$key}) { // 移除舊檔案
                unlink($opt['dir'] . $data->{$key});
                $data->{$key} = $input[$key] = '';
            }

            $data->{$key} = $input[$key] = $uploader->save();

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
                } elseif ( 'multiple' !== $opt['type']) {
                    $data->{$key} = isset($input[$key]) ? trim($input[$key]) : '';
                    $is_empty = $data->{$key} === '';
                } else {
                    $data->{$key} = isset($input[$key]) && is_array($input[$key]) ? $input[$key] : array();
                    $is_empty = empty($data->{$key});
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
                    if ( isset($opt['callbacks'])) {
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
                                throw new Exception( '<strong>'.HtmlValueEncode($label).'</strong>: '. $ex->getMessage() );
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

            } catch ( Exception $ex ) {
                $errors[] = '<li>' . $ex->getMessage() . '</li>';
                continue;
            }
        }

        if ( isset($errors[0])) {
            throw new Exception('<ul>'.implode("\n", $errors).'</ul>');
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
    public static function date($value, $foramt='yyyy/mm/dd', $forceYearLength=false)
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
     * route 的基本路徑(如用於語系切換)
     *
     * @var string
     **/
    public $routeBase = null;
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
    public $segments;
    public $queryString;

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

        $this->queryString = isset($_GET[$this->_paramName]) ? trim($_GET[$this->_paramName], '/') : '';
        $this->segments = explode('/', $this->queryString);
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
     * 傳回指定部位的網址路徑
     *
     * @param integer $index 網址路徑的部位，從0開始
     * @param string $default 若指定的部分無值時，傳回此值
     * @return string
     **/
    public function segment($index, $default='')
    {
        if ( isset($this->segments[$index]) && $this->segments[$index] !== '') {
            return $this->segments[$index];
        }
        return $default;
    }
    /**
     * 傳回網址
     *
     * @param string $routeuri ex. /blog/edit
     * @param boolean $fullurl
     * @param string $argSeparator 當 mod rewrite 未啟用時，連接 url 參數的字串(& or &amp;)
     * @return string
     **/
    public function urlto( $routeuri, $fullurl = false, $argSeparator='&amp;', $addonParams = array())
    {
        if ( is_array($fullurl)) {
            $addonParams = $fullurl;
            $fullurl = false;
            $argSeparator = '&amp;';
        } else if ( is_string($fullurl)) {
            $argSeparator = $fullurl;
            $fullurl = false;
            $addonParams = array();
        }
        $routeuri = ($routeuri = trim($routeuri, '/')) ? ($this->routeBase ? $this->routeBase . '/' : '') . $routeuri : $this->routeBase;

        if ( $this->_modRewriteEnabled ) {
            $url = $this->_urlBase . $routeuri;
            if ( !empty($addonParams)) {
                $url .= '?' . http_build_query($addonParams, '', $argSeparator);
            }

        } else {
            $params = array( $this->_paramName => $routeuri );
            if ( !empty($addonParams)) {
                $params = array_merge($addonParams, $params);
            }
            $routeuri = http_build_query($params, '', $argSeparator);
            if ( $routeuri ) {
                $routeuri = str_replace('%2F', '/', $routeuri);
                $url = $this->_urlBase . '?' . $routeuri;
            } else {
                $url = $this->_urlBase;
            }
        }
        if ( $fullurl ) {
            return 'http://' . $_SERVER['SERVER_NAME'] . $url;
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
    public function redirect($routeuri, $exit=true, $code=302, $headerBefore=NULL, $headerAfter=NULL)
    {
        if($headerBefore!==null){
            foreach($headerBefore as $h){
                header($h);
            }
        }
        header('Location: ' . $this->urlto($routeuri), true, $code);
        if($headerAfter!==null){
            foreach($headerAfter as $h){
                header($h);
            }
        }
        if($exit)
            exit;
    }
    public function exceptionResponse($statusCode, $message)
    {
        header("HTTP/1.0 {$statusCode} {$message}");
        echo "{$statusCode} {$message}";
        exit;
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
