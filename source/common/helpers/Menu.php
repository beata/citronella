<?php
interface IMenuListable
{
    public static function fetchMenuItems($pathPrefix);
}

abstract class Menu
{
    /**
     * Stores menu items.
     *
     * @var MenuItem[]
     */
    protected $_items;

    /**
     * assign MenuItem to $_items;
     */
    abstract protected function load();

    public function get($path, $dataKey=NULL)
    {
        foreach ( $this->_items as $item) {
            if ( $item->path === $path) {
                return (NULL === $dataKey ? $item : $item->{$dataKey});
            }
        }
        return NULL;
    }
    public function getItems()
    {
        return $this->_items;
    }

    protected function currentItem()
    {
        $urls = App::urls();
        if (!$path = $urls->segment(0)) {
            return;
        }
        $queryString = rtrim($urls->getQueryString(), '/') . '/';
        foreach ($this->_items as $item) {
            if (!$item->path) {
                continue;
            }
            if (
                ($path === $item->path) ||
                ($item->path . '/' === substr($queryString, 0, strlen($item->path)+1))
            ) {
                return $item;
            }
        }
    }
    protected function activeCurrent(MenuItem $item)
    {
        $selfId = $item->id;

        $list = array();
        $item = $this->_items[$selfId];
        $item->active = true;
        while ($item->parent_id && isset($this->_items[$item->parent_id]))  {
            $list[] = $item->parent_id;
            $item = $this->_items[$item->parent_id];
            $item->active = true;
        }
        return $list;
    }

    public function toAcl($appendRules=array())
    {
        $allow = array();
        foreach ($this->_items as $item) {
            if ( !$item->path) {
                continue;
            }
            if ( FALSE === strpos($item->path, '/')) {
                $allow[] = $item->path . '/*';
                continue;
            }
            $allow[] = $item->path;
        }

        if (!empty($appendRules)) {
            $allow = array_merge($allow, $appendRules);
        }
        return $allow;
    }


}
class MenuItem
{
    /**
     * Title of this menu item.
     *
     * @var string
     */
    public $name;

    /**
     * Internal routing path of this menu item.
     *
     * @var string
     */
    public $path;

    /**
     * Icon of this menu item.
     *
     * @var string
     */
    public $icon;

    /**
     * External url of this menu item.
     *
     * @var string
     */
    public $url;

    /**
     * Stores submenu of this menu item.
     *
     * @var MenuItem[]
     */
    public $items = array();

    public function __construct($name=NULL, $path=NULL)
    {
        if(func_num_args()) {
            $this->name = $name;
            $this->path = $path;
        }
    }
}
