<?php
/**
 * Upload Class
 *
 * @package Helper.Upload
 * @uses Helper.File
 */
App::loadHelper('File', false, 'common');

class UploadException extends Exception {}

/**
 * Upload Class
 *
 * @package Helper.Upload
 * @subpackage Helper.Upload
 */
class Upload
{
    /**
     * The name of the upload input, the array key of `$_FILES`.
     * @var string
     */
    public $fieldName;

    /**
     * Directory path that stores the uploaded file.
     * @var string
     */
    public $destDir;

    /**
     * Whether or not to rename the uploaded file.
     * If your set this value to:
     *  * TRUE - The uploaded file will be renamed by current timestamp.
     *  * FALSE - The uploaded file will keep its original name.
     *  * Any string - The uploaded file will be renamed to this value.
     * @var boolean|string
     */
    public $rename = true; // true 代表系統重命名、false 代表保持上傳的檔名、字串代表指定新檔名

    /**
     * Restrict the maximum upload size to the uploaded file. (in bytes)
     * @var integer
     */
    public $maxSize; // in bytes

    /**
     * An array of mime list that was grouped by file extension.
     * [extension] => [mime1, mime2, ...]
     * @var array
     */
    public $allowTypes;

    /**
     * Forbid uploading file has the following extensions.
     * @var array
     */
    public $denyFiles = array('php', 'phps', 'php3', 'php4', 'phtml');

    /**
     * The Constructor
     *
     * @param string $fieldName will be Upload::$fieldName
     * @param string $destDir will be Upload::$destDir
     * @param boolean|string $rename will be Upload::$rename
     * @param string $maxSize will be Upload::$maxSize
     * @param array $allowTypes will be Upload::$allowTypes
     * @param array $denyFiles will be Upload::$denyFiles
     * @return void
     */
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
            throw new UploadException(__('Please upload file.'));
        }

        if (NULL === $idx) {
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
            throw new UploadException(__('Please upload file.'));
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
                throw new UploadException(__('Upload Error! Failed to move file to disk.'));
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

        foreach ($_FILES[$this->fieldName]['name'] as $idx => $file) {
            try {

                $this->checkAll($idx);

                $newFileName = $this->newFileName( basename(strtolower($_FILES[$this->fieldName]['name'][$idx])) );
                $filePath = $this->destDir . DIRECTORY_SEPARATOR . $newFileName;

                if ( ! move_uploaded_file($_FILES[$this->fieldName]['tmp_name'][$idx], $filePath)) {
                    throw new UploadException(__('Upload Error! Failed to move file to disk.'));
                }
                $successed[] = array(
                    'name'    => strtolower($_FILES[$this->fieldName]['name'][$idx]),
                    'rename'  => $newFileName,
                    'index'   => $idx,
                );

            } catch ( UploadException $ex ) {
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
        if (! $size) {
            throw new UploadException(__('The uploaded file is empty.'));
        }
        if ($this->maxSize && $this->maxSize < $size) {
            throw new UploadException(sprintf(__('The uploaded file exceeds %s'), File::formatBytes($this->maxSize)));
        }
    }
    public function checkExtension( $ext )
    {
        if ( in_array($ext, $this->denyFiles) ) {
            throw new UploadException(__('Unacceptable file type.'));
        }
    }
    public function checkType( $ext, $type)
    {
        $message = sprintf(__('Plase upload file in %s format.'), implode(', ', array_keys($this->allowTypes)));

        if ( ! isset($this->allowTypes[$ext])) {
            throw new UploadException($message);
        }
        foreach ($this->allowTypes as $ext => $mimes) {
            if ( in_array($type, $mimes)) {
                return;
            }
        }
        throw new UploadException($message);
    }
    /**
     * 產生不重複的新檔名
     *
     * @param  string $filename 舊檔名
     * @return string
     */
    public function newFileName( $filename )
    {
        if (false === $this->rename) {
            return $filename;
        }

        $ext = pathinfo($filename, PATHINFO_EXTENSION);
        $ext = '' === $ext ? '' : '.' . strtolower($ext);

        if (true === $this->rename) {
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
                throw new UploadException(sprintf(__('Upload Error! The uploaded file exceeds the upload_max_filesize directive in php.ini.(%s)'), ini_get('upload_max_filesize')));
            case UPLOAD_ERR_FORM_SIZE:
                throw new UploadException(__('Upload Error! The uploaded file exceeds the MAX_FILE_SIZE directive that was specified in the HTML form.'));
            case UPLOAD_ERR_PARTIAL:
                throw new UploadException(__('Upload Error! The uploaded file was only partially uploaded.'));
            case UPLOAD_ERR_NO_FILE:
                throw new UploadException(__('Upload Error! No file was uploaded.'));
            case UPLOAD_ERR_NO_TMP_DIR:
                throw new UploadException(__('Upload Error! Missing a temporary folder.'));
            case UPLOAD_ERR_CANT_WRITE:
                throw new UploadException(__('Upload Error! Failed to write file to disk.'));
            case UPLOAD_ERR_EXTENSION:
                throw new UploadException(__('Upload Error! A PHP extension stopped the file upload. PHP does not provide a way to ascertain which extension caused the file upload to stop; examining the list of loaded extensions with phpinfo() may help.'));
            default:
                throw new UploadException(__('Upload Error! Unkown error.'));
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
