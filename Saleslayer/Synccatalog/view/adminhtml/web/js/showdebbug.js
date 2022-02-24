require(['jquery'], function($){ 'use strict';
   function sync_custom_command(command) {

        console.log('showdebbug_url desde js sync_custom_command: '+showdebbug_url);
        // console.log('guau: '+showdebbug_url_phtml);
        // console.log('guau2: '+showdebbug_url_phtml_head);

       // var showdebbug_url = "<?php echo $block->getUrl('synccatalog/ajax/showdebbug'); ?>";
       // console.log('showdebbug_url: '+showdebbug_url);
       console.log('command: '+command);
               // url: "<?php echo $block->getUrl('synccatalog/ajax/showdebbug'); ?>",
       $('body').trigger('processStart');
           $.ajax({
               method: "POST",
               url: showdebbug_url,
               data: {'logcommand':command},
               dataType: "json"
           }).done(function (data_return) {
               // console.log('done');
               if(data_return['message_type'] === 'success'){
                   if(data_return['function'] === 'showlogfiles'){
                       if(data_return['content']['file'].length >= 1){
                            document.getElementById('listoflogs').innerHTML = '';
                            document.getElementById('showlog').innerHTML = '';
                            //null info
                           var listed = document.getElementById('sllisted');
                           listed.innerHTML = '';
                           listed.classList.remove("alert");
                           listed.classList.remove("alert-info");
                           //null warnings
                           var warning = document.getElementById('slwarnings');
                           warning.innerHTML = '';
                           warning.classList.remove("alert");
                           warning.classList.remove("alert-warning");
                            //null errors
                           var errors = document.getElementById('slerrors');
                           errors.innerHTML = '';
                           errors.classList.remove("alert");
                           errors.classList.remove("alert-danger");

                           var i;
                           if(data_return['content']['file'].length >= 1){
                               for (i=0; i < data_return['content']['file'].length ; i++ ){
                                   if(i === 0){
                                       sync_custom_command(data_return['content']['file'][i]);
                                   }
                                   var tr = document.createElement('tr');
                                   if(i === 0) {
                                       tr.setAttribute('class', 'filesnamestr table-active');
                                   }else{
                                       tr.setAttribute('class', 'filesnamestr');
                                   }
                                   var tdc = document.createElement('td');
                                   var chk = document.createElement('input');
                                       chk.setAttribute('type', 'checkbox');
                                       chk.setAttribute('name', 'file[]');
                                       chk.setAttribute('value', data_return['content']['file'][i]);
                                       tdc.appendChild(chk);

                                    var tdp = document.createElement('td');
                                        tdp.setAttribute('class', 'filesnames');
                                        tdp.setAttribute('data',  data_return['content']['file'][i]);

                                    var node = document.createTextNode(data_return['content']['file'][i]);

                                    var tdlines = document.createElement('td');
                                    var nodelines = document.createTextNode(data_return['content']['lines'][i]);
                                        tdlines.appendChild(nodelines);

                                   var tdwarnings = document.createElement('td');
                                   if(data_return['content']['warnings'][i] >= 1){
                                       var nodewarnings = document.createTextNode(data_return['content']['warnings'][i]);
                                       tdwarnings.setAttribute('class','text-center text-warning');
                                       tdwarnings.appendChild(nodewarnings);
                                   }

                                   var tderror = document.createElement('td');
                                   if(data_return['content']['errors'][i] >= 1){
                                       var nodeerror = document.createTextNode(data_return['content']['errors'][i]);
                                       tderror.setAttribute('class','text-center text-danger');
                                       tderror.appendChild(nodeerror);
                                   }
                                   tdp.appendChild(node);

                                   tr.appendChild(tdc);
                                   tr.appendChild(tdp);
                                   tr.appendChild(tdlines);
                                   tr.appendChild(tdwarnings);
                                   tr.appendChild(tderror);

                                   var parent = document.getElementById('listoflogs');
                                   parent.appendChild(tr);

                                   $(".filesnames").on("click",function(){
                                       document.getElementById('slh1selector').innerHTML = '';
                                       var commandfor = $(this).attr('data');
                                       sync_custom_command(commandfor);
                                       var h1 = document.createElement('h3');
                                       var div = document.getElementById('slh1selector');
                                       var node = document.createTextNode(commandfor);
                                       h1.appendChild(node);
                                       div.appendChild(h1);
                                   });
                               }
                           }
                           $('body').trigger('processStop');
                       }
                   }else if(data_return['function'] === 'showlogfilecontent'){

                       var table = document.getElementById('listoflogs');
                       var trs = table.getElementsByClassName("filesnamestr");

                       for (var i = 0; i < trs.length; i++) {
                           trs[i].addEventListener("click", function() {
                               var current = document.getElementsByClassName("table-active");
                               if (current.length > 0) {
                                   current[0].className = current[0].className.replace("table-active", "");
                               }
                               this.className += " table-active";
                           });
                       }
                       $('body').trigger('processStop');
                       document.getElementById('showlog').innerHTML = data_return['content'];
                       //avisos de error
                       if(data_return['lines']>=1){
                           var listed = document.getElementById('sllisted');
                               listed.innerHTML = data_return['lines']+ ' Lines' ;
                               listed.classList.add("alert");
                               listed.classList.add("alert-info");
                           var faicon = document.createElement('i');
                               faicon.setAttribute('class','fas fa-info mar-10');
                               listed.insertBefore(faicon,listed.childNodes[0]);

                       }else{
                           var listed = document.getElementById('sllisted');
                               listed.innerHTML = '';
                               listed.classList.remove("alert");
                               listed.classList.remove("alert-info");
                       }
                       if(data_return['warnings']>=1){
                           var warning = document.getElementById('slwarnings');
                               warning.innerHTML = data_return['warnings']+ ' Warnings';
                               warning.classList.add("alert");
                               warning.classList.add("alert-warning");
                               warning.setAttribute('onclick',"window.location.href=\"#idwarning0\"");
                           var faicon = document.createElement('i');
                               faicon.setAttribute('class','fas fa-exclamation-triangle mar-10');
                               warning.insertBefore(faicon,warning.childNodes[0]);

                       }else{
                           var warning = document.getElementById('slwarnings');
                               warning.innerHTML = '';
                               warning.classList.remove("alert");
                               warning.classList.remove("alert-warning");
                       }
                       if(data_return['errors']>=1){
                           var errors = document.getElementById('slerrors');
                               errors.innerHTML = data_return['errors']+ ' Errors';
                               errors.classList.add("alert");
                               errors.classList.add("alert-danger");
                               errors.setAttribute('onclick',"window.location.href=\"#iderror0\"");
                           var faicon = document.createElement('i');
                               faicon.setAttribute('class','fas fa-times mar-10');
                               errors.insertBefore(faicon,errors.childNodes[0]);
                       }else{
                           var errors = document.getElementById('slerrors');
                               errors.innerHTML = '';
                               errors.classList.remove("alert");
                               errors.classList.remove("alert-danger");
                       }
                   }
               }else{
                   showMessage(data_return['message_type'],data_return['content']);
                   clear_message_status();
               }
           }).success(function (data_return) {
               console.log('success');
           }).fail(function (data_return) {
               console.log('Ajax connection error: ');
               console.log(data_return);
           }).always(function (){
               // $('body').trigger('processStop');
           });
   }
   function files_for_delete(command) {
       $('body').trigger('processStart');
        console.log('deletelogs_url desde js files_for_delete: '+deletelogs_url);
        // url: "<?php echo $block->getUrl('synccatalog/ajax/deletelogs'); ?>",
       $.ajax({
           method: "POST",
           url: deletelogs_url,
           data: {'logfilesfordelete':command},
           dataType: "json"
       }).done(function (data_return) {
           document.getElementById('listoflogs').innerHTML = '';
           document.getElementById('showlog').innerHTML = '';
           //null info
           var listed = document.getElementById('sllisted');
           listed.innerHTML = '';
           listed.classList.remove("alert");
           listed.classList.remove("alert-info");
           //null warnings
           var warning = document.getElementById('slwarnings');
           warning.innerHTML = '';
           warning.classList.remove("alert");
           warning.classList.remove("alert-warning");
           //null errors
           var errors = document.getElementById('slerrors');
           errors.innerHTML = '';
           errors.classList.remove("alert");
           errors.classList.remove("alert-danger");
           document.getElementById('slh1selector').innerHTML = '';
           if(data_return['message_type'] === 'success'){
               $('body').trigger('processStop');
               sync_custom_command('showlogfiles');
           }
       }).fail(function (data_return) {
           console.log('Ajax connection error delete logs');
           clear_message_status();
       });
   }
   $("#selectall").on("click",function(){
       var aa = document.querySelectorAll("input[type=checkbox]");
       var first = true;
       for (var i = 0; i < aa.length; i++){
           first = aa[i].checked;
           if(first === false ){
               aa[i].checked = true;
           }else{
               aa[i].checked = false;
           }
       }
   });
   function showMessage(type = 'success', message) {
       if (document.contains(document.getElementById("messages"))) {
           document.getElementById("messages").remove();
       }
       var elm = document.createElement('div');
       elm.setAttribute('id', 'messages');
       var subdiv = document.createElement('div');
       subdiv.setAttribute('class', 'messages');

       var submessage = document.createElement('div');
       submessage.setAttribute('class', 'message message-' + type + ' ' + type);

       var subdivmessage = document.createElement('div');
       subdivmessage.setAttribute('data-ui-id', 'messages-message-' + type);

       var node = document.createTextNode(message);

       subdivmessage.appendChild(node);
       submessage.appendChild(subdivmessage);
       subdiv.appendChild(submessage);
       elm.appendChild(subdiv);

       var parent = document.getElementById('anchor-content');
       parent.insertBefore(elm, parent.lastChild);
   }
   function clear_message_status() {
       var timeout = setTimeout(function () {
           if (document.contains(document.getElementById("messages"))) {
               document.getElementById("messages").remove();
           }
           clearTimeout(timeout);
       }, 7000);
   }
   $(".buttonscommandexecuter").on("click",function(){
        var command = this.name;
        console.log('command en buttonscommandexecuter: '+command);
       // sync_custom_command('showlogfiles');
        if(command === 'deletelogfile'){
            var  array = [];
            var checkboxes = document.querySelectorAll('input[type=checkbox]:checked');
            for (var i = 0; i < checkboxes.length; i++) {
                array.push(checkboxes[i].value)
            }
            if(array.length >=1){
                files_for_delete(array);
            }
        }else{
            sync_custom_command(command);
        }
    });



   sync_custom_command('showlogfiles');
   console.log('hola desde showdebbug.js');
   // var test = "<?php echo $block->getUrl('synccatalog/ajax/showdebbug'); ?>";
   // console.log('test: '+test);

})