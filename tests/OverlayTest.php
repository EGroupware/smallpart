<?php
/**
 * EGroupware - SmallParT (ViDoTeach) - Overlay/Questions (tests/exams) unit/integration tests
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
 * Tests for EGroupware\SmallParT\Overlay (question/answer storage+scoring) and the two pure static
 * methods on EGroupware\SmallParT\Questions it depends on.
 *
 * Not reachable via REST at all (doc/REST-API.md marks tests/exams as "not yet implemented") -
 * Bo-level tests only, same live-DB convention as BoTest.php (see SmallpartTestHelpers).
 *
 * Overlay's own docblock: "non-ajax methods do NOT check ACL" - Overlay::aclCheck()/read() call
 * Bo::getInstance() (the ambient-session singleton), so role-specific tests use asAccount() real
 * session-switching, same as BoTest.php. write()/writeAnswer() take fully-explicit data (incl.
 * account_id) and do no ACL/session-dependent work, so those are called directly.
 */
class OverlayTest extends Api\AppTest
{
	use SmallpartTestHelpers;

	private function questionFixture(array $course, array $video, array $overrides=[]): array
	{
		return array_merge([
			'course_id' => (int)$course['course_id'],
			'video_id' => (int)$video['video_id'],
			'overlay_type' => 'smallpart-question-singlechoice',
			'overlay_start' => 0,
			'max_score' => 10.0,
			'min_score' => 0.0,
			'answers' => [['id' => 'a'], ['id' => 'b']],
			'answer' => 'a',
		], $overrides);
	}

	private function createQuestion(array $course, array $video, array $overrides=[]): int
	{
		return Overlay::write($this->questionFixture($course, $video, $overrides));
	}

	private function answerFixture(int $overlay_id, array $course, array $video, int $account_id, array $overrides=[]): array
	{
		return array_merge([
			'overlay_id' => $overlay_id,
			'course_id' => (int)$course['course_id'],
			'video_id' => (int)$video['video_id'],
			'account_id' => $account_id,
			'overlay_type' => 'smallpart-question-singlechoice',
			'answer' => 'a',
			'max_score' => 10.0,
			'min_score' => 0.0,
			'answer_data' => ['answer' => 'a'],
		], $overrides);
	}

	// -------------------------------------------------------------------
	// Pure logic: Questions::defaultScore() / setMultipleChoiceIds()
	// -------------------------------------------------------------------

	public function testDefaultScoreAllAnswersAlreadyScored()
	{
		$this->assertNull(Questions::defaultScore([
			'max_score' => 10,
			'answers' => [['score' => 5], ['score' => 5]],
		]));
	}

	public function testDefaultScoreSplitsRemainingAmongUnscoredAnswers()
	{
		// one answer explicitly scored 4, remaining 6 points split over the other 2 answers
		$score = Questions::defaultScore([
			'max_score' => 10,
			'answers' => [['score' => 4], ['score' => 0], ['score' => 0]],
		]);
		$this->assertSame(3.0, $score);
	}

	public function testDefaultScoreNullWithoutAnswersOrMaxScore()
	{
		$this->assertNull(Questions::defaultScore(['max_score' => 10, 'answers' => []]));
		$this->assertNull(Questions::defaultScore(['max_score' => 0, 'answers' => [['score' => 0]]]));
	}

	public function testSetMultipleChoiceIdsAssignsStableIds()
	{
		$answers = [['answer' => 'Paris'], ['answer' => 'London']];
		$correct = null;
		Questions::setMultipleChoiceIds($answers, $correct);

		$this->assertNotEmpty($answers[0]['id']);
		$this->assertNotEmpty($answers[1]['id']);
		$this->assertNotSame($answers[0]['id'], $answers[1]['id']);
	}

	public function testSetMultipleChoiceIdsRemapsCorrectAnswer()
	{
		$answers = [['id' => 5, 'answer' => 'Paris'], ['id' => 7, 'answer' => 'London']];
		$correct = '5';
		Questions::setMultipleChoiceIds($answers, $correct);

		// numeric ids get reassigned a stable md5 id; $correct_answer must follow the same answer
		$this->assertSame($answers[0]['id'], $correct);
		$this->assertNotSame('5', $correct);
	}

	// -------------------------------------------------------------------
	// Scoring algorithms (via Reflection - protected static, no DB needed)
	// -------------------------------------------------------------------

