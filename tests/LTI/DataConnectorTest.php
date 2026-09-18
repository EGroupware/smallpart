<?php
/**
 * EGroupware - SmallParT (ViDoTeach) - LTI DataConnector tests
 *
 * @link https://www.egroupware.org
 * @package smallpart
 * @subpackage tests
 * @license https://spdx.org/licenses/AGPL-3.0-or-later.html GNU Affero General Public License v3.0 or later
 */

namespace EGroupware\SmallParT\LTI;

use EGroupware\Api;
use EGroupware\SmallParT\Bo;
use EGroupware\SmallParT\SmallpartTestHelpers;
use ceLTIc\LTI;
use ceLTIc\LTI\PlatformNonce;

require_once realpath(__DIR__.'/../../../api/tests/AppTest.php');
require_once __DIR__.'/LtiFixtures.php';
require_once __DIR__.'/../SmallpartTestHelpers.php';

/**
 * Unit tests for DataConnector (our adapter between celtic/lti and Api\Config/Api\Cache/Bo).
 *
 * This is the single highest-value part of the harness for the planned celtic/lti 4.x -> 5.x
 * update: every method here reads/writes library objects (Platform, PlatformNonce) and library
 * constants (LTI\Util::LTI_VERSION1P3/LTI_VERSION1, which become `LtiVersion` enum cases in 5.x -
 * see doc/ai/projects/smallpart-lti-library-update.md). A test that passes today and fails after
 * bumping the library version, without any change to DataConnector.php itself, is exactly the
 * signal this harness exists to catch - it means DataConnector.php needs the corresponding update
 * (eg. `LTI\Util::LTI_VERSION1P3` -> `LTI\Enum\LtiVersion::V1P3`) before the version bump can ship.
 *
 * Setup: real `ceLTIc\LTI\Platform`/`PlatformNonce` objects, built via their public API
 * (LtiFixtures trait) - no mocking of the library's own classes.
 *
 * Pass criteria: documented per test method below.
 */
class DataConnectorTest extends Api\AppTest
{
	use LtiFixtures;
	use SmallpartTestHelpers {
		SmallpartTestHelpers::tearDown as private tearDownCourseFixtures;
	}

	protected function tearDown(): void
	{
		$this->tearDownLtiFixtures();
		$this->tearDownCourseFixtures();
	}

	// -------------------------------------------------------------------
	// loadPlatform()
	// -------------------------------------------------------------------

	/**
	 * Primary production path (Tool.php::contentSelected() via Platform::fromRecordId()): a
	 * Platform constructed with only its record-id set must be fully populated from the matching
	 * Api\Config entry, using the CURRENT (pre-update) library constants for ltiVersion.
	 */
	public function testLoadPlatformByRecordId(): void
	{
		$iss = $this->registerLti13Platform([
			'client_id' => 'phpunit-client-x',
			'kid' => 'phpunit-kid-x',
			'key_set_url' => 'https://phpunit.lti-platform.invalid/jwks-x',
		]);
		$dc = $this->makeDataConnector();
		$platform = $this->makePlatform($dc);
		$platform->setRecordId($iss.':1.3');

		$this->assertTrue($dc->loadPlatform($platform));
		$this->assertSame(LTI\Util::LTI_VERSION1P3, $platform->ltiVersion,
			'ltiVersion must be the library\'s 1.3 constant - this becomes LtiVersion::V1P3 in library v5');
		$this->assertTrue($platform->enabled);
		$this->assertSame('phpunit-client-x', $platform->clientId);
		$this->assertSame('phpunit-kid-x', $platform->kid);
		$this->assertSame('https://phpunit.lti-platform.invalid/jwks-x', $platform->jku);
		$this->assertSame($iss.':1.3', $platform->getRecordId());
	}

	/**
	 * Fallback path used during the actual OIDC launch (no record-id yet, only platformId known).
	 */
	public function testLoadPlatformByPlatformIdFallback(): void
	{
		$iss = $this->registerLti13Platform();
		$dc = $this->makeDataConnector();
		$platform = $this->makePlatform($dc, ['platformId' => $iss]);

		$this->assertTrue($dc->loadPlatform($platform));
		$this->assertSame($iss.':1.3', $platform->getRecordId(),
			'record-id must be derived from the resolved config, not left unset');
	}

