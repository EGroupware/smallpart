<?php

/**
 * Tests for comment access & updates
 *
 * Staff (admin, teacher, tutor) always see all comments and all replies
 * Reply visibility matches comment visibility - staff always see all comments and replies
 *
 *
 * @link http://www.egroupware.org
 * @author Nathan Gray
 */

namespace EGroupware\smallpart;
require_once realpath(__DIR__ . '/CommentTestBase.php');

use Egroupware\Api;
use EGroupware\Api\Exception\NoPermission;

// TODO: Replace withConsecutive()
class CommentTestAdd extends CommentTestBase
{
	/**
	 * Test that with no restrictions, everyone gets all comments & replies
	 *
	 * @param array $participant Participant to test
	 *
	 * @dataProvider participantProvider
	 */
	public function testAddCommentShowAll($participant)
	{
		$test_video = array_merge(static::$test_video, array(
			'video_options' => Bo::COMMENTS_SHOW_ALL
		));
		$bo = $this->mockBo($participant, static::$test_course, $test_video, static::$participants);

		$comment = $this->commentSimple($participant);

		// Set up expectations - every user gets notified
		$this->checkCommentPush($bo, 'add', $comment, $this->allUsers());
	}

	/**
	 * Make sure student comments aren't sent to other students when comments are set to HIDE OTHER STUDENTS
	 *
	 * @param $student
	 *
	 *
	 * @dataProvider studentProvider
	 * @return void
	 * @throws \EGroupware\Api\Exception\NoPermission
	 * @throws \EGroupware\Api\Exception\NotFound
	 * @throws \EGroupware\Api\Exception\WrongParameter
	 * @throws \ReflectionException
	 */
	public function testAddCommentHideOtherStudents($student)
	{
		$test_video = array_merge(static::$test_video, array(
			'video_options' => Bo::COMMENTS_HIDE_OTHER_STUDENTS
		));
		$bo = $this->mockBo($student, static::$test_course, $test_video, static::$participants);

		$comment = $this->commentSimple($student);

		// Notify all staff, but only the commenting student
		$this->checkCommentPush($bo, 'add', $comment, array_merge($this->staff(), [$student]), $this->otherStudents($student));
	}

	/**
	 * Make sure teacher comments aren't sent to students when comments are set to COMMENTS_HIDE_TEACHERS
	 *
	 * @param $student
	 *
	 * @return void
	 * @throws \EGroupware\Api\Exception\NoPermission
	 * @throws \EGroupware\Api\Exception\NotFound
	 * @throws \EGroupware\Api\Exception\WrongParameter
	 * @throws \ReflectionException
	 */
	public function testAddCommentHideTeachers()
	{
		$test_video = array_merge(static::$test_video, array(
			'video_options' => Bo::COMMENTS_GROUP_HIDE_TEACHERS
		));
		$bo = $this->mockBo(static::$teacher_user, static::$test_course, $test_video, static::$participants);

		$comment = $this->commentSimple(static::$teacher_user);

		// Comment sent to teachers only, no students
		$this->checkCommentPush($bo, 'add', $comment, $this->staff(), $this->students());
	}


	/**
	 * Make sure only student's own comments are sent to only themselves when comments are set to COMMENTS_SHOW_OWN
	 * Staff can see all comments
	 *
	 * @param $student
	 *
	 * @return void
	 * @throws \EGroupware\Api\Exception\NoPermission
	 * @throws \EGroupware\Api\Exception\NotFound
	 * @throws \EGroupware\Api\Exception\WrongParameter
	 * @throws \ReflectionException
	 */
	public function testAddCommentShowOwnStudent()
	{
		$test_video = array_merge(static::$test_video, array(
			'video_options' => Bo::COMMENTS_SHOW_OWN
		));
		$bo = $this->mockBo(static::$student1_user, static::$test_course, $test_video, static::$participants);

		$comment = $this->commentSimple(static::$student1_user);

		$this->checkCommentPush($bo, 'add', $comment, [static::$student1_user], $this->otherStudents(static::$student1_user));
	}

