<?php
/**
 * EGroupware - SmallParT - LTI Learning Tools Interoperatbility - LTI v1.0 Tool Provider
 *
 * @link https://www.egroupware.org
 * @package smallpart
 * @subpackage lti
 * @author Ralf Becker <rb@egroupware.org>
 * @copyright 2020 by Ralf Becker <rb@egroupware.org>
 * @license https://spdx.org/licenses/AGPL-3.0-or-later.html GNU Affero General Public License v3.0 or later
 */

namespace EGroupware\SmallParT\LTI10;

use ceLTIc\LTI;
use ceLTIc\LTI\Profile;
use EGroupware\Api;
use EGroupware\SmallParT\LTI\Ui;

/**
 * LTI v1.0 ToolProvider
 *
 * @package EGroupware\SmallParT\LTI10
 */
class ToolProvider extends LTI\ToolProvider
{
	function __construct(DataConnector $data_connector)
	{
		parent::__construct($data_connector);

		$this->baseUrl = Api\Framework::getUrl(Api\Egw::link('/smallpart'));

		$this->vendor = new Profile\Item('egroupware.org', 'EGroupware', 'EGroupware GmbH', 'https://www.egroupware.org/');
		$this->product = new Profile\Item('smallpart', 'smallPART',
			'smallPART - selfdirected media assisted learning lectures & Process Analysis Reflection Tool',
			'https://www.egroupware.org/smallpart-video-supported-learning-and-teaching/',
			'1.0');

		$requiredMessages = array(new Profile\Message('basic-lti-launch-request', 'connect.php', array('User.id', 'Membership.role')));
		/*$optionalMessages = array(new Profile\Message('ContentItemSelectionRequest', 'connect.php',
			array('User.id', 'Membership.role')),
			new Profile\Message('DashboardRequest', 'connect.php', array('User.id'), array('a' => 'User.id'),
				array('b' => 'User.id')));*/

		$this->resourceHandlers[] = new Profile\ResourceHandler(
			new Profile\Item('smallpart', 'smallPART', 'selfdirected media assisted learning lectures & Process Analysis Reflection Tool'),
			'images/icon50.png', $requiredMessages, $optionalMessages);

		//$this->requiredServices[] = new Profile\ServiceDefinition(array('application/vnd.ims.lti.v2.toolproxy+json'), array('POST'));
	}

	function onLaunch()
	{
		try {
			$session = new Session($this);
			$session->create();

			$ui = new Ui($session);
			$ui->check();

			// we should return a redirect url, but that's not so easy as we need to set CSP policy etc.
			// just outputting the content and exiting seems to work too
			$ui->render();
			exit;
		}
		catch (\Throwable $e) {
			_egw_log_exception($e);
			$this->reason = $e->getMessage();
			$this->ok = false;
		}
	}
}