	private function invokeScorer(string $method, array $args)
	{
		$ref = new \ReflectionMethod(Overlay::class, $method);
		$ref->setAccessible(true);
		return $ref->invokeArgs(null, $args);
	}

	public function testScoreMultipleChoiceScorePerAnswer()
	{
		$answer_data = [];
		$answers = [
			['id' => 'a', 'check' => true, 'correct' => true, 'score' => 4],
			['id' => 'b', 'check' => false, 'correct' => false, 'score' => -2],
			['id' => 'c', 'check' => true, 'correct' => false, 'score' => -2],
		];
		$score = $this->invokeScorer('scoreMultipleChoice',
			[$answers, Overlay::ASSESSMENT_SCORE_PER_ANSWER, &$answer_data, 1.0, 10, null]);

		// (a) correctly checked with a positive score adds its score; (c) incorrectly checked with
		// a negative score ALSO adds its (negative) score, as a distractor penalty; (b) correctly
		// left unchecked with a negative score contributes nothing
		$this->assertEquals(2.0, $score);
	}

	public function testScoreMultipleChoiceThrowsWhenTooManyChecked()
	{
		$answer_data = [];
		$answers = [
			['id' => 'a', 'check' => true, 'correct' => true, 'score' => 1],
			['id' => 'b', 'check' => true, 'correct' => false, 'score' => 1],
		];
		$this->expectException(\InvalidArgumentException::class);
		$this->invokeScorer('scoreMultipleChoice', [$answers, 'other', &$answer_data, 1.0, 10, 1]);
	}

	public function testScoreMarkChoiceCorrectMarksScore()
	{
		$answer_data = ['marks' => [['x' => 1, 'y' => 1, 'c' => 1, 'a' => 0]]];
		$answers = [['id' => 1, 'score' => 5]];
		$marks = [['x' => 1, 'y' => 1, 'c' => 1, 'a' => 0]];

		$score = $this->invokeScorer('scoreMarkChoice', [$marks, $answers, &$answer_data, 1.0]);

		$this->assertEquals(5.0, $score);
		$this->assertTrue($answer_data['answers'][0]['check']);
	}

	public function testScoreMarkChoiceWrongMarksScoreZero()
	{
		$answer_data = ['marks' => [['x' => 9, 'y' => 9, 'c' => 1, 'a' => 0]]];
		$answers = [['id' => 1, 'score' => 5]];
		$marks = [['x' => 1, 'y' => 1, 'c' => 1, 'a' => 0]];

		$score = $this->invokeScorer('scoreMarkChoice', [$marks, $answers, &$answer_data, 1.0]);

		$this->assertEquals(0.0, $score);
		$this->assertFalse($answer_data['answers'][0]['check']);
	}

	public function testScoreMillOutSufficientOverlapScores()
	{
		$teacher_marks = array_map(static fn($i) => ['x' => $i, 'y' => 0, 'c' => 2], range(1, 10));
		// student marks 9 of the 10 teacher pixels (90% >= 80% threshold both ways)
		$student_marks = array_slice($teacher_marks, 0, 9);
		$answer_data = ['marks' => $student_marks];
		$answers = [['id' => 2, 'score' => 8]];

		$score = $this->invokeScorer('scoreMillOut', [$teacher_marks, $answers, &$answer_data, 1.0]);

		$this->assertEquals(8.0, $score);
		$this->assertTrue($answer_data['answers'][0]['check']);
	}

	public function testScoreMillOutInsufficientOverlapScoresZero()
	{
		$teacher_marks = array_map(static fn($i) => ['x' => $i, 'y' => 0, 'c' => 2], range(1, 10));
		// student only marks 2 of the 10 teacher pixels (20% < 80% threshold)
		$student_marks = array_slice($teacher_marks, 0, 2);
		$answer_data = ['marks' => $student_marks];
		$answers = [['id' => 2, 'score' => 8]];

		$score = $this->invokeScorer('scoreMillOut', [$teacher_marks, $answers, &$answer_data, 1.0]);

		$this->assertEquals(0.0, $score);
		$this->assertFalse($answer_data['answers'][0]['check']);
	}

	// -------------------------------------------------------------------
	// write() / writeAnswer() integration
	// -------------------------------------------------------------------

