<?php
/**
 * EGroupware - Setup
 * https://www.egroupware.org
 * Created by eTemplates DB-Tools written by ralfbecker@outdoor-training.de
 *
 * @license https://spdx.org/licenses/AGPL-3.0-or-later.html GNU Affero General Public License v3.0 or later
 * @package smallpart
 * @subpackage setup
 */

use EGroupware\Api;

function smallpart_upgrade0_0()
{
	$defaultgroup = $GLOBALS['egw_setup']->add_account('Default', 'Default', 'Group', false, false);

	// find organisations and create them as groups
	$orgs = [];
	$sql_primary_group = 'CASE';
	foreach($GLOBALS['egw_setup']->db->query("SELECT DISTINCT Organisation FROM users") as $row)
	{
		$orgs[$row['Organisation']] = $GLOBALS['egw_setup']->add_account($row['Organisation'], $row['Organisation'], 'Group', false, false);
		$sql_primary_group .= ' WHEN Organisation='.$GLOBALS['egw_setup']->db->quote($row['Organisation']).' THEN '.(int)$orgs[$row['Organisation']];
	}
	$sql_primary_group .= ' ELSE '.(int)$defaultgroup.' END';

	// migrate account-data
	$GLOBALS['egw_setup']->db->query("INSERT INTO egw_accounts (account_id, account_lid, account_pwd, account_lastpwd_change,".
		" account_status, account_expires, account_type, account_primary_group)".
		" SELECT id, email, CONCAT('{crypt}', REPLACE(passwort, '$2y$', '$2a$')), UNIX_TIMESTAMP(created_at),".
		" 'A', -1, 'u', $sql_primary_group FROM users", __LINE__, __FILE__);

	// group-memberships and acl
	$GLOBALS['egw_setup']->db->query("INSERT INTO egw_acl (acl_appname, acl_location, acl_account, acl_rights)".
		" SELECT 'phpgw_group', $sql_primary_group, id, 1 FROM users", __LINE__, __FILE__);
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

	if (empty($GLOBALS['egw_info']['server']['files_dir']))
	{
		$config = Api\Config::read('phpgwapi');
		$GLOBALS['egw_info']['server']['files_dir'] = $config['files_dir'];
	}
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


function smallpart_upgrade0_8()
{
	$GLOBALS['egw_setup']->oProc->AddColumn('egw_smallpart_comments','comment_marked',array(
		'type' => 'text',
		'meta' => 'json'
	));
	$chunk_size = 100;
	$start = $total = 0;
	do {
		$n = 0;
		foreach($GLOBALS['egw_setup']->db->select('egw_smallpart_comments', '*',
			false, __LINE__, __FILE__, $start, 'ORDER BY comment_id',
			'smallpart', $chunk_size) as $row)
		{
			$marked = [];
			$row['comment_marked_area'] = json_decode($row['comment_marked_area'], true) ?: [];
			$row['comment_marked_color'] = json_decode($row['comment_marked_color'], true) ?: [];
			foreach($row['comment_marked_area'] as $key => $set)
			{
				if ($set)
				{
					$marked[] = [
						// storing x-coordinates as percent (10px of 800px video width)
						'x' => round(($key % 80)*1.25, 2),
						// storing y-coordinates as percent preserving aspect ratio
						'y' => round(intdiv($key, 80)*1.25*$row['comment_video_width']/$row['comment_video_height'], 2),
						'c' => $row['comment_marked_color'][$key],
					];
				}
			}
			if ($marked)
			{
				$GLOBALS['egw_setup']->db->update('egw_smallpart_comments', [
					'comment_marked' => json_encode($marked),
				], [
					'comment_id' => $row['comment_id'],
				], __LINE__, __FILE__, 'smallpart');
			}
			++$start; ++$n;
		}
	}
	while($n && !($start % $chunk_size));

	$GLOBALS['egw_setup']->oProc->DropColumn('egw_smallpart_comments',array(
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
			'comment_related_to' => array('type' => 'int','precision' => '4'),
			'comment_video_height' => array('type' => 'int','precision' => '2','nullable' => False),
			'comment_marked_area' => array('type' => 'text','meta' => 'json','nullable' => False),
			'comment_marked_color' => array('type' => 'text','meta' => 'json'),
			'comment_info_alert' => array('type' => 'varchar','precision' => '2048')
		),
		'pk' => array('comment_id'),
		'fk' => array(),
		'ix' => array('course_id','video_id'),
		'uc' => array()
	),'comment_video_width');
	$GLOBALS['egw_setup']->oProc->DropColumn('egw_smallpart_comments',array(
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
			'comment_related_to' => array('type' => 'int','precision' => '4'),
			'comment_marked_area' => array('type' => 'text','meta' => 'json','nullable' => False),
			'comment_marked_color' => array('type' => 'text','meta' => 'json'),
			'comment_info_alert' => array('type' => 'varchar','precision' => '2048')
		),
		'pk' => array('comment_id'),
		'fk' => array(),
		'ix' => array('course_id','video_id'),
		'uc' => array()
	),'comment_video_height');
	$GLOBALS['egw_setup']->oProc->DropColumn('egw_smallpart_comments',array(
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
			'comment_related_to' => array('type' => 'int','precision' => '4'),
			'comment_marked_color' => array('type' => 'text','meta' => 'json'),
			'comment_info_alert' => array('type' => 'varchar','precision' => '2048')
		),
		'pk' => array('comment_id'),
		'fk' => array(),
		'ix' => array('course_id','video_id'),
		'uc' => array()
	),'comment_marked_area');
	$GLOBALS['egw_setup']->oProc->DropColumn('egw_smallpart_comments',array(
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
			'comment_related_to' => array('type' => 'int','precision' => '4'),
			'comment_info_alert' => array('type' => 'varchar','precision' => '2048')
		),
		'pk' => array('comment_id'),
		'fk' => array(),
		'ix' => array('course_id','video_id'),
		'uc' => array()
	),'comment_marked_color');

	return $GLOBALS['setup_info']['smallpart']['currentver'] = '0.9';
}

