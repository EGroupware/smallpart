<?php
/**
 * EGroupware - SmallParT - hooks
 *
 * @link https://www.egroupware.org
 * @package smallpart
 * @subpackage setup
 * @license https://spdx.org/licenses/AGPL-3.0-or-later.html GNU Affero General Public License v3.0 or later
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
					'ajax' => 'true',
				]),
			];
			if (Bo::checkTeacher())
			{
				$file['Tests'] = Egw::link('/index.php', [
					'menuaction' => Bo::APPNAME.'.'.Questions::class.'.index',
					'ajax' => 'true',
				]);
				$file['Scores'] = Egw::link('/index.php', [
					'menuaction' => Bo::APPNAME.'.'.Questions::class.'.scores',
					'ajax' => 'true',
				]);
			}
			$file['Manage courses'] = Egw::link('/index.php', [
				'menuaction' => Bo::APPNAME.'.'.Courses::class.'.index',
				'active' => $_GET['menuaction'] === Bo::APPNAME.'.'.Courses::class.'.index',
				'ajax' => 'true',
			]);
			display_sidebox($appname, lang($GLOBALS['egw_info']['apps'][$appname]['title']).' '.lang('Menu'),$file);

			$manuals = [
				'Student manual' => Egw::link('/smallpart/doc/ManualUser/'),
				'Converting videofiles' => Egw::link('/smallpart/doc/ManualVideos/'),
				[
					'text' => 'Using smallPART via LTI',
					'link' => 'https://github.com/EGroupware/egroupware/wiki/SmallPART',
					'target' => '_blank',
				],
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
				'Global Categories'  => Egw::link('/index.php', [
					'menuaction' => 'admin.admin_categories.index',
					'appname'    => $appname,
					'global_cats'=> True,
					'ajax' => 'true',
				]),
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

		// Include custom theme
		if ($theme = $GLOBALS['egw_info']['user']['preferences']['smallpart']['theme']) Api\Framework::includeCSS(Bo::APPNAME, $theme);

		// if enabled, add CSP for Youtube videos
		$config = Api\Config::read(Bo::APPNAME);
		if (!empty($config['youtube_videos']))
		{
			Api\Header\ContentSecurityPolicy::add('script-src', 'https://www.youtube.com');

			return ['https://www.youtube.com'];
		}
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

	public static function settings ()
	{
		$themes = ['' => lang('default'), 'theme1' => lang('theme1'), 'theme2' => lang('theme2')];
		if ($GLOBALS['egw_info']['user']['apps']['stylite'])
		{
			$i = 3;
			$exist = true;
			while ($exist)
			{
				$exist = Api\Vfs::file_exists('/etemplates/smallpart/templates/default/student.index.theme'.$i.'.xet');
				if ($exist) $themes = array_merge($themes, ['theme'.$i => lang('theme'.$i)]);
				$i++;
			}
		}

		return [
			'theme' => [
				'type' => 'select',
				'label' => 'Themes',
				'name' => 'theme',
				'values' => $themes,
				'help' => '',
				'xmlrpc' => false,
				'admin' => false,
				'default' => '',
			]
		];
	}

	/**
	 * Hook called by link-class to include smallPART courses in the appregistry of the linkage
	 *
	 * @param array|string $location location and other parameters (not used)
	 * @return array with method-names
	 */
	static function search_link($location)
	{
		unset($location);	// not used, but required by function signature

		return array(
			'query' => Bo::APPNAME.'.'.Bo::class.'.link_query',
			'title' => Bo::APPNAME.'.'.Bo::class.'.link_title',
			'view'  => array(
				'menuaction' => Bo::APPNAME.'.'.Student\Ui::class.'.index',
				'ajax' => 'true',
			),
			'view_id' => 'course_id',
			'edit'  => array(
				'menuaction' => Bo::APPNAME.'.'.Courses::class.'.edit',
			),
			'edit_id' => 'course_id',
			'edit_popup'  => '800x600',
			'list' => array(
				'menuaction' => Bo::APPNAME.'.'.Courses::class.'.index',
				'ajax' => 'true'
			),
			'add' => array(
				'menuaction' => Bo::APPNAME.'.'.Courses::class.'.edit',
			),
			'add_popup'  => '800x600',
			'no_quick_add' => true,
			'file_access' => Bo::class.'::file_access',
			'file_access_user' => true,	// file_access supports 4th parameter $user
			'additional' => [
				Overlay::SUBTYPE => [
					'edit'  => array(
						'menuaction' => Bo::APPNAME.'.'.Questions::class.'.edit',
					),
					'edit_id' => 'overlay_id',
					'edit_popup'  => '800x600',
					'list' => array(
						'menuaction' => Bo::APPNAME.'.'.Questions::class.'.index',
						'ajax' => 'true'
					),
					'add_popup'  => '800x600',
					'no_quick_add' => true,
				],
			],
		);
	}
}
