<?php
	session_start();
	require_once("inc/config.inc.php");
	require_once("inc/functions.inc.php");

	//Überprüfe, dass der User eingeloggt ist
	//Der Aufruf von check_user() muss in alle internen Seiten eingebaut sein
	$user = check_user();

	include("templates/header.inc.php");

	if (isset($_GET['save'])) {
		$save = $_GET['save'];

		if ($save == 'personal_data') {
			$vorname = trim($_POST['vorname']);
			$nachname = trim($_POST['nachname']);

			if ($vorname == "" || $nachname == "") {
				$error_msg = "Bitte Vor- und Nachname ausfüllen.";
			} else {
				$statement = $pdo->prepare("UPDATE users SET vorname = :vorname, nachname = :nachname, updated_at=NOW() WHERE id = :userid");
				$result = $statement->execute(array('vorname' => $vorname, 'nachname' => $nachname, 'userid' => $user['id']));

				$success_msg = "Daten erfolgreich gespeichert.";
			}
		} else if ($save == 'email') {
			$passwort = $_POST['passwort'];
			$email = trim($_POST['email']);
			$email2 = trim($_POST['email2']);

			if ($email != $email2) {
				$error_msg = "Die eingegebenen E-Mail-Adressen stimmten nicht überein.";
			} else if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
				$error_msg = "Bitte eine gültige E-Mail-Adresse eingeben.";
			} else if (!password_verify($passwort, $user['passwort'])) {
				$error_msg = "Bitte korrektes Passwort eingeben.";
			} else {
				$statement = $pdo->prepare("UPDATE users SET email = :email WHERE id = :userid");
				$result = $statement->execute(array('email' => $email, 'userid' => $user['id']));

				$success_msg = "E-Mail-Adresse erfolgreich gespeichert.";
			}

		} else if ($save == 'passwort') {
			$passwortAlt = $_POST['passwortAlt'];
			$passwortNeu = trim($_POST['passwortNeu']);
			$passwortNeu2 = trim($_POST['passwortNeu2']);

			if ($passwortNeu != $passwortNeu2) {
				$error_msg = "Die eingegebenen Passwörter stimmten nicht überein.";
			} else if ($passwortNeu == "") {
				$error_msg = "Das Passwort darf nicht leer sein.";
			} else if (!password_verify($passwortAlt, $user['passwort'])) {
				$error_msg = "Bitte korrektes Passwort eingeben.";
			} else {
				$passwort_hash = password_hash($passwortNeu, PASSWORD_DEFAULT);

				$statement = $pdo->prepare("UPDATE users SET passwort = :passwort WHERE id = :userid");
				$result = $statement->execute(array('passwort' => $passwort_hash, 'userid' => $user['id']));

				$success_msg = "Passwort erfolgreich gespeichert.";
			}

		} else if ($save == 'Userpasswort' and $user['Superadmin']) {
			$UserToCanchePWID = $_POST['UserToCanchePWID'];
			$passwortNeu = trim($_POST['passwortNeu']);
			$passwortNeu2 = trim($_POST['passwortNeu2']);

			if ($passwortNeu != $passwortNeu2) {
				$error_msg = "Die eingegebenen Passwörter stimmten nicht überein.";
			} else if ($passwortNeu == "") {
				$error_msg = "Das Passwort darf nicht leer sein.";
			} else {
				$passwort_hash = password_hash($passwortNeu, PASSWORD_DEFAULT);
				if ($_REQUEST['save'] == 'Userpasswort') {
					$statement = $pdo->prepare("UPDATE users SET passwort = :passwort WHERE id = :userid");
					$result = $statement->execute(array('passwort' => $passwort_hash, 'userid' => $UserToCanchePWID));

					$success_msg = "Passwort erfolgreich gespeichert.";
					header('Location: settings.php?save=Userpasswortchanged');
				}
			}
		}
	}

	$user = check_user();

?>

