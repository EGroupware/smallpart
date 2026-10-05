<?php
/**
 * EGroupware - SmallParT (ViDoTeach) - the ajax endpoints the two lists' actions now call
 *
 * @link https://www.egroupware.org
 * @package smallpart
 * @subpackage tests
 * @license https://spdx.org/licenses/AGPL-3.0-or-later.html GNU Affero General Public License v3.0 or later
 */

namespace EGroupware\SmallParT;

use EGroupware\Api;

require_once realpath(__DIR__.'/../../api/tests/AppTest.php');
require_once __DIR__.'/SmallpartTestHelpers.php';

/**
 * Courses::ajax_action() and Questions::ajax_action() both already existed, but neither was
 * reachable from the lists and neither checked an eTemplate request id. The course list's "Copy
 * Course"/"Copy Course without participants" and the question list's Exempt/Readd/Delete used to
 * submit the whole eTemplate instead.
 *
 * Two bugs the conversion had to fix to make them usable:
 *
 * - Both answered `egw.refresh(..., $selected[1], 'update')`. The second id of a one-element
 *   selection does not exist, so a single-row action named no row and the list quietly did not
 *   update it.
 * - Copying a course called `Api\Framework::redirect_link(); exit;` in the middle of action().
 *   A submit can redirect; an ajax request cannot - it would have to answer the XHR with a 302.
 *   action() now reports the new course_id back and each caller opens it its own way.
 *
 * PASS CRITERIA
 * The copy/question really happened (read back through Bo/Overlay), and the response carries an
 * egw.refresh naming the right row - plus, for a copy, the egw_open that replaces the redirect.
 */
class AjaxActionTest extends Api\AppTest
{
	use SmallpartTestHelpers;

	protected function setUp(): void
	{
		parent::setUp();
		Api\Json\Response::get()->initResponseArray();
	}

	/**
	 * A real eTemplate request id, the way the browser sends one along - the endpoints refuse
	 * without it, see Nextmatch::validateExecId().  Writing to the request is what persists it.
	 */
	private function execId(): string
	{
		$request = \EGroupware\Api\Etemplate\Request::read();
		$id = $request->id();
		$request->content = ['nm' => []];
		unset($request);
		return $id;
	}

	/**
	 * All response chunks of the given shape, eg. every 'apply' of 'egw.refresh'
	 */
	private function responseCalls(string $func): array
	{
		$response = Api\Json\Response::get();
		$prop = (new \ReflectionClass($response))->getProperty('responseArray');
		$prop->setAccessible(true);
		$found = [];
		foreach((array)$prop->getValue($response) as $chunk)
		{
			$chunk = (array)$chunk;
			if (($chunk['data']['func'] ?? null) === $func)
			{
				$found[] = (array)$chunk['data']['parms'];
			}
		}
		return $found;
	}

	/**
	 * A question on a fresh course+video, returned as [course, video, overlay_id]
	 *
	 * Everything happens inside the teacher's session on purpose: asAccount() switches the
	 * EGroupware session, so an Api\Cache::setSession() written outside it lands in the other
	 * user's session and the action then reads an empty filter - which Overlay::aclCheck()
	 * answers with "Permisson denied!", nothing to do with the user's actual role.
	 */
	private function makeQuestion(): array
	{
		$course = $this->createCourse();
		$video  = $this->createVideo($course);
		$overlay_id = $this->asAccount(self::TEACHER, function() use ($course, $video) {
			$this->forgetBoInstance();
			return Overlay::write([
				'course_id'     => $course['course_id'],
				'video_id'      => $video['video_id'],
				'overlay_start' => 0,
				'overlay_type'  => 'smallpart-overlay-html',
				'overlay_data'  => ['html' => 'phpunit question'],
			]);
		});
		if (empty($overlay_id))
		{
			$this->markTestSkipped('could not create an overlay question on this instance');
		}
		return [$course, $video, $overlay_id];
	}

