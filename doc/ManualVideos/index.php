<?php
/**
 * EGroupware - smallPART - manual
 *
 * @link http://www.egroupware.org
 * @package smallpart
 * @subpackage setup
 * @license http://opensource.org/licenses/gpl-license.php GPL - GNU General Public License
 */

use EGroupware\Api;

$GLOBALS['egw_info'] = [
	'flags' => [
		'currentapp' => 'smallpart',
		'nonavbar'   => true,
        'noheader'   => true,
	],
];

require_once __DIR__.'/../../../header.inc.php';

Api\Header\ContentSecurityPolicy::add_script_src(['unsafe-eval', 'unsafe-inline']);

unset($GLOBALS['egw_info']['flags']['noheader']);
echo $GLOBALS['egw']->framework->header();

?>
<style>
    .zoom {
        transition: transform .2s; /* Animation */
        width: 400px;
        margin: 0 auto;
    }

    .zoom:hover {
        transform: scale(2); /* (150% zoom - Note: if the zoom is too large, it will go outside of the viewport) */
    }
</style>
<div style="margin-left: 20px; font-size: 1.2em;">

	<table class="TableCSS">
		<thead>
		<th class="StandartTextH2" colspan="2">Vor dem Upload zu beachten:</th>

		</thead>
		<tbody>
		<tr>
			<th>Sehr Wichtig:</th>
			<td>Videodatei sollte konvertiert sein!</td>
		</tr>
		<tr style="display: none" class="ShowHowToConvert">
			<th>Anleitung für Studierene:</th>
			<td><a href="../ManualUser/" target="_blank">User Manual</td>
		</tr>
		<tr>
			<th>warum konvertieren?</th>
			<td>
				Verbesserung von Streamverhalten
				<br>verkleinert die Videodateigröße (bis zu 95%)
				<br>Wahrnehmabre Audio- und Videoqualität bleiben bestehen
			</td>
		</tr>
		<tr>
			<th>Unterstützte Format:</th>
			<td>
				.MP4 <br>
				.WebM
			</td>
		</tr>
		<tr>
			<th>Bevorzugte Videogröße:</th>
			<td>~ 3,4 MB pro Minute (d.h. bei 90 Minuten Videolaufzeit, wären ca. 306 MB Videogröße optimal)</td>
		</tr>
		<tr>
			<th>Upload max. Größe:</th>
			<td>500 MB</td>
		</tr>
		<tr>
			<th>Freeware-tool zum Konvertieren</th>
			<td>
				<li>
					<a href="https://www.biggmatt.com/p/winff.html" target="_blank">WinFF</a>
				</li>
				<li>
					<a href="http://www.winpenpack.com/main/request.php?1093" target="_blank">WinFF - Portable
						Version</a>
				</li>
			</td>
		</tr>
		<tr>
			<th>Anleitung zum Konvertieren</th>
			<td>
				<button id="ShowHowToConvert" class="btn btn-primary ShowHowToConvert" style="display: none">Anzeigen
				</button>
				<br class="ShowHowToConvert" style="display: none">
				<br>

				<img src="ConvertStep1.PNG" width="400" class="HowToConvertVideoWinFF zoom">
				<br>
				<br>
				Für 4:3 Format: Video Size = 800x600 / Aspect Ratio = 4:3
				<br>
				Für 16:9 Format: Video Size = 800x450 / Aspect Ratio = 16:9
				<br>
				<img src="ConvertStep2.PNG" width="400" class="HowToConvertVideoWinFF zoom">
				<br>
				<br>
				<img src="ConvertStep3.PNG" width="400" class="HowToConvertVideoWinFF zoom">
				<br>
				<br>
				<img src="ConvertingProcess.PNG" width="400" class="HowToConvertVideoWinFF zoom">
				<br>
				<br>
				<img src="ConvertingFinished.PNG" width="400" class="HowToConvertVideoWinFF zoom">
			</td>
		</tr>
		</tbody>
	</table>

</div>

<script>
    // async script loading requires to wait for script to be loaded
    egw_LAB.wait(function() {
        jQuery('#ShowHowToConvert').on('click', function () {
            jQuery('#ShowHowToConvert').text(function (i, text) {
                console.log(jQuery('#ShowHowToConvert').text())
                return text === "Verbergen" ? "Anzeigen" : "Verbergen";
            })
            jQuery('.HowToConvertVideoWinFF').toggle()

        })
    });
</script>