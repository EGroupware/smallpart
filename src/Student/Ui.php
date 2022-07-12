<?php
/**
 * EGroupware - SmallParT - Student Ui
 *
 * @link https://www.egroupware.org
 * @package smallpart
 * @subpackage Ui
 * @license https://spdx.org/licenses/AGPL-3.0-or-later.html GNU Affero General Public License v3.0 or later
 */

namespace EGroupware\SmallParT\Student;

use EGroupware\Api;
use EGroupware\Api\Etemplate;
use EGroupware\Api\Header\ContentSecurityPolicy;
use EGroupware\SmallParT;
use EGroupware\SmallParT\Bo;
use EGroupware\SmallParT\Export;

class Ui
{
	public $public_functions = [
		'start' => true,
		'index' => true
	];

	/**
	 * Startpage of course
	 *
	 * no course selected via course_id GET parameter or content[courses] --> show manage courses
	 *
	 * @param ?array $content
	 * @return void
	 * @throws Api\Exception\AssertionFailed
	 * @throws Api\Exception\NoPermission
	 * @throws Api\Exception\WrongParameter
	 */
	public function start(array $content=null)
	{
		$bo = new Bo($GLOBALS['egw_info']['user']['account_id']);
		$last = $bo->lastVideo();
		if (!empty($content['courses'] ?? $_GET['course_id'] ?? $last['course_id']))
		{
			$course = $bo->read($content['courses'] ?? $_GET['course_id'] ?? $last['course_id'], false, true, false);
		}
		if (!isset($content) || isset($course) && $content['last_course_id'] != $course['course_id'])
		{
			if (!empty($course))
			{
				$content = array_intersect_key($course, array_flip(['course_id', 'course_name', 'course_info', 'course_disclaimer']));
			}
			else
			{
				// no course selected via course_id GET parameter or content[courses] --> show manage courses
				return (new SmallParT\Courses())->index();
			}
		}
		elseif (isset($content['button']))
		{
			$button = key($content['button']);
			unset($content['button']);

			try {
				switch ($button)
				{
					case 'subscribe':
						if (!empty($course['course_disclaimer']) && empty($content['confirm']))
						{
							Etemplate::set_validation_error('confirm', lang('You need to confirm the disclaimer!'));
							break;
						}
						$bo->subscribe($content['courses'], true, null, $content['password']);
						Api\Framework::message(lang('You are now subscribed to the course.'), 'success');
						break;

					case 'unsubscribe':
						$bo->subscribe($content['courses'], false);
						Api\Framework::message(lang('You have been unsubscribed from the course.'), 'success');
						break;
				}
				// re-read course to update participants
				$course = $bo->read($course['course_id'], false, true, false);
			}
			catch(Api\Exception\WrongUserinput $e) {
				Etemplate::set_validation_error('password', $e->getMessage());
			}
			catch(\Exception $e) {
				Api\Framework::message($e->getMessage(), 'error');
			}
		}
		elseif ($content['courses'] === 'manage')
		{
			return (new SmallParT\Courses())->index();
		}
		else
		{
			// Video selected --> show regular student UI
			return $this->index($content);
		}
		// give teaches a hint how to add something to the start-page
		if ($bo->isTutor($course))
		{
			if (empty($content['course_info']))
			{
				$content['course_info'] = lang('Edit course to add information here');
			}
			if (empty($content['course_disclaimer']))
			{
				$content['course_disclaimer'] = "<p>".lang('Disclaimer: need to be confirmed to be able to subscribe').
					"</p>\n<p>".lang('Edit course to add information here')."</p>";
			}
		}
		$content['subscribed'] = $bo->isParticipant($course);
		$content['confirmDisclaimer'] = !$content['subscribed'] && !empty(trim($course['course_info']));
		$content['confirmPassword'] = !$content['subscribed'] && !empty($course['course_password']);
		$content['courses'] = $course['course_id'];
		$content['is_staff'] = $bo->isStaff($course);
		$content['account_id'] = (int)$GLOBALS['egw_info']['user']['account_id'];

		$bo->setLastVideo([
			'course_id' => $course['course_id'],
		]);
		$readonlys = [
			'button[subscribe]' => $content['subscribed'],
			'button[unsubscribe]' => !$content['subscribed'] ||
				$course['course_owner'] == $GLOBALS['egw_info']['user']['account_id'],
			'changenick' => !$content['subscribed'] || $bo->isTutor($course),
		];
		$sel_options = [
			'courses' => $bo->listCourses(false)+[
				'manage' => lang('Manage courses').' ...',
			],
			'videos' => $content['subscribed'] ? array_map(Bo::class.'::videoLabel',
				$course['videos'] ?? $bo->listVideos(['course_id' => $content['courses']], false)) : [],
			'account_id' => array_map(static function($participant) use ($content, $bo)
			{
				return $bo->participantClientside($participant, (bool)$content['is_staff']);
			}, (array)$course['participants']),
		];
		// set standard nickname of current user, if not subscribed
		if (!$content['subscribed'])
		{
			$sel_options['account_id'][] = [
				'value' => $GLOBALS['egw_info']['user']['account_id'],
				'label' => Bo::participantName(['account_id' => $GLOBALS['egw_info']['user']['account_id']], $bo->isTutor($course))
			];
		}

		$tpl = new Etemplate('smallpart.start');
		if (($top_actions = self::_top_tools_actions($bo->isTutor($course))))
		{
			if (!file_get_contents(Api\Vfs::PREFIX."/apps/smallpart/{$content['courses']}/{$content['video']['video_id']}/all/template_note.ods"))
			{
				unset($top_actions['note']);
			}
			$tpl->setElementAttribute('top-tools', 'actions', $top_actions);
		}
		$tpl->exec(Bo::APPNAME.'.'.self::class.'.start', $content, $sel_options, $readonlys, $content+[
			'last_course_id' => $content['courses'],
		]);
	}

