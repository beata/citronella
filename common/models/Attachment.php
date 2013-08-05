<?php
abstract class Attachment extends Model
{
    public $news_id;
    public $id;
    public $name;
    public $description;
    public $mime;
    public $file;
    public $sort;

    protected $_config = array(
        'model' => 'Attachment',
        'primaryKey' => 'id',
        'table' => 'files',
        'uploadDir' => 'uploads/files/',

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
        return array(
            'name' => array(
                'label' => __('檔案名稱'), 'required' => false
            ),
            'description' => array(
                'label' => __('檔案簡述'), 'required' => false,
            ),
            'mime' => array(
                'label' => __('檔案類型'), 'required' => false,
            ),
            'sort' => array(
                'label' => __('排序'), 'required' => false,
                'callbacks' => array('intval')
            ),
        );
    }

    public function getFileUrl()
    {
        return ROOT_URL . $this->_config['uploadDir'] . $this->file;
    }

    public function getName()
    {
        return $this->name ? $this->name : $this->file;
    }

    public function uploadConfig($key)
    {
        return array(
            'file' => array(
                'label' => __('檔案'), 'required' => true,
                'dir' => ROOT_PATH . $this->_config['uploadDir'],
                'fileKey' => $key,
                'type' => 'file',
            ),
        );
    }

    public function bunchActions($foreignKey, &$input, $dataKeys)
    {
        try {
            foreach ( $dataKeys as $action => $key) {
                if ( empty($input[$key]) || !is_array($input[$key])) {
                    continue;
                }
                $method = 'bunch' . camelize($action, true);

                if ( 'create' === $action) {
                    $this->{$method}($foreignKey, $input[$key], $key);
                } else {
                    $this->{$method}($input[$key]);
                }
            }
        } catch ( Exception $ex ) {
            DBHelper::clearNewFiles($this);
            throw $ex;
        }
    }

    public function bunchUpdate($input)
    {
        $updates = array('name', 'description', 'sort');
        $model = $this->_config['model'];
        $m = new $model;
        foreach ( $input as $id => $data) {
            $m->id = $id;
            $m->name = $data['name'];
            $m->description = $data['description'];
            $m->sort = (int)$data['sort'];
            $m->update($updates);
        }
    }

    public function bunchCreate($relKey, &$input, $fileKey)
    {
        $model = $this->_config['model'];
        $this->_new_files = array();

        $rconf = $this->_config['belongsTo']['parent'];
        try {
            foreach ( $input as $key => $data ) {
                $m = new $model;
                $m->{$rconf['relKey']} = $relKey;

                $fields = $m->fields();
                $m->verify( $fields, $data );
                if ( $fileFieldConf = $m->uploadConfig($fileKey.$key) ) {
                    $m->verify( $fileFieldConf, $data);

                    $fields[$rconf['relKey']] = true;
                    $fields['file'] = true;
                    $fields['mime'] = true;
                    $m->mime = $m->file_type;
                    if ( !$m->name ) {
                        $info = pathinfo($m->file_orignal_name);
                        if ( !isset($info['filename'])) {
                            $info['filename'] = substr($info['basename'], 0, strlen($info['basename'])-strlen($info['extension'])-1);
                        }
                        $m->name = $info['filename'];
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
        $ids = array_map('intval', $ids);
        DBHelper::deleteColumnFile(array(
            'table' => $this->_config['table'],
            'columns' => array('file'),
            'where' => '`' . $this->_config['primaryKey'] . '` ' . DBHelper::in($ids) . ' AND `file` != \'\'',
            'dir' => ROOT_PATH . $this->_config['uploadDir'],
        ));
        DBHelper::deleteAll($this->_config['model'], $ids);
    }

    public function deleteAllByParent($relKeys)
    {
        $relKeys = DBHelper::in($relKeys);
        $where = '`' . $this->_config['belongsTo']['parent']['relKey'] . '` ' . $relKeys;
        DBHelper::deleteColumnFile(array(
            'table' => $this->_config['table'],
            'columns' => array('file'),
            'where' => $where . ' AND `file` != \'\'',
            'dir' => ROOT_PATH . $this->_config['uploadDir'],
        ));
        App::db()->exec('DELETE FROM `' . $this->_config['table'] . '` WHERE ' . $where);
    }

    public function getListByParent($relKey)
    {
        if ( ! $relKey) {
            return null;
        }

        $search = new Search;
        $search->where[] = '`' . $this->_config['belongsTo']['parent']['relKey'] . '` = ?';
        $search->params[] = $relKey;
        App::loadHelper('Pagination', false, 'common');
        $pager = new Pagination(array(
            'sortField' => 'sort',
            'sortDir' => 'asc',
            'rowsPerPage' => false
        ));
        return DBHelper::getList($this->_config['model'], $search, $pager);
    }

    public function selectInfo($for)
    {
        $select = array();
        switch ($for) {
            case 'list':
                $select[] = '`id`, `name`, `description`, `sort`, `mime`, `file`';
                break;
            default:
                break;
        }
        return implode(',', $select);
    }
}
