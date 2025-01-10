<?php

namespace EGroupware\smallpart;

require_once realpath(__DIR__ . '/CommentTestBase.php');

use EGroupware\smallpart\CommentTestBase;

class CommentTestReply extends CommentTestBase
{

	/**
	 * Common setup for each reply test sets the comment setting on the video, makes a comment originaly by some user,
	 * then adds a reply by a different user.
	 *
	 * The next step is to checkCommentPush() with the specific requirements for the test, and check the results.
	 *
	 * @param $comment_option
	 * @param $original_commenter
	 * @param $replying_user
	 * @return array
	 * @throws \ReflectionException
	 */
	protected function setupMocks($comment_option, $original_commenter, $replying_user)
	{
		// Current user replying on their own comment
		$comment = $this->commentReplies($original_commenter, null, $replying_user);

		// Expect reply added in
		$expected_comment = $comment;
		$expected_comment['comment_added'][] = $replying_user;
		$expected_comment['comment_added'][] = $comment['text'];

		$test_video = array_merge(static::$test_video, array(
			'video_options' => $comment_option,
		));
		$bo = $this->mockBo($replying_user, static::$test_course, $test_video, static::$participants, null,
							[$comment['comment_id'] => $comment]
		);
		return [
			'bo'               => $bo,
			'comment'          => $comment,
			'expected_comment' => $expected_comment
		];
	}

	/**
	 * Test that any reply by original commenter goes to everyone
	 * @dataProvider participantProvider
	 * @param $participant
	 * @return void
	 */
	public function testReplyShowAll_OwnComment($participant)
	{
		list('bo' => $bo, 'comment' => $comment) = $this->setupMocks(Bo::COMMENTS_SHOW_ALL, $participant, $participant);

		// Every user gets notified
		$this->checkCommentPush($bo, 'retweet', $comment, $this->allUsers());
	}

	/**
	 * Test that any reply to a teacher's comment goes to everyone
	 *
	 * @dataProvider participantProvider
	 * @param $participant
	 * @return void
	 */
	public function testReplyShowAll_CommentOnTeacher($participant)
	{
		list('bo' => $bo, 'comment' => $comment) = $this->setupMocks(Bo::COMMENTS_SHOW_ALL, static::$teacher_user, $participant);

		// Every user gets notified
		$this->checkCommentPush($bo, 'retweet', $comment, $this->allUsers());
	}

	/**
	 * Test that any reply by on a student's comment goes to everyone
	 * @dataProvider participantProvider
	 * @param $participant
	 * @return void
	 */
	public function testReplyShowAll_CommentOnStudent($participant)
	{
		list('bo' => $bo, 'comment' => $comment) = $this->setupMocks(Bo::COMMENTS_SHOW_ALL, static::$student1_user, $participant);

		// Every user gets notified
		$this->checkCommentPush($bo, 'retweet', $comment, $this->allUsers());
	}

	/**
	 * Test that any reply by original commenter goes to everyone except other students
	 *
	 * @dataProvider participantProvider
	 * @param $participant
	 * @return void
	 */
	public function testReplyHideOtherStudents_ReplyToOwnComment($participant)
	{
		// Current user replying on their own comment
		$comment = $this->commentReplies($participant, null, $participant);

		// Expect reply added in
		$expected_comment = $comment;
		$expected_comment['comment_added'][] = $participant;
		$expected_comment['comment_added'][] = $comment['text'];

		$test_video = array_merge(static::$test_video, array(
			'video_options' => Bo::COMMENTS_HIDE_OTHER_STUDENTS
		));
		$bo = $this->mockBo($participant, static::$test_course, $test_video, static::$participants, null,
							[$comment['comment_id'] => $comment]
		);

		// Set up expectations & save - only [staff &] replying user gets notified (empty array)
		$pushes = $this->checkCommentPush(
			$bo, 'retweet', $comment,
			in_array($participant, $this->staff()) ? $this->allUsers() : array_merge($this->staff(), [$participant]), // Notify
			in_array($participant, $this->staff()) ? [] : $this->otherStudents($participant)
		);

		// Check that staff got all replies, students got staff + their own replies
		foreach($pushes as $user_id => $push)
		{
			if(in_array($user_id, $this->staff()))
			{
				$this->assertEquals($expected_comment['comment_added'], $push['comment_added'], 'Wrong replies sent to staff');
			}
			if(in_array($user_id, $this->students()))
			{
				foreach($this->staff() as $staff)
				{
					$this->assertContains($staff, $push['comment_added'], 'Student missing staff replies');
				}
				$this->assertContains($user_id, $push['comment_added'], 'Student missing their comments');
				$this->assertNotContains($this->otherStudents($user_id), $push['comment_added'], 'Student got other student comments');
			}
		}
	}