	public function testWriteAnswerSinglechoiceCorrectScoresMax()
	{
		$course = $this->createCourse();
		$video = $this->createVideo($course);
		$overlay_id = $this->createQuestion($course, $video);

		$answer_id = Overlay::writeAnswer($this->answerFixture($overlay_id, $course, $video,
			$this->accountId(self::STUDENT1), ['answer_data' => ['answer' => 'a']]));

		$read = $this->asAccount(self::TEACHER, function() use ($overlay_id, $video)
		{
			return Overlay::read(['overlay_id' => $overlay_id, 'video_id' => $video['video_id'], 'account_id' => $this->accountId(self::STUDENT1)]);
		});
		$this->assertNotEmpty($answer_id);
		$this->assertSame(10.0, (float)$read['elements'][0]['answer_score']);
	}

	public function testWriteAnswerSinglechoiceWrongScoresMin()
	{
		$course = $this->createCourse();
		$video = $this->createVideo($course);
		$overlay_id = $this->createQuestion($course, $video);

		Overlay::writeAnswer($this->answerFixture($overlay_id, $course, $video,
			$this->accountId(self::STUDENT1), ['answer_data' => ['answer' => 'b']]));

		$read = $this->asAccount(self::TEACHER, function() use ($overlay_id, $video)
		{
			return Overlay::read(['overlay_id' => $overlay_id, 'video_id' => $video['video_id'], 'account_id' => $this->accountId(self::STUDENT1)]);
		});
		$this->assertSame(0.0, (float)$read['elements'][0]['answer_score']);
	}

	public function testWriteAnswerMultipleChoiceEndToEnd()
	{
		$course = $this->createCourse();
		$video = $this->createVideo($course);
		$overlay_id = $this->createQuestion($course, $video, [
			'overlay_type' => 'smallpart-question-multiplechoice',
			'answers' => [
				['id' => 'a', 'correct' => true, 'score' => 5],
				['id' => 'b', 'correct' => false, 'score' => -5],
			],
		]);

		Overlay::writeAnswer($this->answerFixture($overlay_id, $course, $video,
			$this->accountId(self::STUDENT1), [
				'overlay_type' => 'smallpart-question-multiplechoice',
				'answers' => [
					['id' => 'a', 'check' => true, 'correct' => true, 'score' => 5],
					['id' => 'b', 'check' => false, 'correct' => false, 'score' => -5],
				],
				Overlay::ASSESSMENT_METHOD => Overlay::ASSESSMENT_SCORE_PER_ANSWER,
				'answer_data' => [],
			]));

		$read = $this->asAccount(self::TEACHER, function() use ($overlay_id, $video)
		{
			return Overlay::read(['overlay_id' => $overlay_id, 'video_id' => $video['video_id'], 'account_id' => $this->accountId(self::STUDENT1)]);
		});
		$this->assertSame(5.0, (float)$read['elements'][0]['answer_score']);
	}

	public function testWriteAnswerExemptZeroesScoreAndStashesOriginal()
	{
		$course = $this->createCourse();
		$video = $this->createVideo($course);
		$overlay_id = $this->createQuestion($course, $video);

		Overlay::writeAnswer($this->answerFixture($overlay_id, $course, $video,
			$this->accountId(self::STUDENT1), [
				'answer_data' => ['answer' => 'a'],
				'exempt' => true,
			]));

		$read = $this->asAccount(self::TEACHER, function() use ($overlay_id, $video)
		{
			return Overlay::read(['overlay_id' => $overlay_id, 'video_id' => $video['video_id'], 'account_id' => $this->accountId(self::STUDENT1)]);
		});
		$answer_data = is_array($read['elements'][0]['answer_data'] ?? null)
			? $read['elements'][0]['answer_data']
			: json_decode($read['elements'][0]['answer_data'], true);
		$this->assertSame(0.0, (float)$read['elements'][0]['answer_score']);
		$this->assertSame(10.0, (float)$answer_data['exempt'], 'original (pre-exempt) score must be stashed');
	}

	// -------------------------------------------------------------------
	// Overlay::read()
	// -------------------------------------------------------------------

