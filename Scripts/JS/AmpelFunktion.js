/**
 // Need: AjaxGet.VideoElementId
 // Build gird over addressed Element
 */
function FunkKillMarkarea() {
    $('#GridOverlay').remove()
}

function FunkKillFunctionalityOverlay(AjaxGet) {
    $('#GridOverlay').remove()
    $('#FunctionalityOverlay').remove()
    //Style wrapper container
    $('#wrapper_left').css("height", $('#' + AjaxGet.VideoElementId).innerHeight() + 180 + "px");
}

function FunkAmpelFunktion(AjaxGet) {
    var styleTag = ''
    var MarkedAreaColor = 'ffffff'
    var AmpelColor = 'ffffff'
    var ColorInvert = false
    var UnselectedGridOverlayColor = '000000'
    var ShadowOff = false
    var ACT = '80' // AmpelColorTransparency
    var UGOT = '99' //UnselectedGridOverlayTransparency
    var $SrcGridOverlay = $('#' + AjaxGet.VideoDiv)
    var $SrcFunctionalityOverlay = $('#VideobarStudent')
    var ArrayCordXY = new Array() //fixme
    var ArrayColorXY = new Array() //fixme
    var $WrapGridOverlay = $('<div id="GridOverlay"></div>')
    var $WrapFunctionalityOverlay = $('<div id="FunctionalityOverlay"></div>')
    var $gsize = 10


    $('#' + AjaxGet.VideoElementId).get(0).pause();
    //todo fixme
    // if (AjaxGet.UserRole == 'Admin') {
    //     var $SaveToDbAndPlayText = 'Kommentar speichern & Video abpielen'
    // } else {
    //     var $SaveToDbAndPlayText = 'Speichern & Kommentare anzeigen'
    // }
    // var $SaveToDbAndPlayText = 'Speichern und weiter'


    var $VideoWidth = $('#' + AjaxGet.VideoElementId).innerWidth()
    var $VideoHeight = $('#' + AjaxGet.VideoElementId).innerHeight()
    var position = $('#' + AjaxGet.VideoElementId).position()
    var $cols = Math.ceil($VideoWidth / $gsize)
    var $rows = Math.ceil(($VideoHeight - 9) / $gsize)

    //add Style
    $(document).ready(function () {
        $('head').append('<style id="compiled-css" type="text/css">\n' +
            '#' + AjaxGet.VideoDiv + ' { position: absolute; }\n' +
            '#VideoDivParent { position:relative; } \n' +
            '#GridOverlay, #FunctionalityOverlay { position: relative; }\n' +
            '</style>' +
            '<style id = "styleTagAmpelColor">.HoverGridOverlay { background-color: #b5c2d080 !important; }.UnselectedGridOverlay { background-color: #00000099 ; }</style>'
        )
    })
    // Fitting wrapper Height
    $('#wrapper_left').css("height", $VideoHeight + 350 + "px");


// create overlay


    function FunkBuildGirdOverlay() {
        if (AjaxGet.MarkedAreaColorFromDBbyID) {
            var MarkedAreaColorFromDBbyID = jQuery.parseJSON(AjaxGet.MarkedAreaColorFromDBbyID);
        }
        var Cordname = -1
        var $tbl = $('<table cellspacing="0px" cellpadding="0"></table>')
        for (var y = 1; y <= $rows; y++) {
            var $tr = $('<tr></tr>')
            for (var x = 1; x <= $cols; x++) {
                Cordname++

                ArrayCordXY.push('0')
                ArrayColorXY.push('0')
                var $td = $('<td id=' + 'GridOverlayTd' + Cordname + ' Cord=' + Cordname + '> ' + ' </td>')
                $td.css('width', $gsize + 'px').css('height', $gsize + 'px')
                if (MarkedAreaColorFromDBbyID) {
                    if (MarkedAreaColorFromDBbyID[Cordname] > "0") {
                        $td.css('backgroundColor', '#' + MarkedAreaColorFromDBbyID[Cordname] + '80')
                        ArrayCordXY[Cordname] = '1';
                        ArrayColorXY[Cordname] = MarkedAreaColorFromDBbyID[Cordname]
                    }
                }

                $td.addClass('UnselectedGridOverlay')
                $tr.append($td)
            }
            $tbl.append($tr)

        }
        $SrcGridOverlay.css('width', $cols * $gsize + 'px').css('height', $rows * $gsize + 'px')
        $WrapGridOverlay.append($tbl)

    }

    FunkBuildGirdOverlay()

// functionality overlay


    $WrapFunctionalityOverlay.append('<div class="border_site" style="width:"' + $VideoWidth + 'px">' +
        //-------hier
        '<div class="FunctionalityOverlayTop DisableAfterSaveInputTop">' +
        '<div class="flexparent">' +
        '<div class="flexfieldleft">' +
        '<span class="DisableAfterSaveInput StandartText">' +
        'Markierung: </span>' +
        '</div>' +
        '<div class="flexfieldright flexgrow">' +
        '<label class="containerRadioButton">' +
        '<input type="radio" id="MarkedAreaColorWhite" name="MarkedAreaColor" class="DisableAfterSaveInput button_std MarkedAreaColor" value="Weiß" checked="checked">' +
        '<span style="background: #b5c2d0;" id="MarkedAreaColorWhiteSpan" class="checkmark DisableAfterSaveInput">' +
        '</span>' +
        '</label>' +
        '<label class="containerRadioButton">' +
        '<input type="radio" id="MarkedAreaColorGreen" name="MarkedAreaColor" class="DisableAfterSaveInput button_std MarkedAreaColor" value="Grün">' +
        '<span style="background: #61ff61;" id="MarkedAreaColorGreenSpan" class="checkmark DisableAfterSaveInput">' +
        '</span>' +
        '</label>' +
        '<label class="containerRadioButton">' +
        '<input type="radio" id="MarkedAreaColorRed" name="MarkedAreaColor" class="DisableAfterSaveInput button_std MarkedAreaColor" value="Rot">' +
        '<span style="background: #ff7972;" id="MarkedAreaColorRedSpan" class="checkmark DisableAfterSaveInput">' +
        '</span>' +
        '</label>' +
        '<label class="containerRadioButton">' +
        '<input type="radio" id="MarkedAreaColorYellow" name="MarkedAreaColor" class="DisableAfterSaveInput button_std MarkedAreaColor" value="Gelb">' +
        '<span style="background: #ffc909;" id="MarkedAreaColorYellowSpan" class="checkmark DisableAfterSaveInput">' +
        '</span>' +
        '</label>' +

        '<div class="flexitem">' +
        '<input type="button" id="HideMarkarea" class="DisableAfterSaveInput button_std MarkareaFunctionalitys" value="an/aus">' +
        '</div>' +
        '<div class="flexitem">' +
        '<input type="button" id="BackgroundColorTransparency" class="DisableAfterSaveInput button_std MarkareaFunctionalitys" value="abdunkeln">' +
        '</div>' +
        '<div class="flexitem">' +
        '<input type="button" id="ResetInput" class="DisableAfterSaveInput button_std MarkareaFunctionalitys" style="margin-right:10px; display: none" value="Änderung zurücksetzen">' +
        '</div>' +
        '<div class="flexitem">' +
        '<input type="button" id="DeleteInput" class="DisableAfterSaveInput button_std MarkareaFunctionalitys" value="löschen">' +
        '</div>' +

        '</div>' +
        '</div>' +
        '</div>' +
        //----------
        '<div class="flexparent FunctionalityOverlayMiddle">' +
        '<div class="flexfieldleft flexgrow">' +
        '<div class="flexitem">' +
        '<a href="#" id="DeleteCommentAndPlay" class="button_std" style="display: none;">Kommentar: ' +
        '<span class="glyphicon glyphicon-trash">' +
        '</a>' +
        '</div>' +
        '</div>' +
        '<div class="flexfieldright ">' +
        '<div class="flexitem">' +
        '<input type="button" id="DropAndPlay" class="DisableAfterSaveInput button_std" value="Verwerfen">' +
        '</div>' +
        '<div class="flexitem">' +
        '<input type="button" id="SaveToDbAndPlay" class="button_std" value="Speichern">' +
        '</div>' +
        '<div class="flexitem">' +
        '<input type="button" id="RetweetCommentAndPlay" class="button_std" style="display: none;" value="Rekommentieren">' +
        '</div>' +
        '<div class="flexitem">' +
        '<input type="button" id="EditCommentAndPlay" class="button_std" style="display: none;" value="Editieren">' +
        '</div>' +
        '<div class="flexitem">' +
        '<input type="button" id="UndeleteCommentAndPlay" class="button_std" style="display: none;" value="Editiern und Wiederherstellen">' +
        '</div>' +
        '<div class="flexitem">' +
        // '<input type="button" id="KillCommentsAndPlayAdmin" class="button_std" style="display: none;" value="Fortfahren">' +
        '<a href="#" id="KillCommentsAndPlayAdmin" class="button_std_Play" style="display: none;" ><span class="glyphicon glyphicon-play" aria-hidden="true"></span></a>' +
        '</div>' +
        '</div>' +
        '</div>' +

        //-------------hier
        '<div class="FunctionalityOverlayBottom DisableAfterSaveInputTop">' +
        '<div class="flexparent">' +
        '<div class="flexfieldleft ">' +
        '<span class="DisableAfterSaveInput StandartText">' +
        'Kommentar: </span>' +
        '</div>' +
        '<div class="flexfieldright flexgrow StandartTextLeft">' +
        '<div class="middleRadioButton">' +
        '<label class="containerRadioButton">' +
        ' Positiv' +
        '<input type="radio" id="ColorGreen" name="bewertung" value="positiv">' +
        '<span style="background: #00ff00;" id="ColorGreenSpan" class="checkmark DisableAfterSaveInput">' +
        '</span>' +
        '</label>' +
        '<label class="containerRadioButton">' +
        ' Neutral' +
        '<input type="radio" id="ColorWhite" name="bewertung" value="neutral" checked="checked">' +
        '<span style="background: #7c9fd0;" id="ColorWhiteSpan" class="checkmark DisableAfterSaveInput">' +
        '</span>' +
        '</label>' +
        '<label class="containerRadioButton">' +
        ' Negativ' +
        '<input type="radio" id="ColorRed" name="bewertung" value="negativ">' +
        '<span style="background: #ff0000;" id="ColorRedSpan" class="checkmark DisableAfterSaveInput">' +
        '</span>' +
        '</label>' +
        '</div>' +
        '</div>' +
        '</div>' +
        '<caption style="width: 100%;">' +
        '<textarea id="InputVideoComment" rows="5" style="resize: none; width: 100%">' +
        '</textarea>' +
        '</caption>' +
        '</div>' +
        '</div>'
    );


//---------------------------------------------------------
    $SrcGridOverlay.after($WrapGridOverlay)
    $SrcFunctionalityOverlay.after($WrapFunctionalityOverlay)

    // $("#VideoDivParent :hidden").show().css("background-color", "green")


    function FunkMousefunctionalityoverGirdOverlay() {
        $('#GridOverlay td').hover(function () {
            $(this).toggleClass('HoverGridOverlay')
        })

        //Koordinaten abfrage

        $('#GridOverlay td').on('click', function () {

            $(this).toggleClass('SelectedGridOverlay').toggleClass('UnselectedGridOverlay')

            var Cord = $(this).attr('Cord')

            if (ArrayCordXY [Cord] == '1') {
                ArrayCordXY [Cord] = '0'
                $(this).css('backgroundColor', '')
                ArrayColorXY [Cord] = ''
            } else {
                ArrayCordXY [Cord] = '1'
                $(this).css('backgroundColor', '#' + MarkedAreaColor + '80')
                ArrayColorXY [Cord] = MarkedAreaColor
            }


        })

    }

    FunkMousefunctionalityoverGirdOverlay()

    //---------------Choose Markingcolor

    // ChangeStyle of Marks
    function ChangeStyle(MarkedAreaColor, UnselectedGridOverlayColor, ACT, UGOT) {
        $('#styleTagAmpelColor').remove()
        styleTag = $('<style id = "styleTagAmpelColor">.HoverGridOverlay { background-color: #' + MarkedAreaColor + ACT + '!important;}.UnselectedGridOverlay { background-color: #' + UnselectedGridOverlayColor + UGOT + ';}</style>')
        $('html > head').append(styleTag)
    }


    //MarkeadArea ColorRed
    $('#MarkedAreaColorRed').on('click', function () {
        MarkedAreaColor = 'ff0000'
        // MarkedAreaColor = 'ff0000';
        if (ColorInvert) {
            UnselectedGridOverlayColor = MarkedAreaColor
            MarkedAreaColor = '000000'
        } else {
            UnselectedGridOverlayColor = '000000'
        }

        ChangeStyle(MarkedAreaColor, UnselectedGridOverlayColor, ACT, UGOT)
    })
    //MarkeadArea ColorYellow
    $('#MarkedAreaColorYellow').on('click', function () {
        MarkedAreaColor = 'ffff00'
        if (ColorInvert) {
            UnselectedGridOverlayColor = MarkedAreaColor
            MarkedAreaColor = '000000'
        } else {
            UnselectedGridOverlayColor = '000000'
        }
        // // $('#SelectedMarkedAreaColorText').text('Gelb')
        // // $('#SelectedMarkedAreaColorColor').css('backgroundColor', '#' + MarkedAreaColor)
        // $('.MarkedAreaColor').css('backgroundColor', '').css('border', '')
        // $(this).css('backgroundColor', '#' + MarkedAreaColor).css('border', '1px solid #336699')
        ChangeStyle(MarkedAreaColor, UnselectedGridOverlayColor, ACT, UGOT)

    })
    //MarkeadArea ColorGreen
    $('#MarkedAreaColorGreen').on('click', function () {
        MarkedAreaColor = '00ff00'
        // MarkedAreaColor = '00ff00';
        if (ColorInvert) {
            UnselectedGridOverlayColor = MarkedAreaColor
            MarkedAreaColor = '000000'
        } else {
            UnselectedGridOverlayColor = '000000'
        }
        // // $('#SelectedMarkedAreaColorText').text('Grün')
        // // $('#SelectedMarkedAreaColorColor').css('backgroundColor', '#' + MarkedAreaColor)
        // $('.MarkedAreaColor').css('backgroundColor', '').css('border', '')
        // $(this).css('backgroundColor', '#' + MarkedAreaColor).css('border', '1px solid #336699')
        ChangeStyle(MarkedAreaColor, UnselectedGridOverlayColor, ACT, UGOT)

    })
    //MarkeadArea ColorWhite
    $('#MarkedAreaColorWhite').on('click', function () {
        MarkedAreaColor = 'ffffff'
        if (ColorInvert) {
            UnselectedGridOverlayColor = MarkedAreaColor
            MarkedAreaColor = '000000'
        } else {
            UnselectedGridOverlayColor = '000000'
        }
        // $(this).css('backgroundColor')
        // // $('#SelectedMarkedAreaColorText').text('Weiß')
        // // $('#SelectedMarkedAreaColorColor').css('backgroundColor', '#' + MarkedAreaColor)
        // $('.MarkedAreaColor').css('backgroundColor', '').css('border', '')
        // $(this).css('backgroundColor', '#' + MarkedAreaColor).css('border', '1px solid #336699')
        ChangeStyle(MarkedAreaColor, UnselectedGridOverlayColor, ACT, UGOT)

    })
    // // Initialising with ColorWhite
    // $(document).ready(function () {
    //     if (MarkedAreaColor == 'ffffff') {
    //         $(MarkedAreaColorWhite).css('backgroundColor', '#ffffff').css('border', '1px solid #336699')
    //     }
    // })

    //MarkeadArea ColorNone
    $('#MarkedAreaColorNone').on('click', function () {
        // MarkedAreaColor = 'ff00ff';
        MarkedAreaColor = 'ffffff'
        if (ColorInvert) {
            UnselectedGridOverlayColor = MarkedAreaColor
            MarkedAreaColor = '000000'
        } else {
            UnselectedGridOverlayColor = '000000'
        }

        ChangeStyle(MarkedAreaColor, UnselectedGridOverlayColor, ACT, UGOT)
    })
    //ColorInvert
    $('#ColorInvert').on('click', function () {
        ColorInvert = !ColorInvert

        if (ColorInvert) {
            UnselectedGridOverlayColor = MarkedAreaColor
            MarkedAreaColor = '000000'
        } else {
            MarkedAreaColor = UnselectedGridOverlayColor//AmpelColorSave;
            UnselectedGridOverlayColor = '000000'
        }
        ChangeStyle(MarkedAreaColor, UnselectedGridOverlayColor, ACT, UGOT)
    })

    //AmpelColor
    //ColorRed Negativ
    $('#ColorRed').on('click', function () {
        AmpelColor = 'ff0000'
        AjaxGet.AmpelColor = AmpelColor;

    })
    //ColorGreen Positiv
    $('#ColorGreen').on('click', function () {
        AmpelColor = '00ff00'
        AjaxGet.AmpelColor = AmpelColor;

    })
    //ColorWhite Neutral
    $('#ColorWhite').on('click', function () {
        AmpelColor = 'ffffff'
        AjaxGet.AmpelColor = AmpelColor;
    })

    //--------Markierungsfeld Ein/Aus:
    $('#HideMarkarea').on('click', function () {

        if ($('#GridOverlay table').is(':hidden')) {
            $('#GridOverlay table').show()

            $('#BackgroundColorTransparency,#ResetInput, #DeleteInput,.MarkedAreaColor').prop('disabled', false).css('background-color', '').css('border-color', '').css('color', '');
            // $('#BackgroundColorTransparency').prop('disabled', false).css('background-color', '').css('border-color', '');
            // $('#DeleteInput').prop('disabled', false).css('background-color', '').css('border-color', '');
            // $('#ResetInput').prop('disabled', false).css('background-color', '').css('border-color', '');
            // $('.MarkedAreaColor').prop('disabled', false).css('background-color', '').css('border', '').css('border-color', '');

            MarkedAreaColor = 'ffffff'

            // $('#MarkedAreaColorWhite').css('backgroundColor', '#' + MarkedAreaColor).css('border', '1px solid #336699').css('border-color', '')

            $(this).css('background-color', '').css('color', '');
        } else {
            $('#GridOverlay table').hide()
            $('#BackgroundColorTransparency,#ResetInput, #DeleteInput,.MarkedAreaColor').prop('disabled', true).css('background-color', '#f4f4f4').css('border-color', '#cecece').css('color', '#cecece');

            // $('#DeleteInput').prop('disabled', true).css('background-color', '#f4f4f4').css('border-color', '#cecece');
            //
            // $('#ResetInput').prop('disabled', true).css('background-color', '#975170').css('border-color', '#cecece');
            //
            // $('.MarkedAreaColor').prop('disabled', true).css('background-color', '#f4f4f4').css('border-color', '#cecece');

            $(this).css('background-color', '#1dace4').css('color', '#cecece');
        }
    })


    //BackgroundColorTransparency
    $('#BackgroundColorTransparency').on('click', function () {

        ShadowOff = !ShadowOff
        if (ShadowOff) {
            ACT = '80' // AmpelColorTransparency
            UGOT = '00' //UnselectedGridOverlayTransparency
            $(this).css('background-color', '#1dace4').css('color', '#cecece');

        } else {
            ACT = '80' // AmpelColorTransparency
            UGOT = '99' //UnselectedGridOverlayTransparency
            $(this).css('background-color', '').css('color', '');

        }
        ChangeStyle(MarkedAreaColor, UnselectedGridOverlayColor, ACT, UGOT)
    })

//todo

    //--------Markierung löschen:
    $('#DeleteInput').on('click', function () {

        AjaxGet.MarkedAreaColorFromDBbyID = false
        ArrayCordXY = [];
        ArrayColorXY = [];
        $('#GridOverlay table').remove()
        FunkBuildGirdOverlay()
        FunkMousefunctionalityoverGirdOverlay()
    })

    //--------Ännderung zurücksetzen:
    $('#ResetInput').on('click', function () {

        $('#GridOverlay table').remove()
        ArrayCordXY = [];
        ArrayColorXY = [];
        FunkBuildGirdOverlay()
        FunkMousefunctionalityoverGirdOverlay()

    })

    //--------CheckInput:
    $('#CheckInput').on('click', function () {

        //fixme;
        var StopTime = 10 //Math.round($("#" + AjaxGet.VideoElementId).get(0).currentTime);
        var $AddedComment = $('#InputVideoComment').val()

        // var MarkedArea = JSON.stringify(ArrayCordXY);
        var MarkedArea = ArrayCordXY
        var ArraySavedInput = {
            VideoElementId: AjaxGet.VideoElementId,
            StopTime: StopTime,
            VideoWidth: $VideoWidth,
            VideoHeight: $VideoHeight,
            MarkedArea: MarkedArea,
            AddedComment: $AddedComment
        }

        FunkCheckMarkedArea(ArrayCordXY)

        // FunkCallCommentsImport(AjaxGet);

    })

    //Verwerfen & Fortfahren
    $('#DropAndPlay').on('click', function () {
        // EditCommentsClicked = false;
        FunkKillFunctionalityOverlay(AjaxGet)
        FunkVideoPlayPause(AjaxGet)
    })

    //--------speichern & Fortfahren:
    $('#SaveToDbAndPlay').on('click', function () {
        $.get('database/Check_Session.php', function (user_Session_active) {
            if (user_Session_active) {
                var StopTime = Math.round($('#' + AjaxGet.VideoElementId).get(0).currentTime)
                // var StopTime = $('#' + AjaxGet.VideoElementId).get(0).currentTime.toFixed(1)

                var $AddedCommentArray = [$('#InputVideoComment').val()]
                var $AddedComment = JSON.stringify($AddedCommentArray);
                //Fixme
                //MarkedArea = ArrayCordXY;
                var MarkedArea = JSON.stringify(ArrayCordXY)
                var MarkedAreaColor = JSON.stringify(ArrayColorXY)
                //Invertierung Umkeren
                if (ColorInvert) {
                    MarkedAreaColor = UnselectedGridOverlayColor
                }

                var ArraySavedInput = {
                    VideoElementId: AjaxGet.VideoElementId,
                    StopTime: StopTime,
                    VideoWidth: $VideoWidth,
                    VideoHeight: $VideoHeight,
                    MarkedArea: MarkedArea,
                    MarkedAreaColor: MarkedAreaColor,
                    AddedComment: $AddedComment,
                    KursID: AjaxGet.KursID,
                    UserID: AjaxGet.UserId,
                    AmpelColor: AmpelColor,
                    InfoAlert: ''
                }


//fixme aus komentar raus holen, damit Admin alles sieht und teilnehmer nicht
                FunkHideForCallCommentsImport()
                AjaxSend('database/DbInteraktion.php', {
                    DbRequest: 'Insert',
                    DbRequestVariation: 'SavedInput',
                    AjaxDataToSend: ArraySavedInput
                }, 'FunkCallCommentsImportAdmin')


                // if (AjaxGet.UserRole == 'Admin') {
                //   AjaxSend('database/DbInteraktion.php', {
                //     DbRequest: 'Insert',
                //     DbRequestVariation: '',
                //     AjaxDataToSend: ArraySavedInput
                //   }, 'FunkCallCommentsImportAdmin')
                // } else {
                //   AjaxSend('database/DbInteraktion.php', {
                //     DbRequest: 'Insert',
                //     DbRequestVariation: '',
                //     AjaxDataToSend: ArraySavedInput
                //   }, 'FunkCallCommentsImport')
                // }
            }else {
                $('body').html(user_Session_active);
            }
        })
    })

    //--------Retweet & Fortfahren:
    $('#RetweetCommentAndPlay').on('click', function () {
        var StopTime = Math.round($('#' + AjaxGet.VideoElementId).get(0).currentTime)


        if ($('#InputVideoComment').val() != "") {
            // var $AddedCommentArray = [$('#InputVideoComment').val()]

            $.get('database/Check_Session.php', function (user_Session_active) {
                if (user_Session_active) {

                    AjaxGet.$AddedCommentArray.push(AjaxGet.UserNickname)
                    AjaxGet.$AddedCommentArray.push($('#InputVideoComment').val())


                    var $AddedComment = JSON.stringify(AjaxGet.$AddedCommentArray);
                    // alert(AjaxGet.Comment_DB_ID + " - " + $AddedComment)

                    var ArraySavedInput = {
                        VideoElementId: AjaxGet.VideoElementId,
                        StopTime: StopTime,
                        VideoWidth: $VideoWidth,
                        VideoHeight: $VideoHeight,
                        AddedComment: $AddedComment,
                        KursID: AjaxGet.KursID,
                        UserID: AjaxGet.UserId,
                        AmpelColor: AjaxGet.AmpelColor,
                        InfoAlert: '',
                        // DeletedComment: 0,
                        Comment_DB_ID: AjaxGet.Comment_DB_ID
                    }
                    $('#RetweetsCommentsArea').append('<span style="border: 1px solid #d0d0d0; display: inline-block; width: 100%">' +
                        ' <span class="glyphicon glyphicon-hand-right" aria-hidden="true" style="font-size: 1.5em; padding: 0 5px 0 5px; display: table-cell; vertical-align: middle;"> </span>' +
                        ' <span style="color: #8b5957; display: table-cell; vertical-align: middle;">' + AjaxGet.UserNickname + ': </span><span style="font-size: 1.3em; display: table-cell; vertical-align: middle;">' + $('#InputVideoComment').val() + '</span> ' +
                        ' </span>')


                    $('#RetweetCommentAndPlay').hide()
                    $('#DropAndPlay').hide()
                    $('#InputVideoComment').prop('disabled', true).hide()

                    FunkHideForCallCommentsImport()
                    AjaxSend('database/DbInteraktion.php', {
                        DbRequest: 'Insert',
                        DbRequestVariation: 'RetweetInput',
                        AjaxDataToSend: ArraySavedInput
                    }, 'FunkCallCommentsImportAdmin')
                    // }else {
                    //     $('#RetweetsCommentsArea').append('<span style="border: 1px solid #d0d0d0; display: inline-block; width: 100%">' +
                    //         ' <span class="glyphicon glyphicon-hand-right" aria-hidden="true" style="font-size: 1.5em; padding: 0 5px 0 5px; display: table-cell; vertical-align: middle;"> </span>' +
                    //         ' <span style="color: #8b5957; display: table-cell; vertical-align: middle;">'+ AjaxGet.UserNickname + ': </span><span style="font-size: 1.3em; display: table-cell; vertical-align: middle;"> Test' + $('#InputVideoComment').val() + '</span> ' +
                    //         ' </span>')

                }else {
                    $('body').html(user_Session_active);
                }
            });
        }

    })

//--------Edit & Fortfahren:
    $('#EditCommentAndPlay').on('click', function () {
        $.get('database/Check_Session.php', function (user_Session_active) {
            console.log(user_Session_active)

            if (user_Session_active) {
                var StopTime = Math.round($('#' + AjaxGet.VideoElementId).get(0).currentTime)
                // var $AddedComment = $('#InputVideoComment').val()
                // alert( AjaxGet.$AddedCommentArray[0])
                //  AjaxGet.$EditedCommentHistory.push(AjaxGet.$AddedCommentArray[0])
                //  alert(AjaxGet.$EditedCommentHistory)


                if (AjaxGet.$AddedCommentArray[0] != $('#InputVideoComment').val()) {
                    AjaxGet.$EditedCommentHistory.push(AjaxGet.UserNickname)
                    AjaxGet.$EditedCommentHistory.push(AjaxGet.$AddedCommentArray[0])
                    AjaxGet.$AddedCommentArray[0] = $('#InputVideoComment').val()
                } else {
                    AjaxGet.$AddedCommentArray[0] = ($('#InputVideoComment').val())
                }
                var $AddedComment = JSON.stringify(AjaxGet.$AddedCommentArray);
                var $EditedCommentHistory = JSON.stringify(AjaxGet.$EditedCommentHistory);

                //Fixme
                //MarkedArea = ArrayCordXY;
                var MarkedArea = JSON.stringify(ArrayCordXY)
                var MarkedAreaColor = JSON.stringify(ArrayColorXY)
                //Invertierung Umkeren
                if (ColorInvert) {
                    MarkedAreaColor = UnselectedGridOverlayColor
                }

                var ArraySavedInput = {
                    VideoElementId: AjaxGet.VideoElementId,
                    StopTime: StopTime,
                    VideoWidth: $VideoWidth,
                    VideoHeight: $VideoHeight,
                    MarkedArea: MarkedArea,
                    MarkedAreaColor: MarkedAreaColor,
                    AddedComment: $AddedComment,
                    EditedCommentHistory: $EditedCommentHistory,
                    KursID: AjaxGet.KursID,
                    UserID: AjaxGet.UserId,
                    AmpelColor: AjaxGet.AmpelColor,
                    InfoAlert: '',
                    DeletedComment: 0,
                    Comment_DB_ID: AjaxGet.Comment_DB_ID
                }
                $('#EditCommentAndPlay').hide()
                $('#DeleteCommentAndPlay').hide()

                FunkHideForCallCommentsImport()

                AjaxSend('database/DbInteraktion.php', {
                    DbRequest: 'Insert',
                    DbRequestVariation: 'EditInput',
                    AjaxDataToSend: ArraySavedInput
                }, 'FunkCallCommentsImportAdmin')

            } else {
                $('body').html(user_Session_active);
            }
        })
    })

//--------Delete & Fortfahren:
    $('#DeleteCommentAndPlay').on('click', function () {
        $.get('database/Check_Session.php', function (user_Session_active) {
            if (user_Session_active) {

                $('#FunctionalityOverlay').after('<div id="DeleteCommentConfirm" style="text-align: center;">' +
                    '</div>')
                $('#FunctionalityOverlay').hide()
                $('#DeleteCommentConfirm').html('<p><h2>Endgültig Löschen?</h2></p>' +
                    '<input type="button" id="DeleteCommentConfirmYes" class="button_std" style="margin: 10px; background-color:red; font-weight: bold;" value="LÖSCHEN !">' +
                    '<input type="button" id="DeleteCommentConfirmNo" class="button_std"  style="margin: 10px;  background-color:green;font-weight: bold;" value="Abrechen">'
                )

                $('#DeleteCommentConfirmNo').on('click', function () {
                    $('#FunctionalityOverlay').show()
                    $('#DeleteCommentConfirm').remove()
                });

                $('#DeleteCommentConfirmYes').on('click', function () {
                    $('#FunctionalityOverlay').show()
                    $('#DeleteCommentConfirm').remove()
                    var StopTime = Math.round($('#' + AjaxGet.VideoElementId).get(0).currentTime)
                    // var $AddedComment = $('#InputVideoComment').val()
                    var $AddedCommentArray = [$('#InputVideoComment').val()]
                    var $AddedComment = JSON.stringify($AddedCommentArray);
                    //Fixme
                    //MarkedArea = ArrayCordXY;
                    var MarkedArea = JSON.stringify(ArrayCordXY)
                    var MarkedAreaColor = JSON.stringify(ArrayColorXY)
                    //Invertierung Umkeren
                    if (ColorInvert) {
                        MarkedAreaColor = UnselectedGridOverlayColor
                    }

                    var ArraySavedInput = {
                        VideoElementId: AjaxGet.VideoElementId,
                        StopTime: StopTime,
                        VideoWidth: $VideoWidth,
                        VideoHeight: $VideoHeight,
                        MarkedArea: MarkedArea,
                        MarkedAreaColor: MarkedAreaColor,
                        AddedComment: $AddedComment,
                        KursID: AjaxGet.KursID,
                        UserID: AjaxGet.UserId,
                        AmpelColor: AjaxGet.AmpelColor,
                        InfoAlert: '',
                        DeletedComment: 1,
                        Comment_DB_ID: AjaxGet.Comment_DB_ID
                    }
                    $('#EditCommentAndPlay').hide()
                    $('#DeleteCommentAndPlay').hide()

                    FunkHideForCallCommentsImport()
                    AjaxSend('database/DbInteraktion.php', {
                        DbRequest: 'Insert',
                        DbRequestVariation: 'DeleteInput',
                        AjaxDataToSend: ArraySavedInput
                    }, 'FunkCallCommentsImportAdmin')
                });
            }else {
                $('body').html(user_Session_active);
            }
        })
    })

//--------Undelete & Fortfahren:
    $('#UndeleteCommentAndPlay').on('click', function () {
        $.get('database/Check_Session.php', function (user_Session_active) {
            if (user_Session_active) {
                var StopTime = Math.round($('#' + AjaxGet.VideoElementId).get(0).currentTime)
                // var $AddedComment = $('#InputVideoComment').val()
                var $AddedCommentArray = [$('#InputVideoComment').val()]
                var $AddedComment = JSON.stringify($AddedCommentArray);
                //Fixme
                // var $AddedCommentArray=[]
                // $AddedCommentArray.push($('#InputVideoComment').val());
                // var $AddedComment=JSON.stringify($AddedCommentArray);
                //MarkedArea = ArrayCordXY;
                var MarkedArea = JSON.stringify(ArrayCordXY)
                var MarkedAreaColor = JSON.stringify(ArrayColorXY)
                //Invertierung Umkeren
                if (ColorInvert) {
                    MarkedAreaColor = UnselectedGridOverlayColor
                }

                var ArraySavedInput = {
                    VideoElementId: AjaxGet.VideoElementId,
                    StopTime: StopTime,
                    VideoWidth: $VideoWidth,
                    VideoHeight: $VideoHeight,
                    MarkedArea: MarkedArea,
                    MarkedAreaColor: MarkedAreaColor,
                    AddedComment: $AddedComment,
                    KursID: AjaxGet.KursID,
                    UserID: AjaxGet.UserId,
                    AmpelColor: AjaxGet.AmpelColor,
                    InfoAlert: '',
                    DeletedComment: 0,
                    Comment_DB_ID: AjaxGet.Comment_DB_ID
                }
                $('#EditCommentAndPlay').hide()
                $('#DeleteCommentAndPlay').hide()

                FunkHideForCallCommentsImport()
                AjaxSend('database/DbInteraktion.php', {
                    DbRequest: 'Insert',
                    DbRequestVariation: 'EditInput',
                    AjaxDataToSend: ArraySavedInput
                }, 'FunkCallCommentsImportAdmin')
            }else {
                $('body').html(user_Session_active);
            }
        })
    });


}
