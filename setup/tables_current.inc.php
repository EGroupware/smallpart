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
			'course_options' => array('type' => 'int','precision' => '1','default' => '0','comment' => '1=record watched videos'),
			'course_groups' => array('type' => 'int','precision' => '1','comment' => '>0 number of groups, <0 group-size'),
			'course_info' => array('type' => 'varchar','precision' => '8192'),
			'course_disclaimer' => array('type' => 'varchar','precision' => '4096'),
			'allow_neutral_lf_categories' => array('type' => 'int','precision' => '1','default' => '0')
		),
		'pk' => array('course_id'),
		'fk' => array(),
		'ix' => array('course_org'),
		'uc' => array()
	),
	'egw_smallpart_participants' => array(
		'fd' => array(
			'course_id' => array('type' => 'int','precision' => '4','nullable' => False),
			'account_id' => array('type' => 'int','meta' => 'user','precision' => '4','nullable' => False),
			'participant_role' => array('type' => 'int','precision' => '1','nullable' => False,'default' => '0','comment' => '&1=read, &2=edit&delete, &4=lock'),
			'participant_group' => array('type' => 'int','precision' => '1'),
			'participant_alias' => array('type' => 'varchar','precision' => '20'),
			'participant_subscribed' => array('type' => 'timestamp'),
			'participant_unsubscribed' => array('type' => 'timestamp')
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
			'video_question' => array('type' => 'varchar','precision' => '4096'),
			'video_hash' => array('type' => 'ascii','precision' => '64','comment' => 'hash to secure video access'),
			'video_url' => array('type' => 'ascii','precision' => '255','comment' => 'external video URL'),
			'video_type' => array('type' => 'ascii','precision' => '8','nullable' => False,'default' => 'mp4','comment' => 'mime-sub-type: mp4 or webm'),
			'video_options' => array('type' => 'int','precision' => '1','default' => '0','comment' => 'comment display options'),
			'video_published' => array('type' => 'int','precision' => '1','nullable' => False,'default' => '1','comment' => '0=draft, 1=published, 2=unavailable, 3=readonly/scored'),
			'video_published_start' => array('type' => 'timestamp','comment' => 'draft before'),
			'video_published_end' => array('type' => 'timestamp','comment' => 'unavailable after'),
			'video_test_duration' => array('type' => 'int','precision' => '2','comment' => 'in minutes'),
			'video_test_options' => array('type' => 'int','precision' => '1','nullable' => False,'default' => '0','comment' => '&1=allow pause, &2=forbid seek'),
			'video_test_display' => array('type' => 'int','precision' => '1','nullable' => False,'default' => '0','comment' => '0=instead of comments, 1=dialog, 2=on video'),
			'video_limit_access' => array('type' => 'ascii','meta' => 'user-commasep','precision' => '1024','comment' => 'default: all course-participants')
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
			'comment_created' => array('type' => 'timestamp'),
			'comment_cat' => array('type' => 'varchar','precision' => '2048')
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
			'overlay_type' => array('type' => 'ascii','precision' => '64','nullable' => False,'comment' => 'type / classname of overlay-element'),
			'overlay_start' => array('type' => 'int','precision' => '4','nullable' => False,'comment' => 'start-time'),
			'overlay_question_mode' => array('type' => 'int','precision' => '1','nullable' => False,'default' => '0','comment' => '!&1=allow skip, &1=required, &2=timed (overlay_duration), &4=view again'),
			'overlay_duration' => array('type' => 'int','precision' => '4'),
			'overlay_data' => array('type' => 'text','meta' => 'json','comment' => 'json serialized data')
		),
		'pk' => array('overlay_id'),
		'fk' => array(),
		'ix' => array('video_id',array('course_id','video_id','overlay_start')),
		'uc' => array()
	),
	'egw_smallpart_answers' => array(
		'fd' => array(
			'answer_id' => array('type' => 'auto','nullable' => False),
			'overlay_id' => array('type' => 'int','precision' => '4','nullable' => False),
			'video_id' => array('type' => 'int','precision' => '4','nullable' => False),
			'course_id' => array('type' => 'int','precision' => '4','nullable' => False),
			'account_id' => array('type' => 'int','meta' => 'user','precision' => '4','nullable' => False),
			'answer_data' => array('type' => 'varchar','meta' => 'json','precision' => '8192','nullable' => False),
			'answer_score' => array('type' => 'float','precision' => '4'),
			'answer_created' => array('type' => 'timestamp','nullable' => False),
			'answer_modified' => array('type' => 'timestamp','default' => 'current_timestamp'),
			'answer_modifier' => array('type' => 'int','meta' => 'user','precision' => '4')
		),
		'pk' => array('answer_id'),
		'fk' => array(),
		'ix' => array('answer_id','overlay_id','video_id','account_id',array('course_id','video_id','account_id')),
		'uc' => array()
	),
	'egw_smallpart_clmeasurements' => array(
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
	),
	'egw_smallpart_clmeasurements_config' => array(
		'fd' => array(
			'course_id' => array('type' => 'int','precision' => '4','nullable' => False),
			'config_data' => array('type' => 'varchar','meta' => 'json','precision' => '16384','comment' => 'json serialized data')
		),
		'pk' => array('course_id'),
		'fk' => array(),
		'ix' => array('course_id'),
		'uc' => array()
	),
	'egw_smallpart_livefeedback' => array(
		'fd' => array(
			'lf_id' => array('type' => 'auto','nullable' => False),
			'course_id' => array('type' => 'int','precision' => '4','nullable' => False),
			'video_id' => array('type' => 'int','precision' => '4','nullable' => False),
			'session_created' => array('type' => 'timestamp'),
			'session_starttime' => array('type' => 'timestamp'),
			'session_endtime' => array('type' => 'timestamp'),
			'session_interval' => array('type' => 'int','precision' => '4')
		),
		'pk' => array('lf_id'),
		'fk' => array(),
		'ix' => array('course_id','video_id'),
		'uc' => array()
	),
	'egw_smallpart_categories' => array(
		'fd' => array(
			'cat_id' => array('type' => 'auto','nullable' => False),
			'course_id' => array('type' => 'int','precision' => '4','nullable' => False),
			'parent_id' => array('type' => 'int','precision' => '4'),
			'cat_name' => array('type' => 'varchar','precision' => '256'),
			'cat_description' => array('type' => 'varchar','precision' => '256'),
			'cat_color' => array('type' => 'varchar','precision' => '7'),
			'cat_data' => array('type' => 'varchar','meta' => 'json','precision' => '16384','comment' => 'json serialized data')
		),
		'pk' => array('cat_id'),
		'fk' => array(),
		'ix' => array('course_id'),
		'uc' => array()
	)
);