function smallpart_upgrade0_9()
{
	// find organisations and create them as groups
	$orgs = [];
	$sql_primary_group = 'CASE';
	foreach($GLOBALS['egw_setup']->db->query("SELECT DISTINCT course_org FROM egw_smallpart_courses") as $row)
	{
		$orgs[$row['course_org']] = $GLOBALS['egw_setup']->add_account($row['course_org'], $row['course_org'], 'Group', false, false);

		$sql_primary_group .= ' WHEN course_org='.$GLOBALS['egw_setup']->db->quote($row['course_org'])." THEN '".(int)$orgs[$row['course_org']]."'";
	}
	$sql_primary_group .= " ELSE '0' END";

	$GLOBALS['egw_setup']->db->query("UPDATE egw_smallpart_courses SET course_org=$sql_primary_group", __LINE__, __FILE__);

	$GLOBALS['egw_setup']->oProc->AlterColumn('egw_smallpart_courses','course_org',array(
		'type' => 'int',
		'meta' => 'group',
		'precision' => '4'
	));

	// hash passwords, if not configured to be stored cleartext
	$config = Api\Config::read('smallpart');
	if ($config['coursepassword'] !== 'cleartext')
	{
		foreach ($GLOBALS['egw_setup']->db->select('egw_smallpart_courses', 'course_id,course_password', false,
			__LINE__, __FILE__, false, '', 'smallpart') as $row)
		{
			$GLOBALS['egw_setup']->db->update('egw_smallpart_courses', [
				'course_password' => password_hash($row['course_password'], PASSWORD_BCRYPT),
			], [
				'course_id' => $row['course_id'],
			], __LINE__, __FILE__, 'smallpart');
		}
	}
	return $GLOBALS['setup_info']['smallpart']['currentver'] = '1.0';
}

