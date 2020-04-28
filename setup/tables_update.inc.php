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
	$GLOBALS['egw_setup']->oProc->RenameTable('KurseUndTeilnehmer', 'egw_smallpart_course_parts');

	$GLOBALS['egw_setup']->oProc->RefreshTable('egw_smallpart_course_parts',array(
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
			'video_id' => substr($row['VideoElementID'], 7),
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