	/**
	 * Make sure teacher comments are not sent to students when comments are set to COMMENTS_SHOW_OWN
	 * Staff can see all comments
	 *
	 * @param $student
	 *
	 * @return void
	 * @throws \EGroupware\Api\Exception\NoPermission
	 * @throws \EGroupware\Api\Exception\NotFound
	 * @throws \EGroupware\Api\Exception\WrongParameter
	 * @throws \ReflectionException
	 */
	public function testAddCommentShowOwnTeacher()
	{
		$current_user = static::$teacher_user;
		$test_video = array_merge(static::$test_video, array(
			'video_options' => Bo::COMMENTS_SHOW_OWN
		));
		$bo = $this->mockBo($current_user, static::$test_course, $test_video, static::$participants);

		$comment = $this->commentSimple($current_user);

		// Teacher comment sent to only staff
		$this->checkCommentPush($bo, 'add', $comment, $this->staff(), $this->students());
	}

	/**
	 * Make sure student comments are sent to students in the same group when comments are set to COMMENTS_GROUP
	 * Staff can see all comments
	 *
	 * @param $student
	 *
	 * @return void
	 * @throws \EGroupware\Api\Exception\NoPermission
	 * @throws \EGroupware\Api\Exception\NotFound
	 * @throws \EGroupware\Api\Exception\WrongParameter
	 * @throws \ReflectionException
	 */
	public function testAddCommentGroupStudent()
	{
		$current_user = static::$student1_user;
		$test_video = array_merge(static::$test_video, array(
			'video_options' => Bo::COMMENTS_GROUP
		));
		$bo = $this->mockBo($current_user, static::$test_course, $test_video, static::$participants);

		$comment = $this->commentSimple($current_user);

		$this->checkCommentPush($bo, 'add', $comment,
								array_merge($this->staff(), [static::$student1_user, static::$student2_user]),
								[static::$student3_user]
		);
	}

	/**
	 * Comments & replies sent only to same group members when COMMENTS_GROUP_HIDE_TEACHERS
	 *
	 * @return void
	 * @throws \EGroupware\Api\Exception\NoPermission
	 * @throws \EGroupware\Api\Exception\NotFound
	 * @throws \EGroupware\Api\Exception\WrongParameter
	 */
	public function testAddCommentGroupHideTeachers()
	{
		$test_video = array_merge(static::$test_video, array(
			'video_options' => Bo::COMMENTS_GROUP_HIDE_TEACHERS
		));
		$bo = $this->mockBo(static::$student1_user, static::$test_course, $test_video, static::$participants);

		$comment = $this->commentSimple(static::$student1_user);

		// Student comment sent to their own group
		$this->checkCommentPush($bo, 'add', $comment,
								[static::$student1_user, static::$student2_user],
								[static::$student3_user]
		);
	}


	/**
	 * Comments & replies not possible from students when comments are COMMENTS_FORBIDDEN_BY_STUDENTS
	 *
	 * @return void
	 * @throws \EGroupware\Api\Exception\NoPermission
	 * @throws \EGroupware\Api\Exception\NotFound
	 * @throws \EGroupware\Api\Exception\WrongParameter
	 */
	public function testAddCommentForbiddenByStudents()
	{
		$current_user = static::$student1_user;
		$test_video = array_merge(static::$test_video, array(
			'video_options' => Bo::COMMENTS_FORBIDDEN_BY_STUDENTS
		));
		$bo = $this->mockBo($current_user, static::$test_course, $test_video, static::$participants);

		$comment = $this->commentSimple($current_user);

		// Set up expectations - NoPermissionException
		$bo->expects($this->never())->method('pushOnline');
		$this->expectException(NoPermission::class);

		// Do it
		$bo->saveComment($comment);
	}


}