	/**
	 * Show student UI with video
	 *
	 * @param $content
	 * @return void
	 * @throws Api\Exception\AssertionFailed
	 * @throws Api\Exception\NoPermission
	 * @throws Api\Exception\NotFound
	 * @throws Api\Exception\WrongParameter
	 */
	public function index($content=null)
	{
		// allow framing by LMS (LTI 1.3 without specifying a course_id shows Courses::index which redirects here for open
		if (($lms = Api\Cache::getSession('smallpart', 'lms_origin')))
		{
			ContentSecurityPolicy::add('frame-ancestors', $lms);
		}
		$theme=$GLOBALS['egw_info']['user']['preferences']['smallpart']['theme'];
		$tpl = new Etemplate( $theme ? 'smallpart.student.index'.'.'.$theme:'smallpart.student.index');
		$sel_options = $readonlys = [];
		$bo = new Bo($GLOBALS['egw_info']['user']['account_id']);
		$last = $bo->lastVideo();
		$now = new Api\DateTime('now');

		// if student has not yet subscribed to a course --> redirect him to course list
		if ($content['courses'] === 'manage' ||
			(!($courses = $bo->listCourses()) || $last && $last['course_id'] === 'manage') && empty($content['courses'] ?? $_GET['course_id'] ?? $last['course_id']))
		{
			$bo->setLastVideo([
				'course_id' => 'manage',
			], $last);
			// output course-management instead of redirect to not having to cope with redirects in LTI
			return (new SmallParT\Courses())->index();
		}
		$courses['manage'] = lang('Manage courses').' ...';

		// if we have a last course and video or _GET[course_id] set --> use it
		if (!isset($content))
		{
			if (!empty($_GET['course_id'] ?? $last['course_id']) && ($course = $bo->read($_GET['course_id'] ?? $last['course_id'], false)))
			{
				$content = array_intersect_key($course, array_flip(['course_id', 'course_name', 'course_info', 'course_disclaimer', 'course_options']));
				$content['courses'] = (int)$course['course_id'];
				if (!empty($_GET['video_id'] ?? $last['video_id']) && ($video = $bo->readVideo($_GET['video_id'] ?? $last['video_id'])) &&
					$video['course_id'] == $course['course_id'] && $bo->isParticipant($course))
				{
					$content['videos'] = (int)$video['video_id'];
				}
				// --> no video selected or not a participant --> go to start-page of course
				else
				{
					return $this->start();
				}
			}
		}
		if (!isset($content))
		{
			$content['courses'] = '';
		}
		else
		{
			// ignore server-side eT2 validation for new videos added on client-side
			// video2 is a hidden input to which smallpartApp.courseSelection adds the value of videos before submitting
			if (empty($content['videos']) && (int)$content['video2'])
			{
				$content['videos'] = (int)$content['video2'];
			}
			$videos = $bo->listVideos(['course_id' => $content['courses']]);
			if (count($videos) > 1 && !empty($content['disable_navigation']))
			{
				unset($content['disable_navigation']);
				$content['disable_course_selection'] = true;
			}
			if (!empty($content['courses']) && (isset($course) && $course['course_id'] == $content['courses']) || ($course = $bo->read($content['courses'], false)))
			{
				if (!$bo->isParticipant($course)) return $this->start(['courses' => $course['course_id']]);
				$sel_options['videos'] = array_map(Bo::class.'::videoLabel', $videos);
				$content['is_staff'] = $bo->isStaff($content['courses']);
				// existing video selected --> show it
				if (!empty($content['videos']) && isset($sel_options['videos'][$content['videos']]))
				{
					$content['video'] = $videos[$content['videos']];
					try {
						if ($content['video']['accessible'] === false &&
							// case video not yet available is handled with a countdown later
							!($content['video']['video_published'] == Bo::VIDEO_PUBLISHED &&
								isset($content['video']['video_published_start']) &&
								$content['video']['video_published_start'] > $now))
						{
							Api\Framework::message($content['video']['error_msg'] ?:
								lang('This video is currently NOT accessible!'), 'error');
							throw new \Exception('Not accessible');	// deselects the listed video again
						}
						$content['comments'] = $content['video'] ? self::_fixComments($bo->listComments($content['videos']), $bo->isTeacher($content['courses'])) : [];
					}
					// can happen when a video got deleted
					catch (\Exception $e) {
						//_egw_log_exception($e);
						unset($content['videos'], $content['video']);
					}
				}
				// no video selected --> go to start page of course
				else
				{
					return $this->start();
				}

				try {
					if (isset($course) && $course['course_id'] == $content['courses'] || ($course = $bo->read($content['courses'])))
					{
						$content['course_options'] = (int)$course['course_options'];
						if (($course['course_options'] & Bo::OPTION_CL_MEASUREMENT) === Bo::OPTION_CL_MEASUREMENT) $content['clm'] = $course['clm'];
					}
					// remember last course and video of user between sessions
					$bo->setLastVideo([
						'course_id' => $content['courses'],
						'video_id' => empty($content['video']) ? '' : $content['videos'],
					], $last);
				}
				// can happen if user got unsubscribed from a course --> show course list
				catch (\Exception $e) {
					$bo->setLastVideo([
						'course_id' => 'manage',
					], $last);
					// output course-management instead of redirect to not having to cope with redirects in LTI
					return (new SmallParT\Courses())->index();
				}
			}
			else
			{
				unset($content['video'], $content['comments']);
			}

			// read attachments
			if (!empty($content['video']) && !empty($content['video']['video_id']))
			{
				$content['video'] = $bo->readVideoAttachments($content['video']);
			}

			// LTI launches disable navigation, if they specify course_id and video_id (or have only one video)
			if ($content['disable_navigation'])
			{

			}

			$preserv = $content;
			unset($preserv['start_test'], $preserv['stop'], $preserv['pause']);	// dont preserv buttons
		}

		// download (filtered) comments of selected video
		// ToDo: nothing from the filter validates :(
		$raw_content = json_decode($_POST['value'],true);
		if (!empty($raw_content['download']))
		{
			$export = new Export($bo);
			$export->downloadComments($content['courses'], $content['videos'], [
				'comment_color' => $raw_content['comment_color_filter'],
				// the active-participant-filter contains array of comma-sep. comment_id(s)
				'comment_id' => $raw_content['activeParticipantsFilter'] ?
					explode(',', implode(',', $raw_content['activeParticipantsFilter'])) : null,
				'search' => $raw_content['comment_search_filter'],
			]);
		}

		$sel_options = array_merge([
			'courses' => $courses,
			'account_id' => array_map(static function($participant) use ($content, $bo)
			{
				return $bo->participantClientside($participant, (bool)$content['is_staff']);
			}, (array)$course['participants']),
			'staff' => [
				Bo::ROLE_TUTOR => lang('Tutor'),
				Bo::ROLE_TEACHER => lang('Teacher'),
				Bo::ROLE_ADMIN => lang('Course-admin'),
			],
			'group' => [
				'sub' => lang('Subscribed Participants'),
				'unsub' => lang('Unsubscribed Participants'),
			]
		], $sel_options);
		// add/prepend groups
		for($g=$course['course_groups']; $g >= 1; --$g)
		{
			array_unshift($sel_options['group'], ['value' => $g, 'label' => lang('Group %1', $g)]);
		}
		$readonlys = [
			'edit_course' => !$content['is_staff'],
			'edit_questions' => !$content['is_staff'],
			'view_scores' => !$content['is_staff'],
		];

		$actions = self::get_actions();
		// none admin user with forbidden option to comment on video
		if ($content['video']['video_options'] == Bo::COMMENTS_FORBIDDEN_BY_STUDENTS && !$content['is_staff'] ||
			$content['video']['accessible'] === 'readonly')
		{
			unset($actions['delete'], $actions['edit'], $actions['retweet'], $actions['add']);
			$readonlys['add_comment'] = true;
		}
		$tpl->setElementAttribute('comments', 'actions', $actions);

		if ($content['video']['video_options'] == Bo::COMMENTS_DISABLED)
		{
			unset($content['comments']);
		}

		// if video is not yet accessible, show a countdown (other cases then not "yet" accessible are handled above)
		if (isset($content['video']) && $content['video']['accessible'] === false)
		{
			$content['locked'] = true;
			$content['countdown'] = $content['video']['video_published_start'];
		}
		// if video is a test with duration, and not yet started (or paused) and start pressed
		if (isset($content['video']) && $content['video']['video_test_duration'] &&
			($content['video']['accessible'] === null || $content['is_staff'] && $content['video']['accessible'] === true) &&
			!empty($content['start_test']))
		{
			$bo->testStart($content['video'], $content['video_time']);
			// re-read video, now we stopped or paused (accessible changed and some data might be hidden)
			$content['video'] = $bo->readVideo($content['video']['video_id']);
			unset($content['locked'], $content['duration']);	// $content['start_test'] is unset below, to be able to handle admin case!
		}
		// if test is running, set timer or stop/pause it
		if (isset($content['video']) && $content['video']['video_test_duration'] &&
			$bo->testRunning($content['video'], $time_left))
		{
			if (!empty($content['stop']) || !empty($content['pause']))
			{
				$bo->testStop($content['video'], !empty($content['stop']), $content['video_time']);
				if (!empty($content['stop']))
				{
					$bo->setLastVideo([
						'course_id' => $content['courses'],
						'video_id'  => $content['videos']='',
					], $last);
					unset($content['video']);
				}
				else
				{
					// re-read video, now we paused (accessible changed and some data might be hidden)
					$content['video'] = $bo->readVideo($content['video']['video_id']);
				}
				unset($content['stop'], $content['pause'], $content['timer']);
			}
			else
			{
				$content['timer'] = new Api\DateTime("+$time_left seconds");
				// overall time-frame has precedence over individual time left
				if (isset($content['video']['video_published_end']) && $content['timer'] > $content['video']['video_published_end'])
				{
					$content['timer'] = $content['video']['video_published_end'];
				}
				$readonlys['pause'] = !($content['video']['video_test_options'] & Bo::TEST_OPTION_ALLOW_PAUSE);
			}
		}
		// video has a limited publishing time --> show timer, but no pause or stop button
		elseif (isset($content['video']) && $content['video']['accessible'] && !empty($content['video']['video_published_end']) &&
			$content['video']['video_published_end'] > new Api\DateTime('now'))
		{
			$content['timer'] = $content['video']['video_published_end'];
			$readonlys['pause'] = $readonlys['stop'] = true;
			$content['timerNoButtonClass'] = 'timerBoxNoButton';
		}
		else
		{
			unset($content['timer']);
		}
		// if video is a test with duration, and not yet started (or paused)
		if (isset($content['video']) && $content['video']['video_test_duration'] && empty($content['start_test']) &&
			($content['video']['accessible'] === null ||
				$content['is_staff'] && $content['video']['accessible'] === true && $time_left > 0))
		{
			$content['locked'] = true;
			$content['duration'] = $content['video']['video_test_duration'];
			$content['time_left'] = $time_left / 60.0;
			// disable confirmation, if test is pausable
			if ($content['video']['video_test_options'] & Bo::TEST_OPTION_ALLOW_PAUSE)
			{
				$tpl->setElementAttribute('start_test', 'onclick', 'this.form.submit();');
			}
		}
		unset($content['start_test']);

		// if we recorded a video postion, then restore the video position
		if (isset($content['video']) && empty($content['video_time']) && $content['video']['video_id'] == $last['video_id'])
		{
			$content['video_time'] = $last['position'];
		}

		if (($top_actions = self::_top_tools_actions(!empty($content['is_staff']))))
		{
			if (!file_get_contents(Api\Vfs::PREFIX."/apps/smallpart/{$content['courses']}/{$content['video']['video_id']}/all/template_note.ods"))
			{
				unset($top_actions['note']);
			}
			$tpl->setElementAttribute('top-tools', 'actions', $top_actions);
		}
		else
		{
			$tpl->setElementAttribute('top-tools', 'disabled', true);
		}
		$tpl->setElementAttribute('filter-toolbar', 'actions', self::_filter_toolbar_actions());
		// need to set image upload url for uploading images directly into smallpart app location
		// html_editor_upload will carry image upload path for vfs of html-editor overlay.
		$content['html_editor_upload'] = '/apps/smallpart/'.$content['video']['course_id'].'/'.$content['video']['video_id'];

		// send account_lid, so it can be used in path for task attachments
		if (!empty($content['video']))
		{
			$content['video']['seekable'] = ($content['is_staff'] || !($content['video']['video_test_options'] & Bo::TEST_OPTION_FORBID_SEEK));
			$content['video']['account_lid'] = $GLOBALS['egw_info']['user']['account_lid'];
		}

		//error_log(Api\DateTime::to('H:i:s: ').__METHOD__."() video_id=$content[videos], time_left=$time_left, timer=".($content['timer']?$content['timer']->format('H:i:s'):'').", video=".json_encode($content['video']));
		$tpl->exec(Bo::APPNAME.'.'.self::class.'.index', $content, $sel_options, $readonlys, $preserv);
	}

