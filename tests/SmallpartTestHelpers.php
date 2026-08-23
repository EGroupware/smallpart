<?php
/**
 * EGroupware - SmallParT (ViDoTeach) - shared PHPUnit fixture helpers
 *
 * @link https://www.egroupware.org
 * @package smallpart
 * @subpackage tests
 * @license https://spdx.org/licenses/AGPL-3.0-or-later.html GNU Affero General Public License v3.0 or later
 */

namespace EGroupware\SmallParT;

use EGroupware\Api;

require_once __DIR__.'/SmallpartTestAccounts.php';

/**
 * Shared test-user/course/video fixture helpers for smallpart's Bo-level tests.
 *
 * Used by BoTest and OverlayTest. Role-specific behaviour is exercised by actually switching the
 * EGroupware session to a teacher/tutor/student test account (via asAccount(), mirroring
 * LoggedInTest::asAdmin()) rather than passing an explicit account_id to Bo's constructor:
 * Bo::$grants/$is_admin are computed in the constructor from $GLOBALS['egw']->acl (the AMBIENT
 * real session), not from the constructor's optional $_account_id override, so a
 * "new Bo($other_account_id)" instance would silently keep using the CURRENT session's grants for
 * anything beyond plain participant-role bitmask checks (isParticipant()/isTeacher()/isAdmin() do
 * correctly respect the override, but Bo::read()'s ACL-filtered form, checkSubscribe() and the
 * static checkTeacher() do not - and Overlay::aclCheck()/read() use Bo::getInstance(), the same
 * ambient-session singleton, so the same applies there too). Switching sessions avoids that whole
 * class of test-only footguns and matches production usage (Bo is always constructed for "the
 * current request's user" there).
 *
 * The actual test accounts are created (once) and held by SmallpartTestAccounts, a real class
 * (not this trait) shared across every test CLASS using this trait - see its docblock for why.
 *
 * A class using this trait must extend Api\AppTest (for asAccount()/switchUser()).
 */
trait SmallpartTestHelpers
{
	private const TEACHER = SmallpartTestAccounts::TEACHER;
	private const TUTOR = SmallpartTestAccounts::TUTOR;
	private const STUDENT1 = SmallpartTestAccounts::STUDENT1;
	private const STUDENT2 = SmallpartTestAccounts::STUDENT2;

	/**
	 * course_id's created by the current test, deleted in tearDown()
	 *
	 * @var int[]
	 */
	private array $created_courses = [];

	public static function setUpBeforeClass(): void
	{
		parent::setUpBeforeClass();

		SmallpartTestAccounts::ensure();
	}

	protected function tearDown(): void
	{
		if ($this->created_courses)
		{
			$this->asAccount(self::TEACHER, function()
			{
				$bo = new Bo();
				foreach ($this->created_courses as $course_id)
				{
					try
					{
						$bo->deleteCourse($course_id);
					}
					catch (\Throwable $e)
					{
						// ignore, course might already be gone / never fully created
					}
				}
			});
			$this->created_courses = [];
		}
	}

	/**
	 * Run $callback while really logged in as $lid, then always switch back - mirrors
	 * LoggedInTest::asAdmin()'s try/finally pattern (see its docblock for why pairing two bare
	 * switchUser() calls instead is a hazard).
	 *
	 * Constructs a fresh Bo() right after switching, to prime Bo::$instance (the getInstance()
	 * singleton) for the NEW ambient session: Bo::getInstance() (used internally by
	 * Overlay::aclCheck()/read()) returns whatever Bo instance was last constructed anywhere in
	 * this PHP process, NOT necessarily one matching the CURRENT session - across many simulated
	 * switchUser() "sessions" in one process, that's easy to get stale (eg. left over from a
	 * previous test, or from a differently-switched actor earlier in the same test) unless the
	 * caller happens to itself construct "new Bo()" first.
	 *
	 * @return mixed $callback's return value
	 */
	private function asAccount(string $lid, callable $callback)
	{
		$this->switchUser($lid, SmallpartTestAccounts::password($lid));
		new Bo();
		try
		{
			return $callback();
		}
		finally
		{
			$this->switchUser($GLOBALS['EGW_USER'], $GLOBALS['EGW_PASSWORD']);
		}
	}

	/**
	 * Create a course as the teacher account, track it for cleanup.
	 *
	 * @return array full course, as returned by Bo::save()
	 */
	private function createCourse(array $overrides=[]): array
	{
		$course = $this->asAccount(self::TEACHER, function() use ($overrides)
		{
			return (new Bo())->save(array_merge([
				'course_name' => 'phpunit course '.bin2hex(random_bytes(4)),
				// course_owner is NOT auto-set by Bo::save() itself - every real caller (the "new
				// course" UI form via Bo::init(), or ApiHandler's REST create) supplies it explicitly
				'course_owner' => $this->accountId(self::TEACHER),
				// So::aclFilter() (used by Bo::read()'s ACL-filtered form, eg. via
				// checkSubscribe()) only makes a course visible to non-owners via
				// `course_org IN (:acl)` - there is no "public/unrestricted" fallback for a NULL
				// course_org, so every course fixture needs an explicit course_org matching a group
				// the acting test-user actually belongs to, or checkSubscribe()/read() see it as
				// non-existent for anyone but its owner.
				'course_org' => SmallpartTestAccounts::defaultGroupId(),
			], $overrides));
		});
		$this->created_courses[] = $course['course_id'];

		return $course;
	}

	private function createVideo(array $course, array $overrides=[]): array
	{
		return $this->asAccount(self::TEACHER, function() use ($course, $overrides)
		{
			$bo = new Bo();
			$video_id = $bo->saveVideo(array_merge([
				'course_id' => $course['course_id'],
				'video_name' => 'phpunit video '.bin2hex(random_bytes(4)),
			], $overrides));

			return $bo->readVideo($video_id);
		});
	}

	private function accountId(string $lid): int
	{
		return SmallpartTestAccounts::id($lid);
	}

	/**
	 * Bo::read()'s 'participants' is a plain, sequentially-indexed list (Bo::read() calls
	 * So::participants() with $by_account_id=false, which returns array_values(...), NOT keyed by
	 * account_id - only the JsObjects/REST layer re-keys it by account_id for clients).
	 */
	private function findParticipant(array $participants, int $account_id): ?array
	{
		foreach ($participants as $participant)
		{
			if ((int)$participant['account_id'] === $account_id)
			{
				return $participant;
			}
		}
		return null;
	}
}
