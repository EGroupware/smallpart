<?php
/**
 * EGroupware - SmallParT - LTI Learning Tools Interoperatbility - Session
 *
 * @link https://www.egroupware.org
 * @package smallpart
 * @subpackage lti
 * @author Ralf Becker <rb@egroupware.org>
 * @copyright 2020 by Ralf Becker <rb@egroupware.org>
 * @license https://spdx.org/licenses/AGPL-3.0-or-later.html GNU Affero General Public License v3.0 or later
 */

namespace EGroupware\SmallParT\LTI10;

use EGroupware\SmallParT\LTI\BaseSession;

/**
 * Class Session
 *
 * @package EGroupware\SmallParT\LTI10
 */
class Session extends BaseSession
{
	/**
	 * @var ToolProvider
	 */
	private $provider;

	/**
	 * Session constructor.
	 *
	 * @param ToolProvider $provider
	 */
	public function __construct(ToolProvider $provider)
	{
		$this->provider = $provider;

		parent::__construct(
			$this->provider->consumer->consumerGuid,
			$this->provider->userResult->username,
			$this->provider->userResult->sourcedId,
			$this->provider->userResult->firstname,
			$this->provider->userResult->lastname,
			$this->provider->userResult->email,
			$this->provider->getMessageParameters()['launch_presentation_locale'],
			'1.0'
		);
	}

	/**
	 * Check if current user is an instructor
	 *
	 * @return bool
	 */
	public function isInstructor()
	{
		return $this->provider->userResult->isStaff() || $this->provider->userResult->isAdmin();
	}

	/**
	 * Get framing site to set frame-ancestor CSP policy
	 *
	 * @return string
	 */
	public function getFrameAncestor()
	{
		return $this->provider->returnUrl ?: 'https://'.$this->provider->consumer->consumerGuid;
	}

	/**
	 * Get custom data from launch
	 *
	 * If course_id is provided as consumer oauth_key, dont let it be overridden!
	 *
	 * @return array|null
	 */
	public function getCustomData()
	{
		$data = [];
		if (preg_match('/^course_id=(\d+)/', $this->provider->consumer->getKey(), $matches) && $matches[1])
		{
			$data['course_id'] = $matches[1];
		}
		$data += $this->provider->consumer->getSettings();

		return $data;
	}
}