	private static function _filter_toolbar_actions()
	{
		return [
			'date' => [
				'caption' => 'Date filter',
				'icon' => 'date',
				'default' => true,
				'group' => 1,
				'onExecute' => 'javaScript:app.smallpart.student_filter_tools_actions',
				'checkbox' => true,
				'hint' => 'Enables date range filter'
			],
			'searchall' => [
				'caption' => 'Search all',
				'icon' => 'search',
				'default' => true,
				'group' => 1,
				'onExecute' => 'javaScript:app.smallpart.student_filter_tools_actions',
				'checkbox' => true,
				'hint' => 'Enables search in all content option for including all content while searching via filter search box.'
			],
			'mouseover' => [
				'caption' => 'Pause on mouseover',
				'icon' => 'pause_on_hover',
				'default' => true,
				'group' => 1,
				'onExecute' => 'javaScript:app.smallpart.student_filter_tools_actions',
				'checkbox' => true,
				'hint' => 'Video gets paused on mouseover comments area'
			],
			'download' => [
				'caption' => 'Download',
				'icon' => 'download',
				'group' => 1,
				'onExecute' => "javaScript:app.smallpart.student_filter_tools_actions",
				'hint' => 'Download comments of this video as CSV file'
			],
			'pauseaftersubmit' => [
				'caption' => 'Pause after submit',
				'icon' => 'not-started',
				'onExecute' => 'javaScript:app.smallpart.student_filter_tools_actions',
				'checkbox' => true,
				'group' => 1,
				'hint' => 'Video gets paused after submitting (save/cancel) a comment',
			],
			'attachments' => [
				'caption' => 'attachments',
				'icon' => 'attach',
				'onExecute' => 'javaScript:app.smallpart.student_filter_tools_actions',
				'checkbox' => true,
				'group' => 1,
				'hint' => 'Show only comments with attachments',
			],
			'marked' => [
				'caption' => 'Marking',
				'icon' => 'apps', //@todo: marking needs an actual icon similar to glyphicon-film
				'onExecute' => 'javaScript:app.smallpart.student_filter_tools_actions',
				'checkbox' => true,
				'group' => 1,
				'hint' => 'Show only comments with marking',
			],
		];
	}
	private static function _top_tools_actions(bool $is_staff)
	{
		return array_filter([
			'course' => [
				'caption' => 'Edit Course',
				'icon' => 'edit',
				'default' => true,
				'onExecute' => 'javaScript:app.smallpart.student_top_tools_actions',
				'toolbarDefault' => true,
				'staff' => true,    // only display to staff
			],
			'question' => [
				'caption' => 'Edit Questions',
				'icon' => 'edit',
				'onExecute' => 'javaScript:app.smallpart.student_top_tools_actions',
				'staff' => true,
			],
			'score' => [
				'caption' => 'View Scores',
				'icon' => 'view',
				'onExecute' => 'javaScript:app.smallpart.student_top_tools_actions',
				'staff' => true,
			],
			'nickname' => [
				'caption' => 'Change nickname',
				'icon' => 'api/user',
				'onExecute' => 'javaScript:app.smallpart.changeNickname',
				'staff' => false,   // never display to staff (as it's not used for them!)
			],
			'note' => [
				'caption' => 'Add note',
				'icon' => 'note',
				'onExecute' => 'javaScript:app.smallpart.student_top_tools_actions',
			],
		], static function(array $entry) use ($is_staff)
		{
			return !isset($entry['staff']) || $entry['staff'] === $is_staff;
		});
	}

