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
	],
];

require_once __DIR__.'/../../../header.inc.php';

?>

<div style="margin-left: 20px; font-size: 1.2em;">


	<p class="MsoNormal"><b><span
				style="font-size:14.0pt;line-height:107%">Anleitung zum Tool </span></b><?php echo $LivefeedbackplusLogoTextColor; ?>
	</p>

	<p class="MsoNormal">Diese Zusammenstellung ist das erste Manual zur Nutzung des
		Tools smallPART - selfdirected media assisted learning lectures & Process Analysis Reflection Tool.
		<br/>
		Ergänzungen sind jederzeit willkommen.</p>

	<p class="MsoTocHeading">Inhaltsverzeichnis</p>
	<ol>
		<li class="MsoToc1"><a href="#_Toc38357326">Registrierung:<span
					style="color:windowtext;display:none;text-decoration:none"> </span><span
					style="color:windowtext;display:none;text-decoration:none">1</span></a></li>

		<li class="MsoToc1"><a href="#_Toc38357327">An- und Abmeldung; Konto verwalten:<span
					style="color:windowtext;display:none;text-decoration:none"> </span><span
					style="color:windowtext;display:none;text-decoration:none">2</span></a></li>

		<li class="MsoToc1"><a href="#_Toc38357328">Kursen beitreten/verlassen<span
					style="color:windowtext;display:none;text-decoration:none">. </span><span
					style="color:windowtext;display:none;text-decoration:none">2</span></a></li>

		<li class="MsoToc1"><a href="#_Toc38357329">Arbeiten mit den Videos:<span
					style="color:windowtext;display:none;text-decoration:none"> </span><span
					style="color:windowtext;display:none;text-decoration:none">3</span></a></li>
		<ol type="a">
			<li class="MsoToc2"><a href="#_Toc38357330">Kurse/Videos auswählen<span
						style="color:windowtext;display:none;text-decoration:none">. </span><span
						style="color:windowtext;display:none;text-decoration:none">3</span></a></li>

			<li class="MsoToc2"><a href="#_Toc38357331"> Videos und Wiedergabe steuern<span
						style="color:windowtext;display:none;text-decoration:none">. </span><span
						style="color:windowtext;display:none;text-decoration:none">3</span></a></li>

			<li class="MsoToc2"><a href="#_Toc38357332">Markierungen im Video setzen<span
						style="color:windowtext;display:none;text-decoration:none">. </span><span
						style="color:windowtext;display:none;text-decoration:none">3</span></a></li>

			<li class="MsoToc2"><a href="#_Toc38357333">Kommentare zum Video(zeitpunkt)
					verfassen<span style="color:windowtext;display:none;text-decoration:none">. </span><span
						style="color:windowtext;display:none;text-decoration:none">4</span></a></li>

			<li class="MsoToc2"><a href="#_Toc38357334">Arbeiten mit und an Kommentaren<span
						style="color:windowtext;display:none;text-decoration:none">. </span><span
						style="color:windowtext;display:none;text-decoration:none">5</span></a></li>

			<li class="MsoToc2"><a href="#_Toc38357335">Kommentare durchsuchen<span
						style="color:windowtext;display:none;text-decoration:none">. </span><span
						style="color:windowtext;display:none;text-decoration:none">5</span></a></li>
		</ol>
	</ol>
	<hr>
	<ol>
		<h1><br>
			<li><a name="_Toc38357326">Registrierung:</a>
		</h1>
		<div>

			<p>Zur Registrierung auf „Registrieren“ klicken</p>

			<p><img width="604" height="40" id="Grafik 3" src="image002.jpg "></p>
			<p>Um die Registrierung starten zu können, muss der vom Dozenten zur Verfügung gestellte
				Registrierungsschlüssel
				angegeben werden.</p>

			<p><img width="218" height="96" id="Grafik 20" src="image003.jpg"></p>

			<p>Füllen Sie die Felder aus, bestätigen Sie die Datenschutzerklärung und klicken Sie auf
				„Registrieren“</p>

			<p><img width="199" height="226" src="image004.png"></p>


		</div>
		</li>

		<h1><br>
			<li><a name="_Toc38357327">An- und Abmeldung; Konto verwalten:</a>
		</h1>
		<div>

			<p>Zur Anmeldung werden E-Mail-Adresse und Passwort in die dafür vorgesehenen Bereiche
				eingetragen und auf den
				hellblauen „Login“-Button geklickt.</p>

			<p>Wird ein Häkchen bei angemeldet bleiben gesetzt ist eine Anmeldung beim erneuten Start
				der App nicht mehr
				notwendig (solange der Cache nicht gelöscht wird).</p>

			<p><img width="605" height="40" id="Grafik 22"
			        src="image005.png"></p>

			<p>Die Abmeldung erfolgt über den „Logout“-Button am oberen rechten Bildschirmrand</p>

			<p><img width="330" height="50" id="Grafik 1"
			        src="image006.jpg"></p>


			<p>Über den Button „Konto verwalten“ können persönliche Daten, E-Mail-Adresse und Passwort
				geändert werden</p>

			<p>Der vom System erstellte Nickname (Hier: „Eva[21]“) kann jedoch nicht geändert
				werden.</p>

		</div>
		</li>

		<h1><br>
			<li><a name="_Toc38357328">Kursen beitreten/verlassen</a>
		</h1>
		<div>

			<p>Mit dem Button „Kurse verwalten“ in der oberen Menüleiste können Studenten Kursen
				beitreten bzw. aus Kursen
				in
				denen sie angemeldet sind austreten.</p>

			<p>Um einem Kurs beizutreten wird dieser über die Drop-Down-Liste ausgewählt und das
				dazugehörige Passwort
				eingegeben und mit dem Button „Kurs beitreten“ bestätigt.</p>

			<p>Um aus einem Kurs auszutreten wird dieser über die Drop-Down-Liste ausgewählt und
				mit dem Button „Aus dem
				Kurs
				austreten“ bestätigt.</p>

		</div>
		</li>

		<h1><br>
			<li><a name="_Toc38357329">Arbeiten mit den Videos:</a>
		</h1>
		<div>
			<ol type="a">

				<h2><br>
					<li><a name="_Toc38357330">Kurse/Videos auswählen</a>
				</h2>
				<div>

					<p>Zunächst wird der entsprechende Kurs und das zu
						bearbeitende Video aus der Drop-Down-Liste
						ausgewählt.</p>

					<p><img width="605" height="75" id="Grafik 2"
					        src="image007.jpg"></p>

					<p>Nach Auswahl des Videos wird dieses mit der
						entsprechenden Aufgabenstellung dargestellt. </p>

				</div>
				</li>

				<h2><br>
					<li><a name="_Toc38357331">Videos und Wiedergabe steuern</a>
				</h2>
				<div>


					<p>Das Video wird mit dem „Play Video“-Button
						gestartet.</p>

					<p><img width="621" height="297" id="Grafik 4"
					        src="image009.jpg">
					</p>

					<p>Das Video kann mit dem „Pause Button“ ohne
						weiterführende Aktionen auszulösen gestoppt
						werden.</p>

					<p>Durch Klicken in die graue Zeitleiste springt die
						Wiedergabe des Videos an die entsprechende
						relative
						zeitliche
						Position</p>

				</div>
				</li>

				<h2><br>
					<li><a name="_Toc38357332">Markierungen im Video setzen</a>
				</h2>
				<div>

					<p>Das Video kann mit dem „Video zum
						Bearbeiten
						pausieren“-Button zur weiteren
						Bearbeitung angehalten werden.</p>

					<p><img width="335" height="287"
					        id="Grafik 6"
					        src="image010.jpg">
					</p>

					<p>Unterhalb des Videos erscheint nun ein
						Bearbeitungsfeld für Markierungen und
						ein Kommentarfeld mit
						jeweils
						weiteren Buttons zur Bearbeitung </p>

					<p><img width="605" height="313"
					        id="Grafik 8"
					        src="image011.jpg">
					</p>


					<p>Unter dem Feld „Markierung“ können Sie
						verschiedene Farben zum Markieren im
						Video auswählen, um z.B.
						Personen,
						Geräte oder Positionen in einem
						Tafelbild zu markieren. Die Markierung
						machen Sie einfach mit der linken
						Maustaste an entsprechender Stelle. Die
						Verdunkelung des Videos kann mit dem
						Button „abdunkeln“
						gesteuert
						werden. </p>

					<p>Zum Löschen einer einzelnen Markierung
						kann diese erneut angeklickt werden,
						sollen alle Markierungen
						gelöscht
						werden erfolgt dies über den Button
						„löschen“.</p>

				</div>
				</li>

				<h2><br>
					<li><a name="_Toc38357333">Kommentare zum Video(zeitpunkt) verfassen</a>
				</h2>
				<div>

					<p>Im Feld „Kommentar“ können Sie
						zunächst mittels Farbauswahl
						eine Bewertungskategorie für
						ihren Kommentar
						einstellen und dann in das Feld
						den Kommentar schreiben.</p>

					<p>Der Button „Verwerfen und weiter“
						bricht die Bearbeitung ab und
						lässt das Video an der
						pausierten Stelle
						weiterlaufen.</p>

					<p>Der Button „Speichern und weiter“
						speichert die Markierungen und
						Kommentare.</p>

					<p>Im Zeitleiste erscheint eine
						Markierung an der Stelle, an der
						der Kommentar gesetzt
						wurde. </p>

					<p>Nach dem Speichern erscheinen die
						Kommentare rechts neben dem
						Video.</p>


					<p><img width="605" height="279"
					        id="Grafik 9"
					        src="image012.jpg">
					</p>

				</div>
				</li>

				<h2><br>
					<li><a name="_Toc38357334">Arbeiten mit und an Kommentaren</a>

				</h2>
				<div>

					<p>Mit der Antworten
						Funktion (siehe rote
						Markierung oben) können
						Sie auf Kommentare
						anderer eingehen. Es
						öffnet
						sich ein Kommentarfeld
						unter dem Video, in
						welchem Sie auf den
						Kommentar reagieren
						können.</p>

					<p>Mit „Rekommentieren und
						weiter“ senden sie den
						Kommentar ab und er
						erscheint unter dem
						ursprünglichen
						Kommentar. </p>

					<p class="MsoNormal"><img
							width="605"
							height="227"
							id="Grafik 11"
							src="image013.jpg">
					</p>

					<p>Ein Klick auf das
						Dunkelgraue „Label-Feld“
						zu einem Kommentar, das
						den Verfasser und den
						Videozeitpunkt
						enthält,
						wird dieser Kommentar in
						voller Länge direkt
						unter dem Video, das
						dann zum
						korrespondierenden
						Videozeitpunkt
						springt, angezeigt. </p>

					<p>Für das intensive Lesen
						von Kommentaren kann mit
						einem Auswahlbutton
						eingestellt werden, dass
						beim
						Bewegen des
						Mousezeigers in den
						Kommentarbereich das
						Video pausiert. So wird
						vermieden, dass an
						ständig wechselnden
						Positionen Steuerbuttons
						gedrückt werden müssen.
						Nach dem Verlassen
						dieses Bereichs mit der
						Mouse läuft
						das
						Video weiter.</p>

				</div>
				</li>

				<h2><br>
					<li><a name="_Toc38357335">Kommentare durchsuchen</a>
				</h2>
				<div>

					<p>Die Kommentare
						können mit dem
						Filter nach
						Kategorie
						selektiert
						werden.</p>

					<p>Durch Eingabe
						eines
						Suchbegriffes
						werden nur die
						Kommentare
						angezeigt, die
						diesen Begriff
						(exakt diese
						Zeichenfolge)
						enthalten.</p>

					<p>Die Löschtaste in
						diesem Bereich
						setzt den Filter
						und den
						Suchbegriff
						zurück, so dass
						alle Kommentare
						angezeigt
						werden.</p>
				</div>
				</li>
			</ol>
		</div>
		</li>

	</ol>
</div>

