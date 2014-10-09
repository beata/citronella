<?php
/**
 * File class file
 *
 * @package Helper.Menu
 * @license MIT
 * @file
 */

/**
 * IMenuListable
 */
interface IMenuListable
{
    /**
     * Should return a list of MenuItems
     *
     * @param string $pathPrefix The path to be prepended to each returning MenuItems.
     * @return MenuItem[]
     */
    public static function fetchMenuItems($pathPrefix);
}

/**
 * Menu class
 */
abstract class Menu
{
    /**
     * Stores menu items.
     * @var MenuItem[]
     */
    protected $_items = array();

    /**
     * This method assigns MenuItem to `$this->_items`;
     */
    abstract protected function load($role=NULL);

    /**
     * Get MenuItem property by 'path'
     *
     * @param string $path The MenuItem 'path' property.
     * @param string $dataKey The property name.
     * @return string|null
     */
    public function get($path, $dataKey=NULL)
    {
        foreach ( $this->_items as $item) {
            if ( $item->path === $path) {
                return (NULL === $dataKey ? $item : $item->{$dataKey});
            }
        }
        return NULL;
    }
    /**
     * Returns an array of all menu items.
     *
     * @return array
     */
    public function getItems()
    {
        return $this->_items;
    }

    /**
     * Returns current visiting menu item
     *
     * @return MenuItem|null
     */
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
            if (!empty($item->items)) {
                foreach ($item->items as $subitem) {
                    if (
                        ($path === $subitem->path) ||
                        ($subitem->path . '/' === substr($queryString, 0, strlen($subitem->path)+1))
                    ) {
                        return $subitem;
                    }
                }
            }
        }
    }

    /**
     * Active the menu item, as well as its parent nodes.
     *
     * @param MenuItem $item The item to be activated.
     * @return MenuItem[] a menu item list from current item to the root.
     */
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

    /**
     * Returns menu items as an acl path list.
     *
     * @param array $appendRules Additional rules to be append to the result.
     * @return array
     */
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

/**
 * MenuItem class
 */
class MenuItem
{
    /**
     * Title of this menu item.
     * @var string
     */
    public $name;

    /**
     * If this MenuItem is an internal link, specific routing path here.
     * @var string
     */
    public $path;

    /**
     * If this MenuItem is an external link, specific the url here.
     * @var string
     */
    public $url;

    /**
     * Stores icon css class.
     * @var string
     */
    public $icon;

    /**
     * Submenu list.
     * @var MenuItem[]
     */
    public $items = array();

    /**
     * The constructor for MenuItem
     *
     * @param string $name Display title of the menu item
     * @param string $path Path to the menu item
     * @return void
     */
    public function __construct($name=NULL, $path=NULL, $items=array())
    {
        if(func_num_args()) {
            $this->name = $name;
            $this->path = $path;
            if (!empty($items)) {
                $this->items = $items;
            }
        }
    }
}
