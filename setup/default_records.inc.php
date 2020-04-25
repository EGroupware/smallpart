<?php
/**
 * EGroupware - SmallParT - setup definitions
 *
 * @link https://www.egroupware.org
 * @package smallpart
 * @subpackage setup
 * @license http://opensource.org/licenses/gpl-license.php GPL - GNU General Public License
 */

// create Guacamole tables and views
foreach(preg_split('/;\n/', preg_replace(['|/\*.+\*/|Us', '/^--.*$/m', '/egroupware\./', "/\n+/"], ['', '', '', "\n"],
	file_get_contents(__DIR__.'/tables.sql'))) as $sql)
{
	$GLOBALS['egw_setup']->db->query($sql, __LINE__, __FILE__);
}

// give Default and Admins group rights to use the app
$defaultgroup = $GLOBALS['egw_setup']->add_account('Default', 'Default', 'Group', false, false);
$GLOBALS['egw_setup']->add_acl('smallpart', 'run', $defaultgroup);

// give Admins group rights to use the app
$adminsgroup = $GLOBALS['egw_setup']->add_account('Admins', 'Admins', 'Group', false, false);
$GLOBALS['egw_setup']->add_acl('smallpart', 'run', $adminsgroup);
