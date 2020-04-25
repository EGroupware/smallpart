<?php
use EGroupware\Api;
use EGroupware\SmallParT\Bo;

require_once("inc/config.inc.php");
include("templates/header.inc.php")
?>

   <div class="container main-container">

      <?php
      if (Bo::isAdmin()) {
         $showFormular = true;
      } else {
         $showFormular = false;
      } //Variable ob das Registrierungsformular anezeigt werden soll
	      $showFormular = true;
      if (isset($_GET['Kurs'])) {
         $error = false;
         $KursPasswort = $_POST['KursPasswort'];


//         if (strlen($KursPasswort) == 0) {
//            //echo 'Bitte ein Passwort angeben<br>';
//            $error = true;
//         }


//         echo $_POST["selectionKursID"]." <--------<br>";
//         echo $KursPasswort." <--------<br>";
//         echo $_REQUEST['d']." <--------<br>";
//         echo $_REQUEST['Kurs']." <--------<br>";


         //Überprüfe, Ob der Teilnehmer schon regisitreiert ist für den Kurs
	      if (!$error) {
		      $statementusercheck = $pdo->prepare("SELECT * FROM KurseUndTeilnehmer WHERE KursID = :KursID AND UserID =:UserID");
		      $resultcheck = $statementusercheck->execute(array('KursID' => $_POST["selectionKursID"], 'UserID'=> $GLOBALS['egw_info']['user']['account_id']));
		      $UserMitglied = $statementusercheck->fetch();
		
		      if ($UserMitglied) {
			      $error = true;
			      $Nachricht = '<br><br><b style="background-color:#1f1f1f; color: #ef120f; font-size: large;">Sie Sind im Kurs</b>';
			
			
		      }
	      }
	
	
	      //Überprüfe, dass der Kurspasswort stimmt
         if (!$error) {
            $statement = $pdo->prepare("SELECT * FROM Kurse WHERE KursID = :KursID");
            $result = $statement->execute(array('KursID' => $_POST["selectionKursID"]));
            $Kurs = $statement->fetch();

            if ($KursPasswort !== $Kurs['KursPasswort']) {
               $error = true;
               $Nachricht = '<br><br><b style="background-color:#1f1f1f; color: #ef120f; font-size: large;">Passwort ist falsch.</b>';


            }
         }

         //Keine Fehler, wir können dem Kurs beitreten
         if (!$error and $_REQUEST['Kurs']!=='beigetreten') {

            $statement = $pdo->prepare("INSERT INTO KurseUndTeilnehmer (KursID, UserID) VALUES (:KursID, :UserID)");
            $result = $statement->execute(array('KursID' => $_POST["selectionKursID"], 'UserID' => $GLOBALS['egw_info']['user']['account_id'] ));



            Echo '<script>$("#inputPasswort").val("");</script>';

            if ($result) {
	            header( "refresh:3; url=KursVerwaltung.php?Kurs=beigetreten" );
               $Nachricht = '<br><br><b style="background-color:#1f1f1f; color: #3ADF00; font-size: large;">Dem Kurs  wurde erfolgreich beigetreten.</b>';

            } else {
               echo 'Beim Abspeichern ist leider ein Fehler aufgetreten<br>';
               $Nachricht = '<br><br><b style="background-color:#1f1f1f; color: #ef120f; font-size: large;">Beim Abspeichern ist leider ein Fehler aufgetreten</b>';
            }
         }
      }


