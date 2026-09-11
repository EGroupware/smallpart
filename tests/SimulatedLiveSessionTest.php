<?php
/**
 * EGroupware - SmallParT (ViDoTeach) - simulated live session tests
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
 * Tests for a material flagged as a "simulated live session": the student watches an already
 * uploaded video on their own, giving live-feedback as they would in a real session, and once
 * they have watched it through the regular analysis interface takes over.
 *
 * The per-student "has watched it through" state deliberately reuses the row Overlay::test*()
 * keeps (egw_smallpart_answers with overlay_id=0), which is why a simulated session and a test
 * duration are mutually exclusive - that exclusion is enforced in Materials' save form, which
 * needs a real Etemplate request and is therefore not covered here.
 *
 * Student\Ui::ajax_livefeedbackSaveComment()'s simulated branch is likewise not unit-tested: it
 * starts with an etemplate_exec_id CSRF check that redirects rather than throwing. Its effect -
 * a feedback comment stored at the position in the video with no egw_smallpart_livefeedback row
 * present - is asserted here through Bo::saveComment() instead, the same way the other comment
 * tests in this directory do it.
 */
class SimulatedLiveSessionTest extends Api\AppTest
{
	use SmallpartTestHelpers;

	/**
	 * A course with one material flagged as a simulated live session, with the student subscribed
	 *
	 * @return array [$course, $video]
	 */
	private function simulatedFixture(): array
	{
		$course = $this->createCourse();
		$video = $this->createVideo($course, [
			'video_livefeedback_simulated' => 1,
			'video_published' => Bo::VIDEO_PUBLISHED,
		]);
		$this->asAccount(self::STUDENT1, function() use ($course)
		{
			(new Bo())->subscribe($course['course_id']);
		});

		return [$course, $video];
	}

	// -------------------------------------------------------------------
	// The flag itself
	// -------------------------------------------------------------------

	public function testFlagRoundTrips()
	{
		[, $video] = $this->simulatedFixture();

		$this->assertEquals(1, $video['video_livefeedback_simulated'],
			'video_livefeedback_simulated must survive saveVideo()/readVideo()');
	}

	public function testFlagDefaultsOff()
	{
		$course = $this->createCourse();
		$video = $this->createVideo($course);

		$this->assertEmpty($video['video_livefeedback_simulated'],
			'a material is not a simulated live session unless it says so');
	}

	/**
	 * Before 26.1.002 the flag was video_options=8. The migration rewrites stored rows, but an
	 * export/import from an older install can still carry the old value, and no other code
	 * understands it any more - so listVideos() normalises it on read.
	 */
	public function testLegacyCommentOptionIsNormalisedOnRead()
	{
		$course = $this->createCourse();
		$video = $this->createVideo($course, ['video_options' => Bo::COMMENTS_SIMULATED_LIVE_SESSION]);

		$this->assertEquals(1, $video['video_livefeedback_simulated'],
			'a legacy video_options=8 must be read as a simulated live session');
		$this->assertEquals(Bo::COMMENTS_SHOW_ALL, $video['video_options'],
			'... and must no longer claim to be an unknown comment-visibility option');
	}

	// -------------------------------------------------------------------
	// Per-student run: start / finish / one-shot
	// -------------------------------------------------------------------

	public function testStartThenFinish()
	{
		[$course, $video] = $this->simulatedFixture();

		$this->asAccount(self::STUDENT1, function() use ($course, $video)
		{
			$bo = new Bo();
			$this->assertTrue($bo->simulatedStart($course['course_id'], $video['video_id']),
				'a session that has not been watched through can be started');
			$this->assertNotFalse(Overlay::testStarted($video['video_id']),
				'starting records a run in progress');

			$bo->simulatedFinish($course['course_id'], $video['video_id'], 42);
			$this->assertFalse(Overlay::testStarted($video['video_id']),
				'finishing marks the run as watched through');
		});
	}

	public function testResumingAnInterruptedRun()
	{
		[$course, $video] = $this->simulatedFixture();

		$this->asAccount(self::STUDENT1, function() use ($course, $video)
		{
			$bo = new Bo();
			$bo->simulatedStart($course['course_id'], $video['video_id']);

			$this->assertTrue($bo->simulatedStart($course['course_id'], $video['video_id']),
				'a student who stopped half-way can carry on - Overlay::testStart() would throw without $ignore_started');
		});
	}

	public function testCanNotBeWatchedTwice()
	{
		[$course, $video] = $this->simulatedFixture();

		$this->asAccount(self::STUDENT1, function() use ($course, $video)
		{
			$bo = new Bo();
			$bo->simulatedStart($course['course_id'], $video['video_id']);
			$bo->simulatedFinish($course['course_id'], $video['video_id'], 42);

			$this->assertFalse($bo->simulatedStart($course['course_id'], $video['video_id']),
				'one pass only, like a real live session');
		});
	}

