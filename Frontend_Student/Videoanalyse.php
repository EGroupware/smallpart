<?php //Todo ../ verstecken
use EGroupware\Api;
use EGroupware\SmallParT\Bo;

//    include("utils/ToLoadScript.php");
	//require_once 'Scripts/PHP/SelectVideo2.php';

?>

<div id="Mid">
	<!--	<div id="VideoList"></div>-->
	<div id="wrapper_left">
		<div id="Mid_left">
			<div id="VideoDivParentleft"></div>
			<div id="VideoDivParent"></div>
		</div>
	</div>
	<div id="wrapper_right">
		<div id="Mid_right_test"></div>
		<div id="Mid_right"></div>
	</div>
	<div id="Mid_below">
		<div id="Mid_below_left"></div>
		<div id="Mid_below_right"></div>
	</div>
</div>
<?php


	//	$StartFunkLoadKursList .= "AjaxSend('database/DbInteraktion.php', {
	//	        DbRequest: 'Select',
	//	        DbRequestVariation: 'FunkShowKurslist',
	//	        AjaxDataToSend: {KursID: ''}
	//	    }, 'FunkLoadKursList');\n";

	$StartFunkShowKursAndVideolist = "AjaxSend('database/DbInteraktion.php', {
	        DbRequest: 'Select',
	        DbRequestVariation: 'FunkShowKursAndVideolist',
	        AjaxDataToSend: {KursID: ''}
	    }, 'FunkShowKursAndVideolist');\n";


	$StartFunkLoadKursListDevelop = " AjaxSend('database/DbInteraktion.php', {
	        DbRequest: 'Select',
	        DbRequestVariation: 'FunkLoadVideo',
	        AjaxDataToSend: {
                VideoElementId: 'video__253',
                KursID: '33',
                VideoSrc: 'Resources/Videos/Video/33/Uen_oe_ewname.mp4',
                VideoExtention: 'mp4'
            }
	    }, 'FunkLoadVideo')\n";

/*
	$StartFunkLoadKursListDevelopAmpel = "
var checkExist = setInterval(function() {
  if (jQuery('#Medien1').length) {
	AjaxSend('database/DbInteraktion.php', {
	        DbRequest: 'Select',
	        DbRequestVariation: '',
	        AjaxDataToSend: {
                VideoElementId: 'Medien1',
                KursID: '1',
                VideoSrc: 'Resources/Videos/Video/1/Medien1.mp4',
                VideoExtention: 'mp4'
            }
	    }, 'FunkAmpelFunktion')
	    clearInterval(checkExist);
	     }
}, 100);



    var checkExist2 = setInterval(function() {
        if (jQuery('#KillCommentsAndPlayAdmin').length) {
//            jQuery('#KillCommentsAndPlayAdmin').hide();
//            $('#Medien1FunkVideoPlayPause').hide();
//            $('.button_std').show();
//            $('#DeleteCommentAndPlay').show();
    
    
            clearInterval(checkExist);
        }
    }, 100);
  
	
    ";

*/
	if (isset($_SESSION['ScriptLoaded'])) {

		echo '<script>';
		// async script loading requires to wait for script to be loaded
		echo "\negw_LAB.wait(function() {\n";
		echo 'var n = jQuery("head script" ).length;';

//		echo 'if (n>'.$_SESSION['ScriptLoaded'].'){' . $StartFunkLoadKursList . '}';

		echo 'if (n>' . $_SESSION['ScriptLoaded'] . '){' . $StartFunkShowKursAndVideolist . '}';

		if (Bo::getNickname() == 'Arash[19]') {

//			echo 'if (n>' . $_SESSION['ScriptLoaded'] . '){' . $StartFunkLoadKursListDevelop . '}';
//
//			echo ' $("#video__253").get(0).play()';

//		echo 'if (n>' . $_SESSION['ScriptLoaded'] . '){' . $StartFunkLoadKursListDevelopAmpel . '}';
		}
		echo "\n});\n";
		echo '</script>';

	}


?>

<script>
</script>
