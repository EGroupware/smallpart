<?php
/**
 * EGroupware - SmallParT - LTI Learning Tools Interoperatbility - Configuration
 *
 * @link https://www.egroupware.org
 * @package smallpart
 * @subpackage lti
 * @author Ralf Becker <rb@egroupware.org>
 * @copyright 2020 by Ralf Becker <rb@egroupware.org>
 * @license http://opensource.org/licenses/gpl-license.php GPL - GNU General Public License
 */

namespace EGroupware\SmallParT\LTI;

require_once __DIR__ . '/../../vendor/autoload.php';

use EGroupware\OpenID\Keys;
use \IMSGlobal\LTI;

/**
 * Stores configuration data of LMS plattforms allowed to send LTI tool launches
 *
 * @package EGroupware\SmallParT\LTI
 */
class Database implements LTI\Database
{
	/**
	 * Get registration data by issuer / LMS plattform
	 *
	 * @param $iss issuer / lms plattform
	 * @return bool|LTI\LTI_Registration
	 * @throws \Exception
	 */
	public function find_registration_by_issuer($iss)
	{
		if (!($data = Config::read($iss)))
		{
			return false;
		}

		return LTI\LTI_Registration::new()
			->set_auth_login_url($data['auth_login_url'])
			->set_auth_token_url($data['auth_token_url'])
			->set_auth_server($data['auth_server'])
			->set_client_id($data['client_id'])
			->set_key_set_url($data['key_set_url'])
			->set_kid($data['kid'])
			->set_issuer($iss)
			->set_tool_private_key($this->private_key($iss));
	}

	/**
	 * Get / check deployment id
	 *
	 * @param string $iss issuer / lms plattform
	 * @param string $deployment_id
	 * @return bool|LTI\LTI_Deployment
	 */
	public function find_deployment($iss, $deployment_id)
	{
		if (!($data = Config::read($iss)) || !in_array($deployment_id, $data['deployment'])) {
			return false;
		}
		return LTI\LTI_Deployment::new()
			->set_deployment_id($deployment_id);
	}

	/**
	 * Get private key for JWT signing
	 *
	 * We use the key / cert from our OpenID Connect server for now
	 *
	 * @param string $iss issuer / lms plattform
	 * @return string
	 * @throws \Exception
	 */
	private function private_key($iss)
	{
		return (new Keys())->getPrivateKeyString();
	}
}