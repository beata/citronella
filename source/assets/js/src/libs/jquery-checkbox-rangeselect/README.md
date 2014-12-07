jquery-checkbox-rangeselect
===========================

A jQuery plugin that let user to select a range of checkboxes via shift+click.

JavaScript
```javascript
$('#list').
    rangeselect({
        checkbox: '.select',
        row: 'li'
    });
```


HTML
```html
<ul id="list">
    <li><input class="select" type="checkbox" name="select[]" /></li>
    <li><input class="select" type="checkbox" name="select[]" /></li>
    <li><input class="select" type="checkbox" name="select[]" /></li>
</ul>
```


HTML with options
```html
<ul id="list" data-rangeselect-row="li" data-rangeselect-checkbox=".select">
    <li><input class="select" type="checkbox" name="select[]" /></li>
    <li><input class="select" type="checkbox" name="select[]" /></li>
    <li><input class="select" type="checkbox" name="select[]" /></li>
</ul>
```


## Options

* `checkbox`  
    Default: `.select`  
    The jQuery selector string of the checkboxes in the container.

* `row`  
    Default: `tr`  
    The jQuery selector string of row elements in the container. We use the row index to determine which direction to select.
