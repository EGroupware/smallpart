<?php

use EGroupware\Api;
use EGroupware\SmallParT\Bo;

include("../utils/LoadPhp.php");

$video_select = "SELECT video_id AS VideoListID, course_id AS KursID, CONCAT('video__', CAST(video_id AS CHAR)) AS VideoElementId,".
	" REVERSE(SUBSTRING_INDEX(REVERSE(video_name), '.', -1)) AS VideoName, SUBSTRING_INDEX(video_name, '.', -1) AS VideoExtention,".
	" video_name AS VideoNameType, video_date AS VideoDate, CONCAT('Resources/Videos/Video', '/', CAST(video_id AS CHAR), '/', video_name) AS VideoSrc".
	" FROM egw_smallpart_videos";

/**
 * Get all videos of a course
 *
 * @param int $course_id
 * @return array
 */
function videosOfCourse($course_id)
{
	global $pdo, $video_select;

	$statement = $pdo->prepare( "$video_select WHERE course_id=:course_id ORDER BY video_name");
	$statement->execute(array('course_id' => $course_id));

	return $statement->fetchAll(PDO::FETCH_ASSOC);
}

/**
 * Read data of one video
 *
 * @param int $video_id
 * @return array|false
 */
function readVideo($video_id)
{
	global $pdo, $video_select;

	$statement = $pdo->prepare( "$video_select WHERE video_id=:video_id");
	$statement->execute(array('video_id' => $video_id));

	return $statement->fetch(PDO::FETCH_ASSOC);
}

//prepare arrived
	$DbRequest = $_POST['DbRequest'];
	$DbRequestVariation = $_POST['DbRequestVariation'];
	$arrivedData = $_POST['AjaxDataToSend'];

	$beforetime = microtime(true);
//prepare sending
	$ajax = new Ajax();
	$sendData = new stdClass();
//todo DB-request
//	$sendData->VideoDivParent = '';
//	$sendData->Controlbutton = '';
//	$sendData->Output1 = '';
//	$sendData->Output2 = '';
//	$sendData->VideoElementId = '';
//	$sendData->VideoDiv = '';
	$sendData->VideoWidth = '800';
	$sendData->UserId = $GLOBALS['egw_info']['user']['account_id'];
	$sendData->UserNickname = Bo::getNickname();
//	$sendData->VideoList = '';
	$sendData->UserRole = Bo::isAdmin() ? 'Admin' : null;
	$sendData->Superadmin = Bo::isSuperAdmin();
	$sendData->KursID = $arrivedData['KursID'];
	$sendData->ReloadFunction = $arrivedData['ReloadFunction'];

	if ($DbRequest == 'Insert') {
		if ($arrivedData['StopTime'] <= 2) {
			$StartTime = 0;
		} else {
			$StartTime = $arrivedData['StopTime'] - 2;
		}
		$StartTime = $arrivedData['StopTime'];

		$StopTime = $arrivedData['StopTime'];
		$VideoListID = $arrivedData['VideoListID'];
		$VideoElementId = $arrivedData['VideoElementId'];
		$VideoWidth = $arrivedData['VideoWidth'];
		$VideoHeight = $arrivedData['VideoHeight'];
		$MarkedArea = $arrivedData['MarkedArea'];
		$MarkedAreaColor = $arrivedData['MarkedAreaColor'];
		$AddedComment = $arrivedData['AddedComment'];
		$EditedCommentHistory = $arrivedData['EditedCommentHistory'];
		$UserID = $GLOBALS['egw_info']['user']['account_id'];
		$UserNickname = Bo::getNickname();
		$AmpelColor = $arrivedData['AmpelColor'];
		$InfoAlert = $arrivedData['InfoAlert'];
	}
