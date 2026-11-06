<?php
/**
 * EGroupware - SmallParT (ViDoTeach) - LTI Session tests
 *
 * @link https://www.egroupware.org
 * @package smallpart
 * @subpackage tests
 * @license https://spdx.org/licenses/AGPL-3.0-or-later.html GNU Affero General Public License v3.0 or later
 */

namespace EGroupware\SmallParT\LTI;

use EGroupware\Api;
use PHPUnit\Framework\Attributes\DataProvider;

require_once realpath(__DIR__.'/../../../api/tests/AppTest.php');
require_once __DIR__.'/LtiFixtures.php';
require_once __DIR__.'/../../../admin/inc/class.admin_cmd_delete_account.inc.php';
require_once __DIR__.'/../SmallpartTestAccounts.php';

/**
 * Unit tests for Session: the class that turns a launch (Tool with Platform+UserResult set) into
 * an EGroupware account + login session.
 *
 * Session::__construct() reads platform/userResult fields directly (`$provider->userResult->username`,
 * `$provider->platform->platformId`, ...) - exactly the surface a celtic/lti library update can
 * change (property rename, stricter type hints - see doc/ai/projects/smallpart-lti-library-update.md).
 * Constructing a Session from real, fixture Platform/UserResult objects (LtiFixtures trait) is
 * itself most of the regression check for that; create()'s account-matching/creation logic is
 * additionally covered because it is Session's own, non-trivial logic (username generation,
 * hashing, group sync) independent of the library update.
 *
 * NOT covered here (documented gap, see the project doc): the real HTTP entry point
 * (index.php -> Tool::handleRequest()'s OAuth1/JWT signature verification) and checkSetLocale()'s
 * preference-write path. Both need either a real signed request or heavier session-preference
 * fixturing than this harness's scope justifies; verify manually after the library update.
 *
 * Pass criteria: documented per test method below.
 */
class SessionTest extends Api\AppTest
{
	use LtiFixtures;

	/**
	 * account_id's auto-created by create() during a test, deleted again in tearDown()
	 *
	 * @var int[]
	 */
	private array $lti_created_account_ids = [];

	protected function tearDown(): void
	{
		$this->tearDownLtiFixtures();
		if ($this->lti_created_account_ids)
		{
			$this->asAdmin(function()
			{
				foreach ($this->lti_created_account_ids as $account_id)
				{
					try
					{
						$command = new \admin_cmd_delete_account($account_id, null, true);
						$command->comment = 'Removing LTI phpunit test account';
						$command->run();
					}
					catch (\Throwable $e)
					{
						// ignore, account might already be gone
					}
				}
			});
			$this->lti_created_account_ids = [];
		}
		parent::tearDown();
	}

	private function makeToolWithFixtures(string $iss, array $user_props = [], array $message_params = []): Tool
	{
		$tool = $this->makeTool($this->makeDataConnector());
		$tool->platform = $this->makePlatform($this->makeDataConnector(), ['platformId' => $iss]);
		$tool->userResult = $this->makeUserResult($user_props);
		$this->setMessageParameters($tool, $this->defaultMessageParameters($message_params));

		return $tool;
	}

	// -------------------------------------------------------------------
	// pure accessors (no DB writes)
	// -------------------------------------------------------------------

	public function testGetIssuerPrefersPlatformId(): void
	{
		$tool = $this->makeToolWithFixtures('https://platform.phpunit.invalid');

		$session = $this->makeSession($tool);

		$this->assertSame('https://platform.phpunit.invalid', $session->getIssuer());
	}

	public function testGetIssuerFallsBackToConsumerGuidForLti10(): void
	{
		$tool = $this->makeTool($this->makeDataConnector());
		$tool->platform = $this->makePlatform($this->makeDataConnector(), ['consumerGuid' => 'moodle.phpunit.invalid']);
		$tool->userResult = $this->makeUserResult();
		$this->setMessageParameters($tool, $this->defaultMessageParameters(['lti_version' => '1.0']));

		$session = $this->makeSession($tool);

		$this->assertSame('moodle.phpunit.invalid', $session->getIssuer());
	}

