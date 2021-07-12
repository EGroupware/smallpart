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

// give Teachers group rights to use the app AND create courses
$teachersgroup = $GLOBALS['egw_setup']->add_account('Teachers', 'Teachers', 'Group', false, false);
$GLOBALS['egw_setup']->add_acl('smallpart', 'run', $teachersgroup);
$GLOBALS['egw_setup']->add_acl('smallpart', 'admin', $teachersgroup);

// install example course
try
{
	foreach (preg_split('/;\n/', preg_replace(['|/\*.+\*/|Us', '/^--.*$/m', '/egroupware\./', "/\n+/"], ['', '', '', "\n"],
		file_get_contents(__DIR__ . '/brain-slices.sql'))) as $sql)
	{
		if (empty(trim($sql))) continue;
		if ($GLOBALS['egw_setup']->oProc->sType === 'pgsql' && preg_match('/^(LOCK|UNLOCK)/', $sql)) continue;
		if ($GLOBALS['egw_setup']->oProc->sType !== 'mysql')
		{
			$sql = preg_replace_callback('/`([a-z0-9_]+)`/i', static function($matches)
			{
				return $GLOBALS['egw_setup']->db->name_quote($matches[1]);
			}, $sql);
			// quotes inside JSON need to be handled db-specific
			$sql = preg_replace_callback('/\'(\[.*\])\'/U', static function($matches)
			{
				return $GLOBALS['egw_setup']->db->quote(stripslashes($matches[1]));
			}, $sql);
		}
		$GLOBALS['egw_setup']->db->query($sql, __LINE__, __FILE__);
	}
}
catch (Exception $e) {
	_egw_log_exception($e);
	if ($GLOBALS['egw_setup']->oProc->sType !== 'pgsql')
	{
		$GLOBALS['egw_setup']->db->query('UNLOCK TABLES', __LINE__, __FILE__);
	}
}

// fix video-url, in case it's not /egroupware
if (($webserver_url = $GLOBALS['egw_setup']->db->query("SELECT config_value FROM egw_config WHERE config_name='webserver_url' AND config_app='phpgwapi'",
	__LINE__, __FILE__)->fetchColumn()) !== '/egroupware')
{
	$GLOBALS['egw_setup']->db->query("UPDATE egw_smallpart_videos SET video_url=".$GLOBALS['egw_setup']->db->quote($webserver_url.'/smallpart/setup/brain-slices.mp4'));
}

// create smallPART video directory for direct access by webserver (needs configuration on webserver!)
$files_dir = $GLOBALS['egw_setup']->db->query("SELECT config_value FROM egw_config WHERE config_name='files_dir' AND config_app='phpgwapi'",
	__LINE__, __FILE__)->fetchColumn() ?: '/var/lib/egroupware/default/files';
$smallpart_video_dir = $files_dir.'/smallpart/Video';
// directory needs to be world readable so webserver can access it (videos are stored with long random names)
if (!file_exists($smallpart_video_dir)) mkdir($smallpart_video_dir, 0755, true);
chmod($files_dir, 0755);
chmod(dirname($files_dir), 0755);
