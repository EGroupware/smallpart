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
		$tpl = new Etemplate('smallpart.student.index');
		$sel_options = $readonlys = [];
		$bo = new SmallParT\Bo($GLOBALS['egw_info']['user']['account_id']);

		// if student has not yet subscribed to a course --> redirect him to course list
		if (!($courses = array_map(function($val){
				return $val['course_name'];
			}, $bo->listCourses())))
		{
			Api\Egw::redirect_link('/index.php', [
				'menuaction' => Bo::APPNAME.'.'.SmallParT\Courses::class.'.index',
				'ajax' => 'true',
			]);
		}

		// if we have a last course and video or _GET[course_id] set --> use it
		if (!isset($content))
		{
			if (!empty($_GET['course_id']) && $bo->read((int)$_GET['course_id']))
			{
				$content = ['courses' => (int)$_GET['course_id']];
			}
			elseif (($last = $bo->lastVideo()))
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
			if (!empty($content['courses']))
			{
				$sel_options['videos'] = array_map(function($val){
					return $val['video_name'];
				}, $videos);
				if (!empty($content['videos']))
				{
					$content['video'] = $videos[$content['videos']];
					$content['comments'] = self::_fixComments($bo->listComments($content['videos']));

				}
				else
				{
					unset($content['video'], $content['comments']);
				}

				// remember last course and video of user between sessions
				$bo->setLastVideo([
					'course_id' => $content['courses'],
					'video_id'  => $content['videos'],
				]);
			}
			else
			{
				unset($content['video'], $content['comments']);
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
				'content' => self::_fixComments($bo->listComments($comment['video_id'], $where)),
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
				'content' => self::_fixComments($bo->listComments($where['video_id'], $where)),
			]);
			$response->message(lang('Comment deleted.'), 'success');
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
	public static function ajax_filterComments(array $where=[])
	{
		$response = Api\Json\Response::get();
		try {
			$bo = new SmallParT\Bo();
			if (empty($where['comment_color'])) unset($where['comment_color']);
			$response->call('app.smallpart.student_updateComments', [
				'content' => self::_fixComments($bo->listComments($where['video_id'], $where)),
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
				'icon' => 'open',
				'default' => true,
				'onExecute' => 'javaScript:app.smallpart.student_openComment'
			],
			'edit' => [
				'caption' => 'Edit',
				'icon' => 'edit',
				'onExecute' => 'javaScript:app.smallpart.student_openComment'
			],
			'retweet' => [
				'caption' => 'Retweet',
				//'icon' => 'retweet',
				'onExecute' => 'javaScript:app.smallpart.student_openComment'
			]
		];
	}

	/**
	 * fix comments data
	 *
	 * @param array $_comments
	 * @return array
	 */
	private static function _fixComments($_comments)
	{
		foreach ($_comments as &$comment)
		{

		}
		// renumber rows: 1, 2, ...
		return array_merge([false], array_values($_comments));
	}
}
