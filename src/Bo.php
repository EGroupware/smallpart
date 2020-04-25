<?php
/**
 * EGroupware - SmallParT - bussiness logic
 *
 * @link https://www.egroupware.org
 * @package smallpart
 * @subpackage setup
 * @license http://opensource.org/licenses/gpl-license.php GPL - GNU General Public License
 */

namespace EGroupware\SmallParT;

use EGroupware\Api;

/**
 * SmallParT - bussiness logic
 *
 * Mapping existing $_SESSION-variables
 *
 * - int $_SESSION['userid']: $GLOBALS['egw_info']['user']['account_id']
 * - string $_SESSION['nickname']: Bo::getNickname()
 * - bool $_SESSION['superadmin']: Bo::isSuperAdmin()
 * - $_SESSION['userrole'] === 'Admin': Bo::isAdmin()
 * - string $_SESSION['userorganisation']: Bo::getOrganisation()
 * - string $_SESSION['useremail']: Bo::getContact('email')
 *
 * - string $_SESSION['ScriptLoaded']
 */
class Bo
{
	const APPNAME = 'smallpart';
	const ACL_ADMIN_LOCATION = 'admin';

	/**
	 * Current user is an admin / can create lectures
	 *
	 * @return bool
	 */
	public static function isAdmin()
	{
		return self::isSuperAdmin() ||
			$GLOBALS['egw']->acl->get_rights(self::ACL_ADMIN_LOCATION, self::APPNAME);
	}

	/**
	 * Set or remove admin rights / rights to create lectures
	 *
	 * @param int $account_id
	 * @param bool $allow true: make user and admin, false: remove admin rights
	 */
	public static function setAdmin($account_id, $allow=true)
	{
		if ($allow)
		{
			$GLOBALS['egw']->acl->add_repository(self::APPNAME, self::ACL_ADMIN_LOCATION, $account_id, 1);
		}
		else
		{
			$GLOBALS['egw']->acl->delete_repository(self::APPNAME, self::ACL_ADMIN_LOCATION, $account_id);
		}
	}

	/**
	 * Check if a given user is an admin / can create lectures
	 *
	 * @param int $account_id
	 * @return bool
	 */
	public static function checkAdmin($account_id)
	{
		static $admins;
		if (!isset($admins))
		{
			$admins = $GLOBALS['egw']->acl->get_ids_for_location(self::ACL_ADMIN_LOCATION, 1, self::APPNAME);
		}
		return in_array($account_id, $admins);
	}

	/**
	 * Current user can edit accounts or reset passwords
	 *
	 * @return bool
	 */
	public static function isSuperAdmin()
	{
		return !empty($GLOBALS['egw_info']['user']['apps']['admin']);
	}

	/**
	 * Get nickname of a user
	 *
	 * We might need to change that in future, as account_lid might be needed as unique name for Shibboleth auth.
	 *
	 * @param int $account_id =null default current user
	 * @return string
	 */
	public static function getNickname($account_id=null)
	{
		return !isset($account_id) ? $GLOBALS['egw_info']['user']['account_lid'] :
			Api\Accounts::id2name($account_id, 'account_lid');
	}

	/**
	 * Contact data of current user use via Bo::getContact($name)
	 *
	 * @var array
	 */
	protected static $contact;

	/**
	 * Get contact-data of current user
	 *
	 * @param string $name ='org_name'
	 * @return string|null
	 */
	public static function getContact($name='org_name')
	{
		if (!isset(self::$contact))
		{
			$contacts = new Api\Contacts();
			self::$contact = $contacts->read('account:'.$GLOBALS['egw_info']['user']['account_id']);
		}
		return is_array(self::$contact) ? self::$contact[$name] : null;
	}

	/**
	 * Get organisation name of current user
	 *
	 * @return string|null
	 */
	public static function getOrganisation()
	{
		return self::getContact('org_name');
	}
}