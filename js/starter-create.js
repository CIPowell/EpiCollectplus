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

require(['EpiCollectPlus', 'bootstrap-collapse'], function(EpiCollect){
    $('input[name=name]').on('change', function(evt){
            if($('.projectName').text() != '') return;

            $.ajax({
                    url: './' + $(evt.target).val(),
                    error: function(xhr, status, s){
                            //$('#pdNext').button("enable");
                            $(evt.target).parent().removeClass('error');
                            $('#projectNameMsg').html('<p class="alert alert-info">Project name available.</p>');
                    },
                    success: function(){
                            //$('#pdNext').button("disable");

                            $(evt.target).parent().addClass('error');
                            $('#projectNameMsg').html('<p class="alert alert-error">Project name is already in use. Project name must be unique.</p>');
                    }
            });
    });
//		
    $('#uploadxml').on('load', function(){
            var doc = ($('#uploadxml')[0].contentWindow.document || $('#uploadxml')[0].contentDocument);
            var frm = doc.forms[0]; 

            if(frm)
            {
                    $(frm.xml).change(function(){
                            frm.submit();
                    });
            }
            $('#uploadxml').on('load', function(){
                    var cw_url = $('#uploadxml')[0].contentWindow.location.href;
                    var doc = ($('#uploadxml')[0].contentWindow.document || $('#uploadxml')[0].contentDocument);
                    if(cw_url == location.protocol + '//' + location.host + "{#SITE_ROOT#}/html/projectIFrame.html") return;

                    $('#uploadStatus').empty();
                    /*try{*/
                            obj = JSON.parse($(doc.body).text());
                    /*}catch(err){
                            obj = false;
                    }*/

                    if(!obj || !obj.valid){

                            if(cw_url != location.protocol + '//' + location.host + "{#SITE_ROOT#}/html/projectIFrame.html") {$('#uploadxml')[0].src = "{#SITE_ROOT#}/html/projectIFrame.html";}
                            $('#uploadxml').show();
                            //show error messages
                            $('#uploadStatus').addClass("alert alert-error");
                            $('#uploadStatus').removeClass("alert-success");
                            $('#uploadStatus').append("<p>" + obj.msgs.join("</p><p>") + "</p>");

                    }
                    else
                    {
                            if(cw_url != location.protocol + '//' + location.host + "{#SITE_ROOT#}/html/projectIFrame.html") {$('#uploadxml')[0].src = "{#SITE_ROOT#}/html/projectIFrame.html";}
                            $('#uploadStatus').addClass("alert alert-success");
                            $('#uploadStatus').removeClass("alert-error");

                            $('#uploadStatus').append("XML is valid for the project " + obj.name);
                            $('.projectName').text(obj.name);
                            $(document.forms[0].xml).val(obj.file);
                            $('input[name=name]').val(obj.name)
                    }

            });
    });
});
