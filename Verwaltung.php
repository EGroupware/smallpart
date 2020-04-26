<?php
use EGroupware\Api;
use EGroupware\SmallParT\Bo;

require_once("inc/config.inc.php");
include("templates/header.inc.php");

?>

	<div class="container main-container">

		<?php
			if (Bo::isAdmin()) {
				$showFormular = true;
			} else {
				$showFormular = false;
			} //Variable ob das Registrierungsformular anezeigt werden soll

			if (isset($_GET['Kurs'])) {
				$error = false;
				$KursName = trim($_POST['KursName']);
				$KursPasswort = $_POST['KursPasswort'];

				if (empty($KursName)) {
					echo 'Bitte alle Felder ausfüllen<br>';
					$error = true;
				}

				if (strlen($KursPasswort) == 0) {
					echo 'Bitte ein Passwort angeben<br>';
					$error = true;
				}


				//Überprüfe, dass der Kurs noch nicht registriert wurde
				if (!$error) {
					$statement = $pdo->prepare("SELECT * FROM Kurse WHERE KursName = :KursName AND KursOwner = :KursOwner");
					$result = $statement->execute(array('KursName' => $KursName, 'KursOwner' => $GLOBALS['egw_info']['user']['account_id']));
					$Kurs = $statement->fetch();

					if ($Kurs !== false) {
						//echo 'Dieser Kurs ist bereits vorhanden<br>';
						$Nachricht = '<br><br><b style="background-color:#1f1f1f; color: #ef120f; font-size: large;">Dieser Kurs ist bereits vorhanden.</b>';


						$error = true;
					}//else{$Nachricht='';}
				}

				//Keine Fehler, wir können den Kurs registrieren
				if (!$error) {

					$statement = $pdo->prepare("INSERT INTO Kurse (KursName, KursPasswort, KursOwner, Organisation) VALUES (:KursName, :KursPasswort, :KursOwner, :Organisation)");
					$result = $statement->execute(array('KursName' => $KursName, 'KursPasswort' => $KursPasswort, 'KursOwner' => $GLOBALS['egw_info']['user']['account_id'], 'Organisation' => Bo::getOrganisation()));


					$statementKursName = $pdo->prepare("SELECT * FROM Kurse WHERE KursName = :KursName AND KursOwner= :KursOwner");
					$resultKursName = $statementKursName->execute(array('KursName' => $KursName, 'KursOwner' => $GLOBALS['egw_info']['user']['account_id']));
					$NeuKursID = $statementKursName->fetch();


					echo $VideodirectoryPath = 'Resources/Videos/Video/' . $NeuKursID['KursID'];
					$VttdirectoryPath = 'Resources/Videos/Video_vtt/' . $NeuKursID['KursID'];
					if (!file_exists($VideodirectoryPath) and !file_exists($VttdirectoryPath)) {
						mkdir($VideodirectoryPath);
						mkdir($VttdirectoryPath);
					}

					if ($result) {
						//add Owner to Kurs
						$statement = $pdo->prepare("INSERT INTO KurseUndTeilnehmer (KursID, UserID) VALUES (:KursID, :UserID)");
						$result = $statement->execute(array('KursID' => $NeuKursID['KursID'], 'UserID' => $GLOBALS['egw_info']['user']['account_id']));

						//echo 'Kurs wurde erfolgreich registriert.';
						$Nachricht = '<br><br><b style="background-color:#1f1f1f; color: #3ADF00; font-size: large;">Der Kurs <u>' . $KursName . '</u> mit <u>' . $KursPasswort . '</u> als Passwort wurde erfolgreich angelgt.</b>';
						$KursPasswort = '';
						$KursName = '';
					} else {
						//echo 'Beim Abspeichern ist leider ein Fehler aufgetreten<br>';
						$Nachricht = '<br><br><b style="background-color:#1f1f1f; color: #ef120f; font-size: large;">Beim Abspeichern ist leider ein Fehler aufgetreten</b>';
					}
				}
			}


			if (isset($_GET['ausKurslisteloeschen'])) {

				$SelectedKursID = $_POST['JaKilTeilnehmerVonKursName'];
				$SelectedUserID = $_POST['JaKilTeilnehmerVonKursUser'];

				// echo "tada" . $SelectedKursID . " - " . $SelectedKursID;

				$statement = $pdo->prepare("DELETE FROM KurseUndTeilnehmer WHERE KursID = :KursID AND UserID=:UserID");
				$result = $statement->execute(array('KursID' => $SelectedKursID, 'UserID' => $SelectedUserID));
				$Kurs = $statement->fetch();
			}

			if ($showFormular) {
		?>


		<script>
            // async script loading requires to wait for script to be loaded
            egw_LAB.wait(function() {
                window.FunkLoadKursForVerwaltung = function(AjaxGet) {


                    var VerwaltungData = AjaxGet.KursListOwner;
                    var kurse = '';

                    var selectionKursListegesamt = '<option >[Status] - [Kursname] - [ Kurspasswort ]</option>';

                    var KursListe = '<option value="" hidden selected>- Bitte auswählen -</option>';

                    for (i in VerwaltungData) {
                        if (VerwaltungData[i].KursClosed == '1') {
                            KursListe += '<option value="' + VerwaltungData[i].KursID + '"style="color:#333;" disabled>- DEAKTIVIERT -  ' + VerwaltungData[i].KursName + ' [ Kurs-Id: ' + VerwaltungData[i].KursID + ' ]</option>';
                            selectionKursListegesamt += '<option value="' + VerwaltungData[i].KursID + '" style="color:#333;" disabled> - DEAKTIVIERT -  [Kursname: ' + VerwaltungData[i].KursName + '] - [ Kurspasswort: ' + VerwaltungData[i].KursPasswort + ' ] </option>';

                        } else {
                            KursListe += '<option value="' + VerwaltungData[i].KursID + '">' + VerwaltungData[i].KursName + ' [ Kurs-Id: ' + VerwaltungData[i].KursID + ' ]</option>';
                            selectionKursListegesamt += '<option value="' + VerwaltungData[i].KursID + '">[Kursname: ' + VerwaltungData[i].KursName + ' ] - [ Kurspasswort: ' + VerwaltungData[i].KursPasswort + ' ] </option>';

                        }
                    }

                    // selectionKursListegesamt += kurse;
                    // KursListe += kurse;

                    jQuery('#selectionKursListegesamt').html(selectionKursListegesamt)
                    jQuery('#SelectionVideo').html(KursListe)
                    jQuery('#SelectionVideoTask').html(KursListe)
                    jQuery('#AdministrationKursList').html(KursListe)
                }
            });
		</script>

		<h1>Kurs- und Teilnehmerverwaltung</h1>

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
				<li role="presentation" class="" id="KurseVerwaltenLi"><a href="#KurseVerwalten"
				                                                          aria-controls="Angelegt"
				                                                          role="tab" data-toggle="tab">Kurse
						verwalten</a></li>
				<li role="presentation" class="" id="KursVideoUploadLi"><a href="#KursVideoUpload"
				                                                           aria-controls="KursVideoUpload"
				                                                           role="tab" data-toggle="tab">Videos
						verwalten</a></li>
				<li role="presentation" class="" id="KursTastForVideoInputLi"><a href="#KursTastForVideoInput"
				                                                                 aria-controls="KursTastForVideoInput"
				                                                                 role="tab" data-toggle="tab">Aufgabenstellungen </a>
				</li>
				<li role="presentation" class="" id="KurslisteLi"><a href="#Kursliste" aria-controls="Kursliste"
				                                                     role="tab"
				                                                     data-toggle="tab">Kursteilnehmer verwalten</a></li>
				<li role="presentation" class="" id="UserrolleLi"><a href="#Userrolle" aria-controls="messages"
				                                                     role="tab"
				                                                     data-toggle="tab">Userrollen verwalten</a>
				</li>
			</ul>


			<div class="tab-content">
				<!-- Kurse Verwalten-->
				<div role="tabpanel" class="tab-pane " id="KurseVerwalten">
					<br>
					<b style='font-size: 25px; height: 30px;'>Kurs anlegen: </b></br></br>
					<div id="KurseVerwaltenKursAnlegen">
						<form action="?Kurs=Angelegt" method="post" class="form-horizontal">

							<div class="form-group">
								<label for="inputVorname" class="col-sm-2 control-label">Kursname:</label>
								<div class="col-sm-10">
									<input type="text" id="inputVorname" size="40" maxlength="250" name="KursName"
									       class="form-control" value="<?php echo $KursName; ?>" required>
								</div>
							</div>

							<div class="form-group">
								<label for="inputNachname" class="col-sm-2 control-label">Kurspasswort:</label>
								<div class="col-sm-10">
									<input type="text" id="inputPasswort" size="40" maxlength="250" name="KursPasswort"
									       class="form-control" value="<?php echo $KursPasswort; ?>" required>
								</div>
							</div>

							<div class="form-group">
								<div class="col-sm-offset-2 col-sm-10">

									<button type="submit" class="btn btn-primary" id="KursanlegenSubmit">Kurs anlegen
									</button>
									<?php echo $Nachricht; ?>

								</div>
							</div>

						</form>
					</div>
					<hr>
					<br>
					<b style='font-size: 25px; height: 30px;'>Kurs schließen: </b></br>
					<div id="CloseKursTop" class="form-group">

						<div class="col-sm-offset-2 col-sm-10">
							<select name="selectionKursListegesamt" id="selectionKursListegesamt"
							        style="font-size: x-large; min-width: 300px;">
							</select>
						</div>
						<br>
						<br>
						<div class="col-sm-offset-2 col-sm-10">
							<button type="button" class="btn btn-primary" id="CloseKurs" disabled> Kurs schließen
							</button>
						</div>

					</div>
				</div>

				<script>
                    // async script loading requires to wait for script to be loaded
                    egw_LAB.wait(function() {
                        window.FunkKurseClosed = function(AjaxGet) {
                            jQuery('#CloseKursTop').prop('disabled', false)
                            jQuery('#selectionKursListegesamt').attr("disabled", false)
                            jQuery('#selectionKursListegesamt').find('option[value="' + AjaxGet.KursClosed + '"]').prop("disabled", true);
                            FunkLoadKursListForOwner()
                        }

                        jQuery(document).ready(function () {
                            jQuery('#selectionKursListegesamt').on('change', function () {
                                jQuery('#DeleteCommentConfirm').remove()
                                jQuery('#CloseKursTop').after('<div id="DeleteCommentConfirm" style="text-align: center;">' +
                                    '</div>')
                                jQuery('#CloseKurs').show()
                                if (jQuery(this).val()) {
                                    jQuery('#CloseKurs').prop('disabled', false);
                                } else {
                                    jQuery('#CloseKurs').prop('disabled', true);
                                }


                                jQuery('#CloseKurs').on('click', function () {

                                    jQuery('#CloseKurs').hide()
                                    jQuery('#DeleteCommentConfirm').html('<p><h2>Endgültig deaktivieren?</h2></p>' +
                                        '<input type="button" id="DeleteCommentConfirmYes" class="button_std" style="margin: 10px; background-color:red; font-weight: bold;" value="DEAKTIVIEREN !">' +
                                        '<input type="button" id="DeleteCommentConfirmNo" class="button_std"  style="margin: 10px;  background-color:green;font-weight: bold;" value="Abrechen">'
                                    )

                                    jQuery('#DeleteCommentConfirmNo').on('click', function () {
                                        jQuery('#CloseKurs').show()
                                        jQuery('#DeleteCommentConfirm').remove()
                                        jQuery('#selectionKursListegesamt').find('option[value="selected"]').attr("selected", true);
                                    });

                                    jQuery('#DeleteCommentConfirmYes').on('click', function () {
                                        jQuery('#CloseKurs').show()
                                        jQuery('#CloseKurs').prop('disabled', true)

                                        jQuery('#selectionKursListegesamt').attr("disabled", true)

                                        jQuery('#DeleteCommentConfirm').remove()

                                        AjaxSend('database/DbInteraktion.php', {
                                            DbRequest: 'Insert',
                                            DbRequestVariation: 'CloseKurs',
                                            AjaxDataToSend: {KursID: jQuery("#selectionKursListegesamt").val()}
                                        }, 'FunkKurseClosed')


                                    });

                                })
                            })
                        })
                    });
				</script>

				<!-- Video hochladen -->
				<div role="tabpanel" class="tab-pane " id="KursVideoUpload">

					<br>

					<table style="margin:0 auto; height: 100px;" border>
						<tr>
							<td colspan="5" style="width: 100%" ALIGN="CENTER">
								<b style="font-size: 25px; height: 30px;" ALIGN="RIGHT">Kurs: </b>
								<select name="SelectionVideo" id="SelectionVideo"
								        style="font-size: x-large; min-width: 300px;">
									<
								</select>
							</td>

						</tr>
						<tr>
							<td colspan="2" ALIGN="CENTER" style="padding: 10px">
								<b style="font-size: 20px;" ALIGN="RIGHT">
									Video hochladen:
								</b>
							</td>
							<td rowspan="3" STYLE="width: 25px;"></td>
							<td colspan="2" ALIGN="CENTER" style="padding: 10px">
								<b style="font-size: 20px;" ALIGN="RIGHT">
									Video löschen:
								</b>
							</td>
						</tr>
						<tr>
							<td style="font-size: 25px; height: 30px;" ALIGN="RIGHT"><b>Datei: </b></td>
							<td>
								<input type="file" id="SelectionVideoToUpload" name="uploaddatei" size="600"
								       maxlength="255" disabled>
							</td>

							<td class="VideoListeForDelete" style=" font-size: 25px; height: 30px;
							" ALIGN="RIGHT"><b>Video: </b></td>
							<td class="VideoListeForDelete" disabled="true">
								<select style="font-size: x-large; min-width: 255px;" name="SelectKursVideoForDelete"
								        id="SelectKursVideoForDelete"></select>
							</td>
						</tr>
						<!--						<tr hidden>-->
						<!--							<td style="font-size: 25px; height: 30px;" ALIGN="RIGHT"><b>Name: </b></td>-->
						<!--							<td>-->
						<!--								<input type="text" id="SelectionVideoToUploadName" name="NewNameOfVideo" size="70"-->
						<!--								       maxlength="255" disabled>-->
						<!--							</td>-->
						<!--						</tr>-->
						<tr>
							<td><input type="hidden" name="KursID" id="SelectionVideoKursID" value=""></td>
							<td>
								<input type="button" name="submit" id="SelectionVideoUploadNow" class="btn btn-primary"
								       value="Datei hochladen" disabled>
								<input type="button" name="submit" id="SelectionVideoUploadNowCancel"
								       class="btn btn-primary" value="Abbrechen" disabled>
							</td>

							<td></td>
							<td>
								<input type="button" name="submit" id="SelectionVideoDeleteNow" class="btn btn-primary"
								       value="Video löschen" disabled>
								<input type="button" name="submit" id="SelectionVideoDeleteNowCancel"
								       class="btn btn-primary" value="Abbrechen" disabled>
							</td>
						</tr>

						<tr>
							<td colspan="5" id="UploadErgbnis">
							</td>

						</tr>
					</table>


					<br>
					<br>
					<br>

					<?PHP include("Scripts/MANUAL/VideoConverWinFFManual.php"); ?>


					<script>
                        // async script loading requires to wait for script to be loaded
                        egw_LAB.wait(function() {
                            window.FunkVideoListeForDelete = function(AjaxGet) {
                                var VideoList = AjaxGet.VideoList;
                                var VeideoListSelectOption = '<option value="" hidden selected>Bitte Video wählen</option>';
                                if (VideoList.length > 0) {
                                    for (i in VideoList) {
                                        VeideoListSelectOption += '<option value="' + VideoList[i].VideoListID + '">' + VideoList[i].VideoName + '[ Video-id: ' + VideoList[i].VideoListID + '] </option>';
                                    }
                                    jQuery("#SelectKursVideoForDelete").empty().html(VeideoListSelectOption).prop('disabled', false);

                                } else {
                                    jQuery("#SelectKursVideoForDelete").empty().html('<option value=""> >> Es wurden keine Videos hinterlegt <<</option>').prop('disabled', true);
                                }
                                jQuery('#SelectionVideoToUpload').prop('disabled', false);

                            }

                            window.FunkVideoForDeleteReturn = function(AjaxGet) {
                                jQuery('#SelectionVideo').val('').trigger('change')
                                jQuery('#UploadErgbnis').empty()
                                jQuery('#SelectionVideoUploadNow, #SelectionVideoUploadNowCancel, #SelectionVideoDeleteNow, #SelectionVideoDeleteNowCancel').prop('disabled', true);
                                jQuery("#UploadErgbnis").append(AjaxGet.DeleteStatus);
                            }


                            jQuery(document).ready(function () {

                                jQuery('.ShowHowToConvert').show()
                                jQuery('.HowToConvertVideoWinFF').hide()

                                jQuery('#SelectionVideo').on('change', function () {

                                    jQuery('#SelectionVideoKursID').prop('value', jQuery('#SelectionVideo').val());
                                    jQuery('#SelectionVideoToUpload').val('');
                                    jQuery('#SelectionVideoToUploadName').val('');
                                    jQuery('#UploadErgbnis').empty()


                                    if (jQuery('#SelectionVideo').val() != '') {
                                        jQuery('#SelectionVideoToUpload').prop('disabled', true);

                                        jQuery('#SelectKursVideoForDelete').prop('disabled', false).html('<option value="">Videos werden geladen...</option>').prop('disabled', true);

                                        AjaxSend('database/DbInteraktion.php', {
                                            DbRequest: 'Select',
                                            DbRequestVariation: 'FunkAddVideoListFromDB',
                                            AjaxDataToSend: {KursID: jQuery('#SelectionVideo').val()}
                                        }, 'FunkVideoListeForDelete')

                                    } else {
                                        jQuery('#SelectionVideoToUpload').prop('disabled', true);
                                        jQuery('#SelectKursVideoForDelete').prop('disabled', true).empty();

                                    }


                                });

                                jQuery('#SelectionVideoToUpload').on('change', function () {
                                    if (jQuery(this).val() != '') {

                                        jQuery('#SelectionVideoUploadNow').prop('disabled', false);
                                        jQuery('#SelectionVideoUploadNowCancel').prop('disabled', false);

                                        jQuery('#SelectKursVideoForDelete').prop('disabled', true).empty();
                                    } else {

                                        jQuery('#SelectionVideoUploadNow').prop('disabled', true);
                                        jQuery('#SelectionVideoUploadNowCancel').prop('disabled', true);
                                    }
                                    jQuery("#UploadErgbnis").empty()

                                });

                                jQuery('#SelectKursVideoForDelete').on('change', function () {

                                    if (jQuery(this).val() != '') {

                                        jQuery('#SelectionVideoDeleteNow').prop('disabled', false);
                                        jQuery('#SelectionVideoDeleteNowCancel').prop('disabled', false);
                                        jQuery('#SelectionVideoToUpload').prop('disabled', true);

                                    } else {
                                        jQuery("#UploadErgbnis").empty()
                                        jQuery('#SelectionVideoDeleteNow').prop('disabled', true);
                                        jQuery('#SelectionVideoDeleteNowCancel').prop('disabled', true);
                                    }
                                })


                                jQuery('#SelectionVideoUploadNowCancel, #SelectionVideoDeleteNowCancel').on('click', function () {

                                    // jQuery('#SelectionVideoToUpload, #SelectKursVideoForDelete').val('').trigger('change')
                                    jQuery('#SelectionVideo').val('').trigger('change')


                                    jQuery('#SelectionVideoUploadNow, #SelectionVideoUploadNowCancel, #SelectionVideoDeleteNow, #SelectionVideoDeleteNowCancel').prop('disabled', true);

                                });


                                jQuery('#SelectionVideoDeleteNow').on('click', function () {
                                    // jQuery('#SelectionVideo').on('change', function () {

                                    jQuery('#SelectionVideoUploadNow, #SelectionVideoUploadNowCancel, #SelectionVideoDeleteNow, #SelectionVideoDeleteNowCancel').prop('disabled', true);
                                    jQuery('#UploadErgbnis').append('<div id="DeleteVideoConfirm" style="text-align: center;"></div>')

                                    jQuery('#DeleteVideoConfirm').html('<p><h2>Endgültig Löschen?</h2></p>' +
                                        '<input type="button" id="DeleteVideoConfirmYes" class="button_std_darkblue_logout" style="margin: 10px; background-color: #921010;" value="LÖSCHEN !">' +
                                        '<input type="button" id="DeleteVideoConfirmNo" class="button_std_darkblue_logout"  style="margin: 10px;  background-color:#3ADF00;" value="Abrechen">'
                                    )

                                    jQuery('#DeleteVideoConfirmNo').on('click', function () {
                                        jQuery('#SelectionVideo').val('').trigger('change')
                                        jQuery('#UploadErgbnis').empty()
                                    })
                                    jQuery('#DeleteVideoConfirmYes').on('click', function () {


                                        AjaxSend('database/DbInteraktion.php', {
                                            DbRequest: 'Insert',
                                            DbRequestVariation: 'DeleteVideo',
                                            AjaxDataToSend: {VideoListID: jQuery('#SelectKursVideoForDelete').val()}
                                        }, 'FunkVideoForDeleteReturn')
                                    })

                                });

                                jQuery('#SelectionVideoUploadNow').on('click', function () {
                                    jQuery('#SelectionVideoUploadNow').prop('disabled', true);
                                    jQuery('#SelectionVideoUploadNowCancel').prop('disabled', true);
                                    jQuery("#UploadErgbnis").append("<span id='DoingUpload'> Wird Hochgeladen</span>");


                                    var VideoFileForUpload = new FormData(); // das ist unser Daten-Objekt ...
                                    VideoFileForUpload.append('uploaddatei', jQuery('#SelectionVideoToUpload').prop('files')[0]); // ... an die wir unsere
                                    VideoFileForUpload.append('KursID', jQuery('#SelectionVideo').val()); // ... an die wir unsere
                                    VideoFileForUpload.append('DbRequest', 'FunkUploadVideo'); // ... an die wir unsere

                                    // Datei anhängen
                                    jQuery.ajax({
                                        url: 'database/DbInteraktion.php', // Wohin soll die Datei geschickt werden?
                                        data: VideoFileForUpload,          // Das ist unser Datenobjekt.
                                        type: 'POST',         // HTTP-Methode, hier: POST
                                        processData: false,
                                        contentType: false,
                                        // und wenn alles erfolgreich verlaufen ist, schreibe eine Meldung
                                        // in das Response-Div
                                        success: function (response) {
                                            var AjaxGet = JSON.parse(response);

                                            jQuery('#SelectionVideo').val('').trigger('change')

                                            jQuery('#SelectionVideoUploadNow, #SelectionVideoUploadNowCancel, #SelectionVideoDeleteNow, #SelectionVideoDeleteNowCancel').prop('disabled', true);

                                            jQuery("#UploadErgbnis").append(AjaxGet.UploadStus);

                                        }
                                    })


                                });

                                jQuery('#SelectionVideoToUpload').on('change', function (e) {
                                    if (jQuery('#SelectionVideoToUpload').val() != '') {
                                        jQuery('#SelectionVideoToUploadName').prop('disabled', false);
                                    } else {
                                        jQuery('#SelectionVideoToUploadName').prop('disabled', true);
                                    }
                                    var fileName = e.target.files[0].name;

                                    jQuery('#SelectionVideoToUploadName').html(fileName);
                                    // alert('The file "' + fileName + '" has been selected.');

                                    // alert('1 The file "' + jQuery('#SelectionVideoToUpload')[0].files[0].name + '" has been selected.');
                                    // alert('2 The file "' + jQuery('#SelectionVideoToUpload')[0].files[0].name + '" has been selected.');
                                });


                            });
                        });
					</script>
				</div>

				<!-- Aufgabe Erstellen -->
				<div role="tabpanel" class="tab-pane " id="KursTastForVideoInput">

					<br>
					<table style="margin:0 auto; height: 100px;" border>
						<tr>
							<td style="font-size: 25px; height: 30px;" ALIGN="RIGHT"><b id="testtest">Kurs: </b></td>
							<td>
								<select name="SelectionVideoTask" id="SelectionVideoTask"
								        style="font-size: x-large; min-width: 300px;">

								</select>
							</td>
						</tr>
						<tr>
							<td class="VideoListeForTask"
							    style="font-size: 25px; height: 2px; min-width: 30px; display: none" ALIGN="RIGHT">
								<b>Video: </b>
							</td>
							<td class="VideoListeForTask" style="display: none">
								<select style="font-size: x-large; min-width: 500px;" name="SelectKursVideoForTask"
								        id="SelectKursVideoForTask"></select>
							</td>
						</tr>
						<tr class="VideoForTaskAndInputArea" style="display: none">
							<td id="loadVideoForTaskArea" class="VideoForTaskAndInputAreareloadbychange">
							</td>
							<td id="QuastionInputArea2"><textarea id='QuastionInputArea'
							                                      class="VideoForTaskAndInputAreareloadbychange"
							                                      placeholder="Aufgabe hier eingeben.."
							                                      style="height: 90%; width: 100%; bottom: 0; margin-top: 10px;"></textarea>
							</td>
						</tr>
						<tr class="VideoForTaskAndInputArea" style="display: none">
							<td id="InputSavingStatus" style=" padding-left: 40px; font-size: large;"></td>
							<td id="InputSavingCancelButton"></td>
						</tr>


						<script>
                            // async script loading requires to wait for script to be loaded
                            egw_LAB.wait(function() {
                                window.FunkVideoListeForTask = function(AjaxGet) {
                                    var VideoList = AjaxGet.VideoList;
                                    var VeideoListSelectOption = '<option value="" hidden selected>Bitte Video wählen</option>';
                                    if (VideoList.length > 0) {
                                        for (i in VideoList) {
                                            VeideoListSelectOption += '<option value="' + i + '">' + VideoList[i].VideoName + ' </option>';
                                        }
                                        jQuery("#SelectKursVideoForTask").empty().html(VeideoListSelectOption).prop('disabled', false);
                                    } else {
                                        jQuery("#SelectKursVideoForTask").empty().html('<option value=""> >> Es wurden keine Videos hinterlegt <<</option>').prop('disabled', true);
                                    }
                                    // jQuery('#SelectKursVideoForTask').html(VeideoListSelectOption);


                                    jQuery('#SelectKursVideoForTask').on('change', function () {
                                        jQuery('.VideoForTaskAndInputArea').hide();

                                        if (jQuery(this).val() != '') {
                                            jQuery('.VideoForTaskAndInputArea').show();
                                            jQuery('.VideoForTaskAndInputAreareloadbychange').empty();
                                            var SelectedVideo = VideoList[jQuery('#SelectKursVideoForTask').val()];


                                            // var res = jQuery(this).val().split('/');

                                            AjaxSend('database/DbInteraktion.php', {
                                                DbRequest: 'Select',
                                                DbRequestVariation: 'FunkLoadVideo',
                                                AjaxDataToSend: {
                                                    VideoElementId: SelectedVideo.VideoElementId,
                                                    KursID: SelectedVideo.KursID,
                                                    VideoSrc: SelectedVideo.VideoSrc,
                                                    VideoExtention: SelectedVideo.VideoExtention,
                                                    ReloadFunction: false
                                                }
                                            }, 'FunkInputQuastionAreaAndVideo')
                                        }

                                    });
                                }

                                jQuery(document).ready(function () {

                                    jQuery('#SelectionVideoTask').on('change', function () {


                                        if (jQuery(this).val() == '') {
                                            jQuery('.VideoListeForTask').hide();
                                        } else {

                                            jQuery('.VideoListeForTask').show();
                                            jQuery('#SelectKursVideoForTask').html('<option value="">Videos werden geladen...</option>').prop('disabled', true);
                                            AjaxSend('database/DbInteraktion.php', {
                                                DbRequest: 'Select',
                                                DbRequestVariation: 'FunkAddVideoListFromDB',
                                                AjaxDataToSend: {KursID: jQuery('#SelectionVideoTask').val()}
                                            }, 'FunkVideoListeForTask')
                                        }
                                    });


                                });


                                window.FunkInputQuastionAreaAndVideo = function(AjaxGet) {

                                    var AjaxDataToSend = {
                                        VideoElementId: AjaxGet.VideoElementId,
                                        KursID: AjaxGet.KursID,
                                        VideoSrc: AjaxGet.VideoSrc,
                                        VideoExtention: AjaxGet.VideoExtention
                                    }

                                    if (AjaxGet.Question != '') {
                                        jQuery("#QuastionInputArea").val(AjaxGet.Question)
                                    }
                                    // jQuery("#QuastionInputArea").val("'"+AjaxGet.Question+"'")


                                    var VideoElementId = AjaxGet.VideoElementId;

                                    jQuery("#InputSavingCancelButton").html('<button id="' + VideoElementId + 'FunkAddQuestionSave" style="font-size: 1.5em;">Aufgabe speichern</button>' +
                                        '<button id="' + VideoElementId + 'FunkAddQuestionCancel" style="font-size: 1.5em;">Abbrechen</button>' +
                                        '<button id="' + VideoElementId + 'FunkReloadTaskForVideoButton" style="font-size: 1.5em; float: right"><span class="glyphicon glyphicon-repeat flipped-glyphicon"  aria-hidden="true"></span></button>')

                                    if (AjaxGet.ReloadFunction != true) {
                                        jQuery("#loadVideoForTaskArea").html(
                                            '	<video id="VideoForTastArea" width="300px" width="320" height="240" controls="controls">' +
                                            AjaxGet.VideoElementSrc +
                                            "   </video>"
                                        );
                                    }

                                    jQuery("#" + VideoElementId + "FunkReloadTaskForVideoButton").on("click", function () {
                                        jQuery('#SelectionVideoTask').val('').trigger('change')
                                        jQuery("#SelectKursVideoForTask").empty()
                                        jQuery('.VideoForTaskAndInputArea').hide();


                                    })
                                    jQuery("#" + VideoElementId + "FunkAddQuestionCancel").on("click", function () {

                                        jQuery("#QuastionInputArea").val(AjaxGet.Question)
                                        jQuery("#InputSavingStatus").html('Abgebrochen')
                                    });

                                    jQuery("#" + VideoElementId + "FunkAddQuestionSave").on("click", function () {

                                        AjaxSend('database/DbInteraktion.php', {
                                            DbRequest: "Insert",
                                            DbRequestVariation: "AddQuestionToVideo",
                                            AjaxDataToSend: {
                                                Question: jQuery("#QuastionInputArea").val(),
                                                VideoElementId: VideoElementId,
                                                KursID: AjaxGet.KursID
                                            }
                                        }, 'false');


                                        AjaxSend('database/DbInteraktion.php', {
                                            DbRequest: 'Select',
                                            DbRequestVariation: 'FunkLoadVideo',
                                            AjaxDataToSend: {
                                                VideoElementId: AjaxGet.VideoElementId,
                                                KursID: AjaxGet.KursID,
                                                VideoSrc: AjaxGet.VideoSrc,
                                                VideoExtention: AjaxGet.VideoExtention,
                                                ReloadFunction: true
                                            }
                                        }, 'FunkInputQuastionAreaAndVideo')


                                        jQuery("#InputSavingStatus").html('Gespeichert')
                                    });
                                }
                            });
						</script>


					</table>


				</div>

				<!-- Kursteilnehmer verwalten -->
				<div role="tabpanel" class="tab-pane " id="Kursliste">
					<br>
					<!--                  <table style="margin:0 auto; height: 100px; border: solid 2px;">-->

					<table style="margin:0 auto; height: 100px;">
						<tr>
							<td style="font-size: 25px; height: 30px;" ALIGN="RIGHT"><b>Kurs: </b></td>
							<td>
								<select name="AdministrationKursList" id="AdministrationKursList"
								        style="font-size: x-large; min-width: 300px;">

								</select>
							</td>
						</tr>
						<tr id="KursteilnehmerListe">

						</tr>
						<tr>

							<td colspan="2" id="ButtonKilTeilnehmerVonKurs">
								<br>
								<input type="button"
								       style="display: block ; margin:0 auto; background-Color: #ef120f;"
								       class="btn btn-primary" value="Teilnehmer aus der Gruppe entfernen"
								       id="KilTeilnehmerVonKurs" disabled>
							</td>
						</tr>
						<tr class="FrageKilTeilnehmerVonKurs" style="display: none">
							<td colspan="2" style="font-size: x-large; text-align: center;"
							    class="FrageKilTeilnehmerVonKurs"><br>Teilnehmende wirklich
								entfernen?<br><br>
						</tr>
						<tr id="AntwortKilTeilnehmerVonKurs" style="display: none">
							<td>
								<input type="button" style="background-Color: #12ef0f;" class="btn btn-primary"
								       value="NEIN"
								       id="NeinKilTeilnehmerVonKurs">
							</td>

							<td>
								<form action="?ausKurslisteloeschen=Teilnehmegeloescht" method="post"
								      id="FormJaKilTeilnehmerVonKurs"
								      style="font-size: x-large;" id="FormJaKilTeilnehmerVonKurs">
									<input type="text" id="JaKilTeilnehmerVonKursName" style="display: none;"
									       name="JaKilTeilnehmerVonKursName" value="">
									<input type="text" id="JaKilTeilnehmerVonKursUser" style="display: none;"
									       name="JaKilTeilnehmerVonKursUser" value="">
									<input type="button" style="background-Color: #ef120f;"
									       class="btn btn-primary" value="JA"
									       id="JaKilTeilnehmerVonKurs">
								</form>
							</td>
						</tr>
					</table>

					<?php
						//						$test = $_POST['test'];
						//						$sendData->test = $test;

					?>
					<script>
                        // async script loading requires to wait for script to be loaded
                        egw_LAB.wait(function() {
                            jQuery('#AdministrationKursList').on('change', function () {
                                // jQuery(this).closest('form').trigger('submit')


                                AjaxSend('database/DbInteraktion.php', {
                                    DbRequest: 'Select',
                                    DbRequestVariation: 'KursteilnehmerListe',
                                    AjaxDataToSend: {KursID: jQuery('#AdministrationKursList').val()}
                                }, 'KursteilnehmerListe');

                            })

                            window.KursteilnehmerListe = function(AjaxGet) {
                                jQuery('#KursteilnehmerListe').html(AjaxGet.KursteilnehmerListe);
                                // alert(AjaxGet.KursteilnehmerListe);
                            }

                            jQuery('#selection2').on('change', function () {
                                if (jQuery(this).val()) {
                                    jQuery('#KilTeilnehmerVonKurs').prop('disabled', false)
                                } else {
                                    jQuery('#KilTeilnehmerVonKurs').prop('disabled', true)
                                }

                            })

                            jQuery('#KilTeilnehmerVonKurs').on('click', function () {
                                jQuery('#KilTeilnehmerVonKurs').prop('disabled', true)

                                jQuery('.FrageKilTeilnehmerVonKurs').show()
                                jQuery('#AntwortKilTeilnehmerVonKurs').show()
                            })

                            jQuery('#NeinKilTeilnehmerVonKurs').on('click', function () {
                                jQuery('#selection2').val('')
                                jQuery('.FrageKilTeilnehmerVonKurs').hide()
                                jQuery('#AntwortKilTeilnehmerVonKurs').hide()
                            })

                            jQuery('#JaKilTeilnehmerVonKurs').on('click', function () {
                                jQuery('#JaKilTeilnehmerVonKursName').val(jQuery('#AdministrationKursList').val())
                                jQuery('#JaKilTeilnehmerVonKursUser').val(jQuery('#selection2').val())

                                jQuery('#FormJaKilTeilnehmerVonKurs').closest('form').trigger('submit')

                            })
                        });
					</script>

				</div>

				<!-- Userrollen -->
				<div role="tabpanel" class="tab-pane " id="Userrolle">
					<br>

					<?php
						if (isset($_GET['Rolle'])) {

							$SelectedKursID = $_POST["selectUserID2"];
							$SelectedUserID = $GLOBALS['egw_info']['user']['account_id'];

                            // store in EGroupware ACL
                            Bo::setAdmin($_POST['selectUserID2'], $_POST['ZumAdminMachen'] === 'Admin');
							//$statement = $pdo->prepare("UPDATE users SET userrole = :userrole WHERE  id=:id");
							//$result = $statement->execute(array('userrole' => $_POST['ZumAdminMachen'], 'id' => $_POST['selectUserID2']));

							//$Kurs = $statement->fetch();
						};

					?>
					<form action="?Rolle=bestimmt" method="post" class="form-horizontal">
						<table style="margin:0 auto; height: 100px;">
							<tr>
								<td style="font-size: 25px; height: 30px;" ALIGN="RIGHT"><b>Nickname: </b></td>
								<td>

									<select name="selectUserID2" id="selectUserID2"
									        style="font-size: x-large; min-width: 300px;">
										<?php
										    //$stmt1 = $pdo->prepare("SELECT * FROM users WHERE Organisation = :UserOrganisation ORDER BY nickname");
											$stmt1 = $pdo->prepare("SELECT account_lid AS nickname, account_id AS id FROM egw_addressbook JOIN egw_accounts USING(account_id) WHERE org_name = :UserOrganisation ORDER BY account_lid");
											$stmt1->execute(array('UserOrganisation' => Bo::getOrganisation()));
											$results = $stmt1->fetchAll(PDO::FETCH_ASSOC);
											// Einträge ausgeben
											echo '<option value="" > -' . Bo::getOrganisation() . '- Bitte wählen - </option>';
											foreach ($results as $result) {
												$rolen = Bo::checkAdmin($result['id']) ? "Lehrperson" : "Studierende";
												echo '<option value="' . $result["id"] . '"  >' . $result["nickname"] . " [ID: " . $result["id"] . ' Rolle: ' . $rolen . ' ]</option>';
											}

										?>
									</select>
								</td>
							</tr>
							<tr>
								<td style="font-size: 25px; height: 30px;" ALIGN="RIGHT"><b>Rolle zuweisen: </b></td>
								<td style="font-size: 25px; height: 30px;">
									<label style="margin-bottom: 0px; font-weight: normal;" for="ZumAdminMachen">
										<input type="radio" id="ZumAdminMachen" name="ZumAdminMachen" value="Admin"
										       class="ChangeRolleUser" disabled>
										Lehrperson
									</label>
								</td>
							</tr>
							<tr>
								<td></td>
								<td style="font-size: 25px; height: 30px;">
									<label style="margin-bottom: 0px; font-weight: normal;" for="ZumAdminMachen">
										<input type="radio" id="ZumUserMachen" name="ZumAdminMachen" value="User"
										       class="ChangeRolleUser" disabled>
										Studierende
									</label>
								</td>
							</tr>
							<tr>

								<td colspan="2" id="ButtonChangeRolleUser">
									<br>
									<input type="submit"
									       style="display: block;width: 300px; margin:0 auto; background-Color: #ef120f;"
									       class="btn btn-primary" value="Rolle ändern" id="ChangeRolleUser"
									       class="ChangeRolleUser" disabled>

									</input>
								</td>
							</tr>

						</table>
					</form>
				</div>

				<script>
                    // async script loading requires to wait for script to be loaded
                    egw_LAB.wait(function() {
                        jQuery('#selectUserID2').on('change', function () {
                            if (jQuery(this).val()) {
                                jQuery('.ChangeRolleUser').prop('disabled', false)
                                jQuery('#ChangeRolleUser').prop('disabled', false)
                            } else {
                                jQuery('.ChangeRolleUser').prop('disabled', true)
                                jQuery('#ChangeRolleUser').prop('disabled', true)
                            }
                        })
                    });
				</script>

			</div>

		</div>

	</div>


	<?php
} //Ende von if($showFormular)


