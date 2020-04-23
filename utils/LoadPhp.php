<?php
	$db_host = 'localhost';
	$url = $_SERVER['REQUEST_URI'];

	if (strpos($url, 'ViKoLe_v1') !== false) {
		include("../../../db_settings.php");
	} elseif (strpos($url, 'ViKoLe_v2') !== false) {
		include("../../../db_settings_lena.php");
	} else {
		include("../../../db_settings_Vikole.php");
	}

	function umlautepas($string)
	{
		$upas = array(
			"ä" => "ae",
			"ü" => "ue",
			"ö" => "oe",
			"Ä" => "Ae",
			"Ü" => "Ue",
			"Ö" => "Oe",
			"ß" => "ss",
			"#" => "_",
			" " => "_",
			"-" => "_",
			"__" => "_"
		);
		return strtr(strtr(strtr(strtr(strtr($string, $upas), $upas), $upas), $upas), $upas);
	}

	include("../utils/Ajax.php");

