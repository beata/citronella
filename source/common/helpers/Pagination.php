<?php
/**
 * Pagination class
 *
 * @package Helper.Pagination
 * @license MIT
 * @file
 **/
/**
 * Pagination Class
 *
 * @package Helper.Pagination
 * @subpackage Helper.Pagination
 */
class Pagination
{
    /** @name Setting - Can be set from the constructor */
    //{@

    /**
     * Total rows
     * @var integer
     */
    public $totalRows = 0;

    /**
     * If you choose fetching current page number from `$_GET`, set the parameter name here.
     * @var string
     */
    public $param = 'page';

    /**
     * If you choose fetching current page number from `$urls->queryString`, set the segment index here.
     * @var integer
     */
    public $segment = NULL;

    /**
     * The key in `$_GET` that represents sort direction.
     * @var string
     */
    public $paramSort = 'sort';

    /**
     * Main ordering rule, specify the column name here.
     * @var string
     */
    public $sortField = 'id';

    /**
     * Main ordering rule, specify the direction here, can be 'asc' or 'desc'
     * @var string
     */
    public $sortDir = 'asc';

    /**
     * Optional ordering rule before main ordering rule, specify an array of column names
     * @var array
     */
    public $primarySortField = NULL;

    /**
     * Optional ordering rule before main ordering rule, specify directions match each `$primarySortField`.
     * @var array
     */
    public $primarySortDir = NULL;

    /**
     * Optional ordering rule after main ordering rule, specify an array of column names
     * @var array
     */
    public $secondSortField = NULL;

    /**
     * Optional ordering rule after main ordering rule, specify directions match each `$secondSortField`.
     * @var array
     */
    public $secondSortDir = NULL;

    /**
     * How many rows to display on each page
     * @var integer
     */
    public $rowsPerPage = 20;

    /**
     * How many page numbers to display on each page
     * @var integer
     */
    public $numPerPage = 10;

    /**
     * Sortable columns, in key-pair format
     * [column name] => 'display title'
     * @var array
     */
    public $sortable = array();

    /**
     * Set sql group by statement (without 'GROUP BY')
     * @var string
     */
    public $groupBy;

    /**
     * Set sql order by statement (without 'ORDER BY')
     * @var string
     */
    public $searchOrderBy;

    /**
     * If set, `$this->info()` will return this property directly.
     * @var string
     */
    public $info;

    /**
     * Format for `$this->info()`, which outputs `sprintf($infoFormat, $totalRows, $totalPages)`. The default value is set in the constructor.
     * @var string
     */
    public $infoFormat = NULL;

    /**
     * HTML string for ascending icon. The default value is set in the constructor.
     * @var string
     */
    public $symbalAsc = NULL;

    /**
     * HTML string for descending icon. The default value is set in the constructor.
     * @var string
     */
    public $symbalDesc = NULL;

    /**
     * Title for Navication links. The default value is set in the constructor.
     * @var array
     */
    public $labels = array(
        'first' => NULL,
        'prev' => NULL,
        'next' => NULL,
        'last' => NULL
    );

    /**
     * Whether or not to display page number links.
     * @var boolean
     */
    public $showNumbers = TRUE;

    /**
     * Whether or not to display page number dropdown.
     * @var boolean
     */
    public $showJumper = FALSE;
    //@}

    /** @name ReadOnly */
    //{@
    /**
     * Current page number.
     * @var integer
     */

    public $currentPage = 0;

    /**
     * Total pages.
     * @var integer
     */
    public $totalPages = 0;

    /**
     * Start position for sql query statement
     * @var integer
     */
    public $rowStart = 0;
    //@}

    private $_origDir;

