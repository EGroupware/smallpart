<?php
/**
 * EGroupware - SmallParT (ViDoTeach) - LTI Config tests
 *
 * @link https://www.egroupware.org
 * @package smallpart
 * @subpackage tests
 * @license https://spdx.org/licenses/AGPL-3.0-or-later.html GNU Affero General Public License v3.0 or later
 */

namespace EGroupware\SmallParT\LTI;

use EGroupware\Api;

require_once realpath(__DIR__.'/../../../api/tests/AppTest.php');
require_once __DIR__.'/LtiFixtures.php';

/**
 * Unit tests for Config::read()/readById(), the platform-lookup layer DataConnector::loadPlatform()
 * and Session::create() sit on top of.
 *
 * Pure storage/matching logic - no celtic/lti library objects involved - so this part of the
 * harness protects against a *our own* regression while touching this code for the library update,
 * rather than against a library-side behaviour change (see DataConnectorTest/SessionTest for that).
 *
 * Setup: fixtures are written directly via Api\Config::save_value() in the same shape
 * Config::save() itself writes (see LtiFixtures::registerLti13Platform()), and removed again in
 * tearDown() regardless of test outcome.
 *
 * Pass criteria: documented per test method below.
 */
class ConfigTest extends Api\LoggedInTest
{
	use LtiFixtures;

	protected function tearDown(): void
	{
		$this->tearDownLtiFixtures();
		parent::tearDown();
	}

	/**
	 * A registered LTI 1.3 platform is found by its exact issuer + version.
	 */
	public function testReadFindsRegistered1p3(): void
	{
		$iss = $this->registerLti13Platform();

		$data = Config::read($iss, '1.3');

		$this->assertIsArray($data, 'Config::read() did not find the just-registered platform');
		$this->assertSame($iss, $data['iss']);
		$this->assertSame('1.3', $data['lti_version']);
	}

	/**
	 * An unknown issuer must not match, and must not throw.
	 */
	public function testReadUnknownIssuerReturnsNull(): void
	{
		$this->assertNull(Config::read('https://never-registered.invalid', '1.3'));
	}

	/**
	 * Same issuer registered for a different LTI version must not match - Session::create() relies
	 * on this to keep 1.0 and 1.3 launches from the same platform host from colliding.
	 */
	public function testReadDoesNotCrossVersions(): void
	{
		$iss = $this->registerLti13Platform();

		$this->assertNull(Config::read($iss, '1.0'));
	}

	/**
	 * LTI 1.0 lookup matches by the platform's HOSTNAME (consumer GUID), not the full issuer URL,
	 * and additionally requires the oauth_key to match when the config has one set - this is the
	 * path DataConnector::loadPlatform() takes for LTI 1.0 launches (course_id as oauth_key).
	 */
	public function testReadLti10MatchesByHostAndOauthKey(): void
	{
		$iss = 'https://moodle.phpunit.invalid';
		$this->registerLti13Platform([
			'iss' => $iss,
			'lti_version' => '1.0',
			'oauth_key' => 'course_id=123',
		]);

		$this->assertNull(Config::read('moodle.phpunit.invalid', '1.0', 'course_id=999'),
			'must not match with the wrong oauth_key');
		$data = Config::read('moodle.phpunit.invalid', '1.0', 'course_id=123');
		$this->assertIsArray($data, 'must match host + correct oauth_key');
		$this->assertSame($iss, $data['iss']);
	}

	/**
	 * readById() is the reverse of the "$iss:$lti_version" record-id Config::save()/
	 * DataConnector::savePlatform() construct - round-trip must find the same config read() would.
	 */
	public function testReadByIdRoundTrips(): void
	{
		$iss = $this->registerLti13Platform();

		$data = Config::readById($iss.':1.3');

		$this->assertIsArray($data, 'readById() did not resolve the record-id back to the config');
		$this->assertSame($iss, $data['iss']);
	}

	public function testReadByIdRejectsMalformedId(): void
	{
		$this->assertNull(Config::readById('not-a-valid-record-id'));
	}

	/**
	 * The 'disabled' flag round-trips through read() unchanged - Session::create() is the one that
	 * acts on it (throws), read() itself is a pure lookup.
	 */
	public function testReadReturnsDisabledFlagUnfiltered(): void
	{
		$iss = $this->registerLti13Platform(['disabled' => true]);

		$data = Config::read($iss, '1.3');

		$this->assertTrue((bool)$data['disabled']);
	}
}
