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

namespace EGroupware\SmallParT\LTI;

/**
 * Class Session
 */
class Session extends BaseSession
{
	/**
	 * @var Tool
	 */
	private $provider;

	/**
	 * Session constructor.
	 *
	 * @param Tool $provider
	 */
	public function __construct(Tool $provider)
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
			'1.0',
			$this->provider->getMessageParameters()['ext_user_username']
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
		foreach($this->provider->platform->getSettings() as $name => $value)
		{
			if (substr($name, 0, 7) === 'custom_') $name = substr($name, 7);
			$data[$name] = $value;
		}
		if (preg_match('/^course_id=(\d+)/', $this->provider->platform->getKey(), $matches) && $matches[1])
		{
			$data['course_id'] = $matches[1];
		}
		return $data;
	}
}