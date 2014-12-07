<?php
class AdminController extends AdminBaseController
{
    protected $_baseUrl = 'admin';

    public function index()
    {
        $this->_doSelectedAction();

        App::loadModel('Admin');
        App::loadHelper('Pagination', FALSE, 'common');

        $search = new Search;
        $search->status = '-1';
        $search->by = NULL;
        $search->keyword = NULL;
        $search->byList = array(
            'account' => __('Account'),
            'name'    => __('Name'),
            'email'   => __('E-mail'),
        );
        $search->statusList = array('-1' => __('All')) + Admin::getStatusList();

        if ( isset($_GET['status']) && isset($search->statusList[$_GET['status']]) && '-1' !== $_GET['status']) {
            $search->where('status', $_GET['status']);
            $search->status = $_GET['status'];
        }

        $keyword = (isset($_GET['keyword']) ? ($_GET['keyword'] = rtrim($_GET['keyword'])) : NULL);
        if ( isset($_GET['by']) && isset($search->byList[$_GET['by']]) && $keyword !== '') {
            $search->by = $_GET['by'];
            $search->keyword = $keyword;
            $search->where[] = "({$search->by} LIKE ? OR {$search->by} LIKE ?)";
            $search->params[] = $search->keyword . '%';
            $search->params[] = '%' . $search->keyword . '%';
        }

        $pager = new Pagination(array(
            'totalRows' => DBHelper::count('Admin', $search),
            'sortField' => 'account',
            'sortable'  => array(
                'account'    => __('Account'),
                'name'       => __('Name'),
                'email'      => __('E-mail'),
                'last_login' => __('Last Login'),
                'join_time'  => __('Registration Time'),
                'status'     => __('Status'),
            ),
        ));

        $list = DBHelper::getList('Admin', $search, $pager);
        $backUrl = $_SERVER['REQUEST_URI'];
        $model = 'Admin';

        $this->data = array_merge($this->data,  compact('search', 'pager', 'list', 'backUrl', 'model') );

        $this->_prepareLayout();
        App::view()->render('admin_list', $this->data);
    }
    public function create()
    {
        App::loadModel('Admin');

        $data = Admin::create();

        $fields = $data->fields();
        $this->_handlePost('save', array($data, &$fields, &$_POST, __('Account added successfully.')));
        $this->data = array_merge($this->data, array(
            'data' => $data,
            'fields' => $fields,
            'menu_list' => Admin::menuList(),
            'breadcrumbs' => $this->_breadcrumbs(array($this->_baseUrl . '/create' => __('New')))
        ));

        $this->_prepareLayout();
        $this->_appendTitle(__('New Account'));
        App::view()->render('admin_form', $this->data);
    }
    public function edit()
    {
        App::loadModel('Admin');

        if (
            ! ($id = App::urls()->segment(2)) ||
            ! ($data = DBHelper::getOne('Admin', $id, 'detail'))
        ) {
            return $this->_show404(true);
        }
        $data->fetchPermissions();

        $fields = $data->fields();
        $backUrl = (isset($_GET['backUrl']) ? $_GET['backUrl'] : NULL);
        $this->_handlePost('save', array($data, &$fields, &$_POST, __('Account updated successfully.'), $backUrl));

        $this->data = array_merge($this->data, array(
            'data' => $data,
            'fields' => $fields,
            'menu_list' => Admin::menuList(),
            'breadcrumbs' => $this->_breadcrumbs(array($this->_baseUrl . '/edit/' . $data->account  => mb_strimwidth($data->name, 0 , 20, TRIM_MARKER)))
        ));

        $this->_prepareLayout();
        $this->_appendTitle(__('Edit Account'));
        App::view()->render('admin_form', $this->data);
    }
    protected function _handlePostSave($data, &$fields, &$input, $message, $backUrl=NULL)
    {
        $data->save($fields, $input);
        $_SESSION['fresh'] = $message;
        if ($backUrl) {
            redirect($backUrl);
        }
        App::urls()->redirect($this->_baseUrl);
    }
    public function checkAccount()
    {
        if ( empty($_GET['account'])) {
            exit(json_encode(__('Parameter Error!')));
        }
        App::loadModel('Admin');
        $admin = new Admin;
        $admin->account = $_GET['account'];
        try {
            $admin->checkAccount($admin->account);
            $fields = $admin->fields();
            Validator::pattern($admin->account, $fields['account']['pattern'], $fields['account']['title']);
            exit(json_encode(true));
        } catch ( Exception $ex ) {
            exit(json_encode($ex->getMessage()));
        }
    }
    protected function _selectedDelete()
    {
        $ids = $_POST['select'];

        if ( ! empty($ids)) {
            App::loadModel('Admin');

            $db = App::db();
            $db->beginTransaction();
            try {
                Admin::deleteAll($ids);
                $db->commit();
            } catch ( Exception $ex ) {
                $db->rollback();
                throw $ex;
            }

            $_SESSION['fresh'] = __('Account deleted successfully!');
        }
    }
    protected function _selectedChangeStatus()
    {
        App::loadModel('Admin');

        if ( !isset($_POST['status']) || !Admin::getStatusList($_POST['status'])) {
            throw new Exception(__('Status undefined.'));
        }

        DBHelper::updateAll('Admin', array('status' => (int)$_POST['status']), $_POST['select']);

        $_SESSION['fresh'] = __('Status updated successfully.');
    }
}
