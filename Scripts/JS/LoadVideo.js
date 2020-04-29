// Needs VideoElementId & ElementID for Videoinputarea, Controlbutton, Outpt1, Outpot2
//ToDo: After Beta only Videoinput, Controlbutton  [output0 must delete]

//  Returns Video


// function FunkLoadVideo(VideoDivParent, Controlbutton, Output1, Output2){
function FunkLoadVideo(AjaxGet) {

    let VideoDivParent = AjaxGet.VideoDivParent;
    let Controlbutton = AjaxGet.Controlbutton;
    // let Output1 = AjaxGet.Output1;
    // let Output2 = AjaxGet.Output2;
    let VideoElementId = AjaxGet.VideoElementId;
    let VideoDiv = AjaxGet.VideoDiv;
    // let VideoList = AjaxGet.VideoList;

//wrapper_left
    $("#Mid").show()
    $("#Mid_left").css('width', AjaxGet.VideoWidth + 'px');

    $("#" + VideoDivParent).html(
        "<div id='VideoDivTop' style='word-break: break-all;'></div>" + //fixme

        "<div id='" + VideoDiv + "' class='tadahierbinich' style=' width: " + AjaxGet.VideoWidth + "px;'> " +

        "	<video id=" + VideoElementId + " class='isDisabled' width='" + AjaxGet.VideoWidth + "px' preload='auto'>" +

        AjaxGet.VideoElementSrc +

        'A browser with <a href="http://www.jwplayer.com/html5/">HTML5 text track support</a> is required.' +
        "   </video>" +
        "<div id=\"VideobarTime\"></div>" +
        // "<div id=\"VideoPufferBar\"></div>" +
        // "<div id=\"VideobarExpert\"></div>" +
        "<div id=\"VideobarStudent\"></div>" +

        // "<button id='" + VideoElementId + "FunkVideoPlayPause' style='float: right;' class='button_std_Edit'><span class='glyphicon glyphicon-edit' aria-hidden='true'></span></button>" +
        // "<button id='" + VideoElementId + "FunkOnlyPaus' style='float: right;' class='button_std_Play'><span class='glyphicon glyphicon-play' aria-hidden='true'></span></button>"
        "<a href='#' id='" + VideoElementId + "FunkVideoPlayPause'  class='button_std_Edit isDisabled'><span class='glyphicon glyphicon-edit' aria-hidden='true'></span></a>" +
        "<a href='#' id='" + VideoElementId + "FunkOnlyPaus'  class='button_std_Play isDisabled' ><span class='glyphicon glyphicon-play' aria-hidden='true'></span></a>"
    );

    // window.setInterval(function () {
    //     let pct = $("#" + VideoElementId).get(0).buffered.end(0) / $("#" + VideoElementId).get(0).duration * 100;
    //     document.querySelector("#VideoPufferBar").style.background = "linear-gradient(to right, #1dace4 " + pct + "%, #0f5270 " + pct + "%)";
    // }, 10);


    // wrapper_right

    $("#" + Controlbutton).html(
        '<div id="AufgabeText">' +
        '<div class="StandartTextH2">Arbeitsauftrag:' +
        '<span id="collapse-down-up">' +
        '</span>' +
        '</div>' +
        '<br>' +
        '<div id="AufgabeZumVideo" class="StandartTextLeft">' +
        '<textarea id="AufgabeZumVideoTextTextarea" readonly>' +
        '</textarea>' +
        '</div>' +
        '</div>' +
        // "<div id='controlbutton' style='padding: 20px 10px 20px 10px'>" +
        //
        // "</div>" +
        "<div id='CommentsShowArea' ></div>" +
        "<div id='SearchMarkedAreaTop' class='StandartTextLeft'>" +
        "<div id='SelectMarkedAreaType' class='SearchMarkedAreaElements'>" +
        '<span class="SearchMarkedAreaElements">Filter:</span>' +
        '<select name="SelectMarkedAreaTypeInput" id="SelectMarkedAreaTypeInput" class="SearchMarkedAreaElements">' +
        '</select>' +
        "</div>" +
        //todo: rename Div ID
        "<div id='SearchMarkedAreaAjax' class='SearchMarkedAreaElements' >" +
        '<a href="#" id="SearchMarkedAreaButtonReset" class="DelteButton SearchMarkedAreaElements" ><span class="glyphicon glyphicon-trash"></span></a>' +
        '</div>' +
        '<span class="SearchMarkedAreaElements">oder:</span>' +

        "<div id='SearchMarkedAreaAjax' class='SearchMarkedAreaElements' >   " +
        '<input id="SearchMarkedAreaInput" type="search"  placeholder="Schlagwort eingeben"/> ' +
        // '<button id="SearchMarkedAreaButton" class="button_std_SearchButton" >Suchen</button>' +
        '<a href="#" id="SearchMarkedAreaButton" class="button_std_SearchButcton1"><span class="glyphicon glyphicon-search"></span></a>' +
        "</div>" +
        "<div id='OnMouseoverPauseActiveDiv' class=''>" +
        '<label id="OnMouseoverPauseActiveLable" class="controlCheckboxColored controlCheckboxColored-checkbox"> ' +
        'Pause bei Mouseover  ' +
        '<input type="checkbox" id="OnMouseoverPause" name="OnMouseoverPauseActiveName" value="remember-me" title="Pause bei Mouseover" checked="checked"/>' +
        '<div class="controlCheckboxColored_indicator"></div>' +
        '</label>' +
        "</div>" +
        "</div>" +
        "</div>" +
        '<div id="SearchMarkedAreaMid" class="StandartTextLeft"></div>' +
        '<div id="SearchMarkedAreaDown" class="StandartTextLeft"></div>'
    );


    //Eventlistener for Buttons on click
    $(document).ready(function () {

        $("#AufgabeZumVideoTextTextarea").html(AjaxGet.Question).height($("#AufgabeZumVideoTextTextarea")[0].scrollHeight).attr("disabled", "true");

        var lineheigt = "25"
        // if ($("#AufgabeZumVideoText").height() > lineheigt) {
        if ($("#AufgabeZumVideoTextTextarea").height() > lineheigt) {

            $("#collapse-down-up").html('<span class="glyphicon glyphicon-menu-down glyphicon-down button_std_small"></span> ')
        }

        $("#collapse-down-up").on('click', function () {

            if ($("#AufgabeZumVideo").height() > lineheigt) {

                $("#AufgabeZumVideo").css('height', '')
                $("#collapse-down-up").html('<span class="glyphicon glyphicon-menu-down glyphicon-down button_std_small"></span> ')

            } else {

                $("#AufgabeZumVideo").css('height', 'auto')
                $("#collapse-down-up").html('<span class="glyphicon glyphicon-menu-up glyphicon-up button_std_small"> </span>')

            }
        })


        // AjaxSendBright
        function AjaxSendBrightSuccessFunction(successFunction) {
            AjaxSend('database/DbInteraktion.php', {
                DbRequest: "Select",
                DbRequestVariation: "LoadVideo",
                AjaxDataToSend: {UserID: AjaxGet.UserID, VideoElementId: VideoElementId, KursID: AjaxGet.KursID}
            }, successFunction);
        }

        // //FunkVideoPlayPause
        // $("#" + VideoElementId + "FunkVideoPlayPause").on("click", function () {
        //     FunkAmpelFunktion(AjaxGet);
        //     $("#" + AjaxGet.VideoElementId + "FunkVideoPlayPause").hide();
        //     $("#" + AjaxGet.VideoElementId + "FunkOnlyPaus").hide();
        //
        //     // FunkVideoPlayPause(AjaxGet)
        //     // AjaxSendBrightSuccessFunction('FunkVideoPlayPause');
        // });

        // //FunkOnlyPaus
        // // var FunkOnlyPausIDs="#" + VideoElementId + "FunkOnlyPaus, #"+VideoElementId
        // $("#" + VideoElementId + "FunkOnlyPaus, #" + VideoElementId).on("click", function () {
        //     var VideoPause = $('#' + VideoElementId).get(0)
        //     if (VideoPause.paused && !$('#FunctionalityOverlay').length) {
        //
        //         VideoPause.play();
        //         $("#" + VideoElementId + "FunkOnlyPaus").html("<span class='glyphicon glyphicon-pause' aria-hidden='true'></span>");
        //
        //
        //     } else {
        //
        //         VideoPause.pause();
        //         $("#" + VideoElementId + "FunkOnlyPaus").html("<span class='glyphicon glyphicon-play' aria-hidden='true'></span>");
        //
        //     }
        // });


    });

    function FunkOnLoadVideo(loaded) {
        if (loaded) {
            // Enable play-Pause on load
            $("#" + VideoElementId + "FunkOnlyPaus, #" + VideoElementId + "FunkVideoPlayPause, #" + VideoElementId).removeClass('isDisabled')

            //FunkVideoPlayPause
            $("#" + VideoElementId + "FunkVideoPlayPause").on("click", function () {
                FunkAmpelFunktion(AjaxGet);
                $("#" + AjaxGet.VideoElementId + "FunkVideoPlayPause").hide();
                $("#" + AjaxGet.VideoElementId + "FunkOnlyPaus").hide();

                // FunkVideoPlayPause(AjaxGet)
                // AjaxSendBrightSuccessFunction('FunkVideoPlayPause');
            });

            //FunkOnlyPaus
            // var FunkOnlyPausIDs="#" + VideoElementId + "FunkOnlyPaus, #"+VideoElementId
            $("#" + VideoElementId + "FunkOnlyPaus, #" + VideoElementId).on("click", function () {
                var VideoPause = $('#' + VideoElementId).get(0)
                if (VideoPause.paused && !$('#FunctionalityOverlay').length) {

                    VideoPause.play();
                    $("#" + VideoElementId + "FunkOnlyPaus").html("<span class='glyphicon glyphicon-pause' aria-hidden='true'></span>");


                } else {

                    VideoPause.pause();
                    $("#" + VideoElementId + "FunkOnlyPaus").html("<span class='glyphicon glyphicon-play' aria-hidden='true'></span>");

                }
            });


            AjaxGet.barExpertoffsetLeft = $('#' + VideoElementId).offset().left;
            AjaxGet.barStudentoffsetLeft = $('#' + VideoElementId).offset().left;

            AjaxSend('database/DbInteraktion.php', {
                DbRequest: "Select",
                DbRequestVariation: "FunkShowCommentsAdmin",
                AjaxDataToSend: {VideoElementId: AjaxGet.VideoElementId, KursID: AjaxGet.KursID}
            }, 'FunkShowComments');



            //Ampel Suchfunktion
            let vid = document.querySelector('#' + VideoElementId);
            let barStudent = document.querySelector("#VideobarStudent");
// let barExpert = document.querySelector("#VideobarExpert");
            let barTime = document.querySelector("#VideobarTime");
            let frm = document.querySelector("form");
            let frm2 = document.querySelector("#SearchMarkedAreaButton");

//vid.addEventListener('click',play, false);
            vid.addEventListener('timeupdate', update, false);
            barStudent.addEventListener('click', FunkSeekBarStudent, false);

// barExpert.addEventListener('click', FunkSeekBarExpert, false);


            function update() {
                let pct = vid.currentTime / vid.duration * 100;

                // if (!vid.paused) {
                barStudent.style.background = "linear-gradient(to right, #aeadad " + pct + "%, #cecece " + pct + "%)";
                // barExpert.style.background = "linear-gradient(to right, #aeadad " + pct + "%, #cecece " + pct + "%)";
                // }
                var date = new Date(null);

                date.setSeconds(parseInt(vid.currentTime)); // specify value for SECONDS here
                barTime.innerHTML = date.toISOString().substr(11, 8)

                if (vid.currentTime == vid.duration) {
                    $("#" + VideoElementId + "FunkOnlyPaus").html("<span class='glyphicon glyphicon-repeat flipped-glyphicon'  aria-hidden='true'></span>");
                }


            }


            function FunkSeekBarExpert(e) {
                vid.currentTime = (e.pageX - AjaxGet.barExpertoffsetLeft) * vid.duration / AjaxGet.VideoWidth;
            }

            function FunkSeekBarStudent(e) {
                vid.currentTime = ((e.pageX - AjaxGet.barStudentoffsetLeft) * vid.duration / AjaxGet.VideoWidth);
            }




        } else {

        }
    }

    $('#' + VideoElementId).on("loadedmetadata", function () {
        FunkOnLoadVideo(true)

    });

//ToDo
//     if (AjaxGet.UserRole == 'Admin') {


// }

// //Ampel Suchfunktion
//     let vid = document.querySelector('#' + VideoElementId);
//     let barStudent = document.querySelector("#VideobarStudent");
// // let barExpert = document.querySelector("#VideobarExpert");
//     let barTime = document.querySelector("#VideobarTime");
//     let frm = document.querySelector("form");
//     let frm2 = document.querySelector("#SearchMarkedAreaButton");
//
// //vid.addEventListener('click',play, false);
//     vid.addEventListener('timeupdate', update, false);
//     barStudent.addEventListener('click', FunkSeekBarStudent, false);
//
// // barExpert.addEventListener('click', FunkSeekBarExpert, false);
//
//
//     function update() {
//         let pct = vid.currentTime / vid.duration * 100;
//
//         // if (!vid.paused) {
//         barStudent.style.background = "linear-gradient(to right, #aeadad " + pct + "%, #cecece " + pct + "%)";
//         // barExpert.style.background = "linear-gradient(to right, #aeadad " + pct + "%, #cecece " + pct + "%)";
//         // }
//         var date = new Date(null);
//
//         date.setSeconds(parseInt(vid.currentTime)); // specify value for SECONDS here
//         barTime.innerHTML = date.toISOString().substr(11, 8)
//
//         if (vid.currentTime == vid.duration) {
//             $("#" + VideoElementId + "FunkOnlyPaus").html("<span class='glyphicon glyphicon-repeat flipped-glyphicon'  aria-hidden='true'></span>");
//         }
//
//
//     }
//
//
//     function FunkSeekBarExpert(e) {
//         vid.currentTime = (e.pageX - AjaxGet.barExpertoffsetLeft) * vid.duration / AjaxGet.VideoWidth;
//     }
//
//     function FunkSeekBarStudent(e) {
//         vid.currentTime = ((e.pageX - AjaxGet.barStudentoffsetLeft) * vid.duration / AjaxGet.VideoWidth);
//     }
//

}