	#[DataProvider('roleProvider')]
	public function testIsInstructor(array $roles, bool $expected): void
	{
		$tool = $this->makeToolWithFixtures('https://platform.phpunit.invalid', ['roles' => $roles]);

		$this->assertSame($expected, $this->makeSession($tool)->isInstructor());
	}

	public static function roleProvider(): array
	{
		return [
			'Instructor role -> true' => [['Instructor'], true],
			'Learner role -> false' => [['Learner'], false],
			'no roles -> false' => [[], false],
		];
	}

	public function testGetFrameAncestorPrefersReturnUrl(): void
	{
		$tool = $this->makeToolWithFixtures('https://platform.phpunit.invalid');
		$tool->returnUrl = 'https://return.phpunit.invalid/back';

		$this->assertSame('https://return.phpunit.invalid/back', $this->makeSession($tool)->getFrameAncestor());
	}

	public function testGetFrameAncestorFallsBackToConsumerGuid(): void
	{
		$tool = $this->makeTool($this->makeDataConnector());
		$tool->platform = $this->makePlatform($this->makeDataConnector(), ['consumerGuid' => 'moodle.phpunit.invalid']);
		$tool->userResult = $this->makeUserResult();
		$this->setMessageParameters($tool, $this->defaultMessageParameters(['lti_version' => '1.0']));

		$this->assertSame('https://moodle.phpunit.invalid', $this->makeSession($tool)->getFrameAncestor());
	}

	/**
	 * getCustomData() strips the 'custom_' prefix from platform settings, and a course_id encoded
	 * in the LTI 1.0 oauth-key (getKey(), eg. 'course_id=42') always wins over a same-named custom
	 * setting - see the "If course_id is provided as consumer oauth_key, dont let it be overridden"
	 * note in Session::getCustomData()'s own docblock.
	 */
	public function testGetCustomDataStripsPrefixAndKeyOverridesSetting(): void
	{
		$dc = $this->makeDataConnector();
		$tool = $this->makeTool($dc);
		$tool->platform = $this->makePlatform($dc, ['platformId' => 'https://platform.phpunit.invalid']);
		$tool->platform->setSettings(['custom_course_id' => '5', 'custom_foo' => 'bar']);
		$tool->platform->setKey('course_id=42');
		$tool->userResult = $this->makeUserResult();
		$this->setMessageParameters($tool, $this->defaultMessageParameters());

		$data = $this->makeSession($tool)->getCustomData();

		$this->assertSame('42', $data['course_id'], 'oauth-key course_id must win over the custom_course_id setting');
		$this->assertSame('bar', $data['foo']);
	}

	public function testGetCustomDataWithoutKeyMatchKeepsSettingValue(): void
	{
		$dc = $this->makeDataConnector();
		$tool = $this->makeTool($dc);
		$tool->platform = $this->makePlatform($dc, ['platformId' => 'https://platform.phpunit.invalid']);
		$tool->platform->setSettings(['custom_course_id' => '5']);
		$tool->userResult = $this->makeUserResult();
		$this->setMessageParameters($tool, $this->defaultMessageParameters());

		$data = $this->makeSession($tool)->getCustomData();

		$this->assertSame('5', $data['course_id']);
	}

	// -------------------------------------------------------------------
	// create()
	// -------------------------------------------------------------------

	public function testCreateThrowsForMissingConfig(): void
	{
		$tool = $this->makeToolWithFixtures('https://never-registered.phpunit.invalid');

		$this->expectException(\Exception::class);
		$this->expectExceptionMessageMatches('/No LTI configuration/');
		$this->makeSession($tool)->create();
	}

	public function testCreateThrowsForDisabledConfig(): void
	{
		$iss = $this->registerLti13Platform(['disabled' => true]);
		$tool = $this->makeToolWithFixtures($iss);

		$this->expectException(\Exception::class);
		$this->expectExceptionMessageMatches('/disabled/');
		$this->makeSession($tool)->create();
	}

