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
		$lti = (bool)Api\Cache::getSession('smallpart', 'lms_origin');
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
				$content = array_intersect_key($course, array_flip(['course_id', 'course_name', 'course_info',
																	'course_disclaimer', 'student_uploads']));
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
						$bo->subscribe($content['courses'], true, null,
									   $content['password'] ?? $bo->isParticipant($content), // Password not needed if already subscribed (eg. consent only)
							$bo->isStaff($course, false),   // keep role in case user only agrees to disclaimer
							!empty($course['course_disclaimer']) ? new Api\DateTime('now') : null);
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
			$bo->setLastVideo([
				'course_id' => $content['courses'],
				'video_id' => $content['videos'],
			]);
			return $this->index($content);
		}
		// check subscribed AND agreed to disclaimer
		$content['disable_video_selection'] = !($content['subscribed'] = $bo->isParticipant($course, 0, true));
		$content['confirmDisclaimer'] = !$content['subscribed'] && !empty(trim($course['course_disclaimer']));
		$content['confirmPassword'] = !$content['subscribed'] && !empty($course['course_password']) && !$bo->isParticipant($course);
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
		else
		{
			$content['disable_course_selection'] = $lti;
		}
		$content['courses'] = $course['course_id'];
		$content['is_staff'] = $bo->isStaff($course);
		$content['account_id'] = (int)$GLOBALS['egw_info']['user']['account_id'];
		$participant = array_filter($course['participants'], function ($v) use ($content)
		{
			return $v['account_id'] == $content['account_id'];
		});
		$content['notify'] = ((boolean)current($participant)['notify'] ?? false);
		$content['group'] = current($participant)['participant_group'] ?? '';

		// disable (un)subscribe buttons for LTI, as LTI manages this on the LMS
		$readonlys = [
			'button[subscribe]' => $content['subscribed'] || $lti,
			'button[unsubscribe]' => !$content['subscribed'] || $lti ||
				$course['course_owner'] == $GLOBALS['egw_info']['user']['account_id'],
			'changenick' => !$content['subscribed'] || $bo->isTutor($course),
		];
		$sel_options = [
			'account_id' => array_map(static function($participant) use ($content, $bo)
			{
				return $bo->participantClientside($participant, (bool)$content['is_staff']);
			}, (array)$course['participants']),
			'video_published' => Bo::videoStatusLabels('videoStatus'),
		];
		$content['videos'] = $content['subscribed'] ? array_values(array_map(static function ($video) use (&$sel_options, &$bo)
		{
			// add score-summary to list of videos, if it's a test
			if ($video['video_test_duration'] || $video['video_test_display'] == Bo::TEST_DISPLAY_LIST)
			{
				try {
					$video['summary'] = SmallParT\Overlay::summary($video);
				}
				catch(\Exception $e) {
					// ignore permission denied error for student
				}
			}
			$video['editable'] = $bo->videoEditable($video);
			return $video;
		}, $bo->listVideos(['course_id' => $content['courses']], false))) : [];
		// add current course, if it's not yet subscribed
		if (!empty($course) && !isset($sel_options['courses'][$course['course_id']]))
		{
			$sel_options['courses'][$course['course_id']] = $course['course_name'];
		}
		// Add course preferences
		$this->addPreferencesStart($content, $sel_options);

		// Check for unread messages
		$unread = $bo->materialNewCommentCount($course['course_id'], array_column($content['videos'], 'video_id'));
		foreach($content['videos'] as &$video)
		{
			$video['unreadMessageCount'] = $unread[$video['video_id']] ?? 0;
		}

		$bo->setLastVideo([
			'course_id' => $course['course_id'],
		]);

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

			$tpl->setElementAttribute('top-tools', 'select_options', $top_actions);
			$tpl->setElementAttribute('top-tools', 'actions', $top_actions);
		}
		$tpl->setElementAttribute(
			'add_note', 'hidden',
			!file_get_contents(Api\Vfs::PREFIX . "/apps/smallpart/{$content['courses']}/{$content['video']['video_id']}/all/template_note.ods")
		);
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
		$tpl = new Etemplate( 'smallpart.student.index');
		$sel_options = $readonlys = [];
		$bo = new Bo($GLOBALS['egw_info']['user']['account_id']);
		$last = $bo->lastVideo();
		$now = new Api\DateTime('now');

		// if student has not yet subscribed to a course --> redirect him to course list
		if($content['courses'] === 'manage' || ($last && $last['course_id'] === 'manage') ||
			empty($content['courses'] ?? $_GET['course_id'] ?? $last['course_id']))
		{
			$bo->setLastVideo([
				'course_id' => 'manage',
			], $last);
			// output course-management instead of redirect to not having to cope with redirects in LTI
			return (new SmallParT\Courses())->index();
		}
		$courses = [
			'manage' => lang('Create/Subscribe courses').' ...',
		]+$bo->listCourses();

		// if we have a last course and video or _GET[course_id] set --> use it
		if (!isset($content))
		{
			if (!empty($_GET['course_id'] ?? $last['course_id']) && ($course = $bo->read($_GET['course_id'] ?? $last['course_id'], false)))
			{
				$content = array_intersect_key($course, array_flip([
					'course_id', 'course_name', 'course_info', 'course_disclaimer',
					'course_options', 'allow_neutral_lf_categories', 'config'
				]));
				$content['courses'] = (int)$course['course_id'];
				if (!empty($_GET['video_id'] ?? $last['video_id']) && ($video = $bo->readVideo($_GET['video_id'] ?? $last['video_id'], true)) &&
					$video['course_id'] == $course['course_id'] && $bo->isParticipant($course, 0, true))
				{
					$content['videos'] = (int)$video['video_id'];
				}
				// video from another course, the user is a participant of --> show it
				elseif (!empty($video) && $video['course_id'] != $course['course_id'] &&
					($c = $bo->read($video['course_id'])) && $bo->isParticipant($c, 0, true))
				{
					$content = array_intersect_key($course = $c, array_flip(['course_id', 'course_name', 'course_info',
																			 'course_disclaimer', 'course_options',
																			 'allow_neutral_lf_categories']));
					$content['courses'] = (int)$course['course_id'];
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
			if(!empty($content['video2']) && (int)$content['video2'])
			{
				$content['videos'] = (int)$content['video2'];
			}
			$videos = $bo->listVideos(['course_id' => $content['courses']]);
			if (!empty($content['courses']) && (isset($course) && $course['course_id'] == $content['courses']) ||
				($course = $bo->read($content['courses'], false)))
			{
				// if started via lti/lms we are called with $content !== null, but need these too
				$content += array_intersect_key($course, array_flip([
					'course_id', 'course_name', 'course_info', 'course_disclaimer',
					'course_options', 'allow_neutral_lf_categories',
				]));
				if (!$bo->isParticipant($course))
				{
					return $this->start([
						'courses' => $course['course_id'],
						'disable_course_selection' => $content['disable_course_selection'] ?? false,
					]);
				}
				$sel_options['videos'] = array_map(Bo::class.'::videoLabel', $videos);
				$content['is_staff'] = $bo->isStaff($content['courses']);
				// check for a possible video-target, which is NOT returned by listVideos()
				if (!empty($content['videos']) && !isset($sel_options['videos'][$content['videos']]) &&
					($video = $bo->readVideo($content['videos'])))
				{
					$videos[$content['videos']] = $video;
					$sel_options['videos'][$content['videos']] = Bo::videoLabel($video);
				}
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
					return $this->start(['courses' => $course['course_id']]);
				}

				try {
					if (isset($course) && $course['course_id'] == $content['courses'] || ($course = $bo->read($content['courses'])))
					{
						$content['course_options'] = (int)$course['course_options'];
						$content['allow_neutral_lf_categories'] = $course['allow_neutral_lf_categories'];
						if (($course['course_options'] & Bo::OPTION_CL_MEASUREMENT) === Bo::OPTION_CL_MEASUREMENT) $content['clm'] = $course['clm'];
					}
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
			// Clear any unused upload from last time
			$path = "/apps/smallpart/{$content['course_id']}/{$content['video']['video_id']}/{$GLOBALS['egw_info']['user']['account_lid']}/comments/.new/";
			if(Api\Vfs::file_exists($path))
			{
				Api\Vfs::remove($path);
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
		if(!empty($raw_content['download']) || $content['filter-toolbar']['download'])
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
		// copy customfields from video direct into $content and set them readonly, to allow displaying them as tab
		foreach($video as $name => $value)
		{
			if ($name[0] === '#')
			{
				$content[$name] = $value;
				$readonlys[$name] = true;
			}
		}

		$actions = self::get_actions();
		// none admin user with forbidden option to comment on video
		if ($content['video']['video_options'] == Bo::COMMENTS_FORBIDDEN_BY_STUDENTS && !$content['is_staff'] ||
			$content['video']['accessible'] === 'readonly')
		{
			unset($actions['delete'], $actions['edit'], $actions['retweet'], $actions['add']);
		}
		$tpl->setElementAttribute('comments', 'actions', $actions);

		if ($content['video']['video_options'] == Bo::COMMENTS_DISABLED)
		{
			unset($content['comments']);
		}

		// Add course / user preferences
		$this->addPreferences($content, $bo, $tpl);

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
			$content['video'] = $bo->readVideoAttachments($content['video']);
			$content['comments'] = $content['video'] ? self::_fixComments($bo->listComments($content['videos']), $bo->isTeacher($content['courses'])) : [];
			unset($content['locked'], $content['duration']);	// $content['start_test'] is unset below, to be able to handle admin case!
		}
		// If video has prerequisites, check those
		$missing = [];
		if(isset($content['video']) && $content['video']['video_published'] == Bo::VIDEO_PUBLISHED_PREREQUISITE &&
			$content['video']['video_published_prerequisite'] &&
			!$bo->checkComplete($content['video']['video_published_prerequisite'], $GLOBALS['egw_info']['user']['account_id'], $missing)
		)
		{
			$content['locked'] = true;
			$readonlys['start_test'] = true;
			$missing_labels = "\n" . implode("\n", array_map(function ($missing_id) use ($bo)
				{
					return $bo->videoLabel($bo->readVideo($missing_id));
				}, $missing));
			Api\Framework::message(lang('Prerequisites have not been met') . $missing_labels, 'info');
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
					$content['video'] = $bo->readVideoAttachments($content['video']);
					$content['comments'] = $content['video'] ? self::_fixComments($bo->listComments($content['videos']), $bo->isTeacher($content['courses'])) : [];
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

		// if we recorded a video position, then restore the video position
		if (isset($content['video']) && empty($content['video_time']) && $content['video']['video_id'] == $last['video_id'])
		{
			$content['video_time'] = $last['position'];
		}

		if (($top_actions = self::_top_tools_actions(!empty($content['is_staff']))))
		{
			if(!Api\Vfs::file_exists(Api\Vfs::PREFIX . "/apps/smallpart/{$content['courses']}/{$content['video']['video_id']}/all/template_note.ods"))
			{
				unset($top_actions['note']);
			}
			$tpl->setElementAttribute('top-tools', 'select_options', $top_actions);
		}
		$tpl->setElementAttribute('top-tools', 'hidden', !$bo->isAdmin($course) && !$bo->isTeacher($course) && !$bo->isTutor($course));


		$tpl->setElementAttribute(
			'play_control_bar[add_comment]', 'hidden',
			!$this->showCommentButton($content, $bo)
		);
		$tpl->setElementAttribute(
			'add_note', 'hidden',
			!$this->showNoteButton($content, $bo, true)
		);
		$tpl->setElementAttribute(
			'play_control_bar[add_note]', 'hidden',
			!$this->showNoteButton($content, $bo)
		);
		$tpl->setElementAttribute('filter-toolbar', 'actions', self::_filter_toolbar_actions());
		// need to set image upload url for uploading images directly into smallpart app location
		// html_editor_upload will carry image upload path for vfs of html-editor overlay.
		$content['html_editor_upload'] = '/apps/smallpart/'.$content['video']['course_id'].'/'.$content['video']['video_id'];

		if (!empty($content['video']))
		{
			$content['video']['seekable'] = ($content['is_staff'] || !($content['video']['video_test_options'] & Bo::TEST_OPTION_FORBID_SEEK));
			// send account_lid, so it can be used in path for task attachments
			$content['comment'] = [
				'course_id'   => $content['video']['course_id'],
				'video_id'    => $content['video']['video_id'],
				'account_lid' => $GLOBALS['egw_info']['user']['account_lid'],
				'free_comment_only' => (bool)(($content['video']['video_test_options']??0) & Bo::TEST_OPTION_FREE_COMMENT_ONLY),
			];
			// show back-button if we're a target-video and have a previous video
			if ($content['video']['video_published'] != Bo::VIDEO_TARGET || empty($content['video']['video_id']) ||
				!($content['previous_video_id'] = SmallParT\Overlay::getPreviousVideo($content['video']['course_id'], $content['video']['video_id'])) ||
				!($content['previous_video'] = current($bo->listVideos(['video_id' => $content['previous_video_id']], true, false))))
			{
				$readonlys['button[back]'] = true;
			}
		}

		$sel_options['catsOptions'] = self::_buildCatsOptions($course['cats'], $course['config']['no_free_comment']);
		if($course['config']['no_free_comment'])
		{
			$tpl->setElementAttribute('comment[comment_cat]', 'emptyLabel', lang('Choose main category'));
		}

		if ($content['video']['livefeedback_session'])
		{
			$content['cats'] = array_values(array_filter($course['cats'], function($_cat){ return !$_cat['parent_id'];}));

			// Set acronym, if missing
			array_walk($content['cats'], function (&$cat)
			{
				$cat['cat_acronym'] = $cat['cat_acronym'] ?: substr($cat['cat_name'], 0, 3);
			});
			$content['legend_cats'] = $content['cats'];
			array_unshift($content['legend_cats'], false);

			// Set class for position
			$content['video']['area_class'] = in_array(
				$content['video']['livefeedback_session'], ['not-started', 'running', 'hosting']
			) ?
				'et2-layout-full-span' : 'leftBoxArea et2-layout-area-left';
		}
		else
		{
			$content['video']['area_class'] = 'leftBoxArea et2-layout-area-left';
		}

		// Setup special categories
		$this->setupSpecialCategories($course, $tpl);

		// if we display all questions as list, we need to send them to the client-side
		if (!empty($content['video']) && $content['video']['video_test_display'] == Bo::TEST_DISPLAY_LIST)
		{
			$content['questions'] = array_map(static function($question)
			{
				$question['template'] = str_replace('-', '.', $question['overlay_type']);
				return $question;
			}, SmallParT\Overlay::read([
				'course_id' => $content['video']['course_id'],
				'video_id' => $content['video']['video_id'],
				'account_id' => $GLOBALS['egw_info']['user']['account_id'],
			])['elements'] ?? []);
			$content['question_summary'] = SmallParT\Overlay::summary($content['video']);
		}

		//error_log(Api\DateTime::to('H:i:s: ').__METHOD__."() video_id=$content[videos], time_left=$time_left, timer=".($content['timer']?$content['timer']->format('H:i:s'):'').", video=".json_encode($content['video']));
		$tpl->exec(Bo::APPNAME.'.'.self::class.'.index', $content, $sel_options, $readonlys, $preserv);
	}

	private static function _buildCatsOptions($_cats, $no_free_comment = false)
	{
		$options = [];
		if(!$no_free_comment)
		{
			$options[] = [
				'value' => 'free',
				'label' => lang('Free comment'),
			];
		}
		foreach ($_cats as $cat)
		{
			$options[] = [
				'value' => $cat['cat_id'],
				'label' => $cat['cat_name'],
				'title' => $cat['cat_description'],
				'class' => 'cat-color-'.$cat['cat_color'].' '.($cat['parent_id'] ? 'cat_level1' : ''),
				'parent_id' => $cat['parent_id'],
				'color' => $cat['cat_color'],
				'data'     => ['type' => $cat['type'], 'value' => $cat['value']],
				// Special categories are not selectable but may be turned on later
				'disabled' => $cat['type'] == 'sc'
			];
		}
		return $options;
	}

	private static function _filter_toolbar_actions()
	{
		return [
			'searchall' => [
				'caption' => 'Search all',
				'icon' => 'search',
				'default' => true,
				'group' => 1,
				'onExecute' => 'javaScript:app.smallpart.student_filter_tools_actions',
				'checkbox' => true,
				'hint' => 'Enables search in all content option for including all content while searching via filter search box.'
			],
			'download' => [
				'caption' => 'Download',
				'icon' => 'download',
				'group' => 1,
				'onExecute' => "javaScript:app.smallpart.student_filter_tools_actions",
				'hint' => 'Download comments of this video as CSV file'
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
				'icon' => 'brush',
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
			'question' => [
				'caption' => 'Edit Questions',
				'label' => 'Edit Questions',
				'icon' => 'pencil-square',
				'onExecute' => 'javaScript:app.smallpart.student_top_tools_actions',
				'staff' => true,
			],
			'score' => [
				'caption' => 'View Scores',
				'label' => 'View Scores',
				'icon' => 'clipboard-data',
				'onExecute' => 'javaScript:app.smallpart.student_top_tools_actions',
				'staff' => true,
			],
			'nickname' => [
				'caption' => 'Change nickname',
				'label' => 'Change nickname',
				'icon' => 'api/user',
				'onExecute' => 'javaScript:app.smallpart.changeNickname',
				'staff' => false,   // never display to staff (as it's not used for them!)
			],
			'note' => [
				'caption' => 'Add note',
				'label' => 'Add note',
				'icon' => 'note',
				'onExecute' => 'javaScript:app.smallpart.student_top_tools_actions',
			],
			[
				'id'         => 'toolbar_add',
				'label'      => 'Add text',
				'icon' => 'card-text',
				'statustext' => 'Add text overlay',
				'onExecute'  => 'javaScript:app.smallpart.VideoEdit.addText'
			],
			[
				'id'         => 'toolbar_add_question',
				'label'      => 'Add question',
				'icon' => 'exclamation-square',
				'statustext' => 'Add question',
				'onExecute'  => 'javaScript:app.smallpart.VideoEdit.addQuestion'
			]
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
	 * Set a user's notification flag
	 *
	 * @param int $course_id
	 * @param bool $notify
	 * @return void
	 * @throws Api\Exception\NoPermission
	 */
	public static function ajax_changeNotify(int $course_id, bool $notify)
	{
		$bo = new Bo();
		$bo->setNotifyParticipant($course_id, $GLOBALS['egw_info']['user']['account_id'], $notify);
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

			$path = "/apps/smallpart/{$comment['course_id']}/{$comment['video_id']}/{$GLOBALS['egw_info']['user']['account_lid']}/comments/";
			$doPush = ($comment['comment_id'] && !Api\Vfs::file_exists("{$path}.new/"));
			$comment_id = $bo->saveComment($comment, false, $doPush);
			if($comment_id && !$doPush)
			{
				$push_action = !$comment['comment_id'] ? 'add' : $comment['action'];
				$comment['comment_id'] = $comment_id;
				$comment['action'] = "edit";
				// Move new uploads
				$bo->save_comment_attachments($comment['course_id'], $comment['video_id'], $comment_id, $comment['attachments']);

				// Push again with attachments
				$bo->saveComment($comment, false, $push_action);
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
		// Check first if the directory is there
		$comment = current($_comments);
		$upload_path = '/apps/smallpart/' . (int)$comment['course_id'] . '/' . (int)$comment['video_id'] . '/';
		$hasAttachments = Api\Vfs::is_dir($upload_path) && Api\Vfs::is_readable($upload_path);

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
			if($hasAttachments && Api\Vfs::is_readable($upload_path) && !empty($attachments = Etemplate\Widget\Vfs::findAttachments($upload_path)))
			{
				$comment[$upload_path] = $attachments;
				$comment['class'] .= ' commentAttachments';
			}
			if ($comment['comment_cat'])
			{
				$types = [];
				foreach (explode(':', $comment['comment_cat']) as $cat)
				{
					$comment['class'] .= ' cat-'.$cat;
				}

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
		// catch SQL error if JSON_VALUE is not understood (eg. PostgreSQL or older MariaDB/MySQL) --> query all, decode and filter manually
		catch(Api\Db\Exception\InvalidSql $e)
		{
			try {
				$data = $bo->readCLMeasurementRecords($course_id, $video_id, $cl_type, $account_id);
				if ($mode)
				{
					$data = array_filter($data, static function($measurement) use ($mode)
					{
						return  ($cl_data = json_decode($measurement['cl_data'])) && $cl_data['mode'] == $mode;
					});
				}
				$response->data($data);
			}
			catch (\Exception $e) {
				$response->message($e->getMessage(), 'error');
			}
		}
		catch (\Exception $e) {
			$response->message($e->getMessage(), 'error');
		}
	}

	/**
	 * Live feedback session
	 *
	 * @param bool $_status
	 * @param array $_data
	 * @return void
	 * @throws Api\Exception\WrongParameter
	 * @throws Api\Json\Exception
	 */
	public function ajax_livefeedbackSession(bool $_status = false, array $_data = [])
	{
		$response = Api\Json\Response::get();
		if (!empty($_data))
		{
			try {
				$bo = new Bo();

				// check if the user has permission to start/stop the session
				if (!$bo::checkTeacher(['egw_info']['user']['account_id']))
				{
					throw new \Exception('You have no permissions!');
				}

				$record = $bo->readLivefeedback($_data['course_id'], $_data['video_id']);

				if ($record)
				{
					if ($_status && empty($record['session_starttime']))
					{
						$record['session_starttime'] = new Api\DateTime('now');
						$record['host'] = $GLOBALS['egw_info']['user']['account_id'];
						$bo->updateLivefeedback($record);
						$response->data(['msg' => 'session started', 'session' => 'started', 'data' => $record]);
						$bo->pushOnline($_data['course_id'], $_data['course_id'].":".$_data['video_id'], 'update',
							['moderator'=> $GLOBALS['egw_info']['user']['account_id'], 'data' => $record]);
						return;
					}
					else if (empty($record['session_endtime']))
					{
						$record['session_endtime'] = new Api\DateTime('now');
						$bo->updateLivefeedback($record);
						$response->data(['msg' => 'session ended', 'session' => 'ended', 'data' => $record]);
						$bo->pushOnline($_data['course_id'], $_data['course_id'].":".$_data['video_id'], 'update',
							['moderator'=> $GLOBALS['egw_info']['user']['account_id'], 'data' => $record]);
						return;
					}
					else
					{
						throw new \Exception('This session is already closed!');
					}
				}
			}
			catch (\Exception $e)
			{
				$response->message($e->getMessage(), 'error');
			}
		}
	}

	/**
	 * Live feedback session
	 *
	 * @param string $exec_id
	 * @param array $comment
	 * @return void
	 * @throws Api\Exception\WrongParameter
	 * @throws Api\Json\Exception
	 */
	public function ajax_livefeedbackSaveComment(string $exec_id, array $comment)
	{
		$response = Api\Json\Response::get();
		try
		{
			$bo = new Bo();
			$record = $bo->readLivefeedback($comment['course_id'], $comment['video_id']);
			if ($record && empty($record['session_endtime']) && !empty($record['session_starttime']))
			{
				$now = new Api\DateTime('now');
				$comment['comment_starttime'] = $comment['comment_starttime'] ? intval($comment['comment_starttime']) : $now->getTimestamp() - Api\DateTime::to($record['session_starttime'], 'ts');
				$comment['comment_stoptime'] = $comment['comment_starttime'] + 1;
				self::ajax_saveComment($exec_id, $comment);
			}
			else if($record && empty($record['session_endtime']) && empty($record['session_starttime']))
			{
				throw new Api\Json\Exception('The session has not been started yet. You have no access to feedback!');
			}
			else
			{
				$response->data(['session'=>'ended']);
			}
		}
		catch (\Exception $e)
		{
			$response->message($e->getMessage(), 'error');
		}
	}

	public function ajax_livefeedbackPublishVideo($_video_id)
	{
		$response = Api\Json\Response::get();
		try
		{
			$bo = new Bo();
			$video = $bo->readVideo($_video_id);
			if (is_array($video))
			{
				$video['video_published'] = '1';
				$bo->saveVideo($video);
				$bo->pushOnline($video['course_id'], $_video_id, 'update', ['data' => $video['livefeedback']]);
			}
			else
			{
				throw new \Exception('Video is not accessible!');
			}
		}
		catch(\Exception $e)
		{
			$response->message($e->getMessage(), 'error');
		}
	}

	/**
	 * Should the comment button be shown for this user / video
	 *
	 * @param $content
	 * @return bool
	 * @throws Api\Exception\WrongParameter
	 */
	protected function showCommentButton($content, &$bo)
	{
		// If no course, no button
		if(!$content['course_id'])
		{
			return false;
		}

		// Always for teacher or admin
		if($bo->isTeacher($content) || $bo->isAdmin($content))
		{
			return true;
		}

		// For student, depends on course options
		if($bo->isParticipant($content))
		{
			if(in_array((int)$content['video']['video_options'],
						[Bo::COMMENTS_FORBIDDEN_BY_STUDENTS, Bo::COMMENTS_DISABLED]
			))
			{
				return false;
			}
			return true;
		}
		return false;
	}


	/**
	 * Should the note button be shown for this user / video
	 *
	 * @param $content
	 * @param $bo
	 * @return bool
	 */
	protected function showNoteButton($content, &$bo, $skip_acl = false)
	{
		// If no course, no button
		if(!$content['course_id'])
		{
			return false;
		}

		$file_exists = Api\Vfs::file_exists("/apps/smallpart/{$content['courses']}/{$content['video']['video_id']}/all/template_note.ods");

		if($skip_acl)
		{
			return $file_exists;
		}

		if($bo->isTeacher($content) || $bo->isAdmin($content))
		{
			return $file_exists;
		}

		// For student, depends on course options
		if($bo->isParticipant($content))
		{
			if(in_array((int)$content['video']['video_options'],
						[Bo::COMMENTS_FORBIDDEN_BY_STUDENTS, Bo::COMMENTS_DISABLED]
			))
			{
				return $file_exists;
			}
		}
		return false;
	}

	protected function addPreferencesStart(&$content, &$sel_options)
	{
		$content['course_preferences'] = [];
		foreach($GLOBALS['egw_info']['user']['preferences']['smallpart'] as $pref => $value)
		{
			if(str_starts_with($pref, 'course_' . (int)$content['course_id'] . '_') && $value)
			{
				$pref_name = str_replace('course_' . (int)$content['course_id'] . '_', '', $pref);
				$content['course_preferences'][] = $pref_name;
			}
		}
		$sel_options['course_preferences'] = array(
			['value' => "pauseaftersubmit", 'icon' => "pause", 'label' => 'No autoplay after comment submission'],
			['value' => "mouseover", 'icon' => "pause", 'label' => 'Autopause on mouseover in the comment area'],
			['value' => "comment_on_top", 'icon' => "chat-left-text",
			 'label' => 'Show comment input on top of the comments list'],
			['value' => "hide_question_bar", 'icon' => "mortarboard", 'label' => 'Hide teacher comments bar'],
			['value' => "hide_text_bar", 'icon' => "exclamation-square", 'label' => 'Hide extra info bar']
		);
		// Remove preferences disabled by course
		foreach(['disable_question_bar', 'disable_text_bar'] as $disable)
		{
			if($GLOBALS['egw_info']['user']['preferences']['smallpart']['course_' . $content['course_id'] . '_' . $disable])
			{
				$pref_name = str_replace('disable_', 'hide_', $disable);
				if(($key = array_search($pref_name, array_column($sel_options['course_preferences'], 'value'))) !== false)
				{
					unset($sel_options['course_preferences'][$key]);
				}
			}
		}
	}

	/**
	 * Set the actions & stuff needed for special categories
	 *
	 * @param $course
	 * @param $tpl
	 * @return void
	 */
	protected function setupSpecialCategories(&$course, &$tpl)
	{
		$special_cats = [];
		foreach($course['cats'] as $n => &$cat)
		{
			if($cat['type'] == 'sc')
			{
				$special_cats[] = [
					'id'        => 'sc_add_' . $cat['cat_id'],
					'caption'   => $cat['cat_name'],
					'icon'      => 'add',
					'hint'      => lang('add new comment'),
					'group'     => 'add_buttons',
					'disabled'  => true,
					'onExecute' => 'javaScript:app.smallpart.addSpecialComment',
				];
			}
		}
		if(count($special_cats))
		{
			$tpl->setElementAttribute('special_category_toolbar[sc_toggle]', 'hidden', false);
			$tpl->setElementAttribute('special_category_toolbar', 'actions', $special_cats);
		}
	}
	/**
	 * Set up what the preferences need
	 *
	 * @param $content
	 * @param $bo
	 * @param $etemplate
	 * @return void
	 */
	protected function addPreferences(&$content, &$bo, &$etemplate)
	{
		static $user_preferences = ['pauseaftersubmit', 'mouseover', 'comment_on_top', 'hide_question_bar',
									'hide_text_bar'];

		foreach($user_preferences as $pref_name)
		{
			$value = $GLOBALS['egw_info']['user']['preferences']['smallpart']['course_' . $content['course_id'] . '_' . $pref_name];
			$content[$pref_name] = $value;
			$etemplate->setElementAttribute($pref_name, 'checked', $value);
		}
		// Disabled
		foreach(['disable_question_bar', 'disable_text_bar'] as $disable)
		{
			if($GLOBALS['egw_info']['user']['preferences']['smallpart']['course_' . $content['course_id'] . '_' . $disable])
			{
				$pref_name = str_replace('disable_', 'hide_', $disable);
				$content[$pref_name] = false;
				$etemplate->setElementAttribute($pref_name, 'checked', true);
				$etemplate->setElementAttribute($pref_name, 'hidden', true);
			}
		}
	}
}