<?php
/**
 * EGroupware - SmallParT - LTI Learning Tools Interoperatbility - Launch
 *
 * @link https://www.egroupware.org
 * @package smallpart
 * @subpackage lti
 * @author Ralf Becker <rb@egroupware.org>
 * @copyright 2020 by Ralf Becker <rb@egroupware.org>
 * @license https://spdx.org/licenses/AGPL-3.0-or-later.html GNU Affero General Public License v3.0 or later
 */

$GLOBALS['egw_info'] = [
	'flags' => [
		'currentapp' => 'login',
		'noheader' => true,
		'nonavbar' => true,
	],
];
require_once __DIR__.'/../header.inc.php';

use EGroupware\SmallParT\LTI10\DataConnector;
use EGroupware\SmallParT\LTI10\ToolProvider;

error_log(__FILE__.': _POST='.json_encode($_POST));
//echo phpinfo();exit;
//foreach($_POST as $name => $value) echo "_POST['$name']=".json_encode($value)."<br/>\n"; die('Stop');

// fix OAuthRequest class to NOT prever SERVER_NAME over HTTP_HOST
if (!isset($_SERVER['HTTP_X_FORWARDED_HOST'])) $_SERVER['HTTP_X_FORWARDED_HOST'] = $_SERVER['HTTP_HOST'];

try {
	$data_connector = new DataConnector();
	$tool = new ToolProvider($data_connector);
	$tool->setParameterConstraint('resource_link_id', TRUE, 50, array('basic-lti-launch-request'));
	$tool->setParameterConstraint('user_id', TRUE, 50, array('basic-lti-launch-request'));
	$tool->setParameterConstraint('roles', TRUE, NULL, array('basic-lti-launch-request'));
}
catch(\Throwable $e) {
	_egw_log_exception($e);
	if (!isset($tool)) $tool = new ToolProvider($data_connector);
	$tool->reason = $e->getMessage();
}
$tool->handleRequest();

