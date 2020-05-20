<?php
/**
 * EGroupware - SmallParT - LTI Learning Tools Interoperatbility - User interface
 *
 * @link https://www.egroupware.org
 * @package smallpart
 * @subpackage lti
 * @author Ralf Becker <rb@egroupware.org>
 * @copyright 2020 by Ralf Becker <rb@egroupware.org>
 * @license http://opensource.org/licenses/gpl-license.php GPL - GNU General Public License
 */

namespace EGroupware\SmallParT\LTI;

use EGroupware\Api\Exception\NoPermission;
use EGroupware\Api\Framework;
use EGroupware\Api\Framework\Ajax;
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
	 * @var Session
	 */
	protected $session;

	/**
	 * @var array
	 */
	protected $data;

	/**
	 * Ui constructor.
	 *
	 * Prepare output by EGroupware: CSP, rendering without navigation, etc.
	 *
	 * @param Session $session
	 */
	public function __construct(Session $session)
	{
		$this->session = $session;
		$this->data = $this->session->getCustomData();


		// allow framing by lms (strip path off, as it's invalid for CSP!)
		if (preg_match('|^(https://[^/]+)/|', $url = $this->session->getIssuer(), $matches))
		{
			$url = $matches[1];
		}
		ContentSecurityPolicy::add('frame-ancestors', $url);

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
				$bo->read($this->data['course_id']);
			}
			catch (NoPermission $ex) {
				$bo->subscribe($this->data['course_id'], true, null, true);
			}
		}
	}

	/**
	 * Render UI
	 */
	public function render()
	{
		$ui = new \EGroupware\SmallParT\Student\Ui();
		$ui->index([
			'courses' => $this->data['course_id'],
			'videos'  => $this->data['video_id'],
			'disable_navigation' => !empty($this->data['course_id']),
		]);
	}
}