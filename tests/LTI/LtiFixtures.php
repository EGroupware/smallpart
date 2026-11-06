<?php
/**
 * EGroupware - SmallParT (ViDoTeach) - LTI test fixture helpers
 *
 * @link https://www.egroupware.org
 * @package smallpart
 * @subpackage tests
 * @license https://spdx.org/licenses/AGPL-3.0-or-later.html GNU Affero General Public License v3.0 or later
 */

namespace EGroupware\SmallParT\LTI;

use EGroupware\Api;
use ceLTIc\LTI\Platform;
use ceLTIc\LTI\UserResult;

/**
 * Shared fixture builders for the LTI test-harness.
 *
 * The whole point of this harness is to catch behaviour changes coming from a celtic/lti library
 * update (composer.json currently pins ^4.4.1, installed 4.10.3 - a planned update to the current
 * 5.x line replaces several constants with enums under ceLTIc\LTI\Enum, see
 * doc/ai/projects/smallpart-lti-library-update.md). Every fixture here therefore builds *real*
 * library objects (Platform, UserResult, our Tool/DataConnector) via their PUBLIC API
 * (setKey()/setSettings()/setRecordId()/public properties) rather than mocks, so a signature or
 * type change in the library surfaces as a fixture-construction failure instead of being silently
 * absorbed by a mock.
 *
 * Message parameters (`Tool::getMessageParameters()`) are the one exception: populating them for
 * real means either a fully OAuth1-signed POST (1.0) or a signed+encrypted JWT id_token plus the
 * OIDC login dance (1.3), which would end up testing the library's own request-verification
 * pipeline rather than our adapter code. Instead we inject `$tool->messageParameters` directly via
 * reflection (it's `protected`, declared on the library's `System` trait) - this is the
 * testability seam: it isolates our Session/Tool code from the library's transport-level
 * signature verification, which is the library's own responsibility and out of scope here.
 */
trait LtiFixtures
{
	/**
	 * issuer URLs registered via registerPlatformConfig(), removed again in tearDown()
	 *
	 * @var array<int,array{0:string,1:string}> [iss, lti_version]
	 */
	private array $lti_registered_configs = [];

	protected function tearDownLtiFixtures(): void
	{
		foreach ($this->lti_registered_configs as [$iss, $lti_version])
		{
			Api\Config::save_value(substr($iss, 0, 28).':'.$lti_version, null, \EGroupware\SmallParT\Bo::APPNAME);
		}
		$this->lti_registered_configs = [];
	}

	/**
	 * Register an LTI 1.3 platform configuration the way Config::save() would store it.
	 *
	 * @return string $iss used (so the caller can build a matching Platform fixture)
	 */
	private function registerLti13Platform(array $overrides = []): string
	{
		$data = array_merge([
			'iss'            => 'https://phpunit.lti-platform.invalid/'.bin2hex(random_bytes(4)),
			'lti_version'    => '1.3',
			'disabled'       => false,
			'client_id'      => 'phpunit-client-'.bin2hex(random_bytes(4)),
			'deployment'     => ['phpunit-deployment-1'],
			'auth_token_url' => 'https://phpunit.lti-platform.invalid/token',
			'auth_login_url' => 'https://phpunit.lti-platform.invalid/auth',
			'auth_server'    => null,
			'kid'            => 'phpunit-kid',
			'key_set_url'    => 'https://phpunit.lti-platform.invalid/jwks',
			'check_email_first' => false,
			'check_account_description' => false,
			'account_name'   => ['sub', 'host'],
			'account_prefix' => '',
			'created'        => new Api\DateTime(),
			'updated'        => new Api\DateTime(),
		], $overrides);

		$key = substr($data['iss'], 0, 28).':'.$data['lti_version'];
		Api\Config::save_value($key, $data, \EGroupware\SmallParT\Bo::APPNAME);
		$this->lti_registered_configs[] = [$data['iss'], $data['lti_version']];

		return $data['iss'];
	}

	private function makeDataConnector(): DataConnector
	{
		return new DataConnector();
	}

	private function makePlatform(DataConnector $dc, array $props = []): Platform
	{
		$platform = new Platform($dc);
		foreach ($props as $name => $value)
		{
			$platform->$name = $value;
		}
		return $platform;
	}

	private function makeUserResult(array $props = []): UserResult
	{
		$user = new UserResult();
		foreach (array_merge([
			'username'  => 'phpunit-user',
			'firstname' => 'PHPUnit',
			'lastname'  => 'User',
			'email'     => '',
			'sourcedId' => 'phpunit-sourced-id',
			'roles'     => [],
		], $props) as $name => $value)
		{
			$user->$name = $value;
		}
		return $user;
	}

	private function makeTool(DataConnector $dc): Tool
	{
		return new Tool($dc);
	}

	/**
	 * Inject message parameters into a Tool without a real signed launch request.
	 *
	 * @see LtiFixtures class docblock for why this is the right seam.
	 */
	private function setMessageParameters(Tool $tool, array $params): void
	{
		$prop = new \ReflectionProperty(\ceLTIc\LTI\Tool::class, 'messageParameters');
		$prop->setAccessible(true);
		$prop->setValue($tool, $params);
	}

	/**
	 * The subset of launch message parameters Session::__construct() reads
	 * ('launch_presentation_locale', 'lti_version', 'ext_user_username') - defaults to matching
	 * registerLti13Platform()'s default '1.3', since that's what almost every test needs to make
	 * Config::read() find the right platform version.
	 */
	private function defaultMessageParameters(array $overrides = []): array
	{
		return array_merge([
			'launch_presentation_locale' => null,
			'lti_version' => '1.3',
			'ext_user_username' => null,
		], $overrides);
	}

	private function makeSession(Tool $tool): Session
	{
		return new Session($tool);
	}
}
