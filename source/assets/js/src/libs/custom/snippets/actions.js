(function($) {

  $.ajaxSetup({
    error: function (jqXHR, textStatus, errorThrown) {
      var message = 'Request failed: ' + textStatus + ' '+ errorThrown,
          content;
      if (jqXHR.responseJSON) {
        content = jqXHR.responseJSON;
      } else if (jqXHR.responseText) {
        try { content = $.parseJSON(jqXHR.responseText); } catch(e) {}
      }
      if (content && content.error) {
        message += "\n" + content.error.message;
      }

      alert(message);
    }
  });

  // utility
  var $body = $('body').
      on('change', 'select[data-go-selected]', function(event){
        var $_ = $(this),
            value = $_.val();
        if ( !value) {
          return;
        }
        var url = ($_.data('goSelected') || '').replace('_-pageNum-_', value);
        window.location.href = url;
      }).
      on('click', '[data-confirm]', function(event) {
        var $_ = $(this);
        if ( !$_.is('[data-confirm-only]') && $_.is('[data-confirm]') && ! $_.parents( $_.data('parent') || 'form:first').find('input.select:checked').length) {
          alert( $_.data('selectRequired') || '請選擇項目');
          return false;
        }
        return confirm(($_.data('confirm') || '確定？'));
      }).
      on('click', '[data-delete-confirm]', function() {
        var $_ = $(this);
        if ($_.is('[data-confirm-selected]') && ! $_.parents( $_.data('parent') || 'form:first').find('input.select:checked').length) {
          alert( $_.data('selectRequired') || '請選擇要刪除的項目');
          return false;
        }
        if (!confirm(($_.data('deleteConfirm') || '確定要刪除？'))) {
          return false;
        }

        var deleteUrl = $_.data('deleteUrl');
        if (!deleteUrl) {
          return true;
        }
        $.
          ajax(deleteUrl, {
            dataType: 'json',
            data: $_.data('deleteData'),
            type: 'POST',
            async: false
          }).
          done(function (response) {
            if (response.error) {
              return alert(response.error.message);
            }
            window.location.href = window.location.href;
          });
      }).
      on('click', '[data-remove-parent]', function(event){
        var $_ = $(this);
        $_.parents($_.data('removeParent')).remove();
        event.stopPropagation();
      }).
      on('click', '[data-dismiss=alert]', function(event) {
        $(this).parents('.alert:first').remove();
        event.preventDefault();
        event.stopPropagation();
      }).
      on('click', '[data-toggle="element"]', function(event) {
        var $_ = $(this),
            target = $_.data('target'),
            closest = $_.data('closest'),
            $target = (closest && $_.closest(closest).find(target)) || $(target);
        if ( $_.is(':checkbox')) {
          var toggle = $_.prop('checked');
          if ( $_.is('[data-inverse]')) {
            toggle = !toggle;
          }
          if ($_.is('[data-clear-checkbox-when-hide]')) {
            !toggle && $target.find('input:checkbox').prop('checked', false);
          }
          $target.toggle( toggle ).trigger( ($target.is(':hidden') ? 'hidden' : 'shown') );

        } else {
          $target.toggle().trigger( ($target.is(':hidden') ? 'hidden' : 'shown') );
        }
        if ( $_.data('preventDefault')) {
          event.preventDefault();
        }
        event.stopPropagation();
      }).
      on('click', '[data-toggle="checkbox"]', function(event) {
        var $_ = $(this),
            target = ($_.data('target') || 'input.select'),
            checked = ($_.is('[data-checkbox-inverse]') ? !this.checked : this.checked);
        $_.parents( $_.data('parent') || 'form:first').find(target + ':not(:disabled)').prop('checked', checked);
      }).
      on('click', '[data-toggle="buttons-radio"]', function(event){
        var $_ = $(this),
            name = $_.data('toggleName'),
            $btn = $(event.target),
            $form;
        if ( ! name || ! $btn.is(':button')) {
          return null;
        }
        $form = $_.parents('form:first');
        $form.find('input[name="'+name+'"]').val( $btn.val() );
        if ( typeof $_.data('submitNow') !== 'undefined') {
          $form.trigger('submit');
        }
        return null;
      }).
      on('click', '[data-open]', function(event) {
        var $_ = $(this),
            target = $_.data('open'),
            parent = $_.data('openParent');
        ((parent && $_.parents(parent).find(target)) || $(target)).show().trigger('shown');
      }).
      on('click', '[data-close]', function(event) {
        var $_ = $(this),
            target = $_.data('close'),
            parent = $_.data('closeParent');
        ((parent && $_.parents(parent).find(target)) || $(target)).hide().trigger('hidden');
      }).
      on('change', 'select[data-submit-now]', function(event) {
        $(this).parents('form:first').trigger('submit');
        return null;
      }).
      on('click', '[data-submit-now]', function(event) {
        var $_ = $(this);
        if ( 'SELECT' === this.tagName || $_.data('toggle') === 'buttons-radio') {
          return null;
        }
        $_.parents('form:first').trigger('submit');
        return null;
      }).
      on('click', 'button.btn-switchable', function(event) {
        var $_ = $(this),
            data = $_.data();
        if ( !data.remote || data.busy ) { return null; }
        $_.data('busy', 1);
        $.post(data.remote, data, function(response) {
          $_.data('busy', 0);
          if ( response.error ) { return alert(response.error.message); }
          $_.replaceWith( response.html );
          return null;
        }, 'json');
        event.stopPropagation();
        return null;
      }).
      on('click', '[data-move]', function(event){
        var $_ = $(this),
            direction = $_.data('move'),
            sortName = $_.data('field') || 'sort',
            $first = $_.parents($_.data('parent') || 'li:first'),
            $second = $first[(direction === 'up' ? 'prev' : 'next')](),
            $firstSort = $first.find('input[name$="['+sortName+']"]'),
            $secondSort = $second.find('input[name$="['+sortName+']"]'),
            firstSort = $firstSort.val(),
            secondSort = $secondSort.val();
        $firstSort.val(secondSort);
        $secondSort.val(firstSort);
        $first[(direction === 'up' ? 'after' : 'before')]($second);
      }).
      on('click', '[data-history-back]', function (event) {
        event.preventDefault();
        window.history.back($(this).data('historyBack') || -1);
      }).
      on('click', '[data-open-window]', function(event){
        var $button = $(this);
            options = $button.data(),
            url = options.url || $button.attr('href'),
            width = options.width || 300;
            height = options.height || 300,
            autofocus = options.focus || true,
            leftPos = ($(window).width() - width) / 2;
            topPos = ($(window).height() - height) / 2;

        if ($button.is('a')) {
          event.preventDefault();
        }

        var w = window.open(url, options.name || 'dialog', 'menubar=0,toolbar=0,resizable=1,width=' + width + ',height=' + height + ',top=' + topPos + ',left=' + leftPos);
        autofocus && w.focus();
      }).
      on('click', '[data-close-window]', function(event){
        window.close();
      });

  if ( ! Modernizr.touch ) {
    $body.
      // btn-switchable
      on('mouseenter mouseleave', 'button.btn-switchable', function(event) {
        $(this).toggleClass('btn-switchable-active');
      }).
      on('mouseenter mouseleave', '[data-toggle="hover"]', function(event) {
        var $_ = $(this),
            $target = $_.find( $_.data('target') );
        $target.toggleClass('hide', (event.type === 'mouseleave')).trigger( ($target.is(':hidden') ? 'hidden' : 'shown') );
      });

  } else {

    $('li.hoverswipe').
      on('swipe', function(event){
        var $_ = $(this),
            $target;
        if ( 'left' === event.direction ) {
          $_.trigger('tap.closetarget');
          return null;
        }
        if ( 'right' !== event.direction ) {
          return null;
        }
        $target = $_.find( $_.data('target') ).removeClass('hide').trigger('shown');
        $_.one('tap.closetarget', function(){ $target.addClass('hide').trigger('hidden');});
        return null;
      });
  }

  $('#listForm').
    submit(function(event){
      var $_ = $(this);
      if ($_.is('[data-confirm-selected]') && ! $_.find('input.select:checked').length) {
        alert( $_.data('confirmSelected') || '請選擇項目');
        return false;
      }

      var $selActions = $_.find('#selActions');
      if ($selActions.length) {
        var opt = $selActions.find('option[value="'+$selActions.val()+'"]').data();
        if ( opt && opt.confirm ) {
          if (!confirm(opt.confirm)) {
            return false;
          }
        }
      }

      if ($_.data('validator')) {
        $_.valid() && $_.get(0).submit();
      } else {
        $_.get(0).submit();
      }
    });

  // list actions
  var $selActions = $('#selActions');
  if ( $selActions.length ) {
    $selActions.
      change(function() {
        var opt = $selActions.find('option[value="'+$selActions.val()+'"]').data(),
            $subActions = $selActions.nextAll('.subactions');

        $subActions.length && $subActions.not('.hide').addClass('hide').end();
        if ( ! opt ) { return null; }
        opt.open && $subActions.filter(opt.open).removeClass('hide');
        return null;
      }).
      trigger('change');
  }

  var $toggleBySelectedOption = $('select.toggle-element');
  if ( $toggleBySelectedOption.length ) {
    $toggleBySelectedOption.
      change(function(){
        var $_ = $(this),
            data = $_.find('option[value="'+$_.val()+'"]').data();
        if ( data.open ) {
          $(data.open).removeClass('hide').trigger('shown');
        }
        if ( data.close ) {
          $(data.close).addClass('hide').trigger('hidden');
        }
      });
  }

}(jQuery));
