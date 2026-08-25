<?php
/**
 * EGroupware - SmallParT (ViDoTeach) - Export (json import/export) unit/integration tests
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
 * Tests for EGroupware\SmallParT\Export: jsonImport() (new-course and overwrite-existing-course
 * import) and the pure csv_escape() formatting helper.
 *
 * Export is transfer-BETWEEN-installs (a portable JSON bundle), unlike Bo::copyCourse()'s
 * in-install duplication already covered in BoTest.php - no VFS/video-file bundling at all,
 * metadata only. Not reachable via REST (grep of ApiHandler.php/JsObjects.php/doc/REST-API.md
 * confirms zero references) - classic Courses.php UI only, so Bo-level tests only.
 *
 * jsonExport()/downloadComments() are deliberately NOT tested here: both `echo` their output and
 * call `exit`, which would terminate the whole PHPUnit process, not just one test.
 */
class ExportTest extends Api\AppTest
{
	use SmallpartTestHelpers;

	/**
	 * @var string[] paths of temp files created by jsonFileFixture(), removed in tearDown()
	 */
	private array $exportTempFiles = [];

	protected function tearDownExportTempFiles(): void
	{
		foreach ($this->exportTempFiles as $path)
		{
			if (file_exists($path)) unlink($path);
		}
		$this->exportTempFiles = [];
	}

	/**
	 * Write a hand-built course-export JSON structure to a real temp file, optionally compressed,
	 * and return the et2-file-widget-shaped array jsonImport() expects.
	 */
	private function jsonFileFixture(array $course_data, ?string $compress=null): array
	{
		$json = json_encode($course_data);
		$name = 'export.json';
		$type = 'application/json';
		switch ($compress)
		{
			case 'gz':
				$json = gzencode($json);
				$name = 'export.json.gz';
				$type = 'application/gz';
				break;
			case 'bz2':
				$json = bzcompress($json);
				$name = 'export.json.bz2';
				$type = 'application/x-bz2';
				break;
		}
		$path = tempnam(sys_get_temp_dir(), 'smallpart-export-');
		file_put_contents($path, $json);
		$this->exportTempFiles[] = $path;

		return ['tmp_name' => $path, 'name' => $name, 'type' => $type];
	}

	private function videoData(array $overrides=[]): array
	{
		return array_merge([
			'video_name' => 'Imported video '.bin2hex(random_bytes(4)),
			'video_question' => '',
			// absolute URL: jsonImport() only rewrites relative (single-leading-slash) URLs,
			// and the comment/overlay video-matching below compares the *unrewritten* URL
			'video_url' => 'https://example.org/videos/some-video.mp4',
			'video_type' => 'mp4',
			'video_published' => Bo::VIDEO_PUBLISHED,
			'video_options' => 0,
			'video_test_duration' => null,
			'video_test_options' => 0,
			'video_test_display' => 0,
			'comments' => [],
			'overlay' => [],
			'questions' => [],
		], $overrides);
	}

	private function courseExportData(array $overrides=[]): array
	{
		return array_merge([
			'course_name' => 'Exported course '.bin2hex(random_bytes(4)),
			'course_info' => '',
			'course_disclaimer' => '',
			'course_options' => 0,
			'allow_neutral_lf_categories' => 0,
			'cats' => [],
			'videos' => [$this->videoData()],
		], $overrides);
	}

	// -------------------------------------------------------------------
	// New-course import
	// -------------------------------------------------------------------

	public function testJsonImportCreatesNewCourse()
	{
		$data = $this->courseExportData(['course_name' => 'My Export']);
		$file = $this->jsonFileFixture($data);

		$course_id = $this->asAccount(self::TEACHER, function() use ($file)
		{
			return (new Export())->jsonImport(null, $file, false, []);
		});
		$this->created_courses[] = $course_id;

		$course = $this->asAccount(self::TEACHER, function() use ($course_id)
		{
			return (new Bo())->read($course_id);
		});
		$this->assertSame('My Export ('.lang('Import').')', $course['course_name']);
		$this->assertCount(1, $course['videos']);
		$this->assertSame($data['videos'][0]['video_name'], array_values($course['videos'])[0]['video_name']);

		$this->tearDownExportTempFiles();
	}