function smallpart_upgrade1_0()
{
	$GLOBALS['egw_setup']->oProc->AddColumn('egw_smallpart_videos','video_type',array(
		'type' => 'ascii',
		'precision' => '8',
		'nullable' => False,
		'default' => 'mp4',
		'comment' => 'mime-sub-type: mp4 or webm'
	));
	$GLOBALS['egw_setup']->oProc->AddColumn('egw_smallpart_videos','video_url',array(
		'type' => 'ascii',
		'precision' => '255',
		'comment' => 'external video URL'
	));
	$GLOBALS['egw_setup']->oProc->AddColumn('egw_smallpart_videos','video_options',array(
		'type' => 'int',
		'precision' => '1',
		'default' => '0',
		'comment' => 'comment display options'
	));

	// split off sub-type / extension from video_name and fill video_type
	$GLOBALS['egw_setup']->db->query("UPDATE egw_smallpart_videos SET video_type='mp4',video_name=REVERSE(SUBSTRING(REVERSE(video_name), 5)) WHERE video_name LIKE '%.mp4'", __LINE__, __FILE__);
	$GLOBALS['egw_setup']->db->query("UPDATE egw_smallpart_videos SET video_type='webm',video_name=REVERSE(SUBSTRING(REVERSE(video_name), 6)) WHERE video_name LIKE '%.webm'", __LINE__, __FILE__);

	return $GLOBALS['setup_info']['smallpart']['currentver'] = '1.1';
}


function smallpart_upgrade1_1()
{
	$GLOBALS['egw_setup']->oProc->AddColumn('egw_smallpart_courses','course_secret',array(
		'type' => 'ascii',
		'precision' => '64',
		'comment' => 'oauth secret for lti v1.0'
	));

	return $GLOBALS['setup_info']['smallpart']['currentver'] = '1.2';
}


function smallpart_upgrade1_2()
{
	$GLOBALS['egw_setup']->oProc->CreateTable('egw_smallpart_watched',array(
		'fd' => array(
			'watch_id' => array('type' => 'auto','nullable' => False),
			'course_id' => array('type' => 'int','precision' => '4'),
			'video_id' => array('type' => 'int','precision' => '4'),
			'account_id' => array('type' => 'int','meta' => 'account','precision' => '4'),
			'watch_starttime' => array('type' => 'timestamp','nullable' => False,'comment' => 'start-time'),
			'watch_endtime' => array('type' => 'timestamp','nullable' => False,'comment' => 'end-time'),
			'watch_position' => array('type' => 'int','precision' => '4','default' => '0','comment' => 'start-position in video in seconds'),
			'watch_duration' => array('type' => 'int','precision' => '4','comment' => 'duration of watched video in seconds'),
			'watch_paused' => array('type' => 'int','precision' => '2','default' => '0','comment' => 'number of times paused')
		),
		'pk' => array('watch_id'),
		'fk' => array(),
		'ix' => array(array('video_id','account_id'), 'watch_starttime'),
		'uc' => array()
	));

	return $GLOBALS['setup_info']['smallpart']['currentver'] = '1.3';
}

function smallpart_upgrade1_3()
{
	$GLOBALS['egw_setup']->oProc->AddColumn('egw_smallpart_courses','course_options',array(
		'type' => 'int',
		'precision' => '1',
		'default' => '0',
		'comment' => '1=record watched videos'
	));

	return $GLOBALS['setup_info']['smallpart']['currentver'] = '1.3.001';
}

function smallpart_upgrade1_3_001()
{
	$GLOBALS['egw_setup']->oProc->CreateTable('egw_smallpart_overlay',array(
		'fd' => array(
			'overlay_id' => array('type' => 'auto','nullable' => False),
			'video_id' => array('type' => 'int','precision' => '4','nullable' => False),
			'course_id' => array('type' => 'int','precision' => '4','nullable' => False),
			'overlay_type' => array('type' => 'ascii','precision' => '32','nullable' => False,'comment' => 'type / classname of overlay-element'),
			'overlay_start' => array('type' => 'int','precision' => '4','nullable' => False,'comment' => 'start-time'),
			'overlay_player_mode' => array('type' => 'int','precision' => '1','nullable' => False,'default' => '0','comment' => 'disable player controls, etc'),
			'overlay_duration' => array('type' => 'int','precision' => '4'),
			'overlay_data' => array('type' => 'text','meta' => 'json','comment' => 'json serialized data')
		),
		'pk' => array('overlay_id'),
		'fk' => array(),
		'ix' => array('video_id'),
		'uc' => array()
	));

	return $GLOBALS['setup_info']['smallpart']['currentver'] = '1.4.002';
}

