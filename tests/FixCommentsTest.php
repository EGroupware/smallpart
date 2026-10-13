<?php
/**
 * EGroupware - SmallParT (ViDoTeach) - Student\Ui::_fixComments(), the shape of the comments sent to the client
 *
 * @link https://www.egroupware.org
 * @package smallpart
 * @subpackage tests
 * @license https://spdx.org/licenses/AGPL-3.0-or-later.html GNU Affero General Public License v3.0 or later
 */

namespace EGroupware\SmallParT;

use EGroupware\Api;
use EGroupware\SmallParT\Student\Ui;

require_once realpath(__DIR__.'/../../api/tests/AppTest.php');

/**
 * _fixComments() gives every comment the css classes the client lists use (commentOwner, commentMarked and one
 * cat-<id> per category) and puts an empty entry first, which the comment list ignores.
 *
 * The comments are built by hand and the course/video ids point at no directory, so nothing is read from the
 * database or written anywhere.
 *
 * PASS CRITERIA
 * - the class of each comment is exactly the expected set, starting from an empty string (no PHP warning for a
 *   class that was never set)
 * - a teacher owns every comment, anybody else only their own
 * - the second argument is a real boolean: Ui::ajax_deleteComment() once passed its `$where` array there, which
 *   is always truthy and made every row of the answer a commentOwner row
 */
class FixCommentsTest extends Api\AppTest
{
	private function fix(array $comments, bool|array $is_teacher = false) : array
	{
		$method = new \ReflectionMethod(Ui::class, '_fixComments');
		$method->setAccessible(true);
		return $method->invoke(null, $comments, $is_teacher);
	}

	private function comment(int $id, int $account_id, array $extra = []) : array
	{
		return $extra + [
			'comment_id' => $id, 'course_id' => 0, 'video_id' => 0, 'account_id' => $account_id,
			'account_lid' => 'nobody', 'comment_cat' => '', 'comment_marked' => null,
		];
	}

	public function testFirstEntryIsEmptyAndTheRestKeepTheirOrder()
	{
		$fixed = $this->fix([$this->comment(5, 1), $this->comment(6, 1)]);
		$this->assertSame([], $fixed[0], 'the reserved first entry is empty');
		$this->assertSame([5, 6], array_column(array_slice($fixed, 1), 'comment_id'));
	}

	public function testClassesStartEmptyAndListMarkedAndCategories()
	{
		$me = (int)$GLOBALS['egw_info']['user']['account_id'];
		$fixed = $this->fix([
			$this->comment(1, $me + 1000),
			$this->comment(2, $me + 1000, ['comment_marked' => [['x' => 1, 'y' => 2, 'c' => 'ff0000']], 'comment_cat' => '4:9']),
		]);
		$this->assertSame('', $fixed[1]['class'], 'a comment of somebody else without anything special has no class');
		$this->assertEqualsCanonicalizing(['commentMarked', 'cat-4', 'cat-9'],
			array_values(array_filter(explode(' ', $fixed[2]['class']))));
	}

	public function testTeacherOwnsEverythingOthersOnlyTheirOwn()
	{
		$me = (int)$GLOBALS['egw_info']['user']['account_id'];
		$comments = [$this->comment(1, $me), $this->comment(2, $me + 1000)];

		$as_student = $this->fix($comments, false);
		$this->assertStringContainsString('commentOwner', $as_student[1]['class'], 'own comment');
		$this->assertStringNotContainsString('commentOwner', $as_student[2]['class'], 'comment of somebody else');

		$as_teacher = $this->fix($comments, true);
		$this->assertStringContainsString('commentOwner', $as_teacher[1]['class']);
		$this->assertStringContainsString('commentOwner', $as_teacher[2]['class'], 'a teacher owns all comments');
	}

	public function testAnArrayIsNotATeacherFlag()
	{
		$this->expectException(\TypeError::class);
		$this->fix([$this->comment(1, 1)], ['course_id' => 1, 'video_id' => 1]);
	}
}
