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
 * than passing an explicit account_id to Bo's constructor: Bo::$grants/$is_admin are computed in
 * the constructor from $GLOBALS['egw']->acl (the AMBIENT real session), not from the constructor's
 * optional $_account_id override, so a "new Bo($other_account_id)" instance would silently keep
 * using the CURRENT session's grants for anything beyond plain participant-role bitmask checks
 * (isParticipant()/isTeacher()/isAdmin() do correctly respect the override, but Bo::read()'s
 * ACL-filtered form, checkSubscribe() and the static checkTeacher() do not). Switching sessions
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
}
