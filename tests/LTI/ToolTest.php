<?php
/**
 * EGroupware - SmallParT (ViDoTeach) - LTI Tool tests
 *
 * @link https://www.egroupware.org
 * @package smallpart
 * @subpackage tests
 * @license https://spdx.org/licenses/AGPL-3.0-or-later.html GNU Affero General Public License v3.0 or later
 */

namespace EGroupware\SmallParT\LTI;

use EGroupware\Api;
use ceLTIc\LTI\Profile;

require_once realpath(__DIR__.'/../../../api/tests/AppTest.php');
require_once __DIR__.'/LtiFixtures.php';

/**
 * Unit tests for Tool (our celtic/lti Tool subclass: registration profile + onLaunch()).
 *
 * Constructing a Tool exercises a good slice of the library's public constructor API used by
 * Tool.php (`Profile\Item`, `Profile\Message`, `Profile\ResourceHandler`) - a signature change
 * there in a future library version fails right at construction, before any launch even happens.
 *
 * `onLaunch()`'s success path ends in `exit;` (see Tool.php:87) and so cannot be unit-tested
 * in-process; its FAILURE path (an exception inside the try block) does not exit and is covered
 * here instead - this is also the path most exposed by the library update, since it's what
 * surfaces any exception a changed Platform/UserResult property access throws.
 *
 * `onContentItem()`/`contentSelected()` are not covered here: both are only reachable via the
 * library's own request-dispatch (`handleRequest()`, which verifies an OAuth1/JWT signature) or
 * end in `exit;` themselves - see doc/ai/projects/smallpart-lti-library-update.md for why these
 * need a live/manual check instead.
 *
 * Pass criteria: documented per test method below.
 */
class ToolTest extends Api\LoggedInTest
{
	use LtiFixtures;

	protected function tearDown(): void
	{
		$this->tearDownLtiFixtures();
		parent::tearDown();
	}

	public function testConstructorSetsUpRegistrationProfile(): void
	{
		$tool = $this->makeTool($this->makeDataConnector());

		$this->assertInstanceOf(Profile\Item::class, $tool->vendor);
		$this->assertSame('egroupware.org', $tool->vendor->id);
		$this->assertInstanceOf(Profile\Item::class, $tool->product);
		$this->assertSame('smallpart', $tool->product->id);
		$this->assertCount(1, $tool->resourceHandlers);
		$this->assertInstanceOf(Profile\ResourceHandler::class, $tool->resourceHandlers[0]);
		$this->assertNotEmpty($tool->rsaKey, 'private key from openid app must be available');
		$this->assertNotEmpty($tool->kid);
		$this->assertStringEndsWith('/openid/endpoint.php/jwks', $tool->jku);
		$this->assertSame('RS256', $tool->signatureMethod);
		$this->assertStringContainsString('/smallpart/', $tool->baseUrl);
	}

	/**
	 * onLaunch() must turn a Session-construction/creation failure into ok=false + a reason,
	 * rather than letting the exception escape (Tool::handleRequest() relies on this to render a
	 * clean error response instead of a fatal error).
	 */
	public function testOnLaunchTurnsMissingConfigIntoFailure(): void
	{
		$tool = $this->makeTool($this->makeDataConnector());
		$tool->platform = $this->makePlatform($this->makeDataConnector(), [
			'platformId' => 'https://never-registered.phpunit.invalid',
		]);
		$tool->userResult = $this->makeUserResult();
		// non-null messageParameters short-circuits getMessageParameters()'s lazy request-parsing
		// - see LtiFixtures class docblock.
		$this->setMessageParameters($tool, $this->defaultMessageParameters());

		$tool->onLaunch();

		$this->assertFalse($tool->ok);
		$this->assertStringContainsString('No LTI configuration', $tool->reason);
	}

	/**
	 * Same failure path, but for a platform that IS registered, just disabled - this is the other
	 * exception Session::create() throws before ever touching the database/session.
	 */
	public function testOnLaunchTurnsDisabledConfigIntoFailure(): void
	{
		$iss = $this->registerLti13Platform(['disabled' => true]);
		$tool = $this->makeTool($this->makeDataConnector());
		$tool->platform = $this->makePlatform($this->makeDataConnector(), ['platformId' => $iss]);
		$tool->userResult = $this->makeUserResult();
		$this->setMessageParameters($tool, $this->defaultMessageParameters());

		$tool->onLaunch();

		$this->assertFalse($tool->ok);
		$this->assertStringContainsString('disabled', $tool->reason);
	}
}