	/**
	 * Change nickname of current user
	 *
	 * @param int $course_id
	 * @param string $nickname
	 */
	public static function ajax_changeNickname(int $course_id, string $nickname)
	{
		$response = Api\Json\Response::get();
		try {
			$response->message(lang("You're now displayed as '%1' to your fellow students.",
				(new Bo())->changeNickname($course_id, $nickname)));

			$response->call('app.smallpart.changeNicknameStartpage', [[
				'value' => $GLOBALS['egw_info']['user']['account_id'],
				'label' => $nickname,
			]]);
		}
		catch(\Exception $e) {
			$response->message($e->getMessage());
			$response->call('app.smallpart.changeNickname');
		}
	}

	/**
	 * Function to create note file for given filename and extension
	 *
	 * @param string $course_id file extension
	 * @param string $video_id directory
	 *
	 */
	public static function ajax_createNote ($course_id, $video_id)
	{
		$response = Api\Json\Response::get();
		$data = array ();

		$user = $GLOBALS['egw_info']['user']['account_id'];
		$bo = new SmallParT\Bo($user);
		// check video is accessible eg. not draft for students
		if (!($video = $bo->readVideo($video_id)) || !$bo->videoAccessible($video))
		{
			$response->data(array(
				'message' => lang ('Failed to create the file! Because you do not have enough permission to this video!')
			));
			return false;
		}

		$base_dir = "/apps/smallpart/$course_id/$video_id/";
		if (!Api\Vfs::is_dir($base_dir))
		{
			Api\Vfs::$is_root=true;
			Api\Vfs::mkdir($base_dir, 0700, true);
			Api\Vfs::$is_root=false;
		}


		$dir = $base_dir.$GLOBALS['egw_info']['user']['account_lid'].'/notes/';

		$file = $dir.'note.ods';

		if (!Api\Vfs::is_dir($dir))
		{
			Api\Vfs::mkdir($dir, null, true);
		}

		$template = file_get_contents(Api\Vfs::PREFIX.$base_dir.'all/template_note.ods');

		if (Api\Vfs::file_exists($file))
		{
			$data['path'] = $file;
		}
		elseif(!$template)
		{
			$data['message'] = lang('Failed to create file %1, because the %2 is missing!',$file, $base_dir.'all/template_note.ods');
		}
		elseif (!($fp = Api\Vfs::fopen($file,'wb')) || !fwrite($fp, $template ?? ' '))
		{
			$data['message'] = lang('Failed to create file %1!',$file);
		}
		else
		{
			$data['message'] = lang('File %1 has been created successfully.', $file);
			$data['path'] = $file;
		}
		if ($fp) fclose($fp);
		$response->data($data);
	}

