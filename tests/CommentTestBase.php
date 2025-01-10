<?php

namespace EGroupware\smallpart;


require_once realpath(__DIR__ . '/../../api/tests/AppTest.php');    // Application test base
use EGroupware\Api\AppTest;

class CommentTestBase extends AppTest
{
// Users used for testing
	protected static $admin_user = 8;
	protected static $teacher_user = 9;
	protected static $tutor_user = 10;

	protected static $student1_user = 11;
	protected static $student2_user = 12;
	protected static $student3_user = 13;

	protected static $test_course = array(
		'course_id'                   => '1',
		'course_name'                 => 'Test course',
		'course_owner'                => 9,
		'course_org'                  => '-1',
		'course_closed'               => '0',
		'course_secret'               => 'MfIIzDj6l0m6lcRdmYk03xnPNzyDzD9p',
		'course_options'              => '0',
		'course_groups'               => '2',
		'course_info'                 => 'Test course, not actually in DB',
		'allow_neutral_lf_categories' => '1'
	);

	protected static $test_video = array(
		'course_id'       => '1',
		'video_id'        => '1',
		'video_name'      => 'Test video',
		'video_options'   => '1',
		'video_published' => '1'
	);

	protected static $participants = array();

	public static function setUpBeforeClass() : void
	{
		parent::setUpBeforeClass();

		// Common class participants
		static::$participants = [
			static::$admin_user    => ['account_id'       => static::$tutor_user,
									   'participant_role' => Bo::ROLE_ADMIN],
			static::$teacher_user  => ['account_id'       => static::$teacher_user,
									   'participant_role' => Bo::ROLE_TEACHER],
			static::$tutor_user    => ['account_id'       => static::$tutor_user,
									   'participant_role' => Bo::ROLE_TUTOR],
			static::$student1_user => ['account_id'        => static::$student1_user,
									   'participant_role'  => Bo::ROLE_STUDENT,
									   'participant_group' => 1],
			static::$student2_user => ['account_id'        => static::$student2_user,
									   'participant_role'  => Bo::ROLE_STUDENT,
									   'participant_group' => 1],
			// Student 3 is in a different group
			static::$student3_user => ['account_id'        => static::$student3_user,
									   'participant_role'  => Bo::ROLE_STUDENT,
									   'participant_group' => 2]
		];
	}

	protected function setUp() : void
	{
		$this->bo = new Bo();

		// Make pushComment method accessible to test
		$class = new \ReflectionClass($this->bo);
		$this->check_method = $class->getMethod('pushComment');
		$this->check_method->setAccessible(true);
	}


	/**
	 * Participant provider for when you want to test on every participant in the course
	 * @return array[]
	 */
	public function participantProvider()
	{
		return array_merge($this->staffProvider(), $this->studentProvider());
	}

	/**
	 * Provider for testing all staff
	 */
	public function staffProvider()
	{
		return array(
			'Admin user'   => array(static::$admin_user),
			'Teacher user' => array(static::$teacher_user),
			'Tutor user'   => array(static::$tutor_user),
		);
	}

	/**
	 * Participant provider for testing every student
	 * @return array[]
	 */
	public function studentProvider()
	{
		return array(
			'Student #1'                   => array(static::$student1_user),
			'Student #2'                   => array(static::$student2_user),
			'Student #3 (different group)' => array(static::$student3_user),
		);
	}

	public function allUsers()
	{
		return array_merge($this->staff(), $this->students());
	}

	public function staff()
	{
		return array(static::$admin_user, static::$teacher_user, static::$tutor_user);
	}

	public function students()
	{
		return array(static::$student1_user, static::$student2_user, static::$student3_user);
	}

	public function otherStudents($student) : array
	{
		return array_diff($this->students(), [$student]);
	}

	/**
	 * Mock the BO to the point we can use it for testing comment pushing
	 *
	 * @param $test_course
	 * @param $test_video
	 * @param $participants
	 * @param $online
	 * @return (Bo&\PHPUnit\Framework\MockObject\MockObject)|\PHPUnit\Framework\MockObject\MockObject
	 * @throws \ReflectionException
	 */
	protected function mockBo($current_user, $test_course, $test_video, $participants, $online = null, $existing_comments = null)
	{
		$test_course = array_merge($test_course, array(
			'participants' => array_values($participants)
		));
		$existing_comments = $existing_comments ?? [];
		$bo = $this->getMockBuilder(Bo::class)
				   ->setConstructorArgs([$current_user])
				   ->onlyMethods(['read', 'readVideo', 'pushOnline', 'onlineUsers'])
				   ->getMock();
		$class = new \ReflectionClass($bo);
		$bo->method('read')->willReturn($test_course);
		$bo->method('readVideo')->willReturn($test_video);
		$class->getMethod('onlineUsers')->setAccessible(true);
		$bo->method('onlineUsers')
		   ->will($this->returnCallback(function ($users) use ($online)
		   {

			   return array_intersect($users, $online ?? static::allUsers());
		   })
		   );

		// Mock so->participants to use our test course participants
		$mocked_so = $this->getMockBuilder(So::class)
						  ->setConstructorArgs([$current_user])
						  ->onlyMethods(['participants', 'listComments'])
						  ->getMock();
		$mocked_so->method('participants')
				  ->with()
				  ->will($this->returnCallback(function ($course, $by_account_id = false, $subscribed = true, $role = Bo::ROLE_STUDENT) use ($participants)
				  {
					  $filtered = array_filter($participants, function ($participant) use ($role)
					  {
						  return ($participant['participant_role'] & (int)$role) == (int)$role;
					  });
					  return $by_account_id ? $filtered : array_values($filtered);
				  })
				  );
		$mocked_so->method('listComments')->willReturn($existing_comments);

		$so = $class->getProperty('so');
		$so->setAccessible(true);
		$so->setValue($bo, $mocked_so);

		return $bo;
	}