	public function testReadReturnsSumAndMaxScore()
	{
		$course = $this->createCourse();
		$video = $this->createVideo($course);
		$overlay_id = $this->createQuestion($course, $video);
		Overlay::writeAnswer($this->answerFixture($overlay_id, $course, $video,
			$this->accountId(self::STUDENT1), ['answer_data' => ['answer' => 'a']]));

		$read = Overlay::read(['video_id' => $video['video_id'], 'account_id' => $this->accountId(self::STUDENT1)]);

		$this->assertSame(10.0, $read['sum_score']);
		$this->assertSame(10.0, $read['max_score']);
	}

	public function testReadNonAdminOnlySeesOwnAnswer()
	{
		$course = $this->createCourse();
		$video = $this->createVideo($course);
		$overlay_id = $this->createQuestion($course, $video);
		Overlay::writeAnswer($this->answerFixture($overlay_id, $course, $video,
			$this->accountId(self::STUDENT1), ['answer_data' => ['answer' => 'a']]));
		Overlay::writeAnswer($this->answerFixture($overlay_id, $course, $video,
			$this->accountId(self::STUDENT2), ['answer_data' => ['answer' => 'b']]));

		foreach ([self::STUDENT1, self::STUDENT2] as $lid)
		{
			$this->asAccount($lid, function() use ($course)
			{
				(new Bo())->subscribe($course['course_id']);
			});
		}

		$read = $this->asAccount(self::STUDENT1, function() use ($video)
		{
			return Overlay::read(['video_id' => $video['video_id']]);
		});
		// answer_score itself is deliberately hidden from students on a non-readonly (still "live")
		// video (see db2data()'s $remove_correct handling) - answer_data still shows their own
		// submission though, which is enough to prove the per-account join picked the right row
		$this->assertSame('a', $read['elements'][0]['answer_data']['answer'],
			'student1 must see their own answer, not student2\'s');
	}

	// -------------------------------------------------------------------
	// ACL
	// -------------------------------------------------------------------

	public function testAclCheckRequiresParticipantForRead()
	{
		$course = $this->createCourse();

		$this->asAccount(self::STUDENT1, function() use ($course)
		{
			$this->expectException(Api\Exception\NoPermission::class);
			Overlay::aclCheck($course['course_id']);
		});
	}

	public function testAclCheckRequiresTeacherForUpdate()
	{
		$course = $this->createCourse();
		$this->asAccount(self::STUDENT1, function() use ($course)
		{
			(new Bo())->subscribe($course['course_id']);
		});

		$this->asAccount(self::STUDENT1, function() use ($course)
		{
			$this->expectException(Api\Exception\NoPermission::class);
			Overlay::aclCheck($course['course_id'], true);
		});
	}

	public function testDeleteQuestionRequiresTeacher()
	{
		$course = $this->createCourse();
		$video = $this->createVideo($course);
		$overlay_id = $this->createQuestion($course, $video);
		$this->asAccount(self::STUDENT1, function() use ($course)
		{
			(new Bo())->subscribe($course['course_id']);
		});

		$this->asAccount(self::STUDENT1, function() use ($course, $video, $overlay_id)
		{
			$this->expectException(Api\Exception\NoPermission::class);
			Overlay::deleteQuestion(['course_id' => $course['course_id'], 'video_id' => $video['video_id'], 'overlay_id' => $overlay_id]);
		});
	}

	public function testDeleteQuestionHardDeletesWithoutAnswers()
	{
		$course = $this->createCourse();
		$video = $this->createVideo($course);
		$overlay_id = $this->createQuestion($course, $video);

		[$deleted, $hidden] = $this->asAccount(self::TEACHER, function() use ($course, $video, $overlay_id)
		{
			return Overlay::deleteQuestion(['course_id' => $course['course_id'], 'video_id' => $video['video_id'], 'overlay_id' => $overlay_id]);
		});

		$this->assertSame(1, $deleted);
		$this->assertSame(0, $hidden);
		$read = Overlay::read(['video_id' => $video['video_id'], 'overlay_id' => $overlay_id]);
		$this->assertEmpty($read['elements']);
	}