	/**
	 * Test that any reply on a teacher comment goes to staff & replier, not other students
	 *
	 * @dataProvider studentProvider
	 */
	public function testReplyHideOtherStudents_StudentReplyToTeacherComment($participant)
	{
		// Current user (STUDENT) replying on a teacher's comment
		list(
			'bo' => $bo, 'comment' => $comment, 'expected_comment' => $expected_comment
			) = $this->setupMocks(Bo::COMMENTS_HIDE_OTHER_STUDENTS, static::$teacher_user, $participant);

		// Set up expectations & save - only [staff &] user gets notified (empty array)
		$pushes = $this->checkCommentPush(
			$bo, 'retweet', $comment,
			array_merge($this->staff(), [$participant]),  // Push to staff & user who just replied
			$this->otherStudents($participant)
		);

		// Check that staff got all replies, students got staff + their own replies
		foreach($pushes as $user_id => $push)
		{
			if(in_array($user_id, $this->staff()))
			{
				$this->assertEquals($expected_comment['comment_added'], $push['comment_added'], 'Wrong replies sent');
				continue;
			}
			$this->assertContains($participant, $push['comment_added'], 'User missing their comments');
			foreach($this->otherStudents($participant) as $student)
			{
				$this->assertNotContains($student, $push['comment_added'], 'Comments from other students were included');
			}
		}
	}

	/**
	 * Test that any reply by staff on a student comment goes to staff & original student, not other students
	 *
	 * @dataProvider staffProvider
	 */
	public function testReplyHideOtherStudents_TeacherReplyToStudentComment($participant)
	{
		// Current user replying on a student's comment
		$comment = $this->commentReplies(static::$student1_user, null, $participant);

		// Expect reply added in
		$expected_comment = $comment;
		$expected_comment['comment_added'][] = $participant;
		$expected_comment['comment_added'][] = $comment['text'];

		$test_video = array_merge(static::$test_video, array(
			'video_options' => Bo::COMMENTS_HIDE_OTHER_STUDENTS
		));
		$bo = $this->mockBo($participant, static::$test_course, $test_video, static::$participants, null,
							[$comment['comment_id'] => $comment]
		);

		// Set up expectations & save - only [staff &] user gets notified
		$pushes = $this->checkCommentPush(
			$bo, 'retweet', $comment,
														 // Push to staff, user who just replied and the original student
			array_merge($this->staff(), [static::$student1_user, $participant]),
			$this->otherStudents(static::$student1_user),// Don't notify other students
		);

		// Check that staff got all replies, student got staff + their own replies
		foreach($pushes as $user_id => $push)
		{
			if(in_array($user_id, $this->staff()))
			{
				$this->assertEquals($expected_comment['comment_added'], $push['comment_added'], 'Wrong replies sent');
			}
			else
			{
				$this->assertContains($participant, $push['comment_added'], 'User missing their comments');
				$this->assertNotContains($this->otherStudents($participant), $push['comment_added'], 'Comments from other students were included');
			}
		}
	}

	/**
	 * Test that any reply by original commenter goes to everyone for student comments, only staff for teacher comments
	 *
	 * @dataProvider participantProvider
	 * @param $participant
	 * @return void
	 */
	public function testReplyHideTeacher_ReplyToOwnComment($participant)
	{
		// Current user replying on their own comment
		list(
			'bo' => $bo, 'comment' => $comment, 'expected_comment' => $expected_comment
			) = $this->setupMocks(Bo::COMMENTS_HIDE_TEACHERS, $participant, $participant);

		// Set up expectations & save
		$pushes = $this->checkCommentPush(
			$bo, 'retweet', $comment,
			in_array($participant, $this->staff()) ? $this->staff() : $this->students(),
			in_array($participant, $this->staff()) ? $this->students() : [],
		);

		// Check that staff got all replies, students got student replies
		foreach($pushes as $user_id => $push)
		{
			if(in_array($user_id, $this->staff()))
			{
				$this->assertEquals($expected_comment['comment_added'], $push['comment_added'], 'Wrong replies sent');
			}
			if(in_array($user_id, $this->students()) && $participant == $user_id)
			{
				$this->assertContains($participant, $push['comment_added'], 'User missing their comments');
				foreach($this->students() as $student)
				{
					$this->assertContains($student, $push['comment_added'], 'Replies from students were not sent');
				}
			}
		}
	}

