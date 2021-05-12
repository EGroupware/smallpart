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
use EGroupware\SmallParT\LTI10\Tool;
use EGroupware\Api\Header\ContentSecurityPolicy;

// fix OAuthRequest class to NOT prefer SERVER_NAME over HTTP_HOST
if (!isset($_SERVER['HTTP_X_FORWARDED_HOST'])) $_SERVER['HTTP_X_FORWARDED_HOST'] = $_SERVER['HTTP_HOST'];

try {
	$data_connector = new DataConnector();
	$tool = new Tool($data_connector);
	$tool->setParameterConstraint('resource_link_id', TRUE, 50, array('basic-lti-launch-request'));
	$tool->setParameterConstraint('user_id', TRUE, 50, array('basic-lti-launch-request'));
	$tool->setParameterConstraint('roles', TRUE, NULL, array('basic-lti-launch-request'));
}
catch(\Throwable $e) {
	ContentSecurityPolicy::add('frame-ancestors', 'https:');
	_egw_log_exception($e);
	if (!isset($tool)) $tool = new Tool($data_connector);
	$tool->reason = $e->getMessage();
}
$tool->handleRequest();