function smallpart_upgrade1_4_001()
{
	$GLOBALS['egw_setup']->oProc->AddColumn('egw_smallpart_overlay','overlay_duration',array(
		'type' => 'int',
		'precision' => '4'
	));

	return $GLOBALS['setup_info']['smallpart']['currentver'] = '1.4.002';
}

function smallpart_upgrade1_4_002()
{
	$GLOBALS['egw_setup']->oProc->AddColumn('egw_smallpart_comments','comment_updated',array(
		'type' => 'timestamp',
		'nullable' => False,
		'default' => 'current_timestamp'
	));
	$GLOBALS['egw_setup']->oProc->AddColumn('egw_smallpart_comments','comment_created',array(
		'type' => 'timestamp'
	));

	return $GLOBALS['setup_info']['smallpart']['currentver'] = '1.4.003';
}

function smallpart_upgrade1_4_003()
{
	$GLOBALS['egw_setup']->oProc->CreateIndex('egw_smallpart_overlay',array('course_id','video_id','overlay_start'));

	return $GLOBALS['setup_info']['smallpart']['currentver'] = '1.4.004';
}

function smallpart_upgrade1_4_004()
{
	$GLOBALS['egw_setup']->oProc->CreateTable('egw_smallpart_answers',array(
		'fd' => array(
			'answer_id' => array('type' => 'auto','nullable' => False),
			'overlay_id' => array('type' => 'int','precision' => '4','nullable' => False),
			'video_id' => array('type' => 'int','precision' => '4','nullable' => False),
			'course_id' => array('type' => 'int','precision' => '4','nullable' => False),
			'account_id' => array('type' => 'int','meta' => 'user','precision' => '4','nullable' => False),
			'answer_data' => array('type' => 'varchar','meta' => 'json','precision' => '8192','nullable' => False),
			'answer_score' => array('type' => 'float','precision' => '4'),
			'answer_modified' => array('type' => 'timestamp','default' => 'current_timestamp'),
			'answer_modifier' => array('type' => 'int','meta' => 'user','precision' => '4'),
			'answer_created' => array('type' => 'timestamp','nullable' => False)
		),
		'pk' => array('answer_id'),
		'fk' => array(),
		'ix' => array('answer_id','overlay_id','video_id','account_id',array('course_id','video_id','account_id')),
		'uc' => array()
	));

	return $GLOBALS['setup_info']['smallpart']['currentver'] = '1.4.005';
}


function smallpart_upgrade1_4_005()
{
	$GLOBALS['egw_setup']->oProc->AlterColumn('egw_smallpart_overlay','overlay_type',array(
		'type' => 'ascii',
		'precision' => '64',
		'nullable' => False,
		'comment' => 'type / classname of overlay-element'
	));

	return $GLOBALS['setup_info']['smallpart']['currentver'] = '1.4.006';
}


function smallpart_upgrade1_4_006()
{
	$GLOBALS['egw_setup']->oProc->AddColumn('egw_smallpart_videos','video_published',array(
		'type' => 'int',
		'precision' => '1',
		'nullable' => False,
		'default' => '1',
		'comment' => '0=draft, 1=published, 2=unavailable, 3=readonly/scored'
	));
	$GLOBALS['egw_setup']->oProc->AddColumn('egw_smallpart_videos','video_published_start',array(
		'type' => 'timestamp',
		'comment' => 'draft before'
	));
	$GLOBALS['egw_setup']->oProc->AddColumn('egw_smallpart_videos','video_published_end',array(
		'type' => 'timestamp',
		'comment' => 'unavailable after'
	));
	$GLOBALS['egw_setup']->oProc->AddColumn('egw_smallpart_videos','video_test_duration',array(
		'type' => 'int',
		'precision' => '2',
		'comment' => 'in minutes'
	));
	$GLOBALS['egw_setup']->oProc->AddColumn('egw_smallpart_videos','video_test_options',array(
		'type' => 'int',
		'precision' => '1',
		'nullable' => False,
		'default' => '0',
		'comment' => '&1=allow pause, &2=forbid seek'
	));
	$GLOBALS['egw_setup']->oProc->AddColumn('egw_smallpart_videos','video_test_display',array(
		'type' => 'int',
		'precision' => '1',
		'nullable' => False,
		'default' => '0',
		'comment' => '0=instead of comments, 1=dialog, 2=on video'
	));

	return $GLOBALS['setup_info']['smallpart']['currentver'] = '1.4.007';
}


