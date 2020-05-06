<?php
/**
 * EGroupware - SmallParT - Ui
 *
 * @link https://www.egroupware.org
 * @package smallpart
 * @subpackage Ui
 * @license http://opensource.org/licenses/gpl-license.php GPL - GNU General Public License
 */

namespace EGroupware\SmallParT\Student;

use EGroupware\Api\Etemplate;

class Ui {

	public $public_functions = [
		'index' => true
	];

	public function index($content=null)
	{
		$tpl = new Etemplate('smallpart.student.index');
		$sel_options = $readonlys = [];
		$bo = new \EGroupware\SmallParT\Bo($GLOBALS['egw_info']['user']['account_id']);
		$courses = array_map(function($val){
			return $val['course_name'];
		}, $bo->listCourses());

		if (!is_array($content))
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
		$tpl->exec('smallpart.EGroupware\\SmallParT\\Student\\Ui.index', $content, $sel_options, $readonlys, $prefserv);
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
