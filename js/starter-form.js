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
     },'bootstrap-collapse':{
        //These script dependencies should be loaded before loading
        //backbone.js
        deps: ['jquery']
     },
     'bootstrap-dropdown':{
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

require(['jquery', 'EpiCollectPlus', 'backbone', "async!http://maps.googleapis.com/maps/api/js?sensor=false", 'bootstrap-tab', 'bootstrap-tooltip', 'bootstrap-dropdown', 'bootstrap-collapse'], function(EpiCollect){   
    var names = location.pathname.replace(SITE_ROOT, '').trimChars('/').split('/');
    
    var projectName = names[0];
    var formName = names[1];
    
    
    var url = location.href;
    
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
    
    var Entry = Backbone.Model.extend({}); // mutable
    
    var EntryCollection = Backbone.Collection.extend({
        model : Entry,
        url : url.trimChars('/') + '.json'
    });
    
    var entries = new EntryCollection();
   
    
    var Table = Backbone.View.extend({
        model : entries,
        events: {
          "click th" : "setSort",
          "change .show-hide-check-box" : "toggleColumn"
      
        },
        initialize : function(){
            var htemp = this.options.headerTemplate;
            
            this.table = this.$('table');
            
            this.$('table').append('<thead><tr></tr></thead><tbody>No Data</tbody>');
            var ele = this.$el;
            
            this.$('#show-hide-fields').empty();
            
            _.each(this.options.ecform.get('fields'), function(fld)
            {
                $('thead tr', this.table).append(this.options.headerTemplate(fld));
                this.$('#show-hide-fields').append(this.options.showHideTemplate(fld));
            }, this);
            
            this.listenTo(this.model, 'add', this.addOne);
            this.listenTo(this.model, 'reset', this.addAll);
            this.listenTo(this.model, 'all', this.render);
            this.listenTo(this.model, 'sort', this.render);

        },
        render : function()
        {
         
        },
        addOne : function(rec)
        {
            console.debug(this.$('tbody tr').length);
            if(this.$('tbody tr').length < 25)
            {
                var rtemp = this.options.rowTemplate;
                var ele = this.$el;
                var form = this.options.ecform;

                var vals = { record : rec, form : form};

                this.$('tbody', this.table).append(rtemp(vals));
            }
        },
        addAll : function()
        {
            this.model.each(function(rec){ 
                 this.addOne(rec); 
            }, this);        
        },
        setSort : function(arg)
        {           
            var sortColumn = $(arg.target).attr('columnName');
            
            this.$('thead th span').removeClass('icon-chevron-up').removeClass('icon-chevron-down');
            
            if (sortColumn === this.sortColumn)
            {
                this.model.comparator  = function (rec) {
                    var str = rec.get(sortColumn);
                    str = str.toLowerCase();
                    str = str.split("");
                    str = _.map(str, function(letter) { 
                      return String.fromCharCode(-(letter.charCodeAt(0)));
                    });
                    return str;
                };
                this.sortColumn = -sortColumn;
              
                this.$('th[columnName=' + sortColumn + '] span')
                        .addClass('icon-chevron-down');
            }
            else
            {
                this.model.comparator  = function(rec)
                {
                    return rec.get(sortColumn);
                };
                this.sortColumn = sortColumn;
                $('th[columnName=' + sortColumn + '] span')
                        
                        .addClass('icon-chevron-up');
            }
            
            this.model.sort();
            this.$('tbody').empty();
            this.addAll();
        },
        toggleColumn : function(evt)
        {
            var ele = evt.target;
            
            $('.' + ele.name).toggle(ele.checked);
        }
    });
 
    var Map = Backbone.View.extend({
        initialize : function()
        {
            this.map = new google.maps.Map($('#map')[0], {
                center : new google.maps.LatLng(0,0),
                zoom : 1,
                mapTypeID : google.maps.MapTypeId.ROADMAP
            })
            
        }
    });
 
    var form = new Form();

    form.fetch({ url : SITE_ROOT + '/' + projectName + '/' + formName + '.json?describe=true',
        success : function(){
            
            form.get('fields').splice( 0, 0,
                { name : "created", label : "Time Created" },
                { name : "lastUpdated", label : "Time Edited" },
                { name : "uploaded", label : "Time Uploaded" },
                { name : "DeviceID", label : "Device ID" }
            );
            
            console.debug(form.get('fields'));
    
            tbl = new Table({ 
                el : $('#tableTab'), 
                ecform : form ,
                headerTemplate : _.template($('#table-header-template').html()),
                showHideTemplate : _.template($('#show-hide-column-template').html()),
                rowTemplate : _.template($('#table-row-template').html())
            });
            
            var first_location = _.findWhere(form.get('fields'), {'type' : 'location'})['name'];
            
            if(first_location)
            {
                map = new Map({ el : $('#mapTab') });
            }
            
            entries.fetch();
        }
    });
 
   

    
});
