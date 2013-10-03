<?php
/**
 * Core Library
 *
 * @package Core
 * @license MIT
 */


/**
 * NotFoundException
 *
 * @package Core
 */
class NotFoundException extends Exception
{
    /**
     * Constructor
     *
     * If message hasn't been passed to the constructor, the exception will be created with default message __('頁面不存在').
     *
     * @param string $message The exception message.
     * @param integer $code The exception code
     * @param Exception $previous The previous exception used for the exception chaining.
     * @return void
     */
    public function __construct($message='', $code=0, Exception $previous=NULL)
    {
        if (!$message) {
            $message = __('頁面不存在');
        }
        parent::__construct($message, $code, $previous);
    }
}

/**
 * App Controlling and Resource loader.
 *
 * @package Core
 */
class App
{
    /**
     * Application ID, which affects loading path such as controllers, models, modules, viewModels, views and helpers.
     *
     * @var string Default: 'frontend'
     */
    public static $id = 'frontend';

    /**
     * An array which stores Urls objects that was created by App::urls() method.
     *
     * @var Urls[]
     */
    private static $__urlsList = array();

    /**
     * Stores Route instance.
     *
     * @var Route
     */
    private static $__route;

    /**
     * Stores Acl instance.
     *
     * @var Acl
     */
    private static $__acl;

    /**
     * Stores config object.
     *
     * @var stdclass
     */
    private static $__config;

    /**
     * Stores PDO instance.
     *
     * @var PDO
     */
    private static $__db;

    /**
     * Stores I18N instance.
     *
     * @var I18N
     */
    private static $__i18n;



    /**
     * Convers array to object.
     *
     * @param array $array The array to be converted.
     * @return object
     */
    private static function _array2Obj($array)
    {
        foreach ($array as $k => $value) {
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
     * Boot application and execute the controller action for http request.
     *
     * @param  array  $routeConfig {@see Route} for more detail.
     * @param  array  $aclConfig {@see Acl} for more detail.
     * @param  string $aclRole {@see Acl} for more detail.
     * @return void
     */
    public static function run($routeConfig=NULL, $aclConfig=NULL, $aclRole='anonymous')
    {
        self::_prepare();
        self::route($routeConfig)->parse();
        self::acl($aclConfig, $aclRole)->check();
        self::doAction($_REQUEST['controller'], $_REQUEST['action']);
    }

    /**
     * Prepare application
     *
     * This method will set timezone, locale, mb_internal_encoding,
     * strip slashes if magic_quotes is enabled and detects http status and store
     * it into `$_REQUEST['is_ajax']` and `$_REQUEST['is_ssl']`.
     *
     * @return void
     */
    protected static function _prepare()
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

    /**
     * Execute controller action
     *
     * - Any action name starts with `_` cannot be executed
     * - Controller's `$defaultAction` would be used to instead `$actionName` if `$actionName` is unavailable
     * - The order of the execution is
     *  1. `$controller->_preAction()`
     *  2. `$controller->$action()`
     *  3. `$controller->_postAction()`
     *
     * @param string $controllerName The name of the controller to be executed.
     * @param string $actionName The name of the action to be executed.
     * @throws NotFoundException if the action cannot be executed.
     * @return void
     */
    public static function doAction($controllerName, $actionName)
    {
        // load file
        if ('_' === $actionName{0}) {
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
            if (! $controller->defaultAction) {
                throw new NotFoundException(_e('頁面不存在'));
            }
            $action = $controller->defaultAction;
        }

        if ( method_exists($controller, '_preAction')) {
            $controller->_preAction();
        }

        $controller->{$action}();

        if ( method_exists($controller, '_postAction')) {
            $controller->_postAction();
        }
    }

    /**
     * Returns Route instance
     *
     * If Route instance exists, the instance will be reconfigure with `$routeConfig`
     *
     * @param  array  $routeConfig {@see Route} for more detail.
     * @return Route
     */
    public static function route($routeConfig=NULL)
    {
        if (NULL === self::$__route) {
            self::$__route = new Route($routeConfig);
            self::$__route->appendDefaultRoute();
        } elseif (NULL !== $routeConfig) {
            self::$__route->setRoutes($routeConfig);
            self::$__route->appendDefaultRoute();
        }

        return self::$__route;
    }

    /**
     * Returns Acl instance
     *
     * If Acl instance not exists, the instance will be created with `$aclConfig` and `$aclRole`
     *
     * @param  array  $aclConfig {@see Acl} for more detail.
     * @param  string $aclRole {@see Acl} for more detail.
     * @return Acl
     */
    public static function acl($aclConfig=NULL, $aclRole=NULL)
    {
        if (NULL === self::$__acl) {
            self::$__acl = new Acl($aclConfig, $aclRole);
        }

        return self::$__acl;
    }

    /**
     * Returns App::$__config object
     *
     * When calling `App::conf()` first time, `$GLOBALS['config']` array will be converted to object recursively
     * and will be assign to `App::$__config`.
     *
     * @return stdclass
     */
    public static function conf()
    {
        if (NULL === self::$__config) {
            self::$__config = self::_array2Obj($GLOBALS['config']);
        }

        return self::$__config;
    }

    /**
     * Returns App::$__db object
     *
     * If `App::$__db` hasn't been defined, this method will create a new PDO instance and assign it to `App::$__db`
     * The PDO instance will be created using database settings in `App::conf()`:
     *
     * - db->host
     * - db->name
     * - db->charset
     * - db->user
     * - db->password
     * - timezone: 'Asia/Taipei'
     * @return PDO
     */
    public static function db()
    {
        if (NULL === self::$__db) {
            $conf = App::conf();
            $dsn = 'mysql:host=' . $conf->db->host
                    . ';dbname=' . $conf->db->name
                    . ';charset=' . $conf->db->charset;
            $db = new PDO($dsn, $conf->db->user, $conf->db->password, array(
                PDO::MYSQL_ATTR_INIT_COMMAND => "SET NAMES '".$conf->db->charset."';"
            ));
            $db->exec("SET time_zone = '" . $conf->timezone . "'");
            if (phpversion() >= 5.2) {
                $db->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_OBJ);
            }
            $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

            self::$__db = $db;
        }

        return self::$__db;
    }

    /**
     * Returns the specific Urls instance or create a new one.
     *
     * @param string $key The access key of the Urls instance.
     * @param string $urlBase {@see Urls} for more detail.
     * @param string $paramName {@see Urls} for more detail.
     * @return Urls
     */
    public static function urls($key='primary', $urlBase=NULL, $paramName='q')
    {
        if ( isset(self::$__urlsList[$key])) {
            return self::$__urlsList[$key];
        }
        $urlBase = rtrim($urlBase, '/') . '/';

        return (self::$__urlsList[$key] = new Urls( $urlBase, $paramName ));
    }

    /**
     * Create a new View instance with specific app id.
     *
     * `App::$id` would be used if `$appId` hasn't been set.
     *
     * @param string $appId The app id of the view
     * @return View
     */
    public static function view($appId=NULL)
    {
        return new View($appId ? $appId : self::$id);
    }

    /**
     * Returns I18N instance
     *
     * Or create a new I18N instance with `$config`
     *
     * @param array $config {@see I18N} for more detail.
     * @return I18N
     */
    public static function i18n($config=array())
    {
        if (NULL === self::$__i18n) {
            self::$__i18n = new I18N($config);
        }

        return self::$__i18n;
    }


    /**
     * Load Model
     *
     * Path: `{ROOT_PATH}{$appId}/models/{$model}.php`
     *
     * @param string $model The name of the model to be loaded.
     * @param boolean $createObj Whether or not to return the model instance.
     * @param string $appId Load model under `$appId` directory. If not set, current `App::$id` would be used.
     * @return void|Model
     */
    public static function loadModel($model, $createObj=false, $appId=NULL)
    {
        require_once ROOT_PATH . ($appId ? $appId : self::$id) . '/models/' . $model. '.php';

        if ($createObj) {
            return new $model;
        }
    }

    /**
     * Load ViewModel
     *
     * Path: `{ROOT_PATH}{$appId}/viewModels/{$viewModelName}.php`
     *
     * @param string $viewModelName The name of the view model to be loaded.
     * @param boolean|Model $createObj Whether or not to return the view model instance. If is Model, it would be set as ViewModel's model.
     * @param string|array $baseUrl Parameter in ViewModel::__construct(). If is array, each item will be set as member attribute in the view model.
     * @param string $appId Load view model under `$appId` directory. If not set, current `App::$id` would be used.
     * @return void|ViewModel
     */
    public static function loadViewModel($viewModelName, $createObj=false, $baseUrl=NULL, $appId=NULL)
    {
        require_once ROOT_PATH . ($appId ? $appId : self::$id) . '/viewModels/' . $viewModelName . '.php';
        if ($createObj) {
            $vm = $viewModelName . 'ViewModel';

            return new $vm($createObj, $baseUrl);
        }
    }

    /**
     * Load helper file
     *
     * Path: `{ROOT_PATH}{$appId}/helpers/{$class}.php`
     *
     * @param string $class The name of the file to be loaded.
     * @param boolean $createObj Whether or not to return the class instance.
     * @param string $appId Load helper under `$appId` directory. If not set, current `App::$id` would be used.
     * @return void|mixed
     */
    public static function loadHelper($class, $createObj=false, $appId=NULL)
    {
        require_once ROOT_PATH . ($appId ? $appId : self::$id) . '/helpers/' . $class . '.php';
        if ($createObj) {
            return new $class;
        }
    }

    /**
     * Load vendor file
     *
     * Path: `{ROOT_PATH}vendor/{$class}.php`
     *
     * @param string $class The name of the file to be loaded.
     * @param boolean $createObj Whether or not to return the class instance.
     * @return void|mixed
     */
    public static function loadVendor($class, $createObj=false)
    {
        require_once ROOT_PATH . 'vendor/' . $class . '.php';
        if ($createObj) {
            return new $class;
        }
    }

} // END class

/**
 * Request Controller
 *
 * @package Core
 */
abstract class Controller
{
    /**
     * Default action name. If current requested action is unavailable, use this instead.
     *
     * @var string
     */
    public $defaultAction;

