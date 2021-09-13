<?php
/**
 * EGroupware - SmallParT - LTI Learning Tools Interoperatbility - User interface
 *
 * @link https://www.egroupware.org
 * @package smallpart
 * @subpackage lti
 * @author Ralf Becker <rb@egroupware.org>
 * @copyright 2020 by Ralf Becker <rb@egroupware.org>
 * @license https://spdx.org/licenses/AGPL-3.0-or-later.html GNU Affero General Public License v3.0 or later
 */

namespace EGroupware\SmallParT\LTI;

use EGroupware\Api;
use EGroupware\Api\Cache;
use EGroupware\Api\Exception\NoPermission;
use EGroupware\Api\Framework;
use EGroupware\SmallParT\Bo;
use EGroupware\SmallParT\Courses;

/**
 * Class Ui
 *
 * @package EGroupware\SmallParT\LTI
 */
class Ui
{
	/**
	 * menuaction callable methods
	 *
	 * @var bool[]
	 */
	public $public_functions = [
		'contentSelection' => true,
	];

	/**
	 * @var Session
	 */
	protected $session;

	/**
	 * @var array
	 */
	protected $data;

	/**
	 * @var array
	 */
	protected $course;

	protected $is_teacher = false;

	/**
	 * Ui constructor.
	 *
	 * Prepare output by EGroupware: CSP, rendering without navigation, etc.
	 *
	 * @param Session $session
	 */
	public function __construct(Session $session=null)
	{
		if (isset($session))
		{
			$this->session = $session;
			$this->data = $this->session->getCustomData();

			// remember in EGroupware session to add LMS as frame-ancestor
			Cache::setSession('smallpart', 'lms_origin', $session->getIssuer());
		}
		// hack to stop framework from redirecting to draw navbar
		$_GET['cd'] = 'no';
		$GLOBALS['egw_info']['flags']['currentapp'] = 'smallpart';
		$GLOBALS['egw_info']['flags']['js_link_registry'] = true;

		// allow (different) styling for the calling LMS using: body.LtiLaunch
		Framework::bodyClass('LtiLaunch');
	}

	/**
	 * Check if have valid data to launch
	 *
	 * @throw \Exception if course does not exist or is not available to user
	 */
	public function check()
	{
		if (!empty($this->data['course_id']))
		{
			$bo = new Bo();
			try {
				$this->course = $bo->read($this->data['course_id']);
				if ($this->session->debug)
				{
					error_log(__METHOD__."() bo->read(".json_encode($this->data['course_id']).") returned ".json_encode($this->course));
				}
			}
			catch (NoPermission $ex) {
				$bo->subscribe($this->data['course_id'], true, null, true,
					$this->session->isInstructor() ? Bo::ROLE_TEACHER : Bo::ROLE_STUDENT);
				$this->course = $bo->read($this->data['course_id']);
				if ($this->session->debug)
				{
					error_log(__METHOD__."() after bo->subscribe(".json_encode($this->data['course_id']).", true, null, true): bo->read(".json_encode($this->data['course_id']).") returned ".json_encode($this->course));
				}
			}
			$this->is_teacher = $this->course ? $bo->isTutor($this->course) : Bo::checkTeacher();
		}
		if ($this->session->debug)
		{
			error_log(__METHOD__."() this->data=".json_encode($this->data).", is_teacher=$this->is_teacher, course=".json_encode($this->course));
		}
	}

	/**
	 * Render UI
	 */
	public function render()
	{
		$ui = new \EGroupware\SmallParT\Student\Ui();
		$content = [];
		if ($this->course)
		{
			$content = [
				'courses' => $this->data['course_id'],
				'videos'  => $this->data['video_id'] ?: ($this->course ? key($this->course['videos']) : null),
			];
		}
		// do NOT disable navigation for course-admins or if no course selected
		$content['disable_navigation'] = !($this->is_teacher || empty($this->course));

		if ($this->session->debug)
		{
			error_log(__METHOD__."() calling ui->index(".json_encode($content).") this->course=".json_encode($this->course));
		}
		$ui->index($content);
	}

	/**
	 * Content-selection UI
	 *
	 * @param ?array $content
	 * @param ?callable $callback
	 * @param ?array $params for callback
	 * @throws Api\Exception\AssertionFailed
	 */
	public function contentSelection(array $content=null, $callback=null, array $params=null)
	{
		$bo = new Bo();
		if (!empty($content['button']))
		{
			$callback = $content['callback'];
			$params = $content['params'];
			$selection = null;
			if (!empty($content['button']['submit']))
			{
				$selection = array_filter(array_intersect_key($content, array_flip(['course_id', 'video_id'])));
				if (empty($params['title']) && ($course = $bo->read(['course_id' => $selection['course_id']], false)))
				{
					$params['title'] = $course['course_name'];
					if (!empty($selection['video_id']) && isset($course['videos'][$selection['video_id']]))
					{
						$params['title'] .= ': '.$course['videos'][$selection['video_id']]['video_name'];
						if (empty($params['text']))
						{
							$course['videos'][$selection['video_id']]['video_question'];
						}
					}
				}
			}
			// returning result (does NOT return!)
			$callback($params, $selection);
			return;
		}
		// create new course via edit course (with no course_id)
		elseif(!empty($content['course_id']) && $content['course_id'] === 'new')
		{
			return (new Courses())->edit(null, $content['callback'], $content['params']);
		}
		Api\Translation::add_app('smallpart');
		$tpl = new Api\Etemplate('smallpart.lti-content-selection');
		$sel_options = [
			'course_id' => $bo->listCourses(false)+[
				'new' => lang('Add new course'),
			],
		];
		if (empty($content['course_id']))
		{
			$content['course_id'] = key($sel_options['course_id']);
		}
		if (!empty($content['course_id']))
		{
			$content['videos'] = $bo->listVideos(['course_id' => $content['course_id']]);
			$sel_options['video_id'] = array_map(function($video) {
				return $video['video_name'];
			}, $content['videos']);
			// select first video
			$content['video_id'] = key($sel_options['video_id']);
		}
		$tpl->exec('smallpart.'.self::class.'.contentSelection', (array)$content, $sel_options, null, [
			'callback' => $callback ?? $content['callback'],
			'params'     => $params ?? $content['params'],
		], 2);
	}
}
