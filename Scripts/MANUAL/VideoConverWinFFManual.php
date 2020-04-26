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
			<td><a href="https://vikole.bio.uni-kl.de/livefeedbackPLUS/ManualWinFF.php" target="_blank">
					https://vikole.bio.uni-kl.de/livefeedbackPLUS/ManualWinFF.php</td>
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

				<img src="Resources/Gifs/ConvertStep1.PNG" width="400" class="HowToConvertVideoWinFF zoom">
				<br>
				<br>
				<img src="Resources/Gifs/ConvertStep2.PNG" width="400" class="HowToConvertVideoWinFF zoom">
				<br>
				<br>
				<img src="Resources/Gifs/ConvertStep3.PNG" width="400" class="HowToConvertVideoWinFF zoom">
				<br>
				<br>
				<img src="Resources/Gifs/ConvertingProcess.PNG" width="400" class="HowToConvertVideoWinFF zoom">
				<br>
				<br>
				<img src="Resources/Gifs/ConvertingFinished.PNG" width="400" class="HowToConvertVideoWinFF zoom">
			</td>
		</tr>
		</tbody>
	</table>

</div>

<script>
    // async script loading requires to wait for script to be loaded
    egw_LAB.wait(function() {
        $('#ShowHowToConvert').on('click', function () {
            $('#ShowHowToConvert').text(function (i, text) {
                console.log($('#ShowHowToConvert').text())
                return text === "Verbergen" ? "Anzeigen" : "Verbergen";
            })
            $('.HowToConvertVideoWinFF').toggle()

        })
    });
</script>