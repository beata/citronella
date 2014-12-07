<?php
class HomeController extends AdminBaseController
{
    protected $_baseUrl = 'home';

    public function index()
    {
        $this->_prepareLayout();
        App::view()->render('home', $this->data);
    }
}
