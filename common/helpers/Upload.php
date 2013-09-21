<?php
App::loadHelper('File', false, 'common');

class Upload
{
    public $fieldName;
    public $destDir;
    public $rename = true; // true 代表系統重命名、false 代表保持上傳的檔名、字串代表指定新檔名
    public $maxSize; // in bytes
    public $allowTypes;
    public $denyFiles = array('php', 'phps', 'php3', 'php4', 'phtml');

    public function __construct($fieldName, $destDir, $rename=true, $maxSize=NULL, $allowTypes=NULL, $denyFiles=array('php', 'phps', 'php3', 'php4', 'phtml'))
    {
        $this->fieldName = $fieldName;
        $this->destDir = $destDir;
        $this->rename = $rename;
        $this->maxSize = $maxSize;
        $this->allowTypes = $allowTypes;
        $this->denyFiles = $denyFiles;
    }
	public function checkAll($idx=NULL)
	{
        if ( ! isset($_FILES[$this->fieldName])) {
            throw new Exception(__('請上傳檔案'));
        }

		if ( NULL === $idx ) {
            $this->checkError( $_FILES[$this->fieldName]['error']);
            $this->checkSize( $_FILES[$this->fieldName]['size']);

            $ext = pathinfo(strtolower($_FILES[$this->fieldName]['name']), PATHINFO_EXTENSION);
            if ( !empty($this->denyFiles)) {
                $this->checkExtension( $ext );
            }
            if ( !empty($this->allowTypes)) {
                $this->checkType( $ext, $_FILES[$this->fieldName]['type'] );
            }
			return;
		}

		$this->checkError( $_FILES[$this->fieldName]['error'][$idx]);
		$this->checkSize( $_FILES[$this->fieldName]['size'][$idx]);

        $ext = pathinfo(strtolower($_FILES[$this->fieldName]['name'][$idx]), PATHINFO_EXTENSION);
        if ( !empty($this->denyFiles)) {
            $this->checkExtension( $ext );
        }
		if ( !empty($this->allowTypes)) {
			$this->checkType( $ext, $_FILES[$this->fieldName]['type'][$idx] );
		}
	}
    public function save()
    {
        if ( ! isset($_FILES[$this->fieldName])) {
            throw new Exception(__('請上傳檔案'));
        }

        // single file upload
        if ( ! is_array($_FILES[$this->fieldName]['name'])) {

			$this->checkAll();

            if ( ! file_exists($this->destDir)) {
                $file = new File();
                $file->create($this->destDir);
            }
            $newFileName = $this->newFileName( basename(strtolower($_FILES[$this->fieldName]['name'])) );
            $filePath = $this->destDir . DIRECTORY_SEPARATOR . $newFileName;

            if ( ! move_uploaded_file($_FILES[$this->fieldName]['tmp_name'], $filePath)) {
                throw new Exception(__('上傳失敗！搬移檔案至目標資料夾時發生錯誤'));
            }
            return $newFileName;

        }

        // is array
        if ( ! file_exists($this->destDir)) {
            $file = new File();
            $file->create($this->destDir);
        }
        $successed = array();
        $failed = array();

        foreach ( $_FILES[$this->fieldName]['name'] as $idx => $file) {
            try {

				$this->checkAll($idx);

                $newFileName = $this->newFileName( basename(strtolower($_FILES[$this->fieldName]['name'][$idx])) );
                $filePath = $this->destDir . DIRECTORY_SEPARATOR . $newFileName;

                if ( ! move_uploaded_file($_FILES[$this->fieldName]['tmp_name'][$idx], $filePath)) {
                    throw new Exception(__('上傳失敗！搬移檔案至目標資料夾時發生錯誤'));
                }
                $successed[] = array(
                    'name'    => strtolower($_FILES[$this->fieldName]['name'][$idx]),
                    'rename'  => $newFileName,
                    'index'   => $idx,
                );

            } catch ( Exception $ex ) {
                $failed[] = array(
                    'name'    => strtolower($_FILES[$this->fieldName]['name'][$idx]),
                    'index'   => $idx,
                    'message' => $ex->getMessage()
                );
            }
        }
        return compact('successed', 'failed');
    }
    public function checkSize($size)
    {
        if ( ! $size ) {
            throw new Exception(__('所上傳的檔案是空的，請另外上傳有內容的檔案'));
        }
        if ( $this->maxSize && $this->maxSize < $size ) {
            throw new Exception(sprintf(__('只能上傳大小不超過%s的檔案'), File::formatBytes($this->maxSize)));
        }
    }
    public function checkExtension( $ext )
    {
        if ( in_array($ext, $this->denyFiles) ) {
            throw new Exception(__('不接受的檔案格式'));
        }
    }
    public function checkType( $ext, $type)
    {
        $message = sprintf(__('請上傳 %s 格式的檔案'), implode(', ', array_keys($this->allowTypes)));

        if ( ! isset($this->allowTypes[$ext])) {
            throw new Exception($message);
        }
        foreach ( $this->allowTypes as $ext => $mimes ) {
            if ( in_array($type, $mimes)) {
                return;
            }
        }
        throw new Exception($message);
    }
    /**
     * 產生不重複的新檔名
     *
     * @param string $filename 舊檔名
     * @return string
     */
    public function newFileName( $filename )
    {
        if ( false === $this->rename ) {
            return $filename;
        }

        $ext = pathinfo($filename, PATHINFO_EXTENSION);
        $ext = '' === $ext ? '' : '.' . strtolower($ext);

        if ( true === $this->rename ) {
            $name = date('YmdHis') . '.' . mt_rand(1000, 9999);
            while ( file_exists($this->destDir . DIRECTORY_SEPARATOR . $name . $ext)) {
                $name = date('YmdHis') . '-' . mt_rand(1000, 9999);
            }
            return $name . $ext;
        }

        if ( is_string($this->rename) && strlen($this->rename) > 0 ) {
            return $this->rename . $ext;
        }
    }
    public function checkError($errorno)
    {
        switch ($errorno) {
            case UPLOAD_ERR_OK:
                return;
            case UPLOAD_ERR_INI_SIZE:
                throw new Exception(sprintf(__('上傳失敗！上傳的檔案大小超過伺服器限制的%s'), ini_get('upload_max_filesize')));
            case UPLOAD_ERR_FORM_SIZE:
                throw new Exception(__('上傳失敗！上傳的檔案大小超過限定的大小'));
            case UPLOAD_ERR_PARTIAL:
                throw new Exception(__('上傳失敗！檔案上傳不完全'));
            case UPLOAD_ERR_NO_FILE:
                throw new Exception(__('上傳失敗！並未上傳檔案'));
            case UPLOAD_ERR_NO_TMP_DIR:
                throw new Exception(__('上傳失敗！找不到暫存資料夾'));
            case UPLOAD_ERR_CANT_WRITE:
                throw new Exception(__('上傳失敗！無法寫入檔案'));
            case UPLOAD_ERR_EXTENSION:
                throw new Exception(__('上傳失敗！不接受的副檔名'));
            default:
                throw new Exception(__('上傳失敗！未知的錯誤'));
        }
    }
    public function getFileType()
    {
        return $_FILES[$this->fieldName]['type'];
    }
    public function getOrignalName()
    {
        return strtolower($_FILES[$this->fieldName]['name']);
    }
}
