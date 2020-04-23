<?php
	session_start();
	require_once("inc/config.inc.php");
	require_once("inc/functions.inc.php");


	//Logo:
	$LivefeedbackplusLogoColor = '<a class="banner" href="index.php">
					<span style="line-height: 50px; float: bottom; font-family: \'Exo Bold\', arial;">
					<span style="color: #0f5270">Live</span>
						<span style="color: #1dace4">Feedback</span>
					<span style="color: #1dace4; position: relative; bottom: 0px;" class="glyphicon glyphicon-comment">
					<span style="color: #0f5270; position: absolute;left: 8px;top:3px;font-size:18px;">+</span>
					</span>
					</span>
				</a>';

	$LivefeedbackplusLogoTextColor = '<span style="font-family: \'Exo Bold\', arial; font-size: 20px;">
					<span style="color: #0f5270">Live</span>
						<span style="color: #1dace4">Feedback</span>
					<span style="color: #1dace4; position: relative;" class="glyphicon glyphicon-comment">
					<span style="color: #0f5270; position: absolute;left: 5px;top:3px;font-size:10px;">+</span>
					</span>
					</span>';

//define Pathroot
	define('ROOT_PATH', dirname(__DIR__) . '/');

	$dirScripts = "Scripts/JS/";
	$dirCss = "themes/default/css/";
	$dirVideo = "Resources/Videos/";
	$_SESSION['ScriptLoaded'] = '';
	$_SESSION['ScriptLoaded'] = false;

//initialization
	/** Scrips */
	if (is_dir($dirScripts)) {
		if ($dhScripts = opendir($dirScripts)) {
			$dirContentsScripts = scandir($dirScripts);
			$CountJsFiles = 0;
			$LoadedJsFiles = 0;

			foreach ($dirContentsScripts as $fileScripts) {
				$extension = pathinfo($fileScripts, PATHINFO_EXTENSION);

			}
//			echo "<script>\n";
//			echo "var LoadedJsFiles = 0;\n";
			foreach ($dirContentsScripts as $fileScripts) {
				$extension = pathinfo($fileScripts, PATHINFO_EXTENSION);

				if ($extension == 'js') {
					$CountJsFiles++;
//					echo "<script  src='$dirScripts$fileScripts' defer async></script>\n";
					echo "<script  src='$dirScripts$fileScripts'></script>\n";

//					echo "$.getScript('$dirScripts$fileScripts', function(){;\n";
//					echo "});\n";

				}
			}

//			echo "</script>\n";
			closedir($dhScripts);
			$_SESSION['ScriptLoaded'] = $CountJsFiles;


		}

	} else echo "No Scriptfolder found!!";


	/** CSS */
	if (is_dir($dirCss)) {
		if ($dhCss = opendir($dirCss)) {
			$dirContentsCss = scandir($dirCss);

			foreach ($dirContentsCss as $fileCss) {
				$extension = pathinfo($fileCss, PATHINFO_EXTENSION);
				if ($extension == 'css') {
					echo "<link rel='stylesheet' href='$dirCss$fileCss' type='text/css'>";
				}
			}
			closedir($dhCss);
		}
	} else echo "No Css-folder found!!";


	//	$_SESSION['ScriptLoaded'] = $CountJsFiles;
	//}
