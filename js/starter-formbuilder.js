require.config({
  baseUrl : SITE_ROOT + '/js/',
  paths: {

     
  },
  shim: {
       'bootstrap-button':{
        //These script dependencies should be loaded before loading
        //backbone.js
        deps: ['jquery']
     },
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

require(['jquery', 'jquery-ui.min', 'backbone', "async!http://maps.googleapis.com/maps/api/js?sensor=false", 'bootstrap-tab', 'bootstrap-tooltip', 'bootstrap-dropdown', 'bootstrap-collapse', 'bootstrap-affix', 'bootstrap-modal','bootstrap-button', 'markerclusterer'], function(){   
    var names = location.pathname.replace(SITE_ROOT, '').replace(/(^\/|\/$)/, '').split('/');    
    var projectName = names[0];
    var formName = names[1];
    var url = location.href;
    var site_root = location.href.replace(new RegExp(formName + '\/?',''), '').replace(new RegExp(projectName + '\/?',''), '');

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
        label : '',
        type : '',
        getFormBuilderType : function(){
            var type = ''
            
            if ( this.type === 'input' )
            {
                if(this.isInt || this.isDouble)
                {
                    type = 'numeric';
                }
                else if ( this.date || this.setDate)
                {
                    type =  'date';
                }
                else if ( this.time || this.setTime)
                {
                    type =  'time';
                }
                else
                {
                    type =  'text';
                }
            }
            else 
            {
                type =  this.type;
            }
            
            return 'ecplus-' + type + '-element';
        }
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
            
            this.listenTo(this.model, 'reset', this.addAll);
            this.listenTo(this.model, 'add', this.addForm);
            
        },
        render : function()
        {
            this.addAll();
            
            if(this.model.length > 0) this.$el.trigger({ type : 'first_render' });
        },
        addAll :function(){
            this.$('.form').remove();

            this.model.each(function (f){
                this.addForm(f);
            }, this);
        },
        addForm : function(form){
    
             if(form.get('main')){
                this.$el.append('<span id="' + form.get('name') + '" class="form">' + form.get('name') + '</span>'); 
            }
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
           'click #add_key' : 'addKeyDialogOK',
           'first_render #formList' : 'selectFirstForm',
           'click #input_key' : 'showKeyDetails',
           'click #gen_key' : 'hideKeyDetails'
        },
        initialize : function(){
            var ctx = this;

            this.formList = this.options.formList;

            project.fetch({ url : SITE_ROOT + '/' + projectName + '.json',
                success : function(){ 
                    if (project.get('tables').length === 0)
                    {
                       ctx.promptForFormName();
                    }
                    else
                    {
                        ctx.formList.model.reset(project.get('tables'));
                    } 
                    ctx.formList.render();
                } 
            });
            
            setupDraggable();
            
            this.listenTo(this.model, 'reset', this.redraw);
            this.listenTo(this.model, 'add', this.addField);
            
        },
        addFormDialogOK : function()
        {
            
            var name = this.$('#create-form-dialog input').val();
            if(this.validateFormNameInput())
            {
               var frm = { 
                   name : name,
                   num : this.formList.model.length + 1,
                   key : null,
                   fields : [],
                   main : true
               };

               this.formList.model.add(frm);

                $('#create-form-dialog').modal('hide');

               this.formList.render();
               this.selectForm(name);
            }
            
        },
        addField : function(fld){
      
            var ctrl = this.$('#source .' + fld.getFormBuilderType()).clone();
            ctrl.attr('id', fld.name)
            $('.title', ctrl).text(fld.label);
      
            this.$('#destination').append(ctrl);
            
        },
        addKeyDialogOK : function()
        {

            if(this.$('#input_key').hasClass('active'))
            {
                this.addInputKey();
            }
            else
            {
                this.addGenKey();
            }
            $('#create-key-dialog').modal('hide');
        },
        addGenKey : function()
        {
            var fld = new Field();
            fld.genkey = true;
            fld.isKey = true;
            fld.type = 'input';
            fld.label = 'Entry Key';
            fld.name = 'entry_key';
            
            this.model.add(fld);
            
        },
        addInputKey : function()
        {
            var fld = new Field();
            var props = this.$('#key_details').serializeArray();
            var vals = _.object(_.pluck(props, 'name'), _.pluck(props, 'value'));
            console.debug(vals);
            
            fld.genkey = true;
            fld.isKey = true;
            fld.type = vals['key_type']=== 'barcode' ? 'barcode' : 'input';
            fld.label = vals['key_label'];
            fld.name = vals['key_name'];
            fld.isInt = vals['key_type'] === 'numeric'
            this.model.add(fld);
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
        promptForFormName : function()
        {
             this.$el.append(this.options.dialog_template({ 
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
        },
        promptForKey : function()
        {
             this.$el.append(builder.options.dialog_template({ 
                id: 'create-key-dialog', 
                title : 'Name your first form', 
                message : '<p>Every entry should have a unique key, this key can be generated or typed in by the user. If you are unsure of which to use we recommend using a generated key. What kind of key should this form have?</p><div><div class="btn-group" data-toggle="buttons-radio">' +
                            '<button id="gen_key" type="button" class="btn" data-toggle="tooltip" data-placement="top" title="Selecting this option will mean a key is generated for each entry that is designed to be unique to a handset.">A Generated Key</button>' +
                            '<button id="input_key" type="button" class="btn" data-toggle="tooltip" data-placement="top" title="Selecting this option means your user will be asked to enter a key, this can lead to the same key being entered or different phones. In this case the second user to send their data to the server will be informed when they try and synchronise their data.">An Inputted Key</button>' +
                            '</div></div><form id="key_details"><fieldset>'+
                            '<label for="key_name">Key Name</label><input type="text" name="key_name" id="key_name" />'+
                            '<label for="key_label">Key Label</label><input type="text" name="key_label" id="key_label" />'+
                            '<label for="key_type">Key Type</label><select name="key_type" id="key_type" ><option value=\"text\">Text Field</option><option value=\"numeric\">Integer Field</option><option value=\"barcode\">Barcode Field</option></select>'+
                            '</fieldset></form><div id="create-form-input-validation"></div>',
                preventClose : true,
                buttons : [{
                    label : 'Add Key',
                    id : 'add_key',
                    type : 'btn-success'
                }]
            }));
            this.hideKeyDetails();
            $('#create-key-dialog').modal({ backdrop : 'static' });
        },
        redraw : function()
        {
            $('#destination').empty();
        },
        renderForm: function(name)
        {
            
            var form = this.formList.model.findWhere({ name : name });
            
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
               ctl.append(optionTemplate(opt)); 
            });
            
        },
        showKeyDetails : function()
        {
           this.$('#key_details').show();  
        },
        hideKeyDetails : function()
        {
           this.$('#key_details').hide();  
        },
        selectForm : function(name) 
        {
            this.formList.select(name);
            this.renderForm(name);
            
            this.promptForKey();
        },
        selectFirstForm : function()
        {
            var name = this.formList.model.findWhere({ num : 1 }).get('name');
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
            model : new FormCollection()
        }),
        alert_template : _.template($('#alert-template').html()),
        dialog_template :  _.template($('#dialog-template').html()),
        model : new FieldCollection()
    });

});


