<?php
App::loadModel('behaviors/FormInput', FALSE, 'common');

class Configuration extends FormInputModel
{
    // public $lang;
    public $group;

    protected $_config = array(
        'primaryKey' => 'key',
        'table' => 'configuration',
        'uploadDir' => 'uploads/configuration/',

        'groups' => array(
            'basic' => array(
                'key' => 1, 'name' => NULL /* name would be set later in the __construct() */ ),
            'email' => array(
                'key' => 2, 'name' => NULL),
            'thirdparty' => array(
                'key' => 3, 'name' => NULL)
        )
    );

    public function __construct()
    {
        parent::__construct();

        $this->_config['groups']['basic']['name'] = __('基本設定');
        $this->_config['groups']['email']['name'] = __('郵件設定');
        $this->_config['groups']['thirdparty']['name'] = __('第三方服務');
    }

    protected function beforeSave(&$fields)
    {
        $this->input()->beforeSave($fields['value']);
    }

    protected function afterSave(&$fields)
    {
        $this->input()->afterSave($fields['value']);
    }

    public function selectInfo($for='edit')
    {
        switch ($for) {
            case 'edit':
                return '`group`, `key`, `title`, `value`, `input_type`, `input_options`, `input_size`, `input_attrs`, `help_text`';
            case 'load':
                return '`key`, `value`, `input_type`';
            default:
                return '';
        }
    }

    public static function updateAll(PDOStatement $list, &$input, $syncLang=FALSE)
    {
        $db = App::db();

        $errors = array();
        while ( $item = $list->fetchObject(__CLASS__)) {
            $data = array();
            if ( isset($_POST[$item->key])) {
                $data['key'] = $item->key;
                $data['value'] = $input[$item->key];
            }
            $fields = $item->fields();
            try {
                $item->save($fields, $data, TRUE);
                $syncLang && App::conf()->enable_i18n && $item->__syncLang();
            } catch ( Exception $ex ) {
                $errors[] = $ex->getMessage();
            }
        }
        if ( !empty($errors)) {
            throw new Exception(implode('<br />', $errors));
        }
    }
    private function __syncLang()
    {
        $db = App::db();

        $encLang = $db->quote($this->lang);
        $encId = $db->quote($this->key);

        $value = $db->query('SELECT `value` FROM `' . $this->_config['table'] . '` WHERE `lang` = ' . $encLang . ' AND `key` = ' . $encId)->fetchColumn();
        $sql = 'UPDATE `' . $this->_config['table'] . '` SET `value` = ' . $db->quote($value) . ' WHERE `lang` != ' . $encLang . ' AND `key` = ' . $encId;

        $db->exec($sql);
    }

    public static function loadAppConfig($groups)
    {
        $groups = (array) $groups;
        $types = DBHelper::modelConfig(__CLASS__, 'inputTypes');
        $appConf = App::conf();

        $search = new Search;
        if (App::conf()->enable_i18n) {
            $search->where[] = '`lang` = ?';
            $search->params[] = App::conf()->language;
        }
        $search->where[] = '`group` ' . DBHelper::in($groups);
        $search->orderBy = '`sort` ASC';

        $list = DBHelper::getList(__CLASS__, $search, NULL, 'load');
        while ( $item = $list->fetchObject(__CLASS__)) {
            if ('checkbox' === $types[$item->input_type]) {
                $item->value = DBHelper::splitCommaList($item->value);
            }
            $appConf->{$item->key} = $item->value;
        }
    }
}