	/**
	 * Save a comment via ajax
	 *
	 * @param string $exec_id exec-id for CSRF check, as we modify data
	 * @param array $comment
	 * @param array $where =[] optional filter for returned comments
	 * @throws Api\Json\Exception
	 */
	public static function ajax_saveComment(string $exec_id, array $comment, array $where=[])
	{
		// CSRF check (redirects, if failed)
		Etemplate\Request::read($exec_id);

		$response = Api\Json\Response::get();
		try {
			$bo = new Bo();
			$comment_id = $bo->saveComment($comment);
			if ($comment_id)
			{
				$bo->save_comment_attachments($comment['course_id'], $comment['video_id'], $comment_id);
			}
			if (Api\Json\Push::onlyFallback())
			{
				$response->call('app.smallpart.student_updateComments', [
					'content' => self::_fixComments($bo->listComments($comment['video_id'], $where),
						$bo->isTeacher($comment['course_id'])),
				]);
			}
			$response->message(lang('Comment saved.'), 'success');
		}
		catch (\Exception $e) {
			$response->message($e->getMessage(), 'error');
		}
	}

	/**
	 * Delete a comment via ajax
	 *
	 * @param string $exec_id exec-id for CSRF check, as we modify data
	 * @param int $comment_id
	 * @param array $where filter for reload of comments
	 * @throws Api\Json\Exception
	 */
	public static function ajax_deleteComment(string $exec_id, $comment_id, array $where)
	{
		// CSRF check (redirects, if failed)
		Etemplate\Request::read($exec_id);

		$response = Api\Json\Response::get();
		try {
			$bo = new Bo();
			$bo->deleteComment($comment_id);
			$response->call('app.smallpart.student_updateComments', [
				'content' => self::_fixComments($bo->listComments($where['video_id'], $where), $where),
			]);
			$response->message(lang('Comment deleted.'), 'success');
		}
		catch (\Exception $e) {
			$response->message($e->getMessage(), 'error');
		}
	}

