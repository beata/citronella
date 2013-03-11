<?php
/**
 * 分頁類別
 *
 * 透過 __get() and __set() 所控制的成員變數
 *
 * 讀與寫
 *  totalRows   - 總筆數
 *  param       - 網址分頁 $_GET 參數名稱
 *  paramSort   - 網址排序 $_GET 參數名稱
 *  sortField   - 排序欄位
 *  sortDir     - 排序方向
 *  rowsPerPage - 每頁顯示筆數
 *  numPerPage  - 每頁顯示頁數
 *
 * 唯讀
 *  currentPage - 目前頁數
 *  totalPages  - 總頁數
 *  rowStart    - 本頁資料起始筆數
 *
 * @packaged default
 **/
class Pagination
{
    public $totalRows = 0;
    public $param = 'page';
    public $paramSort = 'sort';
    public $sortField = 'id';
    public $sortDir = 'asc';
    public $rowsPerPage = 20;
    public $numPerPage = 10;
    public $sortable = array();

    public $symbalAsc = ' <i class="icon-chevron-up" title="小至大"></i>';
    public $symbalDesc = ' <i class="icon-chevron-down" title="大至小"></i>';

    // readonly members
    public $currentPage = 0;
    public $totalPages = 0;
    public $rowStart = 0;
    public $groupBy;

    public $info;
    public $infoFormat = '有 %s 筆資料，總共 %s 頁';


    public function __construct($config=array())
    {
        if ( ! array_key_exists('rowsPerPage', $config)) {
            $this->rowsPerPage = App::conf()->pagination->rows_per_page;
        }
        if ( ! isset($config['numPerPage']) ) {
            $this->numPerPage = App::conf()->pagination->num_per_page;
        }
        if ( isset($config['sortable'])) {
            $sortable = $config['sortable'];
            if ( isset($sortable[0])) {
                $sortable = array_flip($sortable);
            }
            unset($config['sortable']);
        } else {
            $sortable = array();
        }
        $this->sortable = $sortable;
        foreach ( $config as $key => $value ) {
            $this->{$key} = $value;
        }
        // paginate
        $this->totalPages = $this->rowsPerPage ? ceil($this->totalRows / $this->rowsPerPage) : 1;
        $this->currentPage = isset($_GET[$this->param]) ? min($this->totalPages, max(1, (int)$_GET[$this->param])) : 1;
        $this->rowStart = max(0, ($this->currentPage - 1)) * $this->rowsPerPage;
        if ( isset($_GET[$this->paramSort]))
        {
            $field = $_GET[$this->paramSort];
            $dir = $this->sortDir;
            if ( strpos($field, '.desc') !== false || strpos($field, '.asc') !== false ) {
                list($field, $dir) = explode('.', $field);
            }
            if ( isset($sortable[$field])) {
                $this->sortField = $field;
                $this->sortDir = $dir;
            }
        }
    }
    public function info()
    {
        if ( $this->info ) {
            return $this->info;
        }
        return sprintf(_e($this->infoFormat), '<strong>' . $this->totalRows . '</strong>', '<strong>' . $this->totalPages . '</strong>');
    }
    public function getSqlLimit()
    {
        $limit = '';
        if ( $this->rowsPerPage) {
            $limit = ' LIMIT ' . $this->rowStart . ',' . $this->rowsPerPage;
        }
        return $limit;
    }
    public function getSqlGroupBy()
    {
        if ( $this->groupBy) {
            return ' GROUP BY ' . $this->groupBy;
        }
        return '';
    }
    public function pages($showInfo=false, $cssClass='pagination-centered')
    {
        if ( $this->totalPages < 2) {
            if ( $showInfo ) {
                echo '<div class="pagination ', $cssClass, '"><p class="pagination-info">', $this->info(), '</p></div>';
            }
            return;
        }

        $params = $_GET;
        unset($params[$this->param]);
        $urlparams = http_build_query($params, '', '&amp;');

        $midNumber = floor($this->numPerPage / 2);
        $numStart = max(1, $this->currentPage - $midNumber);
        $numEnd = min($this->totalPages, $numStart + $this->numPerPage);

        echo '<div class="pagination ', $cssClass, '"><ul>';

        $url = $urlparams
            ? '?' . $urlparams . '&amp;' . $this->param . '='
            : '?' . $this->param . '=';

        $disabled = array(
            '_url'  => '#',
            'class' => ' disabled'
        );

        // first page
        if ( $this->currentPage != 1) {
            $_url = $url . '1';
            $class = '';
        } else {
            extract($disabled);
        }
        echo '<li class="first', $class, '"><a href="', $_url, '">', _e('第一頁'), '</a></li>';

        // previous page
        if ( $this->currentPage > 1) {
            $_url = $url . ($this->currentPage-1);
            $class = '';
        } else {
            extract($disabled);
        }
        echo '<li class="previous', $class, '"><a href="', $_url, '">', _e('上一頁'), '</a></li>';

        // page numbers
        foreach ( range($numStart, $numEnd) as $num):
            echo '<li',
                ( $num == $this->currentPage ? ' class="active"' : '' ),
                '><a href="', $url, $num, '">', $num, '</a></li>';
        endforeach;

        // next page
        if ( $this->currentPage < $this->totalPages ) {
            $_url =  $url . ($this->currentPage+1);
            $class = '';
        } else {
            extract($disabled);
        }
        echo '<li class="next', $class, '"><a href="', $_url, '">', _e('下一頁'), '</a></li>';

        // last page
        if ( $this->currentPage != $this->totalPages ) {
            $_url =  $url . $this->totalPages;
            $class = '';
        } else {
            extract($disabled);
        }
        echo '<li class="last', $class, '"><a href="', $_url, '">', _e('最末頁'), '</a></li>';

        echo '</ul>';

        if ( $showInfo ) {
            echo '<p class="pagination-info">', $this->info(), '</p>';
        }

        echo '</div>';
    }
    public function sortLink($fieldName, $displayName, $attrs=NULL, $symbalAsc=NULL, $symbalDesc=NULL)
    {
        static $url_part = NULL;

        if ( $url_part === NULL ) {
            $params = $_GET;
            unset($params[$this->paramSort]);
            if ( $url_part = http_build_query($params, '', '&amp;')) {
                $url_part = '?' . $url_part . '&amp;' . $this->paramSort . '=';
            } else {
                $url_part = '?' . $this->paramSort . '=';
            }
        }
        if ( $fieldName === $this->sortField ) {
            $symbal = ($this->sortDir === 'asc'
                ? ( $symbalAsc === NULL ? $this->symbalAsc : $symbalAsc)
                : ( $symbalDesc === NULL ? $this->symbalDesc : $symbalDesc) );
        } else {
            $symbal = null;
        }
        if ( $fieldName === $this->sortField && $this->sortDir === 'asc' ) {
            $url = $url_part . $fieldName . '.desc'; // desc
        } else {
            $url = $url_part . $fieldName . '.asc'; // asc
        }
        echo '<a href="'.$url.'"'.($attrs ? ' '.$attrs : '').'>', $displayName, $symbal, '</a>';
    }
    public function currentSort($symbalAsc=NULL, $symbalDesc=NULL)
    {
        $symbal = ($this->sortDir === 'asc'
            ? ( $symbalAsc === NULL ? $this->symbalAsc : $symbalAsc)
            : ( $symbalDesc === NULL ? $this->symbalDesc : $symbalDesc) );
        return $this->sortable[$this->sortField] . $symbal;
    }
} // END class
