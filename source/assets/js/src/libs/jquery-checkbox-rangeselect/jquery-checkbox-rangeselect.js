(function($){
  // Range Select Plugin
  var RangeSelect = function( element, options ) {

    var that = this;

    this.chkStart = null;

    var checkboxClickEvent = function(event) {
      if ( ! event.shiftKey || ! that.chkStart ) {
        that.chkStart = this;
        return;
      }
      var $startRow = $(that.chkStart).closest( options.row),
          $endRow = $(this).prop( 'checked', that.chkStart.checked ).closest( options.row),
          offset = $endRow.index() - $startRow.index(),
          nodes;

      if ( Math.abs(offset) <= 1) {
        that.chkStart = null;
        return;
      }
      if ( offset > 0 ) {
        nodes = $startRow.nextUntil( $endRow );
      } else {
        nodes = $startRow.prevUntil( $endRow );
      }
      nodes.find('input'+options.checkbox+':checkbox:not(:disabled)').prop('checked', that.chkStart.checked );
      that.chkStart = null;
    };

    $( element ).
      on( 'click.rangeselect', 'input'+options.checkbox+':checkbox', checkboxClickEvent );
  };

  $.fn.rangeselect = function( options ) {
    return this.each(function(){
      var $element = $(this),
          rangeselect = $element.data('rangeselect');

      if (!rangeselect) {
        rangeselect = new RangeSelect( this, $.extend({
          checkbox: $element.data('rangeselectCheckbox') || '.select',
          row: $element.data('rangeselectRow') || 'tr'
        }, (options||{}) ));
        $element.data('rangeselect', rangeselect);
      }
    });
  };

}(jQuery));