	/**
	 * Test that any reply by a teacher goes to staff, not other students
	 *
	 * @dataProvider staffProvider
	 */
	public function testReplyHideTeacher_TeacherReplyToTeacherComment($participant)
	{
		// Current user (STAFF) replying on a teacher's comment
		list(
			'bo' => $bo, 'comment' => $comment, 'expected_comment' => $expected_comment
			) = $this->setupMocks(Bo::COMMENTS_HIDE_TEACHERS, static::$teacher_user, $participant);

		// Set up expectations & save - only [staff &] user gets notified
		$pushes = $this->checkCommentPush(
			$bo, 'retweet', $comment,
			$this->staff(),                               // Push to staff
			$this->students(),                            // Don't push to students
			[$participant]                                // Must include replier's new reply
		);

		// Check that staff got all replies, students got student replies
		foreach($pushes as $user_id => $push)
		{
			if(in_array($user_id, $this->staff()))
			{
				$this->assertEquals($expected_comment['comment_added'], $push['comment_added'], 'Wrong replies sent');
			}
			if(in_array($user_id, $this->students()) && $participant == $user_id)
			{
				$this->fail('Student should not get this');
			}
		}
	}

	/**
	 * Test that any reply by staff on a student comment goes to everyone
	 *
	 * @dataProvider staffProvider
	 */
	public function testReplyHideTeachers_TeacherReplyToStudentComment($participant)
	{
		// Current user (STAFF) replying on a student's comment
		list(
			'bo' => $bo, 'comment' => $comment, 'expected_comment' => $expected_comment
			) = $this->setupMocks(Bo::COMMENTS_HIDE_TEACHERS, static::$student1_user, $participant);

		// Set up expectations & save - only [staff &] user gets notified
		$pushes = $this->checkCommentPush(
			$bo, 'retweet', $comment,
			// Push to staff is automatic, expect all students
			$this->students(),
		// Teacher reply on a student comment should be visible by staff only in this mode
		);

		// Check that staff got all replies, student(s) got student replies
		foreach($pushes as $user_id => $push)
		{
			if(in_array($user_id, $this->staff()))
			{
				$this->assertEquals($expected_comment['comment_added'], $push['comment_added'], 'Wrong replies sent');
			}
			else
			{
				foreach($this->staff() as $staff)
				{
					$this->assertNotContains($staff, $push['comment_added'], 'Staff comments should not be sent');
				}
				foreach($this->students() as $student)
				{
					$this->assertContains($student, $push['comment_added'], 'Replies from students were not sent');
				}
			}
		}
	}


	/**
	 * Test that any reply by original commenter goes just the commenter (and staff)
	 *
	 * @dataProvider participantProvider
	 * @param $participant
	 * @return void
	 */
	public function testReplyShownOwn_ReplyToOwnComment($participant)
	{
		// Current user replying on their own comment
		list(
			'bo' => $bo, 'comment' => $comment, 'expected_comment' => $expected_comment
			) = $this->setupMocks(Bo::COMMENTS_SHOW_OWN, $participant, $participant);

		// Set up expectations & save
		$pushes = $this->checkCommentPush(
			$bo, 'retweet', $comment,
			in_array($participant, $this->students()) ? [$participant] : [],
			in_array($participant, $this->students()) ? $this->otherStudents($participant) : $this->students(),
		);

		// Check that staff got all replies, students got only their own replies
		foreach($pushes as $user_id => $push)
		{
			if(in_array($user_id, $this->staff()))
			{
				$this->assertEquals($expected_comment['comment_added'], $push['comment_added'], 'Wrong replies sent');
			}
			if(in_array($user_id, $this->students()))
			{
				$this->assertContains($participant, $push['comment_added'], 'User missing their comments');
				foreach($this->otherStudents($participant) as $student)
				{
					$this->assertNotContains($student, $push['comment_added'], 'Replies from other students were sent');
				}
			}
		}
	}

