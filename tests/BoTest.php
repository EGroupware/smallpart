<?php
/**
 * EGroupware - SmallParT (ViDoTeach) - Bo unit/integration tests
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
 * Tests for EGroupware\SmallParT\Bo: course/participant/video/comment ACL and CRUD.
 *
 * These run against a live, logged-in EGroupware session (see doc/ai/testing.md) - there is no
 * mock layer for Bo, so every test that touches the database creates real rows and cleans them up
 * in tearDown()/tearDownAfterClass().
 *
 * Role-specific behaviour is exercised by actually switching the EGroupware session to a
 * teacher/tutor/student test account (via asAccount(), mirroring LoggedInTest::asAdmin()) rather
 * than passing an explicit account_id to Bo's constructor: Bo::read()'s ACL-filtered form,
 * checkSubscribe() and the static checkTeacher() still use the AMBIENT session rather than the
 * constructor's optional $_account_id override (Bo::$grants and isAdmin()'s isSuperAdmin() check
 * are now correctly scoped to it - see git history - but those three are not). Switching sessions
 * avoids that whole class of test-only footguns and matches production usage (Bo is always
 * constructed for "the current request's user" there).
 */
class BoTest extends Api\AppTest
{
	use SmallpartTestHelpers;

	// -------------------------------------------------------------------
	// Fixture helpers
	// -------------------------------------------------------------------

	// -------------------------------------------------------------------
	// Pure logic: Bo::videoStatus()
	// -------------------------------------------------------------------

	private function videoFixture(array $overrides=[]): array
	{
		return array_merge([
			'video_published' => Bo::VIDEO_PUBLISHED,
			'video_published_start' => null,
			'video_published_end' => null,
			'video_test_duration' => null,
			'video_test_options' => 0,
			'video_test_display' => 0,
		], $overrides);
	}

	public function testVideoStatusDraft()
	{
		$this->assertSame(lang('Draft'), Bo::videoStatus($this->videoFixture(['video_published' => Bo::VIDEO_DRAFT])));
	}

	public function testVideoStatusPublishedUnconditional()
	{
		$this->assertSame(lang('Published'), Bo::videoStatus($this->videoFixture()));
	}

	public function testVideoStatusUnavailable()
	{
		$this->assertSame(lang('Unavailable'), Bo::videoStatus($this->videoFixture(['video_published' => Bo::VIDEO_UNAVAILABLE])));
	}

	public function testVideoStatusReadonly()
	{
		$this->assertSame(lang('Readonly'), Bo::videoStatus($this->videoFixture(['video_published' => Bo::VIDEO_READONLY])));
	}

	public function testVideoStatusTarget()
	{
		$this->assertSame(lang('Target'), Bo::videoStatus($this->videoFixture(['video_published' => Bo::VIDEO_TARGET])));
	}

	public function testVideoStatusPrerequisite()
	{
		$this->assertSame(lang('Prerequisite'), Bo::videoStatus($this->videoFixture(['video_published' => Bo::VIDEO_PUBLISHED_PREREQUISITE])));
	}

	public function testVideoStatusIncludesTestDuration()
	{
		$status = Bo::videoStatus($this->videoFixture(['video_test_duration' => 15]));
		$this->assertStringContainsString(lang('Test %1min', 15), $status);
		$this->assertStringContainsString(lang('Published'), $status);
	}

	// -------------------------------------------------------------------
	// Course CRUD / ACL
	// -------------------------------------------------------------------

	public function testCreateCourseRequiresTeacherRight()
	{
		$this->asAccount(self::STUDENT1, function()
		{
			$this->expectException(Api\Exception\NoPermission::class);
			(new Bo())->save(['course_name' => 'should not be created']);
		});
	}

	public function testCreateCourseAsTeacherSucceeds()
	{
		$course = $this->createCourse(['course_name' => 'Creation test course']);

		$this->assertNotEmpty($course['course_id']);
		$this->assertSame('Creation test course', $course['course_name']);
		$this->assertSame($this->accountId(self::TEACHER), $course['course_owner']);
	}

	public function testReadCourseAsNonParticipantThrowsNoPermission()
	{
		$course = $this->createCourse();

		$this->asAccount(self::STUDENT1, function() use ($course)
		{
			$this->expectException(Api\Exception\NoPermission::class);
			(new Bo())->read($course['course_id']);
		});
	}

	public function testReadCourseAsParticipantSucceeds()
	{
		$course = $this->createCourse();
		$this->asAccount(self::STUDENT1, function() use ($course)
		{
			(new Bo())->subscribe($course['course_id']);
		});

		$read = $this->asAccount(self::STUDENT1, function() use ($course)
		{
			return (new Bo())->read($course['course_id']);
		});
		$this->assertSame((int)$course['course_id'], (int)$read['course_id']);
	}

	public function testUpdateCourseAsTeacherSucceeds()
	{
		$course = $this->createCourse();

		$updated = $this->asAccount(self::TEACHER, function() use ($course)
		{
			return (new Bo())->save(['course_id' => $course['course_id'], 'course_name' => 'Renamed by teacher']);
		});
		$this->assertSame('Renamed by teacher', $updated['course_name']);
	}

