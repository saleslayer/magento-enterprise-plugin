require(['jquery', 'mage/url'], function($, url){ 'use strict';
    console.log('hola desde processcounter :)');

    // require([
    //     "IOST",
    //     "jquery",
    //     "mage/url"
    // ], function( iost, $, url ){

    //     console.log(url) ;
    // }); 
    jQuery(document).ready(function(){

        console.log('pasa por jQuery document ready');        
        // $('.progress').hide();
        // $(":input").prop("disabled", true);

        // jQuery('#anchor-content').append(jQuery('#staticBlockId'));

        // console.log('config AjaxUrl: ');
        // console.log(config.AjaxUrl);


        // var syncstatus_url = "<?php echo $block->getUrl('synccatalog/ajax/syncstatus'); ?>";
        // console.log('syncstatus_url' +syncstatus_url);

        // // jQuery('<div/>', {
        // //     id: 'sync-status',
        // //     "class": 'sync-status',
        // //     title: 'Synchronization status'
        // // }).insertBefore('.admin__old');
        // // // }).insertBefore('#page:main-container');
        // // console.log('termina insertar div');
        // var test_url = '<?php echo json_encode($block->getBaseUrl()); ?>';
        // console.log('test_url: '+test_url);
        
        // // define([
        // //     'jquery',
        // //     'jquery/ui',
        // //     'mage/url'
        // // ], function (url) {

        //   var linkUrl = url.build('synccatalog/ajax/showdebbug');
        //   // console.log('linkUrl: '+linkUrl);
        //   console.log('linkUrl: '+linkUrl);
        // var url = "<?php echo $block->getBaseUrl().'saleslayer/ajax/syncstatus' ?>"; // use php in js variable.
        // console.log('url: '+url);
          // check_process_status();
          start_check_process_status();
        // });



        

    });


    function transfer(){
        console.log(url.build) ;
    }

    function start_check_process_status(){

        // $('#messages').html('');
        console.log('pasa por start_check_process_status');
        setTimeout(check_process_status, 5000);
    }

    function check_process_status(){

        console.log('pasa por check_process_status');

        // var test_text = "<h2>Synchronization status:</h2><p>Deleted categories: 1/1<p><p>Deleted categories: 5/5<p><p>Deleted product formats: 0/0<p><p>Updated categories: 20/20<p><p>Updated products: 15/260<p><p>Updated product formats: 0/50<p><p>Updated product links: 0/20<p><p>Updated images: 0/10<p>";

        // $('#sync-data').html(test_text);

        // setTimeout(clear_process_status, 5000);

        
        // url: "<?php echo $block->getUrl('synccatalog/ajax/syncstatus'); ?>",

        

        // "<?php echo $block->getUrl('synccatalog/ajax/showdebbug'); ?>"

        // var linkUrl = ;

        // var param = 'ajax=1';
        //                             $.ajax({
        //                                 showLoader: true,
        //                                 url: "<?php echo $block->getBaseUrl().'saleslayer/ajax/syncstatus'; ?>",
        //                                 data: param,
        //                                 type: "POST",
        //                                 dataType: 'json'

        $('body').trigger('processStart');
        $.ajax({
            method: "POST",
            // url: "<?php echo $block->getUrl('synccatalog/ajax/syncstatus'); ?>",
            url: "<?php echo $block->getBaseUrl().'/saleslayer/ajax/syncstatus'; ?>",
            // url: url.build('synccatalog/ajax/syncstatus'),
            // url: "<?php echo $block->getUrl('/saleslayer/ajax/syncstatus') ?>"
            // data: {'logcommand':"showlogfiles"},
            // data: 'ajax=1',
            dataType: "json"
        }).done(function (data_return) {
            console.log('data_return success:');
            console.log(data_return);
            // showMessage(data_return['message_type'], data_return['message']);
            // clear_message_status();
        }).fail(function (data_return) {
            console.log('data_return fail:');
            console.log(data_return);
            // console.log('Auto save connection error');
        }).always(function (){
            console.log('always function');
            $('body').trigger('processStop');
        });

        console.log('termina check_process_status');

        // jQuery.ajax({
        //     type:'POST',
        //     data:{action:'sl_wc_check_process_status'},
        //     url: ajaxurl,
        //     success: function(data) {
        //         data = JSON.parse(data);
        //         var connector_id = data['connector_id'];

        //         if (data['status'] == 'not_finished'){
                    
        //             $("#progress_catalogue_"+connector_id).show();
        //             $("#progress_products_"+connector_id).show();
        //             $("#progress_product_formats_"+connector_id).show();
        //             $("#progress_product_links_"+connector_id).show();
                    
        //             $(":input").prop("disabled", true);
                    
        //             data_content = data['content'];
                    
        //             var tables = ['catalogue', 'products', 'product_formats', 'product_links'];
                    
        //             for (var index in tables) { 
                        
        //                 var table = tables[index];
                        
        //                 if (table in data_content) {

        //                     var progress_name = '';
                            
        //                     switch(table) {
        //                         case 'products':
        //                             progress_name = ' Products ';
        //                             break;
        //                         case 'product_formats':
        //                             progress_name = ' Product formats ';
        //                             break;
        //                         case 'product_links':
        //                             progress_name = ' Product links ';
        //                             break;
        //                         default:
        //                             progress_name = ' Categories ';
        //                             break;
        //                     }
                            
        //                     var sl_data_processed = data_content[table]['processed'];
        //                     var sl_data_total = data_content[table]['total'];

        //                     var data_now = $("#sub_progress_"+table+'_'+connector_id).attr('aria-valuenow');
        //                     var data_total = $("#sub_progress_"+table+'_'+connector_id).attr('aria-valuemax');
                            
        //                     if (sl_data_total != data_total){
                            
        //                         $("#sub_progress_"+table+'_'+connector_id).attr('aria-valuemax', sl_data_total);                    
                            
        //                     }

        //                     if (sl_data_processed != data_now){
                            
        //                         $("#sub_progress_"+table+'_'+connector_id).addClass('progress-bar-striped active');
        //                         $("#sub_progress_"+table+'_'+connector_id).attr('aria-valuenow', sl_data_processed);
        //                         $("#sub_progress_"+table+'_'+connector_id).width(((sl_data_processed * 100) / sl_data_total)+'%');
        //                         $("#sub_progress_"+table+'_'+connector_id).text(sl_data_processed+'/'+sl_data_total+progress_name+'processed.');
                            
        //                     }
                            
        //                     if (sl_data_processed == sl_data_total){
                                
        //                         $("#sub_progress_"+table+'_'+connector_id).removeClass('progress-bar-striped active');
                            
        //                     }

        //                 }else{
                            
        //                     $("#sub_progress_"+table+'_'+connector_id).parent().hide();
        //                     continue;

        //                 }
                    
        //             }

        //             setTimeout(check_process_status, 1000);
                
        //         }else if (data['status'] == 'stopped'){

        //             $(".progress").hide();
        //             $(":input").prop("disabled", false);
        //             $('#messages').html(data['header']);

        //         }else{

        //             $("#sub_progress_catalogue_"+connector_id).width(0+'%');
        //             $("#sub_progress_products_"+connector_id).width(0+'%');
        //             $("#sub_progress_product_formats_"+connector_id).width(0+'%');
        //             $("#sub_progress_product_links_"+connector_id).width(0+'%');
        //             $("#sub_progress_catalogue_"+connector_id).attr('aria-valuenow', 0);
        //             $("#sub_progress_products_"+connector_id).attr('aria-valuenow', 0);
        //             $("#sub_progress_product_formats_"+connector_id).attr('aria-valuenow', 0);
        //             $("#sub_progress_product_links_"+connector_id).attr('aria-valuenow', 0);

        //             $(".progress").hide();
        //             $(":input").prop("disabled", false);
        //             $('#messages').html(data['content']);

        //         }

        //     }

        // });

        // setTimeout(check_process_status, 3000);
        // jQuery.ajax({
        //     type:'POST',
        //     data:{action:'sl_wc_check_process_status'},
        //     url: ajaxurl,
        //     success: function(data) {
        //         data = JSON.parse(data);
        //         var connector_id = data['connector_id'];

        //         if (data['status'] == 'not_finished'){
                    
        //             $("#progress_catalogue_"+connector_id).show();
        //             $("#progress_products_"+connector_id).show();
        //             $("#progress_product_formats_"+connector_id).show();
        //             $("#progress_product_links_"+connector_id).show();
                    
        //             $(":input").prop("disabled", true);
                    
        //             data_content = data['content'];
                    
        //             var tables = ['catalogue', 'products', 'product_formats', 'product_links'];
                    
        //             for (var index in tables) { 
                        
        //                 var table = tables[index];
                        
        //                 if (table in data_content) {

        //                     var progress_name = '';
                            
        //                     switch(table) {
        //                         case 'products':
        //                             progress_name = ' Products ';
        //                             break;
        //                         case 'product_formats':
        //                             progress_name = ' Product formats ';
        //                             break;
        //                         case 'product_links':
        //                             progress_name = ' Product links ';
        //                             break;
        //                         default:
        //                             progress_name = ' Categories ';
        //                             break;
        //                     }
                            
        //                     var sl_data_processed = data_content[table]['processed'];
        //                     var sl_data_total = data_content[table]['total'];

        //                     var data_now = $("#sub_progress_"+table+'_'+connector_id).attr('aria-valuenow');
        //                     var data_total = $("#sub_progress_"+table+'_'+connector_id).attr('aria-valuemax');
                            
        //                     if (sl_data_total != data_total){
                            
        //                         $("#sub_progress_"+table+'_'+connector_id).attr('aria-valuemax', sl_data_total);                    
                            
        //                     }

        //                     if (sl_data_processed != data_now){
                            
        //                         $("#sub_progress_"+table+'_'+connector_id).addClass('progress-bar-striped active');
        //                         $("#sub_progress_"+table+'_'+connector_id).attr('aria-valuenow', sl_data_processed);
        //                         $("#sub_progress_"+table+'_'+connector_id).width(((sl_data_processed * 100) / sl_data_total)+'%');
        //                         $("#sub_progress_"+table+'_'+connector_id).text(sl_data_processed+'/'+sl_data_total+progress_name+'processed.');
                            
        //                     }
                            
        //                     if (sl_data_processed == sl_data_total){
                                
        //                         $("#sub_progress_"+table+'_'+connector_id).removeClass('progress-bar-striped active');
                            
        //                     }

        //                 }else{
                            
        //                     $("#sub_progress_"+table+'_'+connector_id).parent().hide();
        //                     continue;

        //                 }
                    
        //             }

        //             setTimeout(check_process_status, 1000);
                
        //         }else if (data['status'] == 'stopped'){

        //             $(".progress").hide();
        //             $(":input").prop("disabled", false);
        //             $('#messages').html(data['header']);

        //         }else{

        //             $("#sub_progress_catalogue_"+connector_id).width(0+'%');
        //             $("#sub_progress_products_"+connector_id).width(0+'%');
        //             $("#sub_progress_product_formats_"+connector_id).width(0+'%');
        //             $("#sub_progress_product_links_"+connector_id).width(0+'%');
        //             $("#sub_progress_catalogue_"+connector_id).attr('aria-valuenow', 0);
        //             $("#sub_progress_products_"+connector_id).attr('aria-valuenow', 0);
        //             $("#sub_progress_product_formats_"+connector_id).attr('aria-valuenow', 0);
        //             $("#sub_progress_product_links_"+connector_id).attr('aria-valuenow', 0);

        //             $(".progress").hide();
        //             $(":input").prop("disabled", false);
        //             $('#messages').html(data['content']);

        //         }

        //     }

        // });
        

    }


    function clear_process_status(){

        $('#sync-data').html('');

    }

    // show_counters();
    // function show_counters(){
    //     var timeout = setTimeout(function(){
    //         // if (document.contains(document.getElementById("messages"))) {
    //         //     document.getElementById("messages").remove();
    //         // }
    //         console.log('show_counters! ;)');
    //         clearTimeout(timeout);
    //     }, 5000);
    // }

    /*function update_field_data(connector_id,field_name,field_value){
        var element = document.getElementById('synccatalog_main_ajax_url');
        if(element != null){
            var urlfor = element.value;
            if (typeof urlfor !== 'undefined' ) {
                $('body').trigger('processStart');
                $.ajax({
                    method: "POST",
                    url: urlfor,
                    data: {'connector_id':connector_id,'field_name':field_name,'field_value':field_value},
                    dataType: "json",
                    context: $('#edit_form')
                }).done(function (data_return) {
                    showMessage(data_return['message_type'], data_return['message']);
                    clear_message_status();
                    $('body').trigger('processStop');
                    $(".admin__page-nav-item-messages").remove();
                }).fail(function (data_return) {
                    console.log('Auto save connection error');
                    $('body').trigger('processStop');
                    $(".admin__page-nav-item-messages").remove();
                });
            }
        }
    }
    var timeout = setTimeout(function(){refreshtime()},30 * 1000);
    function refreshtime(){
        var ajaxurltime = document.getElementById('synccatalog_main_ajax_url_time').value;
        $.ajax({
            method: "POST",
            url: ajaxurltime,
            data: {'logcommand':'showservertime'},
            dataType: "json",
            context: $('#edit_form')
        }).done(function (data_return) {
            document.getElementById('servertime').innerHTML = data_return['content'];
            var restseconds = 60 - data_return['seconds'];
                clearTimeout(timeout);
                timeout = setTimeout(function(){refreshtime()},restseconds * 1000);
        }).fail(function () {
            console.log('Auto refresh time connection error');
            clear_message_status();
        });
    }
    function showMessage(type = 'success', message) {
        if (document.contains(document.getElementById("messages"))) {
            document.getElementById("messages").remove();
        }
        
        var elm = document.createElement('div');
        elm.setAttribute('id','messages');
        var subdiv = document.createElement('div');
        subdiv.setAttribute('class','messages');

        var submessage = document.createElement('div');
        submessage.setAttribute('class','message message-'+type+' '+type);

        var  subdivmessage = document.createElement('div');
        subdivmessage.setAttribute('data-ui-id','messages-message-'+type);
        var node = document.createTextNode(message);

        subdivmessage.appendChild( node);
        submessage.appendChild(subdivmessage);
        subdiv.appendChild(submessage);
        elm.appendChild(subdiv);

        var parent = document.getElementById('anchor-content');
        parent.insertBefore(elm, parent.lastChild);

    }
    function clear_message_status(){
        var timeout = setTimeout(function(){
            if (document.contains(document.getElementById("messages"))) {
                document.getElementById("messages").remove();
            }
            clearTimeout(timeout);
        }, 7000);
    }
    $(".conn_field").on("change", function(){

        var connector_id = document.getElementById('synccatalog_main_connector_id').value;
        var field_name = this.name;
        
        var boolean_fields = ["avoid_stock_update", "products_previous_categories"];
        var multiselect_fields = ["store_view_ids[]", "format_configurable_attributes[]"];

        if (jQuery.inArray(field_name, boolean_fields) !== -1){

            var field_value = 0;
            if (this.checked){
                field_value = 1;
            }

        }else if (jQuery.inArray(field_name, multiselect_fields) !== -1){
            field_name = field_name.replace("[]","");

            var field_value = [];

            for (var i = 0; i < this.options.length; i++) {
                if (this.options[i].selected) {
                    field_value.push(this.options[i].value);
                }
            }
        }else{
            var field_value = this.value;
        }
        update_field_data(connector_id,field_name,field_value);
    });
    function showhourinput(){
        var autosync = document.getElementById('synccatalog_main_auto_sync').value;
        if (autosync >= 24) {
            document.getElementById('synccatalog_main_auto_sync_hour').disabled = false;
        } else {
            document.getElementById('synccatalog_main_auto_sync_hour').disabled = true;
        }
    }
    $("#synccatalog_main_auto_sync").on("change", function(){
        showhourinput();
    });
    */
})





