<?php
/**
 * EGroupware - Setup
 * https://www.egroupware.org
 * Created by eTemplates DB-Tools written by ralfbecker@outdoor-training.de
 *
 * @license http://opensource.org/licenses/gpl-license.php GPL - GNU General Public License
 * @package smallpart
 * @subpackage setup
 */

use EGroupware\Api;

function smallpart_upgrade0_0()
{
	// migrate account-data
	$defaultgroup = $GLOBALS['egw_setup']->add_account('Default', 'Default', 'Group', false, false);
	$GLOBALS['egw_setup']->db->query("INSERT INTO egw_accounts (account_id, account_lid, account_pwd, account_lastpwd_change,".
		" account_status, account_expires, account_type, account_primary_group)".
		" SELECT id, email, CONCAT('{crypt}', REPLACE(passwort, '$2y$', '$2a$')), UNIX_TIMESTAMP(created_at),".
		" 'A', -1, 'u', $defaultgroup FROM users", __LINE__, __FILE__);

	// group-memberships and acl
	$GLOBALS['egw_setup']->db->query("INSERT INTO egw_acl (acl_appname, acl_location, acl_account, acl_rights)".
		" SELECT 'phpgw_group', '$defaultgroup', id, 1 FROM users", __LINE__, __FILE__);
	$admingroup = $GLOBALS['egw_setup']->add_account('Admins', 'Admins', 'Group', false, false);
	$GLOBALS['egw_setup']->db->query("INSERT INTO egw_acl (acl_appname, acl_location, acl_account, acl_rights)".
		" SELECT 'phpgw_group', '$admingroup', id, 1 FROM users WHERE Superadmin=1", __LINE__, __FILE__);
	$GLOBALS['egw_setup']->db->query("INSERT INTO egw_acl (acl_appname, acl_location, acl_account, acl_rights)".
		" SELECT 'smallpart', 'admin', id, 1 FROM users WHERE userrole='Admin'", __LINE__, __FILE__);

	// contact-data
	$GLOBALS['egw_setup']->db->query("INSERT INTO egw_addressbook (contact_tid, account_id, contact_owner, contact_private,".
		" n_given, n_family, n_fn, n_fileas, contact_email, org_name, contact_created, contact_creator, contact_modified, contact_modifier)".
		" SELECT 'n', id, 0, 0, vorname, nachname, CONCAT(vorname, ' ', nachname), CONCAT(Organisation, ': ', vorname, ' ', nachname),".
		" email, Organisation, UNIX_TIMESTAMP(created_at), 0, UNIX_TIMESTAMP(updated_at), 0 FROM users",
		__LINE__, __FILE__);
	$install_id = $GLOBALS['egw_setup']->db->query("SELECT config_value FROM egw_config WHERE config_name='install_id' AND config_app='phpgwapi'",
		__LINE__, __FILE__)->fetchColumn() ?: md5(microtime(true));
	$GLOBALS['egw_setup']->db->query("UPDATE egw_addressbook".
		" SET contact_uid=CONCAT('addressbook-', CAST(contact_id AS CHAR), '-$install_id'),".
		" carddav_name=CONCAT(CAST(contact_id AS CHAR), '.vcf')".
		" WHERE contact_uid IS NULL", __LINE__, __FILE__);

	$GLOBALS['egw_setup']->oProc->DropTable('users');
	$GLOBALS['egw_setup']->oProc->DropTable('loged');
	$GLOBALS['egw_setup']->oProc->DropTable('securitytokens');

	return $GLOBALS['setup_info']['smallpart']['currentver'] = '0.1';
}

function smallpart_upgrade0_1()
{
	$GLOBALS['egw_setup']->oProc->RenameTable('VideoList', 'egw_smallpart_videos');

	$GLOBALS['egw_setup']->oProc->RefreshTable('egw_smallpart_videos',array(
		'fd' => array(
			'video_id' => array('type' => 'auto','nullable' => False),
			'course_id' => array('type' => 'int','precision' => '4','nullable' => False),
			'video_name' => array('type' => 'varchar','precision' => '255','nullable' => False),
			'video_date' => array('type' => 'timestamp','nullable' => False,'default' => 'current_timestamp')
		),
		'pk' => array('video_id'),
		'fk' => array(),
		'ix' => array('course_id'),
		'uc' => array()
	), [
		'video_id' => 'VideoListID',
		'course_id' => 'KursID',
		'video_name' => 'VideoNameType',
	]);

	return $GLOBALS['setup_info']['smallpart']['currentver'] = '0.2';
}

