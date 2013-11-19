<?php
/**
 * Core Library
 *
 * @package Core
 * @license MIT
 */


/**
 * NotFoundException
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
        if (phpversion() < 5.3) {
            parent::__construct($message, $code);
        } else {
            parent::__construct($message, $code, $previous);
        }
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
     * @param  array  $routeConfig {@see Route::__construct()} for more detail.
     * @param  array  $aclConfig {@see Acl::__construct()} for more detail.
     * @param  string $aclRole {@see Acl::__construct()} for more detail.
     * @return void
     * @see Route::__construct() Route::__construct()
     * @see Acl::__construct() Acl::__construct()
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
     * @param  array  $routeConfig {@see Route::__construct()} for more detail.
     * @return Route
     * @see Route::__construct() Route::__construct()
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
     * @param  array  $aclConfig {@see Acl::__construct()} for more detail.
     * @param  string $aclRole {@see Acl::__construct()} for more detail.
     * @return Acl
     * @see Acl::__construct() Acl::__construct()
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
     * @param string $urlBase {@see Urls::__construct()} for more detail.
     * @param string $paramName {@see Urls::__construct()} for more detail.
     * @return Urls
     * @see Urls::__construct() Urls::__construct()
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
     * @param array $config {@see I18N::__construct()} for more detail.
     * @return I18N
     * @see I18N::__construct() I18N::__construct()
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

        $this->data['content'] = $content;
        $this->data['backLink'] = $backLink;
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

        $this->_setJSONHeader();
        echo json_encode($response);
    }

    /**
     * Set http header for json response.
     *
     * @return void
     */
    public function _setJSONHeader()
    {
        header('Vary: Accept');
        if (isset($_SERVER['HTTP_ACCEPT']) &&
            (strpos($_SERVER['HTTP_ACCEPT'], 'application/json') !== false)) {
            $mime = 'application/json';
        } else {
            $mime = 'text/plain';
        }
        header('Content-Type: ' . $mime . '; charset="' . App::conf()->encoding . '"');
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
    /**
     * Can be called in either
     *
     * _handlePost([$module,] $action[, $methodName], $args)
     *
     * @return void
     */
    public function _handlePost($module, $action=NULL, $method=NULL, $args=array())
    {
        if (is_string($module)) {
            $args = $method;
            $method = $action;
            $action = $module;
            $module = NULL;
        }
        if (!isset($_POST['action']) || $action !== $_POST['action']) {
            return;
        }
        if (is_array($method)) {
            $args = $method;
        } elseif ($method) {
            $action = $method;
        }
        try {
            $method = '_handlePost' . camelize($action, true);
            $object = ($module ? $module : $this);
            if (is_array($args)) {
                call_user_func_array(array($object, $method), $args);
            }
            $object->{$method}();
        } catch (Exception $ex) {
            $this->data['error'] = $ex->getMessage();
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
    protected $_defaultAction = 'index';

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
    public function doAction()
    {
        $action = camelize(App::urls()->segment($this->_baseUrlPos+1, $this->_defaultAction), true);

        if ( !method_exists($this, 'action' . $action)) {
            throw new NotFoundException;
        }
        $this->{'action' . $action}();
    }
}

/**
 * Model
 *
 * @package Core
 *
 * @property array $rawValueFields An associative array of columns that won't be escaped in query.
 *
 * @method vold beforeVerify(array &$fields, array &$input=NULL, mixed $args=NULL) Executs before verify
 * @method vold beforeSave(array &$fields, array &$input=NULL, mixed $args=NULL) Executs before save
 * @method vold afterSave(array &$fields, array &$input=NULL, mixed $args=NULL) Executs after save
 * @method array fields($for=NULL, $search=NULL) Returns validation settings for each field. {@see Validator::verify()}
 *
 * @see Validator::verify() Validator::verify()
 */
abstract class Model
{
    /**
     * Force insertion while saving.
     *
     * @var boolean
     */
    protected $_forceInsert = FALSE;

    protected $_autoLastInsertId = TRUE;

    /**
     * Model Configuration
     *
     * * `[primaryKey]` `string` *Required* *Default: 'id'* The primary key column in the table.
     *
     * * `[table]` `string` *Required* *Default: NULL* The table name.
     *
     * * `[belongsTo]` `array` *Optional* Defines a set of relational tables.
     *   * `[{TableAlias}]` `array` A relational table.
     *      * `[model]` `string` *Required* The model name of the relational table.
     *      * `[table]` `string` *Required* The name of the relational table.
     *      * `[relKey]` `string` *Required* The column name in main table that represents **[foreignKey].
     *      * `[foreignKey]` `string` *Required* The column in the relational table to be linked.
     *
     * * `[hasMany]` `array` *Default: undefined* Defines a set of relational tables.
     *   * `[{TableAlias}]` `array` *Required* A relational table.
     *      * `[model]` `string` *Required* The model name of the relational table.
     *      * `[table]` `string` *Required* The name of the relational table.
     *      * `[relKey]` `string` *Required* The column in main table to be linked.
     *      * `[foreignKey]` `string` *Required* The column name in the relational table that represents **[relKey]**.
     *      * `[whereLink] `string` *Optional* Define custom linking sql.
     *      * `[where]` `string` *Optional* The where sql after linking sql.
     *
     * @var array
     */
    protected $_config = array(
        'primaryKey' => 'id',
        'table' => NULL
    );


    /**
     * Verifies input variables by fields settings.
     *
     * @param &array $fields The fields settings. {@see Validator::verify()}
     * @param &array $input The input data.
     * @param mixed $args Any value that would be sent to `$this->beforeVerify()`
     * @return void
     * @see Validator::verify() Validator::verify()
     */
    public function verify(&$fields, &$input=NULL, $args=NULL)
    {
        if ( method_exists($this, 'beforeVerify')) {
            $this->beforeVerify($fields, $input, $args);
        }
        Validator::verify($fields, $this, $input);
    }

    /**
     * Save input by fields settings.
     *
     * This method will firstly call verify method if need, then start saving process. Here's the execution order:
     *
     * 1. `verify()`, `beforeVerify()`, `Validator::verify()`
     * 4. `beforeSave()`
     * 5. `insert() / update()`
     * 6. `afterSave()`
     *
     * @param &array $fields The fields settings. {@see Validator::verify()}
     * @param boolean $verify Whether or not to verify the input data.
     * @param &array $input The input data. If not set, `$_POST` array would be used as $input
     * @param mixed $args Any value that would be used in the process.
     * @return void
     * @see Validator::verify() Validator::verify()
     */
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

        if ( $this->_forceInsert || ! $this->hasPrimaryKey()) {
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

    public function setForceInsert($forceInsert=TRUE)
    {
        $this->_forceInsert = $forceInsert;
    }
    /**
     * Insert a record for current model
     *
     * Insert a record with columns which are in `$fieldNames` and set models's primary key to the last inserted id.
     *
     * @param array $fieldNames Columns list that would be saved.
     * @return void
     * @see Model::$rawValueFields Model::$rawValueFields
     */
    public function insert($fieldNames)
    {
        $db = App::db();

        $params = array();
        if ( isset($this->rawValueFields)) {
            $sql = DBHelper::toInsertSql($this, $fieldNames, $params, $this->rawValueFields);
        } else {
            $sql = DBHelper::toInsertSql($this, $fieldNames, $params);
        }
        $stmt = $db->prepare('INSERT INTO `' . $this->_config['table'] . '` ' . $sql);

        DBHelper::bindArrayValue($stmt, $params);
        $stmt->execute();

        if ($this->_autoLastInsertId) {
            if ( ! is_array($this->_config['primaryKey'])) {
                $this->{$this->_config['primaryKey']} = $db->lastInsertId();

                return;
            }
            if ( in_array('id', $this->_config['primaryKey']) && ! $this->id) {
                $this->id = $db->lastInsertId();
            }
        }
    }

    /**
     * Update the record of current model
     *
     * Update a record with columns which are in `$fieldNames`.
     *
     * @param array $fieldNames Columns list that would be saved.
     * @return void
     */
    public function update($fieldNames)
    {
        $params = array();
        if ( isset($this->rawValueFields)) {
            $sql = DBHelper::toSetSql($this, $fieldNames, $params, $this->rawValueFields);
        } else {
            $sql = DBHelper::toSetSql($this, $fieldNames, $params);
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

    /**
     * Delete the record of current model.
     *
     * @return void
     */
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

    /**
     * Check if the primary key of this model has been set.
     *
     * If the primary key is an array, all of the values soulde be set, or it'll return false.
     *
     * @return boolean
     */
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
    /**
     * Get a value from model configuration.
     *
     * @param string $key The access key of the value in the model configuration.
     * @return mixed
     */
    public function getConfig($key=NULL)
    {
        if (NULL !== $key) {
            return isset($this->_config[$key]) ? $this->_config[$key] : NULL;
        }

        return $this->_config;
    }

    /**
     * Set a model configuration.
     *
     * @param string|array $props The access key of the value in the configuration, or an associative array of values to be saved to configuration.
     * @param string $values The value corresponding to the key. Only works when *$props* is a string.
     * @return void
     */
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
    /**
     * Select relational data
     *
     * @param &array $select The select array from Search object {@see Search}
     * @param array $config The [belongsTo] configuration of current Model {@see Model::$_config}
     * @param string|array $columns If is a string, the returning column name whould be set as **'{TableAlias}_{column}'** in the **$select** array. If **$column** is an associative array, all acceptable formats are listed below, each column would be set as **'{TableAlias}_{column}'** in the sql statement:
     *
     * <pre>
     * [TableAlias] => 'column'
     * [TableAlias] => array('column1', 'column2', 'column3')
     * [TableAlias] => array
     *      ['column1'] => '(subquery of column1)',
     *      ['column2'] => '(subquery of column2)',
     *      ['column3'] => '(subquery of column3)'
     * </pre>
     *
     * @return void
     * @see Search Search
     * @see Model::$_config Model::$_config
     */
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
                                $rawSelect = '`' . $rawSelect . '`';
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

    /**
     * Select info from relational table.
     *
     * `'{TableAlias}_count'` would always be set to the *$select* array.
     *
     * @param &array $select The select array from Search object {@see Search}
     * @param array $config The [hasMany] configuration of current Model {@see Model::$_config}
     * @param array $columns An associative array of subqueries, each column would be set as **'{TableAlias}_{column}'** in the sql statement.
     *
     * <pre>
     * [TableAlias] => array
     *      ['column1'] => '(subquery of column1)',
     *      ['column2'] => '(subquery of column2)',
     *      ['column3'] => '(subquery of column3)'
     * </pre>
     *
     * @return void
     * @see Search Search
     * @see Model::$_config Model::$_config
     */
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

        if ( file_exists($this->__getDir($layoutAppId) . 'functions.php')) {
            include $this->__getDir($layoutAppId) . 'functions.php';
        }
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
        if ( isset($this->__addons[$addonName])) {
            echo implode("\n", $this->__addons[$addonName]);
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
        return isset($this->__addons[$addonName]);
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
     * The column(s) for GROUP BY statement, only works on:
     *
     * * `DBHelper::getList()` when its $pager parameter hasn't been set.
     * * `DBHelper::getOne()`
     *
     * @var string
     */
    public $groupBy;

    /**
     * The column(s) for ORDER BY statement, only works on:
     *
     * * `DBHelper::getList()` when its $pager parameter hasn't been set.
     * * `DBHelper::getOne()`
     *
     * @var string
     */
    public $orderBy;

    /**
     * The number(s) for LIMIT statement, only works on:
     *
     * * `DBHelper::getList()` when its $pager parameter hasn't been set.
     *
     * @var string
     */
    public $limit;

    public $having = array();
    public $havingParams = array();

    /**
     * Returns sql WHERE string from $this->where
     *
     * @return string
     */
    public function sqlWhere()
    {
        return sizeof($this->where) ? ' WHERE ' . implode(' AND ', $this->where) : '';
    }
    public function where($field, $value=NULL)
    {
        if (!is_array($field)) {
            $this->where[] = '`' . $field . '` = ?';
            $this->params[] = $value;
        } else {
            foreach ($field as $column => $value) {
                $this->where[] = '`' . $column . '` = ?';
                $this->params[] = $value;
            }
        }
        return $this;
    }
    public function having($field, $value=NULL)
    {
        if (!is_array($field)) {
            $this->having[] = '`' . $field . '` = ?';
            $this->havingParams[] = $value;
        } else {
            foreach ($field as $column => $value) {
                $this->having[] = '`' . $column . '` = ?';
                $this->havingParams[] = $value;
            }
        }
        return $this;
    }

    public function sqlPagerInfo()
    {
        $pagerInfo = '';
        if (!empty($this->groupBy)) {
            $pagerInfo .= ' GROUP BY ' . $this->groupBy;
        }
        if (!empty($this->having)) {
            $pagerInfo .= ' HAVING ' . $this->sqlHaving();
            $params = array_merge($params, $this->havingParams);
        }
        if (!empty($this->orderBy)) {
            $pagerInfo .= ' ORDER BY ' . $this->orderBy;
        } elseif (isset($mconf['primaryKey'])) {
            $pagerInfo .= ' ORDER BY ' . self::__orderByPrimaryKey($mconf['primaryKey']);
        }
        if (!empty($this->limit)) {
            $pagerInfo .= ' LIMIT ' . $this->limit;
        }
        return $pagerInfo;
    }
}

/**
 * DBHelper
 *
 * @package Core
 */
class DBHelper
{
    // SQL generator
    /**
     * Generates sql IN statement for values
     *
     * @param array $values An array of values of the `IN` statement, which would be escaped in the returning string.
     * @param string $field The column to be prepended the returning string. e.g. `$field IN (...)`
     * @return string
     */
    public static function in($values, $field='')
    {
        if ( sizeof($values) === 0) {
            return;
        }

        return $field . ' IN (' . implode(',', array_map(array(App::db(), 'quote'), $values)) . ')';
    }

    /**
     * Generates OR where sql of columns.
     *
     * @param array $array An associative array of columns to be concatenated with OR. Each value in the array would be escaped in the returning string. Accepts:
     * <pre>
     * [column] => 'value',
     * [whatever] array     # which would be concatenated with AND statement.
     *     [column1] => 'value1',
     *     [column2] => 'value2',
     *     [column3] => 'value3'
     * </pre>
     * The example above produces:
     * <pre>
     * (
     *  `column` = 'value'
     *  OR (
     *      `column1` => 'value1'
     *      AND `column2` => 'value2'
     *      AND `column3` => 'value3'
     *     )
     * )
     * </pre>
     * @return string
     */
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

        return ' (' . implode(' OR ', $sql) . ')';
    }

    /**
     * Generates AND where sql of columns.
     *
     * @param array $array An associative array of columns to be concatenated with AND. Each value in the array would be escaped in the returning string. Example:
     * <pre>
     *  [column1] => 'value1',
     *  [column2] => 'value2',
     *  [column3] => 'value3'
     * </pre>
     * The example above produces:
     * <pre>
     * (
     *  `column1` => 'value1'
     *  AND `column2` => 'value2'
     *  AND `column3` => 'value3'
     * )
     * </pre>
     * @return string
     */
    public static function where($array)
    {
        $db = App::db();
        $sql = array();
        foreach ($array as $key => $value) {
            $sql[] = '`' . $key . '` = ' . $db->quote($value);
        }

        return implode(' AND ', $sql);
    }

    /**
     * Generates SET sql for columns.
     *
     * @param object $data Any object.
     * @param array $fields An array of column names.
     * @param &array $params A reference of the parameters array for `PDO::execute()`
     * @param array $rawValueFields An associative array of columns that won't be escaped.
     * @return string
     */
    public static function toSetSql($data, $fields, &$params, $rawValueFields=NULL)
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

    /**
     * Generates INSERT sql for columns.
     *
     * @param object $data  Any object.
     * @param array $fields An array of column names.
     * @param &array $params A reference of the parameters array for `PDO::execute()`
     * @param array $rawValueFields An associative array of columns that won't be escaped.
     * @return string
     */
    public static function toInsertSql($data, $fields, &$params, $rawValueFields=NULL)
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


    // PDO Helpers
    /**
     * Binds key-value pairs array via PDOStatement::bindValue()
     *
     * @param PDOStatement $stmt The query on which link the values
     * @param array $array An associative array containing the values to bind
     * @param array $typeArray An associative array with the desired value for its corresponding key in $array
     * @return void
     */
    public static function bindArrayValue(PDOStatement $stmt, $array, $typeArray = false)
    {
        foreach ($array as $key => $value) {
            if ($typeArray) {
                $stmt->bindValue($key, $value, (isset($typeArray[$key])?$typeArray[$key]:PDO::PARAM_STR) );
            } else {
                $stmt->bindValue($key, $value, self::PDOValueType($value));
            }
        }
    }

    /**
     * Detects the PDO value type of a specific value
     *
     * @param mixed $value Any value to be detected.
     * @return integer|false Returns a corresponding PDO constant: `PDO::PARAM_*`
     */
    public static function PDOValueType($value)
    {
        if(is_int($value)) {
            return PDO::PARAM_INT;
        }
        if(is_bool($value)) {
            return PDO::PARAM_BOOL;
        }
        if(is_null($value)) {
            return PDO::PARAM_NULL;
        }
        if(is_string($value)) {
            return PDO::PARAM_STR;
        }
        return FALSE;
    }

    /**
     * Returning an associative array in key-value pairs with the array key in specific column.
     *
     * @param PDOStatement $stmt The PDOStatement
     * @param string $keyColumn The column to be used as the index in the returning array.
     * @param null|string $displayColumn The column to be used as the value in the returning array. If not set, the data object will be used.
     * @param string $className The class name of the data object. Only works when `$displayColumn` has not been set.
     * @return array
     */
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


    // Queries
    /**
     * Check if "$field=$value" is the only record in the database.
     *
     * @param string $table The table to be checked
     * @param string $field
     * @param string $value
     * @param string|integer|array $ignoreId If has duplicated record, ignore if the record matches $ignoreId. `$ignoreId` could be string, integer or an array of where conditions. {@see DBHelper::where()}
     * @return boolean
     * @see DBHelper::where() DBHelper::where()
     */
    public static function isUniqIn($table, $field, $value, $ignoreId=NULL)
    {
        $sql = 'SELECT COUNT(*) = 0 FROM ' . $table . ' WHERE ';
        if (NULL !== $ignoreId) {
            if (!is_array($ignoreId)) {
                $sql .= ' `id` != ? AND ';
                $params[] = $ignoreId;
            } else {
                $sql .= '!(' . self::where($ignoreId) . ')';
            }
        }
        $sql .= ' `'.$field.'` = ?';
        $params[] = $value;

        $stmt = App::db()->prepare($sql);
        $stmt->execute($params);

        return (bool) $stmt->fetchColumn();
    }


    // Model Queries
    /**
     * Query the database for a list and return the PDOStatement.
     *
     * If neither `$pager` and `$search->orderBy` has been set, the result will be sorted by model's primary key.
     *
     * @param string $model The class name of the model
     * @param array|integer|string|Search $search A Search object, or an array of WHERE conditions, or the value of primary key.
     * @param Pagination $pager The Pagination instance
     * @param string $for This argument will be passed to `Model::select()` to select columns from table.
     * @return PDOStatement
     */
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
            if (empty($search->orderBy) && isset($mconf['primaryKey'])) {
                $search->_origOrderBy = $search->orderBy;
                $search->orderBy = self::__orderByPrimaryKey($mconf['primaryKey']);
            }
            $pagerInfo = $search->sqlPagerInfo();
            if (property_exists($search, '_origOrderBy')) { // restore to original orderBy value.
                $search->orderBy = $search->_origOrderBy;
                unset($search->_origOrderBy);
            }
        }

        $stmt = App::db()->prepare('SELECT ' . $model->selectInfo($for, $search) . ' FROM `' . $mconf['table'] . '` a ' . $where . $pagerInfo);
        $stmt->execute($params);

        return $stmt;
    }

    private static function __orderByPrimaryKey($primaryKey)
    {
        if (!is_array($primaryKey)) {
            return '`' . $primaryKey . '` ASC';
        }

        $sql = '';
        $hasFirst = NULL;
        foreach ($primaryKey as $field)  {
            if ($hasFirst) {
                $sql .= ',';
            } else {
                $hasFirst = TRUE;
            }
            $sql .= ' `' . $field . '` ASC';
        }
        return $sql;
    }

    /**
     * Query the database for one result and return the model instance.
     *
     * @param string $model The class name of the model
     * @param array|integer|string|Search $search A Search object or an array of WHERE conditions or the value of primary key.
     * @param string $for This argument will be passed to `Model::select()` to select columns from table.
     * @param Model $fetchIntoObject If passed, this object will be updated with the result.
     * @return PDOStatement
     */
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

    /**
     * Counts records of the specific model
     *
     * @param string $model The class name of the model
     * @param array|integer|string|Search $search A Search object or an array of WHERE conditions or the value of primary key.
     * @param string $countWith The sql inside sql count function, default is `*`, which produces `COUNT(*) as counts`
     * @return integer
     */
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

    /**
     * Delete records of the specific model
     *
     * @param string $model The class name of the model
     * @param array|Search $ids An array of primary keys or a Search object.
     * @return void
     */
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

    /**
     * Update records of the specific model
     *
     * @param string $model The class name of the model
     * @param array $data An associative array of the data to be saved.
     * @param array $ids An array of primary keys to be updated.
     * @param array $rawValueFields An associative array of columns that won't be escaped.
     * @return void
     */
    public static function updateAll($model, $data, $ids, $rawValueFields=NULL)
    {
        $model = new $model;
        $mconf = $model->getConfig();

        $params = array();

        $fields = array_keys($data);
        $data = (object) $data;
        $sql = DBHelper::toSetSql($data, $fields, $params, $rawValueFields);
        $stmt = App::db()->prepare('UPDATE `' . $mconf['table'] . '` SET ' . $sql . ' WHERE `' . $mconf['primaryKey'] . '` ' . DBHelper::in($ids));
        $stmt->execute($params);
    }


    // Model Helpers
    /**
     * Returns the field settings of a model
     *
     * @param string $model The class name of the model
     * @param string $for Will be passed to `Model::fields()` as the first argument.
     * @param Search $search Will be passed to `Model::fields()` as the second argument.
     * @return array
     */
    public static function modelFields($model, $for=NULL, $search=NULL)
    {
        $model = new $model;
        return $model->fields($for, $search);
    }

    /**
     * Get instance config
     *
     * @param string $model The class name of the model
     * @param string $key The access key of the value in the model configuration.
     * @return mixed
     */
    public static function modelConfig($model, $key=NULL)
    {
        $model = new $model;

        if (!is_array($key)) {
            return $model->getConfig($key);
        }
        $config = new stdclass;
        foreach ($key as $k) {
            $config->{$k} = $model->getConfig($k);
        }
        return $config;
    }
    /**
     * Translate search argument to sql statement.
     *
     * @param string $model The class name of the model
     * @param array|integer|string|Search $search A Search object or an array of WHERE conditions or the value of primary key.
     * @return array
     */
    protected static function translateModelSearchArg($model, $search)
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


    // Filesystem
    /**
     * Delete new files of the data
     *
     * @param stdclass $data Can be any object that has `_new_files` property, which holds file pathes to be deleted.
     * @return void
     */
    public static function clearNewFiles($data)
    {
        if ( empty($data->_new_files)) {
            return;
        }
        foreach ($data->_new_files as $file) {
            if ( '' !== (string)$file && file_exists($file)) {
                unlink($file);
            }
        }
        $data->_new_files = array();
    }

    /**
     * Fetch file names from database and then delete these files.
     *
     * @param array $options
     * <pre>
     *  [columns]  array     An array of column names which stores file pathes.
     *  [table]    string    The name of the table to be checked.
     *  [dir]      string    The directory that stores files.
     *  [where]    string    where conditions sql
     *  [params]   array optional The array of parameters to be used in `PDO::execute()`
     *  [suffixes] array optional Suffixes of files names:
     *      [column] array   An array of suffixes which appends to file name.
     * </pre>
     * @return void
     */
    public static function deleteColumnFile($options)
    {
        extract($options);

        $columns = $options['columns'];

        $sql = 'SELECT `' . implode('`,`', $columns) . '` FROM `' . $options['table'] . '` a WHERE ' . $options['where'];
        if ( empty($options['params'])) {
            $stmt = App::db()->query($sql);
        } else {
            $stmt = App::db()->prepare($sql);
            $stmt->execute($options['params']);
        }
        if ( ! $stmt->rowCount()) {
            return;
        }
        while ( $item = $stmt->fetchObject()) {
            foreach ($columns as $column) {
                if (!$item->{$column}) {
                    continue;
                }
                $delFileOpts = array(
                    'data' => $item,
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

    /**
     * Delete a set of files
     *
     * @param array $options
     * <pre>
     * [data]     object    Any object
     * [column]   string    The property that stores file name in the data object
     * [dir]      string    The directory that stores files.
     * [suffixes] array     An array of suffixes that appends to the file name.
     * </pre>
     * @return void
     */
    public static function deleteFile($options)
    {
        extract($options);

        if (! $data->{$column}) {
            return;
        }
        if ( file_exists($dir . $data->{$column})) {
            unlink($dir . $data->{$column});
        }
        if ( !empty($options['suffixes'])) {
            $info = pathinfo($data->{$column});
            if ( !isset($info['filename'])) { // < php5.2.0
                $info['filename'] = substr($info['basename'], 0, strlen($info['basename'])-strlen($info['extension'])-1);
            }

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


    // Misc
    /**
     * Converts a comma separated string into an associative array.
     *
     * @param string $string A comma separated string.
     * @return array
     */
    public static function splitCommaList($string)
    {
        if ( is_array($string)) {
            return $string;
        }
        if (NULL === $string || '' === $string) {
            return array();
        }
        $array = explode(',', $string);

        return array_combine($array, $array);
    }

} // END class


/**
 * ValidatorException
 * @package Core
 */
class ValidatorException extends Exception {}
/**
 * Validator
 *
 * @package Core
 */
class Validator
{
    /**
     * Verifies fields
     *
     * @param array $fields The fields settings, has the following structure. <br /><br />
     *
     * `[label]` string **Required** <br />
     *  - The label string of $data->{$key} <br /><br />
     *
     * `[type]` string Optional <br />
     *  - Can be on of `image`, `file`, `boolean`, `multiple`, `date` or `datetime`. <br /><br />
     *
     * `[required]` boolean Optional <br />
     *  - Validates $data->{$key} has value. <br /><br />
     *
     * `[list]` array Optional <br />
     *  - An associative array of acceptable values in value-label pairs. <br /><br />
     *
     * `[default]` string Optional <br />
     *  - The default value when $data->{$key} is blank. <br /><br /><br />
     *
     * `[callbacks]` array Optional <br />
     *  - Methods to be executed after cheking process. Methods can be defined in `$data` class, `Validator`, or as a **global function**. <br />
     *  ---- `[{MethodName}]` array - Execute method with parameters <br />
     *  -------- `[]` param1 <br />
     *  -------- `[]` param2, <br />
     *  -------- `[]` ... <br />
     *  ---- `[]` string - Execute method without parameters. <br /><br /><br />
     *
     * // =========================================<br />
     * // When `[type]`=`date` or `datetime` <br />
     * // =========================================<br />
     * `[format]` string Optional Default:`Y-m-d`'or `Y-m-d H:i:s` <br />
     *  - See PHP date format: http://php.net/manual/zh/function.date.php <br /><br /><br />
     *
     * // =========================================<br />
     * // When `[type]`=`multiple` <br />
     * // =========================================<br />
     * `[default]` array Optional <br />
     *  - An associative array of default values <br /><br /><br />
     *
     * // =========================================<br />
     * // When `[type]`=`file` <br />
     * // =========================================<br />
     * `[dir]` string **Required**<br />
     *  - Directory name that stores uploaded file. <br /><br />
     *
     * `[fileKey]` string Optional Default: `$key` <br />
     *  - Alternative file key of $_FILES (e.g: `$_FILES[$fileKey]['tmp_name']`) <br /><br />
     *
     * `[fileKeyIdx]` integer Optional Default: FALSE<br /><br />
     *
     * `[rename]` boolean|string Optional Default: `TRUE`<br />
     *  - Whether or not to rename the uploaded file, if set to `TRUE`, an auto-generated file name will be used.<br /><br />
     *
     * `[max_size]` integer Optional<br />
     *  - Restrict file size of uploaded file in bytes.<br /><br />
     *
     * `[allow_types]` array Optional<br />
     *  - Restrict the mime type of uploaded file.<br />
     *  ---- `[{extention}]` array An array of mime types<br /><br />
     *
     * `[deny_files]` array Optional Default: `php, phps, php3, php4, phtml`<br />
     *  - Reject uploaded file when its extention is listed in this option.<br /><br />
     *
     * // =========================================<br />
     * // When `[type]`=`image` <br />
     * // =========================================<br />
     * `[dir]` string **Required** <br />
     *  - Directory name that stores uploaded file. <br /><br />
     *
     * `[resize]` array **Required**<br />
     *  ---- `[0]` integer Image width in pixel<br />
     *  ---- `[1]` integer Image height in pixel<br /><br />
     *
     * `[fileKey]` string Optional Default: `$key` <br />
     *  - Alternative file key of $_FILES (e.g: `$_FILES[$fileKey]['tmp_name']`) <br /><br />
     *
     * `[max_size]` integer Optional<br />
     *  - Restrict file size of uploaded file in kilobytes.<br /><br />
     *
     * `[crop]` boolean Default: false<br />
     *  - Whether or not to crop when image dimensions exceed. Only works when `[method]` hasn't been set.<br /><br />
     *
     * `[cropOrigin]` string Default: `center`<br />
     *  - available values are: `center` or `top`<br /><br />
     *
     * `[method]` string Optional<br />
     *  - Any thumbnail method in `GdImage`. {@see GdImage} <br /><br />
     *
     * `[thumbnails]` array Optional<br />
     *  ---- `[{suffix}]` array Thumbnail options<br />
     *  -------- `[method]` string Optional<br />
     *  -------- `[crop]` boolean Default:false<br />
     *  -------- `[size]` array Same as `[resize]`<br />
     *  -------- `[cropOrigin]` string<br /><br /><br />
     *
     *
     * @param object $data Any object that would store input data later.
     * @param &array $input The input data. If not set, `$_POST` array would be used as $input
     * @throws ValidatorException if any of the fields isn't valid.
     * @see http://php.net/manual/zh/function.date.php PHP date format
     * @see GdImage GdImage
     */
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
                    $opt['type'] = NULL;
                }

                // assign input to $data object.
                switch ($opt['type']) {
                    case 'image':   // handle uploaded image
                        self::__verifySaveImage($data, $key, $opt, $input);
                        $is_empty = empty($data->{$key});
                        break;
                    case 'file':    // handle uploaded file
                        self::__verifySaveFile($data, $key, $opt, $input);
                        $is_empty = empty($data->{$key});
                        break;
                    case 'boolean':
                        $data->{$key} = (isset($input[$key]) ? (int) (boolean) $input[$key] : '0');
                        $is_empty = false;
                        break;
                    case 'multiple':
                        $data->{$key} = (isset($input[$key]) && is_array($input[$key]) ? $input[$key] : array());
                        $is_empty = empty($data->{$key});
                        break;
                    default:
                        $data->{$key} = (isset($input[$key]) ? trim($input[$key]) : '');
                        $is_empty = $data->{$key} === '';
                        break;
                }

                if (! $is_empty) {

                    if ('date' === $opt['type'] || 'datetime' === $opt['type']) {
                        self::__verifyDateTime($data, $key, $opt);
                    }
                    if ( isset($opt['pattern']) ) {
                        self::pattern($data->{$key}, $opt['pattern'],
                            '<strong>'.HtmlValueEncode($label).'</strong>: '. $opt['title']);
                    }
                    if ( isset($opt['list'])) {
                        self::__verifyList($data, $key, $opt, $input);
                    }

                } else {
                    if ( array_key_exists('default', $opt) ) {
                        $data->{$key} = $opt['default'];
                    }
                    if ( ! empty($opt['required']) ) {
                        throw new ValidatorException(sprintf(
                                _e('%s: 不能為空'),
                                '<strong>' . HtmlValueEncode($label) . '</strong>'));
                    }

                }
                self::__doCallbacks($data, $key, $opt);

            } catch ( Exception $ex ) {
                $errors[] = '<li>' . $ex->getMessage() . '</li>';
                continue;
            }
        }

        if ( !empty($errors)) {
            throw new ValidatorException('<ul>'.implode("\n", $errors).'</ul>');
        }
    }

    /**
     * Verifies $data->{$key} is a valid datetime string.
     *
     * @param object $data The data object.
     * @param string $key The access key of data object.
     * @param &array $opt Options for `$data->{$key}` {@see Validator::verify()}
     * @return void
     * @throws ValidatorException if $data->{$key} isn't a valid datetime string.
     * @see Validator::verify() Validator::verify()
     */
    private static function __verifyDateTime($data, $key, &$opt)
    {
        $label = $opt['label'];

        if ( ! ( $timestamp = strtotime($data->{$key}))) {
            throw new ValidatorException(sprintf(
                    _e('%s: 非正確的時間格式'),
                    '<strong>' . HtmlValueEncode($label) . '</strong>' ));
        }
        if ('date' === $opt['type']) {
            $format = isset($opt['format']) ? $opt['format'] : 'Y-m-d';
        } else {
            $format = isset($opt['format']) ? $opt['format'] : 'Y-m-d H:i:s';
        }
        $data->{$key} = date($format, $timestamp);
    }

    /**
     * Verifies $data->{$key} in the list.
     *
     * @param object $data The data object.
     * @param string $key The access key of data object.
     * @param &array $opt Options for $data->{$key}. {@see Validator::verify()}
     * @param &array $input The input array.
     * @return void
     * @throws ValidatorException if $data->{$key} isn't in the list.
     * @see Validator::verify() Validator::verify()
     */
    private static function __verifyList($data, $key, &$opt, &$input)
    {
        $label = $opt['label'];

        // single selection
        if (!is_array($data->{$key})) {
            if ( ! isset($opt['list'][$data->{$key}])) {
                throw new ValidatorException(sprintf(
                        _e('%s: 不在允許的清單中'),
                        '<strong>' . HtmlValueEncode($label) . '</strong>' ));
            }
            return;
        }

        // multiple selection

        // delete options not listed
        foreach ($data->{$key} as $k => $v) {
            if ( ! isset($opt['list'][$v])) {
                unset($data->{$key}[$k]);
                unset($input[$key][$k]);
            }
        }

        // check empty again after deletion.
        if ( empty($data->{$key})) {
            if ( array_key_exists('default', $opt) ) {
                $data->{$key} = $opt['default'];
            }
            if ( ! empty($opt['required'])) {
                throw new ValidatorException(sprintf(
                        _e('%s: 必須選取'),
                        '<strong>' . HtmlValueEncode($label) . '</strong>' ));
            }
        }
    }

    /**
     * Stores uploaded image and assign file name to $data->{$key}
     *
     * If there's an old image for $data->{$key}, this function will delete old image after uploading success.
     *
     * @param object $data The data object.
     * @param string $key The access key of data object.
     * @param &array $opt Options for $data->{$key}. {@see Validator::verify()}
     * @param &array $input The input array.
     * @return void
     * @throws ValidatorException when meet uploading error.
     * @see Validator::verify() Validator::verify()
     */
    private static function __verifySaveImage($data, $key, $opt, &$input)
    {
        $label = $opt['label'];

        if ( ! isset($data->_new_files)) {
            $data->_new_files = array();
        }
        App::loadHelper('GdImage', false, 'common');

        $fileKey = (!empty($opt['fileKey']) ? $opt['fileKey'] : $key);
        $fileKeyIdx = (array_key_exists('fileKeyIdx', $opt) ? $opt['fileKeyIdx'] : FALSE);

        // file upload
        $gd = new GdImage( $opt['dir'], $opt['dir'] );

        // file is not uploaded.
        if (!$gd->hasSubmitImage($fileKey, $fileKeyIdx)) {
            $input[$key] = $data->{$key};
            return;
        }

        $gd->generatedType = $gd->getUploadExtension($fileKey, $fileKeyIdx);

        // check upload error
        $gd->checkUploadError($fileKey, $fileKeyIdx);

        // validate image
        if ( ! $gd->checkImageExtension($fileKey, $fileKeyIdx)) {
            throw new ValidatorException(sprintf(
                    _e('%s: 圖片類型只能是 jpg, png, gif'),
                    '<strong>' . HtmlValueEncode($label) . '</strong>' ));
        }

        if ( ! $gd->checkImageType($fileKey, $fileKeyIdx)) {
            throw new ValidatorException(sprintf(
                    _e('%s: 圖片類型只能是 jpg, png, gif'),
                    '<strong>' . HtmlValueEncode($label) . '</strong>' ));

        }
        if ( ! $gd->checkImageSize($fileKey, $fileKeyIdx, $opt['max_size'])) {
            throw new ValidatorException(sprintf(
                    _e('%s: 圖片檔案大小不能超過 %sK'),
                    '<strong>' . HtmlValueEncode($label) . '</strong>',
                    $opt['max_size'] ));
        }

        if ( ! $gd->checkImageContent($fileKey, $fileKeyIdx)) {
            throw new ValidatorException(sprintf(
                    _e('%s: 無法解讀的圖片內容'),
                    '<strong>' . HtmlValueEncode($label) . '</strong>' ));
        }

        // upload
        if ( ! $source = $gd->uploadImage($fileKey, $fileKeyIdx)) {
            throw new ValidatorException(sprintf(
                    _e('%s: 圖片上傳失敗，請稍後再上傳一次'),
                    '<strong>' . HtmlValueEncode($label) . '</strong>'));
        }
        $data->_new_files[] = $opt['dir'] . $source;

        // delete original file.
        if ($data->{$key}) {
            DBHelper::deleteFile(array(
                'data' => $data,
                'column' => $key,
                'dir' => $gd->processPath,
                'suffixes' => (isset($opt['thumbnails']) ? array_keys($opt['thumbnails']) : array())
            ));
            $data->{$key} = $input[$key] = '';
        }

        // resize image
        $info = pathinfo($source);
        if ( !isset($info['filename'])) { // < php5.2.0
            $info['filename'] = substr($info['basename'], 0, strlen($info['basename'])-strlen($info['extension'])-1);
        }
        $filename = $info['filename'];
        unset($info);

        $method = self::__saveImage__resizeMethod($opt);
        $gd->{$method}(
            $source,
            $opt['resize'][0], $opt['resize'][1],
            $filename,
            (isset($opt['cropOrigin']) ? $opt['cropOrigin'] : NULL)
        );
        $data->{$key} = $input[$key] = $source;

        if ( isset($opt['thumbnails'])) {
            foreach ($opt['thumbnails'] as $suffix => $sOpt) {
                $sOpt = (array) $sOpt;

                $method = self::__saveImage__resizeMethod($sOpt);
                $gd->{$method}(
                    $source,
                    $sOpt['size'][0], $sOpt['size'][1],
                    $filename . $suffix,
                    (isset($sOpt['cropOrigin']) ? $sOpt['cropOrigin'] : NULL)
                );
                $data->_new_files[] = $opt['dir'] . $filename . $suffix . '.' . $gd->generatedType;
            }
        }
    }

    private static function __saveImage__resizeMethod($opt)
    {
        if (isset($opt['method'])) {
            return $opt['method'];
        }
        return (empty($opt['crop']) ? 'createThumb' : 'adaptiveResizeCropExcess');
    }
    /**
     * Stores uploaded file and assign file name to $data->{$key}
     *
     * If there's an old file for $data->{$key}, this function will delete old file after uploading success.
     *
     * @param object $data The data object.
     * @param string $key The access key of data object.
     * @param &array $opt Options for $data->{$key}. {@see Validator::verify()}
     * @param &array $input The input array.
     * @return void
     * @throws ValidatorException when meet uploading error.
     * @see Validator::verify() Validator::verify()
     */
    private static function __verifySaveFile($data, $key, $opt, &$input)
    {
        $label = $opt['label'];

        $fileKey = ! empty($opt['fileKey']) ? $opt['fileKey'] : $key;
        if ( ! isset($data->_new_files)) {
            $data->_new_files = array();
        }

        // file is not uploaded.
        if ( empty($_FILES[$fileKey]['tmp_name'])) {
            $input[$key] = $data->{$key};
            return;
        }

        App::loadHelper('Upload', false, 'common');

        $uploader = new Upload($fileKey, $opt['dir']);

        if ( isset($opt['rename'])) {
            $uploader->rename = $opt['rename'];
        }
        if ( isset($opt['max_size'])) { // bytes
            $uploader->maxSize = $opt['max_size'];
        }
        if ( isset($opt['allow_types'])) { // [ext] array mime list
            $uploader->allowTypes = $opt['allow_types'];
        }
        if ( isset($opt['deny_files'])) { // extension list
            $uploader->denyFiles = $opt['deny_files'];
        }

        try {
            $oldFile = $data->{$key};
            $newFile = $uploader->save();

            // delete old file after uploading success.
            if ( $oldFile && file_exists($opt['dir'] . $oldFile)) {
                unlink($opt['dir'] . $oldFile);
            }

            // assign new file to $data object
            $data->{$key} = $input[$key] = $newFile;
            $data->{$key.'_type'} = $input[$key.'_type'] = $uploader->getFileType();
            $data->{$key.'_orignal_name'} = $input[$key.'_orignal_name'] = $uploader->getOrignalName();

            // add to _new_files array
            if ( ! is_array($data->{$key})) {
                $data->_new_files[] = $opt['dir'] . DIRECTORY_SEPARATOR . $data->{$key};
            } else {
                foreach ($data->{$key}['successed'] as $info) {
                    $data->_new_files[] = $opt['dir'] . DIRECTORY_SEPARATOR . $info['rename'];
                }
            }
        } catch ( Exception $ex ) {
            throw new ValidatorException( '<strong>'.HtmlValueEncode($label).'</strong>: '. $ex->getMessage() );
        }
    }

    /**
     * Execute callback methods
     *
     * Execute methods defined in `$data`, Validator, or execute a global function.
     *
     * @param object $data The data object.
     * @param string $key The access key of data object.
     * @param &array $opt Options for `$data->{$key}`. {@see Validator::verify()}
     * @return void
     * @throws ValidatorException if catched Exception
     * @see Validator::verify() Validator::verify()
     */
    private static function __doCallbacks($data, $key, &$opt)
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
                if ( method_exists($data, $func)) {             // Execute $data->$func()
                    $data->{$key} = call_user_func_array(array($data, $func), $params);
                } elseif ( method_exists(__CLASS__, $func)) {   // Execute Validator::$func()
                    $data->{$key} = call_user_func_array(array(__CLASS__, $func), $params);
                } else {                                        // Execute a global function.
                    $data->{$key} = call_user_func_array($func, $params);
                }
            } catch ( Exception $ex ) {
                throw new ValidatorException( '<strong>'.HtmlValueEncode($opt['label']).'</strong>: '. $ex->getMessage() );
            }
        }
    }


    // Rules
    /**
     * Validates that $value is no empty after tag stripped.
     *
     * @param string $value The value to be checked
     * @return string $value
     * @throws ValidatorException if the value is not valid.
     */
    public static function requiredText($value)
    {
        if ( '' === trim(strip_tags($value))) {
            throw new ValidatorException(__('文字必須填寫'));
        }

        return $value;
    }

    /**
     * Validates that $value is numeric.
     *
     * @param integer $value The value to be checked
     * @return integer $value
     * @throws ValidatorException if the value is not numeric.
     */
    public function numeric($value)
    {
        if ( ! is_numeric($value)) {
            throw new ValidatorException(__('必須是數字'));
        }

        return $value;
    }

    /**
     * Validates that $value is between $min and $max
     *
     * @param integer $value The value to be checked
     * @param integer $min
     * @param integer $max
     * @return integer $value
     * @throws ValidatorException if the value is not between $min and $max.
     */
    public static function between($value, $min, $max)
    {
        $min = (int)$min;
        $max = (int)$max;

        if ( ($value < $min) || ($value > $max)) {
            throw new ValidatorException(sprintf(__('數字範圍為%s~%s'), $min, $max));
        }

        return $value;
    }

    /**
     * Validates that $value is a date string
     *
     * @param string $value The value to be checked
     * @param string $format Could be one of
     *  `dd/mm/yy`, `mm/dd/yy`, `mm/dd/yyyy`, `dd/mm/yyyy`,
     *  `yy/mm/dd`, `yyyy/mm/dd`
     * @param boolean $strictYear Check year length strictly.
     * @return string $value
     * @throws ValidatorException if the value is not a valid date string.
     */
    public static function date($value, $format='yyyy/mm/dd', $strictYear=false)
    {
        // Year 1900-01-01 through 2099-12-31
        $yearFormat = "(19|20)?[0-9]{2}";
        if ($strictYear == true) {
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
            throw new ValidatorException( sprintf(__('日期格式不正確!請使用%s格式'), HtmlValueEncode($format)));
        }

        return $value;
    }

    /**
     * Validates that $value is a datetime string
     *
     * @param string $value The value to be checked
     * @return string $value
     * @throws ValidatorException if the value is not a valid datetime string.
     */
    public static function datetime($value)
    {
        $rs = strtotime($value);

        if ($rs===false || $rs===-1) {
            throw new ValidatorException(__('時間格式不正確'));
        }

        return $value;
    }

    /**
     * Check if the value is a valid Taiwan ID number / Taiwan ARC number / Passport number
     *
     * @param string $value The value to be checked
     * @param string $identityType Could be one of `id`(Taiwan ID), `arc`(Taiwan ARC) or `passport`.
     * @return string $value
     * @throws ValidatorException if the value is not valid.
     */
    public static function idNumber($value, $identityType='id')
    {
        if ( ! self::__idNumber($value, $identityType)) {
            throw new ValidatorException(__('請輸入有效的證件編號'));
        }

        return $value;
    }

    /**
     * Check if the value is a valid Taiwan ID number / Taiwan ARC number / Passport number
     *
     * @param string $value The value to be checked
     * @param string $identityType Could be one of `id`(Taiwan ID), `arc`(Taiwan ARC) or `passport`.
     * @return boolean
     */
    private static function __idNumber($value, $identityType='id')
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

    /**
     * Check if the value is a valid hex number.
     *
     * @param string $value The value to be checked
     * @return string $value
     * @throws ValidatorException if the value is not a valid hex number.
     */
    public static function hexColor($value)
    {
        if (!preg_match("/^#(?:[0-9a-fA-F]{3}){1,2}$/", $value)) {
            throw new ValidatorException(__('請輸入 HEX 色碼如: #FFF 或 #FFFFFF'));
        }

        return $value;
    }

    /**
     * Check if the value is null or is existing in database.
     *
     * @param string $value The value to be checked
     * @param string $model The name of the model.
     * @param array|integer|string|Search A Search object or an array of WHERE conditions or the value of primary key.
     * @return string $value
     * @throws ValidatorException if the value is not valid.
     */
    public static function nullOrHasRecord($value, $model, $search=NULL)
    {
        if (NULL === $value) {
            return $value;
        }

        return self::hasRecord($value, $model, $search);
    }

    /**
     * Check if the value is existing in database.
     *
     * @param string $value The value to be checked
     * @param string $model The name of the model.
     * @param array|integer|string|Search A Search object or an array of WHERE conditions or the value of primary key.
     * @return string $value
     * @throws ValidatorException if the value is not valid.
     */
    public static function hasRecord($value, $model, $search=NULL)
    {
        if ( ! class_exists($model)) {
            App::loadModel($model);
        }

        $tmpModel = new $model;
        $mconf = $tmpModel->getConfig();
        unset($tmpModel);

        // Search primaryKey=$value
        if ( is_object($search) && $search instanceof Search) {
            array_unshift($search->where, $mconf['primaryKey'] . ' = ?');
            array_unshift($search->params, $value);
        } elseif ( is_array($search) ) {
            $search = array_merge(array($mconf['primaryKey'] => $value), $search);
        } else {
            $search = $value;
        }

        if ( ! DBHelper::count($model, $search) ) {
            throw new ValidatorException(__('資料不存在'));
        }

        return $value;
    }

    /**
     * Check if the value is duplicated.
     *
     * @param string $value The value to be checked
     * @param Model $modelObj The model instance.
     * @param string $valueField The field name of $value
     * @return string $value
     * @throws ValidatorException if the value is not valid.
     */
    public static function isUniq($value, $modelObj, $valueField, $search=NULL)
    {
        $mconf = $modelObj->getConfig();

        if (!is_object($search) || !($search instanceof Search)) {
            $search = new Search;
        }
        // Search $valueField = $value
        array_unshift($search->where, '`' . $valueField  . '` = ?');
        array_unshift($search->params, $value);

        // Search id != $modelObj.id
        if ($modelObj->hasPrimaryKey()) {
            array_unshift($search->where, $mconf['primaryKey'] . ' != ?');
            array_unshift($search->params, $modelObj->{$mconf['primaryKey']});
        }

        if ( DBHelper::count(get_class($modelObj), $search) ) {
            throw new ValidatorException(__('已有資料'));
        }

        return $value;
    }

    /**
     * Check if the value is duplicated.
     *
     * @param string $value The value to be checked
     * @param Model $modelObj The model instance.
     * @param string $valueField The field name of $value
     * @return string $value
     * @throws ValidatorException if the value is not valid.
     */
    public static function nullOrIsUniq($value, $modelObj, $valueField)
    {
        if (NULL === $value) {
            return $value;
        }

        return self::isUniq($value, $modelObj, $valueField);
    }


    /**
     * Check if the value matches pattern
     *
     * @param string $value The value to be checked
     * @param string $pattern The regex pattern
     * @param string $title Exception message when dismatch.
     * @return string $value
     * @throws ValidatorException if the value is not match to the pattern.
     */
    public static function pattern($value, $pattern, $title)
    {
        if (!preg_match($pattern, $value)) {
            throw new ValidatorException($title);
        }
        return $value;
    }
    public static function captcha($value, $sessionKey='turing_string')
    {
        if (
            !isset($_SESSION[$sessionKey]) ||
            (strtoupper($value) !== strtoupper($_SESSION[$sessionKey]))
        ) {
            throw new ValidatorException(__('驗證碼錯誤'));
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
 * @package Core
 */
class Route
{
    /**
     * Stores route config, has the following structure:
     *
     * <pre>
     *  [__defaultParams]
     *      [controller] string
     *      [action] string
     *      [format] string
     *
     *  [{uri regexp}] (Optional)
     *      [controller] string - Default controller
     *      [action] string - Default action
     *      [format] string - Default format
     *      [__id] string - Optional - The route name
     * </pre>
     * @var array
     */
    private $__routes = array();

    /**
     * Stores named route config.
     *
     * @var array
     */
    private $__namedRoutes = array();

    /**
     * Stores default parameters.
     *
     * @var array
     */
    private $__defaultParams = array();

    /**
     * Stores request history.
     *
     * @var array
     */
    private $__history = array();

    /**
     * Constructor
     *
     * @param string|array $routeConfig Can be one of the following type:<br />
     * `string` - The file name suffix of route config file, which will include `config/route.{$routeConfig}.php` and get the returning array as `$routeConfig`.<br />
     * `array` - Set `$routeConfig` directly. {@see Route::$__routes}
     * @return void
     * @see Route::$__routes Route::$__routes
     */
    public function __construct($routeConfig)
    {
        if (is_string($routeConfig)) {
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

    /**
     * Update route config
     *
     * @param array $routeConfig {@see Route::$__routes}
     * @return void
     * @see Route::$__routes Route::$__routes
     */
    public function setRoutes($routeConfig)
    {
        if ( isset($routeConfig['__defaultParams'])) {
            $this->__defaultParams = $routeConfig['__defaultParams'];
            $_REQUEST = array_merge($_REQUEST, $routeConfig['__defaultParams']);
            unset($routeConfig['__defaultParams']);
        }

        $this->__routes = $routeConfig;
        $this->__namedRoutes = self::__filterNamedRoutes($this->__routes);
    }

    /**
     * Append default route to current route config.
     *
     * The `default` route is `{{controller}}/{{action}}/{{id}}.{{format}}`
     *
     * @return void
     */
    public function appendDefaultRoute()
    {
        // @NOTE if you changed route regexp heare, you have to modify `Route::urltoId` method too.
        $pattern = '(?P<controller>[^./]+)?(/(?P<action>[^./]+)(/(?P<id>[^./]+))?)?(\.(?P<format>[^/]+))?';
        $config = array('__id' => 'default');

        $this->__routes[$pattern] = $config;

        // update named routes
        $named = self::__filterNamedRoutes(array( $pattern => $config ));
        $this->__namedRoutes['default'] = $named['default'];
    }

    /**
     * Collect routes that has named with [__id] attribute.
     *
     * @param array $routes {@see Route::$__routes}
     * @return array
     * @see Route::$__routes Route::$__routes
     */
    private static function __filterNamedRoutes($routes)
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

    /**
     * Returns named routes
     *
     * @param null|string $key `NULL` to return all named routes, or give route name to return corresponding route config.
     * @return null|array
     */
    public function getNamedRoutes($key=NULL)
    {
        if (NULL === $key) {
            return $this->__namedRoutes;
        }

        return (!isset($this->__namedRoutes[$key]) ? NULL : $this->__namedRoutes[$key]);
    }

    /**
     * Return value stored in $this->__routes['__defaultParams']
     *
     * @param string $key The value key in `__defaultParams` array. e.g. `controller`, `action` or `format`
     * @return void
     */
    public function getDefault($key)
    {
        return (!isset($this->__defaultParams[$key]) ? NULL : $this->__defaultParams[$key]);
    }

    /**
     * Parse current request uri and store the variables corresponding to the pattern to $_REQUEST.
     *
     * @return void
     */
    public function parse()
    {
        $urls = App::urls();
        $queryString = implode('/', $urls->getSegments());
        foreach ($this->__routes as $pattern => $config) {
            if ( !preg_match('#^' . $pattern . '#', $queryString, $matches)) {
                continue;
            }

            // pass route parameters to $_REQUEST array
            unset($config['__id']);
            $_REQUEST = array_merge($_REQUEST, $config);
            foreach ($matches as $name => $value) {
                if ( is_string($name)) {
                    if ('' === $value && isset($this->__defaultParams[$name])) {
                        $_REQUEST[$name] = $this->__defaultParams[$name];
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

    /**
     * Push current request to history and set the new action as current.
     *
     * @param string $controller The new controller name
     * @param string $action The new action name
     * @return void
     */
    public function addHistory($controller, $action)
    {
        $this->__history[] = array(
            'controller' => $_REQUEST['controller'],
            'action' => $_REQUEST['action']
        );
        $_REQUEST['controller'] = $controller;
        $_REQUEST['action'] = $action;
    }

    /**
     * Push current request to history and execute acl checking for the new action then execute the action.
     *
     * @param string $controller The new controller name
     * @param string $action The new action name
     * @return void
     */
    public function forwardTo($controller, $action)
    {
        $this->addHistory($controller, $action);
        App::acl()->check();
        App::doAction($_REQUEST['controller'], $_REQUEST['action']);
    }

    /**
     * Get an entry from history
     *
     * @param string $offset Can be either `first` or `last`.
     * @return array
     */
    public function getHistory($offset=null)
    {
        if ('last' === $offset) {
            $length = sizeof($this->__history);

            return !$length ? array() : $this->__history[$length-1];
        }
        if ('first' === $offset) {
            return !isset($this->__history[0]) ? array() : $this->__history[0];
        }

        return $this->__history;
    }
} // END class

/**
 * Access control
 *
 * @package Core
 */
class Acl
{
    /**
     * Stores ACL config, has the following structure:
     *
     * <pre>
     *  [__default]
     *      [allow] string|array The rule can be '*', '{controller}/*' or '{controller}/{action}', or using an array of rules.
     *      [deny] string|array The rule can be '*', '{controller}/*' or '{controller}/{action}', or using an array of rules.
     *      [__failRoute] string Can be '404', '{controller}' or '{controller}/{action}'.
     *
     *  [{role}] (Rules for specific role)
     *      [allow] string|array
     *      [deny] string|array
     *      [__failRoute] string
     *
     *  [{role}] (The accessible action should be in allowing list, otherwise it shouldn't be in deny list.) {@see Acl::__isAccessible()}
     *      [deny] '*'
     *      [allow] array(...)
     *      [__failRoute] string
     *
     *  [{role}] (The accessible action shouldn't be in deny list and should be in allowing list.) {@see Acl::__isAccessible()}
     *      [allow] '*'
     *      [deny] array(...)
     *      [__failRoute] string
     * </pre>
     * @var array
     * @see Acl::__isAccessible() Acl::__isAccessible()
     */
    private $__rules = array();

    /**
     * Stores the role.
     *
     * @var string
     */
    private $__role = 'anonymous';

    /**
     * Constructor
     *
     * @param string|array $aclConfig Can be one of the following type:<br />
     * `string` - The file name suffix of acl config file, which will include `config/acl.{$aclConfig}.php` and get the returning array as `$aclConfig`.<br />
     * `array` - Set `$aclConfig` directly. {@see Acl::$__rules}
     * @param string The role.
     * @return void
     * @see Acl::$__rules Acl::$__rules
     */
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

        $this->__rules = $aclConfig;
        $this->__role = $aclRole;
    }

    /**
     * Get rules of specific role.
     *
     * @param string $role The role name.
     * @return array
     */
    private function __getRoleRules($role)
    {
        $defaultRule = !isset($this->__rules['__default']) ? array() : $this->__rules['__default'];
        $roleRules = $this->__rules[$role];

        // key 越後面的規則優先
        foreach ($roleRules as $key => $value) {
            if ( isset($defaultRule[$key])) {
                unset($defaultRule[$key]);
            }
        }

        return array_merge($defaultRule, $roleRules);
    }

    /**
     * Check if current may access current requested action.
     *
     * If not accessible, redirect current user to `$roleRules['__failRoute']`.
     *
     * @return void
     * @throws NotFoundException if current request is not accessible and `$roleRules['__failRoute']` is equal to `'404'`
     * @see Acl::$__rules Acl::$__rules
     */
    public function check()
    {
        $roleRules = $this->__getRoleRules($this->__role);

        if ( ! $this->__isAccessible($roleRules)) {
            if ('404' === $roleRules['__failRoute']) {
                throw new NotFoundException(_e('頁面不存在'));
            }

            if ( false === strpos($roleRules['__failRoute'], '/')) {
                $controller = $roleRules['__failRoute'];
                $action = App::route()->getDefault('action');
            } else {
                $route = explode('/', $roleRules['__failRoute']);
                $controller = $route[0];
                $action = $route[1];
            }
            App::route()->addHistory($controller, $action);
        }
    }

    /**
     * Check if current request is accessible by the $roleRules
     *
     * @param array $roleRules Rules of the specific role. {@see Acl::$__rules}
     * @return boolean
     * @see Acl::$__rules Acl::$__rules
     */
    private function __isAccessible($roleRules)
    {
        $keys = array_keys($roleRules);
        $allowPrior = array_search('allow', $keys);
        $denyPrior = array_search('deny', $keys);
        unset($keys);

        $path = $_REQUEST['controller'] . '/' . $_REQUEST['action'];


        // allow first
        if ($denyPrior > $allowPrior) {
            // The accessible action shouldn't be in deny list and should be in allowing list.
            return ( !$this->__inList($path, $roleRules['deny']) && $this->__inList($path, $roleRules['allow']) );
        }

        // deny first
        // The accessible action should be in allowing list or not in deny list.
        return ( $this->__inList($path, $roleRules['allow']) || !$this->__inList($path, $roleRules['deny']));
    }
    /**
     * Check if $path matches any one of $rules.
     *
     * @param string $path The path to be check.
     * @param array $rules The rules
     * @return boolean
     */
    private function __inList($path, $rules)
    {
        if ( is_string($rules)) {
            return $this->__matchRule($path, $rules);
        }

        if ( is_array($rules)) {
            foreach ($rules as $rule) {
                if ( $this->__matchRule($path, $rule)) {
                    return true;
                }
            }
        }

        return false;
    }

    /**
     * Check if $path matches $rule
     *
     * @param string $path The path to be check.
     * @param string $rule The rule, can be `*`, `{controller}/*` or `{controller}/{action}`.
     * @return boolean
     */
    private function __matchRule($path, $rule)
    {
        // $rule = '*'
        if ('*' === $rule) {
            return true;
        }

        // $rule = '{controller}'
        if ( false === strpos($rule, '/')) {
            return ($path === $rule);
        }

        // $rule = '{controller}/{action}'
        list($rController, $rAction) = explode('/', $rule);
        if ('*' !== $rAction) {
            return ($path === $rule);
        }

        // $rule = '{controller}/*'
        list($controller) = explode('/', $path);

        return ($controller === $rController);
    }

    /**
     * Get current role
     *
     * @return string
     */
    public function getRole()
    {
        return $this->__role;
    }
    /**
     * Set current role
     *
     * @param string $role The role name.
     * @return void
     */
    public function setRole($role)
    {
        $this->__role = $role;
    }

} // END class

/**
 * I18N
 *
 * @package Core
 */
class I18N
{
    /**
     * Stores Translations instance.
     *
     * @var Translations|NOOP_Translations
     */
    private $__translation;

    /**
     * Constructor
     *
     * @param array $config I18N configuration, defaults are:<br /><br />
     *
     * `[locale]` `string` (Default: 'zh_TW.UTF8')<br />
     * `[encoding]` `string` (Default: 'UTF-8')<br />
     * `[folder]` `string` (Default: 'locales' . DIRECTORY_SEPARATOR) // relative to ROOT_PATH<br />
     * `[domain]` `string` (Default: 'default')<br /><br />
     *
     * According to the configuration, the PO File Path will be `{ROOT_PATH}{folder}/{domain}.{locale}.po`
     *
     * @return void
     */
    public function __construct($config=array())
    {
        $config = array_merge(array(
            'locale'  => 'zh_TW.UTF8',
            'encoding'  => 'UTF-8',
            'folder'    => 'locales' . DIRECTORY_SEPARATOR,
            'domain'    => 'default',
        ), $config);

        App::loadVendor('beata/pomo' . DIRECTORY_SEPARATOR . 'po', false, 'common');

        $this->loadTextDomain($config);
    }

    /**
     * Load another PO File
     *
     * @param array $config {@see I18N::__construct()}
     * @return void
     * @see I18N::__construct() I18N::__construct()
     */
    public function loadTextDomain($config)
    {
        $this->config = $config;

        $pofile = $config['folder'] . $config['domain'] . '.' . $config['locale'] . '.po';
        if ( ! is_readable($pofile)) {
            $this->__translation = new NOOP_Translations;

            return false;
        }
        $po = new PO();
        if ( ! $po->import_from_file($pofile)) {
            $this->__translation = new NOOP_Translations;

            return false;
        }
        $this->__translation = $po;

        return true;
    }

    /**
     * Returns current locale name
     *
     * @param boolean $withEncoding If this parameter is set to true, the returning string will include encoding, such as `zh_TW.UTF8`. Set to false if you want to get locale string without encoding (like `zh_TW`).
     * @return string
     */
    public function getLocalName($withEncoding=TRUE)
    {
        if ($withEncoding) {
            return $this->config['locale'];
        }

        return current(explode('.', $this->config['locale']));
    }

    /**
     * Return current Translations instance.
     *
     * @return Translations|NOOP_Translations
     */
    public function getTranslation()
    {
        return $this->__translation;
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
    if (!App::conf()->enable_i18n) {
        return $message;
    }
    return App::i18n()->getTranslation()->translate($message);
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
