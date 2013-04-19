require.config({
  baseUrl : SITE_ROOT + '/js/',
  paths: {

     
  },
  shim: {
     'bootstrap-tab':{
        //These script dependencies should be loaded before loading
        //backbone.js
        deps: ['jquery']
     },
     'bootstrap-tooltip':{
        //These script dependencies should be loaded before loading
        //backbone.js
        deps: ['jquery']
     },
     'bootstrap-affix':{
        //These script dependencies should be loaded before loading
        //backbone.js
        deps: ['jquery']
     },
     'bootstrap-collapse':{
        //These script dependencies should be loaded before loading
        //backbone.js
        deps: ['jquery']
     },
     'jquery-ui.min':{
        //These script dependencies should be loaded before loading
        //backbone.js
        deps: ['jquery']
     },
     'bootstrap-dropdown':{
        //These script dependencies should be loaded before loading
        //backbone.js
        deps: ['jquery']
     },
     'bootstrap-modal':{
        //These script dependencies should be loaded before loading
        //backbone.js
        deps: ['jquery']
     },
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
    },
    'google-maps':
    {
        exports : 'google'
    }
  }
});

require(['jquery', 'jquery-ui.min', 'backbone', "async!http://maps.googleapis.com/maps/api/js?sensor=false", 'bootstrap-tab', 'bootstrap-tooltip', 'bootstrap-dropdown', 'bootstrap-collapse', 'bootstrap-affix', 'bootstrap-modal', 'markerclusterer'], function(){   
    var names = location.pathname.replace(SITE_ROOT, '').replace(/(^\/|\/$)/, '').split('/');    
    var projectName = names[0];
    var formName = names[1];
    var url = location.href;
    var site_root = location.href.replace(new RegExp(formName + '\/?',''), '').replace(new RegExp(projectName + '\/?',''), '')

    var EpiCollect = {};

    EpiCollect.KEYWORDS = [
         'test', 'markers', 'images', 'js', 'css', 'ec', 'pc', 'create'
    ];


    if(url.indexOf('?') >=0)
    {
       url = url.substr(0, url.indexOf('?'));
    }
    if(url.indexOf('#') >=0)
    {
       url = url.substr(0, url.indexOf('#'));
    }
    
    var Field = Backbone.Model.extend({
        name : '',
        label : ''
    });
    
    var FieldCollection = Backbone.Collection.extend({
        model : Field
    });
    
    var Form = Backbone.Model.extend({ 
        url : SITE_ROOT + '/' + projectName + '/' + formName ,
        defaults : {
            fields : new FieldCollection()
        }
    });
    
    var FormCollection = Backbone.Collection.extend({
        model : Form
    });
    
    var Project = Backbone.Model.extend({
        tables : [],
        validateFormName: function(name)
        {
            if(name === "") { return "Form name cannot be blank"; }
            if(_.indexOf(EpiCollect.KEYWORDS, name) !== -1)
            {
                return name + " cannot be user as a form name, other words that cannot be used are : " + EpiCollect.KEYWORDS.join(', ');
            }
            if(this.tables[name]) return "There is already a form called " + name + " in this project" ;
            if(name.match(/\s/gi)) return "The form name cannot contain spaces";
            if(name.match(/[^A-Z0-9_-]/gi)) return "The form name can only contain letters, numbers and _ or -";
            return true;
        }
    });
     
    var project = new Project();

   $('#props div').tooltip();
   
   function setupDraggable()
   {
        $('#destination').sortable({
            revert : 50,
            tolerance : 'pointer',
            items : '> .ecplus-form-element',
            start : function(evt, ui)
            {
                    ui.placeholder.css("visibility", "");
            },
            stop : function(evt, ui)
            {
//			if(!currentForm)
//			{
//				EpiCollect.dialog({ content : "You need to choose a form in order to change the controls on it."});
//				$("#destination div").remove();
//			}
//			else
//			{
                            var jq = $('#destination .end').remove();

                            setSelected($(ui.item));
//			}
            }
        });

        $(".ecplus-form-element").draggable({
            connectToSortable: "#destination",
            cursor : 'url(https://mail.google.com/mail/images/2/closedhand.cur), default',
            helper: 'clone',
            revert: "invalid",
            revertDuration : 100,
            appendTo : 'body',
            scroll : true
        });
    }
    
    
    var FormList = Backbone.View.extend({
        initialize : function()
        {
            this.render();
        },
        render : function()
        {
            this.$('.form').remove();

            _.each(this.model, function (form){
               if(form.main){
                   this.$el.append('<span id="' + form.name + '" class="form">' + form.name + '</span>'); 
               }
            }, this);
            
            if(this.model.length > 0) this.$el.trigger({ type : 'first_render' });
        },
        select : function(name)
        {
            this.$('.form').removeClass('selected');
            this.$('#' + name).addClass('selected');
        }
    });
    
    var FormBuilder = Backbone.View.extend({
        events : {
           'change #create-form-input' : 'validateFormNameInput',
           'click #add_form' : 'addFormDialogOK',
           'first_render #formList' : 'selectFirstForm'
        },
        initialize : function(){
            var $el = this.$el;
            var ctx = this;

            this.formList = this.options.formList;

            this.model.fetch({ url : SITE_ROOT + '/' + projectName + '.json',
                success : function(){ 
                    if (ctx.model.get('tables').length === 0)
                    {
                        
                       $el.append(builder.options.dialog_template({ 
                            id: 'create-form-dialog', 
                            title : 'Name your first form', 
                            message : '<p>Please choose a name for your first form</p><input type="text" name="form_name" id="create-form-input" /><div id="create-form-input-validation"></div>',
                            preventClose : true,
                            buttons : [{
                                label : 'Add Form',
                                id : 'add_form',
                                type : 'btn-success'
                            }]
                        }));
                        
                        $('#create-form-dialog').modal({ backdrop : 'static' });
                    }
                    else
                    {
                        ctx.formList.model = ctx.model.get('tables');
                    } 
                    ctx.formList.render();
                } 
            });
        },
        addFormDialogOK : function()
        {
            if(this.validateFormNameInput())
            {
                var name = this.$('#create-form-dialog input').val();
                if(this.validateFormNameInput)
                {
                   var frm = { 
                       name : name,
                       num : this.model.tables.length + 1,
                       key : null
                   };

                   this.model.tables.push(frm);
                   $('#create-form-dialog').modal('hide');
                   this.formList.model = this.model.get('tables');
                   this.formList.render();
                   this.selectForm(name);
                }
            }
        },
        getFormBuiderType : function(ctrl)
        {
            if(ctrl.type === 'input')
            {
                if(ctrl.integer || ctrl.double)
                {
                    return 'numeric';
                }
                else if(ctrl.date || ctrl.setDate)
                {
                    return 'date';
                }
                else if(ctrl.time || ctrl.setTime)
                {
                    return 'time';
                }
                else
                {
                    return 'text';
                }
            }
            else if(ctrl.type === 'gps')
            {
                return 'location';
            }
            else 
            {
                return ctrl.type;
            }
            
        },
        renderForm: function(name)
        {
            var form = _(this.model.get('tables')).findWhere({ name : name })
            
            console.debug( form );
            
            _.each(form.fields, function(fld)
            {
                this.renderControl(fld);
            }, this);
        },
        renderControl : function(ctrl)
        {   
            var type = this.getFormBuiderType(ctrl);
            
            var ctl = this.$('#source .ecplus-' + type + '-element').clone();
            
            $('.title', ctl).text(ctrl.label);
            ctl.attr('id', ctrl.id);
            this.$('#destination').append(ctl);
            
            $('.option', ctl).remove();
            
            var optionTemplate = _.template($('#option-template').html());
            
            _(ctrl.options).each(function(opt){
                console.debug(opt);
               ctl.append(optionTemplate(opt)); 
            });
            
        },
        selectForm : function(name) 
        {
            this.formList.select(name);
            this.renderForm(name);
        },
        selectFirstForm : function()
        {
            var name = _.findWhere(this.model.get('tables'), { num : 1 }).name;
            this.selectForm(name);
        },
        validateFormNameInput : function()
        {
           var name = this.$('#create-form-dialog input').val();
           var btn = this.$('#create-form-dialog .btn-success');
           var msgs = this.$('#create-form-dialog #create-form-input-validation');
           var frm = new Form();
           var valid = project.validateFormName(name);
           
           msgs.empty();
           
           if(valid === true)
           {
              btn.removeClass('disabled');
              return true;
           }
           else
           {
               btn.addClass('disabled');
               msgs.append(this.options.alert_template({ type : 'alert-error', title : 'You cannot use this form name ', message : valid}));
               return false;
           }
        }
        
       
    });
    
    
    var builder = new FormBuilder({
        el : $('#main'),
        formList : new FormList({ 
            el : $('#formList'),
            model : project.tables
        }),
        alert_template : _.template($('#alert-template').html()),
        dialog_template :  _.template($('#dialog-template').html()),
        model : project
    });
   
   setupDraggable();
   
});