    /**
     * The view data, which would be extracted as variable while rendering view template.
     *
     * @var array
     */
    public $data = array();

    /**
     * Url base segment. Generally used to pass to $urls->urlto().
     *
     * @var string
     */
    protected $_baseUrl;

    /**
     * Assigns basic variables to data array
     *
     * Variables assigned to data array are.
     *
     * - `conf`: `App::conf()`.
     * - `urls`: `App::urls()`
     * - `baseUrl`: `Controller::_baseUrl`
     *
     * @param string $type Hasn't been used, could be used in children classes.
     * @return void
     */
    public function _prepareLayout($type=NULL)
    {
        $this->data = array_merge($this->data, array(
            'conf' => App::conf(),
            'urls' => App::urls(),
            'baseUrl' => $this->_baseUrl
        ));
    }

    /**
     * Display 404 page
     *
     * Will send 404 header on none IIS server.
     *
     * @param boolean $backLink Whether or not to display history back link.
     * @param string $content The HTML string of message. If not set, it goes with default message: '您所要檢視的頁面不存在'.
     * @param string $layout Which layout to be rendered.
     * @return void
     */
    public function _show404($backLink=false, $content=NULL, $layout='layout')
    {
        if (FALSE === $_SERVER['IS_IIS']) {
            header('HTTP/1.0 404 Not Found', true, 404);
            header('Status: 404');
        }
        $this->data['page_title'] = $this->data['window_title'] = _e('404 頁面不存在!');
        $content = (NULL === $content ? _e('您所要檢視的頁面不存在！') : $content);
        $this->_showError($content, $backLink, $layout);
    }


    /**
     * Display error page
     *
     * Will disable $layout and $backLink if current request is via ajax
     *
     * @param string $content The HTML string of messagse.
     * @param boolean $backLink Whether or not to display history back link.
     * @param string $layout Which layout to be rendered.
     * @return void
     */
    public function _showError($content, $backLink=true, $layout='layout')
    {
        if ($_REQUEST['is_ajax']) {
            $layout = false;
            $backLink = false;
        }

        if ($backLink) {
            $content = '<div>' . $content . '</div>'
                . '<div class="alert-actions"><a href="javascript:window.history.back(-1)" class="btn btn-medium">' . _e('返回上一頁') . '</a></div>';
        }
        $this->data['content'] = $content;
        $this->_prepareLayout();
        $appId = 'common';
        $layoutAppId = NULL;
        App::view()->render('error', $this->data, compact('layout', 'appId', 'layoutAppId'));
    }

    /**
     * Load module
     *
     * Path: `{ROOT_PATH}{$appId}/modules/{$module}.php`
     *
     * @param string $module The name of the module to be loaded.
     * @param boolean|array $createObj Whether or not to return the view model instance. If is array, each item will be set as member attribute in the view model.
     * @param string $appId Load model under `$appId` directory. If not set, current `App::$id` would be used.
     * @return void|Module
     */
    public function _loadModule($module, $createObj=true, $appId=NULL)
    {
        require_once ROOT_PATH . ($appId ? $appId : App::$id) . '/modules/' . $module . '.php';
        if ($createObj) {
            $module = $module . 'Module';
            if ( is_array($createObj)) {
                return new $module($this, $createObj);
            }

            return new $module($this, NULL);
        }
    }

    /**
     * Set page/window title
     *
     * @param string $title The string to be set as page/window title.
     * @return void
     */
    public function _setTitle($title)
    {
        $this->data['page_title'] = $this->data['window_title'] = $title;
    }

