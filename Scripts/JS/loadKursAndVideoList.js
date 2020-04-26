function IsJsonString(str) {
    try {
        JSON.parse(str);
    } catch (e) {
        return false;
    }
    return true;
}

function FunkShowKursAndVideolist(AjaxGet) {
    jQuery("#VideoList").html(' <div class="flexparent"><div class="selectdropdown flexfieldleft"><select name="SelectKursForVideo" id="SelectKursForVideo"><option value="">Kurse werden geladen...</option></select><div class="selectdropdown_arrow"></div></div><div id="SelectKursVideoArea" class="selectdropdown"><select name="SelectKursVideo" id="SelectKursVideo" disabled></select><div class="selectdropdown_arrow"></div></div></div></div>'
    );

    //--------Beginn of building select options

    // Kursliste erstellen
    var KursList = AjaxGet.KursList;
    var KursListSelectOption = '<option value="">Bitte Kurs wählen</option>';

    if (KursList.length > 0) {
        jQuery.each(KursList, function (key, valueObj) {
            KursListSelectOption += '<option value="' + valueObj.KursID + '" >' + valueObj.KursName + ' [ Kurs-Id: ' + valueObj.KursID + ' ]</option>';
        });

        jQuery("#SelectKursForVideo").empty().prop('disabled', false).html(KursListSelectOption);
    } else {
        jQuery("#SelectKursForVideo").empty().prop('disabled', true).html('<option value=""> >> Sie sind in keinem Kurs registriert <<</option>');
    }


    // Videoliste Erstellen
    var VideoList = AjaxGet.VideoList;
    var LoadedVideoList = [];
    VeideoListSelectOption = '<option value="" class="PlsChooseVideo" >Bitte zuerst Kurs wählen</option>';


    jQuery.each(VideoList, function (kurskey, valueObj) {
        jQuery.each(valueObj, function (key, valueObj) {
            LoadedVideoList[valueObj.VideoListID] = valueObj
            VeideoListSelectOption += '<option value="' + valueObj.VideoListID + '" class="' + kurskey + '" disabled hidden> >>  ' + valueObj.VideoName + '</option>'
        });
    });


    jQuery("#SelectKursVideo").empty().html(VeideoListSelectOption).prop('disabled', false);


    jQuery('#SelectKursForVideo').on('change', function () {


        if (jQuery(this).val() !== '') {

            jQuery('#SelectKursVideo').prop('disabled', false)
            jQuery("#SelectKursVideo option").prop('disabled', false).prop('hidden', true);

            if (jQuery("#SelectKursVideo ." + jQuery(this).val()).length > 0) {

                jQuery("#SelectKursVideo .PlsChooseVideo").text('Bitte Video wählen').prop('selected', true).prop('hidden', true);
            } else {
                jQuery("#SelectKursVideo .PlsChooseVideo").text('>> Es wurden keine Videos hinterlegt <<').prop('selected', true).prop('hidden', true);
            }

            jQuery("#SelectKursVideo ." + jQuery(this).val()).prop('disabled', false).prop('hidden', false);


        } else {
            jQuery("#SelectKursVideo .PlsChooseVideo").text('Bitte zuerst Kurs wählen').prop('selected', true)
            jQuery("#SelectKursVideo option").prop('disabled', false).prop('hidden', true);
            // jQuery('#SelectKursVideo').prop('disabled', true)

        }
    });


    //---- Reload last workes on Video not older dann 120 Min vikole
    var UTCDateNow = new Date()
    UTCDateNow = UTCDateNow.toUTCString()

    if (AjaxGet.LastVideoWorkingOn[0]) {

        if (IsJsonString(AjaxGet.LastVideoWorkingOn[0].LastVideoWorkingOnData)) {

            var LastVideoWorkingOn = jQuery.parseJSON(AjaxGet.LastVideoWorkingOn[0].LastVideoWorkingOnData)

            if (Date.parse(LastVideoWorkingOn.UTCDateNow) - Date.parse(UTCDateNow) + (2 * 60 * 60 * 1000) > 0) {
                jQuery('#SelectKursForVideo').val(LastVideoWorkingOn.KursID).trigger('change');
                jQuery('#SelectKursVideo').val(LastVideoWorkingOn.VideoListID).trigger('change');

                AjaxSend('database/DbInteraktion.php', {
                    DbRequest: 'Select',
                    DbRequestVariation: 'FunkLoadVideo',
                    AjaxDataToSend: {
                        VideoElementId: LastVideoWorkingOn.VideoElementId,
                        KursID: LastVideoWorkingOn.KursID,
                        VideoSrc: LastVideoWorkingOn.VideoSrc,
                        VideoExtention: LastVideoWorkingOn.VideoExtention,
                        VideoListID: LastVideoWorkingOn.VideoListID,
                        UTCDateNow: UTCDateNow
                    }
                }, 'FunkLoadVideo')
            }
        }
    }


    // if (LastVideoWorkingOnIsSet) {
    //
    //     if (Date.parse(LastVideoWorkingOn.UTCDateNow) - Date.parse(UTCDateNow) + (2 * 60 * 60 * 1000) > 0) {
    //         jQuery('#SelectKursForVideo').val(LastVideoWorkingOn.KursID).trigger('change');
    //         jQuery('#SelectKursVideo').val(LastVideoWorkingOn.VideoListID).trigger('change');
    //
    //         AjaxSend('database/DbInteraktion.php', {
    //             DbRequest: 'Select',
    //             DbRequestVariation: 'FunkLoadVideo',
    //             AjaxDataToSend: {
    //                 VideoElementId: LastVideoWorkingOn.VideoElementId,
    //                 KursID: LastVideoWorkingOn.KursID,
    //                 VideoSrc: LastVideoWorkingOn.VideoSrc,
    //                 VideoExtention: LastVideoWorkingOn.VideoExtention,
    //                 VideoListID: LastVideoWorkingOn.VideoListID,
    //                 UTCDateNow: UTCDateNow
    //             }
    //         }, 'FunkLoadVideo')
    //
    //     }
    // }


    jQuery('#SelectKursVideo').on('change', function () {
        if (jQuery(this).val() !== '') {
            AjaxSend('database/DbInteraktion.php', {
                DbRequest: 'Select',
                DbRequestVariation: 'FunkLoadVideo',
                AjaxDataToSend: {
                    VideoElementId: LoadedVideoList[jQuery(this).val()].VideoElementId,
                    KursID: LoadedVideoList[jQuery(this).val()].KursID,
                    VideoSrc: LoadedVideoList[jQuery(this).val()].VideoSrc,
                    VideoExtention: LoadedVideoList[jQuery(this).val()].VideoExtention,
                    VideoListID: LoadedVideoList[jQuery(this).val()].VideoListID,
                    UTCDateNow: UTCDateNow
                }
            }, 'FunkLoadVideo')
        }

        // jQuery("#VideoList").hide(); */
    });


}

