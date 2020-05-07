/**
 // Need: AjaxGet.VideoElementId
 // Build gird over addressed Element
 */
function FunkKillMarkarea() {
    jQuery('#GridOverlay').remove()
}

function FunkKillFunctionalityOverlay(AjaxGet) {
    jQuery('#GridOverlay').remove()
    jQuery('#FunctionalityOverlay').remove()
    //Style wrapper container
    jQuery('#wrapper_left').css("height", jQuery('#' + AjaxGet.VideoElementId).innerHeight() + 180 + "px");
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
    var $SrcGridOverlay = jQuery('#' + AjaxGet.VideoDiv)
    var $SrcFunctionalityOverlay = jQuery('#VideobarStudent')
    var ArrayCordXY = new Array() //fixme
    var ArrayColorXY = new Array() //fixme
    var $WrapGridOverlay = jQuery('<div id="GridOverlay"></div>')
    var $WrapFunctionalityOverlay = jQuery('<div id="FunctionalityOverlay"></div>')
    var $gsize = 10


    jQuery('#' + AjaxGet.VideoElementId).get(0).pause();
    //todo fixme
    // if (AjaxGet.UserRole == 'Admin') {
    //     var $SaveToDbAndPlayText = 'Kommentar speichern & Video abpielen'
    // } else {
    //     var $SaveToDbAndPlayText = 'Speichern & Kommentare anzeigen'
    // }
    // var $SaveToDbAndPlayText = 'Speichern und weiter'


    var $VideoWidth = jQuery('#' + AjaxGet.VideoElementId).innerWidth()
    var $VideoHeight = jQuery('#' + AjaxGet.VideoElementId).innerHeight()
    var position = jQuery('#' + AjaxGet.VideoElementId).position()
    var $cols = Math.ceil($VideoWidth / $gsize)
    var $rows = Math.ceil(($VideoHeight - 9) / $gsize)

    //add Style
    jQuery(document).ready(function () {
        jQuery('head').append('<style id="compiled-css" type="text/css">\n' +
            '#' + AjaxGet.VideoDiv + ' { position: absolute; }\n' +
            '#VideoDivParent { position:relative; } \n' +
            '#GridOverlay, #FunctionalityOverlay { position: relative; }\n' +
            '</style>' +
            '<style id = "styleTagAmpelColor">.HoverGridOverlay { background-color: #b5c2d080 !important; }.UnselectedGridOverlay { background-color: #00000099 ; }</style>'
        )
    })
    // Fitting wrapper Height
    jQuery('#wrapper_left').css("height", $VideoHeight + 350 + "px");


// create overlay


    function FunkBuildGirdOverlay() {
        if (AjaxGet.MarkedAreaColorFromDBbyID) {
            var MarkedAreaColorFromDBbyID = jQuery.parseJSON(AjaxGet.MarkedAreaColorFromDBbyID);
        }
        var Cordname = -1
        var $tbl = jQuery('<table cellspacing="0px" cellpadding="0"></table>')
        for (var y = 1; y <= $rows; y++) {
            var $tr = jQuery('<tr></tr>')
            for (var x = 1; x <= $cols; x++) {
                Cordname++

                ArrayCordXY.push('0')
                ArrayColorXY.push('0')
                var $td = jQuery('<td id=' + 'GridOverlayTd' + Cordname + ' Cord=' + Cordname + '> ' + ' </td>')
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
        '<div class="flexitem" id="flexitemOverLeftMid">' +
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

    // jQuery("#VideoDivParent :hidden").show().css("background-color", "green")


    function FunkMousefunctionalityoverGirdOverlay() {
        jQuery('#GridOverlay td').hover(function () {
            jQuery(this).toggleClass('HoverGridOverlay')
        })

        //Koordinaten abfrage

        jQuery('#GridOverlay td').on('click', function () {

            jQuery(this).toggleClass('SelectedGridOverlay').toggleClass('UnselectedGridOverlay')

            var Cord = jQuery(this).attr('Cord')

            if (ArrayCordXY [Cord] == '1') {
                ArrayCordXY [Cord] = '0'
                jQuery(this).css('backgroundColor', '')
                ArrayColorXY [Cord] = ''
            } else {
                ArrayCordXY [Cord] = '1'
                jQuery(this).css('backgroundColor', '#' + MarkedAreaColor + '80')
                ArrayColorXY [Cord] = MarkedAreaColor
            }


        })

    }

    FunkMousefunctionalityoverGirdOverlay()

    //---------------Choose Markingcolor

    // ChangeStyle of Marks
    function ChangeStyle(MarkedAreaColor, UnselectedGridOverlayColor, ACT, UGOT) {
        jQuery('#styleTagAmpelColor').remove()
        styleTag = jQuery('<style id = "styleTagAmpelColor">.HoverGridOverlay { background-color: #' + MarkedAreaColor + ACT + '!important;}.UnselectedGridOverlay { background-color: #' + UnselectedGridOverlayColor + UGOT + ';}</style>')
        jQuery('html > head').append(styleTag)
    }


    //MarkeadArea ColorRed
    jQuery('#MarkedAreaColorRed').on('click', function () {
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
    jQuery('#MarkedAreaColorYellow').on('click', function () {
        MarkedAreaColor = 'ffff00'
        if (ColorInvert) {
            UnselectedGridOverlayColor = MarkedAreaColor
            MarkedAreaColor = '000000'
        } else {
            UnselectedGridOverlayColor = '000000'
        }
        // // jQuery('#SelectedMarkedAreaColorText').text('Gelb')
        // // jQuery('#SelectedMarkedAreaColorColor').css('backgroundColor', '#' + MarkedAreaColor)
        // jQuery('.MarkedAreaColor').css('backgroundColor', '').css('border', '')
        // jQuery(this).css('backgroundColor', '#' + MarkedAreaColor).css('border', '1px solid #336699')
        ChangeStyle(MarkedAreaColor, UnselectedGridOverlayColor, ACT, UGOT)

    })
    //MarkeadArea ColorGreen
    jQuery('#MarkedAreaColorGreen').on('click', function () {
        MarkedAreaColor = '00ff00'
        // MarkedAreaColor = '00ff00';
        if (ColorInvert) {
            UnselectedGridOverlayColor = MarkedAreaColor
            MarkedAreaColor = '000000'
        } else {
            UnselectedGridOverlayColor = '000000'
        }
        // // jQuery('#SelectedMarkedAreaColorText').text('Grün')
        // // jQuery('#SelectedMarkedAreaColorColor').css('backgroundColor', '#' + MarkedAreaColor)
        // jQuery('.MarkedAreaColor').css('backgroundColor', '').css('border', '')
        // jQuery(this).css('backgroundColor', '#' + MarkedAreaColor).css('border', '1px solid #336699')
        ChangeStyle(MarkedAreaColor, UnselectedGridOverlayColor, ACT, UGOT)

    })
    //MarkeadArea ColorWhite
    jQuery('#MarkedAreaColorWhite').on('click', function () {
        MarkedAreaColor = 'ffffff'
        if (ColorInvert) {
            UnselectedGridOverlayColor = MarkedAreaColor
            MarkedAreaColor = '000000'
        } else {
            UnselectedGridOverlayColor = '000000'
        }
        // jQuery(this).css('backgroundColor')
        // // jQuery('#SelectedMarkedAreaColorText').text('Weiß')
        // // jQuery('#SelectedMarkedAreaColorColor').css('backgroundColor', '#' + MarkedAreaColor)
        // jQuery('.MarkedAreaColor').css('backgroundColor', '').css('border', '')
        // jQuery(this).css('backgroundColor', '#' + MarkedAreaColor).css('border', '1px solid #336699')
        ChangeStyle(MarkedAreaColor, UnselectedGridOverlayColor, ACT, UGOT)

    })
    // // Initialising with ColorWhite
    // jQuery(document).ready(function () {
    //     if (MarkedAreaColor == 'ffffff') {
    //         jQuery(MarkedAreaColorWhite).css('backgroundColor', '#ffffff').css('border', '1px solid #336699')
    //     }
    // })

    //MarkeadArea ColorNone
    jQuery('#MarkedAreaColorNone').on('click', function () {
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
    jQuery('#ColorInvert').on('click', function () {
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
    jQuery('#ColorRed').on('click', function () {
        AmpelColor = 'ff0000'
        AjaxGet.AmpelColor = AmpelColor;

    })
    //ColorGreen Positiv
    jQuery('#ColorGreen').on('click', function () {
        AmpelColor = '00ff00'
        AjaxGet.AmpelColor = AmpelColor;

    })
    //ColorWhite Neutral
    jQuery('#ColorWhite').on('click', function () {
        AmpelColor = 'ffffff'
        AjaxGet.AmpelColor = AmpelColor;
    })

    //--------Markierungsfeld Ein/Aus:
    jQuery('#HideMarkarea').on('click', function () {

        if (jQuery('#GridOverlay table').is(':hidden')) {
            jQuery('#GridOverlay table').show()

            jQuery('#BackgroundColorTransparency,#ResetInput, #DeleteInput,.MarkedAreaColor').prop('disabled', false).css('background-color', '').css('border-color', '').css('color', '');
            // jQuery('#BackgroundColorTransparency').prop('disabled', false).css('background-color', '').css('border-color', '');
            // jQuery('#DeleteInput').prop('disabled', false).css('background-color', '').css('border-color', '');
            // jQuery('#ResetInput').prop('disabled', false).css('background-color', '').css('border-color', '');
            // jQuery('.MarkedAreaColor').prop('disabled', false).css('background-color', '').css('border', '').css('border-color', '');

            MarkedAreaColor = 'ffffff'

            // jQuery('#MarkedAreaColorWhite').css('backgroundColor', '#' + MarkedAreaColor).css('border', '1px solid #336699').css('border-color', '')

            jQuery(this).css('background-color', '').css('color', '');
        } else {
            jQuery('#GridOverlay table').hide()
            jQuery('#BackgroundColorTransparency,#ResetInput, #DeleteInput,.MarkedAreaColor').prop('disabled', true).css('background-color', '#f4f4f4').css('border-color', '#cecece').css('color', '#cecece');

            // jQuery('#DeleteInput').prop('disabled', true).css('background-color', '#f4f4f4').css('border-color', '#cecece');
            //
            // jQuery('#ResetInput').prop('disabled', true).css('background-color', '#975170').css('border-color', '#cecece');
            //
            // jQuery('.MarkedAreaColor').prop('disabled', true).css('background-color', '#f4f4f4').css('border-color', '#cecece');

            jQuery(this).css('background-color', '#1dace4').css('color', '#cecece');
        }
    })


    //BackgroundColorTransparency
    jQuery('#BackgroundColorTransparency').on('click', function () {

        ShadowOff = !ShadowOff
        if (ShadowOff) {
            ACT = '80' // AmpelColorTransparency
            UGOT = '00' //UnselectedGridOverlayTransparency
            jQuery(this).css('background-color', '#1dace4').css('color', '#cecece');

        } else {
            ACT = '80' // AmpelColorTransparency
            UGOT = '99' //UnselectedGridOverlayTransparency
            jQuery(this).css('background-color', '').css('color', '');

        }
        ChangeStyle(MarkedAreaColor, UnselectedGridOverlayColor, ACT, UGOT)
    })

//todo

    //--------Markierung löschen:
    jQuery('#DeleteInput').on('click', function () {

        AjaxGet.MarkedAreaColorFromDBbyID = false
        ArrayCordXY = [];
        ArrayColorXY = [];
        jQuery('#GridOverlay table').remove()
        FunkBuildGirdOverlay()
        FunkMousefunctionalityoverGirdOverlay()
    })

    //--------Ännderung zurücksetzen:
    jQuery('#ResetInput').on('click', function () {

        jQuery('#GridOverlay table').remove()
        ArrayCordXY = [];
        ArrayColorXY = [];
        FunkBuildGirdOverlay()
        FunkMousefunctionalityoverGirdOverlay()

    })

    //--------CheckInput:
    jQuery('#CheckInput').on('click', function () {

        //fixme;
        var StopTime = 10 //Math.round(jQuery("#" + AjaxGet.VideoElementId).get(0).currentTime);
        var $AddedComment = jQuery('#InputVideoComment').val()

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
    jQuery('#DropAndPlay').on('click', function () {
        // EditCommentsClicked = false;
        FunkKillFunctionalityOverlay(AjaxGet)
        FunkVideoPlayPause(AjaxGet)
    })

    //--------speichern & Fortfahren:
    jQuery('#SaveToDbAndPlay').on('click', function () {
        var StopTime = Math.round(jQuery('#' + AjaxGet.VideoElementId).get(0).currentTime)
        // var StopTime = jQuery('#' + AjaxGet.VideoElementId).get(0).currentTime.toFixed(1)

        var $AddedCommentArray = [jQuery('#InputVideoComment').val()]
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
    })

    //--------Retweet & Fortfahren:
    jQuery('#RetweetCommentAndPlay').on('click', function () {
        var StopTime = Math.round(jQuery('#' + AjaxGet.VideoElementId).get(0).currentTime)


        if (jQuery('#InputVideoComment').val() != "") {
            // var $AddedCommentArray = [jQuery('#InputVideoComment').val()]
            AjaxGet.$AddedCommentArray.push(AjaxGet.UserNickname)
            AjaxGet.$AddedCommentArray.push(jQuery('#InputVideoComment').val())


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
            jQuery('#RetweetsCommentsArea').append('<span style="border: 1px solid #d0d0d0; display: inline-block; width: 100%">' +
                ' <span class="glyphicon glyphicon-hand-right" aria-hidden="true" style="font-size: 1.5em; padding: 0 5px 0 5px; display: table-cell; vertical-align: middle;"> </span>' +
                ' <span style="color: #8b5957; display: table-cell; vertical-align: middle;">' + AjaxGet.UserNickname + ': </span><span style="font-size: 1.3em; display: table-cell; vertical-align: middle;">' + jQuery('#InputVideoComment').val() + '</span> ' +
                ' </span>')


            jQuery('#RetweetCommentAndPlay').hide()
            jQuery('#DropAndPlay').hide()
            jQuery('#InputVideoComment').prop('disabled', true).hide()

            FunkHideForCallCommentsImport()
            AjaxSend('database/DbInteraktion.php', {
                DbRequest: 'Insert',
                DbRequestVariation: 'RetweetInput',
                AjaxDataToSend: ArraySavedInput
            }, 'FunkCallCommentsImportAdmin')
            // }else {
            //     jQuery('#RetweetsCommentsArea').append('<span style="border: 1px solid #d0d0d0; display: inline-block; width: 100%">' +
            //         ' <span class="glyphicon glyphicon-hand-right" aria-hidden="true" style="font-size: 1.5em; padding: 0 5px 0 5px; display: table-cell; vertical-align: middle;"> </span>' +
            //         ' <span style="color: #8b5957; display: table-cell; vertical-align: middle;">'+ AjaxGet.UserNickname + ': </span><span style="font-size: 1.3em; display: table-cell; vertical-align: middle;"> Test' + jQuery('#InputVideoComment').val() + '</span> ' +
            //         ' </span>')
        }
    })

    //--------Edit & Fortfahren:
    jQuery('#EditCommentAndPlay').on('click', function () {
        var StopTime = Math.round(jQuery('#' + AjaxGet.VideoElementId).get(0).currentTime)
        // var $AddedComment = jQuery('#InputVideoComment').val()
        // alert( AjaxGet.$AddedCommentArray[0])
        //  AjaxGet.$EditedCommentHistory.push(AjaxGet.$AddedCommentArray[0])
        //  alert(AjaxGet.$EditedCommentHistory)

        if (AjaxGet.$AddedCommentArray[0] != jQuery('#InputVideoComment').val()) {
                    AjaxGet.$EditedCommentHistory.push(AjaxGet.UserNickname)
            AjaxGet.$EditedCommentHistory.push(AjaxGet.$AddedCommentArray[0])
            AjaxGet.$AddedCommentArray[0] = jQuery('#InputVideoComment').val()
        } else {
            AjaxGet.$AddedCommentArray[0] = (jQuery('#InputVideoComment').val())
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
        jQuery('#EditCommentAndPlay').hide()
        jQuery('#DeleteCommentAndPlay').hide()

        FunkHideForCallCommentsImport()

        AjaxSend('database/DbInteraktion.php', {
            DbRequest: 'Insert',
            DbRequestVariation: 'EditInput',
            AjaxDataToSend: ArraySavedInput
        }, 'FunkCallCommentsImportAdmin')


    })

    //--------Delete & Fortfahren:
    jQuery('#DeleteCommentAndPlay').on('click', function () {
        jQuery('#FunctionalityOverlay').after('<div id="DeleteCommentConfirm" style="text-align: center;">' +
            '</div>')
        jQuery('#FunctionalityOverlay').hide()
        jQuery('#DeleteCommentConfirm').html('<p><h2>Endgültig Löschen?</h2></p>' +
            '<input type="button" id="DeleteCommentConfirmYes" class="button_std" style="margin: 10px; background-color:red; font-weight: bold;" value="LÖSCHEN !">' +
            '<input type="button" id="DeleteCommentConfirmNo" class="button_std"  style="margin: 10px;  background-color:green;font-weight: bold;" value="Abrechen">'
        )

        jQuery('#DeleteCommentConfirmNo').on('click', function () {
            jQuery('#FunctionalityOverlay').show()
            jQuery('#DeleteCommentConfirm').remove()
        });

        jQuery('#DeleteCommentConfirmYes').on('click', function () {
            jQuery('#FunctionalityOverlay').show()
            jQuery('#DeleteCommentConfirm').remove()
            var StopTime = Math.round(jQuery('#' + AjaxGet.VideoElementId).get(0).currentTime)
            // var $AddedComment = jQuery('#InputVideoComment').val()
            var $AddedCommentArray = [jQuery('#InputVideoComment').val()]
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
            jQuery('#EditCommentAndPlay').hide()
            jQuery('#DeleteCommentAndPlay').hide()

            FunkHideForCallCommentsImport()
            AjaxSend('database/DbInteraktion.php', {
                DbRequest: 'Insert',
                DbRequestVariation: 'DeleteInput',
                AjaxDataToSend: ArraySavedInput
            }, 'FunkCallCommentsImportAdmin')
        });

    })

    //--------Undelete & Fortfahren:
    jQuery('#UndeleteCommentAndPlay').on('click', function () {
        var StopTime = Math.round(jQuery('#' + AjaxGet.VideoElementId).get(0).currentTime)
        // var $AddedComment = jQuery('#InputVideoComment').val()
        var $AddedCommentArray = [jQuery('#InputVideoComment').val()]
        var $AddedComment = JSON.stringify($AddedCommentArray);
        //Fixme
        // var $AddedCommentArray=[]
        // $AddedCommentArray.push(jQuery('#InputVideoComment').val());
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
        jQuery('#EditCommentAndPlay').hide()
        jQuery('#DeleteCommentAndPlay').hide()

        FunkHideForCallCommentsImport()
        AjaxSend('database/DbInteraktion.php', {
            DbRequest: 'Insert',
            DbRequestVariation: 'EditInput',
            AjaxDataToSend: ArraySavedInput
        }, 'FunkCallCommentsImportAdmin')
    });


}
