<?php
/**
 * EGroupware - SmallParT - Student Ui
 *
 * @link https://www.egroupware.org
 * @package smallpart
 * @subpackage Ui
 * @license http://opensource.org/licenses/gpl-license.php GPL - GNU General Public License
 */

namespace EGroupware\SmallParT\Student;

use EGroupware\Api;
use EGroupware\Api\Etemplate;
use EGroupware\SmallParT;

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
		$bo = new SmallParT\Bo($GLOBALS['egw_info']['user']['account_id']);
		$last = $bo->lastVideo();

		// if student has not yet subscribed to a course --> redirect him to course list
		if (!($courses = $bo->listCourses()) ||
			// or he selected "manage courses ..."
			$content['courses'] === 'manage' ||
			// or he was on "manage courses ..." last and not explicitly selecting a course
			!isset($_GET['course_id']) && empty($content['courses']) && $last && $last['course_id'] === 'manage')
		{
			$bo->setLastVideo([
				'course_id' => 'manage',
			]);
			/* output course-management instead of redirect to not having to cope with redirects in LTI
			Api\Egw::redirect_link('/index.php', [
				'menuaction' => SmallParT\Bo::APPNAME.'.'.SmallParT\Courses::class.'.index',
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
				$sel_options['videos'] = array_map(function($val){
					return $val['video_name'];
				}, $videos);
				$content['is_admin'] = $bo->isAdmin($content['courses']);
				if (!empty($content['videos']))
				{
					$content['video'] = $videos[$content['videos']];
					try {
						$content['comments'] = $content['video'] ? self::_fixComments($bo->listComments($content['videos']), $content['is_admin']) : [];
					}
					// can happen when a video got deleted
					catch (\Exception $e) {
						_egw_log_exception($e);
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
				]);

				if ($content['is_admin']) $content['participants'] = $bo->read($content['courses'])['participants'];
			}
			else
			{
				unset($content['video'], $content['comments']);
			}

			// LTI launches disable navigation, if they specify course_id and video_id (or have only one video)
			if ($content['disable_navigation'])
			{

			}

			$prefserv = $content;
		}


		$sel_options = array_merge([
			'courses' => $courses
		], $sel_options);

		$readonlys = [];
		if ($content['comments']) $tpl->setElementAttribute('comments', 'actions', self::get_actions());
		$tpl->exec(SmallParT\Bo::APPNAME.'.'.self::class.'.index', $content, $sel_options, $readonlys, $prefserv);
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
			$bo = new SmallParT\Bo();
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
			$bo = new SmallParT\Bo();
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
			$bo = new SmallParT\Bo();
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
			$bo = new SmallParT\Bo();
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
}