//	$sendData->UserId = $UserID;


	if ($DbRequest == 'FunkUploadVideo') {
		if (isset($_FILES['uploaddatei'])) {

			$UploadStatus = 'isset';

			$allowedExts = array("jpg", "jpeg", "gif", "png", "mp3", "mp4", "wma", "webm");
			$extension = pathinfo($_FILES['uploaddatei']['name'], PATHINFO_EXTENSION);

			if (in_array($extension, $allowedExts) && ($_FILES["uploaddatei"]["size"] < 524288000000000000)) {


				$UploadStatus = 'in Array';
				$UploadStatus = "Upload: " . $_FILES["uploaddatei"]["name"] . "<br />";
				$UploadStatus .= "Type: " . $_FILES["uploaddatei"]["type"] . "<br />";
				$UploadStatus .= "Size: " . ($_FILES["uploaddatei"]["size"] / 1048576) . " MB<br />";
				$UploadStatus .= "Temp file: " . $_FILES["uploaddatei"]["tmp_name"] . "<br />";

				$FileNameType = umlautepas($_FILES['uploaddatei']['name']);
				$FileName = pathinfo($FileNameType, PATHINFO_FILENAME);
				$extension = pathinfo($FileNameType, PATHINFO_EXTENSION);
				$FileFolder = "Resources/Videos/Video/" . $_POST['KursID'] . "/";
				$FileSrc = $FileFolder . $FileNameType;
				$FileDate = date("Y-m-d H:i:s", filectime($_FILES["uploaddatei"]["tmp_name"]));
				$SavePath = '../' . $FileFolder . $FileNameType;


//				$UploadStatus .= "<br>--------------------+++-----------------------------<br>";
//				$UploadStatus .= '$VideoName: ' . $VideoName . "<br />";
//				$UploadStatus .= 'umlautepas: ' . $FileNameType . "<br />";
//				$UploadStatus .= '$FileName: ' . $FileName . "<br />";
//				$UploadStatus .= '$extension: ' . $extension . "<br />";
//				$UploadStatus .= '$FileFolder: ' . $FileFolder . "<br />";
//				$UploadStatus .= '$FileNameType: ' . $FileNameType . "<br />";
//				$UploadStatus .= '$FileSrc: ' . $FileSrc . "<br />";
//				$UploadStatus .= '$FileDate: ' . $FileDate . "<br />";
//				$UploadStatus .= "KursID: " . $_POST['KursID'] . "<br />";
//				$UploadStatus .= "<br>--------------------+++-----------------------------<br>";


//				$pdo = FunkDbParam($dbName);
				$pdo = FunkDbParam();
				$statementUpload = $pdo->prepare("SELECT video_id FROM egw_smallpart_videos WHERE video_name LIKE :VideoNameType AND course_id = :course_id");
				$resultUpload = $statementUpload->execute(array('VideoNameType' => "%.$FileNameType", 'course_id' => $_POST['KursID']));
				$FileExist = $statementUpload->fetch();


				if ($FileExist == false) {

					if (move_uploaded_file($_FILES['uploaddatei']['tmp_name'], $SavePath)) {
						$statementUploadDo = $pdo->prepare("INSERT INTO egw_smallpart_videos(video_name, course_id, video_date) VALUES(:video_name, :course_id, :video_date)");

						$resultUploadDo = $statementUploadDo->execute(array('video_name' => $FileNameType, 'video_date' => $FileDate, 'course_id' => $_POST['KursID']));

						$UploadStatus = "Video hochgeladen";
						$pdo = null;
					} else {
						$UploadStatus = " Failed to Save: " . $_FILES["uploaddatei"]["name"];
					}

				} else {
					$UploadStatus = $_FILES["uploaddatei"]["name"] . " already exists . ";
				}
//

			} else {
				$UploadStatus = "Invalid file";
			}


			$sendData->UploadStus = $UploadStatus;
		}

//	$sendData->UploadStatus = isset($_FILES['uploaddatei']);
		$sendData->UploadStatus = $UploadStatus;
	}

