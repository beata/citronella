<?php
function show_menu($items)
{
    $urls = App::urls();
    $current = $urls->segment(0);
    $isFirst = true;

    foreach ($items as $item) {
        $classes = array();
        if ( $isFirst) { $classes[] = 'first'; }
        if ( !empty($item->active)) { $classes[] = 'active'; }
        $has_subitems = !empty($item->items);

        $url = $urls->urlto($item->path);
        $linkAttrs = '';
        if ($has_subitems) {
            $classes[] = 'dropdown';
            $subitemId = 'subitem-' . $item->id;
            $linkAttrs = ' href="#" data-toggle="dropdown"';
        } else {
            $linkAttrs = ' href="' . $url . '"';
        }

        echo '<li'
            . ( isset($classes[0]) ? ' class="' . implode(' ', $classes) . '"' : '')
            . '><a ' . $linkAttrs . '>';

        if ( !empty($item->icon) ) {
            echo '<i class="glyphicon glyph', $item->icon, ' padding-right-half"></i>';
        }
        echo HtmlValueEncode($item->name);

        if ( $has_subitems ) {
          echo '<i class="pull-right glyphicon glyphicon-chevron-' . (empty($item->active) ? 'right' : 'down') . '"></i>';
        }
        echo '</a>';
        $isFirst = false;

        if ( $has_subitems ) {
          echo '<ul id="' . $subitemId . '" class="dropdown-menu" role="menu">';
          show_menu($item->items);
          echo '</ul>';
        }
        echo '</li>';
    }
}