	public function testUpdateCourseAsStudentRejected()
	{
		$course = $this->createCourse();
		$this->asAccount(self::STUDENT1, function() use ($course)
		{
			(new Bo())->subscribe($course['course_id']);
		});

		$this->asAccount(self::STUDENT1, function() use ($course)
		{
			$this->expectException(Api\Exception\NoPermission::class);
			(new Bo())->save(['course_id' => $course['course_id'], 'course_name' => 'Hijacked']);
		});
	}

	public function testDeleteCourseRequiresAdmin()
	{
		$course = $this->createCourse();
		$this->asAccount(self::TUTOR, function() use ($course)
		{
			(new Bo())->subscribe($course['course_id']);
		});

		$this->asAccount(self::TUTOR, function() use ($course)
		{
			$this->expectException(Api\Exception\NoPermission::class);
			(new Bo())->deleteCourse($course['course_id']);
		});
	}

	// -------------------------------------------------------------------
	// Participants / subscribe
	// -------------------------------------------------------------------

	public function testSubscribeSelfAsStudent()
	{
		$course = $this->createCourse();

		$this->asAccount(self::STUDENT1, function() use ($course)
		{
			(new Bo())->subscribe($course['course_id']);
		});

		$read = $this->asAccount(self::TEACHER, function() use ($course)
		{
			return (new Bo())->read($course['course_id']);
		});
		$participant = $this->findParticipant($read['participants'], $this->accountId(self::STUDENT1));
		$this->assertNotNull($participant);
		$this->assertSame(Bo::ROLE_STUDENT, (int)$participant['participant_role']);
	}

	public function testSelfSubscribeCannotEscalateRole()
	{
		$course = $this->createCourse();

		// regression test: a plain self-subscribe must always end up as ROLE_STUDENT, even if a
		// higher role is explicitly requested (GHSA-worthy privilege-escalation, now fixed)
		$this->asAccount(self::STUDENT1, function() use ($course)
		{
			(new Bo())->subscribe($course['course_id'], true, null, null, Bo::ROLE_ADMIN);
		});

		$read = $this->asAccount(self::TEACHER, function() use ($course)
		{
			return (new Bo())->read($course['course_id']);
		});
		$participant = $this->findParticipant($read['participants'], $this->accountId(self::STUDENT1));
		$this->assertNotNull($participant);
		$this->assertSame(Bo::ROLE_STUDENT, (int)$participant['participant_role'],
			'self-subscribe must never grant more than student, regardless of requested role');
	}

	public function testSubscribeWithWrongPasswordRejected()
	{
		$course = $this->createCourse(['course_password' => 'correct-horse-battery-staple']);

		$this->asAccount(self::STUDENT1, function() use ($course)
		{
			$this->expectException(Api\Exception\WrongUserinput::class);
			(new Bo())->subscribe($course['course_id'], true, null, 'wrong-password');
		});
	}

	public function testSubscribeWithCorrectPasswordSucceeds()
	{
		$course = $this->createCourse(['course_password' => 'correct-horse-battery-staple']);

		$this->asAccount(self::STUDENT1, function() use ($course)
		{
			(new Bo())->subscribe($course['course_id'], true, null, 'correct-horse-battery-staple');
		});

		$read = $this->asAccount(self::TEACHER, function() use ($course)
		{
			return (new Bo())->read($course['course_id']);
		});
		$this->assertNotNull($this->findParticipant($read['participants'], $this->accountId(self::STUDENT1)));
	}

	public function testSubscribeOthersRequiresTeacher()
	{
		$course = $this->createCourse();
		$this->asAccount(self::TUTOR, function() use ($course)
		{
			(new Bo())->subscribe($course['course_id']);
		});

		// tutor is not a teacher and must not be able to subscribe someone else
		$this->asAccount(self::TUTOR, function() use ($course)
		{
			$this->expectException(Api\Exception\NoPermission::class);
			(new Bo())->subscribe($course['course_id'], true, $this->accountId(self::STUDENT1));
		});
	}

	public function testRegisteringStaffRequiresAdminOrOwner()
	{
		$course = $this->createCourse();

		// promote the tutor to teacher first, so they pass the "isTeacher()" baseline check but
		// are still not a course-admin nor the owner
		$this->asAccount(self::TEACHER, function() use ($course)
		{
			(new Bo())->subscribe($course['course_id'], true, $this->accountId(self::TUTOR), true, Bo::ROLE_TEACHER);
		});

		$this->asAccount(self::TUTOR, function() use ($course)
		{
			$this->expectException(Api\Exception\NoPermission::class);
			(new Bo())->subscribe($course['course_id'], true, $this->accountId(self::STUDENT1), null, Bo::ROLE_TEACHER);
		});
	}

	public function testRegisteringStaffAsAdminSucceeds()
	{
		$course = $this->createCourse();

		$this->asAccount(self::TEACHER, function() use ($course)
		{
			(new Bo())->subscribe($course['course_id'], true, $this->accountId(self::STUDENT1), null, Bo::ROLE_TUTOR);
		});

		$read = $this->asAccount(self::TEACHER, function() use ($course)
		{
			return (new Bo())->read($course['course_id']);
		});
		$participant = $this->findParticipant($read['participants'], $this->accountId(self::STUDENT1));
		$this->assertNotNull($participant);
		$this->assertSame(Bo::ROLE_TUTOR, (int)$participant['participant_role']);
	}