	public function testLoadPlatformReturnsFalseWhenNotFound(): void
	{
		$dc = $this->makeDataConnector();
		$platform = $this->makePlatform($dc, ['platformId' => 'https://never-registered.invalid']);

		$this->assertFalse($dc->loadPlatform($platform));
	}

	/**
	 * A disabled platform config still loads (Session::create() is what refuses it), but 'enabled'
	 * must reflect the flag.
	 */
	public function testLoadPlatformMapsDisabledToEnabledFalse(): void
	{
		$iss = $this->registerLti13Platform(['disabled' => true]);
		$dc = $this->makeDataConnector();
		$platform = $this->makePlatform($dc, ['platformId' => $iss]);

		$this->assertTrue($dc->loadPlatform($platform));
		$this->assertFalse($platform->enabled);
	}

	/**
	 * LTI 1.0 course-based lookup: secret comes from the course itself (`course_secret`) when the
	 * platform config has no explicit oauth_secret, and a closed course disables the platform.
	 *
	 * NOTE (found while writing this test): for a Platform with no platformId (the LTI 1.0 case),
	 * loadPlatform() resolves the issuer from $_POST['tool_consumer_instance_guid'] directly
	 * instead of $platform->consumerGuid - a real request always has this in $_POST (it's a
	 * standard LTI 1.0 launch parameter), but it means this method is not purely a function of its
	 * $platform argument. Simulated here by setting $_POST directly; flagged in the project doc as
	 * a fragile coupling worth cleaning up separately (out of scope for this harness).
	 */
	public function testLoadPlatformLti10UsesCourseSecretAndClosedFlag(): void
	{
		$course = $this->createCourse(['course_secret' => 'phpunit-course-secret']);
		$closed_course = $this->createCourse(['course_secret' => 'phpunit-course-secret-2']);
		// close via a plain update, not at creation: Bo::save() auto-subscribes the owner only when
		// creating (empty course_id), and that auto-subscribe (checkSubscribe()) unconditionally
		// refuses an already-closed course - so a course can only ever become closed after already
		// existing (open) and subscribed.
		$this->asAccount(self::TEACHER, function() use ($closed_course)
		{
			(new Bo())->save(['course_id' => $closed_course['course_id'], 'course_closed' => 1]);
		});
		$host = 'moodle.phpunit-datacon.invalid';
		$this->registerLti13Platform([
			'iss' => 'https://'.$host,
			'lti_version' => '1.0',
		]);

		$previous_post = $_POST['tool_consumer_instance_guid'] ?? null;
		$_POST['tool_consumer_instance_guid'] = $host;
		try
		{
			$dc = $this->makeDataConnector();
			$platform = $this->makePlatform($dc);
			$platform->setKey('course_id='.$course['course_id']);

			$this->assertTrue($dc->loadPlatform($platform));
			$this->assertSame('phpunit-course-secret', $platform->secret);
			$this->assertTrue($platform->enabled);

			// a platform pointing at an already-closed course must load as disabled
			$platform2 = $this->makePlatform($dc);
			$platform2->setKey('course_id='.$closed_course['course_id']);
			$this->assertTrue($dc->loadPlatform($platform2));
			$this->assertFalse($platform2->enabled, 'a closed course must disable the platform');
		}
		finally
		{
			if ($previous_post === null)
			{
				unset($_POST['tool_consumer_instance_guid']);
			}
			else
			{
				$_POST['tool_consumer_instance_guid'] = $previous_post;
			}
		}
	}

	// -------------------------------------------------------------------
	// savePlatform()
	// -------------------------------------------------------------------

