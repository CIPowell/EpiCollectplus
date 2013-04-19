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

require(['jquery', 'backbone', "async!http://maps.googleapis.com/maps/api/js?sensor=false", 'bootstrap-tab', 'bootstrap-tooltip', 'bootstrap-dropdown', 'bootstrap-collapse', 'markerclusterer'], function(EpiCollect){   
    var names = location.pathname.replace(SITE_ROOT, '').replace(/(^\/|\/$)/, '').split('/');
    
    var projectName = names[0];
    var formName = names[1];
    
    
    var url = location.href;
    var site_root = location.href.replace(new RegExp(formName + '\/?',''), '').replace(new RegExp(projectName + '\/?',''), '')
    
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
        url : url.replace(/(^\/|\/$)/, '') + '.json'
    });
    
    var entries = new EntryCollection();
   
    
    var Table = Backbone.View.extend({
        
        events: {
          "click th" : "setSort",
          "change .show-hide-check-box" : "toggleColumn",
          "click td" : "selectRow",
          "click .next-page" : "nextPage",
          "click .prev-page" : "prevPage",
          "click .first-page" : "firstPage",
          "click .last-page" : "lastPage",
          "change #filter_value" : "filter",
          "click #btn_filter" : "filter",
          "click #btn_clear" : "clearFilter"
        },
        initialize : function(){
            var htemp = this.options.headerTemplate;
            
            this.table = this.$('table');
            
            this.$('table').append('<thead><tr></tr></thead><tbody>No Data</tbody>');
            var ele = this.$el;
            
            this.$('#show-hide-fields').empty();
            
            this.$('#num_entries').val(this.options.pageSize);
            
            _.each(this.options.ecform.get('fields'), function(fld)
            {
                this.$('thead tr').append(this.options.headerTemplate(fld));
                this.$('#show-hide-fields').append(this.options.showHideTemplate(fld));
                this.$('#filter_fields').append(this.options.optionTemplate(fld));
                
            }, this);
            
            _.each(this.$('#show-hide-fields input[type=checkbox]'), function(cb){
                this.$('.' + cb.name).toggle(cb.checked);
            }, this);
            
            this.listenTo(this.model, 'sort', this.render);
            this.listenTo(this.model, 'sync', this.drawPage);
            this.listenTo(this.model, 'fetch', this.drawPage);
            this.listenTo(this.model, 'reset', this.drawPage);

        },
        render : function(evt)
        {   
            var first = 0;
            this.$('#start').text(first);
            this.$('#end').text(first + this.options.pageSize);
            this.$('#total').text(this.model.length);           
            
            this.firstPage();
        },
        addOne : function(rec)
        {
            var rtemp = this.options.rowTemplate;
            var ele = this.$el;
            var form = this.options.ecform;

            var vals = { record : rec, form : form};

            this.$('tbody', this.table).append(rtemp(vals));
        },
        addAll : function()
        {
            this.drawPage(this.pageNumber);
        },
        clearFilter : function(evt)
        {
           this.$('#filter_value').val('');
            console.debug('reset')
           this.model.reset([]);
           this.model.fetch();
           
        },
        drawPage : function(n)
        {
            var pn = n;
            if(isNaN(pn) || pn < 0) pn = 0;
            
            this.pageNumber = pn;
             
            this.$('tbody').empty(); 
            
            var start = pn * this.options.pageSize;
                
            var end = start + this.options.pageSize;
            
            if(this.reverse)
            {
               
                 _.each(this.model.slice( -(end + 1), -(start + 1)).reverse(), function(rec){ 
                     this.addOne(rec); 
                }, this);
            }
            else
            {
                _.each(this.model.slice(start, end), function(rec){ 
                     this.addOne(rec); 
                }, this);
            }
            this.$('#start').text(start + 1);
            this.$('#end').text(end);
            this.$('#total').text(this.model.length);
            
            _.each(this.$('#show-hide-fields input[type=checkbox]'), function(cb){
                this.$('.' + cb.name).toggle(cb.checked);
            }, this);
        },
        filter : function(evt)
        {
            var field = this.$('#filter_fields').val();
            var val = this.$('#filter_value').val();
            
            var attr = {};
            attr[field] = val;
            
            console.debug('start filter');
            
            var recs = this.model.where(attr);
            this.model.reset(recs);
            
            console.debug('end filter')
            
        },
        firstPage : function()
        {
           if(this.pageNumber != 0) // don't redraw the page!
           {
               this.drawPage(0);
           }
        },
        lastPage : function()
        {
           var last_page = Math.floor(this.model.length / this.options.pageSize) - (this.model.length % this.options.pageSize == 0 ? 1 : 0);
           if(this.pageNumber != last_page) // don't redraw the page!
           {
                this.drawPage(last_page);
           }
        },
        nextPage : function()
        {
           if(((this.pageNumber + 1) * this.options.pageSize) < this.model.length)
           {
               this.drawPage(this.pageNumber + 1);
           }
        },
        prevPage : function()
        {
           if(this.pageNumber != 0)
           {
               this.drawPage(this.pageNumber - 1);
           }
        },
                
        selectRow : function(evt)
        {
            var th = evt.target;
            var tr = $(th).parent();
            
            this.$('tr.selected').removeClass('selected');
            
            tr.addClass('selected');
        },
        setSort : function(arg)
        {           
            var sortColumn = $(arg.target).attr('columnName');
            
            this.$('thead th span').removeClass('icon-chevron-up').removeClass('icon-chevron-down').removeClass('icon-white');
            
            if (sortColumn === this.sortColumn)
            {
                this.reverse = !this.reverse;
            }
            else
            {  
                this.sortColumn = sortColumn;
                this.reverse = false;
            }
            
            if(this.reverse)
            {
                this.$('th[columnName=' + sortColumn + '] span')
                        .addClass('icon-chevron-down icon-white');
            }
            else
            {
                $('th[columnName=' + sortColumn + '] span')
                        .addClass('icon-chevron-up icon-white');
            }
            
            this.model.comparator  = function(rec)
            {
                return rec.get(sortColumn);
            };
            this.model.sort();
            this.$('tbody').empty();
            this.drawPage(this.pageNumber);
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
                mapTypeId :google.maps.MapTypeId.ROADMAP
            });
            
            this.listenTo(this.model, 'add', this.addOne);  
 
            this.clusterer = new MarkerClusterer(this.map, [],
            {
                    maxZoom : 21,
                    gridSize : 40,
                    styles : [
                        {   
                            url : site_root + "/markers/cluster",
                            height: 50,
                            width: 50,
                            anchor: [24, 24],
                            textColor: 'transparent',
                            textSize: 14
                        },
                        {
                            url : site_root + "/markers/cluster",
                            height: 50,
                            width: 50,
                            anchor: [24, 24],
                            textColor: 'transparent',
                            textSize: 14
                        },{
                            url : site_root + "/markers/cluster",
                            height: 50,
                            width: 50,
                            anchor: [24, 24],
                            textColor: 'transparent',
                            textSize: 14
                        },{
                            url : site_root + "/markers/cluster",
                            height: 50,
                            width: 50,
                            anchor: [24, 24],
                            textColor: 'transparent',
                            textSize: 14
                        }
                    ]
            });


        },
        addOne : function(rec)
        {
            var loc_field = this.options.field;
            var loc_val = rec.get(loc_field);
            var loc = new google.maps.LatLng(loc_val.latitude, loc_val.longitude);
       
            var mkr = new google.maps.Marker({
                position : loc,
                map: this.map
            });
            
            this.clusterer.addMarker(mkr);
        }
    });
     
    var form = new Form();

    form.fetch({ url : SITE_ROOT + '/' + projectName + '/' + formName + '.json?describe=true',
        success : function(){
            
            form.get('fields').splice( 0, 0,
                { name : "created", label : "Time Created", display : true, key: false },
                { name : "lastUpdated", label : "Time Edited", display : false, key: false },
                { name : "uploaded", label : "Time Uploaded", display : false, key: false },
                { name : "DeviceID", label : "Device ID", display : false, key: false }
            );
            
            tbl = new Table({ 
                el : $('#tableTab'),
                model : entries,
                ecform : form ,
                headerTemplate : _.template($('#table-header-template').html()),
                showHideTemplate : _.template($('#show-hide-column-template').html()),
                rowTemplate : _.template($('#table-row-template').html()),
                optionTemplate : _.template($('#column-filter-option-template').html()),
                pageSize : 10
            });
            
            var first_location = _.findWhere(form.get('fields'), {'type' : 'location'})['name'];
            
            if(first_location)
            {
                var map = new Map({ 
                    el : $('#mapTab'),
                    field : first_location,
                    ecform : form,
                    model : entries
                });
                
                $('#tabs').on('shown', function(e){
                    google.maps.event.trigger(map.map, "resize");
                });
            }
            
            entries.fetch();
        }
    });
 
   

    
});