function smallpart_upgrade0_2()
{
	$GLOBALS['egw_setup']->oProc->RenameTable('KurseUndTeilnehmer', 'egw_smallpart_participants');

	$GLOBALS['egw_setup']->oProc->RefreshTable('egw_smallpart_participants',array(
		'fd' => array(
			'course_id' => array('type' => 'int','precision' => '4','nullable' => False),
			'account_id' => array('type' => 'int','meta' => 'user','precision' => '4','nullable' => False)
		),
		'pk' => array('course_id','account_id'),
		'fk' => array(),
		'ix' => array('account_id'),
		'uc' => array()
	), [
		'course_id' => 'KursID',
		'account_id' => 'UserID',
	]);

	return $GLOBALS['setup_info']['smallpart']['currentver'] = '0.3';
}

function smallpart_upgrade0_3()
{
	$GLOBALS['egw_setup']->oProc->RenameTable('Kurse', 'egw_smallpart_courses');

	$GLOBALS['egw_setup']->oProc->RefreshTable('egw_smallpart_courses', array(
		'fd' => array(
			'course_id' => array('type' => 'auto', 'nullable' => False),
			'course_name' => array('type' => 'varchar', 'precision' => '255', 'nullable' => False),
			'course_password' => array('type' => 'ascii', 'precision' => '255'),
			'course_owner' => array('type' => 'int', 'meta' => 'user', 'precision' => '4', 'nullable' => False, 'comment' => 'owner'),
			'course_org' => array('type' => 'varchar', 'precision' => '255'),
			'course_closed' => array('type' => 'int', 'precision' => '1', 'default' => '0')
		),
		'pk' => array('course_id'),
		'fk' => array(),
		'ix' => array('course_org'),
		'uc' => array()
	), [
		'course_id' => 'KursID',
		'course_name' => 'KursName',
		'course_password' => 'KursPasswort',
		'course_owner' => 'KursOwner',
		'course_org' => 'Organisation',
		'course_closed' => 'KursClosed',
	]);

	return $GLOBALS['setup_info']['timesheet']['currentver'] = '0.4';
}

function smallpart_upgrade0_4()
{
	$GLOBALS['egw_setup']->oProc->AddColumn('egw_smallpart_videos','video_question',array(
		'type' => 'varchar',
		'precision' => '2048'
	));

	foreach($GLOBALS['egw_setup']->db->query('SELECT * FROM KursVideoQuestion', __LINE__, __FILE__) as $row)
	{
		$GLOBALS['egw_setup']->db->update('egw_smallpart_videos', [
			'video_question' => $row['Question'],
		], [
			'course_id' => $row['KursID'],
			'video_id' => substr($row['VideoElementId'], 7),
		], __LINE__, __FILE__, 'smallpart');
	}

	$GLOBALS['egw_setup']->oProc->DropTable('KursVideoQuestion');

	return $GLOBALS['setup_info']['smallpart']['currentver'] = '0.5';
}

function smallpart_upgrade0_5()
{
	$GLOBALS['egw_setup']->oProc->RenameTable('LastVideoWorkingOn', 'egw_smallpart_lastvideo');

	$GLOBALS['egw_setup']->oProc->RefreshTable('egw_smallpart_lastvideo',array(
		'fd' => array(
			'account_id' => array('type' => 'int','meta' => 'user','precision' => '4','nullable' => False),
			'last_data' => array('type' => 'varchar','meta' => 'json','precision' => '255','nullable' => False)
		),
		'pk' => array('account_id'),
		'fk' => array(),
		'ix' => array(),
		'uc' => array()
	), [
		'account_id' => 'UserID',
		'last_data' => 'LastVideoWorkingOnData',
	]);

	return $GLOBALS['setup_info']['smallpart']['currentver'] = '0.6';
}

