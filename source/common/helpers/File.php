<?php
/**
 * File class file
 *
 * @package Helper.File
 * @license MIT
 * @file
 */

/**
 * File Helper
 */
class File
{
    /**
     * Stores file permission from constructor, which will be pass to `mkdir()`.
     * @var string
     */
    public $chmod;

    /**
     * The constructor
     *
     * @param int $chmod The file permission for `mkdir()`. @see http://www.php.net/mkdir
     * @return void
     */
    public function  __construct($chmod=0777)
    {
        $this->chmod = $chmod;
    }

    /**
     * Delete contents in a folder recursively
     * @param  string $dir Path of the folder to be deleted
     * @return int    Total of deleted files/folders
     */
    public function purgeContent($dir)
    {
        $totalDel = 0;
        $handle = opendir($dir);

        while (false !== ($file = readdir($handle))) {
            if ($file != '.' && $file != '..') {
                if (is_dir($dir.$file)) {
                    $totalDel += $this->purgeContent($dir.$file.'/');
                    if( rmdir($dir.$file) )
                        $totalDel++;
                } else {
                    if( unlink($dir.$file) )
                        $totalDel++;
                }
            }
        }
        closedir($handle);

        return $totalDel;
    }

    /**
     * Delete a folder (and all files and folders below it)
     * @param  string   $path       Path to folder to be deleted
     * @param  bool     $deleteSelf true if the folder should be deleted. false if just its contents.
     * @return int|bool Returns the total of deleted files/folder. Returns false if delete failed
     */
    public function delete($path, $deleteSelf=true)
    {
        if (file_exists($path)) {
            //delete all sub folder/files under, then delete the folder itself
            if (is_dir($path)) {
                if ($path[strlen($path)-1] != '/' && $path[strlen($path)-1] != '\\' ) {
                    $path .= DIRECTORY_SEPARATOR;
                    $path = str_replace('\\', '/', $path);
                }
                if ($total = $this->purgeContent($path)) {
                    if($deleteSelf)
                        if($t = rmdir($path))

                            return $total + $t;
                    return $total;
                } elseif ($deleteSelf) {
                    return rmdir($path);
                }

                return false;
            } else {
                return unlink($path);
            }
        }
    }

    /**
     * Delete files and directories in the `$files` list recursivily.
     * @param  string   $path       Path to folder/file to be deleted
     * @param  bool     $deleteSelf true if the folder should be deleted. false if just its contents.
     * @return void
     */
    public function deleteAll($files=array(), $deleteSelf=true)
    {
        foreach ($files as $path) {
            $this->delete($path, $deleteSelf);
        }
    }
    /**
     * If the folder does not exist creates it (recursively)
     * @param  string $path          Path to folder/file to be created
     * @param  mixed  $content       Content to be written to the file
     * @param  string $writeFileMode Mode to write the file
     * @return bool   Returns true if file/folder created
     */
    public function create($path, $content=null, $writeFileMode='w+')
    {
        //create file if content not empty
        if (!empty($content)) {
            if (strpos($path, '/')!==false || strpos($path, '\\')!==false) {
                $path = str_replace('\\', '/', $path);
                $filename = $path;
                $path = explode('/', $path);
                array_splice($path, sizeof($path)-1);

                $path = implode('/', $path);
                if ($path[strlen($path)-1] != '/') {
                    $path .= '/';
                }
            } else {
                $filename = $path;
            }

            if($filename!=$path && !file_exists($path))
                mkdir($path, $this->chmod, true);
            $fp = fopen($filename, $writeFileMode);
            $rs = fwrite($fp, $content);
            fclose($fp);

            return ($rs>0);
        } else {
            if (!file_exists($path)) {
                return mkdir($path, $this->chmod, true);
            } else {
                return true;
            }
        }
    }

    /**
     * Send file to the http client.
     *
     * @param string $filePath The file path
     * @param string $simpleName 簡短檔名，如 "abc.txt"。當 $fancyName 不可用時，會提示客戶端儲存成 $simpleName，因此 $simpleName 最好取英數字母
     * @param string $fancyName 複雜檔名，如 "文件名稱.txt"。
     * @return void
     */
    public static function send($filePath, $simpleName, $fancyName=NULL)
    {
        if ( ini_get('zlib.output_compression')) {
            ini_set('zlib.output_compression', 'Off');
        }

        self::downloadHeader('application/force-download', 'binary', $simpleName, $fancyName);
        header('Content-Length: ' . filesize($filePath));
        readfile($filePath);
    }

    /**
     * Send downloading header to the http client.
     *
     * @param string $mime The mime type
     * @param string $transferEncoding "Content-Transfer-Encoding: $transferEncoding"
     * @param string $simpleName 簡短檔名，如 "abc.txt"。當 $fancyName 不可用時，會提示客戶端儲存成 $simpleName，因此 $simpleName 最好取英數字母
     * @param string $fancyName 複雜檔名，如 "文件名稱.txt"。
     * @return void
     */
    public static function downloadHeader($mime, $transferEncoding, $simpleName, $fancyName=NULL)
    {
        header('Pragma: public'); // required
        header('Expires: 0');
        header('Cache-Control: must-revalidate, post-check=0, pre-check=0');
        header('Cache-Control: private',false); // required for certain browsers
        header('Content-type: ' . $mime);
        if ($fancyName === NULL) {
            header('Content-Disposition: attachment; filename="' . $simpleName . '"');
        } else {
            header('Content-Disposition: attachment; filename="' . $simpleName . '"; filename*=utf-8\'\'' . rawurlencode($fancyName));
        }
        if ($transferEncoding) {
            header('Content-Transfer-Encoding: ' . $transferEncoding);
        }
    }

    /**
     * 給人類讀的檔案大小
     *
     * @param integer file size in bytes
     * @return string
     */
    public static function formatBytes($bytes)
    {
        if ( ! is_numeric($bytes) ) {
            return 'NaN';
        }

        $decr = 1024;
        $step = 0;
        $unit = array('Byte','KB','MB','GB','TB','PB');
        while (($bytes / $decr) > 0.9) {
            $bytes = $bytes / $decr;
            $step++;
        }

        return round($bytes, 2) . $unit[$step];
    }

}
