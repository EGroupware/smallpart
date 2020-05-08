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

	public static function get_actions()
	{
		return [
			'open' => [
				'caption' => 'open',
				'icon' => 'open',
				'default' => true,
				'onExecute' => 'javaScript:app.smallpart.student_openComment'
			],
			'edit' => [
				'caption' => 'edit',
				'icon' => 'edit',
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
			$comment['comment_added'] = preg_replace('/[\[""\]]/', '', $comment['comment_added']);
		}
		return $_comments;
	}
}
