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
use EGroupware\SmallParT;
use EGroupware\SmallParT\Bo;

class Ui
{
	public $public_functions = [
		'index' => true
	];

	public function index($content=null)
	{
		$theme=$GLOBALS['egw_info']['user']['preferences']['smallpart']['theme'];
		$tpl = new Etemplate( $theme ? 'smallpart.student.index'.'.'.$theme:'smallpart.student.index');
		$sel_options = $readonlys = [];
		$bo = new Bo($GLOBALS['egw_info']['user']['account_id']);
		$last = $bo->lastVideo();
		$now = new Api\DateTime('now');

		// if student has not yet subscribed to a course --> redirect him to course list
		if (!($courses = $bo->listCourses()) ||
			// or he selected "manage courses ..."
			$content['courses'] === 'manage' ||
			// or he was on "manage courses ..." last and not explicitly selecting a course
			!isset($_GET['course_id']) && empty($content['courses']) && $last && $last['course_id'] === 'manage')
		{
			$bo->setLastVideo([
				'course_id' => 'manage',
			], $last);
			/* output course-management instead of redirect to not having to cope with redirects in LTI
			Api\Egw::redirect_link('/index.php', [
				'menuaction' => Bo::APPNAME.'.'.SmallParT\Courses::class.'.index',
				'ajax' => 'true',
			]);*/
			$courses = new SmallParT\Courses();
			$courses->index();
			return;
		}
		$courses['manage'] = lang('Manage courses').' ...';

		// if we have a last course and video or _GET[course_id] set --> use it
		if (!isset($content))
		{
			if (!empty($_GET['course_id']) && $bo->read((int)$_GET['course_id']))
			{
				$content = ['courses' => (int)$_GET['course_id']];
				if (!empty($_GET['video_id']) && $bo->readVideo($_GET['video_id']))
				{
					$content['videos'] = (int)$_GET['video_id'];
				}
			}
			elseif ($last && $last['course_id'] !== 'manage')
			{
				$content = [
					'courses' => $last['course_id'],
					'videos' => $last['video_id'] ?: '',
				];
			}
		}
		if (!isset($content))
		{
			$content['courses'] = '';
		}
		else
		{
			$videos = $bo->listVideos(['course_id' => $content['courses']]);
			if (count($videos) === 1) $content['videos'] = key($videos);
			if (count($videos) > 1 && !empty($content['disable_navigation']))
			{
				unset($content['disable_navigation']);
				$content['disable_course_selection'] = true;
			}
			if (!empty($content['courses']))
			{
				$sel_options['videos'] = array_map(Bo::class.'::videoLabel', $videos);
				$content['is_admin'] = $bo->isAdmin($content['courses']);
				if (!empty($content['videos']))
				{
					$content['video'] = $videos[$content['videos']];
					try {
						if ($content['video']['accessible'] === false &&
							// case video not yet available is handled with a countdown later
							!($content['video']['video_published'] == Bo::VIDEO_PUBLISHED &&
								isset($content['video']['video_published_start']) &&
								$content['video']['video_published_start'] > $now))
						{
							Api\Framework::message($content['video']['error_msg'] ?: lang('This video is currently NOT accessible!'));
							throw new \Exception('Not accessible');	// deselects the listed video again
						}
						$content['comments'] = $content['video'] ? self::_fixComments($bo->listComments($content['videos']), $content['is_admin']) : [];
					}
					// can happen when a video got deleted
					catch (\Exception $e) {
						//_egw_log_exception($e);
						unset($content['videos'], $content['video']);
					}
				}
				else
				{
					unset($content['video'], $content['comments']);
				}

				// remember last course and video of user between sessions
				$bo->setLastVideo([
					'course_id' => $content['courses'],
					'video_id'  => empty($content['video']) ? '' : $content['videos'],
				], $last);

				if (($course = $bo->read($content['courses'])))
				{
					if ($content['is_admin']) $content['participants'] = $course['participants'];
					$content['course_options'] = (int)$course['course_options'];
				}
			}
			else
			{
				unset($content['video'], $content['comments']);
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
			$bo->downloadComments($content['courses'], $content['videos'], [
				'comment_color' => $raw_content['comment_color_filter'],
				// the active-participant-filter contains array of comma-sep. comment_id(s)
				'comment_id' => $raw_content['activeParticipantsFilter'] ?
					explode(',', implode(',', $raw_content['activeParticipantsFilter'])) : null,
				'search' => $raw_content['comment_search_filter'],
			]);
		}

		$sel_options = array_merge([
			'courses' => $courses
		], $sel_options);

		$readonlys = [
			'edit_course' => !$content['is_admin'],
			'edit_questions' => !$content['is_admin'],
			'view_scores' => !$content['is_admin'],
		];
		if ($content['comments'])
		{
			$actions = self::get_actions();

			// none admin user with forbidden option to comment on video
			if ($content['video']['video_options'] == Bo::COMMENTS_FORBIDDEN_BY_STUDENTS && !$content['is_admin'] ||
				$content['video']['accessible'] === 'readonly')
			{
				unset($actions['delete'], $actions['edit'], $actions['retweet'], $actions['add']);
				$readonlys['add_comment'] = true;
			}
			$tpl->setElementAttribute('comments', 'actions', $actions);
		}
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
			($content['video']['accessible'] === null || $content['is_admin'] && $content['video']['accessible'] === true) &&
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
				// overal time-frame has precedence over individual time left
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
				$content['is_admin'] && $content['video']['accessible'] === true && $time_left > 0))
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

		// if we recored a video postion, then restore the video position
		if (isset($content['video']) && empty($content['video_time']) && $content['video']['video_id'] == $last['video_id'])
		{
			$content['video_time'] = $last['position'];
		}

		//error_log(Api\DateTime::to('H:i:s: ').__METHOD__."() video_id=$content[videos], time_left=$time_left, timer=".($content['timer']?$content['timer']->format('H:i:s'):'').", video=".json_encode($content['video']));
		$tpl->exec(Bo::APPNAME.'.'.self::class.'.index', $content, $sel_options, $readonlys, $preserv);
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
			$bo->saveComment($comment);
			$response->call('app.smallpart.student_updateComments', [
				'content' => self::_fixComments($bo->listComments($comment['video_id'], $where),
					$bo->isAdmin($comment['course_id'])),
			]);
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
					$bo->isAdmin($where)),
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
	 * @param boolean $is_admin =false, true: current user is admin AND should be able to act as the user (edit&delete comments)
	 * @return array
	 */
	private static function _fixComments($_comments, $is_admin=false)
	{
		foreach ($_comments as &$comment)
		{
			if ($is_admin || $comment['account_id'] == $GLOBALS['egw_info']['user']['account_id'])
			{
				$comment['class'] = 'commentOwner';
			}
			if (!empty($comment['comment_marked']))
			{
				$comment['class'] .= ' commentMarked';
			}
		}
		// renumber rows: 1, 2, ...
		return array_merge([false], array_values($_comments));
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
}