	/**
	 * Test that any reply by staff goes to staff, not any students
	 *
	 * @dataProvider staffProvider
	 */
	public function testReplyShowOwn_TeacherReplyToTeacherComment($participant)
	{
		// Current user (STAFF) replying on a teacher's comment
		list(
			'bo' => $bo, 'comment' => $comment, 'expected_comment' => $expected_comment
			) = $this->setupMocks(Bo::COMMENTS_SHOW_OWN, static::$teacher_user, $participant);

		// Set up expectations & save - only [staff &] user gets notified (empty array)
		$pushes = $this->checkCommentPush(
			$bo, 'retweet', $comment,
			$this->staff(),                               // Push to staff
			$this->students(),                            // Don't push to students
			[$participant]                                // Must include replier's new reply
		);

		// Check that staff got all replies, students got student replies
		foreach($pushes as $user_id => $push)
		{
			if(in_array($user_id, $this->staff()))
			{
				$this->assertEquals($expected_comment['comment_added'], $push['comment_added'], 'Wrong replies sent');
			}
			if(in_array($user_id, $this->students()) && $participant == $user_id)
			{
				$this->assertContains($participant, $push['comment_added'], 'User missing their relies');
				foreach($this->students() as $student)
				{
					$this->assertContains($student, $push['comment_added'], 'Replies from students were not sent');
				}
			}
		}
	}

	/**
	 * Test that any reply by staff on a student comment goes to just staff
	 *
	 * @dataProvider staffProvider
	 */
	public function testReplyShowOwn_TeacherReplyToStudentComment($participant)
	{
		// Current user (STAFF) replying on a student's comment
		list(
			'bo' => $bo, 'comment' => $comment, 'expected_comment' => $expected_comment
			) = $this->setupMocks(Bo::COMMENTS_HIDE_TEACHERS, static::$student1_user, $participant);

		// Set up expectations & save - only [staff &] user gets notified
		$pushes = $this->checkCommentPush(
			$bo, 'retweet', $comment,
			// Push to staff is automatic, expect all students
			$this->students(),
		// Teacher reply on a student comment should be visible by staff only in this mode
		);

		// Check that staff got all replies, student(s) got student replies
		foreach($pushes as $user_id => $push)
		{
			if(in_array($user_id, $this->staff()))
			{
				$this->assertEquals($expected_comment['comment_added'], $push['comment_added'], 'Wrong replies sent');
			}
			else
			{
				foreach($this->staff() as $staff)
				{
					$this->assertNotContains($staff, $push['comment_added'], 'Staff comments should not be sent');
				}
				foreach($this->students() as $student)
				{
					$this->assertContains($student, $push['comment_added'], 'Replies from students were not sent');
				}
			}
		}
	}

	/**
	 * Test that any reply by student commenter goes to whole group (and staff)
	 *
	 * @param $participant
	 * @return void
	 */
	public function testReplyGroup_ReplyToOwnComment()
	{
		// Current user replying on their own comment
		list(
			'bo' => $bo, 'comment' => $comment, 'expected_comment' => $expected_comment
			) = $this->setupMocks(Bo::COMMENTS_GROUP, static::$student1_user, static::$student1_user);

		// Set up expectations & save
		$pushes = $this->checkCommentPush(
			$bo, 'retweet', $comment,
			[static::$student1_user, static::$student2_user], // 1 & 2 are in the same group
			[static::$student3_user],                         // 3 is not
			[static::$student1_user, static::$student2_user]
		);
	}

	/**
	 * Test that any reply by student commenter goes to whole group (and staff)
	 *
	 * @param $participant
	 * @return void
	 */
	public function testReplyGroup_ReplyToGroupComment()
	{
		// Current user replying on their own comment
		list(
			'bo' => $bo, 'comment' => $comment, 'expected_comment' => $expected_comment
			) = $this->setupMocks(Bo::COMMENTS_GROUP, static::$student1_user, static::$student2_user);

		// Set up expectations & save
		$pushes = $this->checkCommentPush(
			$bo, 'retweet', $comment,
			[static::$student1_user, static::$student2_user], // 1 & 2 are in the same group
			[static::$student3_user],                         // 3 is not
			[static::$student1_user, static::$student2_user],
			[static::$student3_user],                         // No replies from 3
		);
	}


