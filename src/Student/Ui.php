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
		$sel_options = [];
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
			}
			if (!empty($content['videos']))
			{
				$content['video'] = array_merge($videos[$content['videos']],['video_comments' => $bo->listComments($content['videos'])]);
			}
			$prefserv = $content;
		}


		$sel_options = array_merge([
			'courses' => $courses
		], $sel_options);

		$readonlys = [];

		$tpl->exec('smallpart.EGroupware\\SmallParT\\Student\\Ui.index', $content, $sel_options, $readonlys, $prefserv);
	}
}
