// Needs AjaxGet.VideoElementId from Video and ID from parent <div>
// Builds Buttons


function FunkVideoPlayPause(AjaxGet) {
    let vid = $("#" + AjaxGet.VideoElementId).get(0);


    // alert("Start: " + vid.buffered.start(0) + " End: "  + vid.buffered.end(0));

    if (vid.paused) {
        vid.play();
        $("#" + AjaxGet.VideoElementId + "FunkOnlyPaus").html("<span class='glyphicon glyphicon-pause' aria-hidden='true'></span>");
        $("#" + AjaxGet.VideoElementId + "FunkVideoPlayPause").show();
        $("#" + AjaxGet.VideoElementId + "FunkOnlyPaus").show();
        
        FunkKillFunctionalityOverlay(AjaxGet);


    } else {
        vid.pause();
        $("#" + AjaxGet.VideoElementId + "FunkOnlyPaus").html("<span class='glyphicon glyphicon-play' aria-hidden='true'></span>");
        $("#" + AjaxGet.VideoElementId + "FunkVideoPlayPause").hide();
        $("#" + AjaxGet.VideoElementId + "FunkOnlyPaus").hide();
        FunkAmpelFunktion(AjaxGet);


    }


}


function FunkmakeBig(AjaxGet) {

    let vid = $("#" + AjaxGet.VideoElementId).get(0);
    vid.width = 720;
    $("#" + AjaxGet.VideoDiv).css('width', vid.width + 'px');

    $("#demo1").html(" ");
    $("#demo2").html("Stop at Time: [" + vid.currentTime + " Sek]");
    $("#demo3").html($("#" + AjaxGet.VideoElementId).bind("loadedmetadata").get(0).videoWidth + "<--Big----");
    $("#demo4").html($("#" + AjaxGet.VideoElementId).bind("loadedmetadata").get(0).videoHeight);
    $("#demo5").html(AjaxGet.VideoElementId);
    $("#demo6").html(AjaxGet.VideoDiv);

    // if (vid.paused) {
    //     FunkKillFunctionalityOverlay();
    //     FunkBuildGitterNetz(AjaxGet);
    // }

}


function FunkmakeSmall(AjaxGet) {
    let vid = $("#" + AjaxGet.VideoElementId).get(0);
    vid.width = 320;
    $("#" + AjaxGet.VideoDiv).css('width', vid.width + 'px');
    if (vid.paused) {
        FunkKillFunctionalityOverlay(AjaxGet);
        FunkBuildGitterNetz(AjaxGet);
    }
}

function FunkmakeNormal(AjaxGet) {
    let vid = $("#" + AjaxGet.VideoElementId).get(0);
    vid.width = AjaxGet.VideoWidth;
    $("#" + AjaxGet.VideoDiv).css('width', vid.width + 'px');

    if (vid.paused) {
        FunkKillFunctionalityOverlay(AjaxGet);
        FunkBuildGitterNetz(AjaxGet);
    }
}

function FunkJumpForSek(AjaxGet) {
    let vid = $("#" + AjaxGet.VideoElementId).get(0);
    vid.currentTime = 25;

    if (vid.paused) {
        FunkKillFunctionalityOverlay(AjaxGet);
        FunkBuildGitterNetz(AjaxGet);
    }
}