    /**
     * The constructor
     *
     * @param array $config Pass member properties to the controller.
     * @return void
     */
    public function __construct($config=array())
    {
        $this->symbalAsc = ' <i class="glyphicon glyphicon-chevron-up" title="' .  _e('Asc.') .  '"></i>';
        $this->symbalDesc = ' <i class="glyphicon glyphicon-chevron-down" title="' .  _e('Desc.') .  '"></i>';
        $this->infoFormat = _e('%s Results, %s Pages');

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
        $this->labels = array(
            'first' => _e('First'),
            'prev' => _e('Previous'),
            'next' => _e('Next'),
            'last' => _e('Last')
        );
        foreach ($config as $key => $value) {
            if ( is_array($value) && !empty($this->{$key})) {
                $this->{$key} = array_merge($this->{$key}, $value);
            } else {
                $this->{$key} = $value;
            }
        }
        // paginate
        $this->totalPages = $this->rowsPerPage ? ceil($this->totalRows / $this->rowsPerPage) : 1;

        if (NULL === $this->segment) {
            $pageNum = isset($_GET[$this->param]) ? $_GET[$this->param] : 1;
        } else {
            if (!($pageNum = (int) App::urls()->segment($this->segment))) {
                $pageNum = 1;
            }
        }
        $this->setCurrentPage($pageNum);

        $this->_origDir = $this->sortDir;
        if ( isset($_GET[$this->paramSort])) {
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

    /**
     * Specify current page number
     *
     * @param integer $pageNum The page number to be set.
     * @return void
     */
    public function setCurrentPage($pageNum)
    {
        $this->currentPage = min($this->totalPages, max(1, (int) $pageNum));
        $this->rowStart = max(0, ($this->currentPage - 1)) * $this->rowsPerPage;
    }

    /**
     * Returns info string
     *
     * This method will firstly try to return `$this->info`.
     * If `$this->info` hasn't been set, then will return `$this->infoFormat`, which will be formatted via `sprintf($infoFormat, $totalRows, $totalPages)`
     *
     * @return string
     */
    public function info()
    {
        if ($this->info) {
            return $this->info;
        }

        return sprintf(_e($this->infoFormat), '<strong>' . $this->totalRows . '</strong>', '<strong>' . $this->totalPages . '</strong>');
    }

    /**
     * Returns sql LIMIT statement
     *
     * @return string
     */
    public function getSqlLimit()
    {
        $limit = '';
        if ($this->rowsPerPage) {
            $limit = ' LIMIT ' . $this->rowStart . ',' . $this->rowsPerPage;
        }

        return $limit;
    }

    /**
     * Returns sql GROUP BY statement
     *
     * @return string
     */
    public function getSqlGroupBy()
    {
        if ($this->groupBy) {
            return ' GROUP BY ' . $this->groupBy;
        }

        return '';
    }

    /**
     * Returns sql ORDER BY statement
     *
     * @return string
     */
    public function getSqlOrderBy()
    {
        // specify order by directly from search object
        if (!empty($this->searchOrderBy)) {
            return ' ORDER BY ' . $this->searchOrderBy;
        }

        $list = array();

        if ( !empty($this->primarySortField)) {
            foreach ($this->primarySortField as $idx => $name) {
                $dir = $this->primarySortDir[$idx];
                if ($this->sortDir !== $this->_origDir) { // when main direction has been changed
                    $dir = 'asc' === $dir ? 'desc' : 'asc'; // change primarySortDir as well
                }
                $list[] = '`' . $name . '` ' . $dir;
            }
        }

        $list[] = '`' . $this->sortField . '` ' . $this->sortDir;

        if ( !empty($this->secondSortField)) {
            foreach ($this->secondSortField as $idx => $name) {
                $dir = $this->secondSortDir[$idx];
                if ($this->sortDir !== $this->_origDir) { // when main direction has been changed
                    $dir = 'asc' === $dir ? 'desc' : 'asc'; // change secondSortDir as well
                }
                $list[] = '`' . $name . '` ' . $dir;
            }
        }

        return ' ORDER BY ' . implode(',', $list);

    }

    /**
     * Output pager links.
     *
     * @param boolean $showInfo Whether or not to display info text.
     * @param array $options
     *  * wrapperClass      => ''   // css class for widget container
     *  * paginationClass   => ''   // css class for widget
     *  * bootstrapVersion  => 3    // bootstrap version
     * @return void
     */
    public function pages($showInfo=false, $options=array())
    {
        $options = array_merge(array(
            'wrapperClass' => '',
            'paginationClass' => '', // bootstrap 3
            'bootstrapVersion' => 3
        ), $options);

        $wrapperClass = $paginationClass = $bootstrapVersion = NULL;
        extract($options, EXTR_IF_EXISTS);

        if ( 2 == $bootstrapVersion) {
            $wrapperClass = 'pagination ' . ($wrapperClass ? $wrapperClass : 'pagination-centered');
            $ulClass = '';
        } else {
            $wrapperClass = 'pagination-wrapper ' . ($wrapperClass ? $wrapperClass : 'text-center');
            $ulClass = 'pagination ' . $paginationClass;
        }
        if ($this->totalPages < 2) {
            if ($showInfo) {
                echo '<div class="', $wrapperClass, '"><p class="pagination-info">', $this->info(), '</p></div>';
            }

            return;
        }

        $params = $_GET;
        unset($params[$this->param]);
        $urlparams = http_build_query($params, '', '&amp;');

        $midNumber = floor($this->numPerPage / 2);
        $numStart = max(1, $this->currentPage - $midNumber);
        $numEnd = min($this->totalPages, $numStart + $this->numPerPage);

        echo '<div class="', $wrapperClass, '"><ul', ($ulClass ? ' class="' . $ulClass . '"' : ''), '>';

        $url = $urlparams
            ? '?' . $urlparams . '&amp;' . $this->param . '='
            : '?' . $this->param . '=';

        $disabled = array(
            '_url'  => '#',
            'class' => ' disabled'
        );

        // first page
        if (false !== $this->labels['first']) {
            if ($this->currentPage != 1) {
                $_url = $this->__pageUrl(1);
                $class = '';
            } else {
                extract($disabled);
            }
            echo '<li class="first', $class, '"><a href="', $_url, '">', $this->labels['first'], '</a></li>';
        }

        // previous page
        if ($this->currentPage > 1) {
            $_url = $this->__pageUrl(($this->currentPage-1));
            $class = '';
        } else {
            extract($disabled);
        }
        echo '<li class="previous', $class, '"><a href="', $_url, '">', $this->labels['prev'], '</a></li>';

        // page numbers
        if ($this->showNumbers) {
            foreach ( range($numStart, $numEnd) as $num):
                echo '<li',
                    ( $num == $this->currentPage ? ' class="active"' : '' ),
                    '><a class="visible-desktop" href="', $this->__pageUrl($num), '">', $num, '</a></li>';
            endforeach;
        }

        if ($this->showJumper) {
            echo '<li><span class="hidden-desktop"><select class="pagination-jumper" data-go-selected="', $this->__pageUrl('_-pageNum-_'), '">';
            foreach ( range($numStart, $numEnd) as $num):
                echo '<option value="', $num, '"',
                    ( $num == $this->currentPage ? ' selected="selected"' : '' ),
                    '>', sprintf(__('Page %s'), $num), '</option>';
            endforeach;
            echo '</select></span></li>';
        }

        // next page
        if ($this->currentPage < $this->totalPages) {
            $_url =  $this->__pageUrl(($this->currentPage+1));
            $class = '';
        } else {
            extract($disabled);
        }
        echo '<li class="next', $class, '"><a href="', $_url, '">', $this->labels['next'], '</a></li>';

        // last page
        if (false !== $this->labels['last']) {
            if ($this->currentPage != $this->totalPages) {
                $_url =  $this->__pageUrl($this->totalPages);
                $class = '';
            } else {
                extract($disabled);
            }
            echo '<li class="last', $class, '"><a href="', $_url, '">', $this->labels['last'], '</a></li>';
        }

        echo '</ul>';

        if ($showInfo) {
            echo '<p class="pagination-info">', $this->info(), '</p>';
        }

        echo '</div>';
    }

    /**
     * Return array of page links
     *
     * @return array
     */
    public function pageLinks()
    {
        $links = array();
        for ($i=1; $i<=$this->totalPages; $i++) {
            $links['page-' . $i] = str_replace('&amp;', '&', $this->__pageUrl($i));
        }
        return $links;
    }
    /**
     * Generates internal url for the page number.
     *
     * @param integer $pageNum The page number
     * @return string
     */
    private function __pageUrl($pageNum)
    {
        if (NULL === $this->segment) {
            $params = $_GET;
            unset($params[$this->param]);
            $urlparams = http_build_query($params, '', '&amp;');
            $url = $urlparams
                ? '?' . $urlparams . '&amp;' . $this->param . '='
                : '?' . $this->param . '=';

            return $url . $pageNum;
        }

        $urls = App::urls();
        $segments = $urls->allSegments();
        $segments[$this->segment] = $pageNum;

        return $urls->urlto(implode('/', $segments));
    }

    public function pageUrl($num)
    {
        return $this->__pageUrl($num);
    }

    /**
     * Generates sorting link HTML
     *
     * @param string $fieldName     DB Column to be sorted
     * @param string $displayName   Display label for the sorting link
     * @param array $attrs       HTML attributes for the sorting link
     * @param string $symbalAsc  Custom ascending icon HTML
     * @param string $symbalDesc Custom descending icon HTML
     * @return string
     */
    public function sortLink($fieldName, $displayName, $attrs=NULL, $symbalAsc=NULL, $symbalDesc=NULL)
    {
        static $url_part = NULL;

        if ($url_part === NULL) {
            $params = $_GET;
            unset($params[$this->paramSort]);
            if ( $url_part = http_build_query($params, '', '&amp;')) {
                $url_part = '?' . $url_part . '&amp;' . $this->paramSort . '=';
            } else {
                $url_part = '?' . $this->paramSort . '=';
            }
        }
        if ($fieldName === $this->sortField) {
            $symbal = ($this->sortDir === 'asc'
                ? ( $symbalAsc === NULL ? $this->symbalAsc : $symbalAsc)
                : ( $symbalDesc === NULL ? $this->symbalDesc : $symbalDesc) );
        } else {
            $symbal = null;
        }
        if ($fieldName === $this->sortField && $this->sortDir === 'asc') {
            $url = $url_part . $fieldName . '.desc'; // desc
        } else {
            $url = $url_part . $fieldName . '.asc'; // asc
        }
        echo '<a href="'.$url.'"'.($attrs ? ' '.$attrs : '').'>', $displayName, $symbal, '</a>';
    }

    /**
     * Returns the title text of current sorting column along with the HTML of sorting direction icon.
     *
     * @param string $symbalAsc Custom ascending icon HTML
     * @param string $symbalDesc Custom descending icon HTML
     * @return string
     */
    public function currentSort($symbalAsc=NULL, $symbalDesc=NULL)
    {
        $symbal = ($this->sortDir === 'asc'
            ? ( $symbalAsc === NULL ? $this->symbalAsc : $symbalAsc)
            : ( $symbalDesc === NULL ? $this->symbalDesc : $symbalDesc) );

        return $this->sortable[$this->sortField] . $symbal;
    }

} // END class
