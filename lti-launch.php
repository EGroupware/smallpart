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

use IMSGlobal\LTI;
use EGroupware\SmallParT\LTI\Database;
use EGroupware\SmallParT\LTI\Session;
use EGroupware\SmallParT\LTI\Ui;
use EGroupware\Api\Header\ContentSecurityPolicy;

try
{
	// check if we have a valid launch
	$database = new Database();
	$launch = LTI\LTI_Message_Launch::new($database)
		->validate();

	// create a user, if not yet existing, and a session
	$session = new Session($launch, $database);
	$session->create();

	// create user UI
	$ui = new Ui($session);
	$ui->check();
	$ui->render();
}
catch (\Throwable $e) {
	ContentSecurityPolicy::add('frame-ancestors', 'https:');
	_egw_log_exception($e);
	http_response_code(500);
	$GLOBALS['egw']->framework->render("<h1>LTI Launch failed :(</h1>\n".
		"<p><b>".$e->getMessage().' ('.$e->getCode().")</b></p>\n", null, false);
}