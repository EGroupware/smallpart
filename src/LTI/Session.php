<?php
/**
 * EGroupware - SmallParT - LTI Learning Tools Interoperatbility - Session
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

use EGroupware\Api\Egw;
use EGroupware\Api\Exception\NotFound;
use EGroupware\Api\Preferences;
use EGroupware\Api\Translation;
use IMSGlobal\LTI;

/**
 * Class Session
 *
 * @package EGroupware\SmallParT\LTI
 */
class Session
{
	const PLATFORM_CLAIM = 'https://purl.imsglobal.org/spec/lti/claim/tool_platform';
	const CUSTOM_CLAIM = 'https://purl.imsglobal.org/spec/lti/claim/custom';
	const PRESENTATION_CLAIM = 'https://purl.imsglobal.org/spec/lti/claim/launch_presentation';
	const ROLE_CLAIM = 'https://purl.imsglobal.org/spec/lti/claim/roles';
	const CONTEXT_CLAIM = 'https://purl.imsglobal.org/spec/lti/claim/context';

	/**
	 * @var array
	 *
	 * Launch data example from Moodle:
	 *
	 *	array (size=23)
	 *		'nonce' => string 'nonce-5ebff10bb8c374.49574140' (length=29)
	 *		'iat' => int 1589637388
	 *		'exp' => int 1589637448
	 *		'iss' => string 'https://office.egroupware.org/moodle' (length=36)
	 *		'aud' => string 'XtUSQFBVpO1m2b9' (length=15)
	 *		'https://purl.imsglobal.org/spec/lti/claim/deployment_id' => string '1' (length=1)
	 *		'https://purl.imsglobal.org/spec/lti/claim/target_link_uri' => string 'https://boulder.egroupware.org/egroupware/smallpart/' (length=52)
	 *		'sub' => string '2' (length=1)
	 *		'https://purl.imsglobal.org/spec/lti/claim/lis' => array (size=2)
	 *			'person_sourcedid' => string '' (length=0)
	 *			'course_section_sourcedid' => string '2' (length=1)
	 *		'https://purl.imsglobal.org/spec/lti/claim/roles' => array (size=3)
	 *			0 => string 'http://purl.imsglobal.org/vocab/lis/v2/institution/person#Administrator' (length=71)
	 *			1 => string 'http://purl.imsglobal.org/vocab/lis/v2/membership#Instructor' (length=60)
	 *			2 => string 'http://purl.imsglobal.org/vocab/lis/v2/system/person#Administrator' (length=66)
	 *		'https://purl.imsglobal.org/spec/lti/claim/context' => array (size=4)
	 *			'id' => string '3' (length=1)
	 *			'label' => string 'smallPART' (length=9)
	 *			'title' => string 'LTI smallPART von boulder.egroupware.org' (length=40)
	 *			'type' =>	array (size=1)
	 *				0 => string 'CourseSection' (length=13)
	 *		'https://purl.imsglobal.org/spec/lti/claim/resource_link' => array (size=2)
	 *			'title' => string 'Video' (length=5)
	 *			'id' => string '1' (length=1)
	 *		'https://purl.imsglobal.org/spec/lti-bos/claim/basicoutcomesservice' => array (size=2)
	 *			'lis_result_sourcedid' => string '{"data":{"instanceid":"1","userid":"2","typeid":"1","launchid":379287429},"hash":"6fcbf20e2b4e28ec298fb754eb122bf424c986d4186ae6642b8d72f4b49eb33c"}' (length=148)
	 *			'lis_outcome_service_url' => string 'https://office.egroupware.org/moodle/mod/lti/service.php' (length=56)
	 *		'given_name' => string 'Administrator/in' (length=16)
	 *		'family_name' => string 'Nutzer' (length=6)
	 *		'name' => string 'Administrator/in Nutzer' (length=23)
	 *		'https://purl.imsglobal.org/spec/lti/claim/ext' => array (size=2)
	 *			'user_username' => string 'admin' (length=5)
	 *			'lms' => string 'moodle-2' (length=8)
	 *		'email' => string 'admin@egroupware.org' (length=20)
	 *		'https://purl.imsglobal.org/spec/lti/claim/launch_presentation' => array (size=3)
	 *			'locale' => string 'de' (length=2)
	 *			'document_target' => string 'iframe' (length=6)
	 *			'return_url' => string 'https://office.egroupware.org/moodle/mod/lti/return.php?course=3&launch_container=3&instanceid=1&sesskey=yryiu9QuVW' (length=115)
	 *		'https://purl.imsglobal.org/spec/lti/claim/tool_platform' => array (size=5)
	 *			'family_code' => string 'moodle' (length=6)
	 *			'version' => string '2019111803.02' (length=13)
	 *			'guid' => string 'office.egroupware.org' (length=21)
	 *			'name' => string 'Test-Moodle' (length=11)
	 *			'description' => string 'Ralf's Test-Moodle' (length=18)
	 *		'https://purl.imsglobal.org/spec/lti/claim/version' => string '1.3.0' (length=5)
	 *		'https://purl.imsglobal.org/spec/lti/claim/message_type' => string 'LtiResourceLinkRequest' (length=22)
	 *		'https://purl.imsglobal.org/spec/lti/claim/custom' => array (size=1)
	 *			'course_id' => string '38' (length=2)
	 */
	protected $data;