	/**
	 * Record the list's saved state, which Questions::action() reads the course and video out of
	 * when it is not handed a filter
	 *
	 * Must be called inside the SAME asAccount() callback as the action it is for: each
	 * asAccount() switches the EGroupware session, so a Cache::setSession() from an earlier block
	 * is not there any more. The action then reads an empty filter and Overlay::aclCheck()
	 * answers "Permisson denied!" - which looks like a rights problem and is not one.
	 */
	private function rememberListState(array $course, array $video): void
	{
		Api\Cache::setSession(Questions::class, 'state', ['col_filter' => [
			'course_id' => $course['course_id'],
			'video_id'  => $video['video_id'],
		]]);
	}

	/**
	 * Drop Bo's process-wide singleton, so the next getInstance() is built for the account the
	 * test has switched to rather than the one that happened to be logged in first
	 */
	private function forgetBoInstance(): void
	{
		$property = (new \ReflectionClass(Bo::class))->getProperty('instance');
		$property->setAccessible(true);
		$property->setValue(null, null);
	}

	/**
	 * Response::message() chunks, which are a 'message' type rather than an 'apply' of a function
	 */
	private function responseMessages(): array
	{
		$response = Api\Json\Response::get();
		$prop = (new \ReflectionClass($response))->getProperty('responseArray');
		$prop->setAccessible(true);
		$found = [];
		foreach((array)$prop->getValue($response) as $chunk)
		{
			$chunk = (array)$chunk;
			if (($chunk['type'] ?? null) === 'message') $found[] = (array)$chunk['data'];
		}
		return $found;
	}

	/**
	 * Copying a course must not redirect, and must tell the client to open the copy.
	 */
	public function testCopyCourseOpensTheCopyInsteadOfRedirecting()
	{
		$course = $this->createCourse();

		$this->asAccount(self::TEACHER, function() use ($course) {
			(new Courses())->ajax_action($this->execId(), 'copy_course', [$course['course_id']], false);
		});

		$open = $this->responseCalls('egw_open');
		$this->assertNotEmpty($open, 'the copy has to be opened, the way the redirect did');
		$new_id = (int)$open[0][0];
		$this->assertNotSame((int)$course['course_id'], $new_id, 'and it must be the COPY');
		$this->created_courses[] = $new_id;

		$copy = $this->asAccount(self::TEACHER, fn() => (new Bo())->read($new_id));
		$this->assertNotEmpty($copy, 'the copy must really exist');

		$refresh = $this->responseCalls('egw.refresh');
		$this->assertNotEmpty($refresh, 'and the list must be told');
		$this->assertNull($refresh[0][2], 'a copy lands wherever the sort puts it, so no single id');
	}

	/**
	 * Without a valid exec id the endpoint must do nothing at all.
	 */
	public function testABogusExecIdCopiesNothing()
	{
		$course = $this->createCourse();

		$this->asAccount(self::TEACHER, function() use ($course) {
			(new Courses())->ajax_action('smallpart_nobody_not-real', 'copy_course',
				[$course['course_id']], false);
		});

		$this->assertEmpty($this->responseCalls('egw_open'), 'nothing may be opened');
		$this->assertEmpty($this->responseCalls('egw.refresh'), 'and nothing refreshed');
	}

	/**
	 * A single-row action has to name THAT row - $selected[0], not $selected[1] - in both
	 * endpoints, which carry the same two lines.
	 */
	public function testCoursesSingleRowActionNamesTheRowItActedOn()
	{
		$course = $this->createCourse();

		$this->asAccount(self::TEACHER, function() use ($course) {
			(new Courses())->ajax_action($this->execId(), 'close', [$course['course_id']], false);
		});

		$refresh = $this->responseCalls('egw.refresh');
		$this->assertNotEmpty($refresh, 'the endpoint must answer with egw.refresh');
		$this->assertEquals($course['course_id'], $refresh[0][2],
			'the row acted on, not the non-existent second one');
		$this->assertSame('update', $refresh[0][3], 'closing a course changes a row, it does not remove it');
	}