	/**
	 * A never-before-seen platform (no record-id) must create a new Api\Config entry and set the
	 * record-id on the Platform object.
	 */
	public function testSavePlatformCreatesNewRegistration(): void
	{
		// NOTE (found while writing this test): savePlatform() truncates platformId to 28 chars for
		// BOTH the record-id and the Api\Config storage key (matching Config::save()'s own
		// truncation - see its docblock: "Configuration is stored ... under issuer name shortend to
		// 32 char"), but stores the FULL platformId in $data['iss'] - so Config::read()'s exact-
		// match lookup still needs the untruncated iss, while getRecordId()/Config::readById() only
		// ever see the truncated form.
		$iss = 'https://new-plat.phpunit.invalid';
		$this->lti_registered_configs[] = [$iss, '1.3']; // ensure cleanup even though we didn't register it ourselves
		$dc = $this->makeDataConnector();
		$platform = $this->makePlatform($dc, [
			'platformId' => $iss,
			'ltiVersion' => LTI\Util::LTI_VERSION1P3,
			'clientId' => 'phpunit-new-client',
			'deploymentId' => 'phpunit-dep-1',
			'accessTokenUrl' => 'https://new-plat.phpunit.invalid/token',
			'authenticationUrl' => 'https://new-plat.phpunit.invalid/auth',
			'kid' => 'new-kid',
			'jku' => 'https://new-plat.phpunit.invalid/jwks',
			'enabled' => true,
		]);

		$this->assertTrue($dc->savePlatform($platform));
		$this->assertSame(substr($iss, 0, 28).':1.3', $platform->getRecordId());

		$stored = Config::read($iss, '1.3');
		$this->assertIsArray($stored);
		$this->assertSame($iss, $stored['iss'], 'the full (untruncated) issuer must be preserved in the stored data');
		$this->assertSame('phpunit-new-client', $stored['client_id']);
		$this->assertSame(['phpunit-dep-1'], $stored['deployment']);
	}

	/**
	 * A second, still-unregistered Platform object for the SAME platform+client but a NEW
	 * deployment id must append to the existing deployment list rather than overwrite it - this is
	 * the path LTI's dynamic registration / multi-deployment support relies on.
	 */
	public function testSavePlatformAppendsNewDeploymentId(): void
	{
		$iss = $this->registerLti13Platform([
			'client_id' => 'phpunit-shared-client',
			'deployment' => ['dep-existing'],
		]);
		$dc = $this->makeDataConnector();
		$platform = $this->makePlatform($dc, [
			'platformId' => $iss,
			'ltiVersion' => LTI\Util::LTI_VERSION1P3,
			'clientId' => 'phpunit-shared-client',
			'deploymentId' => 'dep-new',
		]);

		$this->assertTrue($dc->savePlatform($platform));

		$stored = Config::read($iss, '1.3');
		$this->assertSame(['dep-existing', 'dep-new'], $stored['deployment']);
	}

	/**
	 * A client_id mismatch for an already-registered issuer must be refused (prevents a different
	 * tool registration silently taking over an existing platform record).
	 */
	public function testSavePlatformRejectsClientIdMismatch(): void
	{
		$iss = $this->registerLti13Platform(['client_id' => 'phpunit-original-client']);
		$dc = $this->makeDataConnector();
		$platform = $this->makePlatform($dc, [
			'platformId' => $iss,
			'ltiVersion' => LTI\Util::LTI_VERSION1P3,
			'clientId' => 'phpunit-different-client',
			'deploymentId' => 'dep-x',
		]);

		$this->expectException(\Exception::class);
		$dc->savePlatform($platform);
	}

	// -------------------------------------------------------------------
	// nonce
	// -------------------------------------------------------------------

	/**
	 * Full save -> load -> delete round-trip via Api\Cache, matching how the library's
	 * OIDC/OAuth1 replay-protection actually calls these three methods in sequence.
	 */
	public function testNonceSaveLoadDeleteRoundTrip(): void
	{
		$dc = $this->makeDataConnector();
		$platform = $this->makePlatform($dc, ['platformId' => 'https://nonce-test.phpunit.invalid']);
		$nonce = new PlatformNonce($platform, 'phpunit-nonce-value-'.bin2hex(random_bytes(4)));

		$this->assertTrue($dc->savePlatformNonce($nonce));

		$reloaded = new PlatformNonce($platform, $nonce->getValue());
		$this->assertTrue($dc->loadPlatformNonce($reloaded), 'a just-saved nonce must be found (replay check)');

		$this->assertTrue($dc->deletePlatformNonce($nonce));

		$after_delete = new PlatformNonce($platform, $nonce->getValue());
		$this->assertFalse($dc->loadPlatformNonce($after_delete), 'a deleted nonce must no longer be found');
	}

	public function testLoadPlatformNonceUnknownValueReturnsFalse(): void
	{
		$dc = $this->makeDataConnector();
		$platform = $this->makePlatform($dc, ['platformId' => 'https://nonce-test2.phpunit.invalid']);
		$nonce = new PlatformNonce($platform, 'never-saved-nonce');

		$this->assertFalse($dc->loadPlatformNonce($nonce));
	}
}
