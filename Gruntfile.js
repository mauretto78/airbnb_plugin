module.exports = function(grunt) {

    var es2015Preset = require('babel-preset-es2015');
    var reactPreset = require('babel-preset-react');

    grunt.initConfig( {
        browserify: {
            core: {
                options: {
                    transform: [
                        [ 'babelify', { presets: [ es2015Preset, reactPreset ] } ]
                    ],
                    browserifyOptions: {
                        paths: [ __dirname + '/node_modules' ]
                    }
                },
                src: [
                    'static/src/js/cat_source/microsoft-core.js',
                    'static/src/js/cat_source/microsoft-core.*.js'
                ],
                dest:  'static/build/microsoft-core-build.js'
            },
        },
        sass: {
            dist: {
                options: {
                    sourceMap: false,
                    includePaths: ['static/src/css/sass/']
                },
                src: [
                    'static/src/css/sass/microsoft-core.scss'
                ],
                dest: 'static/build/microsoft-build.css'
            }
        },
        replace: {
            css: {
                src: [
                    'static/build/*'
                ],
                dest: 'static/build/',
                replacements: [
                    {
                        from: 'url(../img',
                        to: 'url(../src/css/img'
                    }
                ]
            }
        }

    });

    grunt.loadNpmTasks('grunt-browserify');
    grunt.loadNpmTasks('grunt-contrib-watch');
    grunt.loadNpmTasks('grunt-sass');
    grunt.loadNpmTasks('grunt-text-replace');

    // Define your tasks here
    grunt.registerTask('default', ['bundle:js']);

    grunt.registerTask('bundle:js', [
        'browserify:core',
        'sass',
        'replace'
    ]);



};
