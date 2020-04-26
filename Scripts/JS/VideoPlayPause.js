// Needs AjaxGet.VideoElementId from Video and ID from parent <div>
// Builds Buttons


function FunkVideoPlayPause(AjaxGet) {
    let vid = jQuery("#" + AjaxGet.VideoElementId).get(0);


    // alert("Start: " + vid.buffered.start(0) + " End: "  + vid.buffered.end(0));

    if (vid.paused) {
        vid.play();
        jQuery("#" + AjaxGet.VideoElementId + "FunkOnlyPaus").html("<span class='glyphicon glyphicon-pause' aria-hidden='true'></span>");
        jQuery("#" + AjaxGet.VideoElementId + "FunkVideoPlayPause").show();
        jQuery("#" + AjaxGet.VideoElementId + "FunkOnlyPaus").show();
        
        FunkKillFunctionalityOverlay(AjaxGet);


    } else {
        vid.pause();
        jQuery("#" + AjaxGet.VideoElementId + "FunkOnlyPaus").html("<span class='glyphicon glyphicon-play' aria-hidden='true'></span>");
        jQuery("#" + AjaxGet.VideoElementId + "FunkVideoPlayPause").hide();
        jQuery("#" + AjaxGet.VideoElementId + "FunkOnlyPaus").hide();
        FunkAmpelFunktion(AjaxGet);


    }


}


function FunkmakeBig(AjaxGet) {

    let vid = jQuery("#" + AjaxGet.VideoElementId).get(0);
    vid.width = 720;
    jQuery("#" + AjaxGet.VideoDiv).css('width', vid.width + 'px');

    jQuery("#demo1").html(" ");
    jQuery("#demo2").html("Stop at Time: [" + vid.currentTime + " Sek]");
    jQuery("#demo3").html(jQuery("#" + AjaxGet.VideoElementId).bind("loadedmetadata").get(0).videoWidth + "<--Big----");
    jQuery("#demo4").html(jQuery("#" + AjaxGet.VideoElementId).bind("loadedmetadata").get(0).videoHeight);
    jQuery("#demo5").html(AjaxGet.VideoElementId);
    jQuery("#demo6").html(AjaxGet.VideoDiv);

    // if (vid.paused) {
    //     FunkKillFunctionalityOverlay();
    //     FunkBuildGitterNetz(AjaxGet);
    // }

}


function FunkmakeSmall(AjaxGet) {
    let vid = jQuery("#" + AjaxGet.VideoElementId).get(0);
    vid.width = 320;
    jQuery("#" + AjaxGet.VideoDiv).css('width', vid.width + 'px');
    if (vid.paused) {
        FunkKillFunctionalityOverlay(AjaxGet);
        FunkBuildGitterNetz(AjaxGet);
    }
}

function FunkmakeNormal(AjaxGet) {
    let vid = jQuery("#" + AjaxGet.VideoElementId).get(0);
    vid.width = AjaxGet.VideoWidth;
    jQuery("#" + AjaxGet.VideoDiv).css('width', vid.width + 'px');

    if (vid.paused) {
        FunkKillFunctionalityOverlay(AjaxGet);
        FunkBuildGitterNetz(AjaxGet);
    }
}

function FunkJumpForSek(AjaxGet) {
    let vid = jQuery("#" + AjaxGet.VideoElementId).get(0);
    vid.currentTime = 25;

    if (vid.paused) {
        FunkKillFunctionalityOverlay(AjaxGet);
        FunkBuildGitterNetz(AjaxGet);
    }
}




