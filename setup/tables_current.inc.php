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


$phpgw_baseline = array(
	'egw_smallpart_courses' => array(
		'fd' => array(
			'course_id' => array('type' => 'auto','nullable' => False),
			'course_name' => array('type' => 'varchar','precision' => '255','nullable' => False),
			'course_password' => array('type' => 'ascii','precision' => '255'),
			'course_owner' => array('type' => 'int','meta' => 'user','precision' => '4','nullable' => False,'comment' => 'owner'),
			'course_org' => array('type' => 'varchar','precision' => '255'),
			'course_closed' => array('type' => 'int','precision' => '1','default' => '0')
		),
		'pk' => array('course_id'),
		'fk' => array(),
		'ix' => array('course_org'),
		'uc' => array()
	),
	'egw_smallpart_participants' => array(
		'fd' => array(
			'course_id' => array('type' => 'int','precision' => '4','nullable' => False),
			'account_id' => array('type' => 'int','meta' => 'user','precision' => '4','nullable' => False)
		),
		'pk' => array('course_id','account_id'),
		'fk' => array(),
		'ix' => array('account_id'),
		'uc' => array()
	),
	'egw_smallpart_videos' => array(
		'fd' => array(
			'video_id' => array('type' => 'auto','nullable' => False),
			'course_id' => array('type' => 'int','precision' => '4','nullable' => False),
			'video_name' => array('type' => 'varchar','precision' => '255','nullable' => False),
			'video_date' => array('type' => 'timestamp','nullable' => False,'default' => 'current_timestamp'),
			'video_question' => array('type' => 'varchar','precision' => '2048'),
			'video_hash' => array('type' => 'ascii','precision' => '64','comment' => 'hash to secure video access')
		),
		'pk' => array('video_id'),
		'fk' => array(),
		'ix' => array('course_id'),
		'uc' => array()
	),
	'egw_smallpart_lastvideo' => array(
		'fd' => array(
			'account_id' => array('type' => 'int','meta' => 'user','precision' => '4','nullable' => False),
			'last_data' => array('type' => 'varchar','meta' => 'json','precision' => '255','nullable' => False)
		),
		'pk' => array('account_id'),
		'fk' => array(),
		'ix' => array(),
		'uc' => array()
	),
	'egw_smallpart_comments' => array(
		'fd' => array(
			'comment_id' => array('type' => 'auto','nullable' => False),
			'course_id' => array('type' => 'int','precision' => '4','nullable' => False),
			'account_id' => array('type' => 'int','meta' => 'user','precision' => '4','nullable' => False),
			'video_id' => array('type' => 'int','precision' => '4','nullable' => False),
			'comment_starttime' => array('type' => 'int','precision' => '4','default' => '0'),
			'comment_stoptime' => array('type' => 'int','precision' => '4','default' => '0'),
			'comment_color' => array('type' => 'ascii','precision' => '6'),
			'comment_deleted' => array('type' => 'int','precision' => '1','nullable' => False,'default' => '0'),
			'comment_added' => array('type' => 'varchar','meta' => 'json','precision' => '16384','nullable' => False),
			'comment_history' => array('type' => 'text'),
			'comment_related_to' => array('type' => 'int','precision' => '4'),
			'comment_info_alert' => array('type' => 'varchar','precision' => '2048'),
			'comment_marked' => array('type' => 'text','meta' => 'json')
		),
		'pk' => array('comment_id'),
		'fk' => array(),
		'ix' => array('course_id','video_id'),
		'uc' => array()
	)
);
