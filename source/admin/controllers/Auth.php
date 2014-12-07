<?php
class AuthController extends AdminBaseController
{
    public function login()
    {
        $data = new stdclass;
        $data->account = $data->password = null;

        if ( ! empty($_POST)) {
            try {
                if (
                    ! isset($_POST['account']) || ( $_POST['account'] = trim($_POST['account']) ) === '' ||
                    ! isset($_POST['password']) || ( $_POST['password'] = trim($_POST['password']) ) === ''
                ) {
                    throw new Exception(__('Login account and password are both requried.'));
                }
                $data->account = $_POST['account'];


                App::loadModel('Admin');

                $where = array(
                    'account' => $data->account,
                    'password' => Admin::encodePassword($_POST['password']),
                );
                if ( ! $admin = DBHelper::getOne('Admin', $where, 'auth')) {
                    throw new Exception(__('Incorrect account or password.'));
                }
                if ( ! $admin->status ) {
                    throw new Exception(__('Your account is currently locked. Please contact administrator for assistance.'));
                }
                if ( ! $admin->has_privilege ) {
                    throw new Exception(__("Your account is forbid to access administration panel."));
                }

                $_SESSION['admin'] = $admin->toSessionObject();

                $admin->rawValueFields = array('last_login' => 'NOW()');
                $admin->update(array());

                // login redirection
                $history = App::route()->getHistory('last');
                if ( empty($history)) {
                    App::urls()->redirect('');
                } else {
                    redirect($_SERVER['REQUEST_URI']);
                }

            } catch ( Exception $ex ) {
                $this->data['error'] = $ex->getMessage();
            }
        }

        $this->_prepareLayout('single');
        $this->data['data'] = $data;
        $this->_setTitle(__('Login'));
        App::view()->render('auth_login', $this->data);
    }
    public function logout()
    {
        if ( isset($_SESSION['admin'])) {
            session_destroy();
        }
        App::urls()->redirect('');
    }
    public function resetPasswordAuth()
    {
        $data = new stdclass;
        $data->account = $data->email = null;
        if ( ! empty($_POST)) {
            try {
                if (
                    ! isset($_POST['account']) || ( $_POST['account'] = trim($_POST['account']) ) === '' ||
                    ! isset($_POST['email']) || ( $_POST['email'] = trim($_POST['email']) ) === ''
                ) {
                    throw new Exception(__('Please enter your login account and email.'));
                }
                $data->account = $_POST['account'];
                $data->email   = $_POST['email'];

                App::loadModel('Admin');

                $where = array(
                    'account' => $data->account,
                    'email' => $data->email,
                );
                if ( ! $admin = DBHelper::getOne('Admin', $where, 'auth')) {
                    throw new Exception(__("The account doesn't exist."));
                }
                if ( ! $admin->status ) {
                    throw new Exception(__('Your account is currently locked. Please contact administrator for assistance.'));
                }
                $_SESSION['tmpUser'] = $admin->toSessionObject();

                App::urls()->redirect('auth/reset-password');
            } catch ( Exception $ex ) {
                $this->data['error'] = $ex->getMessage();
            }
        }
        $this->_prepareLayout('single');
        $this->data['data'] = $data;
        $this->_setTitle(__('Reset Password'));
        App::view()->render('auth_reset_password_auth', $this->data);
    }
    public function resetPassword()
    {
        if ( ! isset($_SESSION['tmpUser'])) {
            App::urls()->redirect('auth/reset-password-auth');
        }

        if ( ! empty($_POST)) {
            try {
                if ( ! isset($_POST['password']) || ( $_POST['password'] = trim($_POST['password']) ) === '' ) {
                    throw new Exception(__('Please set your new password.'));
                }
                App::loadModel('Admin');
                $admin = new Admin;
                $admin->id = $_SESSION['tmpUser']->id;
                $admin->account = $_SESSION['tmpUser']->account;
                $admin->password = Admin::encodePassword($_POST['password']);
                $admin->update(array('password'));
                unset($_SESSION['tmpUser']);

                $_SESSION['fresh'] = __('You can now login with your new password.');
                App::urls()->redirect('auth/login');
            } catch ( Exception $ex ) {
                $this->data['error'] = $ex->getMessage();
            }
        }

        $this->data['disableCaptcha'] = TRUE;
        $this->_prepareLayout('single');
        $this->_setTitle(__('Reset Password'));
        App::view()->render('auth_reset_password', $this->data);
    }
}