	public function testAutoGroupAssignmentByNumber()
	{
		// regression test for the substr(...,4) vs substr(...,-4) bug: without the fix, students
		// are never assigned to any group at all
		$course = $this->createCourse(['course_groups' => 2, 'groups_mode' => 'number-auto']);

		foreach ([self::STUDENT1, self::STUDENT2] as $lid)
		{
			$this->asAccount($lid, function() use ($course)
			{
				(new Bo())->subscribe($course['course_id']);
			});
		}

		$read = $this->asAccount(self::TEACHER, function() use ($course)
		{
			return (new Bo())->read($course['course_id']);
		});
		$group1 = $this->findParticipant($read['participants'], $this->accountId(self::STUDENT1))['participant_group'];
		$group2 = $this->findParticipant($read['participants'], $this->accountId(self::STUDENT2))['participant_group'];
		$this->assertNotEmpty($group1, 'student1 must have been auto-assigned a group');
		$this->assertNotEmpty($group2, 'student2 must have been auto-assigned a group');
		$this->assertNotEquals($group1, $group2, 'the least-populated group must be picked each time');
	}

	// -------------------------------------------------------------------
	// Videos
	// -------------------------------------------------------------------

	public function testVideoAccessibleDraftDeniedToStudent()
	{
		$course = $this->createCourse();
		$video = $this->createVideo($course, ['video_published' => Bo::VIDEO_DRAFT]);
		$this->asAccount(self::STUDENT1, function() use ($course)
		{
			(new Bo())->subscribe($course['course_id']);
		});

		$accessible = $this->asAccount(self::STUDENT1, function() use ($video)
		{
			return (new Bo())->videoAccessible($video['video_id']);
		});
		$this->assertFalse($accessible);
	}

	public function testVideoAccessiblePublishedGrantedToStudent()
	{
		$course = $this->createCourse();
		$video = $this->createVideo($course, ['video_published' => Bo::VIDEO_PUBLISHED]);
		$this->asAccount(self::STUDENT1, function() use ($course)
		{
			(new Bo())->subscribe($course['course_id']);
		});

		$accessible = $this->asAccount(self::STUDENT1, function() use ($video)
		{
			return (new Bo())->videoAccessible($video['video_id']);
		});
		$this->assertTrue($accessible);
	}

	public function testVideoEditableOwnerVsOther()
	{
		$course = $this->createCourse();
		$video = $this->createVideo($course);

		$this->assertTrue($this->asAccount(self::TEACHER, function() use ($video)
		{
			return (bool)(new Bo())->videoEditable($video);
		}), 'teacher (owner) must be able to edit their own video');

		$this->asAccount(self::STUDENT1, function() use ($course)
		{
			(new Bo())->subscribe($course['course_id']);
		});
		$this->assertFalse($this->asAccount(self::STUDENT1, function() use ($video)
		{
			return (bool)(new Bo())->videoEditable($video);
		}), 'a plain student must not be able to edit someone else\'s video');
	}

	public function testDeleteVideoRequiresTeacherOrOwner()
	{
		$course = $this->createCourse();
		$video = $this->createVideo($course);
		$this->asAccount(self::STUDENT1, function() use ($course)
		{
			(new Bo())->subscribe($course['course_id']);
		});

		$this->asAccount(self::STUDENT1, function() use ($video)
		{
			$this->expectException(Api\Exception\NoPermission::class);
			(new Bo())->deleteVideo($video, true);
		});
	}

	public function testDeleteVideoWithCommentsRequiresConfirmation()
	{
		$course = $this->createCourse();
		$video = $this->createVideo($course);
		$this->asAccount(self::STUDENT1, function() use ($course)
		{
			(new Bo())->subscribe($course['course_id']);
		});
		$this->asAccount(self::STUDENT1, function() use ($course, $video)
		{
			(new Bo())->saveComment([
				'course_id' => $course['course_id'],
				'video_id' => $video['video_id'],
				'action' => 'add',
				'text' => 'a comment',
				'comment_starttime' => 0,
				'comment_stoptime' => 0,
			], false, false);
		});

		$this->asAccount(self::TEACHER, function() use ($video)
		{
			$this->expectException(Api\Exception\WrongParameter::class);
			(new Bo())->deleteVideo($video, false);
		});
	}

	// -------------------------------------------------------------------
	// Comments
	// -------------------------------------------------------------------

	private function addComment(string $as, array $course, array $video, string $text): int
	{
		return $this->asAccount($as, function() use ($course, $video, $text)
		{
			return (new Bo())->saveComment([
				'course_id' => $course['course_id'],
				'video_id' => $video['video_id'],
				'action' => 'add',
				'text' => $text,
				'comment_starttime' => 0,
				'comment_stoptime' => 0,
			], false, false);
		});
	}

	public function testSaveCommentRequiresParticipant()
	{
		$course = $this->createCourse();
		$video = $this->createVideo($course);

		$this->asAccount(self::STUDENT1, function() use ($course, $video)
		{
			$this->expectException(Api\Exception\NoPermission::class);
			(new Bo())->saveComment([
				'course_id' => $course['course_id'],
				'video_id' => $video['video_id'],
				'action' => 'add',
				'text' => 'not a participant yet',
				'comment_starttime' => 0,
				'comment_stoptime' => 0,
			], false, false);
		});
	}

