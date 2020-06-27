<?php

namespace EGroupware\SmallParT;

/*
define("TOOL_HOST", ($_SERVER['HTTP_X_FORWARDED_PROTO'] ?: $_SERVER['REQUEST_SCHEME']) . '://' . $_SERVER['HTTP_HOST']);
session_start();
*/

use EGroupware\OpenID\Keys;
use \IMSGlobal\LTI;

/*
$this->db = [];
$reg_configs = array_diff(scandir(__DIR__ . '/configs'), array('..', '.', '.DS_Store'));
foreach ($reg_configs as $key => $reg_config) {
	$this->db = array_merge($this->db, json_decode(file_get_contents(__DIR__ . "/configs/$reg_config"), true));
}*/

class LtiDatabase implements LTI\Database
{
	private $db;

	function __construct()
	{
		/* "http://localhost:9001": {
			"client_id": "d42df408-70f5-4b60-8274-6c98d3b9468d",
			"auth_login_url": "http://localhost:9001/platform/login.php",
			"auth_token_url": "http://localhost/platform/token.php",
			"key_set_url": "http://localhost/platform/jwks.php",
			"private_key_file": "/private.key",
			"kid": "58f36e10-c1c1-4df0-af8b-85c857d1634f",
			"deployment": [
				"8c49a5fa-f955-405e-865f-3d7e959e809f"
			]
		} */
		/* Login: POST /egroupware/smallpart/lti-login.php HTTP/2.0
			iss: https://office.egroupware.org/moodle
			target_link_uri: https://boulder.egroupware.org/egroupware/smallpart/
			login_hint: 2
			lti_message_hint: 1
		*/
		$this->db['https://office.egroupware.org/moodle'] = [
			'client_id' => 'XtUSQFBVpO1m2b9',
			'auth_login_url' => 'https://office.egroupware.org/moodle/mod/lti/auth.php',
			'auth_token_url' => 'https://office.egroupware.org/moodle/mod/lti/token.php',
			'key_set_url' => 'https://office.egroupware.org/moodle/mod/lti/certs.php',
			'client_id' => 'XtUSQFBVpO1m2b9',
			// all the above commes from the LMS platform eg. in Moodle Administration > Plugins > External Tools
			'auth_server' => null,
			'kid' => 'test',
			'deployment' => [
				"1"
			],
		];
	}

	public function find_registration_by_issuer($iss)
	{
		if (empty($this->db) || empty($this->db[$iss])) 
		{
			return false;
		}
		return LTI\LTI_Registration::new()
			->set_auth_login_url($this->db[$iss]['auth_login_url'])
			->set_auth_token_url($this->db[$iss]['auth_token_url'])
			->set_auth_server($this->db[$iss]['auth_server'])
			->set_client_id($this->db[$iss]['client_id'])
			->set_key_set_url($this->db[$iss]['key_set_url'])
			->set_kid($this->db[$iss]['kid'])
			->set_issuer($iss)
			->set_tool_private_key($this->private_key($iss));
	}

	public function find_deployment($iss, $deployment_id)
	{
		if (!in_array($deployment_id, $this->db[$iss]['deployment'])) {
			return false;
		}
		return LTI\LTI_Deployment::new()
			->set_deployment_id($deployment_id);
	}

	private function private_key($iss)
	{
		return (new Keys())->getPrivateKeyString();
	}
}