//current Db-Funktion

	//				$pdo = FunkDbParam($dbName);
	$pdo = FunkDbParam();

	switch ($_POST['DbRequestVariation']) {


		case "FunkShowKursAndVideolist2":

			$stmt1 = $pdo->prepare("SELECT k.course_id, k.course_name".
				" FROM egw_smallpart_courses k".
				" INNER JOIN egw_smallpart_course_parts kt ON k.course_id = kt.course_id AND account_id=:account_id".
				" ORDER BY k.course_name");
			$stmt1->execute(array('account_id' => $GLOBALS['egw_info']['user']['account_id']));
			$KursList = $stmt1->fetchAll(PDO::FETCH_ASSOC);

			$sendData->KursList = $KursList;

			foreach ($KursList as $VideoListForKurs)
			{
				$VideoList[$VideoListForKurs['KursID']] = videosOfCourse($VideoListForKurs['KursID']);
			}

			// @Arash: there is/was no colum users.LastVideoWorkingOnElementId only a table LastVideoWorkingOn
			// Looks like something missed in creating of LastvideoWorkingOn table
			// @ToDo: fix as our PDO class throws by default!
			//$statement3 = $pdo->prepare("SELECT LastVideoWorkingOnElementId FROM users WHERE id= :UserID");
//			$statement3 = $pdo->prepare("SELECT LastVideoWorkingOnElementId FROM users WHERE id= :UserID");
			//$statement3->execute(array('UserID' => $GLOBALS['egw_info']['user']['account_id']));
			//$LastVideoWorkingOn = $statement3->fetchAll(PDO::FETCH_ASSOC);


			$sendData->LastVideoWorkingOn = $LastVideoWorkingOn;
			$sendData->VideoList = $VideoList;

			break;

		case "FunkShowKursAndVideolist":

			$stmt1 = $pdo->prepare("SELECT k.course_id AS KursID, k.course_name AS KursName".
				" FROM egw_smallpart_courses k".
				" INNER JOIN egw_smallpart_course_parts kt ON k.course_id = kt.course_id AND account_id=:account_id".
				" ORDER BY k.course_name");
			$stmt1->execute(array('account_id' => $GLOBALS['egw_info']['user']['account_id']));
			$KursList = $stmt1->fetchAll(PDO::FETCH_ASSOC);

			$sendData->KursList = $KursList;

			foreach ($KursList as $VideoListForKurs)
			{
				$VideoList[$VideoListForKurs['KursID']] = videosOfCourse($VideoListForKurs['KursID']);
			}

			$statement3 = $pdo->prepare("SELECT LastVideoWorkingOnData FROM LastVideoWorkingOn WHERE UserId= :UserID");
//			$statement3 = $pdo->prepare("SELECT LastVideoWorkingOnElementId FROM users WHERE id= :UserID");
			$statement3->execute(array('UserID' => $GLOBALS['egw_info']['user']['account_id']));
			$LastVideoWorkingOn = $statement3->fetchAll(PDO::FETCH_ASSOC);
//			$LastVideoWorkingOn = $statement3->fetch(PDO::FETCH_ASSOC);
//


			$sendData->LastVideoWorkingOn = $LastVideoWorkingOn;
			$sendData->VideoList = $VideoList;

			break;

		case "FunkShowKurslist":
			$stmt1 = $pdo->prepare("SELECT k.course_id AS KursID, course_name AS KursName, course_owner AS KursOwner, course_org AS Organisation, course_closed AS KurseClosed".
				" FROM egw_smallpart_courses k".
				" INNER JOIN egw_smallpart_course_parts kt ON k.course_id = kt.course_id AND account_id= :account_id".
				" ORDER BY k.course_id");
			$stmt1->execute(array('account_id' => $GLOBALS['egw_info']['user']['account_id']));
			$KursList = $stmt1->fetchAll(PDO::FETCH_ASSOC);

			$sendData->KursList = $KursList;

			break;

		case "FunkAddVideoListFromDB":
			$sendData->VideoList = videosOfCourse($arrivedData['KursID']);
			break;

		case "KursteilnehmerListe":

			//				$pdo = FunkDbParam($dbName);
			$pdo = FunkDbParam();
			$stmt = $pdo->prepare("SELECT account_id FROM egw_smallpart_course_parts WHERE course_id=:course_id");
			$stmt->execute(array('course_id' => $arrivedData['KursID']));
			$results = $stmt->fetchAll(PDO::FETCH_ASSOC);
			// Einträge ausgeben
			$KursteilnehmerListe = '<td style="font-size: 25px; height: 30px;" ALIGN="RIGHT"><b>Teilnehmende: </b>';
			$KursteilnehmerListe .= '</td>';
			$KursteilnehmerListe .= '<td>';
			$KursteilnehmerListe .= '<select style="font-size: x-large; min-width: 300px; " name="selection2" id="selection2">';
			$KursteilnehmerListe .= '<option></option>';

			foreach ($results as $result) {
				$KursteilnehmerListe .= '<option value="' . $result["UserID"] . '">' . Bo::getNickname($result['UserID']) . $result["UserID"] . '</option>';
			}
			$KursteilnehmerListe .= '</select>';
			$KursteilnehmerListe .= '</td>';
			//$pdo = null;
			$sendData->KursteilnehmerListe = $KursteilnehmerListe;

			break;

		case "FunkShowKurslistOwner":

			// Kurse auflisten
			$stmt = $pdo->prepare("SELECT course_id AS KursID, course_name AS KursName, course_owner AS KursOwner, course_org AS Organisation, course_closed AS KurseClosed".
				" FROM egw_smallpart_courses WHERE course_owner =:course_owner ORDER BY course_name");
			$stmt->execute(array('course_owner' => $GLOBALS['egw_info']['user']['account_id']));
			$KursListOwner = $stmt->fetchAll(PDO::FETCH_ASSOC);

			$sendData->KursListOwner = $KursListOwner;

			break;

		case "FunkShowCommentsAdmin":


			$statement = $pdo->prepare("SELECT * FROM test WHERE VideoElementId=? AND KursID=?  AND Deleted = 0 ORDER BY StartTime ASC, ID ASC");
			$statement->execute(array($arrivedData['VideoElementId'], $arrivedData['KursID']));
			//nur Objekt übergeben:
			$ergebnis = $statement->fetchAll();

			//$statement2 = $pdo->prepare("SELECT nickname, vorname, nachname FROM users u INNER JOIN egw_smallpart_course_parts kt ON u.ID = kt.UserID AND KursID= :KursID");
			// this assumes accounts are stored in SQL!
			$statement2 = $pdo->prepare("SELECT egw_smallpart_course_parts.account_id AS UserID, account_lid AS nickname, n_given AS vorname, n_family AS nachname".
				" FROM egw_smallpart_course_parts".
				" JOIN egw_accounts ON egw_accounts.account_id=egw_smallpart_course_parts.account_id".
				" JOIN egw_addressbook ON egw_addressbook.account_id=egw_smallpart_course_parts.account_id".
				" WHERE course_id=:course_id");
			$statement2->execute(array('course_id' => $arrivedData['KursID']));
			$ShowUserNameList = $statement2->fetchAll();

			$statement3 = $pdo->prepare("SELECT course_owner FROM egw_smallpart_courses WHERE course_id = :course_id");
			$statement3->execute(array('course_id' => $arrivedData['KursID']));
			$AllowdToSeeNames = $statement3->fetchAll();


//			$pdo = null;

			$sendData->CommentsTimePoint = "Alle Kommentare:";
			$sendData->AllowdToSeeNames = $AllowdToSeeNames;
			$sendData->ShowUserNameList = $ShowUserNameList;
			$sendData->ShowSavedComments = $ergebnis;

			break;

		case "FunkLoadVideo":

			//$VideoElementSrc

			$VideoElementSrc = '<source class="' . $arrivedData['VideoElementId'] . '" src="' . $arrivedData['VideoSrc'] . '" type="video/' . $arrivedData['VideoExtention'] . '">';

			//	Vtt exist?
			if (file_exists("../Resources/Videos/Video_vtt/" . $arrivedData['KursID'] . "/" . $arrivedData['VideoElementId'] . ".vtt")) {
				$VideoElementSrc .= '<track class="' . $arrivedData['VideoElementId'] . '" src="Resources/Videos/Video_vtt/' . $arrivedData['KursID'] . '/' . $arrivedData['VideoElementId'] . '.vtt" kind="metadata" default>';
			}

			//	$stmtKursVideoQuestionResult

			$stmtKursVideoQuestion = $pdo->prepare("SELECT * FROM KursVideoQuestion WHERE KursID=? AND VideoElementId=?");
			$stmtKursVideoQuestion->execute(array($arrivedData['KursID'], $arrivedData['VideoElementId']));
			$stmtKursVideoQuestionResult = $stmtKursVideoQuestion->fetch();


//			$statementVideoWorkingOn = $pdo->prepare("Update users SET LastVideoWorkingOnElementId= :LastVideoWorkingOnElementId WHERE  id=:userid");
//			$statementVideoWorkingOn->execute(array('LastVideoWorkingOnElementId' => json_encode($arrivedData), 'userid' => $GLOBALS['egw_info']['user']['account_id']));


			$statementVideoWorkingOn = $pdo->prepare("INSERT INTO LastVideoWorkingOn (UserId, LastVideoWorkingOnData) VALUES (:userid, :LastVideoWorkingOnData) ON DUPLICATE KEY UPDATE LastVideoWorkingOnData=:LastVideoWorkingOnData");
			$statementVideoWorkingOn->execute(array('userid' => $GLOBALS['egw_info']['user']['account_id'], 'LastVideoWorkingOnData' => json_encode($arrivedData)));


			$sendData->VideoElementSrc = $VideoElementSrc;
			$sendData->VideoExtention = $arrivedData['VideoExtention'];
			$sendData->VideoSrc = $arrivedData['VideoSrc'];
			$sendData->Question = $stmtKursVideoQuestionResult['Question'];

			break;

//		case "FunkLoadVideo2":
//
//			//$VideoElementSrc
//
//			$VideoElementSrc = '<source class="' . $arrivedData['VideoElementId'] . '" src="' . $arrivedData['VideoSrc'] . '" type="video/' . $arrivedData['VideoExtention'] . '">';
//
//			//	Vtt exist?
//			if (file_exists("../Resources/Videos/Video_vtt/" . $arrivedData['KursID'] . "/" . $arrivedData['VideoElementId'] . ".vtt")) {
//				$VideoElementSrc .= '<track class="' . $arrivedData['VideoElementId'] . '" src="Resources/Videos/Video_vtt/' . $arrivedData['KursID'] . '/' . $arrivedData['VideoElementId'] . '.vtt" kind="metadata" default>';
//			}
//
//			//	$stmtKursVideoQuestionResult
//
//			$stmtKursVideoQuestion = $pdo->prepare("SELECT * FROM KursVideoQuestion WHERE KursID=? AND VideoElementId=?");
//			$stmtKursVideoQuestion->execute(array($arrivedData['KursID'], $arrivedData['VideoElementId']));
//			$stmtKursVideoQuestionResult = $stmtKursVideoQuestion->fetch();
//
//
//			$statementVideoWorkingOn = $pdo->prepare("Update users SET LastVideoWorkingOnElementId= :LastVideoWorkingOnElementId WHERE  id=:userid");
//			$statementVideoWorkingOn->execute(array('LastVideoWorkingOnElementId' => json_encode($arrivedData), 'userid' => $GLOBALS['egw_info']['user']['account_id']));
//
//			if ($GLOBALS['egw_info']['user']['account_id'] != 37) {
//				$statementVideoWorkingOn = $pdo->prepare("INSERT INTO LastVideoWorkingOn (UserId, LastVideoWorkingOnData) VALUES (:userid, :LastVideoWorkingOnData)");
//				$statementVideoWorkingOn->execute(array('userid' => $GLOBALS['egw_info']['user']['account_id'], 'LastVideoWorkingOnData' => json_encode($arrivedData)));
//			}
//
//			$sendData->VideoElementSrc = $VideoElementSrc;
//			$sendData->VideoExtention = $arrivedData['VideoExtention'];
//			$sendData->VideoSrc = $arrivedData['VideoSrc'];
//			$sendData->Question = $stmtKursVideoQuestionResult['Question'];
//
//			break;

		case 'SavedInput':

			$statement2 = $pdo->prepare("INSERT INTO test (UserID, KursID, UserNickname, VideoElementId, StartTime, StopTime, AmpelColor, AddedComment, VideoWidth, VideoHeight, MarkedArea, MarkedAreaColor, InfoAlert) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ? ,?)");
			$statement2->execute(array($UserID, $arrivedData['KursID'], $UserNickname, $VideoElementId, $StartTime, $StopTime, $AmpelColor, $AddedComment, $VideoWidth,
				$VideoHeight, $MarkedArea, $MarkedAreaColor, $InfoAlert));


			// Save as VTT-File

			$statement = $pdo->prepare("SELECT * FROM test WHERE VideoElementId = ? AND Deleted = 0 ORDER BY StartTime");
			$statement->execute(array($VideoElementId));

			$ergebnis = "WEBVTT\n\n";
			while ($row = $statement->fetch()) {


				$startTime = $row['StartTime'];

				$StopTime = $row['StopTime'];

//    UserID, StartTime, StopTime, AmpelColor, AddedComment   ;
				//Fixme: VideoElementId
				//$VideoElementId=$row['VideoElementId'];
				$UserID = $row['UserID'];
				$VideoElementId = $row['VideoElementId'];
				$MarkedArea = $row['MarkedArea'];
//				if ($row['Deleted'] == 1) {
//					$AddedComment = 'gelöscht';
//				} else {
				$AddedComment = $row['AddedComment'];
//				}


				switch ($row['AmpelColor']) {
					case "ff0000":
						$AmpelColor = $row['AmpelColor'] . " " . " Rot negativ";
						break;
					case "00ff00":
						$AmpelColor = $row['AmpelColor'] . " " . " Grün positiv";
						break;
					case "ffffff":
						$AmpelColor = $row['AmpelColor'] . " " . "Weiß neutral";
						break;
				}

				$ergebnis .= gmdate("H:i:s.v", $startTime) . "-->" . gmdate("H:i:s.v", $StopTime) . " color " . $row['AmpelColor'] .
					"\n("
					. $AmpelColor . " " . $AddedComment . ") \n\n";

//    vttCue = new VTTCue(startTime, endTime, text);
			}
//			$pdo = null;


			$file = '../Resources/Videos/Video_vtt/' . $arrivedData['KursID'] . '/' . $VideoElementId . '.vtt';

			file_put_contents($file, $ergebnis) or die("Unable to open file!");

			break;

		case 'RetweetInput':


			$statement2 = $pdo->prepare("Update test SET AddedComment= :AddedComment WHERE  id=:id");
			$statement2->execute(array('AddedComment' => $AddedComment, 'id' => $arrivedData['Comment_DB_ID']));


			// Save as VTT-File

			$statement = $pdo->prepare("SELECT * FROM test WHERE VideoElementId = ? ORDER BY StartTime");
			$statement->execute(array($VideoElementId));

			$ergebnis = "WEBVTT\n\n";
			while ($row = $statement->fetch()) {


				$startTime = $row['StartTime'];

				$StopTime = $row['StopTime'];

//    UserID, StartTime, StopTime, AmpelColor, AddedComment   ;
				//Fixme: VideoElementId
				//$VideoElementId=$row['VideoElementId'];
				$UserID = $row['UserID'];
				$VideoElementId = $row['VideoElementId'];
				$MarkedArea = $row['MarkedArea'];
				if ($row['Deleted'] == 1) {
					$AddedComment = 'gelöscht';
				} else {
					$AddedComment = $row['AddedComment'];
				}


				switch ($row['AmpelColor']) {
					case "ff0000":
						$AmpelColor = $row['AmpelColor'] . " " . " Rot negativ";
						break;
					case "00ff00":
						$AmpelColor = $row['AmpelColor'] . " " . " Grün positiv";
						break;
					case "ffffff":
						$AmpelColor = $row['AmpelColor'] . " " . " Weiß neutral";
						break;
				}

				$ergebnis .= gmdate("H:i:s.v", $startTime) . "-->" . gmdate("H:i:s.v", $StopTime) . " color " . $row['AmpelColor'] .
					"\n("
					. $AmpelColor . " " . $AddedComment . ") \n\n";

//    vttCue = new VTTCue(startTime, endTime, text);
			}
//			$pdo = null;


			$file = '../Resources/Videos/Video_vtt/' . $arrivedData['KursID'] . '/' . $VideoElementId . '.vtt';

			file_put_contents($file, $ergebnis) or die("Unable to open file!");

			break;

		case 'EditInput':


			$statement2 = $pdo->prepare("Update test SET UserID= :UserID, KursID= :KursID, UserNickname= :UserNickname, VideoElementId= :VideoElementId, StartTime= :StartTime, StopTime= :StopTime, AmpelColor= :AmpelColor, AddedComment= :AddedComment, EditedCommentsHistory= :EditedCommentsHistory, VideoWidth= :VideoWidth, VideoHeight= :VideoHeight, MarkedArea= :MarkedArea, MarkedAreaColor= :MarkedAreaColor, InfoAlert= :InfoAlert, Deleted = :Deleted WHERE  id=:id");
			$statement2->execute(array('UserID' => $UserID, 'KursID' => $arrivedData['KursID'], 'UserNickname' => $UserNickname, 'VideoElementId' => $VideoElementId, 'StartTime' => $StartTime, 'StopTime' => $StopTime, 'AmpelColor' => $AmpelColor, 'AddedComment' => $AddedComment, 'EditedCommentsHistory' => $EditedCommentHistory, 'VideoWidth' => $VideoWidth, 'VideoHeight' => $VideoHeight, 'MarkedArea' => $MarkedArea, 'MarkedAreaColor' => $MarkedAreaColor, 'InfoAlert' => $InfoAlert, 'Deleted' => $arrivedData['DeletedComment'], 'id' => $arrivedData['Comment_DB_ID']));


			// Save as VTT-File

			$statement = $pdo->prepare("SELECT * FROM test WHERE VideoElementId = ?  AND Deleted = 0 ORDER BY StartTime");
			$statement->execute(array($VideoElementId));

			$ergebnis = "WEBVTT\n\n";
			while ($row = $statement->fetch()) {


				$startTime = $row['StartTime'];

				$StopTime = $row['StopTime'];

//    UserID, StartTime, StopTime, AmpelColor, AddedComment   ;
				//Fixme: VideoElementId
				//$VideoElementId=$row['VideoElementId'];
				$UserID = $row['UserID'];
				$VideoElementId = $row['VideoElementId'];
				$MarkedArea = $row['MarkedArea'];
//				if ($row['Deleted'] == 1) {
//					$AddedComment = 'gelöscht';
//				} else {
				$AddedComment = $row['AddedComment'];
//				}


				switch ($row['AmpelColor']) {
					case "ff0000":
						$AmpelColor = $row['AmpelColor'] . " " . " Rot negativ";
						break;
					case "00ff00":
						$AmpelColor = $row['AmpelColor'] . " " . " Grün positiv";
						break;
					case "ffffff":
						$AmpelColor = $row['AmpelColor'] . " " . " Weiß neutral";
						break;
				}

				$ergebnis .= gmdate("H:i:s.v", $startTime) . "-->" . gmdate("H:i:s.v", $StopTime) . " color " . $row['AmpelColor'] .
					"\n("
					. $AmpelColor . " " . $AddedComment . ") \n\n";

//    vttCue = new VTTCue(startTime, endTime, text);
			}
//			$pdo = null;


			$file = '../Resources/Videos/Video_vtt/' . $arrivedData['KursID'] . '/' . $VideoElementId . '.vtt';

			file_put_contents($file, $ergebnis) or die("Unable to open file!");

			break;

		case 'DeleteInput':


			$statement3 = $pdo->prepare("Update test SET Deleted=:Deleted WHERE  id=:id");
			$statement3->execute(array('Deleted' => $arrivedData['DeletedComment'], 'id' => $arrivedData['Comment_DB_ID']));


			// Save as VTT-File

			$statement = $pdo->prepare("SELECT * FROM test WHERE VideoElementId = ? ORDER BY StartTime");
			$statement->execute(array($VideoElementId));

			$ergebnis = "WEBVTT\n\n";
			while ($row = $statement->fetch()) {


				$startTime = $row['StartTime'];

				$StopTime = $row['StopTime'];

//    UserID, StartTime, StopTime, AmpelColor, AddedComment   ;
				//Fixme: VideoElementId
				//$VideoElementId=$row['VideoElementId'];
				$UserID = $row['UserID'];
				$VideoElementId = $row['VideoElementId'];
				$MarkedArea = $row['MarkedArea'];
				if ($row['Deleted'] == 1) {
					$AddedComment = 'gelöscht';
				} else {
					$AddedComment = $row['AddedComment'];
				}


				switch ($row['AmpelColor']) {
					case "ff0000":
						$AmpelColor = $row['AmpelColor'] . " " . " Rot negativ";
						break;
					case "00ff00":
						$AmpelColor = $row['AmpelColor'] . " " . " Grün positiv";
						break;
					case "ffffff":
						$AmpelColor = $row['AmpelColor'] . " " . "Weiß neutral";
						break;
				}

				$ergebnis .= gmdate("H:i:s.v", $startTime) . "-->" . gmdate("H:i:s.v", $StopTime) . " color " . $row['AmpelColor'] .
					"\n("
					. $AmpelColor . " " . $AddedComment . ") \n\n";

//    vttCue = new VTTCue(startTime, endTime, text);
			}
//			$pdo = null;


			$file = '../Resources/Videos/Video_vtt/' . $arrivedData['KursID'] . '/' . $VideoElementId . '.vtt';

			file_put_contents($file, $ergebnis) or die("Unable to open file!");

			break;

		case 'CloseKurs':

			$sendData->KursClosed = $arrivedData["KursID"];

			$statement4 = $pdo->prepare("Update egw_smallpart_courses SET course_closed=:course_closed WHERE course_id=:course_id");
			$statement4->execute(array('course_closed' => true, 'course_id' => $arrivedData["KursID"]));
//			echo '<script>alert(tada)</script>';

			break;

		case 'AddQuestionToVideo':
			$Question = $arrivedData['Question'];
			$KursID = $arrivedData['KursID'];

			$stmtKursVideoQuestion = $pdo->prepare("SELECT * FROM KursVideoQuestion WHERE KursID=? AND VideoElementId=?");
			$stmtKursVideoQuestion->execute(array($KursID, $VideoElementId));
			$stmtKursVideoQuestionResult = $stmtKursVideoQuestion->fetch();

			if ($stmtKursVideoQuestionResult == false) {
				$stmtKursVideoQuestion2 = $pdo->prepare("INSERT INTO KursVideoQuestion (KursID, VideoElementId, Question) VALUES (?, ?, ?)");
				$stmtKursVideoQuestion2->execute(array($KursID, $VideoElementId, $Question));
			} else {
				$stmtKursVideoQuestion2 = $pdo->prepare("UPDATE KursVideoQuestion SET Question = :Question WHERE ID = :ID");
				$stmtKursVideoQuestion2->execute(array('Question' => $Question, 'ID' => $stmtKursVideoQuestionResult['ID']));
			}
			break;

		case 'DeleteVideo':

			$VideoDeleteError = true;

			$UnlinkVideoAndVTTResult = readVideo($VideoListID);

			$VideoFehler = $VideoListID . '<br>Select-DB<br>';


			if ($UnlinkVideoAndVTTResult !== false) {
				$VideoFehler .= 'Select-DB okay<br>';
				$VideoFehler .= "../" . $UnlinkVideoAndVTTResult['VideoSrc'] . '<br>';

				if (unlink("../" . $UnlinkVideoAndVTTResult['VideoSrc'])) {
					$VideoDeleteError = false;
					$VideoFehler .= 'Video<br>';
				}


				if (!$VideoDeleteError && unlink("../Resources/Videos/Video_vtt/" . $UnlinkVideoAndVTTResult['KursID'] . "/" . $UnlinkVideoAndVTTResult['VideoElementId'] . ".vtt")) {
					$VideoDeleteError = false;
					$VideoFehler .= 'VTT<br>';

				}


				if (!$VideoDeleteError) {
					$statementDelete = $pdo->prepare("DELETE FROM egw_smallpart_videos WHERE video_id=:video_id");
					$resultDelete = $statementDelete->execute(array('video_id' => $VideoListID));


					if ($resultDelete) {
						$VideoDeleteError = false;
						$VideoFehler .= 'DB<br>';
					}
				}

			}

			if (!$VideoDeleteError) {
				$sendData->DeleteStatus = 'Video gelöscht';
			} else {
				$sendData->DeleteStatus = $VideoFehler . '<h3> Es gab ein Fehler!</h3><h3> <a href="http://www.fdbio-tukl.de/index.php?id=1017" target="_blank"><u><b>Kontakt</b></u></a> unter Angabe dieser Daten anschreiben: </h3>	 <h4> <ul> <li> Kursname </li> <li> KursID </li> <li> Videoname </li> <li> VideoID </li> </ul>	 </h4>';

			}
			break;

	}


//// Need

	$sendData->VideoDivParent = 'VideoDivParent';
	$sendData->Controlbutton = 'Mid_right';
	$sendData->VideoDiv = 'VideoDiv' . $arrivedData['VideoElementId'] . $arrivedData['KursID'];
	$sendData->VideoElementId = $arrivedData['VideoElementId'];


//*/
	$pdo = null;
	$sendData->Perf = number_format((microtime(true) - $beforetime), 4);

	$ajax->send($sendData);
	$sendData = null;