	/**
	 * @var string $account_lid of user
	 */
	protected $account_lid;

	/**
	 * @var int $account_id of user
	 */
	protected $account_id;

	/**
	 * @var LTI\LTI_Message_Launch
	 */
	protected $launch;

	/**
	 * @var Database
	 */
	protected $database;

	/**
	 * @var Egw
	 */
	protected $egw;

	/**
	 * Session constructor.
	 *
	 * @param LTI\LTI_Message_Launch $launch
	 * @param Database $database
	 */
	public function __construct(LTI\LTI_Message_Launch $launch, Database $database)
	{
		$this->launch = $launch;
		$this->data = $launch->get_launch_data();
		$this->database = $database;

		$this->egw = $GLOBALS['egw'];
	}

	/**
	 * Maximum account-name length in EGroupware
	 */
	const MAX_NAME_LENGHT = 64;
	/**
	 * Group for teachers/instructors created on installation
	 */
	const TEACHERS_GROUP = 'Teachers';

	/**
	 * Create EGroupware session
	 *
	 * Creates EGroupware account, if not yet existing, and then a session
	 */
	public function create()
	{
		if (!($config = Config::read($this->data['iss'])))
		{
			throw new \Exception("No LTI configuration for {$this->data['iss']} found!");
		}

		// should we first search for an existing account by it's email address
		if (!empty($config['check_email_first']) &&
			($this->account_id = $this->egw->accounts->name2id($this->data['email'], 'account_email')))
		{
			$this->account_lid = $this->egw->accounts->id2name($this->account_id);
		}
		else
		{
			$this->account_lid = $this->username($config['account_name'], $config['account_prefix']);
			$this->account_id = $this->checkCreateAccount();
		}

		// create egroupware session for user
		if (!$this->egw->session->create($this->account_lid, '', '', false, false))
		{
			throw new \Exception("Could not create session for LTI launch: ".$this->egw->session->reason);
		}

		// check if a local is given and different from EGroupware
		$this->checkSetLocale();

		// check if user is an instructor and add him to our "Teachers" group
		if (($teachers_group = $this->egw->accounts->name2id(self::TEACHERS_GROUP)) &&
			$this->isInstructor() !== (($key = array_search($teachers_group,
				($memberships = $this->egw->accounts->memberships($this->account_id, true)), false)) !== false))
		{
			if ($this->isInstructor())
			{
				$memberships[] = $teachers_group;
			}
			else
			{
				unset($memberships[$key]);
			}
			$this->egw->accounts->set_memberships($memberships, $this->account_id);
		}
	}

	/**
	 * Get issuer / URL of LMS plattform
	 *
	 * @return string
	 */
	public function getIssuer()
	{
		return $this->data['iss'];
	}

	/**
	 * Get presentation data from launch
	 *
	 * @return array|null array with values for keys ""
	 */
	public function getPresentation()
	{
		return $this->data[self::PRESENTATION_CLAIM];
	}

	/**
	 * Get context from launch
	 *
	 * @return array|null array with values for keys "id", "label", "title", "type" (array of strings eg. "CourseSection")
	 */
	public function getContext()
	{
		return $this->data[self::CONTEXT_CLAIM];
	}

	/**
	 * Get custom data from launch
	 *
	 * @return array|null
	 */
	public function getCustomData()
	{
		return $this->data[self::CUSTOM_CLAIM];
	}

	const ROLE_INSTRUCTOR = 'http://purl.imsglobal.org/vocab/lis/v2/membership#Instructor';