<div class="container main-container">

	<h1>Einstellungen</h1>

	<?php
		if (isset($success_msg) && !empty($success_msg)):
			?>
			<div class="alert alert-success">
				<a href="#" class="close" data-dismiss="alert" aria-label="close">&times;</a>
				<?php echo $success_msg; ?>
			</div>
		<?php
		endif;
	?>

	<?php
		if (isset($error_msg) && !empty($error_msg)):
			?>
			<div class="alert alert-danger">
				<a href="#" class="close" data-dismiss="alert" aria-label="close">&times;</a>
				<?php echo $error_msg; ?>
			</div>
		<?php
		endif;
	?>

	<div>

		<!-- Nav tabs -->
		<ul class="nav nav-tabs" role="tablist">
			<li role="presentation" class="active"><a href="#data" aria-controls="home" role="tab" data-toggle="tab">Persönliche
					Daten</a></li>
			<li role="presentation"><a href="#email" aria-controls="profile" role="tab" data-toggle="tab">E-Mail</a>
			</li>
			<li role="presentation"><a href="#passwort" aria-controls="messages" role="tab"
			                           data-toggle="tab">Passwort</a></li>
			<?php
				if ($user['Superadmin']) {
					echo '<li role="presentation"><a href="#Userpasswort" aria-controls="messages" role="tab" data-toggle="tab">User Passwort</a></li>';
					echo '<li role="presentation" class=""><a href="#Statistik" aria-controls="messages" role="tab" data-toggle="tab">User Statistiken</a></li>';
				}
			?>
		</ul>

		<!-- Persönliche Daten-->
		<div class="tab-content">
			<div role="tabpanel" class="tab-pane active" id="data">
				<br>
				<form action="?save=personal_data" method="post" class="form-horizontal">
					<div class="form-group">
						<label for="inputVorname" class="col-sm-2 control-label">Vorname</label>
						<div class="col-sm-10">
							<input class="form-control" id="inputVorname" name="vorname" type="text"
							       value="<?php echo htmlentities($user['vorname']); ?>" required>
						</div>
					</div>

					<div class="form-group">
						<label for="inputNachname" class="col-sm-2 control-label">Nachname</label>
						<div class="col-sm-10">
							<input class="form-control" id="inputNachname" name="nachname" type="text"
							       value="<?php echo htmlentities($user['nachname']); ?>" required>
						</div>
					</div>

					<div class="form-group">
						<div class="col-sm-offset-2 col-sm-10">
							<button type="submit" class="btn btn-primary">Speichern</button>
						</div>
					</div>
				</form>
			</div>

			<!-- Änderung der E-Mail-Adresse -->
			<div role="tabpanel" class="tab-pane" id="email">
				<br>
				<p>Zum Änderen deiner E-Mail-Adresse gib bitte dein aktuelles Passwort sowie die neue E-Mail-Adresse
					ein.</p>
				<form action="?save=email" method="post" class="form-horizontal">
					<div class="form-group">
						<label for="inputPasswort3" class="col-sm-2 control-label">Passwort</label>
						<div class="col-sm-10">
							<input class="form-control" id="inputPasswort3" name="passwort" type="password" required>
						</div>
					</div>

					<div class="form-group">
						<label for="inputEmail" class="col-sm-2 control-label">E-Mail</label>
						<div class="col-sm-10">
							<input class="form-control" id="inputEmail" name="email" type="email"
							       value="<?php echo htmlentities($user['email']); ?>" required>
						</div>
					</div>


					<div class="form-group">
						<label for="inputEmail2" class="col-sm-2 control-label">E-Mail (wiederholen)</label>
						<div class="col-sm-10">
							<input class="form-control" id="inputEmail2" name="email2" type="email" required>
						</div>
					</div>

					<div class="form-group">
						<div class="col-sm-offset-2 col-sm-10">
							<button type="submit" class="btn btn-primary">Speichern</button>
						</div>
					</div>
				</form>
			</div>

			<!-- Änderung des Passworts -->
			<div role="tabpanel" class="tab-pane" id="passwort">
				<br>
				<p>Zum Änderen deines Passworts gib bitte dein aktuelles Passwort sowie das neue Passwort ein.</p>
				<form action="?save=passwort" method="post" class="form-horizontal">
					<div class="form-group">
						<label for="inputPasswort" class="col-sm-2 control-label">Altes Passwort</label>
						<div class="col-sm-10">
							<input class="form-control" id="inputPasswort" name="passwortAlt" type="password" required>
						</div>
					</div>

					<div class="form-group">
						<label for="inputPasswortNeu" class="col-sm-2 control-label">Neues Passwort</label>
						<div class="col-sm-10">
							<input class="form-control" id="inputPasswortNeu" name="passwortNeu" type="password"
							       required>
						</div>
					</div>


					<div class="form-group">
						<label for="inputPasswortNeu2" class="col-sm-2 control-label">Neues Passwort
							(wiederholen)</label>
						<div class="col-sm-10">
							<input class="form-control" id="inputPasswortNeu2" name="passwortNeu2" type="password"
							       required>
						</div>
					</div>

					<div class="form-group">
						<div class="col-sm-offset-2 col-sm-10">
							<button type="submit" class="btn btn-primary">Speichern</button>
						</div>
					</div>
				</form>
			</div>
			<?php
				if ($user['Superadmin']) {
					?>
					<!-- Änderung des UserPassworts -->
					<div role="tabpanel" class="tab-pane" id="Userpasswort">
						<br>
						<p>Zum Änderen des Passworts wähle den User und gib das neue Passwort ein.</p>
						<p>
						<span
							style="display: table-cell; padding: 5px 50px; text-align: left;">
										<u style="font-size: 20px;">Rolle</u><br/>
									<label style="margin-bottom: 0px; font-weight: normal;" for="FilterAdminsUserPW">
										<input type="radio" id="FilterAdminsUserPW" name="FilterAdminsPW"
										       class="FilterAdminsPW" value="NOFilterSet"
										       checked>
										Alle
									</label><br>
									<label style="margin-bottom: 0px; font-weight: normal;" for="FilterAdminsPW">
										<input type="radio" id="FilterAdminsPW" name="FilterAdminsPW"
										       class="FilterAdminsPW"
										       value="LEHRPERSON">
										Lehrperson
									</label><br>
									<label style="margin-bottom: 0px; font-weight: normal;" for="FilterUser">
										<input type="radio" id="FilterUser" name="FilterAdminsPW" class="FilterAdminsPW"
										       value="Studierende">
										Studierende
									</label>
									</span>


							<span
								style="display: table-cell; padding: 5px 50px; text-align: left;">
										<u style="font-size: 20px;">Organisation</u><br/>
									<label style="margin-bottom: 0px; font-weight: normal;" for="FilterOrganisationPW">
										<input type="radio" id="FilterOrganisationPW" name="FilterOrganisationPW"
										       class="FilterOrganisationPW" value="NOFilterSet"
										       checked>
										Alle
									</label><br>
									<label style="margin-bottom: 0px; font-weight: normal;"
									       for="FilterOrganisationPWTUK">
										<input type="radio" id="FilterOrganisationPWTUK" name="FilterOrganisationPW"
										       class="FilterOrganisationPW" value="uni-kl">
										Teschnische Universität Kaiserslautern
									</label><br>
									<label style="margin-bottom: 0px; font-weight: normal;"
									       for="FilterOrganisationPWTuebingen">
										<input type="radio" id="FilterOrganisationPWTuebingen"
										       name="FilterOrganisationPW"
										       class="FilterOrganisationPW" value="uni-tuebingen">
										Universität Tübingen
									</label>
									</span>


							<span
								style="display: table-cell; padding: 5px 50px; text-align: left;">
										<u style="font-size: 20px;">Suche</u><br/>
										<span style="font-size: 0.8em;">(Groß-/Kleinschreibung beachten)</span>
										<br/>
									<input style="width: 250px;" id="SearchFilterAdminsUserPW" type="search"
									       placeholder=""/>

									</span>
						</p>
						<hr>
						<form action="?save=Userpasswort" method="post" class="form-horizontal">
							<div class="form-group">
								<select multiple size="11" name="UserToCanchePWID" id="UserToCanchePWID"
								        style="font-size: x-large; min-width: 80%;">
									<?php
										$stmt1 = $pdo->prepare("SELECT * FROM users ORDER BY nachname");
										$stmt1->execute();
										$results = $stmt1->fetchAll(PDO::FETCH_ASSOC);
										// Einträge ausgeben
										echo '<option value="" selected hidden> - Bitte wählen - </option>';
										$i = 0;
										$LehrpersonenGesamt = 0;
										$LehrpersonenTUK = 0;
										$LehrpersonenTuebingen = 0;

										$StudierendenGesamt = 0;
										$StudierendenTUK = 0;
										$StudierendenTuebingen = 0;
										foreach ($results as $result) {
											$i++;
											if ($result['userrole'] == 'Admin') {
												$rolen = "LEHRPERSON";

												$LehrpersonenGesamt++;
												if ($result["Organisation"] == 'uni-kl') {
													$LehrpersonenTUK++;
												} else {
													$LehrpersonenTuebingen++;
												}

											} else {
												$rolen = "Studierende";

												$StudierendenGesamt++;
												if ($result["Organisation"] == 'uni-kl') {
													$StudierendenTUK++;
												} else {
													$StudierendenTuebingen++;
												}
											}


											echo '<option value="' . $result["id"] . '" class="' . $rolen . ' ' . $result["Organisation"] . ' NOFilterSet" >' . $result["nachname"] . ', ' . $result["vorname"] . ': - ' . $rolen . ' - { ' . $result["Organisation"] . ' } { ' . $result["nickname"] . ' }</option>';
										}
									?>
								</select>
							</div>

							<div class="form-group">
								<label for="inputPasswort2Neu" class="col-sm-2 control-label">Neues Passwort</label>
								<div class="col-sm-10">
									<input class="form-control" id="inputPasswort2Neu" name="passwortNeu"
									       type="password"
									       required>
								</div>
							</div>


							<div class="form-group">
								<label for="inputPasswort2Neu2" class="col-sm-2 control-label">Neues Passwort
									(wiederholen)</label>
								<div class="col-sm-10">
									<input class="form-control" id="inputPasswort2Neu2" name="passwortNeu2"
									       type="password"
									       required>
								</div>
							</div>

							<div class="form-group">
								<div class="col-sm-offset-2 col-sm-10">
									<button type="submit" class="btn btn-primary">Speichern</button>
									<button type="button" id="PWChangeCancle" class="btn btn-primary">Abbrechen</button>
								</div>
							</div>
						</form>

					</div>

					<script>


                        $('#PWChangeCancle').on('click', function () {
                            $('#inputPasswort2Neu2').val('')
                            $('#inputPasswort2Neu').val('')
                                $("#FilterAdminsUserPW").prop('checked', true);
                                $("#FilterOrganisationPW").prop('checked', true);
                                $('#UserToCanchePWID').val('')


                        })


                        // $('#SearchFilterAdminsUserButton').on('click', function () {
                        $('#SearchFilterAdminsUserPW').on('input', function () {
                            $('#UserToCanchePWID .NOFilterSet').hide()
                            // $(".NOFilterSet:contains("+$('#SearchFilterAdminsUserPW').val()+")").show()

                            var FilterOrganisationchecked = '.' + $('.FilterOrganisationPW:checked').val()
                            var FilterAdminschecked = '.' + $('.FilterAdminsPW:checked').val()
                            var containsSearch = $('#SearchFilterAdminsUserPW').val()

							<?PHP if ($_SESSION['superadmin']) { ?>
                            $(FilterAdminschecked + ":contains(" + containsSearch + ")" + FilterOrganisationchecked + ":contains(" + containsSearch + ")").show()
							<?PHP }else{ ?>
                            $(FilterAdminschecked + ":contains(" + containsSearch + ")").show()
							<?PHP } ?>

                        })

                        $('.FilterOrganisationPW, .FilterAdminsPW').on('change', function () {
                            // console.log('FilterOrganisationPW: ' + $(this).val() + " <-3")
                            $('#UserToCanchePWID .NOFilterSet').hide()


                            var FilterOrganisationchecked = '.' + $('.FilterOrganisationPW:checked').val()
                            var FilterAdminschecked = '.' + $('.FilterAdminsPW:checked').val()
                            var containsSearch = $('#SearchFilterAdminsUserPW').val()

							<?PHP if ($_SESSION['superadmin']) { ?>
                            $(FilterAdminschecked + ":contains(" + containsSearch + ")" + FilterOrganisationchecked + ":contains(" + containsSearch + ")").show()
							<?PHP }else{ ?>
                            $(FilterAdminschecked + ":contains(" + containsSearch + ")").show()
							<?PHP } ?>


                        })

					</script>
					<!-- Statistik -->
					<div role="tabpanel" class="tab-pane" id="Statistik">
						<span style="display: table-cell; padding: 5px 50px; text-align: left;">
						<table class="TableCSS StandartTextTable" Border>
							<tr style="text-decoration: underline">
								<th colspan="2">Gesamte Nutzer</th>
								<td><?php echo $i; ?></td>
							</tr>
							<tr>
								<td colspan="3"></td>
							</tr>
							<tr>
								<th rowspan="2">Organisation</th>
								<th>TUK</th>
								<td><?php echo $StudierendenTUK + $LehrpersonenTUK; ?></td>
							</tr>
							<tr>
								<th>Tuebingen</th>
								<td><?php echo $StudierendenTuebingen + $LehrpersonenTuebingen; ?></td>
							</tr>
							<tr>
								<td colspan="3"></td>
							</tr>
							<tr>
								<th rowspan="3">Lehrpersonen</th>
								<th>Gesamt</th>
								<td><?php echo $LehrpersonenGesamt; ?></td>
							</tr>
							<tr>
								<th>TUK</th>
								<td><?php echo $LehrpersonenTUK; ?></td>
							</tr>
							<tr>
								<th>Tuebingen</th>
								<td><?php echo $LehrpersonenTuebingen; ?></td>
							</tr>
							<tr>
								<td colspan="3"></td>
							</tr>
							<tr>
								<th rowspan="3">Studierende</th>
								<th>Gesamt</th>
								<td><?php echo $StudierendenGesamt; ?></td>
							</tr>
							<tr>
								<th>TUK</th>
								<td><?php echo $StudierendenTUK; ?></td>
							</tr>
							<tr>
								<th>Tuebingen</th>
								<td><?php echo $StudierendenTuebingen; ?></td>
							</tr>

						</table>
						</span>

						<span style="display: table-cell; padding: 5px 50px; text-align: left;">

						<table class="TableCSS StandartTextTable" Border>
							 <tr style="text-decoration: underline">
								<th colspan="2">Gesamte Kurse</th>
								<td><?php $nRows = $pdo->query('select count(*) from Kurse')->fetchColumn();
										echo $nRows; ?></td>
							</tr>

							<tr>
								<th rowspan="2">Organisation</th>
								<th>TUK</th>
								<td><?php $nRows = $pdo->query('select count(*) from Kurse WHERE Organisation="uni-kl"')->fetchColumn();
										echo $nRows; ?></td>
							</tr>
							<tr>
								<th>Tuebingen</th>
								<td><?php $nRows = $pdo->query('select count(*) from Kurse WHERE Organisation = "uni-tuebingen"')->fetchColumn();
										echo $nRows; ?></td>
							</tr>
							<tr>
								<td colspan="3"></td>
							</tr>
							<tr style="text-decoration: underline">
								<th colspan="2">Gesamte Videos</th>
								<td><?php $nRows = $pdo->query('select count(*) from VideoList')->fetchColumn();
										echo $nRows; ?></td>
							</tr>

							<tr>
								<th rowspan="2">Organisation</th>
								<th>TUK</th>
								<td><?php $nRows = $pdo->query('select count(*) from VideoList v INNER JOIN Kurse k ON v.KursID = k.KursID AND k.Organisation="uni-kl"')->fetchColumn();
										echo $nRows; ?></td>
							</tr>
							<tr>
								<th>Tuebingen</th>
								<td><?php $nRows = $pdo->query('select count(*) from VideoList v INNER JOIN Kurse k ON v.KursID = k.KursID AND k.Organisation = "uni-tuebingen"')->fetchColumn();
										echo $nRows; ?></td>
							</tr>
							<tr>
								<td colspan="3"></td>
							<tr style="text-decoration: underline">
								<th colspan="2">Gesamte Kommentaren</th>
								<td><?php $nRows = $pdo->query('select count(*) from test')->fetchColumn();
										echo $nRows; ?></td>
							</tr>
							<tr>
								<th rowspan="3">Organisation</th>
								<th>TUK</th>
								<td><?php $nRows = $pdo->query('select count(*) from test t INNER JOIN Kurse k ON t.KursID = k.KursID AND k.Organisation="uni-kl"')->fetchColumn();
										echo $nRows; ?></td>
							</tr>
							<tr>
								<th>Tuebingen</th>
								<td><?php $nRows = $pdo->query('select count(*) from test t INNER JOIN Kurse k ON t.KursID = k.KursID AND k.Organisation = "uni-tuebingen"')->fetchColumn();
										echo $nRows; ?></td>
							</tr>
							<tr>
								<th>Rest</th>
								<td><?php $nRows = $pdo->query('select count(*) from test t LEFT JOIN Kurse k ON t.KursID= k.KursID WHERE k.KursID IS NULL')->fetchColumn();
										echo $nRows; ?></td>
							</tr>

						</table>
</span>
					</div>


					<?php
				}
			?>
		</div>

	</div>


</div>
<?php
	include("templates/footer.inc.php")
?>
