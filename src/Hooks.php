<?php
/**
 * EGroupware - SmallParT - hooks
 *
 * @link https://www.egroupware.org
 * @package smallpart
 * @subpackage setup
 * @license http://opensource.org/licenses/gpl-license.php GPL - GNU General Public License
 */

namespace EGroupware\SmallParT;

use EGroupware\Api;
use EGroupware\Api\Egw;
use EGroupware\Api\Acl;

class Hooks
{
	/**
	 * Hook to build sidebox- and admin-menu
	 *
	 * @param string|array $args hook args
	 */
	public static function menu($args)
	{
		$appname = Bo::APPNAME;
		$location = is_array($args) ? $args['location'] : $args;

		if ($location == 'sidebox_menu')
		{
			$file = [
				'Courses' => Egw::link('/index.php', [
					'menuaction' => 'smallpart.\\EGroupware\\SmallParT\\Student\\Ui.index',
					'course_id' => '',
					'ajax' => true,
				]),
				'Manage courses' => Egw::link('/index.php', [
					'menuaction' => Bo::APPNAME.'.'.Courses::class.'.index',
					'active' => $_GET['menuaction'] === Bo::APPNAME.'.'.Courses::class.'.index',
					'ajax' => 'true',
				]),
			];
			display_sidebox($appname, lang($GLOBALS['egw_info']['apps'][$appname]['title']).' '.lang('Menu'),$file);

			$manuals = [
				'Student manual' => Egw::link('/smallpart/doc/ManualUser/'),
				'Converting videofiles' => Egw::link('/smallpart/doc/ManualVideos/'),
			];
			display_sidebox($appname, lang('Help'), $manuals);
		}

		if ($GLOBALS['egw_info']['user']['apps']['admin'])
		{
			$file = [
				'Site Configuration' => Egw::link('/index.php', [
					'menuaction' => 'admin.admin_config.index',
					'appname' => Bo::APPNAME,
					'ajax' => 'true',
				]),
				'LTI Tool Configuration' => Egw::link('/index.php', [
					'menuaction' => Bo::APPNAME.'.'.LTI\Config::class.'.index',
					'ajax' => 'true',
				]),
				/*'Global Categories'  => Egw::link('/index.php', [
					'menuaction' => 'admin.admin_categories.index',
					'appname'    => $appname,
					'global_cats'=> True,
					'ajax' => 'true',
				]),*/
			];
			if ($location == 'admin')
			{
				display_section($appname,$file);
			}
			else
			{
				display_sidebox($appname,lang('Admin'),$file);
			}
		}
	}

	/**
	 * Add CSP font-src (by missusing csp-frame-src hook
	 *
	 * @param array $data
	 * @return array with frame sources
	 */
	public static function csp_frame_src(array $data)
	{
		Api\Header\ContentSecurityPolicy::add('font-src', 'self');
			// no use to be more specific, as 'self' get added anyway, if you add something
			// Api\Header\Http::fullUrl(Egw::Link('/smallpart/fonts')));

		// to be able to load videos from arbitrary sources
		Api\Header\ContentSecurityPolicy::add('media-src', 'https:');

		return [];
	}

	/**
	 * ACL rights and labels used
	 *
	 * @param string|array string with location or array with parameters incl. "location", specially "owner" for selected acl owner
	 * @return array Acl::(READ|ADD|EDIT|DELETE|PRIVAT|CUSTOM(1|2|3)) => $label pairs
	 */
	public static function acl_rights($params)
	{
		unset($params);	// not used, but default function signature for hooks
		return array(
			Acl::READ    => 'read',		// courses can be subscribed
			Acl::EDIT    => 'edit',		// courses can be edited / administrated
			Acl::DELETE  => 'delete',	// courses can be deleted
		);
	}
}