	public function testJsonImportNewCourseRequiresTeacherRight()
	{
		$file = $this->jsonFileFixture($this->courseExportData());

		$this->asAccount(self::STUDENT1, function() use ($file)
		{
			$this->expectException(Api\Exception\NoPermission::class);
			(new Export())->jsonImport(null, $file, false, []);
		});

		$this->tearDownExportTempFiles();
	}

	public function testJsonImportInvalidJsonThrowsWrongUserinput()
	{
		$path = tempnam(sys_get_temp_dir(), 'smallpart-export-');
		file_put_contents($path, 'this is not json at all');
		$this->exportTempFiles[] = $path;
		$file = ['tmp_name' => $path, 'name' => 'export.json', 'type' => 'application/json'];

		$this->asAccount(self::TEACHER, function() use ($file)
		{
			$this->expectException(Api\Exception\WrongUserinput::class);
			(new Export())->jsonImport(null, $file, false, []);
		});

		$this->tearDownExportTempFiles();
	}

	public function testJsonImportGzipCompressed()
	{
		$data = $this->courseExportData(['course_name' => 'Gzip Export']);
		$file = $this->jsonFileFixture($data, 'gz');

		$course_id = $this->asAccount(self::TEACHER, function() use ($file)
		{
			return (new Export())->jsonImport(null, $file, false, []);
		});
		$this->created_courses[] = $course_id;

		$course = $this->asAccount(self::TEACHER, function() use ($course_id)
		{
			return (new Bo())->read($course_id);
		});
		$this->assertSame('Gzip Export ('.lang('Import').')', $course['course_name']);

		$this->tearDownExportTempFiles();
	}

	public function testJsonImportBzip2Compressed()
	{
		if (!extension_loaded('bz2'))
		{
			$this->markTestSkipped('bz2 extension not available');
		}
		$data = $this->courseExportData(['course_name' => 'Bzip2 Export']);
		$file = $this->jsonFileFixture($data, 'bz2');

		$course_id = $this->asAccount(self::TEACHER, function() use ($file)
		{
			return (new Export())->jsonImport(null, $file, false, []);
		});
		$this->created_courses[] = $course_id;

		$course = $this->asAccount(self::TEACHER, function() use ($course_id)
		{
			return (new Bo())->read($course_id);
		});
		$this->assertSame('Bzip2 Export ('.lang('Import').')', $course['course_name']);

		$this->tearDownExportTempFiles();
	}

	// -------------------------------------------------------------------
	// Overwrite-into-existing-course import
	// -------------------------------------------------------------------

	public function testJsonImportOverwriteRequiresAdmin()
	{
		$course = $this->createCourse();
		$this->asAccount(self::STUDENT1, function() use ($course)
		{
			(new Bo())->subscribe($course['course_id']);
		});
		$file = $this->jsonFileFixture($this->courseExportData());

		$this->asAccount(self::STUDENT1, function() use ($course, $file)
		{
			$this->expectException(Api\Exception\NoPermission::class);
			(new Export())->jsonImport($course['course_id'], $file, true, []);
		});

		$this->tearDownExportTempFiles();
	}

	public function testJsonImportOverwriteReplacesVideosAndParticipants()
	{
		$course = $this->createCourse();
		$original_video = $this->createVideo($course, ['video_name' => 'Original video']);
		$this->asAccount(self::STUDENT1, function() use ($course)
		{
			(new Bo())->subscribe($course['course_id']);
		});

		$data = $this->courseExportData([
			'videos' => [$this->videoData(['video_name' => 'Replacement video'])],
		]);
		$file = $this->jsonFileFixture($data);

		$this->asAccount(self::TEACHER, function() use ($course, $file)
		{
			(new Export())->jsonImport($course['course_id'], $file, true, []);
		});

		$updated = $this->asAccount(self::TEACHER, function() use ($course)
		{
			return (new Bo())->read($course['course_id']);
		});
		$this->assertCount(1, $updated['videos']);
		$this->assertSame('Replacement video', array_values($updated['videos'])[0]['video_name']);
		$this->assertNotEquals($original_video['video_id'], array_values($updated['videos'])[0]['video_id']);

		$student_participant = $this->findParticipant($updated['participants'], $this->accountId(self::STUDENT1));
		$this->assertNotEmpty($student_participant['participant_unsubscribed'] ?? null,
			'the original participant must have been unsubscribed by the overwrite');

		$teacher_participant = $this->findParticipant($updated['participants'], $this->accountId(self::TEACHER));
		$this->assertSame(Bo::ROLE_ADMIN, (int)$teacher_participant['participant_role'],
			'the importer must end up subscribed as course-admin after an overwrite');

		$this->tearDownExportTempFiles();
	}