    /**
     * Append page/window title
     *
     * @param string $title The string to be append to page/window title.
     * @param array $sep Separators of page/window title.<br />
     *  * `[pageTitleSep]` (string Default: '-'), <br />
     *  * `[windowTitleSep]` (string Default: ' | ')
     *
     * @return void
     */
    public function _appendTitle($title, $sep = array())
    {
        $sep = array_merge(array( 'pageTitleSep' => '-', 'windowTitleSep' => '|'), $sep);
        $this->data['page_title'] .= ' ' . $sep['pageTitleSep'] . ' ' . $title;
        $this->data['window_title'] = $title . $sep['windowTitleSep'] . $this->data['window_title'];
    }

} // END class

/**
 * Extended from Controller, has `api` and `_doSelectedAction` methods.
 *
 * @package Core
 */
abstract class BaseController extends Controller
{
    /**
     * Whether or not to send fail header
     *
     * @var boolean
     */
    public $_apiSendFailHeader = FALSE;

    /**
     * Handles JSON API calling and responses in JSON format.
     *
     * This method will call either `$module->{'_api' . $method}()` or `$controller->{'_api' . $method}()`
     *
     * @param integer $segment The segment number in queryString that represents api method name.
     * @param Module $module Execute `$module` api instead of $controller api
     * @return void
     */
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
            if ($this->_apiSendFailHeader) {
                header('HTTP/1.1 400 Bad Request');
            }
            $response = array( 'error' => array( 'message' => $ex->getMessage() ) );
        }
        header('Content-Type: text/plain; charset="' . App::conf()->encoding . '"');
        echo json_encode($response);
    }

    /**
     * Process $_POST['select'] with $_POST['action'] then redirect to current REQUEST_URI.
     *
     * This method will call either `$module->{'_select' . $action}()` or `$controller->{'_select' . $action}()`
     *
     * @param Module $module Execute `$module` action instead of $controller action
     * @return void
     */
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

/**
 * Controller Module
 *
 * @package Core
 */
abstract class Module
{
    /**
     * Stores Controller instance
     *
     * @var Controller
     */
    protected $c;

    /**
     * Constructor
     *
     * @param Controller $controller Would be set as Model's controller, which is `$model->c`
     * @param array $members Each item will be set as $model's member attribute.
     * @return void
     */
    public function __construct($controller, $members=NULL)
    {
        $this->c = $controller;
        if ($members !== NULL) {
            foreach ($members as $key => $value) {
                $this->{$key} = $value;
            }
        }
    }
}

/**
 * Model
 *
 * @TODO document
 *
 * @package Core
 *
 * @method vold beforeVerify(array &$fields, array &$input=NULL, mixed $args=NULL)
 * @method vold beforeSave(array &$fields, array &$input=NULL, mixed $args=NULL)
 * @method vold afterSave(array &$fields, array &$input=NULL, mixed $args=NULL)
 */
abstract class Model
{
    protected $_customId = FALSE;

    protected $_config = array(
        'primaryKey' => 'id',
        'table' => null
    );

