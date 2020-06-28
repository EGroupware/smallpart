<?php
/**
 * EGroupware - SmallParT - setup definitions
 *
 * @link https://www.egroupware.org
 * @package smallpart
 * @subpackage setup
 * @license http://opensource.org/licenses/gpl-license.php GPL - GNU General Public License
 */

use EGroupware\SmallParT\Hooks;
use EGroupware\SmallParT\Student\Ui;

$setup_info['smallpart']['name'] = 'smallpart';
$setup_info['smallpart']['title'] = 'smallPART';
$setup_info['smallpart']['version'] = '1.2';
$setup_info['smallpart']['app_order'] = 5;
$setup_info['smallpart']['enable'] = 1;
$setup_info['smallpart']['autoinstall'] = true;	// install automatically on update
$setup_info['smallpart']['tables'] = array('egw_smallpart_courses','egw_smallpart_participants','egw_smallpart_videos','egw_smallpart_lastvideo','egw_smallpart_comments');
$setup_info['smallpart']['index'] = 'smallpart.'.Ui::class.'.index&ajax=true';

$setup_info['smallpart']['author'] =
$setup_info['smallpart']['maintainer'] = array(
	'name' => 'Arash Tolou',
	'email' => 'arashtolou@gmail.com',
);
$setup_info['smallpart']['description'] =
	'smallPART - selfdirected media assisted learning lectures & Process Analysis Reflection Tool';

// Hooks we implement
$setup_info['smallpart']['hooks']['sidebox_menu'] = Hooks::class.'::menu';
$setup_info['smallpart']['hooks']['admin'] = Hooks::class.'::menu';
$setup_info['smallpart']['hooks']['csp-frame-src'] = Hooks::class.'::csp_frame_src';
$setup_info['smallpart']['hooks']['acl_rights'] = Hooks::class.'::acl_rights';
$setup_info['smallpart']['hooks']['settings'] = Hooks::class.'::settings';
$setup_info['smallpart']['hooks']['search_link'] = Hooks::class.'::search_link';

/* Dependencies for this app to work */
$setup_info['smallpart']['depends'][] = array(
	'appname' => 'api',
	'versions' => Array('20.1')
);