	/**
	 * Exempt changes a question in place, so the list updates that row rather than dropping it.
	 */
	public function testExemptNamesTheQuestionAndUpdatesIt()
	{
		[$course, $video, $overlay_id] = $this->makeQuestion();

		$this->asAccount(self::TEACHER, function() use ($course, $video, $overlay_id) {
			$this->forgetBoInstance();
			$this->rememberListState($course, $video);
			(new Questions())->ajax_action($this->execId(), 'exempt', [$overlay_id], false);
		});

		$refresh = $this->responseCalls('egw.refresh');
		$this->assertNotEmpty($refresh, 'the endpoint must answer with egw.refresh');
		$this->assertEquals($overlay_id, $refresh[0][2],
			'the row acted on, not the non-existent second one');
		$this->assertSame('update', $refresh[0][3], 'exempt changes a row, it does not remove it');
	}

	/**
	 * Delete removes the question, so the refresh type has to say so.
	 */
	public function testDeletingAQuestionAsksForARowDelete()
	{
		[$course, $video, $overlay_id] = $this->makeQuestion();

		$this->asAccount(self::TEACHER, function() use ($course, $video, $overlay_id) {
			$this->forgetBoInstance();
			$this->rememberListState($course, $video);
			(new Questions())->ajax_action($this->execId(), 'delete', [$overlay_id], false);
		});

		$refresh = $this->responseCalls('egw.refresh');
		$this->assertNotEmpty($refresh, 'the endpoint must answer with egw.refresh');
		$this->assertEquals($overlay_id, $refresh[0][2]);
		$this->assertSame('delete', $refresh[0][3]);
		$this->assertEmpty($this->responseMessages(), 'and no error message alongside it');
	}

	/**
	 * When the action throws - here Overlay::aclCheck() refusing a delete - the endpoint must
	 * answer with the message and NOT with an egw.refresh: telling the list a row went away when
	 * it did not is worse than not telling it anything.
	 */
	public function testAFailedActionDoesNotClaimTheRowChanged()
	{
		$course = $this->createCourse();
		$video = $this->createVideo($course);

		$overlay_id = $this->asAccount(self::TEACHER, function() use ($course, $video) {
			return Overlay::write([
				'course_id'    => $course['course_id'],
				'video_id'     => $video['video_id'],
				'overlay_start' => 0,
				'overlay_type' => 'smallpart-overlay-html',
				'overlay_data' => ['html' => 'phpunit question'],
			]);
		});
		if (empty($overlay_id))
		{
			$this->markTestSkipped('could not create an overlay question on this instance');
		}

		// as a student: Overlay::aclCheck($course_id, true) wants a teacher
		$this->asAccount(self::STUDENT1, function() use ($course, $video, $overlay_id) {
			Api\Cache::setSession(Questions::class, 'state', ['col_filter' => [
				'course_id' => $course['course_id'],
				'video_id'  => $video['video_id'],
			]]);
			(new Questions())->ajax_action($this->execId(), 'delete', [$overlay_id], false);
		});

		$this->assertEmpty($this->responseCalls('egw.refresh'),
			'a refused delete must not answer with egw.refresh');
		$this->assertNotEmpty($this->responseMessages(), 'but it does have to say why');
	}

	/**
	 * Without a valid exec id the question endpoint must do nothing either.
	 */
	public function testABogusExecIdChangesNoQuestion()
	{
		$this->asAccount(self::TEACHER, function() {
			(new Questions())->ajax_action('smallpart_nobody_not-real', 'delete', [1], false);
		});

		$this->assertEmpty($this->responseCalls('egw.refresh'),
			'a rejected request must not answer with egw.refresh');
	}
}
