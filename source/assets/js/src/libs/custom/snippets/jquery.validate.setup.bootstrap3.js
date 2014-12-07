(function($){

  // jquery validator
  if ( $.isFunction($.fn.validate)) {
    $.validator.setDefaults({
      onfocusout: function(element, event) {
        this.element(element);
      },
      errorElement: 'span',
      errorClass: 'help-block text-danger',
      errorPlacement: function(label, $element) {
        if ($element.hasClass('ckeditor') ) {
            label.insertAfter($element.parent().children().last());
            return;
        }

        // append to ...
        $target = $element.closest('.form-controls');
        if ($target.length) {
            label.appendTo($target);
            return;
        }

        // after ...
        $target = $element.closest('.input-group');
        if ($target.length) {
            label.insertAfter($target);
            return;
        }

        // checkbox ...
        if ( $element.is(':checkbox') && $element.closest('label').length) {
            label.insertAfter($element.closest('label'));
            return;
        }

        // default
        label.insertAfter($element);
      },
      highlight: function(element, errorClass, validClass) {
        $(element).closest('.form-group').removeClass('has-success').addClass('has-error');
      },
      unhighlight: function(element, errorClass, validClass) {
        var $element = $(element),
            $target = $element.closest('.form-group');
        if ( !$target.find('span.help-block.text-danger:visible').length ) {
          $target.removeClass('has-error').addClass('has-success');
        }
      }
    });


    $.validator.mySetupFormValidator = function(form) {
      $(form).
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
            validate();
    };

    $.validator.mySetupFormValidator('form');
  }

}(jQuery));
