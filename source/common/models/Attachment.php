<?php
abstract class Attachment extends Model
{
    /*
    public $rel_id;
    public ${prefix}id;
    public ${prefix}note; // title
    public ${prefix}type; // mime
    public ${prefix}name; // file
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

        return array(
            $prefix . 'note' => array(
                'label' => __('檔案標題'), 'required' => false,
            ),
            $prefix . 'type' => array( // mime
                'label' => __('檔案類型'), 'required' => false,
            ),
            $prefix . 'sort' => array(
                'label' => __('排序'), 'required' => false,
                'callbacks' => array('intval')
            ),
        );
    }

    public function getFileUrl()
    {
        $prefix = $this->_config['prefix'];
        return ROOT_URL . $this->_config['uploadDir'] . $this->{$prefix.'name'};
    }

    public function getTitle()
    {
        $prefix = $this->_config['prefix'];
        return $this->{$prefix.'note'} ? $this->{$prefix.'note'} : $this->{$prefix.'name'};
    }

    public function uploadConfig($key)
    {
        $prefix = $this->_config['prefix'];

        return array(
            $prefix . 'name' => array(
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
        $updates = array($prefix.'note', $prefix.'sort');
        $model = $this->_config['model'];

        $m = new $model;
        foreach ($input as $id => $data) {
            $m->{$prefix . 'id'} = $id;
            $m->{$prefix . 'note'} = $data[$prefix.'note'];
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
                    $fields[$prefix.'name'] = true;
                    $fields[$prefix.'type'] = true;
                    $m->{$prefix.'type'} = $m->{$prefix.'name_type'};
                    if (!$m->{$prefix.'note'}) {
                        $info = pathinfo($m->{$prefix.'name_orignal_name'});
                        if ( !isset($info['filename'])) {
                            $info['filename'] = substr($info['basename'], 0, strlen($info['basename'])-strlen($info['extension'])-1);
                        }
                        $m->{$prefix.'note'} = $info['filename'];
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
        DBHelper::deleteColumnFile(array(
            'table' => $this->_config['table'],
            'columns' => array($prefix.'name'),
            'where' => '`' . $this->_config['primaryKey'] . '` ' . DBHelper::in($ids) . ' AND `' . $prefix . 'name` != \'\'',
            'dir' => ROOT_PATH . $this->_config['uploadDir'],
        ));
        DBHelper::deleteAll($this->_config['model'], $ids);
    }

    public function deleteAllByParent($relKeys)
    {
        $prefix = $this->_config['prefix'];
        $relKeys = DBHelper::in($relKeys);
        $where = '`' . $this->_config['belongsTo']['parent']['relKey'] . '` ' . $relKeys;
        DBHelper::deleteColumnFile(array(
            'table' => $this->_config['table'],
            'columns' => array($prefix.'name'),
            'where' => $where . ' AND `' . $prefix . 'name` != \'\'',
            'dir' => ROOT_PATH . $this->_config['uploadDir'],
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
                    . ' `' . $prefix . 'note`,'
                    . ' `' . $prefix . 'type`,'
                    . ' `' . $prefix . 'name`,'
                    . ' `' . $prefix . 'sort`';
                break;
            default:
                break;
        }

        return implode(',', $select);
    }
}
interface IAttachemntsRead
{
    public function getAttachmentClass();
    public function attachments();
}
