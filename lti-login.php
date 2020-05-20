<?php
/**
 * EGroupware - SmallParT - LTI Learning Tools Interoperatbility - Login
 *
 * @link https://www.egroupware.org
 * @package smallpart
 * @subpackage lti
 * @author Ralf Becker <rb@egroupware.org>
 * @copyright 2020 by Ralf Becker <rb@egroupware.org>
 * @license http://opensource.org/licenses/gpl-license.php GPL - GNU General Public License
 */

$GLOBALS['egw_info'] = [
	'flags' => [
		'currentapp' => 'login',
		'noheader' => true,
	],
];
require_once __DIR__.'/../header.inc.php';

require_once __DIR__ . '/vendor/autoload.php';

use IMSGlobal\LTI;
use EGroupware\SmallParT\LTI\Database;
use EGroupware\Api;

LTI\LTI_OIDC_Login::new(new Database())
	->do_oidc_login_redirect(Api\Framework::getUrl(Api\Framework::link('/smallpart/lti-launch.php')))
	->do_redirect();