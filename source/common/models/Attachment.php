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
    public ${prefix}sort;
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
                'label' => __('檔案標題'), 'required' => false,
            ),
            $prefix . 'sort' => array(
                'label' => __('排序'), 'required' => false,
                'callbacks' => array('intval')
            ),
        );
        if (property_exists($this, $prefix . 'mime')) {
            $fields[$prefix . 'mime'] = array(
                'label' => __('檔案類型'), 'required' => false,
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
                'label' => __('檔案'), 'required' => true,
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
        $updates = array($prefix.'title', $prefix.'sort');
        $model = $this->_config['model'];

        $m = new $model;
        foreach ($input as $id => $data) {
            $m->{$prefix . 'id'} = $id;
            $m->{$prefix . 'title'} = $data[$prefix.'title'];
            $m->{$prefix . 'sort'} = (int) $data[$prefix.'sort'];
            $m->update($updates);
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
                        $info = pathinfo($m->{$prefix.'file_orignal_name'});
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
        $prefix = $this->_config['prefix'];
        $ids = array_map('intval', $ids);

        $uploadConfig = $this->uploadConfig('dummy');
        $suffixes = array();
        if ('image' === $uploadConfig[$prefix.'file']['type']) {
            $suffixes[$prefix.'file'] = array_keys($uploadConfig[$prefix.'file']['thumbnails']);
        }

        DBHelper::deleteColumnFile(array(
            'table' => $this->_config['table'],
            'columns' => array($prefix.'file'),
            'where' => '`' . $this->_config['primaryKey'] . '` ' . DBHelper::in($ids) . ' AND `' . $prefix . 'file` != \'\'',
            'dir' => ROOT_PATH . $this->_config['uploadDir'],
            'suffixes' => $suffixes
        ));
        DBHelper::deleteAll($this->_config['model'], $ids);
    }

    public function deleteAllByParent($relKeys)
    {
        $prefix = $this->_config['prefix'];
        $relKeys = DBHelper::in($relKeys);
        $where = '`' . $this->_config['belongsTo']['parent']['relKey'] . '` ' . $relKeys;

        $uploadConfig = $this->uploadConfig('dummy');
        $suffixes = array();
        if ('image' === $uploadConfig[$prefix.'file']['type']) {
            $suffixes[$prefix.'file'] = array_keys($uploadConfig[$prefix.'file']['thumbnails']);
        }

        DBHelper::deleteColumnFile(array(
            'table' => $this->_config['table'],
            'columns' => array($prefix.'file'),
            'where' => $where . ' AND `' . $prefix . 'file` != \'\'',
            'dir' => ROOT_PATH . $this->_config['uploadDir'],
            'suffixes' => $suffixes
        ));
        App::db()->exec('DELETE FROM `' . $this->_config['table'] . '` WHERE ' . $where);
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
        $search->orderBy = '`' . $prefix . 'sort` ASC';

        return DBHelper::getList($this->_config['model'], $search, NULL);
    }

    public function selectInfo($for)
    {
        $prefix = $this->_config['prefix'];

        $select = array();
        switch ($for) {
            case 'list':
                $select[] = '`' . $prefix . 'id`,'
                    . ' `' . $prefix . 'title`,'
                    . ' `' . $prefix . 'file`,'
                    . ' `' . $prefix . 'sort`';

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