//      $stmt = $pdo->prepare("SELECT * FROM Kurse ");
      $stmt = $pdo->prepare("SELECT * FROM Kurse WHERE Organisation =:userorganisation ORDER BY KursName");

      $stmt->execute(array('userorganisation' => Bo::getOrganisation()));
      $results = $stmt->fetchAll(PDO::FETCH_ASSOC);
      // Einträge ausgeben


      if (!isset($_GET['Kursliste'])) {
         $KursListe .= '<option selected >- Bitte Auswählen -</option>';
      };
      foreach ($results as $result) {
	      $statementusercheck2 = $pdo->prepare("SELECT * FROM KurseUndTeilnehmer WHERE KursID = :KursID AND UserID =:UserID");
	      $resultcheck2 = $statementusercheck2->execute(array('KursID' => $result["KursID"], 'UserID'=> $GLOBALS['egw_info']['user']['account_id']));
	      $UserMitglied2 = $statementusercheck2->fetch();
	      if (!$UserMitglied2 && !$result["KursClosed"]) {
		      if ($result["KursID"] === $_POST["selection1"]) {
			      $optionselected = "selected=\"selected\"";
		      } else {
			      $optionselected = '';
		      }
		      $KursListe .= '<option ' . $optionselected . ' value="' . $result["KursID"] . '">' . $result["KursName"] . ' [ Kurs-Id: ' . $result["KursID"] . ' ]</option>';
	      }
      }
      


      if (isset($_GET['Kursverlassen'])) {

         $SelectedKursID = $_POST["selectionKursID2"];
         $SelectedUserID = $GLOBALS['egw_info']['user']['account_id'];

//         echo "tada" . $SelectedKursID . " - " . $SelectedUserID;

         $statement = $pdo->prepare("DELETE FROM KurseUndTeilnehmer WHERE KursID = :KursID AND UserID=:UserID");
         $result = $statement->execute(array('KursID' => $SelectedKursID, 'UserID' => $SelectedUserID));
         $Kurs = $statement->fetch();
      };

      if ($showFormular) {
         ?>


         <h1>Kursverwaltung</h1>

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
               <li role="presentation" class="active"><a href="#Kursanlegen" aria-controls="Angelegt" role="tab"
                                                         data-toggle="tab">Kurs beitreten</a></li>
               <li role="presentation" ><a href="#Kursliste" aria-controls="Kursliste" role="tab" data-toggle="tab">Kurs
                     austreten</a></li>
<!--               <li role="presentation"><a href="#passwort" aria-controls="messages" role="tab" data-toggle="tab">Kurzteilnehmer</a>               </li>-->
            </ul>


            <div class="tab-content">
               <!-- Kurs beitreten-->
               <div role="tabpanel" class="tab-pane active" id="Kursanlegen">
                  <br>
                  <form action="?Kurs=beitreten" method="post" class="form-horizontal">

                     <div class="form-group">
                        <label for="inputVorname" class="col-sm-2 control-label">Kursname:</label>
                        <div class="col-sm-10">
                           <select name="selectionKursID" id="selectionKursID" style="font-size: x-large; width: 100%;">
                              <?php echo $KursListe; ?>
                           </select>

                        </div>
                     </div>

                     <div class="form-group">
                        <label for="inputNachname" class="col-sm-2 control-label">Passwort:</label>
                        <div class="col-sm-10">
                           <input type="text" id="inputPasswort" size="40" maxlength="250" name="KursPasswort"
                                  class="form-control" value="" required>
                        </div>
                     </div>

                     <div class="form-group">
                        <div class="col-sm-offset-2 col-sm-10">

                           <button type="submit" class="btn btn-primary">Kurs beitreten</button>
                           <?php echo $Nachricht; ?>

                        </div>
                     </div>

                  </form>
               </div>

               <!-- Kurse verlassen -->
               <div role="tabpanel" class="tab-pane " id="Kursliste">
                  <br>
                  <!--                  <table style="margin:0 auto; height: 100px; border: solid 2px;">-->
                  <form action="?Kursverlassen=Verlassen" method="post" class="form-horizontal">
                     <table style="margin:0 auto; height: 100px;" >
                        <tr>
                           <td style="font-size: 25px; height: 30px;" ALIGN="RIGHT"><b>Kurs: </b></td>
                           <td>

                              <select name="selectionKursID2" id="selectionKursID2" style="font-size: x-large; min-width: 300px;">
                                 <?php
                                 $stmt1 = $pdo->prepare("SELECT * FROM Kurse k INNER JOIN KurseUndTeilnehmer kt ON k.KursID = kt.KursID AND UserID= :UserID ORDER BY k.KursName");
                                 $stmt1->execute(array('UserID' => $GLOBALS['egw_info']['user']['account_id']));
                                 $results = $stmt1->fetchAll(PDO::FETCH_ASSOC);
                                 // Einträge ausgeben
                                 echo '<option value=""> - Bitte wählen - </option>';
                                 foreach ($results as $result) {
                                 	if ($result["KursOwner"]!=$GLOBALS['egw_info']['user']['account_id'] && !$result["KursClosed"]) {
	                                    echo '<option value="' . $result["KursID"] . '">' . $result["KursName"] . " [ID: " . $result["KursID"] . ' - '.$result["KursOwner"].' ]</option>';
                                    }
                                 }
                                 ?>
                              </select>
                           </td>
                        </tr>
                        <tr>
                           <td style="font-size: 25px; height: 30px;" ALIGN="RIGHT"><b>Teilnehmer: </b></td>
                           <td style="font-size: 25px; height: 30px;">
                              <?php
                              echo " ".$_SESSION['usereemail'] ;
                              ?>
                           </td>
                        </tr>
                        <tr>
                           <td style="font-size: 25px; height: 30px;" ALIGN="RIGHT"><b>Nickname: </b></td>
                           <td style="font-size: 25px; height: 30px;">
                              <?php
                              echo " ". Bo::getNickname();
                              ?>
                           </td>
                        </tr>

                     <tr>

                        <td colspan="2" id="ButtonKilTeilnehmerVonKurs">
                           <br>
                           <button style="display: block;width: 300px; margin:0 auto; background-Color: #ef120f;"
                                  class="btn btn-primary" value=""
                                   id="KilTeilnehmerVonKurs" disabled>Aus dem Kurs austretten</button>
                        </td>
                     </tr>
                     <tr id="FrageKilTeilnehmerVonKurs" style="display: none">
                        <td colspan="2" style="font-size: x-large; text-align: center;"><br>Kurs wirklich
                           verlassen?<br><br>
                           <button style="background-Color: #12ef0f;" class="btn btn-primary"
                                   id="NeinKilTeilnehmerVonKurs">NEIN</button>

                           <button type="submit" style="background-Color: #ef120f;" class="btn btn-primary"
                                   id="JaKilTeilnehmerVonKurs">JA</button>



                           </form>



                  </table>
                  <script>

                      $('#selectionKursID2').on('change', function () {
                          if ($(this).val()) {
                              $('#KilTeilnehmerVonKurs').prop("disabled", false);
                          } else {
                              $('#KilTeilnehmerVonKurs').prop("disabled", true);
                          }

                      });

                      $('#KilTeilnehmerVonKurs').on('click', function () {
                          $('#KilTeilnehmerVonKurs').prop("disabled", true);

                          $('#FrageKilTeilnehmerVonKurs').show();

                      });

                      $('#NeinKilTeilnehmerVonKurs').on('click', function () {
                          $('#selection2').val("");
                          $('#FrageKilTeilnehmerVonKurs').hide();

                      });




                  </script>

               </div>

               <!-- Änderung des Passworts -->
<!--               <div role="tabpanel" class="tab-pane" id="passwort">-->
<!--                  <br>-->
<!--                  <p>Zum Änderen deines Passworts gib bitte dein aktuelles Passwort sowie das neue Passwort-->
<!--                     ein.</p>-->
<!--                  <form action="?save=passwort" method="post" class="form-horizontal">-->
<!--                     <div class="form-group">-->
<!--                        <label for="inputPasswort" class="col-sm-2 control-label">Altes Passwort</label>-->
<!--                        <div class="col-sm-10">-->
<!--                           <input class="form-control" id="inputPasswort" name="passwortAlt" type="password"-->
<!--                                  required>-->
<!--                        </div>-->
<!--                     </div>-->
<!---->
<!--                     <div class="form-group">-->
<!--                        <label for="inputPasswortNeu" class="col-sm-2 control-label">Neues Passwort</label>-->
<!--                        <div class="col-sm-10">-->
<!--                           <input class="form-control" id="inputPasswortNeu" name="passwortNeu" type="password"-->
<!--                                  required>-->
<!--                        </div>-->
<!--                     </div>-->
<!---->
<!---->
<!--                     <div class="form-group">-->
<!--                        <label for="inputPasswortNeu2" class="col-sm-2 control-label">Neues Passwort-->
<!--                           (wiederholen)</label>-->
<!--                        <div class="col-sm-10">-->
<!--                           <input class="form-control" id="inputPasswortNeu2" name="passwortNeu2"-->
<!--                                  type="password" required>-->
<!--                        </div>-->
<!--                     </div>-->
<!---->
<!--                     <div class="form-group">-->
<!--                        <div class="col-sm-offset-2 col-sm-10">-->
<!--                           <button type="submit" class="btn btn-primary">Speichern</button>-->
<!--                        </div>-->
<!--                     </div>-->
<!--                  </form>-->
<!--               </div>-->
            </div>

         </div>


         <?php
      } //Ende von if($showFormular)


      ?>
   </div>


<?php
include("templates/footer.inc.php")
?>