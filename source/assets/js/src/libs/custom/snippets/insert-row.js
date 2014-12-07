(function($) {
  if ( !window.Handlebars ) {
    return;
  }
  $('body').
    on('click', 'button.js-insert-row, button.insert-row', function(event){
      var $btn = $(this),
          opt = {
            times: $btn.data('insertTimes') || 1,
            template: $btn.data('template'),
            target: $btn.data('target'),
            model: $btn.data('model'),
            totalRows: $btn.data('totalRows'),

            templateInst: $btn.data('templateInst'),
            $target: $btn.data('$target')
          },
          view = opt.model || {},
          curRow;

      if (!opt.templateInst) {
        opt.templateInst = Handlebars.compile($(opt.template).html());
        $btn.data('templateInst', opt.templateInst);
      }

      if ( ! opt.$target ) {
        opt.$target = (opt.target && $(opt.target)) || $btn.parents('table:first').find('tbody');
        $btn.data('$target', opt.$target);
      }

      for ( var i=0; i<opt.times; i++) {
        curRow = ($btn.data('newRows')||0)+1,
        $btn.data('newRows', curRow);
        var data = $.extend( {}, view, {
          id: new Date().getTime() + '' + curRow,
          isNew: 1,
          sort: opt.totalRows+curRow
        });
        opt.$target.append(opt.templateInst(data)).find('>:last').trigger('added.insert-row');
      }

      opt.times && opt.$target.trigger('insert-row.updated');

      event.stopPropagation();
    });

  var $defaultList = $('button.js-insert-row[data-default-list], button.insert-row[data-default-list]');
  $defaultList.length && $defaultList.each(function(){
    var $btn = $(this),
        list = $btn.data('defaultList') || [];
    if (!list.length) {
      return;
    }
    var opt = {
          $template: $btn.data('$template'),
          $target: $btn.data('$target'),
          target: $btn.data('target'),
          template: $btn.data('template'),
          model: $btn.data('model'),
          totalRows: $btn.data('totalRows')
        },
        view = opt.model || {},
        curRow;

    if ( ! opt.$template ) {
      opt.$template = $(opt.template);
    }
    if ( ! opt.$target ) {
      opt.$target = (opt.target && $(opt.target)) || $btn.parents('table:first').find('tbody');
    }

    $.each(list, function(i, v){
        curRow = ($btn.data('newRows')||0)+1,
        $btn.data('newRows', curRow);
        var data = $.extend( {}, view, this);
        opt.$template.mustache(data).appendTo(opt.$target);
    });
    list.length && opt.$target.trigger('insert-row.updated');
  });

  var $autoInsert = $('button.js-insert-row[data-auto-insert], button.insert-row[data-auto-insert]');
  $autoInsert.length && $autoInsert.each(function(){
    var $btn = $(this),
        times = $btn.data('autoInsert') || 1;
    for ( var i=0; i<times; i++) {
      $btn.trigger('click');
    }
  });

}(jQuery));