	// -------------------------------------------------------------------
	// Comments / questions import
	// -------------------------------------------------------------------

	public function testJsonImportComments()
	{
		$data = $this->courseExportData([
			'videos' => [$this->videoData([
				'video_name' => 'Video with comment',
				'comments' => [[
					'comment_added' => ['an imported comment'],
					'comment_starttime' => 0,
					'comment_stoptime' => 0,
					'comment_color' => 'ffffff',
				]],
			])],
		]);
		$file = $this->jsonFileFixture($data);

		$course_id = $this->asAccount(self::TEACHER, function() use ($file)
		{
			return (new Export())->jsonImport(null, $file, false, ['participants' => true]);
		});
		$this->created_courses[] = $course_id;

		$comments = $this->asAccount(self::TEACHER, function() use ($course_id)
		{
			$bo = new Bo();
			$course = $bo->read($course_id);
			$video = array_values($course['videos'])[0];
			return $bo->listComments($video['video_id']);
		});
		$this->assertCount(1, $comments);
		$this->assertSame('an imported comment', array_values($comments)[0]['comment_added'][0]);
	}

	public function testJsonImportOverlayQuestions()
	{
		$data = $this->courseExportData([
			'videos' => [$this->videoData([
				'video_name' => 'Video with question',
				'overlay' => [[
					'overlay_type' => 'smallpart-question-singlechoice',
					'overlay_start' => 0,
					'max_score' => 10.0,
					'min_score' => 0.0,
					'answers' => [['id' => 'a'], ['id' => 'b']],
					'answer' => 'a',
				]],
			])],
		]);
		$file = $this->jsonFileFixture($data);

		$course_id = $this->asAccount(self::TEACHER, function() use ($file)
		{
			return (new Export())->jsonImport(null, $file, false, []);
		});
		$this->created_courses[] = $course_id;

		$read = $this->asAccount(self::TEACHER, function() use ($course_id)
		{
			$course = (new Bo())->read($course_id);
			$video = array_values($course['videos'])[0];
			return Overlay::read(['video_id' => $video['video_id']]);
		});
		$this->assertCount(1, $read['elements']);
		$this->assertSame('smallpart-question-singlechoice', $read['elements'][0]['overlay_type']);
	}

	// -------------------------------------------------------------------
	// Export::csv_escape() (protected static, pure formatting - no DB needed)
	// -------------------------------------------------------------------

	private function csvEscape(array $row): string
	{
		$ref = new \ReflectionMethod(Export::class, 'csv_escape');
		$ref->setAccessible(true);
		return $ref->invoke(null, $row);
	}

	public function testCsvEscapeFormatsTimeAsHms()
	{
		$line = $this->csvEscape(['comment_starttime' => 3725]); // 1h 2m 5s
		$this->assertStringContainsString('1:02:05', $line);
	}

	public function testCsvEscapeDoublesEnclosureQuotes()
	{
		$line = $this->csvEscape(['video_question' => 'a "quoted" word']);
		$this->assertStringContainsString('a ""quoted"" word', $line);
	}

	public function testCsvEscapeMarkedAsBooleanInt()
	{
		$this->assertStringContainsString(';1'.PHP_EOL, ';'.$this->csvEscape(['comment_marked' => ['some' => 'mark']]));
		$this->assertStringContainsString(';0'.PHP_EOL, ';'.$this->csvEscape(['comment_marked' => null]));
	}

	public function testCsvEscapeColorToCategory()
	{
		$line = $this->csvEscape(['comment_color' => 'ff0000']);
		$this->assertStringContainsString(lang('red'), $line);
	}
}