    public function verify(&$fields, &$input=NULL, $args=NULL)
    {
        if ( method_exists($this, 'beforeVerify')) {
            $this->beforeVerify($fields, $input, $args);
        }
        Validator::verify($fields, $this, $input);
    }
    public function save(&$fields, $verify=true, &$input=NULL, $args=NULL)
    {
        if (NULL === $input) {
            $input = &$_POST;
        }

        if ($verify) {
            $this->verify($fields, $input, $args);
        }

        if ( method_exists($this, 'beforeSave')) {
            $this->beforeSave($fields, $input, $args);
        }

        if ( $this->_customId || ! $this->hasPrimaryKey()) {
            if ( is_array($this->_config['primaryKey'])) {
                foreach ($this->_config['primaryKey'] as $key) {
                    if ( ! isset($fields[$key]) && $this->{$key} !== NULL) {
                        $fields[$key] = true;
                    }
                }
            }
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
            foreach ($this->_config['primaryKey'] as $key) {
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
            foreach ($this->_config['primaryKey'] as $key) {
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
        foreach ($this->_config['primaryKey'] as $key) {
            if ($this->{$key} === NULL) {
                return false;
            }
        }

        return true;
    }

    // Model Config
    public function getConfig($key=null)
    {
        if (NULL !== $key) {
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

    // select relational
    public static function selectBelongsTo(&$select, $config, $columns='name')
    {
        foreach ($config as $alias => $rconf) {
            if ( is_string($columns)) {
                $select[] = '(SELECT `' . $columns . '` FROM `' . $rconf['table'] . '` b WHERE b.`' . $rconf['foreignKey'] . '` = a.`' . $rconf['relKey'] . '`) `' . $alias . '_' . $columns . '`';
                continue;
            }
            if ( is_array($columns)) {
                if ( isset($columns[$alias])) {
                    if ( is_string($columns[$alias])) { // select field
                        $select[] = '(SELECT `' . $columns[$alias] . '` FROM `' . $rconf['table'] . '` b WHERE b.`' . $rconf['foreignKey'] . '` = a.`' . $rconf['relKey'] . '`) `' . $alias . '_' . $columns[$alias] . '`';
                        continue;
                    }
                    if ( is_array($columns[$alias])) { // complex query
                        foreach ($columns[$alias] as $colName => $rawSelect) {
                            if ( is_numeric($colName)) {
                                $colName = $rawSelect;
                            }
                            $select[] = '(SELECT ' . $rawSelect . ' FROM `' . $rconf['table'] . '` b WHERE b.`' . $rconf['foreignKey'] . '` = a.`' . $rconf['relKey'] . '`) `' . $alias . '_' . $colName . '`';
                        }
                        continue;
                    }
                }
                continue;
            }
        }
    }

    public static function selectHasMany(&$select, $config, $columns=array())
    {
        foreach ($config as $alias => $rconf) {
            $fromWhere = ' FROM `' . $rconf['table'] . '` b WHERE '
                . (isset($rconf['whereLink']) ? $rconf['whereLink'] : 'b.`' . $rconf['foreignKey'] . '` = a.`' . $rconf['relKey'] . '`')
                . (isset($rconf['where']) ? ' AND ' . $rconf['where'] : '');
            $select[] = '(SELECT COUNT(*) ' . $fromWhere . ') `' . $alias . '_count`';

            if ( isset($columns[$alias])) {
                foreach ($columns[$alias] as $colName => $rawSelect) {
                    $select[] = '(SELECT ' . $rawSelect . ' ' . $fromWhere . ') `' . $alias . '_' . $colName . '`';
                }
            }
        }
    }

}

/**
 * ViewModel
 *
 * @package Core
 */
abstract class ViewModel
{
    /**
     * Stores Model instance
     *
     * @var Model
     */
    protected $m;

    /**
     * Base url of this ViewModel
     *
     * @var string
     */
    protected $baseUrl;

    /**
     * Constructor
     *
     * @param Model $model Would be set as ViewModel's model, which is `$viewModel->m`
     * @param string|array $baseUrl If is array, each item will be set as $viewModel's member attribute.
     * @return void
     */
    public function __construct($model, $baseUrl=NULL)
    {
        $this->m = $model;
        if (is_array($baseUrl)) {
            foreach ($baseUrl as $key => $value) {
                $this->{$key} = $value;
            }
        } else {
            $this->baseUrl = $baseUrl;
        }
    }
}

/**
 * View
 *
 * @package Core
 */
class View
{
    /**
     * Stores the application id of this view, affects the loading path of view file.
     *
     * @var string
     */
    private $__appId;

    /**
     * Stores rendered outputs
     *
     * between `$this->startAddon($addonName)` and `$this->endAddon()`
     *
     * @var string[]
     */
    private $__addons = array();


    /**
     * Stores current addon name
     *
     * while processing `$this->startAddon($addonName)` and `$this->endAddon()`
     *
     * @var string
     */
    private $__currentAddonName;


    /**
     * Constructor
     *
     * @param string $appId The application id of this View. If not set, `App::$id` would be used.
     * @return void
     */
    public function __construct($appId)
    {
        $this->__appId = ($appId ? $appId : App::$id);
    }

    /**
     * Returns view directory path of specific application id.
     *
     * @param string $appId The view's application id. If not set, `$this->__appId` would be used.
     * @return string
     */
    private function __getDir($appId=NULL)
    {
        return ROOT_PATH . ($appId ? $appId : $this->__appId) . '/views/';
    }

    /**
     * Returns contents of specific view file
     *
     * @param string $viewFile File name of the view file. The path starts from `{ROOT_PATH}{$appId}/views/`.
     * @param string $appId The view file's application id. If not set, `$this->__appId` would be used.
     * @return string
     */
    public function content($viewFile, $appId=NULL)
    {
        return file_get_contents( $this->__getDir($appId) . $viewFile);
    }

    /**
     * Load View File
     *
     * Extracts `$this->data` and `$viewData` as view variables before including view file.
     *
     * @param string $viewFile File name of the view file. The path starts from `{ROOT_PATH}{$appId}/views/`.
     * @param string $appId The view file's application id. If not set, `$this->__appId` would be used.
     * @param array $viewData Additional view data to be used in the view.
     * @return void
     */
    public function load($viewFile, $appId=NULL, $viewData=array())
    {
        extract($this->data);
        extract($viewData);
        if ( false === strpos($viewFile, '.')) {
            include $this->__getDir($appId) . $viewFile . '.html';
        } else {
            include $this->__getDir($appId) . $viewFile;
        }
    }

    /**
     * Renders view with options
     *
     * * Layout would automatically set to false if current request is sent with `X-PJAX` header.
     * * `{ROOT_PATH}{$appId}/views/{$layout}_functions.php` would be loaded automatically if it exists.
     * * Where does string `{{content_html}}` take in place in the layout file, would be replaced with the rendered view content.
     *
     * @param string $viewFile File name of the view file. The path starts from `{ROOT_PATH}{$appId}/views/`.
     * @param array $data View data, which would be stored as `$this->data` and extracted as variable in both view and layout file.
     * @param array $options Options that controllers rendering behaviors.<br /><br />
     * * `[appId]` `string`<br />
     *   The view file's application id. If not set, `$this->__appId` would be used.<br /><br />
     * * `[layout]` `boolean|string`<br />
     k   File name of view layout, or set to false to disable layout rendering. The path starts from `{ROOT_PATH}{$appId}/views/`.<br /><br />
     * * `[layoutAppId]` `string`<br />
     *   The layout file's application id. If not set, `$this->__appId` would be used.<br /><br />
     * * `[return]` `boolean`<br />
     *   Returning the rendered string instead of printing
     *
     * @return void|string
     */
    public function render($viewFile, $data=array(), $options=array())
    {
        $layout = 'layout';
        $appId = NULL;
        $layoutAppId = NULL;
        $return = false;
        extract($options, EXTR_IF_EXISTS);

        if (!empty($_SERVER['HTTP_X_PJAX'])) {
            $layout = FALSE;
        }

        $this->data = $data;

        if ( file_exists($this->__getDir($layoutAppId) . $layout . '_functions.php')) {
            include $this->__getDir($layoutAppId) . $layout . '_functions.php';
        }

        ob_start();
        $this->load($viewFile, $appId);
        $content_html = ob_get_contents();
        ob_end_clean();

        if (! $layout) {
            if ($return) {
                return $content_html;
            }
            echo $content_html;

            return null;
        }

        ob_start();
        $this->load($layout, $layoutAppId);
        $layout_html = explode('{{content_html}}', ob_get_contents());
        ob_end_clean();

        if (! $return) {
            echo $layout_html[0];
            echo $content_html;
            if ( isset($layout_html[1]) ) {
                echo $layout_html[1];
            }

            return;
        }

        return $layout_html[0] . $content_html . $layout_html[1];
    }

    /**
     * Start an addon snippet.
     *
     * @param string $addonName The access key of addon.
     * @return void
     */
    public function startAddon($addonName)
    {
        $this->__currentAddonName = $addonName;
        if ( ! isset($this->__addons[$addonName])) {
            $this->__addons[$addonName] = array();
        }
        ob_start();
    }

    /**
     * Close an addon snippet.
     *
     * @param string $addonName The access key of addon.
     * @return void
     */
    public function endAddon()
    {
        $this->__addons[$this->__currentAddonName][] = ob_get_contents();
        $this->__currentAddonName = NULL;
        ob_end_clean();
    }

    /**
     * Prints snippets of specific addon.
     *
     * @param string $addonName The access key of addon.
     * @return void
     */
    public function showAddon($addonName)
    {
        if ( isset($this->_addons[$addonName])) {
            echo implode("\n", $this->_addons[$addonName]);
        }
    }

    /**
     * Check if the specific addon exists.
     *
     * @param string $addonName The access key of addon.
     * @return boolean
     */
    public function hasAddon($addonName)
    {
        return isset($this->_addons[$addonName]);
    }
} // END class

/**
 * Search Helper
 *
 * @package Core
 */
class Search
{
    /**
     * Stores sql where conditions.
     *
     * @var string[]
     */
    public $where = array();

    /**
     * Stores params for PDO::execute()
     *
     * @var array
     */
    public $params = array();

    /**
     * Returns sql WHERE string from $this->where
     *
     * @return string
     */
    public function sqlWhere()
    {
        return sizeof($this->where) ? ' WHERE ' . implode(' AND ', $this->where) : '';
    }
}

/**
 * DBHelper
 *
 * @TODO document
 *
 * @package Core
 */
class DBHelper
{
    public static function isUniqIn($table, $field, $value, $id=NULL)
    {
        $sql = 'SELECT COUNT(*) = 0 FROM ' . $table . ' WHERE ';
        if ($id) {
            $sql .= ' `id` != ? AND ';
            $params[] = $id;
        }
        $sql .= ' `'.$field.'` = ?';
        $params[] = $value;

        $stmt = App::db()->prepare($sql);
        $stmt->execute($params);

        return (bool) $stmt->fetchColumn();
    }
    public static function clearNewFiles($data)
    {
        if ( empty($data->_new_files)) {
            return;
        }
        foreach ($data->_new_files as $file) {
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

        foreach ($array as $key => $value) {
            if ( is_array($value) ) {
                if ( count($value) > 1) {
                    $sql[] = '(' . self::where($value) . ')';
                } else {
                    foreach ($value as $k => $v) {
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
        foreach ($array as $key => $value) {
            $sql[] = '`' . $key . '` = ' . $db->quote($value);
        }

        return implode(' AND ', $sql);
    }
    public static function toSetSql($data, $fields, &$params, $rawValueFields=NULL)
    {
        $sql = array();
        foreach ($fields as $field) {
            $sql[] = '`'.$field.'` = ?';
            $params[] = $data->{$field};
        }
        if ( ! empty($rawValueFields)) {
            foreach ($rawValueFields as $field => $value) {
                $sql[] = '`'.$field.'` = ' . $value;
            }
        }

        return implode(',', $sql);
    }
    public static function toKeySetSql($data, $fields, &$params, $rawValueFields=NULL)
    {
        $sql = array();
        foreach ($fields as $field) {
            $sql[] = '`'.$field.'` = :'.$field;
            $params[':'.$field] = $data->{$field};
        }
        if ( ! empty($rawValueFields)) {
            foreach ($rawValueFields as $field => $value) {
                $sql[] = '`'.$field.'` = ' . $value;
            }
        }

        return implode(',', $sql);
    }
    public static function toInsertSql($data, $fields, &$params, $rawValueFields=NULL)
    {
        $columns = $values = array();
        foreach ($fields as $field) {
            $columns[] = $field;
            $values[] = '?';
            $params[] = $data->{$field};
        }
        if ( ! empty($rawValueFields)) {
            foreach ($rawValueFields as $field => $value) {
                $columns[] = $field;
                $values[] = $value;
            }
        }

        return '(`'.implode('`,`', $columns).'`) VALUES ('.implode(',', $values).')';
    }
    public static function toKeyInsertSql($data, $fields, &$params, $rawValueFields=NULL)
    {
        $columns = $values = array();
        foreach ($fields as $field) {
            $columns[] = $field;
            $values[] = ':'.$field;
            $params[':'.$field] = $data->{$field};
        }
        if ( ! empty($rawValueFields)) {
            foreach ($rawValueFields as $field => $value) {
                $columns[] = $field;
                $values[] = $value;
            }
        }

        return '(`'.implode('`,`', $columns).'`) VALUES ('.implode(',', $values).')';
    }
    /**
     * @param string $req       : the query on which link the values
     * @param array  $array     : associative array containing the values to bind
     * @param array  $typeArray : associative array with the desired value for its corresponding key in $array
     * */
    public static function bindArrayValue($req, $array, $typeArray = false)
    {
        if (is_object($req) && ($req instanceof PDOStatement)) {
            foreach ($array as $key => $value) {
                if($typeArray)
                    $req->bindValue("$key",$value,(isset($typeArray[$key])?$typeArray[$key]:PDO::PARAM_STR));
                else {
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
        /* Options
         * [columns]  array
         * [table]    string
         * [dir]      string
         * [where]    string
         * [params]   array optional
         * [suffixes] array optional
         */
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
        while ( $item = $stmt->fetchObject()) {
            foreach ($columns as $column) {
                $delFileOpts = array(
                    'model' => $item,
                    'column' => $column,
                    'dir' => $dir,
                );
                if ( isset($options['suffixes'][$column])) {
                    $delFileOpts['suffixes'] = $options['suffixes'][$column];
                }
                self::deleteFile($delFileOpts);
            }
        }
    }

    public static function deleteFile($options)
    {
        /* Options
         * [model]    Model
         * [column]   string
         * [dir]      string
         * [suffixes] array
         */
        extract($options);

        if (! $model->{$column}) {
            return;
        }
        if ( file_exists($dir . $model->{$column})) {
            unlink($dir . $model->{$column});
        }
        if ( isset($options['suffixes'])) {
            $info = pathinfo($model->{$column});
            $fdir = '';
            if ('.' !== $info['dirname']) {
                $fdir = $info['dirname'] . DIRECTORY_SEPARATOR;
            }
            foreach ($options['suffixes'] as $suffix) {
                $filename = $fdir . $info['filename'] . $suffix . (isset($info['extension']) ? '.' . $info['extension'] : '');
                if ( file_exists($dir . $filename)) {
                    unlink($dir . $filename);
                }
            }
        }
    }

    public static function splitCommaList($str)
    {
        if ( is_array($str)) {
            return $str;
        }
        if (NULL === $str || '' === $str) {
            return array();
        }
        $array = explode(',', $str);

        return array_combine($array, $array);
    }

    public static function fetchKeyedList(PDOStatement $stmt, $keyColumn='id', $displayColumn=NULL, $className='stdClass')
    {
        $list = array();

        if (NULL === $displayColumn) {
            while ( $item = $stmt->fetchObject($className)) {
                $list[$item->{$keyColumn}] = $item;
            }
        } else {
            while ( $item = $stmt->fetchObject($className)) {
                $list[$item->{$keyColumn}] = $item->{$displayColumn};
            }
        }

        return $list;
    }

    // Table Operations

    public static function deleteAll($model, $ids)
    {
        $model = new $model;
        $mconf = $model->getConfig();

        if ( is_object($ids) && $ids instanceof Search ) {
            $search = $ids;
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
        $data = (object) $data;
        $sql = DBHelper::toKeySetSql($data, $fields, $params, $rawValueFields);
        $stmt = App::db()->prepare('UPDATE `' . $mconf['table'] . '` SET ' . $sql . ' WHERE `' . $mconf['primaryKey'] . '` ' . DBHelper::in($ids));
        $stmt->execute($params);
    }

    public static function count($model, $search, $countWith='*')
    {
        $where = $params = NULL;
        extract(self::translateModelSearchArg($model, $search));

        $model = new $model;
        $mconf = $model->getConfig();

        $stmt = App::db()->prepare('SELECT COUNT(' . $countWith . ') `counts` FROM `' . $mconf['table'] . '` a ' . $where . ' LIMIT 1');
        $stmt->execute($params);

        return $stmt->fetchColumn();
    }

    public static function getList($model, $search=NULL, Pagination $pager=NULL, $for='list')
    {
        $where = $params = NULL;
        extract(self::translateModelSearchArg($model, $search));

        $model = new $model;
        $mconf = $model->getConfig();

        $pagerInfo = null;

        if (NULL !== $pager) {
            $pagerInfo = $pager->getSqlGroupBy() . $pager->getSqlOrderBy() . $pager->getSqlLimit();
        } else {
            $pagerInfo = '';
            if (!empty($search->groupBy)) {
                $pagerInfo .= ' GROUP BY ' . $search->groupBy;
            }
            if (!empty($search->orderBy)) {
                $pagerInfo .= ' ORDER BY ' . $search->orderBy;
            } elseif (isset($mconf['primaryKey'])) {
                $pagerInfo .= ' ORDER BY `' . $mconf['primaryKey'] . '` ASC';
            }
            if (!empty($search->limit)) {
                $pagerInfo .= ' LIMIT ' . $search->limit;
            }
        }

        $stmt = App::db()->prepare('SELECT ' . $model->selectInfo($for, $search) . ' FROM `' . $mconf['table'] . '` a ' . $where . $pagerInfo);
        $stmt->execute($params);

        return $stmt;
    }

    public static function getOne($model, $search, $for=NULL, $fetchIntoObject=NULL)
    {
        $where = $params = NULL;
        extract(self::translateModelSearchArg($model, $search));

        $model = new $model;
        $mconf = $model->getConfig();

        $searchInfo = NULL;
        if ( is_object($search) && $search instanceof Search ) {
            if (!empty($search->groupBy)) {
                $searchInfo .= ' GROUP BY ' . $search->groupBy;
            }
            if (!empty($search->orderBy)) {
                $searchInfo .= ' ORDER BY ' . $search->orderBy;
            }
        }

        $stmt = App::db()->prepare('SELECT ' . $model->selectInfo($for, $search) . ' FROM `' . $mconf['table'] . '` a ' . $where . $searchInfo . ' LIMIT 1');
        $stmt->execute($params);

        if (NULL !== $fetchIntoObject) {
            $stmt->setFetchMode(PDO::FETCH_INTO, $fetchIntoObject);
            $stmt->fetch(PDO::FETCH_OBJ);

            return $fetchIntoObject;
        }

        return $stmt->fetchObject(get_class($model));
    }

    public static function getFields($model, $for=null)
    {
        $model = new $model;

        return $model->fields($for);
    }
    public static function translateModelSearchArg($model, $search)
    {
        if ( is_object($search) && $search instanceof Search ) {
            $where = $search->sqlWhere();
            $params = $search->params;
        } elseif ( is_array($search)) {
            $where = ' WHERE ' . DBHelper::where($search);
            $params = array();
        } else {
            $model = new $model;
            $mconf = $model->getConfig();
            $where = ' WHERE `' . $mconf['primaryKey'] . '` = ?';
            $params = array($search);
        }

        return compact('where', 'params');
    }

} // END class

/**
 * Validator
 *
 * @TODO document
 *
 * @package Core
 */
class Validator
{
    /**
     * 儲存圖片，若為圖片更新則會刪除舊圖片之後再存新圖
     *
     * $opt
     *  [resize] (array)
     *  [crop] (bool)
     *  [thumbnails] (array)
     *      [{suffix}] (array)
     *          [size] (array)
     *          [crop] (bool)
     *
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
        $data->_new_files[] = $opt['dir'] . $source;

        if ($data->{$key}) { // 移除舊檔案
            $file = $gd->processPath . $data->{$key};
            if (file_exists($file)) {
                unlink($file);
            }
            if ( isset($opt['thumbnails'])) {
                $oldinfo = pathinfo($data->{$key});
                if ( !isset($oldinfo['filename'])) {
                    $oldinfo['filename'] = substr($oldinfo['basename'], 0, strlen($oldinfo['basename'])-strlen($oldinfo['extension'])-1);
                }
                foreach ($opt['thumbnails'] as $suffix => $sOpt) {
                    $file = $gd->processPath . $oldinfo['dirname'] . DIRECTORY_SEPARATOR . $oldinfo['filename'] . $suffix . '.' . $oldinfo['extension'];
                    if ( file_exists($file)) {
                        unlink($file);
                    }
                }
            }
            $data->{$key} = $input[$key] = '';
        }
        if (!isset($opt['method'])) {
            $method = empty($opt['crop']) ? 'createThumb' : 'adaptiveResizeCropExcess';
        } else {
            $method = $opt['method'];
        }
        $info = pathinfo($source);
        if ( !isset($info['filename'])) {
            $info['filename'] = substr($info['basename'], 0, strlen($info['basename'])-strlen($info['extension'])-1);
        }
        $filename = $info['filename'];
        $ext = strtolower($info['extension']);
        $gd->{$method}(
            $source,
            $opt['resize'][0], $opt['resize'][1],
            $filename,
            (isset($opt['cropFrom']) ? $opt['cropFrom'] : null)
        );
        $data->{$key} = $input[$key] = $source;

        if ( isset($opt['thumbnails'])) {
            foreach ($opt['thumbnails'] as $suffix => $sOpt) {
                $sOpt = (array) $sOpt;
                if (!isset($sOpt['method'])) {
                    $method = empty($sOpt['crop']) ? 'createThumb' : 'adaptiveResizeCropExcess';
                } else {
                    $method = $sOpt['method'];
                }
                $gd->{$method}(
                    $source,
                    $sOpt['size'][0], $sOpt['size'][1],
                    $filename . $suffix,
                    (isset($sOpt['cropFrom']) ? $sOpt['cropFrom'] : null)
                );
                $data->_new_files[] = $opt['dir'] . $filename . $suffix . '.' . $ext;
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
            $oldFile = $data->{$key};
            $newFile = $uploader->save();

            if ( $oldFile && file_exists($opt['dir'] . $oldFile)) { // remove old file
                unlink($opt['dir'] . $oldFile);
            }
            $data->{$key} = $input[$key] = $newFile;
            $data->{$key.'_type'} = $input[$key.'_type'] = $uploader->getFileType();
            $data->{$key.'_orignal_name'} = $input[$key.'_orignal_name'] = $uploader->getOrignalName();

            if ( ! is_array($data->{$key})) {
                $data->_new_files[] = $opt['dir'] . DIRECTORY_SEPARATOR . $data->{$key};
            } else {
                foreach ($data->{$key}['successed'] as $info) {
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

        if ($input === NULL) {
            $input = &$_POST;
        }
        // verify data
        foreach ($fields as $key  => $opt) {
            try {

                $label = $opt['label'];

                if ( ! isset($opt['type'])) {
                    $opt['type'] = null;
                }

                // upload files
                switch ($opt['type']) {
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
                if ('boolean' === $opt['type']) {
                    $data->{$key} = isset($input[$key]) ? (int) (boolean) $input[$key] : '0';
                    $is_empty = false;
                } elseif ('multiple' === $opt['type']) {
                    $data->{$key} = isset($input[$key]) && is_array($input[$key]) ? $input[$key] : array();
                    $is_empty = empty($data->{$key});
                } else {
                    $data->{$key} = isset($input[$key]) ? trim($input[$key]) : '';
                    $is_empty = $data->{$key} === '';
                }
                if (! $is_empty) {
                    switch ($opt['type']) {
                        case 'date':
                        case 'datetime':
                            if ( ! ( $timestamp = strtotime($data->{$key}))) {
                                throw new Exception(sprintf(
                                        _e('%s: 非正確的時間格式'),
                                        '<strong>' . HtmlValueEncode($label) . '</strong>' ));
                            }
                            if ('date' === $opt['type']) {
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
                            foreach ($data->{$key} as $k => $v) {
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
        foreach ($opt['callbacks'] as $ck => $func) {
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
                } elseif ( method_exists(__CLASS__, $func)) { // Validator::$func()
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

        switch ($format) {
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
            throw new Exception( sprintf(__('日期格式不正確!請使用%s格式'), $format));
        }

        return $value;
    }
    public static function datetime($value)
    {
        $rs = strtotime($value);

        if ($rs===false || $rs===-1) {
            throw new Exception(__('時間格式不正確'));
        }

        return $value;
    }

    public static function idNumber($value, $identityType='id')
    {
        if ( ! self::_idNumber($value, $identityType)) {
            throw new Exception(__('請輸入有效的證件編號'));
        }

        return $value;
    }

    private static function _idNumber($value, $identityType='id')
    {
        if ('passport' === $identityType) {
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
        if (!$isArcNumber) {
            $sum +=  (int) $number{1} * 8;
        } else {
            $gender = $cities[$number{1}];
            $sum += ($gender % 10 * 8);
            unset($gender);
        }

        // 計算中間值的加權
        for ($i=2; $i<=8; $i++) {
            $sum +=  (int) $number{$i} * (9-$i);
        }

        // 加上檢查碼
        $sum += (int) $number{9};

        return ($sum % 10 === 0);
    }

    public static function NullOrHasRecord($value, $model, $search=NULL)
    {
        if (NULL === $value) {
            return $value;
        }

        return self::hasRecord($value, $model, $search);
    }

    public static function hasRecord($value, $model, $search=NULL)
    {
        if ( ! class_exists($model)) {
            App::loadModel($model);
        }

        $tmpModel = new $model;
        $mconf = $tmpModel->getConfig();
        unset($tmpModel);

        if ( is_object($search) && $search instanceof Search) {
            array_unshift($search->where, $mconf['primaryKey']);
            array_unshift($search->params, $value);
        } elseif ( is_array($search) ) {
            array_merge(array($mconf['primaryKey'] => $value), $search);
        } else {
            $search = $value;
        }

        if ( ! DBHelper::count($model, $search) ) {
            throw new Exception(__('資料不存在'));
        }

        return $value;
    }
    public static function hexColor($value)
    {
        if (!preg_match("/^#(?:[0-9a-fA-F]{3}){1,2}$/", $value)) {
            throw new Exception(__('請輸入 HEX 色碼如: #FFF 或 #FFFFFF'));
        }

        return $value;
    }
}

/**
 * Urls
 *
 * @package Core
 */
class Urls
{
    /**
     * Stores activation status of mod rewrite.
     *
     * If enabled, the generated url will look like `/subfolder/a/b/c`.
     *
     * @var string
     **/
    private $_modRewriteEnabled = null;

    /**
     * The access key of uri string in $_GET array
     *
     * @var string
     **/
    private $_paramName = 'q';

    /**
     * Stores the requested uri string (ex: $_GET['q']) with trailing slash stripped .
     *
     * @var string
     */
    private $_queryString;

    /**
     * Stores the required uri string in array format.
     *
     * @var array
     **/
    private $_segments;

    /**
     * Stores the path that will be prepended to the generated url.
     *
     * e.g. With urlBase: `/subfolder/` => generates `/subfolder/?q=a/b/c`
     *
     * @var string
     **/
    private $_urlBase;

    /**
     * Stores the file name of the generated url.
     *
     * e.g With file name `app.php` => generates `app.php?q=a/b/c`
     *
     * @var string
     **/
    private $_fileName = '';

    /**
     * Stores the base segment of the generated url.
     *
     * e.g: With segment base `zh-tw` => generates `?q=zh-tw/a/b/c`
     *
     * @var string
     **/
    private $_segmentBase;

    /**
     * Constructor
     *
     * @param  string $urlBase   Set the path that will be prepended to the generated url.<br />
     *                           e.g. `/subfolder/` => `/subfolder/?q=a/b/c`
     * @param  string $paramName The access key of uri string in `$_GET` array
     * @return void
     */
    public function __construct($urlBase, $paramName='q')
    {
        $this->_urlBase = rtrim($urlBase, '/') . '/';
        $this->_modRewriteEnabled = App::conf()->enable_rewrite && self::__isModRewriteEnabled();
        $this->_paramName = $paramName;

        $this->_queryString = isset($_GET[$this->_paramName]) ? trim($_GET[$this->_paramName], '/') : '';
        $this->_segments = explode('/', $this->_queryString);
    }

    /**
     * Get the segments array
     *
     * @return array
     */
    public function getSegments()
    {
        return $this->_segments;
    }

    /**
     * Shift the segments array
     *
     * @return string The shifted segment
     */
    public function shiftSegments()
    {
        return array_shift($this->_segments);
    }

    /**
     * Set the file name of the generated url.
     *
     * e.g With file name `app.php` => generates `app.php?q=a/b/c`
     *
     * @param string $name The file name.
     * @return void
     */
    public function setFileName($name)
    {
        $this->_fileName = $name;
    }

    /**
     * Set the base segment of the generated url.
     *
     * e.g: With segment base `zh-tw` => generates `?q=zh-tw/a/b/c`
     *
     * @param string $base The base segment.
     * @return void
     */
    public function setSegmentBase($base)
    {
        $this->_segmentBase = $base;
    }

    /**
     * Returns the base segment
     *
     * @return string
     */
    public function getSegmentBase()
    {
        return $this->_segmentBase;
    }

    /**
     * Returns the requested uri string.
     *
     * @return string
     */
    public function getQueryString()
    {
        return $this->_queryString;
    }

    /**
     * Detects whether or not the server has enabled mod rewrite
     *
     * @return boolean
     */
    private static function __isModRewriteEnabled()
    {
        if (function_exists('apache_get_modules')) {
            return in_array('mod_rewrite', apache_get_modules());
        }

        return (getenv('HTTP_MOD_REWRITE') === 'On');
    }

    /**
     * Returns the specific part of segments
     *
     * @param  integer $index   The segment position in the segments array.
     * @param  string  $default If the segment is undefined, return `$default` instead.
     * @return string
     **/
    public function segment($index, $default='')
    {
        if ( isset($this->_segments[$index]) && $this->_segments[$index] !== '') {
            return $this->_segments[$index];
        }

        return $default;
    }

    /**
     * A callback function for `preg_replace_callback` that taken in place in Urls::urltoId()
     *
     * @return string
     */
    private function ___urltoIdReplacer($matches)
    {
        $paramName = $matches['name'];
        if (isset($this->tmpRouteParams[$paramName])) {
            return $this->tmpRouteParams[$paramName];
        }

        return App::route()->getDefault($paramName);
    }

    /**
     * Generates url by predefined route id.
     *
     * @param string $routeId The route id.
     * @param array $routeParams The route params corresponding to route pattern.
     * @param array $urlParams Additional query parameters to be pass to the generated url.
     * @param options $options Url options:<br /><br />
     *
     * * `fullurl` `boolean` `Default: false`<br />
     *   Where or not to prepend domain info to the generated url.<br /><br />
     *
     * * `argSeparator` `string` `Default: '&nbsp;'`<br />
     *   The separator used in PHP generated URLs to separate arguments.
     *
     * @return string
     */
    public function urltoId(
        $routeId, $routeParams=NULL, $urlParams=NULL,
        $options=array( 'fullurl' => false, 'argSeparator' => '&amp;')
    ) {
        $route = App::route();

        if ( !$rule = $route->getNamedRoutes($routeId)) {
            return NULL;
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
     * Generates url.
     *
     * @param string $url The url string. e.g: `a/b/c` => `?q=a/b/c`
     * @param array $urlParams Additional query parameters to be pass to the generated url.
     * @param options $options Url options:<br /><br />
     *
     * * `fullurl` `boolean` `Default: false`<br />
     *    Where or not to prepend domain info to the generated url.<br /><br />
     *
     * * `argSeparator` `string` `Default: '&nbsp;'`<br />
     *    The separator used in PHP generated URLs to separate arguments.
     *
     * @return string
     */
    public function urlto( $url, $urlParams = null, $options=array( 'fullurl' => false, 'argSeparator' => '&amp;') )
    {
        $fullurl = false;
        $argSeparator = '&amp;';
        extract($options, EXTR_IF_EXISTS);

        if ( !is_array($urlParams) || empty($urlParams)) {
            $urlParams = null;
        }

        if ( ! $url = trim($url, '/')) {
            $url = $this->_segmentBase;
        } else {
            $url = ($this->_segmentBase ? $this->_segmentBase . '/' : '') . $url;
        }

        if ($this->_modRewriteEnabled) {
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
            if ($url) {
                $url = str_replace('%2F', '/', $url);
                $url = $this->_urlBase . $this->_fileName . '?' . $url;
            } else {
                $url = $this->_urlBase;
            }
        }
        if ($fullurl) {
            return get_domain_url() . $url;
        }

        return  $url;
    }

    /**
     * Redirect to an internal URL with HTTP 302 header sent by default
     *
     * @param string $routeuri     URL of the redirect location
     * @param bool   $exit         Where or not to end the application
     * @param code   $code         HTTP status code to be sent with the header
     * @param array  $headerBefore Headers to be sent before header("Location: some_url_address");
     * @param array  $headerAfter  Headers to be sent after header("Location: some_url_address");
     */
    public function redirect($routeuri, $exit=true, $code=303, $headerBefore=NULL, $headerAfter=NULL)
    {
        redirect($this->urlto($routeuri), $exit, $code, $headerBefore, $headerAfter);
    }
} // END class

/**
 * Route
 *
 * @TODO document
 *
 * @package Core
 */
class Route
{
    private $_routes = array();
    private $_namedRoutes = array();
    private $_defaultParams = array();
    private $_history = array();

    public function __construct($routeConfig)
    {
        if (NULL === $routeConfig) {
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
        if (NULL === $key) {
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
        $urls = App::urls();
        $queryString = implode('/', $urls->getSegments());
        foreach ($this->_routes as $pattern => $config) {
            if ( !preg_match('#^' . $pattern . '#', $queryString, $matches)) {
                continue;
            }

            // pass route parameters to $_REQUEST array
            unset($config['__id']);
            $_REQUEST = array_merge($_REQUEST, $config);
            foreach ($matches as $name => $value) {
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

/**
 * Acl
 *
 * @TODO document
 *
 * @package Core
 */
class Acl
{
    private $_rules = array();
    private $_role = 'anonymous';

    public function __construct($aclConfig, $aclRole=NULL)
    {
        if (NULL === $aclConfig) {
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

        // key 越後面的規則優先
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
            if ('404' === $rule['__failRoute']) {
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
        if ($denyPrior > $allowPrior) {
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
        if ('*' !== $rAction) {
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

/**
 * I18N
 *
 * @TODO document
 * @package Core
 */
class I18N
{
    public $translation;

    public function __construct($config=array())
    {
        $config = array_merge(array(
            'locale'  => 'zh_TW.UTF8',
            'encoding'  => 'UTF-8',
            'folder'    => 'locales' . DIRECTORY_SEPARATOR,
            'domain'    => 'default',
        ), $config);

        App::loadVendor('pomo' . DIRECTORY_SEPARATOR . 'po', false, 'common');

        $this->loadTextDomain($config);
    }
    public function loadTextDomain($config)
    {
        $this->config = $config;

        $pofile = $config['folder'] . $config['domain'] . '.' . $config['locale'] . '.po';
        if ( ! is_readable($pofile)) {
            $this->translation = new NOOP_Translations;

            return false;
        }
        $po = new PO();
        if ( ! $po->import_from_file($pofile)) {
            $this->translation = new NOOP_Translations;

            return false;
        }
        $this->translation = $po;

        return true;
    }

    public function getLocalName($withEncoding=TRUE)
    {
        if ($withEncoding) {
            return $this->config['locale'];
        }

        return current(explode('.', $this->config['locale']));
    }
} // END class

/**
 * Returns a translated string
 *
 * If the message hasn't been translated, it reutrns `$message`.
 *
 * @param string $message The messsage to be translated.
 * @return string
 */
function __($message)
{
    return $message;
}

/**
 * Returns a translated string in singular/plural format based on $number
 *
 * @param string $singular The singular string to be translated.
 * @param string $plural The plural string to be translated.
 * @param integer $number The number
 * @return string
 */
function _n($singular, $plural, $number)
{
    if ($number == 1) {
        return __($singular);
    }

    return __($plural);
}

/**
 * Returns a translated string in HTML encoded format.
 *
 * If the message hasn't been translated, it reutrns `$message`.
 *
 * @param string $message The messsage to be translated.
 * @return string
 */
function _e($message)
{
    return HtmlValueEncode(__($message));
}

/**
 * Returns an HTML encoded translated string in singular/plural format based on $number
 *
 * @param string $singular The singular string to be translated.
 * @param string $plural The plural string to be translated.
 * @param integer $number The number
 * @return string
 */
function _en($singular, $plural, $number)
{
    return HtmlValueEncode(_n($singular, $plural, $number));
}
