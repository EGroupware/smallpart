<?php
use EGroupware\SmallParT\Bo;
?>
<!DOCTYPE html>
<html lang="de">
<head>
	<meta charset="utf-8">
	<meta http-equiv="X-UA-Compatible" content="IE=edge">
	<meta name="viewport" content="width=device-width, initial-scale=1">
	<!-- The above 3 meta tags *must* come first in the head; any other head content must come *after* these tags -->
	<title>LIVEFEEDBACKPLUS</title>

	<!-- Bootstrap core CSS -->
	<link href="css/bootstrap.min.css" rel="stylesheet">
	<link rel="stylesheet" href="css/bootstrap-theme.min.css">

	<!-- Custom styles for this template -->
	<link href="css/style.css" rel="stylesheet">

	<!--Jquery-->
	<!--script type="text/javascript" src="Resources/Web_Librarys/jquery-3.4.1.min.js"></script-->
	<script src="../vendor/bower-asset/jquery/dist/jquery.js"></script>
	<script>$ = jQuery;</script>


	<?php //Todo ../ verstecken
		include("utils/ToLoadScript.php");

	?>

</head>
<body>

<div class="flexdiplay flexdiplaycolumn flexnotgrowcolumn100">

	<div class="menu-bar-flexdisplay">
        <div id="menu-bar-nav">

			<ul id="menu-bar" class="StandartTextLeft">

				<li>
					<a href="internal.php">Kurse</a>
				</li>

				<li>
					<a href="KursVerwaltung.php">Kurse verwalten</a>
				</li>



				<?php if (Bo::isAdmin()) {
					echo '<li><a href="Verwaltung.php">Lehrperson: Verwaltung</a></li>';
				} ?>

				<li>
					<a href="Manuals.php">|&nbsp;Hilfe&nbsp;|</a>
				</li>

			</ul>
		</nav>
	</div>


	<script>
        $("#menu-bar li").on({
            mouseover: function () {
                $("#menu-bar li a").removeClass('active')
            }
            ,

            mouseout: function () {
                $("li a[href='" + window.location.pathname.replace('/livefeedbackPLUS/', '') + "']").addClass('active')

            }

        });
        $("li a[href='" + window.location.pathname.replace('/livefeedbackPLUS/', '') + "']").addClass('active')
	</script>
</div>


<div id="VideoList"></div>
<div class="Mid flexgrow flexdiplay">