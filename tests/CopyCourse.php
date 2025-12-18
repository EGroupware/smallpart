<?php
/**
 * EGroupware - SmallParT - Tests
 *
 * @link https://www.egroupware.org
 * @package smallpart
 * @subpackage tests
 * @license https://spdx.org/licenses/AGPL-3.0-or-later.html GNU Affero General Public License v3.0 or later
 */

namespace EGroupware\SmallParT;


require_once realpath(__DIR__ . '/../../api/tests/AppTest.php');

use EGroupware\Api\AppTest;
use EGroupware\Api;


/**
 * Test cases for Bo::copyCourse method
 */
class CopyCourse extends AppTest
{
	/**
	 * @var Bo
	 */
	protected $bo;

	/**
	 * @var array Test course data
	 */
	protected $testCourse;

	/**
	 * Set up test environment
	 */
	protected function setUp() : void
	{
		$this->bo = new Bo();

		// Create and save a test course
		$courseData = [
			'course_name'        => 'Test Course',
			'course_description' => 'Test Description',
			'course_org'         => $GLOBALS['egw_info']['user']['account_primary_group'],
			'course_owner'       => $GLOBALS['egw_info']['user']['account_id'],
			'course_options'     => '{}',
			'course_certificate' => '',
			'course_closed'      => 0
		];

		// Save the course first
		$course = $this->bo->save($courseData);
		$course_id = $course['course_id'];
		$this->assertNotEmpty($course_id, 'Failed to create test course');

		// Add some test videos
		$videos = [
			[
				'video_name'        => 'Test Video 1',
				'video_description' => 'Test Video Description 1',
				'video_duration'    => 120,
				'course_id'         => $course_id,
				'video_options'     => '{}',
			],
			[
				'video_name'        => 'Test Video 2',
				'video_description' => 'Test Video Description 2',
				'video_duration'    => 180,
				'course_id'         => $course_id,
				'video_options'     => '{}',
			]
		];

		$video_ids = [];
		foreach($videos as $video)
		{
			$video_id = $this->bo->saveVideo($video);
			$this->assertNotEmpty($video_id, 'Failed to create test video');
			$this->video_ids[] = $video_id;
		}

		// Store the complete test course data
		$this->testCourse = $this->bo->read($course_id);
		$this->assertNotEmpty($this->testCourse, 'Failed to read test course');
	}

	/**
	 * Test successful course copy
	 */
	public function testCopyCourseSuccess()
	{

		$result = $this->bo->copyCourse($this->testCourse['course_id']);

		$this->assertIsArray($result);
		$this->assertArrayHasKey('course_id', $result);
		$this->assertEquals($this->testCourse['course_name'], $result['course_name']);
		$this->assertEquals($this->testCourse['course_description'], $result['course_description']);
		$this->assertEquals(count($this->testCourse['videos']), count($result['videos']));
	}


	/**
	 * Test course copy with invalid course ID
	 */
	public function testCopyCourseInvalidId()
	{
		$this->expectException(Api\Exception\WrongParameter::class);
		$this->bo->copyCourse(-1, ['course_name' => 'Test']);
	}

	/**
	 * Test course copy with video copying enabled
	 */
	public function testCopyCourseWithVideos()
	{
		$result = $this->bo->copyCourse($this->testCourse['course_id'], $this->video_ids);

		$this->assertIsArray($result);
		$this->assertArrayHasKey('videos', $result);
		$this->assertCount(count($this->testCourse['videos']), $result['videos']);

		// Check if videos were copied
		$index = 0;
		foreach($result['videos'] as $video)
		{
			$oldVideo = $this->testCourse['videos'][$this->video_ids[$index++]];
			$this->assertNotEquals($oldVideo['video_id'], $video['video_id']);
			$this->assertEquals($oldVideo['video_name'], $video['video_name']);
		}
	}

	/**
	 * Test course copy without videos
	 */
	public function testCopyCourseWithoutVideos()
	{
		$result = $this->bo->copyCourse($this->testCourse['course_id'], []);

		$this->assertIsArray($result);
		$this->assertArrayHasKey('videos', $result);
		$this->assertEmpty($result['videos']);
	}

	/**
	 * Clean up after tests
	 */
	protected function tearDown() : void
	{
		if(!empty($this->testCourse['course_id']))
		{
			try
			{
				$this->bo->deleteCourse($this->testCourse['course_id']);
			}
			catch (\Exception $e)
			{
				// Ignore cleanup errors
			}
		}
	}
}