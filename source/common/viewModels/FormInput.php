<?php
class FormInputViewModel extends ViewModel
{
    public function bs3InputSize($adjust=0)
    {
        $item = $this->m; // FormInputModel
        switch ($item->input_size) {
            case 'mini':
                $size = 1;
                break;
            case 'small':
                $size = 2;
                break;
            case 'large':
                $size = 12;
                break;
            case 'medium':
            default:
                $size = 4;
                break;
        }
        return 'col-xs-' . min(12, max(0, ($size + $adjust)));
    }
    public function input($bootstrapVersion=3)
    {
        $item = $this->m; // FormInputModel

        $type = $item->input();
        $typeClass = get_class($type);

        $preAttrs = $postAttrs = array();

        $preAttrs['class'] = (3 == $bootstrapVersion ? 'form-control' : 'input-' . $item->input_size);
        $postAttrs['id'] = $postAttrs['name'] = $item->key;

        switch ($typeClass) {
            case 'FormInput_Text':
            case 'FormInput_Email':
            case 'FormInput_Url':
            case 'FormInput_Tel':
            case 'FormInput_Password':
                $postAttrs['type'] = strtolower(substr($typeClass, strlen('FormInput_')));
                $postAttrs['value'] = $item->value;

                echo '<input';
                $this->_inputAttrs($preAttrs, $postAttrs);
                echo ' />';
                break;

            case 'FormInput_Select':
                $conf = $type->fieldConfig();

                echo '<select';
                $this->_inputAttrs($preAttrs, $postAttrs);
                echo '>';
                html_options($conf['list'], $item->value);
                echo '</select>';
                break;

            case 'FormInput_Radio':
            case 'FormInput_Checkbox':
                $conf = $type->fieldConfig();
                $type = strtolower(substr($typeClass, strlen('FormInput_')));
                $selected = $type === 'radio' ? $item->value : DBHelper::splitCommaList($item->value);
                html_checkboxes($type, $conf['list'], $item->key, $selected, '', $breakEvery='block');
                break;

            case 'FormInput_Textarea':
                echo '<textarea';
                $this->_inputAttrs($preAttrs, $postAttrs);
                echo '>';
                echo HtmlValueEncode($item->value);
                echo '</textarea>';
                break;

            case 'FormInput_Html':
                $postAttrs['class'] = $preAttrs['class'] . ' ckeditor';
                echo '<textarea';
                $this->_inputAttrs($preAttrs, $postAttrs);
                echo '>';
                echo HtmlValueEncode($item->value);
                echo '</textarea>';
                break;

            default:
                break;

        }
    }

    private function _inputAttrs($preAttrs, $postAttrs)
    {
        $attrs = array_merge($preAttrs, $this->m->decodeInputAttrs(), $postAttrs);
        foreach ( $attrs as $name => $value) {
            echo ' ' . $name . '="' . HtmlValueEncode($value) . '"';
        }
    }

}
