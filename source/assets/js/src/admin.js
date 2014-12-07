(function($, document) {
  var ui = {
    CLICK_TOUCH: 'click',
    click_event: 'click',
    init: {}
  };

  var _helepr = {};

  _helepr.validInput = function () {
    var $input = $(this);
    if ( !$input.is('input')) {
      $input = $input.find('input:first');
    }
    $input.valid();
  };



  ui.init.rangeselect = function() {
    $('ul.list-checkable').rangeselect({ row: 'li', checkbox: '.select' });
    $('table.table').rangeselect({ row: 'tr', checkbox: '.select' });
  };

  ui.init.tooltip = function ($scope) {
    $.fn.tooltip && ($scope ? $scope.find('[data-rel="tooltip"]') : $('[data-rel="tooltip"]')).tooltip({
      container: 'body'
    });
  };

  ui.init.rangeselect();
  ui.init.tooltip();

  $(function () {
  });


}(jQuery, document));