	public function testDeleteQuestionHidesWithExistingAnswers()
	{
		$course = $this->createCourse();
		$video = $this->createVideo($course);
		$overlay_id = $this->createQuestion($course, $video);
		Overlay::writeAnswer($this->answerFixture($overlay_id, $course, $video,
			$this->accountId(self::STUDENT1), ['answer_data' => ['answer' => 'a']]));

		[$deleted, $hidden] = $this->asAccount(self::TEACHER, function() use ($course, $video, $overlay_id)
		{
			return Overlay::deleteQuestion(['course_id' => $course['course_id'], 'video_id' => $video['video_id']]);
		});

		$this->assertSame(0, $deleted);
		$this->assertSame(1, $hidden);
		$read = Overlay::read(['video_id' => $video['video_id']]);
		$this->assertEmpty($read['elements'], 'hidden (soft-deleted) question must not show up in a normal read()');
	}

	// -------------------------------------------------------------------
	// countAnswers()
	// -------------------------------------------------------------------

	public function testCountAnswers()
	{
		$course = $this->createCourse();
		$video = $this->createVideo($course);
		$overlay_id = $this->createQuestion($course, $video);
		Overlay::writeAnswer($this->answerFixture($overlay_id, $course, $video, $this->accountId(self::STUDENT1)));
		Overlay::writeAnswer($this->answerFixture($overlay_id, $course, $video, $this->accountId(self::STUDENT2)));

		$this->assertSame(2, Overlay::countAnswers($video['video_id']));
	}

	// -------------------------------------------------------------------
	// Test timer (testStarted()/testStart()/testStop())
	// -------------------------------------------------------------------

	public function testTestStartedNullBeforeStart()
	{
		$course = $this->createCourse();
		$video = $this->createVideo($course, ['video_test_duration' => 10]);
		$this->asAccount(self::STUDENT1, function() use ($course)
		{
			(new Bo())->subscribe($course['course_id']);
		});

		$started = $this->asAccount(self::STUDENT1, function() use ($video)
		{
			return Overlay::testStarted($video['video_id']);
		});
		$this->assertNull($started);
	}

	public function testTestStartThenStartedReturnsTruthyStartTime()
	{
		// regression test for the 'answer_started' (nonexistent column) vs 'answer_created' bug -
		// before the fix, testStarted() always returned null here, even though the test IS running
		$course = $this->createCourse();
		$video = $this->createVideo($course, ['video_test_duration' => 10]);
		$this->asAccount(self::STUDENT1, function() use ($course)
		{
			(new Bo())->subscribe($course['course_id']);
		});

		$this->asAccount(self::STUDENT1, function() use ($video)
		{
			$video_time = null;
			(new Bo())->testStart($video['video_id'], $video_time);
		});

		$started = $this->asAccount(self::STUDENT1, function() use ($video)
		{
			return Overlay::testStarted($video['video_id']);
		});
		$this->assertNotEmpty($started, 'a running test must report a truthy start time');
	}

	public function testTestStartTwiceThrowsWithoutIgnoreStarted()
	{
		$course = $this->createCourse();
		$video = $this->createVideo($course, ['video_test_duration' => 10]);
		$this->asAccount(self::STUDENT1, function() use ($course)
		{
			(new Bo())->subscribe($course['course_id']);
		});

		$this->asAccount(self::STUDENT1, function() use ($video, $course)
		{
			Overlay::testStart($video['video_id'], $course['course_id']);
			$this->expectException(Api\Exception\WrongParameter::class);
			Overlay::testStart($video['video_id'], $course['course_id']);
		});
	}

	public function testTestStopStoppedCannotRestart()
	{
		$course = $this->createCourse();
		$video = $this->createVideo($course, ['video_test_duration' => 10]);
		$this->asAccount(self::STUDENT1, function() use ($course)
		{
			(new Bo())->subscribe($course['course_id']);
		});

		$this->asAccount(self::STUDENT1, function() use ($video, $course)
		{
			Overlay::testStart($video['video_id'], $course['course_id']);
			Overlay::testStop($video['video_id'], $course['course_id'], true);

			$started = Overlay::testStarted($video['video_id']);
			$this->assertFalse($started, 'a stopped test must report false (cannot be restarted)');
		});
	}

	public function testTestStopNotRunningThrows()
	{
		$course = $this->createCourse();
		$video = $this->createVideo($course, ['video_test_duration' => 10]);
		$this->asAccount(self::STUDENT1, function() use ($course)
		{
			(new Bo())->subscribe($course['course_id']);
		});

		$this->asAccount(self::STUDENT1, function() use ($video, $course)
		{
			$this->expectException(Api\Exception\WrongParameter::class);
			Overlay::testStop($video['video_id'], $course['course_id']);
		});
	}
}