	/**
	 * Happy path: a never-seen user auto-creates an EGroupware account named per the platform's
	 * configured account_name parts ('sub-host', the default), with firstname/lastname/email
	 * copied across, an Instructor role adding them to the "Teachers" group, and a real EGroupware
	 * login session established.
	 *
	 * IMPORTANT: create() replaces the CURRENT ambient PHPUnit session (it calls
	 * $this->egw->session->create() directly, the same call a real LTI launch's dedicated HTTP
	 * request makes) - this test restores the original test session in a finally block, mirroring
	 * SmallpartTestHelpers::asAccount()'s try/finally pattern for the same reason.
	 *
	 * PHPUnit may report this test (only this one, not the other create() tests - it's the
	 * combination with tearDown()'s admin_cmd_delete_account cleanup that triggers it) as "risky:
	 * removed error handlers other than its own". This is real session_start()/header.inc.php
	 * handler churn from switchUser() being called an odd number of times across the test method,
	 * not a defect - production code has no "restore" step to mirror here, a real launch IS a
	 * single fresh request. Harmless; verified the assertions above still ran and passed.
	 */
	public function testCreateAutoCreatesAccountAndSession(): void
	{
		$iss = $this->registerLti13Platform();
		$username = 'phpunit-lti-'.bin2hex(random_bytes(4));
		$email = $username.'@example.org';
		$tool = $this->makeToolWithFixtures($iss, [
			'username' => $username,
			'firstname' => 'PHPUnit',
			'lastname' => 'Lti',
			'email' => $email,
			'roles' => ['Instructor'],
		]);
		$expected_lid = $username.'-'.parse_url($iss, PHP_URL_HOST);

		try
		{
			$this->makeSession($tool)->create();

			$account_id = $GLOBALS['egw']->accounts->name2id($expected_lid);
			$this->assertNotEmpty($account_id, "expected auto-created account '$expected_lid'");
			$this->lti_created_account_ids[] = $account_id;

			$this->assertSame('PHPUnit', $GLOBALS['egw']->accounts->id2name($account_id, 'account_firstname'));
			$this->assertSame('Lti', $GLOBALS['egw']->accounts->id2name($account_id, 'account_lastname'));
			$this->assertSame($email, $GLOBALS['egw']->accounts->id2name($account_id, 'account_email'));

			if (($teachers_group = $GLOBALS['egw']->accounts->name2id(Session::TEACHERS_GROUP)))
			{
				$this->assertContains($teachers_group, $GLOBALS['egw']->accounts->memberships($account_id, true),
					'an Instructor launch must add the new account to the Teachers group');
			}
		}
		finally
		{
			$this->switchUser($GLOBALS['EGW_USER'], $GLOBALS['EGW_PASSWORD']);
		}
	}

	/**
	 * A launch from a platform config with check_email_first=true must match an EXISTING account
	 * by email instead of creating a new one, even though the username-derived lid would differ.
	 */
	public function testCreateMatchesExistingAccountByEmailWhenConfigured(): void
	{
		// use one of the already-existing, never-deleted test accounts as the "existing" match
		$existing_lid = \EGroupware\SmallParT\SmallpartTestAccounts::TEACHER;
		\EGroupware\SmallParT\SmallpartTestAccounts::ensure();
		$existing_id = \EGroupware\SmallParT\SmallpartTestAccounts::id($existing_lid);
		$existing_email = $GLOBALS['egw']->accounts->id2name($existing_id, 'account_email');
		$this->assertNotEmpty($existing_email, 'fixture account must have an email for this test to be meaningful');

		$iss = $this->registerLti13Platform(['check_email_first' => true]);
		$tool = $this->makeToolWithFixtures($iss, [
			'username' => 'some-completely-different-lti-username',
			'email' => $existing_email,
		]);

		try
		{
			$this->makeSession($tool)->create();

			$this->assertSame($existing_id, (int)$GLOBALS['egw_info']['user']['account_id'],
				'must have logged into the existing account matched by email, not created a new one');
		}
		finally
		{
			$this->switchUser($GLOBALS['EGW_USER'], $GLOBALS['EGW_PASSWORD']);
		}
	}
}
