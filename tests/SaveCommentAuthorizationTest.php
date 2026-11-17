<?php

/**
 * Regression tests for Bo::saveComment()'s authorization hardening
 *
 * @link http://www.egroupware.org
 */

namespace EGroupware\smallpart;

require_once realpath(__DIR__.'/CommentTestBase.php');

use EGroupware\Api;

class SaveCommentAuthorizationTest extends CommentTestBase
{
	/**
	 * A comment_id whose own course/video doesn't match the submitted course_id/video_id
	 * must be rejected.
	 */
	public function testSaveCommentRejectsMismatchedCourseOrVideo()
	{
		$attacker = static::$student1_user;
		$bo = $this->mockBo($attacker, static::$test_course, static::$test_video, static::$participants, null, [
			'42' => [
				'comment_id'     => '42',
				'course_id'      => '999',
				'video_id'       => '999',
				'account_id'     => static::$admin_user,
				'comment_added'  => ['Original comment'],
			],
		]);

		$comment = [
			'comment_id' => '42',
			'course_id'  => static::$test_course['course_id'],
			'video_id'   => static::$test_video['video_id'],
			'action'     => 'retweet',
			'text'       => 'injected reply',
		];

		$this->expectException(Api\Exception\NoPermission::class);
		$bo->saveComment($comment);
	}

	/**
	 * A non-owner, non-staff caller must not be able to use 'edit' to touch a comment
	 * they don't own.
	 */
	public function testEditCannotUseReplyIndexToProveOwnershipOfRoot()
	{
		$attacker = static::$student1_user;
		$bo = $this->mockBo($attacker, static::$test_course, static::$test_video, static::$participants, null, [
			'42' => [
				'comment_id'    => '42',
				'course_id'     => static::$test_course['course_id'],
				'video_id'      => static::$test_video['video_id'],
				'account_id'    => static::$admin_user,
				'comment_added' => [
					'Original comment by admin',
					$attacker, 'Attacker reply',
				],
			],
		]);

		$comment = [
			'comment_id' => '42',
			'course_id'  => static::$test_course['course_id'],
			'video_id'   => static::$test_video['video_id'],
			'action'     => 'edit',
			'index'      => 2,
			'text'       => 'overwritten root text',
		];

		$this->expectException(Api\Exception\NoPermission::class);
		$bo->saveComment($comment);
	}
}