	/**
	 * Set up a simple comment, ready to add
	 *
	 * @param $commenter
	 * @param $comment_text
	 * @return array
	 */
	protected function commentSimple($commenter, $comment_text = '') : array
	{
		return array(
			'course_id'    => static::$test_course['course_id'],
			'video_id'     => static::$test_video['video_id'],
			'action'       => 'add',
			'comment_text' => "$comment_text - Test comment by $commenter",
			'account_id'   => $commenter,
		);
	}

	/**
	 * Set up a reply to a complicated comment where everyone had something to say
	 *
	 * @param $commenter
	 * @param $comment_text
	 * @param $reply_by
	 */
	protected function commentReplies($commenter, $comment_text, $reply_by) : array
	{
		$comment = $this->commentSimple($commenter, $comment_text ?? "Comment with replies");
		$comment['comment_id'] = '1';
		$comment['action'] = 'retweet';
		$comment['comment_added'] = [
			// Original comment
			$comment['comment_text'],
			// User ID, Reply
			$commenter, "Original user ($commenter) replying to themself",
			static::$admin_user, "Admin user comment",
			static::$teacher_user, "Teacher user comment",
			static::$tutor_user, "Tutor user comment",
			static::$student1_user, "Student 1 user comment",
			static::$student2_user, "Student 2 user comment",
			static::$student3_user, "Student 3 user comment - different group"
		];
		$comment['text'] = "Test reply by $reply_by";
		return $comment;
	}

	/**
	 * Test saving a reply and check who it gets pushed to
	 *
	 * When replies are complicated, like teacher is expected to get all replies but students only theirs,
	 * don't use $reply_users or $not_reply_users.  Instead, check the returned push array after.
	 *
	 * @param $bo Mocked BO object
	 * @param $push_type string push type
	 * @param $comment Comment to save
	 * @param $notified_users List of user IDs who must be pushed to
	 * @param $not_notified_users List of user IDs who must not be pushed to
	 * @param $reply_users List of user IDs whose replies must be included
	 * @param $not_reply_users List of user IDs whose replies must not be included
	 */
	protected function checkCommentPush($bo, string $push_type, $comment, $notified_users, $not_notified_users = [], $reply_users = [], $not_reply_users = [])
	{
		// Types without replies
		$no_reply_types = ['add'];

		// Users who we push to
		$actual_notified = [];

		// Comment replies included
		$actual_replies = [];

		// User => data we pushed to them
		$actual_pushes = [];

		// Staff are always notified, so pull any staff out
		$notified_users = array_unique(array_diff($notified_users, $this->staff())) ?? [];
		sort($notified_users);

		// Staff always get all replies, so pull any staff out
		$staff_replies = [];
		$reply_users = array_unique(array_diff($reply_users, $this->staff())) ?? [];
		sort($reply_users);

		$bo->expects($this->atLeastOnce())
		   ->method('pushOnline')
		   ->with($this->isType('array'), $this->isType('string'), $push_type)
		   ->willReturnCallback(function ($users, $notify_id, $type, $notify_data) use (&$actual_notified, $not_notified_users, &$actual_replies, $not_reply_users, &$actual_pushes, &$staff_replies)
		   {
			   // Staff always get all comments, so ignore those
			   $staff_push = [];
			   foreach($users as $user)
			   {
				   $actual_pushes[$user] = $notify_data;
				   if(in_array($user, $this->staff()))
				   {
					   $staff_push[] = $user;
				   }
			   }
			   $actual_notified = array_merge($actual_notified, $users);
			   $wrong_notified = array_intersect($users, $not_notified_users);
			   $this->assertEmpty($wrong_notified, "Pushed to someone we weren't supposed to (" . print_r($wrong_notified, true) . ")");

			   // Filter comment owner IDs out of replies
			   $reply_users = array_filter($notify_data['comment_added'], function ($v, $k)
			   {
				   return $k % 2 == 1 && is_int($v);
			   },                          ARRAY_FILTER_USE_BOTH);


			   // Staff always get all replies, so can't really check on the staff push
			   if(count($staff_push) == count($users))
			   {
				   $staff_replies = $reply_users;
			   }
			   else
			   {
				   $actual_replies = array_merge($actual_replies, $reply_users);

				   $wrong_replies = array_intersect($reply_users, $not_reply_users);
				   $this->assertEmpty($wrong_replies, "Includes replies from someone we weren't supposed to (" . implode(", ", $wrong_replies) . ")");
			   }
		   });

		// Do it
		$bo->saveComment($comment);

		// Don't care about double-notification right now
		$actual_notified = array_unique($actual_notified);
		sort($actual_notified);
		sort($actual_replies);

		// Staff always get all comments & replies
		$this->assertEmpty(array_diff($this->staff(), $actual_notified), "Staff did not get push comment");
		if(!in_array($push_type, $no_reply_types)) // No replies when adding
		{
			$this->assertEmpty(array_diff($this->staff(), $staff_replies), "Staff did not get push replies");
		}

		// Remove staff for rest of checks
		$actual_notified = array_diff($actual_notified, $this->staff());
		$actual_replies = array_diff($actual_replies, $this->staff());

		// Pushed to everyone we expected
		sort($actual_notified);
		$this->assertEquals($notified_users, $actual_notified, "Did not push to everyone expected");

		// Including replies from everyone expected
		$actual_replies = array_unique($actual_replies);
		sort($actual_replies);
		if(count($reply_users) && !in_array($push_type, $no_reply_types))
		{
			$this->assertEquals($reply_users, $actual_replies, "Did not include replies from everyone expected");
		}

		return $actual_pushes;
	}
}