	public function testSaveCommentDeniedWhenVideoIsDraft()
	{
		$course = $this->createCourse();
		$video = $this->createVideo($course, ['video_published' => Bo::VIDEO_DRAFT]);
		$this->asAccount(self::STUDENT1, function() use ($course)
		{
			(new Bo())->subscribe($course['course_id']);
		});

		$this->asAccount(self::STUDENT1, function() use ($course, $video)
		{
			$this->expectException(Api\Exception\NoPermission::class);
			(new Bo())->saveComment([
				'course_id' => $course['course_id'],
				'video_id' => $video['video_id'],
				'action' => 'add',
				'text' => 'draft video comment',
				'comment_starttime' => 0,
				'comment_stoptime' => 0,
			], false, false);
		});
	}

	private function commentVisibilityFixture(): array
	{
		$course = $this->createCourse();
		$video = $this->createVideo($course);
		foreach ([self::STUDENT1, self::STUDENT2] as $lid)
		{
			$this->asAccount($lid, function() use ($course)
			{
				(new Bo())->subscribe($course['course_id']);
			});
		}
		$teacher_comment = $this->addComment(self::TEACHER, $course, $video, 'teacher comment');
		$student1_comment = $this->addComment(self::STUDENT1, $course, $video, 'student1 comment');
		$student2_comment = $this->addComment(self::STUDENT2, $course, $video, 'student2 comment');

		return [$course, $video, $teacher_comment, $student1_comment, $student2_comment];
	}

	private function listCommentsAs(string $lid, array $course, array $video, int $comment_type): array
	{
		return $this->asAccount($lid, function() use ($course, $video, $comment_type)
		{
			$bo = new Bo();
			return $bo->listComments($video['video_id'], [], $comment_type);
		});
	}

	public function testListCommentsShowAll()
	{
		[$course, $video] = $this->commentVisibilityFixture();

		$comments = $this->listCommentsAs(self::STUDENT1, $course, $video, Bo::COMMENTS_SHOW_ALL);
		$this->assertCount(3, $comments);
	}

	public function testListCommentsHideOtherStudents()
	{
		[$course, $video] = $this->commentVisibilityFixture();

		$comments = $this->listCommentsAs(self::STUDENT1, $course, $video, Bo::COMMENTS_HIDE_OTHER_STUDENTS);
		$texts = array_column($comments, 'comment_added');
		// must see own + teacher, but not student2's comment
		$this->assertCount(2, $comments);
	}

	public function testListCommentsHideTeachers()
	{
		[$course, $video] = $this->commentVisibilityFixture();

		$comments = $this->listCommentsAs(self::STUDENT1, $course, $video, Bo::COMMENTS_HIDE_TEACHERS);
		$this->assertCount(2, $comments, 'must see both students\' comments but not the teacher\'s');
	}

	public function testListCommentsShowOwn()
	{
		[$course, $video] = $this->commentVisibilityFixture();

		$comments = $this->listCommentsAs(self::STUDENT1, $course, $video, Bo::COMMENTS_SHOW_OWN);
		$this->assertCount(1, $comments, 'must see only their own comment');
	}

	public function testListCommentsForbiddenByStudents()
	{
		[$course, $video] = $this->commentVisibilityFixture();

		$comments = $this->listCommentsAs(self::STUDENT1, $course, $video, Bo::COMMENTS_FORBIDDEN_BY_STUDENTS);
		$this->assertCount(1, $comments, 'must see only the teacher\'s comment');
	}

	public function testListCommentsDisabled()
	{
		[$course, $video] = $this->commentVisibilityFixture();

		$comments = $this->listCommentsAs(self::STUDENT1, $course, $video, Bo::COMMENTS_DISABLED);
		$this->assertCount(0, $comments);
	}

	public function testListCommentsStaffSeesEverythingRegardlessOfMode()
	{
		[$course, $video] = $this->commentVisibilityFixture();

		$comments = $this->listCommentsAs(self::TEACHER, $course, $video, Bo::COMMENTS_DISABLED);
		$this->assertCount(3, $comments, 'staff must see all comments regardless of the video\'s comment mode');
	}

	public function testDeleteCommentRequiresAdminOrOwner()
	{
		$course = $this->createCourse();
		$video = $this->createVideo($course);
		$this->asAccount(self::STUDENT1, function() use ($course)
		{
			(new Bo())->subscribe($course['course_id']);
		});
		$comment_id = $this->addComment(self::STUDENT1, $course, $video, 'my own comment');

		$this->asAccount(self::STUDENT2, function() use ($course, $comment_id)
		{
			(new Bo())->subscribe($course['course_id']);
		});
		$this->asAccount(self::STUDENT2, function() use ($comment_id)
		{
			$this->expectException(Api\Exception\NoPermission::class);
			(new Bo())->deleteComment($comment_id);
		});

		// but the owner themselves can delete it
		$this->asAccount(self::STUDENT1, function() use ($comment_id)
		{
			(new Bo())->deleteComment($comment_id);
		});
	}

	// -------------------------------------------------------------------
	// Regression test for the "throw new Ap\Db\Exception" typo (uncatchable fatal Error before fix)
	// -------------------------------------------------------------------

