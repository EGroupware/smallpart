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

use EGroupware\Api\Exception\NoPermission;
use EGroupware\Api\Framework;
use EGroupware\Api\Header\ContentSecurityPolicy;
use EGroupware\SmallParT\Bo;

/**
 * Class Ui
 *
 * @package EGroupware\SmallParT\LTI
 */
class Ui
{
	/**
	 * @var BaseSession
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

	protected $is_admin = false;

	/**
	 * Ui constructor.
	 *
	 * Prepare output by EGroupware: CSP, rendering without navigation, etc.
	 *
	 * @param BaseSession $session
	 */
	public function __construct(BaseSession $session)
	{
		$this->session = $session;
		$this->data = $this->session->getCustomData();

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
			}
			catch (NoPermission $ex) {
				$bo->subscribe($this->data['course_id'], true, null, true);
				$this->course = $bo->read($this->data['course_id']);
			}
			$this->is_admin = $bo->isAdmin($this->course ?: null);
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
		$content['disable_navigation'] = !($this->is_admin || empty($this->course));
		$ui->index($content);
	}
}