function smallpart_upgrade1_4_007()
{
	$GLOBALS['egw_setup']->oProc->RenameColumn('egw_smallpart_overlay','overlay_player_mode','overlay_question_mode');
	$GLOBALS['egw_setup']->oProc->AlterColumn('egw_smallpart_overlay','overlay_question_mode',array(
		'type' => 'int',
		'precision' => '1',
		'nullable' => False,
		'default' => '0',
		'comment' => '!&1=allow skip, &1=required, &2=timed (overlay_duration), &4=view again'
	));

	return $GLOBALS['setup_info']['smallpart']['currentver'] = '1.4.008';
}

/**
 * Bump version to 21.1
 *
 * @return string
 */
function smallpart_upgrade1_4_008()
{
	return $GLOBALS['setup_info']['smallpart']['currentver'] = '21.1';
}

function smallpart_upgrade21_1()
{
	$GLOBALS['egw_setup']->oProc->AddColumn('egw_smallpart_participants','participant_role',array(
		'type' => 'int',
		'precision' => '1',
		'nullable' => False,
		'default' => '0',
		'comment' => '&1=read, &2=edit&delete, &4=lock'
	));
	$GLOBALS['egw_setup']->oProc->AddColumn('egw_smallpart_participants','participant_group',array(
		'type' => 'int',
		'precision' => '1'
	));
	$GLOBALS['egw_setup']->oProc->AddColumn('egw_smallpart_participants','participant_alias',array(
		'type' => 'varchar',
		'precision' => '20'
	));
	$GLOBALS['egw_setup']->oProc->AddColumn('egw_smallpart_participants','participant_subscribed',array(
		'type' => 'timestamp'
	));
	$GLOBALS['egw_setup']->oProc->AddColumn('egw_smallpart_participants','participant_unsubscribed',array(
		'type' => 'timestamp'
	));

	return $GLOBALS['setup_info']['smallpart']['currentver'] = '21.1.001';
}

function smallpart_upgrade21_1_001()
{
	$GLOBALS['egw_setup']->oProc->AddColumn('egw_smallpart_courses','course_groups',array(
		'type' => 'int',
		'precision' => '1',
		'comment' => '>0 number of groups, <0 group-size'
	));

	$GLOBALS['egw_setup']->oProc->AlterColumn('egw_smallpart_participants','participant_group',array(
		'type' => 'int',
		'precision' => '1'
	));

	return $GLOBALS['setup_info']['smallpart']['currentver'] = '21.1.002';
}

function smallpart_upgrade21_1_002()
{
	$GLOBALS['egw_setup']->oProc->CreateTable('egw_smallpart_clmeasurements',array(
		'fd' => array(
			'cl_id' => array('type' => 'auto','nullable' => False),
			'course_id' => array('type' => 'int','precision' => '4','nullable' => False),
			'video_id' => array('type' => 'int','precision' => '4','nullable' => False),
			'account_id' => array('type' => 'int','meta' => 'user','precision' => '4','nullable' => False),
			'cl_timestamp' => array('type' => 'timestamp','nullable' => False,'default' => 'current_timestamp'),
			'cl_type' => array('type' => 'ascii','precision' => '16','comment' => 'type of measurement/data'),
			'cl_data' => array('type' => 'varchar','meta' => 'json','precision' => '16384','comment' => 'json serialized data')
		),
		'pk' => array('cl_id'),
		'fk' => array(),
		'ix' => array('course_id',array('video_id','account_id','cl_timestamp')),
		'uc' => array()
	));

	return $GLOBALS['setup_info']['smallpart']['currentver'] = '21.1.003';
}

