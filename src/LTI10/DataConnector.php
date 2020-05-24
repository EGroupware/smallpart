<?php
/**
 * EGroupware - SmallParT - LTI Learning Tools Interoperatbility - LTI v1.0 Tool Provider
 *
 * @link https://www.egroupware.org
 * @package smallpart
 * @subpackage lti
 * @author Ralf Becker <rb@egroupware.org>
 * @copyright 2020 by Ralf Becker <rb@egroupware.org>
 * @license http://opensource.org/licenses/gpl-license.php GPL - GNU General Public License
 */

namespace EGroupware\SmallParT\LTI10;

require_once __DIR__ . '/../../vendor/autoload.php';

use ceLTIc\LTI;
use EGroupware\SmallParT\Bo;
use EGroupware\SmallParT\LTI\Config;

/**
 * LTI v1.0 DataConector
 *
 * @package EGroupware\SmallParT\LTI10
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
	 * @param LTI\ToolConsumer $consumer ToolConsumer object
	 *
	 * @return bool    True if the tool consumer object was successfully loaded
	 */
	public function loadToolConsumer(LTI\ToolConsumer $consumer)
	{
		if (preg_match('/^course_id=(\d+)$/', $consumer->getKey(), $matches) &&
			($course = $this->bo->read($matches[1], false)))
		{
			$consumer->secret = $course['course_secret'];
			$consumer->enabled = !empty($course['course_secret']) && !$course['course_closed'];
		}
		elseif (($config = Config::readByOauthKey($consumer->getKey())))
		{
			$consumer->secret = $config['oauth_key'];
			$consumer->enabled = true;
		}
		else
		{
			error_log(__METHOD__."(constumer->getKey()=".$consumer->getKey().") returning FALSE");
			return false;
		}
		$now = time();
		$consumer->created = $now;
		$consumer->updated = $now;
		error_log(__METHOD__."(constumer->getKey()=".$consumer->getKey().") returning TRUE");
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