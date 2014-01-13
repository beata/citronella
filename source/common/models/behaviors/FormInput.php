<?php
abstract class FormInputModel extends Model
{
    public $key;
    public $title;
    public $value;
    public $input_type;
    public $input_options;
    public $input_size;
    public $input_attrs;
    public $help_text;
    public $sort;

    /**
     * @var FormInput Stores FormInput instance
     */
    private $_input;

    public function __construct()
    {
        $this->_config['inputTypes'] = array(
            0  => 'none',
            11 => 'text',
            12 => 'email',
            13 => 'url',
            14 => 'tel',
            21 => 'select',
            22 => 'radio',
            23 => 'checkbox',
            31 => 'textarea',
            32 => 'html',
        );
    }

    public function decodeInputOptions()
    {
        if (empty($this->input_options_array)) {
            $this->input_options_array = empty($this->input_options) ? array() : json_decode($this->input_options, true);
        }

        return $this->input_options_array;
    }

    public function fields()
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

    public function input()
    {
        if (NULL === $this->_input) {
            $class = 'FormInput_' . camelize($this->_config['inputTypes'][$this->input_type], true);
            $this->_input = new $class($this);
        }

        return $this->_input;
    }
    public function getLabel()
    {
        return $this->title;
    }
}
/**
 * 各類型的表單輸入定義
 */
abstract class FormInput
{
    protected $m;

    public function __construct(FormInputModel $model)
    {
        $this->m = $model;
    }
    public function fieldConfig() { return array(); }
    public function beforeSave(&$field) {}
    public function afterSave(&$field) {}
    public function getValueText()
    {
        return $this->m->value;
    }
}

class FormInput_None extends FormInput {}
class FormInput_Text extends FormInput {}
class FormInput_Email extends FormInput {}
class FormInput_Url extends FormInput {}
class FormInput_Tel extends FormInput {}
class FormInput_Select extends FormInput
{
    public function fieldConfig()
    {
        return array(
            'list' => $this->m->decodeInputOptions()
        );
    }
    public function getValueText()
    {
        $list = $this->m->decodeInputOptions();
        return array_get_value($list, $this->m->value);
    }
}

class FormInput_Radio extends FormInput
{
    public function fieldConfig()
    {
        return array(
            'list' => $this->m->decodeInputOptions()
        );
    }
    public function getValueText()
    {
        $list = $this->m->decodeInputOptions();
        return array_get_value($list, $this->m->value);
    }
}

class FormInput_Checkbox extends FormInput
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

    public function getValueText()
    {
        $list = $this->m->decodeInputOptions();
        $values = explode(',', $this->m->value);
        $return = array();
        foreach ($values as $value) {
            if (isset($list[$value])) {
                $return[] = $list[$value];
            }
        }
        return implode(', ', $return);
    }
}

class FormInput_Textarea extends FormInput {}
class FormInput_Html extends FormInput
{
    public function fieldConfig()
    {
        return array(
            'callbacks' => array('HtmlClean' => array(App::$id))
        );
    }
}