	public function testSaveFailureThrowsCatchableDbException()
	{
		$course = $this->createCourse();

		$this->asAccount(self::TEACHER, function() use ($course)
		{
			$bo = new Bo();
			$ref = new \ReflectionProperty(Bo::class, 'so');
			$ref->setAccessible(true);
			$fake_so = new class($this->accountId(self::TEACHER)) extends So
			{
				function save($keys=null, $extra_where=null)
				{
					return 'forced failure';
				}
			};
			$ref->setValue($bo, $fake_so);

			$this->expectException(Api\Db\Exception::class);
			$bo->save(['course_id' => $course['course_id'], 'course_name' => 'trigger failure']);
		});
	}

	// -------------------------------------------------------------------
	// copyCourse()
	// -------------------------------------------------------------------

	/**
	 * copyCourse() has no ACL check of its own beyond what read() (participant) and save()
	 * (checkTeacher(), for creating the new course) already enforce - so it's called as the
	 * teacher throughout, same as creating a course in the first place.
	 */
	private function copyCourse(array $course, ?array $videos=null, ?array $categories=null, ?array $participants=null, array $options=[]): array
	{
		$copy = $this->asAccount(self::TEACHER, function() use ($course, $videos, $categories, $participants, $options)
		{
			return (new Bo())->copyCourse($course['course_id'], $videos, $categories, $participants, $options);
		});
		$this->created_courses[] = $copy['course_id'];

		return $copy;
	}

	public function testCopyCourseDefaultCopiesVideosAndParticipants()
	{
		$course = $this->createCourse();
		$video = $this->createVideo($course, ['video_name' => 'Original video']);
		$this->asAccount(self::STUDENT1, function() use ($course)
		{
			(new Bo())->subscribe($course['course_id']);
		});

		$copy = $this->copyCourse($course);

		$this->assertNotEquals($course['course_id'], $copy['course_id']);
		// Bo::read()'s 'videos' is keyed by video_id (like listVideos()), NOT sequentially indexed
		$copied_video = array_values($copy['videos'])[0];
		$this->assertCount(1, $copy['videos']);
		$this->assertSame('Original video', $copied_video['video_name']);
		$this->assertNotEquals($video['video_id'], $copied_video['video_id'],
			'the copy must be a NEW video row, not the same one');
		$this->assertNotNull($this->findParticipant($copy['participants'], $this->accountId(self::STUDENT1)),
			'student1 must be subscribed to the copy too, with their original role');
	}

	public function testCopyCourseCommentsOptionCopiesComments()
	{
		$course = $this->createCourse();
		$video = $this->createVideo($course);
		$this->asAccount(self::STUDENT1, function() use ($course)
		{
			(new Bo())->subscribe($course['course_id']);
		});
		$this->addComment(self::STUDENT1, $course, $video, 'a comment to copy');

		$copy = $this->copyCourse($course, null, null, null, ['comments' => true]);

		$new_video_id = array_values($copy['videos'])[0]['video_id'];
		$comments = $this->asAccount(self::TEACHER, function() use ($new_video_id)
		{
			return (new Bo())->listComments($new_video_id);
		});
		$this->assertCount(1, $comments);
	}

	public function testCopyCourseWithoutCommentsOptionCopiesNone()
	{
		$course = $this->createCourse();
		$video = $this->createVideo($course);
		$this->asAccount(self::STUDENT1, function() use ($course)
		{
			(new Bo())->subscribe($course['course_id']);
		});
		$this->addComment(self::STUDENT1, $course, $video, 'must not be copied');

		$copy = $this->copyCourse($course);

		$new_video_id = array_values($copy['videos'])[0]['video_id'];
		$comments = $this->asAccount(self::TEACHER, function() use ($new_video_id)
		{
			return (new Bo())->listComments($new_video_id);
		});
		$this->assertCount(0, $comments);
	}

	public function testCopyCourseQuestionsOptionCopiesQuestions()
	{
		$course = $this->createCourse();
		$video = $this->createVideo($course);
		Overlay::write([
			'course_id' => (int)$course['course_id'],
			'video_id' => (int)$video['video_id'],
			'overlay_type' => 'smallpart-question-singlechoice',
			'overlay_start' => 0,
			'max_score' => 10.0,
			'min_score' => 0.0,
			'answers' => [['id' => 'a'], ['id' => 'b']],
			'answer' => 'a',
		]);

		$copy = $this->copyCourse($course, null, null, null, ['questions' => true]);

		$new_video_id = array_values($copy['videos'])[0]['video_id'];
		$read = Overlay::read(['video_id' => $new_video_id, 'course_id' => $copy['course_id']]);
		$this->assertCount(1, $read['elements']);
		$this->assertNotEquals($video['video_id'], $read['elements'][0]['video_id'],
			'the copied question must point at the NEW video, not the original');
	}

	public function testCopyCourseExplicitVideoSubsetOnlyCopiesThose()
	{
		$course = $this->createCourse();
		$video1 = $this->createVideo($course, ['video_name' => 'Keep me']);
		$video2 = $this->createVideo($course, ['video_name' => 'Skip me']);

		$copy = $this->copyCourse($course, [$video1['video_id']]);

		$this->assertCount(1, $copy['videos']);
		$this->assertSame('Keep me', array_values($copy['videos'])[0]['video_name']);
	}

