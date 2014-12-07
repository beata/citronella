(function($){
  if ( ! $.isFunction($.fn.validate)) {
    return null;
  }
  function stripHtml(value) {
    // remove html tags and space chars
    return value.replace(/<.[^<>]*?>/g, ' ').replace(/&nbsp;|&#160;/gi, ' ')
    // remove punctuation
    .replace(/[.(),;:!?%#$'"_+=\/\-]*/g,'');
  }

  $.validator.addMethod("checkboxRequired", function(value, element, params) {
    var name = $(element).attr('checkboxRequiredName'),
        length = params || 1;
    return !!$(this.currentForm).find('input:checkbox[name^="'+name+'"]:checked').length;
  }, $.validator.format( $.validator.messages.checkboxRequired || "Please select at least {0} option(s)"));


  $.validator.addMethod("maxWords", function(value, element, params) {
    return this.optional(element) || stripHtml(value).match(/\b\w+\b/g).length <= params;
  }, $.validator.format( $.validator.messages.maxWords || "Please enter {0} words or less."));

  $.validator.addMethod("minWords", function(value, element, params) {
    return this.optional(element) || stripHtml(value).match(/\b\w+\b/g).length >= params;
  }, $.validator.format( $.validator.messages.minWords || "Please enter at least {0} words."));

  $.validator.addMethod("rangeWords", function(value, element, params) {
    var valueStripped = stripHtml(value);
    var regex = /\b\w+\b/g;
    return this.optional(element) || valueStripped.match(regex).length >= params[0] && valueStripped.match(regex).length <= params[1];
  }, $.validator.format( $.validator.messages.rangeWords || "Please enter between {0} and {1} words."));

  $.validator.addMethod("letterswithbasicpunc", function(value, element) {
    return this.optional(element) || /^[a-z\-.,()'\"\s]+$/i.test(value);
  }, $.validator.messages.letterswithbasicpunc || "Letters or punctuation only please");

  $.validator.addMethod("alphanumeric", function(value, element) {
    return this.optional(element) || /^\w+$/i.test(value);
  }, $.validator.messages.alphanumeric || "Letters, numbers, and underscores only please");

  $.validator.addMethod("lettersonly", function(value, element) {
    return this.optional(element) || /^[a-z]+$/i.test(value);
  }, $.validator.messages.lettersonly || "Letters only please");

  $.validator.addMethod("nowhitespace", function(value, element) {
    return this.optional(element) || /^\S+$/i.test(value);
  }, $.validator.messages.nowhitespace || "No white space please");

  $.validator.addMethod("zipcodeTW", function(value, element) {
    return this.optional(element) || /^\d{3}$|^\d{5}$/.test(value)
  }, $.validator.messages.zipcodeTW || "The specified TW ZIP Code is invalid");

  $.validator.addMethod("integer", function(value, element) {
    return this.optional(element) || /^-?\d+$/.test(value);
  }, $.validator.messages.integer || "A positive or negative non-decimal number please");

  $.validator.addMethod("time", function(value, element) {
    return this.optional(element) || /^([0-1]\d|2[0-3]):([0-5]\d)$/.test(value);
  }, $.validator.messages.time || "Please enter a valid time, between 00:00 and 23:59");

  $.validator.addMethod("time12h", function(value, element) {
    return this.optional(element) || /^((0?[1-9]|1[012])(:[0-5]\d){0,2}(\ [AP]M))$/i.test(value);
  }, $.validator.messages.time12h || "Please enter a valid time, between 00:00 am and 12:00 pm");


  var isValidPhoneNumber = function(number) {
    return number.length >= 9 && number.match(/^(\d{2}-?\d{3,4}-?\d{3,4}|\d{3}-?\d{3}-?\d{3}|\(\d{2}\)\d{3,4}-?\d{3,4}|\(\d{3}\)\d{3}-?\d{3})(\s*#\d+)?$/);
  };
  var isValidMobileNumber = function (number) {
    return number.length >= 9 && number.match(/^0?9\d{8}$/);
  };

  /**
   * matches TW telephone number:
   *
   * 061234567 or
   * 0212345678 or
   * 035123456
   * 035123456#123
   * 035123456 #123
   *
   * 06-123-4567 or
   * 02-1234-5678 or
   * 035-123-456
   *
   * (06)1234567 or
   * (02)12345678 or
   * (035)123456
   *
   * (06)123-4567 or
   * (035)123-456
   *
   * (06)1234-567 or
   * (02)1234-5678
   *
   * but not
   * (02)123-45678
   * and not
   * (035)1234-56
   */
  $.validator.addMethod("phoneTW", function(phone_number, element) {
    phone_number = phone_number.replace(/\s+/g, "");
    return this.optional(element) || isValidPhoneNumber(phone_number);
  }, $.validator.messages.phoneTW || "Please specify a valid phone number");

  $.validator.addMethod('mobileTW', function(phone_number, element) {
    phone_number = phone_number.replace(/\s+|-/g,'');
    return this.optional(element) || isValidMobileNumber(phone_number);
  }, $.validator.messages.mobileTW || 'Please specify a valid mobile number');

  $.validator.addMethod("phoneMobileTW", function(phone_number, element) {
    phone_number = phone_number.replace(/\s+/g, "");
    return this.optional(element) || isValidPhoneNumber(phone_number) || isValidMobileNumber(phone_number);
  }, $.validator.messages.phoneMobileTW || "Please specify a valid telephone/mobile number");

  $.validator.addMethod('taxNumberTW', function(number, element) {
    var isValid = function(number) {
      if ( number.length !== 8) {
        return false;
      }

      var tbNum = [1,2,1,2,1,2,4,1],
          multiple = 0,
          sum = 0,
          i = 0,
          tbNumLength = tbNum.length,
          result = 0;

      for ( i=0; i<tbNumLength; i++) {
        multiply = number.charAt(i) * tbNum[i];
        sum += Math.floor(multiply/10) + (multiply%10);
      }

      result = sum%10;
      return ( 0 === result || (9 === result && 7 === number.charAt(6) ));
    };
    return this.optional(element) || number.length == 8 && isValid(number);
  }, $.validator.messages.taxNumberTW || 'Please specify a valid tax number');


  var checkIdNumberTW = function(number, isArcNumber) {

      number = number.toUpperCase();

      var pattern = !isArcNumber ? new RegExp('^[A-Z][12][0-9]{8}$') : new RegExp('^[A-Z][A-D][0-9]{8}$');

      if  (!(pattern.test(number))) {
        return false;
      }

      var cities = {
          A:10, B:11, C:12, D:13, E:14, F:15, G:16, H:17, I:34, J: 18,
          K:19, L:20, M:21, N:22, O:35, P:23, Q:24, R:25, S:26, T:27,
          U:28, V:29, W:32, X:30, Y:31, Z:33
        },
        sum = 0;

      // 計算縣市加權
      var city = cities[number.substr(0, 1)];
      sum += Math.floor(city / 10) + (city % 10 * 9);

      // 計算性別加權
      if ( !isArcNumber ) {
        sum +=  parseInt(number.substr(1, 1), 10) * 8;
      } else {
        var gender = cities[number.substr(1,1)];
        sum += (gender % 10 * 8);
      }

      // 計算中間值的加權
      for ( var i=2; i<=8; i++) {
        sum +=  parseInt(number.substr(i, 1), 10) * (9-i);
      }

      // 加上檢查碼
      sum += parseInt(number.substr(9,1), 10);

      return (sum % 10 === 0);
  };

  $.validator.addMethod('idNumberTW', function(number, element) {
    return this.optional(element) || checkIdNumberTW(number);
  }, $.validator.messages.idNumberTW || 'Please specify a valid id number');

  $.validator.addMethod('passportNumberTW', function(number, element) {
    var isValid = function(number) {
      return number.length <= 20;
    };
    return this.optional(element) || isValid(number.toUpperCase());
  }, $.validator.messages.passportNumberTW || 'Please specify a valid passport number');

  $.validator.addMethod('arcNumberTW', function(number, element) {
    return this.optional(element) || checkIdNumberTW(number, true);
  }, $.validator.messages.arcNumberTW || 'Please specify a valid arc number');

  /**
   * Return true if the field value matches the given format RegExp
   *
   * @example $.validator.methods.pattern("AR1004",element,/^AR\d{4}$/)
   * @result true
   *
   * @example $.validator.methods.pattern("BR1004",element,/^AR\d{4}$/)
   * @result false
   *
   * @name $.validator.methods.pattern
   * @type Boolean
   * @cat Plugins/Validate/Methods
   */
  $.validator.addMethod("pattern", function(value, element, param) {
    if (this.optional(element)) {
      return true;
    }
    if (typeof param === 'string') {
      param = new RegExp('^(?:' + param + ')$');
    }
    var valid = param.test(value),
        message;
    if (!valid && (message = $(element).data('message'))) {
      $.validator.messages.pattern = message;
    }
    return valid;
  }, $.validator.messages.pattern || "Invalid format.");
  /*
   * Lets you say "at least X inputs that match selector Y must be filled."
   *
   * The end result is that neither of these inputs:
   *
   *  <input class="productinfo" name="partnumber">
   *  <input class="productinfo" name="description">
   *
   *  ...will validate unless at least one of them is filled.
   *
   * partnumber:  {require_from_group: [1,".productinfo"]},
   * description: {require_from_group: [1,".productinfo"]}
   *
   */
  $.validator.addMethod("require_from_group", function(value, element, options) {
    var validator = this;
    var myOptions = $.isArray(options) ? options : options.split('|');
    var selector = myOptions[1];
    var validOrNot = $(selector, element.form).filter(function() {
      return validator.elementValue(this);
    }).length >= parseInt(myOptions[0], 10);

    /*
    if(!$(element).data('being_validated')) {
      var fields = $(selector, element.form);
      fields.data('being_validated', true);
      fields.valid();
      fields.data('being_validated', false);
    }
    */
    return validOrNot;
  }, $.format( $.validator.messages.require_from_group || "Please fill at least {0} of these fields."));
  /*
   * Lets you say "either at least X inputs that match selector Y must be filled,
   * OR they must all be skipped (left blank)."
   *
   * The end result, is that none of these inputs:
   *
   *  <input class="productinfo" name="partnumber">
   *  <input class="productinfo" name="description">
   *  <input class="productinfo" name="color">
   *
   *  ...will validate unless either at least two of them are filled,
   *  OR none of them are.
   *
   * partnumber:  {skip_or_fill_minimum: [2,".productinfo"]},
   *  description: {skip_or_fill_minimum: [2,".productinfo"]},
   * color:       {skip_or_fill_minimum: [2,".productinfo"]}
   *
   */
  $.validator.addMethod("skip_or_fill_minimum", function(value, element, options) {
    var validator = this;
    var myOptions = $.isArray(options) ? options : options.split('|');

    numberRequired = parseInt(myOptions[0], 10);
    selector = myOptions[1];
    var numberFilled = $(selector, element.form).filter(function() {
      return validator.elementValue(this);
    }).length;
    var valid = numberFilled >= numberRequired || numberFilled === 0;

    /*
    if(!$(element).data('being_validated')) {
      var fields = $(selector, element.form);
      fields.data('being_validated', true);
      fields.valid();
      fields.data('being_validated', false);
    }
    */
    return valid;
  }, $.format( $.validator.messages.skip_or_fill_minimum || "Please either skip these fields or fill at least {0} of them."));

  // Accept a value from a file input based on a required mimetype
  $.validator.addMethod("accept", function(value, element, param) {
    // Split mime on commas incase we have multiple types we can accept
    var typeParam = typeof param === "string" ? param.replace(/,/g, '|') : "image/*",
    optionalValue = this.optional(element),
    i, file;

    // Element is optional
    if(optionalValue) {
      return optionalValue;
    }

    if($(element).attr("type") === "file") {
      // If we are using a wildcard, make it regex friendly
      typeParam = typeParam.replace("*", ".*");

      // Check if the element has a FileList before checking each file
      if(element.files && element.files.length) {
        for(i = 0; i < element.files.length; i++) {
          file = element.files[i];

          // Grab the mimtype from the loaded file, verify it matches
          if(!file.type.match(new RegExp( ".?(" + typeParam + ")$", "i"))) {
            return false;
          }
        }
      }
    }

    // Either return true because we've validated each file, or because the
    // browser does not support element.files and the FileList feature
    return true;
  }, $.format( $.validator.messages.accept || "Please enter a value with a valid mimetype."));

  // Older "accept" file extension method. Old docs: http://docs.jquery.com/Plugins/Validation/Methods/accept
  $.validator.addMethod("extension", function(value, element, param) {
    param = typeof param === "string" ? param.replace(/,/g, '|') : "png|jpe?g|gif";
    return this.optional(element) || value.match(new RegExp(".(" + param + ")$", "i"));
  }, $.format( $.validator.messages.extension || "Please enter a value with a valid extension."));
}(jQuery));
