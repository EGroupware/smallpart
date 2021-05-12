<?php
/**
 * EGroupware - SmallParT - LTI Learning Tools Interoperatbility - Login
 *
 * @link https://www.egroupware.org
 * @package smallpart
 * @subpackage lti
 * @author Ralf Becker <rb@egroupware.org>
 * @copyright 2020 by Ralf Becker <rb@egroupware.org>
 * @license https://spdx.org/licenses/AGPL-3.0-or-later.html GNU Affero General Public License v3.0 or later
 */

// to support old LTI 1.3 URL
$_SERVER['REQUEST_URI'] = str_replace('lti-login.php', 'lti-launch.php', $_SERVER['REQUEST_URI']);
include __DIR__.'/index.php';