	/**
	 * The client calls this from the video's ended-callback, which can fire more than once, and
	 * Overlay::testStop() throws when no run is in progress.
	 */
	public function testFinishingTwiceIsHarmless()
	{
		[$course, $video] = $this->simulatedFixture();

		$this->asAccount(self::STUDENT1, function() use ($course, $video)
		{
			$bo = new Bo();
			$bo->simulatedStart($course['course_id'], $video['video_id']);
			$bo->simulatedFinish($course['course_id'], $video['video_id'], 42);
			$bo->simulatedFinish($course['course_id'], $video['video_id'], 42);

			$this->assertFalse(Overlay::testStarted($video['video_id']), 'still just finished');
		});
	}

	public function testOneStudentsRunDoesNotFinishAnothers()
	{
		[$course, $video] = $this->simulatedFixture();
		$this->asAccount(self::STUDENT2, function() use ($course)
		{
			(new Bo())->subscribe($course['course_id']);
		});

		$this->asAccount(self::STUDENT1, function() use ($course, $video)
		{
			$bo = new Bo();
			$bo->simulatedStart($course['course_id'], $video['video_id']);
			$bo->simulatedFinish($course['course_id'], $video['video_id'], 42);
		});

		$this->asAccount(self::STUDENT2, function() use ($course, $video)
		{
			$this->assertTrue((new Bo())->simulatedStart($course['course_id'], $video['video_id']),
				'each student gets their own pass');
		});
	}

	// -------------------------------------------------------------------
	// ACL
	// -------------------------------------------------------------------

	public function testStartNeedsASimulatedMaterial()
	{
		$course = $this->createCourse();
		$video = $this->createVideo($course, ['video_published' => Bo::VIDEO_PUBLISHED]);
		$this->asAccount(self::STUDENT1, function() use ($course)
		{
			(new Bo())->subscribe($course['course_id']);
		});

		$this->expectException(Api\Exception\NoPermission::class);
		$this->asAccount(self::STUDENT1, function() use ($course, $video)
		{
			(new Bo())->simulatedStart($course['course_id'], $video['video_id']);
		});
	}

	public function testStartNeedsToBeAParticipant()
	{
		[$course, $video] = $this->simulatedFixture();

		$this->expectException(Api\Exception\NoPermission::class);
		$this->asAccount(self::STUDENT2, function() use ($course, $video)
		{
			(new Bo())->simulatedStart($course['course_id'], $video['video_id']);
		});
	}

	public function testStartChecksTheVideoBelongsToTheCourse()
	{
		[$course, $video] = $this->simulatedFixture();
		$other = $this->createCourse();

		$this->expectException(Api\Exception\NotFound::class);
		$this->asAccount(self::STUDENT1, function() use ($other, $video)
		{
			(new Bo())->simulatedStart($other['course_id'], $video['video_id']);
		});
	}

	// -------------------------------------------------------------------
	// Feedback is stored against the position in the video
	// -------------------------------------------------------------------

	/**
	 * A simulated session has no egw_smallpart_livefeedback row - there is no shared session to
	 * count a wall-clock offset from, the position in the video IS the time of the feedback.
	 */
	public function testFeedbackIsStoredWithoutALivefeedbackSession()
	{
		[$course, $video] = $this->simulatedFixture();

		$this->assertEmpty((new Bo())->readLivefeedback($course['course_id'], $video['video_id']),
			'a simulated session must not need a livefeedback record');

		// listComments() is ACL-checked against the AMBIENT session, so read it back as the student
		// who wrote it rather than as whoever the test process happens to be logged in as
		[$comment_id, $comment] = $this->asAccount(self::STUDENT1, function() use ($course, $video)
		{
			$bo = new Bo();
			$bo->simulatedStart($course['course_id'], $video['video_id']);

			$comment_id = $bo->saveComment([
				'course_id' => $course['course_id'],
				'video_id' => $video['video_id'],
				'comment_starttime' => 42,
				'comment_stoptime' => 43,
				'comment_cat' => 'free:lfc',
				'action' => 'add',
				'comment_added' => [' '],
			]);

			return [$comment_id, current($bo->listComments($video['video_id'], ['comment_id' => $comment_id]))];
		});

		$this->assertNotEmpty($comment_id);
		$this->assertEquals(42, $comment['comment_starttime'],
			'the feedback is stored at the position in the video the student was at');
	}

	// -------------------------------------------------------------------
	// REST / JSON
	// -------------------------------------------------------------------

	public function testJsonExposesTheFlag()
	{
		[, $video] = $this->simulatedFixture();

		$json = JsObjects::JsMaterial($video, false);
		$this->assertTrue($json['simulatedLiveSession'] ?? false,
			'REST clients need to see that this material is a simulated live session');
	}

	/**
	 * JsObjects::commentType() throws on any video_options it does not know, and 8 was never in
	 * its map - so before the flag moved out of video_options, every REST read of a simulated
	 * material was a fatal.
	 */
	public function testJsonDoesNotThrowForALegacyCommentOption()
	{
		$course = $this->createCourse();
		$video = $this->createVideo($course, ['video_options' => Bo::COMMENTS_SIMULATED_LIVE_SESSION]);

		$json = JsObjects::JsMaterial($video, false);
		$this->assertTrue($json['simulatedLiveSession'] ?? false);
	}
}