function smallpart_upgrade0_6()
{
	$GLOBALS['egw_setup']->oProc->RenameTable('test', 'egw_smallpart_comments');

	$GLOBALS['egw_setup']->oProc->RefreshTable('egw_smallpart_comments',array(
		'fd' => array(
			'comment_id' => array('type' => 'auto','nullable' => False),
			'course_id' => array('type' => 'int','precision' => '4','nullable' => False),
			'account_id' => array('type' => 'int','meta' => 'user','precision' => '4','nullable' => False),
			'video_id' => array('type' => 'int','precision' => '4','nullable' => False),
			'comment_starttime' => array('type' => 'int','precision' => '4','default' => '0'),
			'comment_stoptime' => array('type' => 'int','precision' => '4','default' => '0'),
			'comment_color' => array('type' => 'ascii','precision' => '6'),
			'comment_deleted' => array('type' => 'int','precision' => '1','default' => '0','nullable' => False),
			'comment_added' => array('type' => 'varchar','meta' => 'json','precision' => '16384','nullable' => False),
			'comment_history' => array('type' => 'text'),
			'comment_relation_to' => array('type' => 'int','precision' => '4'),
			'comment_video_width' => array('type' => 'int','precision' => '2','nullable' => False),
			'comment_video_height' => array('type' => 'int','precision' => '2','nullable' => False),
			'comment_marked_area' => array('type' => 'text','meta' => 'json','nullable' => False),
			'comment_marked_color' => array('type' => 'text','meta' => 'json'),
			'comment_info_alert' => array('type' => 'varchar','precision' => '2048')
		),
		'pk' => array('comment_id'),
		'fk' => array(),
		'ix' => array('course_id','video_id'),
		'uc' => array()
	), [
		'comment_id' => 'ID',
		'course_id' => 'KursID',
		'account_id' => 'UserID',
		'video_id' => 'CAST(SUBSTRING(VideoElementID, 8) AS INTEGER)',
		'comment_starttime' => 'StartTime',
		'comment_stoptime' => 'StopTime',
		'comment_color' => 'AmpelColor',
		'comment_deleted' => 'Deleted',
		'comment_added' => 'AddedComment',
		'comment_history' => 'EditedCommentsHistory',
		'comment_relation_to' => "CASE RelationToID WHEN '' THEN NULL ELSE CAST(RelationToID AS INTEGER) END",
		'comment_video_width' => 'CAST(VideoWidth AS INTEGER)',
		'comment_video_height' => 'CAST(VideoHeight AS INTEGER)',
		'comment_marked_area' => 'MarkedArea',
		'comment_marked_color' => 'MarkedAreaColor',
		'comment_info_alert' => 'InfoAlert',
	]);

	return $GLOBALS['setup_info']['smallpart']['currentver'] = '0.7';
}

function smallpart_upgrade0_7()
{
	$GLOBALS['egw_setup']->oProc->AddColumn('egw_smallpart_videos','video_hash',array(
		'type' => 'ascii',
		'precision' => '64',
		'comment' => 'hash to secure video access'
	));

	$smallpart_video_dir = ($GLOBALS['egw_info']['server']['files_dir'] ?: '/var/lib/egroupware/default/files').'/smallpart/Video';
	$smallpart_vtt_dir = ($GLOBALS['egw_info']['server']['files_dir'] ?: '/var/lib/egroupware/default/files').'/smallpart/Video_vtt';

	foreach($GLOBALS['egw_setup']->db->select('egw_smallpart_videos', '*', false, __LINE__, __FILE__,
		false, '', 'smallpart') as $row)
	{
		if (!file_exists($course_dir=$smallpart_video_dir.'/'.$row['course_id']))
		{
			echo __METHOD__.": Video directory of course #$row[course_id] and therefore video $row[video_name] not found!\n";
			continue;
		}
		// try finding the video
		if (!file_exists($old_video = $course_dir.'/'.$row['video_name']) &&
			!file_exists($old_video = $course_dir.'/'.sha1(pathinfo($row['video_name'], PATHINFO_FILENAME))))
		{
			echo __METHOD__.": Video directory of course #$row[course_id]/$row[video_name] not found!\n";
			continue;
		}

		// generate new name based on a random 64 byte hash
		$hash = Api\Auth::randomstring(64);
		$new_video = $course_dir.'/'.$hash.'.'.pathinfo($row['video_name'], PATHINFO_EXTENSION);

		if (!rename($old_video, $new_video))
		{
			echo __METHOD__.": Can not rename SmalParT video $old_video to $new_video!\n";
			continue;
		}

		// store hash / new name, only if rename succeeded
		$GLOBALS['egw_setup']->db->update('egw_smallpart_videos', [
			'video_hash' => $hash,
		], [
			'video_id' => $row['video_id'],
		], __LINE__, __FILE__, 'smallpart');

		// check if we have a vtt file to rename too
		if (file_exists($vtt_directory = $smallpart_vtt_dir.'/'.$row['course_id']) &&
			file_exists($old_vtt_file = $vtt_directory.'/video__'.$row['video_id'].'.vtt') &&
			!rename($old_vtt_file, $new_vtt_file = $vtt_directory.'/'.$hash.'.vtt'))
		{
			echo __METHOD__.": Can not rename SmalParT vtt-file $old_vtt_file to $new_vtt_file!\n";
		}
	}

	return $GLOBALS['setup_info']['smallpart']['currentver'] = '0.8';
}
