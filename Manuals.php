<?php
	session_start();
	require_once("inc/config.inc.php");
	require_once("inc/functions.inc.php");
	include("templates/header.inc.php");
?>

	<div style="margin-left1: 50px; font-size: 1.2em;">

<h1>Anleitungen:</h1>
		<ul style="padding-bottom: 15px;">
			<li><a href="Manual_LivefeedbackPLUS.php" target="_blank"><?php echo $LivefeedbackplusLogoTextColor ?></a></li>

			<li><a href="ManualWinFF.php" target="_blank"><span style="font-family: 'Exo Bold', arial; font-size: 18px;">Videodatei konvertieren</span></a></li>

		</ul>



	</div>

<?php
	include("templates/footer.inc.php");
	?>