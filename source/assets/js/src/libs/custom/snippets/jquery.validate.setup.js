(function($){

  var $forms = $('form').
    on('submit.postonce', function(event){
      var $_ = $(this);
      if ( $_.data('postonce')) {
        event.preventDefault();
      } else {
        $_.data('postonce', true);
      }
    });


  // jquery validator
  if ( $.isFunction($.fn.validate)) {
    $.validator.setDefaults({
      onfocusout: function(element, event) {
        this.element(element);
      },
      errorElement: 'span',
      errorClass: 'help-inline text-error',
      errorPlacement: function(label, $element) {
        if ($element.hasClass('ckeditor') ) {
            label.insertAfter($element.parent().children().last());
        } else if ( $element.parents('.control-group:first').length ) {
            label.appendTo($element.parents('.control-group:first'));
        } else if ( $element.parents('.input-group:first').length ) {
            label.appendTo($element.parents('.input-group:first'));
        } else if ( $element.parents('.input-append:first').length ) {
            label.insertAfter($element.parents('.input-append:first'));
        } else if ( $element.parents('.input-prepend:first').length ) {
            label.insertAfter($element.parents('.input-prepend:first'));
        } else if ( $element.is(':checkbox') && $element.parents('label:first').length) {
            label.insertAfter($element.parents('label:first'));
        } else {
            label.insertAfter($element);
        }
      },
      highlight: function(element, errorClass, validClass) {
        var $element = $(element),
            $target = $element.parents('.input-group:first');
        if ( !$target.length ) {
          $target = $element.parents('.control-group:first');
        }
        $target.removeClass('success').addClass('error');
      },
      unhighlight: function(element, errorClass, validClass) {
        var $element = $(element),
            $target = $element.parents('.input-group:first');
        if ( !$target.length ) {
          $target = $element.parents('.control-group:first');
        }
        if ( !$target.find('span.help-inline.text-error:visible').length ) {
          $target.removeClass('error').addClass('success');
        }
      }
    });

    $forms.
      filter('[data-validate]').
        has('textarea.ckeditor').
          on('submit.validate-ckeditor', function(){
            $(this).find('textarea.ckeditor').each(function() {
              CKEDITOR.instances[this.name].updateElement();
            });
          }).end().
        has('[remote]').
          on('submit.validate-remote', function(){
            $.ajaxSetup({async: false});
            $(this).find('[remote]').trigger('focusout');
          }).end().
        validate({
          invalidHandler: function(form, validator) {
            $(this).data('postonce', false);
          }
        });
  }

}(jQuery));
