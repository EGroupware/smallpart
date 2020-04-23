<?php
/**
 * EGroupware - SmallPart - setup definitions
 *
 * @link http://www.egroupware.org
 * @package smallpart
 * @subpackage setup
 * @license http://opensource.org/licenses/gpl-license.php GPL - GNU General Public License
 */

$setup_info['smallpart']['name'] = 'smallpart';
$setup_info['smallpart']['title'] = 'SmallParT';
$setup_info['smallpart']['version'] = '0.1';
$setup_info['smallpart']['app_order'] = 5;
$setup_info['smallpart']['enable'] = 1;
$setup_info['smallpart']['tables'] = array();

$setup_info['smallpart']['author'] =
$setup_info['smallpart']['maintainer'] = array(
	'name' => 'Arash Tolou',
	'email' => 'arashtolou@gmail.com',
);
$setup_info['smallpart']['description'] =
	'SmallParT - Selfdirected media assisted learning lectures & Process analysis reflection Tool';

// Hooks we implement
//$setup_info['smallpart']['hooks']['search_link'] = Example\Hooks::class . '::search_link';

/* Dependencies for this app to work */
$setup_info['smallpart']['depends'][] = array(
	'appname' => 'api',
	'versions' => Array('19.1')
);