?>
	</div>

	<script>
        // async script loading requires to wait for script to be loaded
        egw_LAB.wait(function() {
            window.FunkLoadKursListForOwner = function() {
                AjaxSend('database/DbInteraktion.php', {
                    DbRequest: 'Select',
                    DbRequestVariation: 'FunkShowKurslistOwner',
                    AjaxDataToSend: {}
                }, 'FunkLoadKursForVerwaltung');
            }

            window.FunkLoadKursListForOwner()

            if (window.location.href.indexOf("?Kurs=") > -1) {
                jQuery('#KurseVerwaltenLi').addClass('active')
                jQuery('#KurseVerwalten').addClass('active')
            }
            if (window.location.href.indexOf("?Video=") > -1) {
                jQuery('#KursVideoUploadLi').addClass('active')
                jQuery('#KursVideoUpload').addClass('active')
            }
            if (window.location.href.indexOf("?Rolle=") > -1) {
                jQuery('#UserrolleLi').addClass('active')
                jQuery('#Userrolle').addClass('active')
            }

            if (window.location.href.indexOf("?") == -1) {
                jQuery('#KursVideoUploadLi').addClass('active')
                jQuery('#KursVideoUpload').addClass('tab-pane active')

                // jQuery('#KurseVerwaltenLi').addClass('active')
                // jQuery('#KurseVerwalten').addClass('active')
            }

            //alert(<?php //echo Bo::getNickname(); ?>//)
        });
	</script>

<?php
	if (Bo::getNickname() == 'TestasAdmin[18]') {
		?>
		<script>
            // async script loading requires to wait for script to be loaded
            egw_LAB.wait(function() {
                jQuery("#AdministrationKursList, #selectUserID2").prop('disabled', true)
                jQuery('#IsDisabledForTest').remove();
                jQuery("#Kursliste, #Userrolle").append('<span id="IsDisabledForTest">Ist für Testzugang ausgeschaltet</span>')
            });
		</script>
		<?php
	}
	include("templates/footer.inc.php");
?>