<?php

	session_start();

	if($_SESSION['nickname'] == "") {

		die('<br>&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp; Sitzung abgelaufen, bitte wieder <a href="login.php"><u class="StandartTextlightColor">einloggen</u></a>');

	}else{
		echo true;
	}



/*


	require_once("inc/config.inc.php");
	require_once("inc/functions.inc.php");

//Überprüfe, dass der User eingeloggt ist
//Der Aufruf von check_user() muss in alle internen Seiten eingebaut sein
	$user = check_user();
*/