	public function testCopyCourseEmptyParticipantsWithCommentsLeavesUnsubscribed()
	{
		$course = $this->createCourse();
		$video = $this->createVideo($course);
		$this->asAccount(self::STUDENT1, function() use ($course)
		{
			(new Bo())->subscribe($course['course_id']);
		});

		$copy = $this->copyCourse($course, null, null, [], ['comments' => true]);

		$participant = $this->findParticipant($copy['participants'], $this->accountId(self::STUDENT1));
		$this->assertNotNull($participant, 'participant row must still exist (subscribed then unsubscribed)');
		$this->assertNotEmpty($participant['participant_unsubscribed']);
	}

	public function testCopyCoursePrerequisiteRemappedToNewVideoId()
	{
		$course = $this->createCourse();
		$prereq_video = $this->createVideo($course, ['video_name' => 'Prerequisite']);
		$dependent_video = $this->createVideo($course, [
			'video_name' => 'Dependent',
			'video_published' => Bo::VIDEO_PUBLISHED_PREREQUISITE,
			'video_published_prerequisite' => (string)$prereq_video['video_id'],
		]);

		$copy = $this->copyCourse($course);

		$new_prereq_id = null;
		$new_dependent = null;
		foreach ($copy['videos'] as $video)
		{
			if ($video['video_name'] === 'Prerequisite') $new_prereq_id = $video['video_id'];
			if ($video['video_name'] === 'Dependent') $new_dependent = $video;
		}
		$this->assertNotNull($new_prereq_id);
		$this->assertNotNull($new_dependent);
		$prerequisite = is_array($new_dependent['video_published_prerequisite'])
			? $new_dependent['video_published_prerequisite'][0]
			: $new_dependent['video_published_prerequisite'];
		$this->assertEquals($new_prereq_id, $prerequisite,
			'the copied dependent video must point at the NEW prerequisite video id, not the original');
	}

	// -------------------------------------------------------------------
	// Binary video upload (Bo::addVideo()/updateVideo()) - no Collabora/VFS needed, video files are
	// plain filesystem writes (Bo::videoPath()); the app never inspects the actual bytes, only the
	// Content-Type string, so a real video/PDF encoding is not needed either
	// -------------------------------------------------------------------

	/**
	 * @var string[] paths of temp files created by uploadFixture(), removed in tearDown()
	 */
	private array $uploadTempFiles = [];

	protected function tearDownUploadTempFiles(): void
	{
		foreach ($this->uploadTempFiles as $path)
		{
			if (file_exists($path)) unlink($path);
		}
		$this->uploadTempFiles = [];
	}

	private function uploadFixture(string $mime='video/mp4', string $bytes='fake video bytes', string $name='test.mp4'): array
	{
		$path = tempnam(sys_get_temp_dir(), 'smallpart-upload-');
		file_put_contents($path, $bytes);
		$this->uploadTempFiles[] = $path;

		return ['tmp_name' => $path, 'type' => $mime, 'name' => $name];
	}

	public function testAddVideoStoresRealFileContent()
	{
		$course = $this->createCourse();

		$video = $this->asAccount(self::TEACHER, function() use ($course)
		{
			$bo = new Bo();
			return $bo->addVideo($course['course_id'], $this->uploadFixture('video/mp4', 'hello mp4 bytes', 'lesson.mp4'));
		});

		$this->assertNotEmpty($video['video_hash']);
		$path = (new Bo())->videoPath($video);
		$this->assertFileExists($path);
		$this->assertSame('hello mp4 bytes', file_get_contents($path));
		// a teacher/staff upload has no personal owner
		$this->assertNull($video['owner']);

		$this->tearDownUploadTempFiles();
	}

	public function testAddVideoRejectsUnsupportedMimeType()
	{
		$course = $this->createCourse();

		$this->asAccount(self::TEACHER, function() use ($course)
		{
			$bo = new Bo();
			$this->expectException(Api\Exception\WrongUserinput::class);
			$bo->addVideo($course['course_id'], $this->uploadFixture('text/plain', 'not a video', 'notes.txt'));
		});

		$this->tearDownUploadTempFiles();
	}

	public function testAddVideoRequiresUploadPermission()
	{
		$course = $this->createCourse();
		$this->asAccount(self::STUDENT1, function() use ($course)
		{
			(new Bo())->subscribe($course['course_id']);
		});

		$this->asAccount(self::STUDENT1, function() use ($course)
		{
			// student_uploads is not configured on this course, so a plain student may never upload
			$this->expectException(Api\Exception\NoPermission::class);
			(new Bo())->addVideo($course['course_id'], $this->uploadFixture());
		});

		$this->tearDownUploadTempFiles();
	}

