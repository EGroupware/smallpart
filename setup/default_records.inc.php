<?php
/**
 * EGroupware - SmallParT - setup definitions
 *
 * @link https://www.egroupware.org
 * @package smallpart
 * @subpackage setup
 * @license http://opensource.org/licenses/gpl-license.php GPL - GNU General Public License
 */

// give Default and Admins group rights to use the app
$defaultgroup = $GLOBALS['egw_setup']->add_account('Default', 'Default', 'Group', false, false);
$GLOBALS['egw_setup']->add_acl('smallpart', 'run', $defaultgroup);

// give Admins group rights to use the app
$adminsgroup = $GLOBALS['egw_setup']->add_account('Admins', 'Admins', 'Group', false, false);
$GLOBALS['egw_setup']->add_acl('smallpart', 'run', $adminsgroup);

// install example course
foreach(preg_split('/;\n/', preg_replace(['|/\*.+\*/|Us', '/^--.*$/m', '/egroupware\./', "/\n+/"], ['', '', '', "\n"],
	file_get_contents(__DIR__.'/brain-slices.sql'))) as $sql)
{
	if (empty(trim($sql))) continue;
	$GLOBALS['egw_setup']->db->query($sql, __LINE__, __FILE__);
}
$files_dir = $GLOBALS['egw_setup']->db->query("SELECT config_value FROM egw_config WHERE config_name='files_dir' AND config_app='phpgwapi'",
	__LINE__, __FILE__)->fetchColumn() ?: '/var/lib/egroupware/default/files';
$smallpart_video_dir = $files_dir.'/smallpart/Video/1';
if (!file_exists($smallpart_video_dir)) mkdir($smallpart_video_dir, 0777, true);
copy(__DIR__.'/brain-slices.mp4', $smallpart_video_dir.'/vZ8dUgqAmLXREckLveupt2sqOUX1ePS9pwEQHibJhASGAdq2R3O4jVAmD6hkmM6l.mp4');