function smallpart_upgrade21_1_003()
{
	$GLOBALS['egw_setup']->oProc->CreateTable('egw_smallpart_clmeasurements_config',array(
		'fd' => array(
			'course_id' => array('type' => 'int','precision' => '4','nullable' => False),
			'config_data' => array('type' => 'varchar','meta' => 'json','precision' => '16384','comment' => 'json serialized data')
		),
		'pk' => array('config_id'),
		'fk' => array(),
		'ix' => array('course_id'),
		'uc' => array()
	));

	return $GLOBALS['setup_info']['smallpart']['currentver'] = '21.1.004';
}

function smallpart_upgrade21_1_004()
{
	$GLOBALS['egw_setup']->oProc->AddColumn('egw_smallpart_courses','course_info',array(
		'type' => 'varchar',
		'precision' => '8192'
	));
	$GLOBALS['egw_setup']->oProc->AddColumn('egw_smallpart_courses','course_disclaimer',array(
		'type' => 'varchar',
		'precision' => '4096'
	));

	return $GLOBALS['setup_info']['smallpart']['currentver'] = '21.1.005';
}

/**
 * Bump version to 23.1
 *
 * @return string
 */
function smallpart_upgrade21_1_005()
{
	return $GLOBALS['setup_info']['smallpart']['currentver'] = '23.1';
}

function smallpart_upgrade23_1()
{
	$GLOBALS['egw_setup']->oProc->CreateTable('egw_smallpart_livefeedback',array(
		'fd' => array(
			'lf_id' => array('type' => 'auto','nullable' => False),
			'course_id' => array('type' => 'int','precision' => '4','nullable' => False),
			'video_id' => array('type' => 'int','precision' => '4','nullable' => False),
			'session_created' => array('type' => 'timestamp'),
			'session_starttime' => array('type' => 'timestamp'),
			'session_endtime' => array('type' => 'timestamp')
		),
		'pk' => array('lf_id'),
		'fk' => array(),
		'ix' => array('course_id', 'video_id'),
		'uc' => array()
	));

	return $GLOBALS['setup_info']['smallpart']['currentver'] = '23.1.001';
}

function smallpart_upgrade23_1_001()
{
	$GLOBALS['egw_setup']->oProc->AddColumn('egw_smallpart_comments', 'comment_cat', array(
		'type' => 'varchar',
		'precision' => '2048'
	));
	return $GLOBALS['setup_info']['smallpart']['currentver'] = '23.1.002';
}

function smallpart_upgrade23_1_002()
{
	$GLOBALS['egw_setup']->oProc->CreateTable('egw_smallpart_categories',array(
		'fd' => array(
			'cat_id' => array('type' => 'auto','nullable' => False),
			'course_id' => array('type' => 'int','precision' => '4','nullable' => False),
			'parent_id' => array('type' => 'int','precision' => '4'),
			'cat_name' => array('type' => 'varchar','precision' => '256'),
			'cat_description' => array('type' => 'varchar','precision' => '256'),
			'cat_color' =>array('type' => 'varchar','precision' => '7'),
			'cat_data' => array('type' => 'varchar','meta' => 'json','precision' => '16384','comment' => 'json serialized data'),
		),
		'pk' => array('cat_id'),
		'fk' => array(),
		'ix' => array('course_id'),
		'uc' => array()
	));

	return $GLOBALS['setup_info']['smallpart']['currentver'] = '23.1.003';
}

function smallpart_upgrade23_1_003()
{
	$GLOBALS['egw_setup']->oProc->AddColumn('egw_smallpart_livefeedback','session_interval',array(
		'type' => 'int',
		'precision' => '4',
		'comment' => 'time interval period for feedback action activation'
	));
	return $GLOBALS['setup_info']['smallpart']['currentver'] = '23.1.004';
}

