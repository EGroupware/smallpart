<?php
require_once __DIR__.'/../inc/config.inc.php';

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

require_once __DIR__.'/Ajax.php';
