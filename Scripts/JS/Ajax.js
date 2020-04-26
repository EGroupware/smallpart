function AjaxSend(url, sendObj, successFunction) {
    //let sendObjReady = JSON.stringify(sendObj);
    //sendObjReady = sendObj;
    jQuery.ajax({
        url: url,
        data: sendObj,
        type: 'post',
        // dataType:"json",
        // async: false,
        success: function (response) {
            if (successFunction!='false') {
               var AjaxGet = JSON.parse(response);
                // alert(response);
                window[successFunction](AjaxGet);
            }
        },
        //error: function (response) {
        error: function (request, status, error) {
            successmessage = 'AJAX: Error!!!!!!!!';
            // //response2 = JSON.parse(response);
            // //alert(response);
            // alert(successmessage);
        }
    });

}
// function AjaxUpload (url, VideoFileForUpload, successFunction) {
//     jQuery.ajax({
//         url: url, // Wohin soll die Datei geschickt werden?
//         data: VideoFileForUpload,          // Das ist unser Datenobjekt.
//         type: 'POST',         // HTTP-Methode, hier: POST
//         processData: false,
//         contentType: false,
//         // und wenn alles erfolgreich verlaufen ist, schreibe eine Meldung
//         // in das Response-Div
//         success: function (response) {
//             if (successFunction != 'false') {
//                 var AjaxGet = JSON.parse(response);
//                 alert(response);
//                 window[successFunction](AjaxGet);
//             }
//         }
//     });
//
// }
