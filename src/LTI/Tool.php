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

namespace EGroupware\SmallParT\LTI;

use ceLTIc\LTI;
use ceLTIc\LTI\Profile;
use ceLTIc\LTI\Content;
use EGroupware\Api;
use EGroupware\Api\Header\ContentSecurityPolicy;
use EGroupware\OpenID\Keys;

/**
 * LTI v1.0 ToolProvider
 *
 * @package EGroupware\SmallParT\LTI10
 */
class Tool extends LTI\Tool
{
	function __construct(DataConnector $data_connector)
	{
		parent::__construct($data_connector);

		$this->baseUrl = Api\Framework::getUrl(Api\Egw::link('/smallpart/'));

		$this->vendor = new Profile\Item('egroupware.org', 'EGroupware', 'EGroupware GmbH', 'https://www.egroupware.org/');
		$this->product = new Profile\Item('smallpart', 'smallPART',
			'smallPART - selfdirected media assisted learning lectures & Process Analysis Reflection Tool',
			'https://www.egroupware.org/smallpart-video-supported-learning-and-teaching/',
			'1.0');

		$requiredMessages = [
			new Profile\Message('basic-lti-launch-request', '', [
				'User.id',
				/*'Person.name.full',
				'Person.name.given',
				'Person.name.family',
				'Person.email.primary',*/
				'Membership.role',
			]),
		];
		$optionalMessages = [
			new Profile\Message('ContentItemSelectionRequest', '', array('User.id', 'Membership.role')),
			//new Profile\Message('DashboardRequest', '', array('User.id'), array('a' => 'User.id'), array('b' => 'User.id')));
		];
		$this->resourceHandlers[] = new Profile\ResourceHandler(
			new Profile\Item('smallpart', 'smallPART', 'selfdirected media assisted learning lectures & Process Analysis Reflection Tool'),
			'templates/default/images/navbar.svg', $requiredMessages, $optionalMessages);

		//$this->requiredServices[] = new Profile\ServiceDefinition(array('application/vnd.ims.lti.v2.toolproxy+json'), array('POST'));

		// LTI 1.3 settings
		// private key from OpenID server used by SmallPART to sign JWT
		$keys = new Keys();
		$this->rsaKey = $keys->getPrivateKeyString();
		$this->kid = $keys->getKid();
		$this->jku = Api\Framework::getUrl(Api\Egw::link('/openid/endpoint.php/jwks'));
		$this->signatureMethod = 'RS256';
		$this->requiredScopes = [
			//LTI\Service\LineItem::$SCOPE,
			//LTI\Service\Score::$SCOPE,
			//LTI\Service\Membership::$SCOPE
		];
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

	/**
	 * Process a valid content-item request
	 */
	protected function onContentItem()
	{
		// Check that the Platform is allowing the return of an LTI link
		$this->ok = in_array(LTI\Content\Item::LTI_LINK_MEDIA_TYPE, $this->mediaTypes) || in_array('*/*', $this->mediaTypes);
		if (!$this->ok)
		{
			$this->reason = 'Return of an LTI link not offered';
		}
		else
		{
			$this->ok = !in_array('none', $this->documentTargets) || (count($this->documentTargets) > 1);
			if (!$this->ok)
			{
				$this->reason = 'No visible document target offered';
			}
		}
		if ($this->ok)
		{
			$session = new Session($this);
			$session->create();

			$ui = new Ui($session);

			// Initialise the user interaction
			$ui->contentSelection(null, self::class.'::contentSelected', [
				'consumer_pk' => $this->platform->getRecordId(),
				'deployment_id' => $this->platform->deploymentId,
				'lti_version' => $this->platform->ltiVersion,
				'return_url' => $this->returnUrl,
				'title' => $_POST['title'],
				'text' => $_POST['text'],
				'data' => $_POST['data'],
				'document_targets' => $this->documentTargets,
				'message_parameters' => $this->getMessageParameters(),
			]);
			exit;
		}
	}

	/**
	 * Send content-selection back to platform
	 *
	 * @param array $params
	 * @param ?array $selection selected course_id and video_id or empty for canceled selection
	 */
	public static function contentSelected(array $params, array $selection=null)
	{
		$dataconnector = new DataConnector();
		LTI\Tool::$defaultTool = new self($dataconnector);
		LTI\Tool::$defaultTool->platform = LTI\Platform::fromRecordId($params['consumer_pk'], $dataconnector);

		// Pass on preference for iframe, window, overlay, popup, frame options in that order if any of these is offered
		$placement = null;
		foreach(['iframe', 'window', 'overlay', 'popup', 'frame'] as $target)
		{
			if (in_array($target, $params['document_targets']))
			{
				$placement = new Content\Placement($target);
				break;
			}
		}
		$formParams = [
			'deployment_id' => $params['deployment_id'],
		];
		if (!empty($selection))
		{
			$item = new Content\LtiLinkItem($placement);
			$item->setMediaType(Content\Item::LTI_LINK_MEDIA_TYPE);
			$item->setTitle($params['title'] ?: lang('Video'));
			$item->setText($params['text']);
			$item->setIcon(new Content\Image(Api\Framework::getUrl(Api\Egw::link('/smallpart/templates/default/images/navbar.svg')), 50, 50));
			foreach ($selection as $name => $value)
			{
				$item->addCustom($name, $value);
			}
			$formParams['content_items'] = Content\Item::toJson($item, LTI\Tool::$defaultTool->platform->ltiVersion);
		}
		if (!is_null($params['data']))
		{
			$formParams['data'] = $params['data'];
		}
		$formParams = LTI\Tool::$defaultTool->signParameters($params['return_url'], 'LtiDeepLinkingResponse',
			$params['lti_version'], $formParams);
		$page = LTI\Util::sendForm($params['return_url'], $formParams);
		// otherwise our CSP forbids browser to run this page
		ContentSecurityPolicy::add('frame-ancestors', 'https:');
		ContentSecurityPolicy::add('script-src', 'unsafe-inline');
		echo $page;
		exit;
	}
}