// require(['jquery', 'mage/url'], function($, url){ 'use strict';
//     console.log('hola desde processcounter :)');

//     // require([
//     //     "IOST",
//     //     "jquery",
//     //     "mage/url"
//     // ], function( iost, $, url ){

//     //     console.log(url) ;
//     // }); 
//     jQuery(document).ready(function(){


// define([
// require([
//         'jquery',
//         'underscore',
//         'mage/template',
//         // 'jquery/list-filter'
//         ], function (
//             $,
//             _,
//             template
//         ) {
//             // function main(config, element) {
//             function main(){
//                 // var $element = $(element);
//                 // $(document).on('click','yourID_Or_Class',function() {
//                 $(document).ready(function(){
//                         var param = 'ajax=1';
//                             $.ajax({
//                                 showLoader: true,
//                                 url: "<?php echo $block->getBaseUrl().'saleslayer/ajax/syncstatus'; ?>",
//                                 data: param,
//                                 type: "POST",
//                                 dataType: 'json'
//                             }).done(function (data) {
//                                 console.log('done:');
//                                 console.log(data);
//                                 // $('#test').removeClass('hideme');
//                                 // var html = template('#test', {posts:data}); 
//                                 // $('#test').html(html);
//                             });
//                             console.log('termina main');
//                     });
//             };
//         return main;
//     });