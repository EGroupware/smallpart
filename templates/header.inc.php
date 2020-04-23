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
		<div id="menu-bar-Logo">
			<?php echo $LivefeedbackplusLogoColor; ?>
		</div>
		<div id="menu-bar-nav">
			<?php if (!is_checked_in()): ?>
				<div id="navbar" class="navbar-collapse collapse">
					<form class="navbar-form navbar-right" action="login.php" method="post">
						<table class="login" role="presentation">
							<tbody>
							<tr>
								<td>
									<div class="input-group">
										<div class="input-group-addon">
											<span class="glyphicon glyphicon-envelope"></span>
										</div>
										<input class="form-control" placeholder="E-Mail" name="email" type="email"
										       required>
									</div>
								</td>
								<td>
									<div class="input-group">
										<div class="input-group-addon">
											<span class="glyphicon glyphicon-lock"></span>
										</div>
										<input class="form-control" placeholder="Passwort" name="passwort"
										       type="password" value="" required>
									</div>
								</td>

								<td>
									<button type="submit" class="button_std_blue">Login</button>
								</td>
								<td><a href="register.php" role="button">
										<button type="button" class="button_std_darkblue">Registrieren</button>
									</a></td>

								<td class="StandartTextLeftight">
									<label id="AngemeldetBleiben"
									       class="controlCheckboxColored controlCheckboxColored-checkbox">
										Angemeldet bleiben
										<input type="checkbox" name="angemeldet_bleiben" value="remember-me"
										       title="Angemeldet bleiben" checked="checked"/>
										<div class="controlCheckboxColored_indicator"></div>
									</label>
									<a href="passwortvergessen.php" class="StandartTextLeftight">Passwort
										vergessen</a>
									&nbsp;<a href="Manuals.php" target="_blank">|&nbsp;Hilfe&nbsp;|</a>
								</td>


							</tr>
							</tbody>
						</table>


					</form>
				</div>
				</div>
				<!--/.navbar-collapse -->
			<?php else:
				$LogedUser = $_SESSION['nickname']; ?>

			<ul id="menu-bar" class="StandartTextLeft">

				<li>
					<a href="internal.php">Kurse</a>
				</li>

				<li>
					<a href="KursVerwaltung.php">Kurse verwalten</a>
				</li>



				<?php if ($_SESSION['userrole'] === 'Admin') {
					echo '<li><a href="Verwaltung.php">Lehrperson: Verwaltung</a></li>';
				} ?>

				<li>
					<a href="settings.php">Konto verwalten</a>
				</li>

				<li>
					<a href="Manuals.php" target="_blank">|&nbsp;Hilfe&nbsp;|</a>
				</li>

				<li>
					<a href="logout.php" role="button" id="logouthref">
						<button type="button" class="button_std_darkblue_logout">Logout</button>
						<?php echo "<span class='StandartTextLeftight'> Benutzer: &nbsp;</span>" . $LogedUser; ?>
					</a>
				</li>
			</ul>
		</nav>
	</div>
	<?php endif; ?>


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