<?php
	session_start();
	require_once("inc/config.inc.php");
	require_once("inc/functions.inc.php");

	$error_msg = "";
	if (isset($_POST['email']) && isset($_POST['passwort'])) {
		$email = $_POST['email'];
		$passwort = $_POST['passwort'];

		$statement = $pdo->prepare("SELECT * FROM users WHERE email = :email");
		$result = $statement->execute(array('email' => $email));
		$user = $statement->fetch();

		//Überprüfung des Passworts
		if ($user !== false && password_verify($passwort, $user['passwort'])) {
			$_SESSION['userid'] = $user['id'];
			$_SESSION['userorganisation'] = $user['Organisation'];
			$_SESSION['usereemail'] = $user['email'];
			$_SESSION['nickname'] = $user['nickname'];
			$_SESSION['userrole'] = $user['userrole'];
			$_SESSION['superadmin'] = $user['Superadmin'];

			//Loginlog

//			$ip = $_SERVER['REMOTE_ADDR'];
//			$ipinfo = file_get_contents("https://ipinfo.io/" . $ip);
//			$ipinfo_json = json_decode($ipinfo, true);
//
//
//
//			$statement = $pdo->prepare("INSERT INTO loged (UserId, nickname, Time, ip, city, region, country, loc, postleitzahl, org) VALUES (:UserId, :nickname, NOW() , :ip, :city, :region, :country, :loc, :postleitzahl, :org) ON DUPLICATE KEY UPDATE nickname= :nickname, Time= NOW(), ip=:ip , city=:city, region=:region, country=:country, loc=:loc, postleitzahl= :postleitzahl, org = :org");
//
//
//			$result = $statement->execute(array('UserId'=>$user['id'], 'nickname' => $user['nickname'], 'ip' => $ipinfo_json['ip'], 'city' => $ipinfo_json['city'], 'region' => $ipinfo_json['region'], 'country' => $ipinfo_json['country'], 'loc' => $ipinfo_json['loc'], 'postleitzahl' => $ipinfo_json['postal'], 'org' => $ipinfo_json['org']));


			$statement = $pdo->prepare("INSERT INTO loged (UserId, nickname, Time) VALUES (:UserId, :nickname, NOW() ) ON DUPLICATE KEY UPDATE nickname= :nickname, Time= NOW()");


			$result = $statement->execute(array('UserId' => $user['id'], 'nickname' => $user['nickname']));

			//Möchte der Nutzer angemeldet beleiben?
			if (isset($_POST['angemeldet_bleiben'])) {
				$identifier = random_string();
				$securitytoken = random_string();

				$insert = $pdo->prepare("INSERT INTO securitytokens (user_id, identifier, securitytoken) VALUES (:user_id, :identifier, :securitytoken)");
				$insert->execute(array('user_id' => $user['id'], 'identifier' => $identifier, 'securitytoken' => sha1($securitytoken)));
				setcookie("identifier", $identifier, time() + (3600 * 24 * 365)); //Valid for 1 year
				setcookie("securitytoken", $securitytoken, time() + (3600 * 24 * 365)); //Valid for 1 year
			}

			header("location: internal.php");
			//header("location: ../Frontend_Student/Videoanalyse.php");
			exit;
		} else {
			$error_msg = "E-Mail oder Passwort war ungültig<br><br>";
		}

	}

	$email_value = "";
	if (isset($_POST['email']))
		$email_value = htmlentities($_POST['email']);

	include("templates/header.inc.php");
?>
	<div class="container small-container-330 form-signin" xmlns="http://www.w3.org/1999/html">
		<form action="login.php" method="post">
			<h2 class="form-signin-heading">Login</h2>

			<?php
				if (isset($error_msg) && !empty($error_msg)) {
					echo $error_msg;
				}
			?>
			<label for="inputEmail" class="sr-only">E-Mail</label>
			<input type="email" name="email" id="inputEmail" class="form-control" placeholder="E-Mail"
			       value="<?php echo $email_value; ?>" required autofocus>
			<label for="inputPassword" class="sr-only">Passwort</label>
			<input type="password" name="passwort" id="inputPassword" class="form-control" placeholder="Passwort"
			       required>
			<div>
				<label>
					<input type="checkbox" value="remember-me" name="angemeldet_bleiben" value="1" checked
					       style="margin-left: 0px; width: 30px;">
					Angemeldet bleiben
				</label>

				<!--				<label>-->
				<!--					<input type="checkbox" value="remember-me" name="angemeldet_bleiben" value="1" checked-->
				<!--					       style="margin-left: 0px; width: 30px;">-->
				<!--					Angemeldet bleiben-->
				<!--				</label>-->

			</div>
			<button class="btn btn-lg btn-primary btn-block" type="submit">Login</button>
			<br>
			<a href="passwortvergessen.php">Passwort vergessen</a>
		</form>

	</div> <!-- /container -->


<?php
	include("templates/footer.inc.php")
?>