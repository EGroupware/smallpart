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

use EGroupware\Api\Egw;
use EGroupware\Api\Exception\NotFound;
use EGroupware\Api\Header\ContentSecurityPolicy;
use EGroupware\Api\Preferences;
use EGroupware\Api\Translation;
use IMSGlobal\LTI;

/**
 * Class Session
 *
 * @package EGroupware\SmallParT\LTI
 */
abstract class BaseSession
{
	/**
	 * @var string $account_lid of user
	 */
	protected $account_lid;

	/**
	 * @var int $account_id of user
	 */
	protected $account_id;

	/**
	 * Reference to global egw object
	 *
	 * @var Egw
	 */
	protected $egw;

	/**
	 * LTI 1.3 issuer (URL of lms), LTI 1.0 plattform guid (hostname of lms)
	 *
	 * @var string
	 */
	protected $iss;
	/**
	 * username of lms
	 *
	 * @var string
	 */
	protected $username;
	/**
	 * globally unique identifier eg. "school.edu:user"
	 *
	 * @var string
	 */
	protected $lis_person_sourcedid;
	protected $firstname;
	protected $lastname;
	protected $email;
	/**
	 * Username from Moodle via LTI 1.3 send via https://purl.imsglobal.org/spec/lti/claim/ext
	 *
	 * @var string
	 */
	protected $user_username;
	/**
	 * Presentation locale
	 *
	 * @var string
	 */
	protected $locale;
	protected $lti_version='1.3';

	/**
	 * Session constructor.
	 *
	 * @param LTI\LTI_Message_Launch $launch
	 * @param Database $database
	 */
	/**
	 * BaseSession constructor.
	 * @param string $iss 1.3 issuer or 1.0 plattform guid
	 * @param string $username username of the lms
	 * @param string $lis_person_sourcedid globally unique identifier eg. "school.edu:user"
	 * @param string $firstname
	 * @param string $lastname
	 * @param string $email
	 * @param string $locale
	 * @param string $lti_version='1.3' '1.0' or '1.3'
	 * @param ?string $user_username=null user_username from Moodle via LTI 1.3 send as ext claim
	 */
	public function __construct($iss, $username, $lis_person_sourcedid, $firstname, $lastname, $email, $locale, $lti_version='1.3', $user_username=null)
	{
		$this->iss = $iss;
		$this->username = $username;
		$this->lis_person_sourcedid = $lis_person_sourcedid;
		$this->firstname = $firstname;
		$this->lastname = $lastname;
		$this->email = $email;
		$this->locale = $locale;
		$this->lti_version = $lti_version;
		$this->user_username = $user_username;

		$this->egw = $GLOBALS['egw'];

		// allow framing by LMS
		ContentSecurityPolicy::add('frame-ancestors', $this->getFrameAncestor());
	}

	/**
	 * Check if current user is an instructor
	 *
	 * @return bool
	 */
	abstract public function isInstructor();

	/**
	 * Get framing site to set frame-ancestor CSP policy
	 *
	 * @return string
	 */
	abstract public function getFrameAncestor();

	/**
	 * Get custom data from launch
	 *
	 * @return array|null
	 */
	abstract public function getCustomData();

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
		if (!($config = Config::read($this->iss, $this->lti_version)))
		{
			throw new \Exception("No LTI configuration for {$this->iss} found!");
		}

		// should we first search for an existing account by it's email address
		if (!empty($config['check_email_first']) &&
			($this->account_id = $this->egw->accounts->name2id($this->email, 'account_email')))
		{
			$this->account_lid = $this->egw->accounts->id2name($this->account_id);
		}
		elseif (!empty($config['check_account_description']) &&
			($this->account_id = $this->egw->accounts->name2id($this->lis_person_sourcedid, 'account_description')))
		{
			$this->account_lid = $this->egw->accounts->id2name($this->account_id);
		}
		else
		{
			$this->account_lid = $this->username($config['account_name'], $config['account_prefix']);
			$this->account_id = $this->checkCreateAccount();
		}

		// Set SameSite attribute for cookies, as LTI embeding does NOT work without
		$GLOBALS['egw_info']['server']['cookie_samesite_attribute'] = 'None';

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
		return $this->iss;
	}

	/**
	 * Generate EGroupware username from launch-data using given configuration
	 *
	 * @param array values for keys 'iss'
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
			switch($part)
			{
				case 'sub':
					$parts[$part] = $this->username;
					break;
				case 'host':
					$parts[$part] = parse_url($this->iss, PHP_URL_HOST) ?: $this->iss;
					break;
				default:
					$parts[$part] = $this->$part;
					break;
			}
		}
		$name = implode('-', array_diff($parts, ['', null]));

		if (empty($name))
		{
			throw new \Exception("All configured username parts are empty!");
		}

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
			$GLOBALS['auto_create_acct'] = [
				'firstname' => $this->firstname,
				'lastname'  => $this->lastname,
				'email'     => $this->email,
				//'primary_group' =>
			];
			if (!($account_id = $this->egw->accounts->auto_add($this->account_lid, '')))
			{
				throw new \Exception("Could not create account '$this->account_lid' for LTI launch!");
			}
		}
		// fix not set names and email
		foreach(['firstname' => 'New', 'lastname' => 'User', 'email' => null] as $name => $empty)
		{
			if (!empty($this->$name) && $this->egw->accounts->id2name($account_id, 'account_'.$name) == $empty)
			{
				if (!isset($account)) $account = $this->egw->accounts->read($account_id);
				$account['account_'.$name] = $this->$name;
			}
		}
		if (isset($account)) $this->egw->accounts->save($account);
		return $account_id;
	}

	/**
	 * Check if locale (language and country) is given in launch data and match that of user, if not set it accordingly
	 */
	protected function checkSetLocale()
	{
		if (empty($this->locale)) return;

		list($locale_lang, $locale_country) = explode('_', strtolower($this->locale));
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