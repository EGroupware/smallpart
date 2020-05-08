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
				'Courses' => Egw::link('/index.php', ['menuaction' => 'smallpart.\\EGroupware\\SmallParT\\Student\\Ui.index', 'appname' => Bo::APPNAME, 'ajax' => true]),
				'Manage courses' => Egw::link('/index.php', [
					'menuaction' => Bo::APPNAME.'.'.Courses::class.'.index',
					'ajax' => 'true',
				]),
			];
			if (Bo::isAdmin())
			{
				$file['Teachers: administration'] = Egw::link('/smallpart/Verwaltung.php');
			}
			//$file['Help'] = Egw::link('/smallpart/Manuals.php');

			display_sidebox($appname, lang($GLOBALS['egw_info']['apps'][$appname]['title']).' '.lang('Menu'),$file);

			$manuals = [
				'Student manual' => Egw::link('/smallpart/Manual_LivefeedbackPLUS.php'),
				'Converting videofiles' => Egw::link('/smallpart/ManualWinFF.php'),
			];
			display_sidebox($appname, lang('Help'), $manuals);
		}

		/*if ($GLOBALS['egw_info']['user']['apps']['admin'])
		{
			$file = [
				'Site Configuration' => Egw::link('/index.php', 'menuaction=admin.admin_config.index&appname='.$appname,'&ajax=true'),
				'Global Categories'  => Egw::link('/index.php', [
					'menuaction' => 'admin.admin_categories.index',
					'appname'    => $appname,
					'global_cats'=> True,
					'ajax' => 'true',
				])
			];
			if ($location == 'admin')
			{
				display_section($appname,$file);
			}
			else
			{
				display_sidebox($appname,lang('Admin'),$file);
			}
		}*/
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

		return [];
	}
}