function smallpart_upgrade23_1_004()
{
	$GLOBALS['egw_setup']->oProc->AddColumn('egw_smallpart_courses', 'allow_neutral_lf_categories', array(
		'type'      => 'int',
		'precision' => '1',
		'default'   => '0'
	));
	return $GLOBALS['setup_info']['smallpart']['currentver'] = '23.1.005';
}

function smallpart_upgrade23_1_005()
{
	$GLOBALS['egw_setup']->oProc->AlterColumn('egw_smallpart_videos','video_question',array(
		'type' => 'varchar',
		'precision' => '4096'
	));
	$GLOBALS['egw_setup']->oProc->AddColumn('egw_smallpart_videos','video_limit_access',array(
		'type' => 'ascii',
		'meta' => 'user-commasep',
		'precision' => '1024',
		'comment' => 'default: all course-participants'
	));

	return $GLOBALS['setup_info']['smallpart']['currentver'] = '23.1.006';
}

function smallpart_upgrade23_1_006()
{
	$GLOBALS['egw_setup']->oProc->AddColumn('egw_smallpart_participants','participant_agreed',array(
		'type' => 'timestamp',
		'comment' => 'participant agreed to disclaimer'
	));

	// set all current participants as agreed to currently existing disclaimers
	$GLOBALS['egw_setup']->db->query('UPDATE egw_smallpart_participants SET participant_agreed = COALESCE(participant_subscribed, NOW())'.
		' WHERE participant_agreed IS NULL AND course_id IN (SELECT course_id FROM egw_smallpart_courses WHERE course_disclaimer IS NOT NULL)',
		__LINE__, __FILE__);

	return $GLOBALS['setup_info']['smallpart']['currentver'] = '23.1.007';
}

function smallpart_upgrade23_1_007()
{
	$GLOBALS['egw_setup']->oProc->AddColumn('egw_smallpart_courses', 'export_columns', array(
		'type'      => 'ascii',
		'precision' => '255'
	));

	return $GLOBALS['setup_info']['smallpart']['currentver'] = '23.1.008';
}

function smallpart_upgrade23_1_008()
{
	$GLOBALS['egw_setup']->oProc->AddColumn('egw_smallpart_livefeedback', 'host', array(
		'type'      => 'int',
		'precision' => '4',
	));

	return $GLOBALS['setup_info']['smallpart']['currentver'] = '23.1.009';
}

function smallpart_upgrade23_1_009()
{
	$GLOBALS['egw_setup']->oProc->AddColumn('egw_smallpart_videos', 'video_published_prerequisite', array(
		'type'      => 'ascii',
		'precision' => '1024',
	));

	return $GLOBALS['setup_info']['smallpart']['currentver'] = '23.1.010';
}

function smallpart_upgrade23_1_010()
{
	$GLOBALS['egw_setup']->oProc->AddColumn('egw_smallpart_courses','course_parent',array(
		'type' => 'int',
		'precision' => '4',
		'comment' => 'id of parent-course'
	));

	return $GLOBALS['setup_info']['smallpart']['currentver'] = '23.1.011';
}

function smallpart_upgrade23_1_011()
{
	$GLOBALS['egw_setup']->oProc->AddColumn('egw_smallpart_lastvideo','course_id',array(
		'type' => 'int',
		'precision' => '4',
		'nullable' => False,
		'default' => '0'
	));
	$GLOBALS['egw_setup']->oProc->AddColumn('egw_smallpart_lastvideo','video_id',array(
		'type' => 'int',
		'precision' => '4',
		'nullable' => False,
		'default' => '0'
	));
	$GLOBALS['egw_setup']->oProc->AddColumn('egw_smallpart_lastvideo','position',array(
		'type' => 'int',
		'precision' => '4',
	));
	$GLOBALS['egw_setup']->oProc->AddColumn('egw_smallpart_lastvideo','last_updated',array(
		'type' => 'timestamp',
		'default' => 'current_timestamp'
	));
	// copy json-blob into columns
	try {
		$GLOBALS['egw_setup']->db->query("UPDATE egw_smallpart_lastvideo SET ".
			" course_id=CASE JSON_VALUE(last_data, '$.course_id') WHEN 'manage' THEN 0 ELSE JSON_VALUE(last_data, '$.course_id') END,".
										 "video_id=COALESCE(JSON_VALUE(last_data, '$.video_id'), 0), position=JSON_VALUE(last_data, '$.position')",
			__LINE__, __FILE__);
	}
	catch(\Exception $e) {
		foreach($GLOBALS['egw_setup']->db->select('egw_smallpart_lastvideo','*',false, __LINE__, __FILE__, false, '', 'smallpart') as $row)
		{
			$row += json_decode($row['last_data'], true);
			if ($row['course_id'] === 'manage') $row['course_id'] = 0;
			if (!isset($row['video_id'])) $row['video_id'] = 0;
			$GLOBALS['egw_setup']->db->insert('egw_smallpart_lastvideo', $row, [
				'account_id' => $row['account_id'],
			], __LINE__, __FILE__, 'smallpart');
		}
	}

	return $GLOBALS['setup_info']['smallpart']['currentver'] = '23.1.012';
}

