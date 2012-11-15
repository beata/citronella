<?php

require __DIR__ . DIRECTORY_SEPARATOR . 'config' . DIRECTORY_SEPARATOR . 'config.php';

require SYS_PATH . 'functions.php';
require SYS_PATH . 'core.php';

class FrontBaseController extends BaseController
{
    public function _prepareLayout($type=NULL)
    {
        parent::_prepareLayout($type);

        if ( 'simple' !== $type) {
            $this->data['menu'] = user_menu();
        }
    }
}

function user_menu()
{
    static $menu = NULL;

    if ( $menu === NULL) {
        $menu = array(
            'menu_path' => 'Menu Name',
        );
    }
    return $menu;
}

session_start();

App::$id = 'frontend';

// init urls
App::urls( 'primary', ROOT_URL, 'q');

// route
try {
    App::run(array(
        '__defaultParams' => array(
            'controller' => 'home',
            'action' => 'index',
            'format' => 'html',
        )
    ), array(
        'anonymous' => array(
            'allow' => '*',
            'deny' => array(),
            '__failRoute' => '404',
        )
    ), 'anonymous');
} catch ( NotFoundException $ex ) {
    $controller = new FrontBaseController();
    $controller->_show404(true);
}
