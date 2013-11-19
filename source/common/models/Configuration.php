<?php
class Configuration extends Model
{
    public $lang;
    public $group;
    public $id;
    public $title;
    public $value;
    public $input_type;
    public $input_options;
    public $input_class;
    public $input_attrs;
    public $help_text;
    public $sort;

    private $_input;

    protected $_config = array(
        'primaryKey' => array('lang', 'id'),
        'table' => 'tb_configuration',
        'uploadDir' => 'uploads/configuration/',

        'groups' => array(
            'basic' => array(
                'key' => 1, 'name' => NULL /* name would be set later in the __construct() */ ),
            'email' => array(
                'key' => 2, 'name' => NULL),
            'service' => array(
                'key' => 3, 'name' => NULL),
            'join' => array(
                'key' => 5, 'name' => NULL),
            'thirdparty' => array(
                'key' => 4, 'name' => NULL)
        ),

        'inputTypes' => array(
            0  => 'none',
            11 => 'text',
            12 => 'email',
            13 => 'url',
            21 => 'select',
            22 => 'radio',
            23 => 'checkbox',
            31 => 'textarea',
            32 => 'html',
        ),
    );

    public function __construct()
    {
        $this->_config['groups']['basic']['name'] = __('基本設定');
        $this->_config['groups']['email']['name'] = __('郵件設定');
        $this->_config['groups']['service']['name'] = __('服務中心頁');
        $this->_config['groups']['join']['name'] = __('加入聯盟頁');
        $this->_config['groups']['thirdparty']['name'] = __('第三方服務');
    }

    public function fields($for='manage')
    {
        $attrs = $this->decodeInputAttrs();

        $fields = array(
            'value' => array_merge(array(
                'label' => $this->getLabel(),
                'required' => !empty($attrs['required']),
            ), $this->input()->fieldConfig()),
        );

        return $fields;
    }

    public function decodeInputAttrs()
    {
        if (empty($this->input_attrs_array)) {
            $this->input_attrs_array = empty($this->input_attrs) ? array() : json_decode($this->input_attrs, true);
        }

        return $this->input_attrs_array;
    }

    public function decodeInputOptions()
    {
        if (empty($this->input_options_array)) {
            $this->input_options_array = empty($this->input_options) ? array() : json_decode($this->input_options, true);
        }

        return $this->input_options_array;
    }

    public function getLabel()
    {
        return $this->title;
    }

    public function beforeSave(&$fields)
    {
        $this->input()->beforeSave($fields['value']);
    }

    public function afterSave(&$fields)
    {
        $this->input()->afterSave($fields['value']);
    }

    public function input()
    {
        if (NULL === $this->_input) {
            $class = 'Configuration_Input_' . camelize($this->_config['inputTypes'][$this->input_type], true);
            $this->_input = new $class($this);
        }

        return $this->_input;
    }

    public function selectInfo($for='edit')
    {
        switch ($for) {
            case 'edit':
                return '`lang`, `group`, `id`, `title`, `value`, `input_type`, `input_options`, `input_class`, `input_attrs`, `help_text`';
            case 'load':
                return '`id`, `value`, `input_type`';
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
            if ( isset($_POST[$item->id])) {
                $data['id'] = $item->id;
                $data['value'] = $input[$item->id];
            }
            $fields = $item->fields();
            try {
                $item->save($fields, true, $data);
                $syncLang && $item->__syncLang();
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
        $encId = $db->quote($this->id);

        $value = $db->query('SELECT `value` FROM `' . $this->_config['table'] . '` WHERE `lang` = ' . $encLang . ' AND `id` = ' . $encId)->fetchColumn();
        $sql = 'UPDATE `' . $this->_config['table'] . '` SET `value` = ' . $db->quote($value) . ' WHERE `lang` != ' . $encLang . ' AND `id` = ' . $encId;

        $db->exec($sql);
    }

    public static function loadAppConfig($groups)
    {
        $groups = (array) $groups;

        $fake = new self;
        $types = $fake->getConfig('inputTypes');
        unset($fake);
        $appConf = App::conf();

        $search = new Search;
        $search->where[] = '`lang` = ?';
        $search->where[] = '`group` ' . DBHelper::in($groups);
        $search->params[] = App::conf()->language;
        $search->orderBy = '`sort` ASC';

        $list = DBHelper::getList(__CLASS__, $search, NULL, 'load');
        while ( $item = $list->fetchObject(__CLASS__)) {
            if ('checkbox' === $types[$item->input_type]) {
                $item->value = DBHelper::splitCommaList($item->value);
            }
            $appConf->{$item->id} = $item->value;
        }
    }
}

abstract class Configuration_Input
{
    protected $m;

    public function __construct(Configuration $model)
    {
        $this->m = $model;
    }
    public function fieldConfig() { return array(); }
    public function beforeSave(&$field) {}
    public function afterSave(&$field) {}
}

class Configuration_Input_None extends Configuration_Input {}
class Configuration_Input_Text extends Configuration_Input {}
class Configuration_Input_Email extends Configuration_Input {}
class Configuration_Input_Url extends Configuration_Input {}
class Configuration_Input_Select extends Configuration_Input
{
    public function fieldConfig()
    {
        return array(
            'list' => $this->m->decodeInputOptions()
        );
    }
}

class Configuration_Input_Radio extends Configuration_Input
{
    public function fieldConfig()
    {
        return array(
            'list' => $this->m->decodeInputOptions()
        );
    }
}

class Configuration_Input_Checkbox extends Configuration_Input
{
    public function fieldConfig()
    {
        return array(
            'list' => $this->m->decodeInputOptions(),
            'multiple' => true
        );
    }

    public function beforeSave(&$field)
    {
        $this->m->value = implode(',', $this->m->value);
    }

}

class Configuration_Input_Textarea extends Configuration_Input {}
class Configuration_Input_Html extends Configuration_Input
{
    public function fieldConfig()
    {
        return array(
            'callbacks' => array('HtmlClean' => array('admin'))
        );
    }
}
