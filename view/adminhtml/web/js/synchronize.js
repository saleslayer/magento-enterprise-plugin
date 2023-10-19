require(['jquery'], function($){ 'use strict';
    function update_field_data(connector_id,field_name,field_value){
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
})