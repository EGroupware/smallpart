function IsJsonString(str) {
    try {
        JSON.parse(str);
    } catch (e) {
        return false;
    }
    return true;
}

function FunkShowKursAndVideolist(AjaxGet) {
    $("#VideoList").html(' <div class="flexparent"><div class="selectdropdown flexfieldleft"><select name="SelectKursForVideo" id="SelectKursForVideo"><option value="">Kurse werden geladen...</option></select><div class="selectdropdown_arrow"></div></div><div id="SelectKursVideoArea" class="selectdropdown"><select name="SelectKursVideo" id="SelectKursVideo" disabled></select><div class="selectdropdown_arrow"></div></div></div></div>'
    );

    //--------Beginn of building select options

    // Kursliste erstellen
    var KursList = AjaxGet.KursList;
    var KursListSelectOption = '<option value="">Bitte Kurs wählen</option>';

    if (KursList.length > 0) {
        $.each(KursList, function (key, valueObj) {
            KursListSelectOption += '<option value="' + valueObj.KursID + '" >' + valueObj.KursName + ' [ Kurs-Id: ' + valueObj.KursID + ' ]</option>';
        });

        $("#SelectKursForVideo").empty().prop('disabled', false).html(KursListSelectOption);
    } else {
        $("#SelectKursForVideo").empty().prop('disabled', true).html('<option value=""> >> Sie sind in keinem Kurs registriert <<</option>');
    }


    // Videoliste Erstellen
    var VideoList = AjaxGet.VideoList;
    var LoadedVideoList = [];
    VeideoListSelectOption = '<option value="" class="PlsChooseVideo" >Bitte zuerst Kurs wählen</option>';


    $.each(VideoList, function (kurskey, valueObj) {
        $.each(valueObj, function (key, valueObj) {
            LoadedVideoList[valueObj.VideoListID] = valueObj
            VeideoListSelectOption += '<option value="' + valueObj.VideoListID + '" class="' + kurskey + '" disabled hidden> >>  ' + valueObj.VideoName + '</option>'
        });
    });


    $("#SelectKursVideo").empty().html(VeideoListSelectOption).prop('disabled', false);


    $('#SelectKursForVideo').on('change', function () {


        if ($(this).val() !== '') {

            $('#SelectKursVideo').prop('disabled', false)
            $("#SelectKursVideo option").prop('disabled', false).prop('hidden', true);

            if ($("#SelectKursVideo ." + $(this).val()).length > 0) {

                $("#SelectKursVideo .PlsChooseVideo").text('Bitte Video wählen').prop('selected', true).prop('hidden', true);
            } else {
                $("#SelectKursVideo .PlsChooseVideo").text('>> Es wurden keine Videos hinterlegt <<').prop('selected', true).prop('hidden', true);
            }

            $("#SelectKursVideo ." + $(this).val()).prop('disabled', false).prop('hidden', false);


        } else {
            $("#SelectKursVideo .PlsChooseVideo").text('Bitte zuerst Kurs wählen').prop('selected', true)
            $("#SelectKursVideo option").prop('disabled', false).prop('hidden', true);
            // $('#SelectKursVideo').prop('disabled', true)

        }
    });


    //---- Reload last workes on Video not older dann 120 Min vikole
    var UTCDateNow = new Date()
    UTCDateNow = UTCDateNow.toUTCString()

    if (AjaxGet.LastVideoWorkingOn[0]) {

        if (IsJsonString(AjaxGet.LastVideoWorkingOn[0].LastVideoWorkingOnData)) {

            var LastVideoWorkingOn = jQuery.parseJSON(AjaxGet.LastVideoWorkingOn[0].LastVideoWorkingOnData)

            if (Date.parse(LastVideoWorkingOn.UTCDateNow) - Date.parse(UTCDateNow) + (2 * 60 * 60 * 1000) > 0) {
                $('#SelectKursForVideo').val(LastVideoWorkingOn.KursID).trigger('change');
                $('#SelectKursVideo').val(LastVideoWorkingOn.VideoListID).trigger('change');

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
    //         $('#SelectKursForVideo').val(LastVideoWorkingOn.KursID).trigger('change');
    //         $('#SelectKursVideo').val(LastVideoWorkingOn.VideoListID).trigger('change');
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


    $('#SelectKursVideo').on('change', function () {
        if ($(this).val() !== '') {
            AjaxSend('database/DbInteraktion.php', {
                DbRequest: 'Select',
                DbRequestVariation: 'FunkLoadVideo',
                AjaxDataToSend: {
                    VideoElementId: LoadedVideoList[$(this).val()].VideoElementId,
                    KursID: LoadedVideoList[$(this).val()].KursID,
                    VideoSrc: LoadedVideoList[$(this).val()].VideoSrc,
                    VideoExtention: LoadedVideoList[$(this).val()].VideoExtention,
                    VideoListID: LoadedVideoList[$(this).val()].VideoListID,
                    UTCDateNow: UTCDateNow
                }
            }, 'FunkLoadVideo')
        }

        // $("#VideoList").hide(); */
    });


}

