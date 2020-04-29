<?php
	session_start();
	require_once("inc/config.inc.php");
	require_once("inc/functions.inc.php");

	include("templates/header.inc.php");
	if (!is_checked_in()) {
?>
	<div class="container main-container registration-form">



		<?php
			//Todo
			//check ob Registrierung abgeschlossen ist
			$registernotdone=true;
			// Check ob berechtigunng für Registreirung vorliegt
			if (isset($_GET['preregister']) and $_POST['Registrierungskey'] == "TUK-Tuebingen") {
//if (isset($_GET['preregister'])) {
				$showFormular = true; //Variable ob das Registrierungsformular anezeigt werden soll
			} else {
				$showFormular = false; //Variable ob das Registrierungsformular anezeigt werden soll
			}
			if (isset($_GET['register']) and $_REQUEST['register'] == 'registering') {
				$error = false;
				$vorname = trim($_POST['vorname']);
				$nachname = trim($_POST['nachname']);
				$email = trim($_POST['email']);
				$Organisation = trim($_POST['Organisation']);
				$passwort = $_POST['passwort'];
				$passwort2 = $_POST['passwort2'];
				$DSGVO = $_POST['DSGVO'];

				if (empty($vorname) || empty($nachname) || empty($email)|| empty($Organisation)) {
					echo '- Bitte alle Felder ausfüllen (Keine Zahlen, Zeichen oder Umlaute)<br>';
					$error = true;
				}

				if (!ctype_alpha($vorname)) {
					echo '- Bitte einen gültigen Vornamen eingeben (Keine Zahlen, Zeichen oder Umlaute)<br>';
					$error = true;
				}

				if (!ctype_alpha($nachname)) {
					echo '- Bitte einen gültigen Nachnamen eingeben (Keine Zahlen oder Umlaute)<br>';
					$error = true;
				}

				if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
					echo '- Bitte eine gültige E-Mail-Adresse eingeben<br>';
					$error = true;
				}
				if (strlen($passwort) == 0) {
					echo '- Bitte ein Passwort angeben<br>';
					$error = true;
				}
				if ($passwort != $passwort2) {
					echo '- Die Passwörter müssen übereinstimmen<br>';
					$error = true;
				}
				if (!$DSGVO) {
					echo '- Bitte Datenschutzerklärung zustimmen<br>';
					$error = true;
				}

				//Überprüfe, dass die E-Mail-Adresse noch nicht registriert wurde
				if (!$error) {
					$statement = $pdo->prepare("SELECT * FROM users WHERE email = :email");
					$result = $statement->execute(array('email' => $email));
					$user = $statement->fetch();

					if ($user !== false) {
						echo 'Diese E-Mail-Adresse ist bereits vergeben<br>';
						$error = true;
					}
				}

				//Keine Fehler, wir können den Nutzer registrieren
				if (!$error) {
					$passwort_hash = password_hash($passwort, PASSWORD_DEFAULT);

					$statement = $pdo->prepare("INSERT INTO users (email, passwort, vorname, nachname, Organisation) VALUES (:email, :passwort, :vorname, :nachname, :Organisation)");
					$result = $statement->execute(array('email' => $email, 'passwort' => $passwort_hash, 'vorname' => $vorname, 'nachname' => $nachname, 'Organisation' => $Organisation));

					$statement = $pdo->prepare("SELECT * FROM users WHERE email = :email");
					$result = $statement->execute(array('email' => $email));
					$user = $statement->fetch();
					//Nickname zusammensetzen
					$nickname = $user['vorname'] . '[' . $user['id'] . ']';
					$statement = $pdo->prepare("UPDATE users SET nickname = :nickname WHERE  id=:id");
					$result = $statement->execute(array('nickname' => $nickname, 'id' => $user['id']));

					if ($result) {
						$registernotdone=false;
						echo 'Du wurdest erfolgreich registriert. Du wirst zum <a href="login.php">Login</a> weitergeleited';
						echo '<meta http-equiv="Refresh" content="5; url=login.php" />';
						header( "refresh:3; url=index.php" );
						$showFormular = false;
					} else {
						echo 'Beim Abspeichern ist leider ein Fehler aufgetreten<br>';
					}
				}
			}

//			$registernotdone=true;

			if ($registernotdone) {


//				header( "refresh:2; url=index.php" );

				echo "<h1>Registrierung</h1>";



//				if ($_REQUEST['register'] == 'allowed' or $_REQUEST['register'] == 'registering') {
				if ($showFormular or $_REQUEST['register'] == 'allowed' or $_REQUEST['register'] == 'registering') {


					?>

					<form action="?register=registering" method="post">

						<div class="form-group">
							<label for="inputVorname">Vorname: <br> (Keine Zahlen, Zeichen oder Umlaute)</label>
							<input type="text" id="inputVorname" size="40" maxlength="250" name="vorname"
							       class="form-control"
							       required1>
						</div>

						<div class="form-group">
							<label for="inputNachname">Nachname: <br> (Keine Zahlen, Zeichen oder Umlaute)</label>
							<input type="text" id="inputNachname" size="40" maxlength="250" name="nachname"
							       class="form-control"
							       required1>
						</div>

						<div class="form-group">
							<label for="inputOrganisation">Organisation:</label>
							<select type="text" id="inputOrganisation"  maxlength="250" name="Organisation"
							        class="form-control"
							        required1>
								<option value="">Bitte wählen</option>
								<option value="uni-kl">TU Kaiserslautern</option>
								<option value="uni-tuebingen">Eberhard Karls Universität Tübingen</option>
							</select>
						</div>

						<div class="form-group">
							<label for="inputEmail">E-Mail:</label>
							<input type="email" id="inputEmail" size="40" maxlength="250" name="email"
							       class="form-control"
							       required1>
						</div>

						<div class="form-group">
							<label for="inputPasswort">Dein Passwort:</label>
							<input type="password" id="inputPasswort" size="40" maxlength="250" name="passwort"
							       class="form-control" required1>
						</div>

						<div class="form-group">
							<label for="inputPasswort2">Passwort wiederholen:</label>
							<input type="password" id="inputPasswort2" size="40" maxlength="250" name="passwort2"
							       class="form-control" required1>
						</div>
						<div>
							<input id="DSGVO" type="checkbox" value="DSGVO_Accepted" name="DSGVO" value=""
							       style="margin-left: 0px;" required1>
							<label for="DSGVO">Ich habe die <a target="_blank" href="DSGVO.php" class="StandartTextlightColor">Datenschutzerklärung</a> gelesen  und stimme dieser zu.</label>
						</div>
						<button type="submit" class="btn btn-lg btn-primary btn-block">Registrieren</button>
					</form>

					<?php

				} else { ?>
					<form action="?register=allowed" method="post">
						<div class="form-group">
							<label for="Registrierungskey">Bitte Registrierungsschlüssel angeben</label>
							<input type="Text" id="Registrierungskey" size="40" maxlength="250" name="Registrierungskey"
							       class="form-control" required>
						</div>
						<button type="submit" class="btn btn-lg btn-primary btn-block">Registrierung starten</button>
					</form>
					<?php
				}
				} //Ende von if($showFormular)


		?>
	</div>
<?php
	} else{
		header("Location: index.php");
	}
	include("templates/footer.inc.php")
?>