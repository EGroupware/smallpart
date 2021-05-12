<?php
/**
 * EGroupware - SmallParT - LTI Learning Tools Interoperatbility - LTI v1.0 Tool Provider
 *
 * @link https://www.egroupware.org
 * @package smallpart
 * @subpackage lti
 * @author Ralf Becker <rb@egroupware.org>
 * @copyright 2020 by Ralf Becker <rb@egroupware.org>
 * @license https://spdx.org/licenses/AGPL-3.0-or-later.html GNU Affero General Public License v3.0 or later
 */

namespace EGroupware\SmallParT\LTI;

use ceLTIc\LTI;
use ceLTIc\LTI\PlatformNonce;
use EGroupware\Api\Cache;
use EGroupware\SmallParT\Bo;

/**
 * LTI DataConnector
 */
class DataConnector extends LTI\DataConnector\DataConnector
{
	/**
	 * @var Bo
	 */
	protected $bo;

	protected $consumer_guid;

	public function __construct()
	{
		parent::__construct(null);

		$this->bo = new Bo();
	}

	/**
	 * Set consumer guid
	 *
	 * @param $guid
	 */
	public function setConsumerGuid($guid)
	{
		$this->consumer_guid = $guid;
	}

	/**
	 * Load tool consumer object by oauth_key
	 *
	 * No other consumer attribute like eg. the consumer_guid is already set!
	 *
	 * @param LTI\Platform $platform Platform object
	 *
	 * @return bool    True if the tool consumer object was successfully loaded
	 */
	public function loadPlatform($platform)
	{
		// LTI 1.3
		if (!empty($platform->platformId) && ($data = Config::read($platform->platformId)))
		{
			$platform->accessTokenUrl = $data['auth_token_url'];
			$platform->authenticationUrl = $data['auth_login_url'];
			$platform->authorizationServerId = $data['auth_server'];
			$platform->clientId = $data['client_id'];
			$platform->kid = $data['kid'];
			$platform->jku = $data['key_set_url'];
			$platform->enabled = true;
		}
		// LTI < 1.3 uses consumer-key / client-id
		elseif (!empty($key = $platform->getKey()))
		{
			if (preg_match('/^course_id=(\d+)$/', $key, $matches) &&
				($course = $this->bo->read($matches[1], false)))
			{
				$platform->secret = $course['course_secret'];
				$platform->enabled = !empty($course['course_secret']) && !$course['course_closed'];
			}
			elseif (($config = Config::readByOauthKey($key)))
			{
				$platform->secret = $config['oauth_key'];
				$platform->enabled = true;
			}
			else
			{
				error_log(__METHOD__ . "(constumer->getKey()=" . $platform->getKey() . ") returning FALSE");
				return false;
			}
		}
		else
		{
			error_log(__METHOD__ . "() returning FALSE");
			return false;
		}
		$now = time();
		$platform->created = $now;
		$platform->updated = $now;
		error_log(__METHOD__."() returning TRUE");
		return true;
	}

	/**
	 * Load nonce object.
	 *
	 * @param PlatformNonce $nonce Nonce object
	 *
	 * @return bool    True if the nonce object was successfully loaded
	 */
	public function loadPlatformNonce($nonce)
	{
		return $nonce->getValue() === Cache::getInstance(__CLASS__, 'nonce-'.$nonce->getPlatform()->platformId);
	}

	/**
	 * Save nonce object.
	 *
	 * @param PlatformNonce $nonce Nonce object
	 *
	 * @return bool    True if the nonce object was successfully saved
	 */
	public function savePlatformNonce($nonce)
	{
		Cache::setInstance(__CLASS__, 'nonce-'.$nonce->getPlatform()->platformId,
			$nonce->getValue(), $nonce->expires - time());

		return true;
	}

	/**
	 * Delete nonce object.
	 *
	 * @param PlatformNonce $nonce Nonce object
	 *
	 * @return bool    True if the nonce object was successfully deleted
	 */
	public function deletePlatformNonce($nonce)
	{
		Cache::unsetInstance(__CLASS__, 'nonce-'.$nonce->getPlatform()->platformId);

		return true;
	}

	/**
	 * Load user object by ltiUserId.
	 *
	 * No other attributes are available!
	 *
	 * @param UserResult $userresult UserResult object
	 *
	 * @return bool    True if the user object was successfully loaded
	 */
	public function loadUserResult($userresult)
	{
		error_log(__METHOD__."(".json_encode($userresult).")");

		return parent::loadUserResult($userresult);
	}

	/**
	 * Save user object.
	 *
	 * @param UserResult $userresult UserResult object
	 *
	 * @return bool    True if the user object was successfully saved
	 */
	public function saveUserResult($userresult)
	{
		error_log(__METHOD__."(".json_encode($userresult).")");

		return parent::saveUserResult($userresult);
	}
}