	/**
	 * List videos of a course
	 *
	 * @param int $course_id
	 * @throws Api\Json\Exception
	 */
	public static function ajax_listVideos($course_id)
	{
		$response = Api\Json\Response::get();
		try {
			$bo = new Bo();
			$response->data(array_values($bo->listVideos(['course_id' => $course_id])));
		}
		catch (\Exception $e) {
			$response->message($e->getMessage(), 'error');
		}
	}

	/**
	 * Get (filtered) comments
	 *
	 * @param array $where values for keys video_id and others
	 * @throws Api\Json\Exception
	 */
	public static function ajax_listComments(array $where=[])
	{
		$response = Api\Json\Response::get();
		try {
			$bo = new Bo();
			if (empty($where['comment_color'])) unset($where['comment_color']);
			$response->call('app.smallpart.student_updateComments', [
				'content' => self::_fixComments($bo->listComments($where['video_id'], $where),
					$bo->isTeacher($where)),
			]);
		}
		catch (\Exception $e) {
			$response->message($e->getMessage(), 'error');
		}
	}

	public static function get_actions()
	{
		return [
			'open' => [
				'caption' => 'Open',
				'icon' => 'view',
				'default' => true,
				'onExecute' => 'javaScript:app.smallpart.student_openComment',
				'group' => $group=1,
				'singleClick' => true
			],
			'add' => [
				'caption' => 'Add',
				'icon' => 'add',
				'onExecute' => 'javaScript:app.smallpart.student_addComment',
				'group' => $group,
			],
			'edit' => [
				'caption' => 'Edit',
				'icon' => 'edit',
				'onExecute' => 'javaScript:app.smallpart.student_openComment',
				'enableClass' => 'commentOwner',
				'group' => ++$group,
			],
			'retweet' => [
				'caption' => 'Retweet',
				//'icon' => 'retweet',
				'onExecute' => 'javaScript:app.smallpart.student_openComment',
				'group' => $group,
			],
			'delete' => [
				'caption' => 'Delete',
				'icon' => 'delete',
				'onExecute' => 'javaScript:app.smallpart.student_deleteComment',
				'enableClass' => 'commentOwner',
				'group' => ++$group,
			],
		];
	}

