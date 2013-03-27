require.config({
  paths: {
      "jquery": "empty:"
  },
  baseUrl : 'js/',
  shim: {
    'backbone': {
        //These script dependencies should be loaded before loading
        //backbone.js
        deps: ['underscore', 'jquery'],
        //Once loaded, use the global 'Backbone' as the
        //module value.
        exports: 'Backbone'
    },
    'underscore': {
        exports: '_'
    },
    'EpiCollectPlus' : {
        deps : ['jquery', 'underscore', 'backbone'],
        exports : 'EpiCollectPlus'
    }
  }
});

require(['EpiCollectPlus'], function(EpiCollectPlus){
    EpiCollectPlus.init();
    
    var el = $('#project-list');
    var template = _.template($('#project-template').html());
    
    $.getJSON('./projects', function(projects){
        _.each(projects, function(p){
            el.append(template(p));
        });
    });
    
});