	/**
	 * Test that any reply by student commenter on a teacher goes to whole group (and staff),
	 * and other groups are unaffected
	 *
	 * @param $participant
	 * @return void
	 */
	public function testReplyGroup_ReplyToTeacherComment()
	{
		// Current user replying on their own comment
		list(
			'bo' => $bo, 'comment' => $comment, 'expected_comment' => $expected_comment
			) = $this->setupMocks(Bo::COMMENTS_GROUP, static::$teacher_user, static::$student2_user);

		// Set up expectations & save
		$pushes = $this->checkCommentPush(
			$bo, 'retweet', $comment,
			[static::$student1_user, static::$student2_user], // 1 & 2 are in the same group
			[static::$student3_user],                         // 3 is not
			[static::$student1_user, static::$student2_user],
			[static::$student3_user],                         // No replies from 3
		);
	}

	/**
	 * Test that any reply by staff goes to staff, not any students
	 *
	 * @dataProvider staffProvider
	 */
	public function testReplyGroup_TeacherReplyToTeacherComment($participant)
	{
		// Current user (STAFF) replying on a teacher's comment
		list(
			'bo' => $bo, 'comment' => $comment, 'expected_comment' => $expected_comment
			) = $this->setupMocks(Bo::COMMENTS_GROUP, static::$teacher_user, $participant);

		// Set up expectations & save - only [staff &] user gets notified (empty array)
		$pushes = $this->checkCommentPush(
			$bo, 'retweet', $comment,
			$this->allUsers()                               // Push to everyone
		);

		// Check that staff got all replies, student(s) got staff + only their group members replies
		foreach($pushes as $user_id => $push)
		{
			if(in_array($user_id, $this->staff()))
			{
				$this->assertEquals($expected_comment['comment_added'], $push['comment_added'], 'Wrong replies sent');
			}
			elseif(in_array($user_id, [static::$student1_user, static::$student2_user], true))
			{
				// Group 1
				foreach($this->staff() as $staff)
				{
					$this->assertContains($staff, $push['comment_added'], 'Staff replies should be sent');
				}
				$this->assertContains(static::$student1_user, $push['comment_added'], 'Group member replies should be sent');
				$this->assertContains(static::$student2_user, $push['comment_added'], 'Group member replies should be sent');
				$this->assertNotContains(static::$student3_user, $push['comment_added'], 'Student replies from outside group should not be sent');
			}
			else
			{
				// Group 2
				foreach($this->staff() as $staff)
				{
					$this->assertContains($staff, $push['comment_added'], 'Staff replies should be sent');
				}
				$this->assertContains(static::$student3_user, $push['comment_added'], 'Group member replies should be sent');
				$this->assertNotContains(static::$student1_user, $push['comment_added'], 'Student replies from outside group should not be sent');
				$this->assertNotContains(static::$student2_user, $push['comment_added'], 'Student replies from outside group should not be sent');
			}
		}
	}

	/**
	 * Test that any reply by staff on a student comment goes to just staff
	 *
	 * @dataProvider staffProvider
	 */
	public function testReplyGroup_TeacherReplyToStudentComment($participant)
	{
		// Current user (STAFF) replying on a student's comment
		list(
			'bo' => $bo, 'comment' => $comment, 'expected_comment' => $expected_comment
			) = $this->setupMocks(Bo::COMMENTS_GROUP, static::$student1_user, $participant);

		// Set up expectations & save - only [staff &] user gets notified
		$pushes = $this->checkCommentPush(
			$bo, 'retweet', $comment,
			// Push to staff is automatic, expect group students
			[static::$student1_user, static::$student2_user],
		// Teacher reply on a student comment should be visible by staff only in this mode
		);

		// Check that staff got all replies, student(s) got student replies
		foreach($pushes as $user_id => $push)
		{
			if(in_array($user_id, $this->staff()))
			{
				$this->assertEquals($expected_comment['comment_added'], $push['comment_added'], 'Wrong replies sent');
			}
			else
			{
				foreach($this->staff() as $staff)
				{
					$this->assertContains($staff, $push['comment_added'], 'Staff replies should be sent');
				}
				$this->assertContains(static::$student1_user, $push['comment_added'], 'Student replies should be sent');
				$this->assertContains(static::$student2_user, $push['comment_added'], 'Student replies should be sent');
				$this->assertNotContains(static::$student3_user, $push['comment_added'], 'Student replies from outside group should not be sent');
			}
		}
	}
}