	/**
	 * fix comments data
	 *
	 * @param array $_comments
	 * @param boolean $is_teacher =false, true: current user is teacher AND should be able to act as the user (edit&delete comments)
	 * @return array
	 */
	private static function _fixComments($_comments, $is_teacher=false)
	{
		foreach ($_comments as &$comment)
		{
			if ($is_teacher || $comment['account_id'] == $GLOBALS['egw_info']['user']['account_id'])
			{
				$comment['class'] = 'commentOwner';
			}
			if (!empty($comment['comment_marked']))
			{
				$comment['class'] .= ' commentMarked';
			}
			$upload_path = '/apps/smallpart/'.(int)$comment['course_id'].'/'.(int)$comment['video_id'].'/'.$comment['account_lid'].'/comments/'.(int)$comment['comment_id'].'/';
			if (!empty($attachments = Etemplate\Widget\Vfs::findAttachments($upload_path)))
			{
				$comment[$upload_path] = $attachments;
				$comment['class'] .= ' commentAttachments';
			}
		}
		// renumber rows: 0, 1, 2, ...
		array_unshift($_comments,[]); // reserve the first row for grid header
		return array_values($_comments);
	}

	/**
	 * Record student watch (part of) a video
	 *
	 * @param array $data values for keys "course_id", "video_id", "starttime", "duration" and optional "watch_id"
	 * @throws Api\Json\Exception
	 */
	public static function ajax_recordWatched(array $data)
	{
		$response = Api\Json\Response::get();
		try {
			$bo = new Bo();
			$response->data($bo->recordWatched($data));
		}
		catch (\Exception $e) {
			$response->message($e->getMessage(), 'error');
		}
	}