function smallpart_upgrade23_1_012()
{
	$GLOBALS['egw_setup']->oProc->RefreshTable('egw_smallpart_lastvideo',array(
		'fd' => array(
			'account_id' => array('type' => 'int','meta' => 'user','precision' => '4','nullable' => False),
			'course_id' => array('type' => 'int','precision' => '4','nullable' => False,'default' => '0'),
			'video_id' => array('type' => 'int','precision' => '4','nullable' => False,'default' => '0'),
			'position' => array('type' => 'int','precision' => '4'),
			'last_updated' => array('type' => 'timestamp','default' => 'current_timestamp')
		),
		'pk' => array('account_id','course_id','video_id'),
		'fk' => array(),
		'ix' => array(),
		'uc' => array()
	));
	return $GLOBALS['setup_info']['smallpart']['currentver'] = '23.1.013';
}

function smallpart_upgrade23_1_013()
{
	$GLOBALS['egw_setup']->oProc->AddColumn(
		'egw_smallpart_courses', 'notify_participants',
		array(
			'type'      => 'int',
			'precision' => '1',
			'default'   => '0',
			'comment'   => 'notify on new comments'
		)
	);
	$GLOBALS['egw_setup']->oProc->AddColumn(
		'egw_smallpart_participants', 'notify',
		array(
			'type'      => 'int',
			'precision' => '1',
			'default'   => '0',
			'comment'   => 'notify on new comments'
		)
	);
	return $GLOBALS['setup_info']['smallpart']['currentver'] = '23.1.014';
}

function smallpart_upgrade23_1_014()
{
	$GLOBALS['egw_setup']->oProc->AddColumn('egw_smallpart_courses', 'student_uploads', array(
		'type' => 'int', 'precision' => '1', 'default' => '0'
	));
	$GLOBALS['egw_setup']->oProc->AddColumn('egw_smallpart_videos', 'owner', array(
		'type'      => 'int',
		'meta'      => 'user',
		'precision' => '4',
		'nullable'  => true
	));
	return $GLOBALS['setup_info']['smallpart']['currentver'] = '23.1.015';
}

function smallpart_upgrade23_1_015()
{
	$GLOBALS['egw_setup']->oProc->CreateTable('egw_smallpart_extra',array(
		'fd' => array(
			'course_id' => array('type' => 'int','precision' => '4','nullable' => False),
			'video_id' => array('type' => 'int','precision' => '4','nullable' => False,'default'=>0,'comment' => '0=course defaults'),
			'extra_name' => array('type' => 'varchar','precision' => '64','nullable' => False),
			'extra_value' => array('type' => 'varchar','precision' => '16384','nullable' => False),
			'extra_id' => array('type' => 'auto','nullable' => False)
		),
		'pk' => array('extra_id'),
		'fk' => array(),
		'ix' => array(),
		'uc' => array(array('course_id','video_id','extra_name'))
	));

	return $GLOBALS['setup_info']['smallpart']['currentver'] = '23.1.016';
}

/**
 * Bump version to 26.1
 *
 * @return string
 */
function smallpart_upgrade23_1_016()
{
	return $GLOBALS['setup_info']['smallpart']['currentver'] = '26.1';
}