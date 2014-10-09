<?php
interface IAttachemntsRead
{
    public function getAttachmentClass();
    public function attachments();
}
abstract class Attachment extends Model
{
    /*
    public $rel_id;
    public ${prefix}id;
    public ${prefix}title; // title
    public ${prefix}mime; // mime
    public ${prefix}file; // file
    public ${prefix}position;
     */

    protected $_config = array(
        'model' => 'Attachment',
        'primaryKey' => 'id',
        'table' => 'files',
        'uploadDir' => 'uploads/files/',
        'prefix' => NULL,

        'belongsTo' => array(
            'parent' => array(
                'model' => 'Doc',
                'table' => 'docs',
                'foreignKey' => 'id', // docs.id
                'relKey' => 'doc_id', // files.doc_id
            )
        )
    );

    public function fields()
    {
        $prefix = $this->_config['prefix'];

        $fields = array(
            $prefix . 'title' => array(
                'label' => __('File Title'), 'required' => false,
            ),
            $prefix . 'position' => array(
                'label' => __('Position'), 'required' => false,
                'callbacks' => array('intval')
            ),
        );
        if (property_exists($this, $prefix . 'mime')) {
            $fields[$prefix . 'mime'] = array(
                'label' => __('File Type'), 'required' => false,
            );
        }

        return $fields;
    }

    public function getFileUrl($suffix=NULL)
    {
        $prefix = $this->_config['prefix'];
        $dir = ROOT_URL . $this->_config['uploadDir'];
        return get_file_path($dir, $this->{$prefix.'file'}, $suffix);
    }

    public function getTitle()
    {
        $prefix = $this->_config['prefix'];
        return $this->{$prefix.'title'} ? $this->{$prefix.'title'} : $this->{$prefix.'file'};
    }

    public function uploadConfig($key)
    {
        $prefix = $this->_config['prefix'];

        return array(
            $prefix . 'file' => array(
                'label' => __('File'), 'required' => true,
                'dir' => ROOT_PATH . $this->_config['uploadDir'],
                'fileKey' => $key,
                'type' => 'file',
            ),
        );
    }

    public function bunchActions($foreignKey, &$input, $actions)
    {
        try {
            foreach ($actions as $action => $inputKey) {
                if ( empty($input[$inputKey]) || !is_array($input[$inputKey])) {
                    continue;
                }
                $method = 'bunch' . camelize($action, true);

                if ('create' === $action) {
                    $this->{$method}($foreignKey, $input[$inputKey], $inputKey);
                } else {
                    $this->{$method}($input[$inputKey]);
                }
            }
        } catch ( Exception $ex ) {
            DBHelper::clearNewFiles($this);
            throw $ex;
        }
    }

    public function bunchUpdate($input)
    {
        $prefix = $this->_config['prefix'];
        $updates = array($prefix.'title', $prefix.'position');
        $model = $this->_config['model'];

        foreach ($input as $id => $data) {
            $m = DBHelper::getOne($model, $id, 'list');
            $fields = $m->fields();
            if (isset($fields['mime'])) {
                unset($fields['mime']);
            }
            $m->save($fields, $data, true);
        }
    }

    public function bunchCreate($relKey, &$input, $fileKey)
    {
        $prefix = $this->_config['prefix'];
        $model = $this->_config['model'];
        $this->_new_files = array();

        $rconf = $this->_config['belongsTo']['parent'];
        try {
            foreach ($input as $key => $data) {
                $m = new $model;
                $m->{$rconf['relKey']} = $relKey;

                $fields = $m->fields();
                $m->verify( $fields, $data );
                if ( $fileFieldConf = $m->uploadConfig($fileKey.$key) ) {
                    $m->verify( $fileFieldConf, $data);

                    $fields[$rconf['relKey']] = true;
                    $fields[$prefix.'file'] = true;
                    if (property_exists($m, $prefix.'mime')) {
                        $fields[$prefix.'mime'] = true;
                    }
                    $m->{$prefix.'mime'} = $m->{$prefix.'file_type'};
                    if (property_exists($m, $prefix.'create_time')) {
                        $m->rawValueFields = array('create_time' => 'NOW()');
                    }
                    if (!$m->{$prefix.'title'}) {
                        $info = pathinfo($m->{$prefix.'file_original_name'});
                        if ( !isset($info['filename'])) {
                            $info['filename'] = substr($info['basename'], 0, strlen($info['basename'])-strlen($info['extension'])-1);
                        }
                        $m->{$prefix.'title'} = $info['filename'];
                    }
                }
                $this->_new_files = array_merge($this->_new_files, $m->_new_files);
                $m->insert(array_keys($fields));
            }
        } catch ( Exception $ex ) {
            DBHelper::clearNewFiles($this);
            throw $ex;
        }
    }

    public function bunchDelete($ids)
    {
        $model = $this->_config['model'];
        call_user_func($model.'::deleteAll', $ids);
    }

    public function deleteAllByParent($relKeys)
    {
        $model = $this->_config['model'];
        call_user_func($model.'::deleteAllByColumn', $this->_config['belongsTo']['parent']['relKey'], $relKeys);
    }

    public function getListByParent($relKey)
    {
        if (! $relKey) {
            return null;
        }

        $prefix = $this->_config['prefix'];

        $search = new Search;
        $search->where[] = '`' . $this->_config['belongsTo']['parent']['relKey'] . '` = ?';
        $search->params[] = $relKey;
        $search->orderBy = '`' . $prefix . 'position` ASC';

        return DBHelper::getList($this->_config['model'], $search, NULL);
    }

    public function selectInfo($for)
    {
        $prefix = $this->_config['prefix'];

        $select = array();
        switch ($for) {
            case 'id':
                return '`id`';

            case 'list':
                $select[] = '`' . $prefix . 'id`,'
                    . ' `' . $prefix . 'title`,'
                    . ' `' . $prefix . 'file`,'
                    . ' `' . $prefix . 'position`';

                if (property_exists($this, $prefix.'mime')){
                    $select[] = ' `' . $prefix . 'mime`';
                }
                if (property_exists($this, $prefix.'create_time')){
                    $select[] = ' `' . $prefix . 'create_time`';
                }
                break;
            default:
                break;
        }

        return implode(',', $select);
    }
}