	public function testUpdateVideoReplacesFileAndRemovesOld()
	{
		$course = $this->createCourse();

		[$video, $first_path] = $this->asAccount(self::TEACHER, function() use ($course)
		{
			$bo = new Bo();
			$video = $bo->addVideo($course['course_id'], $this->uploadFixture('video/mp4', 'version one', 'lesson.mp4'));
			return [$video, $bo->videoPath($video)];
		});
		$this->assertSame('version one', file_get_contents($first_path));

		$second_path = $this->asAccount(self::TEACHER, function() use ($video)
		{
			$bo = new Bo();
			$bo->updateVideo($video, $this->uploadFixture('video/webm', 'version two', 'lesson.webm'));
			return $bo->videoPath($bo->readVideo($video['video_id']));
		});

		$this->assertFileDoesNotExist($first_path, 'the old (mp4) file must be removed once the new (webm) one is written');
		$this->assertFileExists($second_path);
		$this->assertSame('version two', file_get_contents($second_path));

		$this->tearDownUploadTempFiles();
	}

	public function testDeleteVideoRemovesUploadedFile()
	{
		$course = $this->createCourse();

		[$video, $path] = $this->asAccount(self::TEACHER, function() use ($course)
		{
			$bo = new Bo();
			$video = $bo->addVideo($course['course_id'], $this->uploadFixture());
			return [$video, $bo->videoPath($video)];
		});
		$this->assertFileExists($path);

		$this->asAccount(self::TEACHER, function() use ($video)
		{
			(new Bo())->deleteVideo($video, true);
		});

		$this->assertFileDoesNotExist($path);

		$this->tearDownUploadTempFiles();
	}

	// -------------------------------------------------------------------
	// Bo::file_access() - VFS ACL gate for /apps/smallpart/$course_id/... (used by
	// Api\Link::file_access() via Vfs\Links\StreamWrapper::check_extended_acl())
	// -------------------------------------------------------------------

	public function testFileAccessInvalidCourseIdReturnsFalse()
	{
		$this->assertFalse(Bo::file_access(0, Api\Acl::READ, ''));
		$this->assertFalse(Bo::file_access(-1, Api\Acl::READ, ''));
		$this->assertFalse(Bo::file_access('not-a-number', Api\Acl::READ, ''));
	}

	public function testFileAccessCourseRootNonParticipantDenied()
	{
		$course = $this->createCourse();

		// STUDENT1 never subscribed to this course at all
		$access = $this->asAccount(self::STUDENT1, function() use ($course)
		{
			return Bo::file_access($course['course_id'], Api\Acl::READ, '');
		});
		$this->assertFalse($access);
	}

	public function testFileAccessCourseRootReadGrantedToParticipant()
	{
		$course = $this->createCourse();
		$this->asAccount(self::STUDENT1, function() use ($course)
		{
			(new Bo())->subscribe($course['course_id']);
		});

		$access = $this->asAccount(self::STUDENT1, function() use ($course)
		{
			return Bo::file_access($course['course_id'], Api\Acl::READ, '');
		});
		$this->assertEquals(1, $access);
	}

	public function testFileAccessCourseRootEditDeniedToPlainStudent()
	{
		$course = $this->createCourse();
		$this->asAccount(self::STUDENT1, function() use ($course)
		{
			(new Bo())->subscribe($course['course_id']);
		});

		$access = $this->asAccount(self::STUDENT1, function() use ($course)
		{
			return Bo::file_access($course['course_id'], Api\Acl::EDIT, '');
		});
		$this->assertFalse($access);
	}

	public function testFileAccessCourseRootEditGrantedToTutor()
	{
		$course = $this->createCourse();
		$this->asAccount(self::TEACHER, function() use ($course)
		{
			(new Bo())->subscribe($course['course_id'], true, $this->accountId(self::TUTOR), null, Bo::ROLE_TUTOR);
		});

		$access = $this->asAccount(self::TUTOR, function() use ($course)
		{
			return Bo::file_access($course['course_id'], Api\Acl::EDIT, '');
		});
		$this->assertEquals(1, $access);
	}

	public function testFileAccessStaffBypassesVideoDirectoryChecks()
	{
		$course = $this->createCourse();
		// draft video: would be denied to a student, but staff (tutor+) always get full access
		$video = $this->createVideo($course, ['video_published' => Bo::VIDEO_DRAFT]);
		$this->asAccount(self::TEACHER, function() use ($course)
		{
			(new Bo())->subscribe($course['course_id'], true, $this->accountId(self::TUTOR), null, Bo::ROLE_TUTOR);
		});

		$access = $this->asAccount(self::TUTOR, function() use ($course, $video)
		{
			return Bo::file_access($course['course_id'], Api\Acl::EDIT, $video['video_id'].'/'.self::STUDENT1);
		});
		$this->assertTrue($access);
	}

	public function testFileAccessDraftVideoDeniedToStudent()
	{
		$course = $this->createCourse();
		$video = $this->createVideo($course, ['video_published' => Bo::VIDEO_DRAFT]);
		$this->asAccount(self::STUDENT1, function() use ($course)
		{
			(new Bo())->subscribe($course['course_id']);
		});

		$access = $this->asAccount(self::STUDENT1, function() use ($course, $video)
		{
			return Bo::file_access($course['course_id'], Api\Acl::READ, $video['video_id'].'/all');
		});
		$this->assertEquals(0, $access);
	}

