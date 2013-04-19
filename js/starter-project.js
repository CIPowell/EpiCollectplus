require.config({
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

require(['EpiCollectPlus'], function(EpiCollect){
   var uid = "{#uid#}";
		
    var project = null;
    var pnl;
    var gp;

    var table;

    var el = $('#menu');
    var f_template = _.template($('#form-template').html());

    EpiCollect.loadProject(location.href.trimChars("/") + ".xml", loadCallback);
    
    function loadCallback(prj){
        project = prj;		
        var _burl = location.href.trimChars("/");


        el.append(f_template(project));
        

        if(_.values(project.forms).length == 0)
        {
            var curpage = location.href.trimChars('/');
            $("#main p").remove();
            $("#menu").append("<p>This project's homepage is <a href=\"" + curpage + "\">" + curpage + "</a></p>");
            $("#menu").append('<p>Now that you have created this project you can <a href="' + curpage + '/formBuilder">create the project forms</a> and <a href="' + curpage + '/manage">update the project\'s settings</a> to change who can access the project and add an image or desctription to the project</p>');
            $("#menu").append('<p>Once you have created your forms you can update the forms using the <a href="' + curpage + '/formBuilder">Edit Forms</a> option and manager the other project settings using <a href="' + curpage + '/manage">Manage</a> option above this message.</p>');
        }

        $("select").change(function(evt)
        {
            $("#" + evt.target.id.replace("field_","")).attr("name", $(evt.target).val());
        });

    }
});