	/**
	 * Record student watch (part of) a video
	 *
	 * @param array $data values for keys "course_id", "video_id", "position"
	 * @throws Api\Json\Exception
	 */
	public static function ajax_setLastVideo(array $data)
	{
		$response = Api\Json\Response::get();
		try {
			$bo = new Bo();
			$response->data($bo->setLastVideo($data));
		}
		catch (\Exception $e) {
			$response->message($e->getMessage(), 'error');
		}
	}

	/**
	 * Record a Cognitive Load Measurement
	 *
	 * @param int $course_id
	 * @param int $video_id
	 * @param string $cl_type measurement type
	 * @param array $data measurement data JSON encoded
	 * @throws Api\Exception\WrongParameter
	 */
	public function ajax_recordCLMeasurement(int $course_id, int $video_id, string $cl_type, array $data)
	{
		$response = Api\Json\Response::get();
		try {
			$bo = new Bo();
			$response->data($bo->recordCLMeasurement($course_id, $video_id, $cl_type, $data));
		}
		catch (\Exception $e) {
			$response->message($e->getMessage(), 'error');
		}
	}

	/**
	 * Read Cognitive Load Measurements
	 *
	 * @param int $course_id
	 * @param int $video_id
	 * @param string $cl_type measurement type
	 * @param int|null $account_id account id
	 * @param string $mode=''
	 * @throws Api\Exception\WrongParameter
	 */
	public function ajax_readCLMeasurement(int $course_id, int $video_id, string $cl_type, int $account_id=null, string $mode = '')
	{
		$response = Api\Json\Response::get();
		try {
			$bo = new Bo();
			$response->data($bo->readCLMeasurementRecords($course_id, $video_id, $cl_type, $account_id, $mode ? " AND JSON_VALUE(cl_data, '$[*].mode')=".$GLOBALS['egw']->db->quote($mode):''));
		}
		catch (\Exception $e) {
			$response->message($e->getMessage(), 'error');
		}
	}
}