	/**
	 * Check if current user is an instructor
	 *
	 * @return bool
	 */
	public function isInstructor()
	{
		return in_array(self::ROLE_INSTRUCTOR, $this->getRoles() ?: []);
	}

	/**
	 * Get role(s) of current user
	 *
	 * @return array|null
	 */
	public function getRoles()
	{
		return $this->data[self::ROLE_CLAIM];
	}

	/**
	 * Get plattform data from launch
	 *
	 * @return array|null with values for keys "family_code" (eg. "moodle"), "version", "guid" (full qualified host name)
	 * 	"name" (short name) and "description"
	 */
	public function getPlattformData()
	{
		return $this->data[self::PLATFORM_CLAIM];
	}

	/**
	 * Get full launch object
	 *
	 * @return LTI\LTI_Message_Launch
	 */
	public function getLaunch()
	{
		return $this->launch;
	}

	/**
	 * Generate EGroupware username from launch-data using given configuration
	 *
	 * @param array|string[] $account_name default ['sub','host']
	 * @param string $account_prefix
	 * @return string account_lid according to given parameters (or hash of it, if to long)
	 */
	protected function username(array $account_name=['sub','host'], $account_prefix='')
	{
		// try "sub-host.domain.org"
		$parts = [];
		if (!empty($account_prefix)) $parts['prefix'] = $account_prefix;
		foreach($account_name as $part)
		{
			$parts[$part] = $part !== 'host' ? $this->data[$part] :
				parse_url($this->data['iss'], PHP_URL_HOST);
		}
		$name = implode('-', $parts);

		// if generated name is to long, we have to hash it
		if (strlen($name) > self::MAX_NAME_LENGHT)
		{
			// try keep prefix, if one is specified
			if (!empty($account_prefix) && strlen($new_name = $account_prefix.'-'.sha1($name)) <= self::MAX_NAME_LENGHT)
			{
				$name = $new_name;
			}
			else
			{
				// last resort hash everything
				$name = sha1($name);
			}
		}
		// should we lowercase the name
		if ($GLOBALS['egw_info']['server']['auto_create_acct'] === 'lowercase')
		{
			$name = strtolower($name);
		}
		return $name;
	}

	/**
	 * Check if there is user already has an account, if not create it
	 *
	 * @return int existing or new created account_id
	 * @throws \Exception
	 */
	protected function checkCreateAccount()
	{
		if (!($account_id = $this->egw->accounts->name2id($this->account_lid)))
		{
			$GLOBALS['egw_create_acct'] = [
				'firstname' => $this->data['given_name'],
				'lastname'  => $this->data['family_name'],
				'email'     => $this->data['email'],
				//'primary_group' =>
			];
			if (!($account_id = $this->egw->accounts->auto_add($this->account_lid, '')))
			{
				throw new \Exception("Could not create account '$this->account_lid' for LTI launch!");
			}
		}
		return $account_id;
	}

	/**
	 * Check if locale (language and country) is given in launch data and match that of user, if not set it accordingly
	 */
	protected function checkSetLocale()
	{
		$locale = $this->data[self::PRESENTATION_CLAIM]['locale'];
		if (empty($locale)) return;

		list($locale_lang, $locale_country) = explode('_', strtolower($locale));
		$user_lang = $GLOBALS['egw_info']['user']['preferences']['common']['lang'];

		$egw_langs = Translation::get_available_langs(false);
		// eg. pt-br, zh-tw
		if (isset($egw_langs[$locale_lang.'-'.$locale_country]))
		{
			$lang = $locale_lang.'-'.$locale_country;
		}
		// most translations: eg. de, fr, ...
		elseif (isset($egw_langs[$locale_lang]))
		{
			$lang = $locale_lang;
		}
		// es-es
		elseif (isset($egw_langs[$locale_lang.'-'.$locale_lang]))
		{
			$lang = $locale_lang.'-'.$locale_lang;
		}

		// if we found a matching language different from users preference, set it now and refresh session with it
		if (isset($lang) && $lang !== $user_lang)
		{
			/** @var Preferences $prefs */
			$prefs = $this->egw->preferences;
			$prefs->add('common', 'lang', $lang, 'user');
			$prefs->save_repository();
			$GLOBALS['egw_info']['user']['preferences']['common']['lang'] = $lang;
			Translation::init();
		}
	}
}