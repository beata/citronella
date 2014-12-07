module.exports = function(grunt) {
  "use strict";

  grunt.initConfig({
    dir: {
      jslib: 'js/src/libs'
    },

    // jshint
    jshint: {
      options: {
        "validthis": true,
        "laxcomma" : true,
        "laxbreak" : true,
        "browser"  : true,
        "eqnull"   : true,
        "debug"    : true,
        "devel"    : true,
        "boss"     : true,
        "expr"     : true,
        "asi"      : true
      },
      uses_defaults: ["js/src/*.js", "<%= dir.jslib %>/custom/**/*.js"]
    },

    uglify: {
      options: {
        sourceMap: true
      },
      dist: {
        files: {

          'js/admin-lib.min.js': [
            "<%= dir.jslib %>/bootstrap3/transition.js",
            "<%= dir.jslib %>/bootstrap3/collapse.js",
            "<%= dir.jslib %>/bootstrap3/tooltip.js",
            "<%= dir.jslib %>/bootstrap3/modal.js",
            "<%= dir.jslib %>/bootstrap3/alert.js",
            "<%= dir.jslib %>/bootstrap3/button.js",
            "<%= dir.jslib %>/bootstrap3/tab.js",
            "<%= dir.jslib %>/bootstrap3/dropdown.js",
            "<%= dir.jslib %>/jquery-validation/jquery.validate.js",
            "<%= dir.jslib %>/handlebars-v1.3.0.js",

            // plugin
            "<%= dir.jslib %>/jquery-checkbox-rangeselect/jquery-checkbox-rangeselect.js",
          ],

          'js/admin.min.js': [
            "<%= dir.jslib %>/custom/snippets/jquery.validate.additional-methods.custom.js",
            "<%= dir.jslib %>/custom/snippets/jquery.validate.setup.bootstrap3.js",
            "<%= dir.jslib %>/custom/snippets/insert-row.js",
            "<%= dir.jslib %>/custom/snippets/actions.js",
            "js/src/admin.js"
          ],

          'js/ie.min.js': [
            "<%= dir.jslib %>/jquery.pseudo.js",
            "<%= dir.jslib %>/selectivizr.js",
            "<%= dir.jslib %>/respond.src.js",
            "<%= dir.jslib %>/html5shiv/html5shiv.js",
            "<%= dir.jslib %>/polyfills.js",

            "<%= dir.jslib %>/custom/snippets/empty.js"
          ],

          'js/lang/admin/zh_TW.js': [
            "<%= dir.jslib %>/custom/snippets/jquery.validate.messages_zh.js",
          ]
        }
      }
    },

    compress: {
      dist: {
        options: {
          mode: 'gzip'
        },
        files: [
          {expand: true, src: ['js/*.min.js'], ext: '.js.gz'},
          {expand: true, src: ['js/lang/admin/*.js'], ext: '.js.gz'}
        ]
      }
    },

    less: {
      admin_ie: {
        options: {
          paths: ["css"],
          relativeUrls: false,
          sourceMap: true,
          sourceMapFilename: 'css/admin.ie.css.map'
        },
        src: ['less/admin.ie.less'],
        dest: 'css/admin.ie.css'
      },
      admin: {
        options: {
          paths: ["css"],
          relativeUrls: false,
          sourceMap: true,
          sourceMapFilename: 'css/admin.css.map'
        },
        src: ['less/admin.less'],
        dest: 'css/admin.css'
      }
    },

    watch: {
      js: {
        files: ["js/src/*.js", "<%= dir.jslib %>/custom/**/*.js", "<%= dir.jslib %>/*.js"],
        tasks: ['jshint', 'compilejs']
      },
      less: {
        files: ['less/**/*.*'],
        tasks: ['less']
      }
    }
  });

  // Grunt contribution tasks.
  grunt.loadNpmTasks("grunt-contrib-jshint");
  grunt.loadNpmTasks("grunt-contrib-uglify");
  grunt.loadNpmTasks("grunt-contrib-compress");

  grunt.loadNpmTasks('grunt-contrib-less');

  grunt.loadNpmTasks('grunt-contrib-watch');

  // When running the default Grunt command, just lint the code.
  grunt.registerTask("compilejs", [
    "jshint",
    "uglify",
    "compress"
  ]);

  grunt.registerTask("default", [
    "compilejs",
    "less"
  ]);


}
