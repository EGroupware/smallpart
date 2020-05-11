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
$setup_info['smallpart']['title'] = 'SmallParT';
$setup_info['smallpart']['version'] = '0.9';
$setup_info['smallpart']['app_order'] = 5;
$setup_info['smallpart']['enable'] = 1;
$setup_info['smallpart']['tables'] = array('egw_smallpart_courses','egw_smallpart_participants','egw_smallpart_videos','egw_smallpart_lastvideo','egw_smallpart_comments');
$setup_info['smallpart']['index'] = 'smallpart.'.Ui::class.'.index&ajax=true';

$setup_info['smallpart']['author'] =
$setup_info['smallpart']['maintainer'] = array(
	'name' => 'Arash Tolou',
	'email' => 'arashtolou@gmail.com',
);
$setup_info['smallpart']['description'] =
	'SmallParT - Selfdirected media assisted learning lectures & Process analysis reflection Tool';

// Hooks we implement
$setup_info['smallpart']['hooks']['sidebox_menu'] = Hooks::class.'::menu';
$setup_info['smallpart']['hooks']['admin'] = Hooks::class.'::menu';
$setup_info['smallpart']['hooks']['csp-frame-src'] = Hooks::class.'::csp_frame_src';

/* Dependencies for this app to work */
$setup_info['smallpart']['depends'][] = array(
	'appname' => 'api',
	'versions' => Array('19.1')
);
