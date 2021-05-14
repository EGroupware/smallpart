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
use ceLTIc\LTI\Platform;
use ceLTIc\LTI\PlatformNonce;
use EGroupware\Api;
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
		$platform->ltiVersion = !empty($platform->platformId) ? '1.3' : '1.0';
		if ($data = Config::read($platform->platformId ?: $_POST['tool_consumer_instance_guid'],
			$platform->ltiVersion, $platform->getKey()))
		{
			$platform->setRecordId($data['iss'] . ':' . $data['lti_version']);
			$platform->enabled = empty($data['disabled']);
			$platform->created = (new Api\DateTime($data['created'] ?: 'now'))->getTimestamp();

			if (!empty($platform->platformId) && $platform->ltiVersion === '1.3')
			{
				$platform->accessTokenUrl = $data['auth_token_url'];
				$platform->authenticationUrl = $data['auth_login_url'];
				$platform->authorizationServerId = $data['auth_server'];
				$platform->clientId = $data['client_id'];
				$platform->kid = $data['kid'];
				$platform->jku = $data['key_set_url'];
			}
			// LTI < 1.3 uses consumer-key / client-id
			elseif (!empty($key = $platform->getKey()) &&
				preg_match('/^course_id=(\d+)$/', $key, $matches) &&
				($course = $this->bo->read($matches[1], false)))
			{
				$platform->secret = $data['oauth_secret'] ?: $course['course_secret'];
				if ($course['course_closed']) $platform->enabled = false;
			}
			else
			{
				error_log(__METHOD__ . "() returning FALSE");
				return false;
			}
		}
		else
		{
			error_log(__METHOD__ . "() returning FALSE");
			return false;
		}
		$platform->updated = time();
		error_log(__METHOD__."() returning TRUE");
		return true;
	}

	/**
	 * Save platform object.
	 *
	 * @param Platform $platform  Platform object
	 *
	 * @return bool    True if the platform object was successfully saved
	 */
	public function savePlatform($platform)
	{
		$platform->updated = time();

		if (!$platform->getRecordId())
		{
			$lti_version = substr($platform->ltiVersion, 0, 3);	// 1.3 or 1.0 used in EGroupware
			$iss = substr($platform->platformId, 0, 28).':'.$lti_version;
			// check if platform is already registered
			if (($data = Config::read($platform->platformId ?: $platform->consumerGuid, $lti_version)))
			{
				if ($data['client_id'] !== $platform->clientId)
				{
					throw new \Exception(lang("Platform already registered with this tool!"));
				}
				// store new deploymentId (not setting disabled!)
				if (!in_array($platform->deploymentId, $data['deployment']))
				{
					$data['deployment'][] = $platform->deploymentId;
				}
			}
			else
			{
				$data = [
					'iss'            => $platform->platformId ?: $platform->consumerGuid,	// LTI 1.3 ?: < 1.3
					'lti_version'    => $lti_version,
					// LTI 1.3
					'auth_token_url' => $platform->accessTokenUrl,
					'auth_login_url' => $platform->authenticationUrl,
					'auth_server'    => $platform->authorizationServerId,
					'client_id'      => $platform->clientId,
					'deployment'     => [$platform->deploymentId],
					'kid'            => $platform->kid,
					'key_set_url'    => $platform->jku,
					'disabled'       => !$platform->enabled,
					'check_email_first' => true,
					'account_name'   => ['user_username'],	// Moodle username
					'created'        => new Api\DateTime(),
					'updated'        => new Api\DateTime(),
				];
			}
			Api\Config::save_value($iss, $data, 'smallpart');
			$platform->setRecordId($iss);
		}
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