	public function testFileAccessAllDirReadOnlyForStudents()
	{
		$course = $this->createCourse();
		$video = $this->createVideo($course, ['video_published' => Bo::VIDEO_PUBLISHED]);
		$this->asAccount(self::STUDENT1, function() use ($course)
		{
			(new Bo())->subscribe($course['course_id']);
		});

		[$read, $edit] = $this->asAccount(self::STUDENT1, function() use ($course, $video)
		{
			return [
				Bo::file_access($course['course_id'], Api\Acl::READ, $video['video_id'].'/all'),
				Bo::file_access($course['course_id'], Api\Acl::EDIT, $video['video_id'].'/all'),
			];
		});
		$this->assertEquals(1, $read, 'students must have read access to the shared "all" dir');
		$this->assertEquals(0, $edit, 'students must NOT have write access to the shared "all" dir');
	}

	public function testFileAccessOwnDirFullAccessForOwner()
	{
		$course = $this->createCourse();
		$video = $this->createVideo($course, ['video_published' => Bo::VIDEO_PUBLISHED]);
		$this->asAccount(self::STUDENT1, function() use ($course)
		{
			(new Bo())->subscribe($course['course_id']);
		});

		[$read, $edit] = $this->asAccount(self::STUDENT1, function() use ($course, $video)
		{
			return [
				Bo::file_access($course['course_id'], Api\Acl::READ, $video['video_id'].'/'.self::STUDENT1),
				Bo::file_access($course['course_id'], Api\Acl::EDIT, $video['video_id'].'/'.self::STUDENT1),
			];
		});
		$this->assertEquals(1, $read);
		$this->assertEquals(1, $edit, 'a student must have full access to their own comment dir');
	}

	public function testFileAccessOtherStudentDirHiddenWhenCommentsHideOtherStudents()
	{
		$course = $this->createCourse();
		$video = $this->createVideo($course, [
			'video_published' => Bo::VIDEO_PUBLISHED,
			'video_options' => Bo::COMMENTS_HIDE_OTHER_STUDENTS,
		]);
		foreach ([self::STUDENT1, self::STUDENT2] as $lid)
		{
			$this->asAccount($lid, function() use ($course)
			{
				(new Bo())->subscribe($course['course_id']);
			});
		}

		$access = $this->asAccount(self::STUDENT1, function() use ($course, $video)
		{
			return Bo::file_access($course['course_id'], Api\Acl::READ, $video['video_id'].'/'.self::STUDENT2);
		});
		$this->assertEquals(0, $access);
	}

	public function testFileAccessOtherStudentDirVisibleByDefault()
	{
		$course = $this->createCourse();
		// default video_options (COMMENTS_SHOW_ALL) - other students' comments are visible
		$video = $this->createVideo($course, ['video_published' => Bo::VIDEO_PUBLISHED]);
		foreach ([self::STUDENT1, self::STUDENT2] as $lid)
		{
			$this->asAccount($lid, function() use ($course)
			{
				(new Bo())->subscribe($course['course_id']);
			});
		}

		[$read, $edit] = $this->asAccount(self::STUDENT1, function() use ($course, $video)
		{
			return [
				Bo::file_access($course['course_id'], Api\Acl::READ, $video['video_id'].'/'.self::STUDENT2),
				Bo::file_access($course['course_id'], Api\Acl::EDIT, $video['video_id'].'/'.self::STUDENT2),
			];
		});
		$this->assertEquals(1, $read);
		$this->assertEquals(0, $edit, 'a student must never have write access to another student\'s dir');
	}

	/**
	 * Regression test: file_access($course_id, $check, $rel_path, $user) constructs "new self($user)"
	 * specifically to check access on behalf of an explicit $user (its own docblock; also how
	 * Vfs\Links\StreamWrapper::check_extended_acl() calls Api\Link::file_access(..., $this->user) for
	 * eg. background/merge-print or admin-impersonation VFS access, where $this->user can differ
	 * from the real logged-in session). Before the fix, Bo::$grants (used by isAdmin()'s co-owner/
	 * deputy-rights check, which isParticipant()'s admin fallback uses) was always computed from the
	 * AMBIENT session (Acl::get_grants() with no $user arg), NOT from $_account_id - so file_access()
	 * could grant access based on the CALLER's own ACL grants instead of the checked $user's.
	 */
	public function testFileAccessIgnoresAmbientSessionGrants()
	{
		$course = $this->createCourse(); // owned by TEACHER
		$teacher_id = $this->accountId(self::TEACHER);
		$student1_id = $this->accountId(self::STUDENT1); // not subscribed to this course at all
		$student2_id = $this->accountId(self::STUDENT2);

		// TEACHER (the course owner) grants STUDENT2 a deputy edit-right over their OWN smallpart
		// data - a real, valid ACL grant, unrelated to this particular course
		$GLOBALS['egw']->acl->add_repository('smallpart', $student2_id, $teacher_id, Api\Acl::EDIT);
		try
		{
			$access = $this->asAccount(self::STUDENT2, function() use ($course, $student1_id)
			{
				// ambient session is STUDENT2 (holds the deputy grant above), but we're checking
				// access on behalf of STUDENT1, who has no role on this course whatsoever
				return Bo::file_access($course['course_id'], Api\Acl::EDIT, '', $student1_id);
			});
		}
		finally
		{
			$GLOBALS['egw']->acl->delete_repository('smallpart', $student2_id, $teacher_id);
		}

		$this->assertFalse($access,
			"file_access() must check \$user's own rights, not the ambient session's grants");
	}
}
