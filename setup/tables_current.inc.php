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
			'course_org' => array('type' => 'int','meta' => 'group','precision' => '4'),
			'course_closed' => array('type' => 'int','precision' => '1','default' => '0'),
			'course_secret' => array('type' => 'ascii','precision' => '64','comment' => 'oauth secret for lti v1.0'),
			'course_options' => array('type' => 'int','precision' => '1','default' => '0','comment' => '1=record watched videos')
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
			'video_name' => array('type' => 'varchar','precision' => '255','nullable' => False,'comment' => 'video or lesson name'),
			'video_date' => array('type' => 'timestamp','nullable' => False,'default' => 'current_timestamp'),
			'video_question' => array('type' => 'varchar','precision' => '2048'),
			'video_hash' => array('type' => 'ascii','precision' => '64','comment' => 'hash to secure video access'),
			'video_url' => array('type' => 'ascii','precision' => '255','comment' => 'external video URL'),
			'video_type' => array('type' => 'ascii','precision' => '8','nullable' => False,'default' => 'mp4','comment' => 'mime-sub-type: mp4 or webm'),
			'video_options' => array('type' => 'int','precision' => '1','default' => '0','comment' => 'comment display options')
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
			'comment_marked' => array('type' => 'text','meta' => 'json'),
			'comment_updated' => array('type' => 'timestamp','nullable' => False,'default' => 'current_timestamp'),
			'comment_created' => array('type' => 'timestamp')
		),
		'pk' => array('comment_id'),
		'fk' => array(),
		'ix' => array('course_id','video_id'),
		'uc' => array()
	),
	'egw_smallpart_watched' => array(
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
		'ix' => array(array('video_id','account_id'),'watch_starttime'),
		'uc' => array()
	),
	'egw_smallpart_overlay' => array(
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
	)
);
