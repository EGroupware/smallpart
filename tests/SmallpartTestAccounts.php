<?php
/**
 * EGroupware - SmallParT (ViDoTeach) - shared, process-wide test accounts
 *
 * @link https://www.egroupware.org
 * @package smallpart
 * @subpackage tests
 * @license https://spdx.org/licenses/AGPL-3.0-or-later.html GNU Affero General Public License v3.0 or later
 */

namespace EGroupware\SmallParT;

use EGroupware\Api;

/**
 * Creates (once) and holds the teacher/tutor/student test accounts shared by every smallpart
 * test CLASS using SmallpartTestHelpers - a real class, not a trait, since trait static
 * properties are duplicated PER USING CLASS, not actually shared.
 *
 * Deliberately NOT deleted in any tearDownAfterClass(): Bo::checkTeacher() caches the "smallpart
 * admin" account_ids in a `static` LOCAL variable inside the method itself, computed once and
 * never invalidated for the lifetime of the PHP process. If class A created its own teacher
 * account, granted it admin, ran its tests, then deleted it in tearDownAfterClass(), and class B
 * then created a NEW same-lid account (a different, fresh account_id) - checkTeacher()'s stale
 * cache would still only know class A's (now-deleted) id, so class B's teacher would fail
 * checkTeacher() entirely. Keeping ONE, persistent, reused set of accounts for the whole process
 * (and across repeated local test runs, via the name2id() reuse-if-exists check below) sidesteps
 * that staleness hazard at the root, instead of working around it.
 */
final class SmallpartTestAccounts
{
	public const TEACHER = 'smallpart_test_teacher';
	public const TUTOR = 'smallpart_test_tutor';
	public const STUDENT1 = 'smallpart_test_student1';
	public const STUDENT2 = 'smallpart_test_student2';

	/**
	 * @var array<string,int> account_lid => account_id
	 */
	private static array $accounts = [];

	/**
	 * @var array<string,string> account_lid => password
	 */
	private static array $passwords = [];

	private static ?int $defaultGroupId = null;

	private static bool $ready = false;

	public static function ensure(): void
	{
		if (self::$ready)
		{
			return;
		}
		self::asAdmin(function()
		{
			foreach ([
				self::TEACHER => 'Teacher',
				self::TUTOR => 'Tutor',
				self::STUDENT1 => 'Student1',
				self::STUDENT2 => 'Student2',
			] as $lid => $firstname)
			{
				self::ensureUser($lid, $firstname);
			}
			self::$defaultGroupId = $GLOBALS['egw']->accounts->name2id('Default');

			// grant course-creation right (Bo::checkTeacher()'s gate), if not already granted by an
			// earlier local test run
			if (!$GLOBALS['egw']->acl->get_specific_rights_for_account(self::$accounts[self::TEACHER], 'admin', 'smallpart'))
			{
				$GLOBALS['egw']->acl->add_repository('smallpart', 'admin', self::$accounts[self::TEACHER], 1);
			}
		});
		self::$ready = true;
	}

	private static function ensureUser(string $lid, string $firstname): void
	{
		$password = 'Sm4llp4rt-'.$firstname.'!';
		self::$passwords[$lid] = $password;

		if (($existing_id = $GLOBALS['egw']->accounts->name2id($lid)))
		{
			self::$accounts[$lid] = $existing_id;
			return;
		}
		$command = new \admin_cmd_edit_user(false, [
			'account_lid' => $lid,
			'account_firstname' => $firstname,
			'account_lastname' => 'Test',
			'account_email' => $lid.'@example.org',
			'account_passwd' => $password,
			'account_passwd_2' => $password,
			'account_primary_group' => 'Default',
		]);
		$command->comment = 'Needed for smallpart tests';
		$command->run();
		self::$accounts[$lid] = $command->account;
	}

	/**
	 * Duplicates LoggedInTest::asAdminStatic()'s logic (can't call it directly - it's protected,
	 * and this class isn't a LoggedInTest subclass), using its public load_egw()/
	 * tearDownAfterClass().
	 */
	private static function asAdmin(callable $callback)
	{
		Api\LoggedInTest::tearDownAfterClass();
		Api\LoggedInTest::load_egw($GLOBALS['EGW_ADMIN_USER'], $GLOBALS['EGW_ADMIN_PASSWORD']);
		try
		{
			return $callback();
		}
		finally
		{
			Api\LoggedInTest::tearDownAfterClass();
			Api\LoggedInTest::load_egw($GLOBALS['EGW_USER'], $GLOBALS['EGW_PASSWORD']);
		}
	}

	public static function id(string $lid): int
	{
		self::ensure();
		return self::$accounts[$lid];
	}

	public static function password(string $lid): string
	{
		self::ensure();
		return self::$passwords[$lid];
	}

	public static function defaultGroupId(): int
	{
		self::ensure();
		return self::$defaultGroupId;
	}
}
