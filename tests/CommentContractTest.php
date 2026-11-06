<?php
/**
 * EGroupware - SmallParT (ViDoTeach) - what Student\Ui::index() sends to the client, for every option combination
 *
 * @link https://www.egroupware.org
 * @package smallpart
 * @subpackage tests
 * @license https://spdx.org/licenses/AGPL-3.0-or-later.html GNU Affero General Public License v3.0 or later
 */

namespace EGroupware\SmallParT;

use EGroupware\Api;
use EGroupware\SmallParT\Student\Ui;

require_once realpath(__DIR__.'/../../api/tests/AppTest.php');
require_once __DIR__.'/SmallpartTestHelpers.php';

/**
 * The server half of the comment option matrix, see CommentOptionsMatrix.md (next to this file) for the dimensions,
 * the rules and how the combinations were chosen.
 *
 * Each test drives the real Student\Ui::index() as a real course member - the same call the browser's page load makes -
 * and reads back what it sends: `content` (comments, video flags, is_staff, timer, ...) and the attribute changes it
 * makes to template widgets (the comment actions, the add-comment button).  Nothing is mocked, so a value arrives in
 * the PHP type the client will get it in: database columns are strings, flags are booleans.
 *
 * PASS CRITERIA, for every case of every test
 * - the facts the case checks (which comments and replies arrive, the comment actions, the video flags, locked, timer,
 *   ...) equal the rules table written in this class, which is written from the option definitions in Bo.php and not
 *   derived from the code under test
 * - every payload has the types the client relies on (PAYLOAD_TYPES), e.g. `is_staff` is null or a role name,
 *   `accessible` is true / false / "readonly" / null, the video's option columns are integer-like
 * - the payload of every case equals its golden file in fixtures/ - this is what the JS tests feed the client's
 *   decision functions, so a change of what the server sends has to be made visible: run with
 *   `UPDATE_GOLDEN=1` to rewrite the files, then review the diff
 * A case listed in knownGaps() asserts today's behaviour instead of the rule and FAILS when the gap
 * closes, so the entry gets removed.
 *
 * Fixture courses are created per test and deleted again in tearDown() (SmallpartTestHelpers).
 */
class CommentContractTest extends Api\AppTest
{
	use SmallpartTestHelpers;

	/**
	 * Visibility modes, Bo::COMMENTS_* => what a student (viewer) gets of the authors, see visibleAuthors()
	 */
	protected const VISIBILITY_RULES = [
		Bo::COMMENTS_SHOW_ALL => 'all',
		Bo::COMMENTS_HIDE_OTHER_STUDENTS => 'self+staff',
		Bo::COMMENTS_HIDE_TEACHERS => 'students',
		Bo::COMMENTS_SHOW_OWN => 'self',
		Bo::COMMENTS_FORBIDDEN_BY_STUDENTS => 'staff',
		Bo::COMMENTS_DISABLED => 'none',
		Bo::COMMENTS_GROUP => 'group+staff',
		Bo::COMMENTS_GROUP_HIDE_TEACHERS => 'group',
	];

	/**
	 * Known differences between the rule and what the code does today: case id => fact => [current value, why]
	 *
	 * Each is a finding, not a decision: the rule is what the option definitions and comments say should happen.
	 * A test fails when a gap closes, so the entry gets removed and the case is held to the rule from then on.
	 *
	 * @return array<string,array<string,array>>
	 */
	protected static function knownGaps() : array
	{
		static $gaps = null;
		if ($gaps !== null)
		{
			return $gaps;
		}
		$both = ['admin[s1]', 's1[admin]'];
		$locked = 'Ui::index() reads the comments before it decides the page is locked and never drops them again, so '.
			'a locked page still carries every comment (a test not yet started even carries the staff comments in full)';
		$gaps = ['state/published-starts-later/s1' => ['visible' => [$both, $locked]]];
		foreach (['none', 'allow-pause', 'forbid-seek', 'free-comment-only', 'readonly-after-test', 'teacher-free-comment', 'hide-teacher-text', 'all'] as $name)
		{
			$gaps["test/$name/not-started/s1"] = ['visible' => [$both, $locked]];
		}
		// Bo::videoAccessible(): `$video['video_test_options'] && self::TEST_OPTION_VIDEO_READONLY_AFTER_TEST` is a logical and, so
		// any non-zero option makes a finished test read-only; the rule is the bit 8 only
		foreach (['allow-pause', 'forbid-seek', 'free-comment-only', 'teacher-free-comment', 'hide-teacher-text'] as $name)
		{
			$gaps["test/$name/stopped/s1"] = [
				'has_video' => [true, 'Bo::videoAccessible() tests `video_test_options && TEST_OPTION_VIDEO_READONLY_AFTER_TEST` (logical and): any non-zero option makes a finished test read-only, not only bit 8'],
				'visible' => [$both, 'same cause: the finished test is delivered read-only'],
			];
		}
		return $gaps;
	}

	/** Facts of the video that are recorded and type-checked: field => type rule (see assertPayloadTypes()) */
	protected const VIDEO_FACTS = [
		'video_options' => 'int-like', 'video_published' => 'int-like', 'video_livefeedback_simulated' => 'int-like',
		'video_test_duration' => 'int-like-or-null', 'video_test_options' => 'int-like', 'video_test_display' => 'int-like',
		'accessible' => 'accessible', 'seekable' => 'bool', 'livefeedback_session' => 'string',
	];

	/** Roles by label: who is which account.  The tutor account is a teacher in the course without groups. */
	protected const AUTHORS = ['admin' => self::TEACHER, 'staff2' => self::TUTOR, 's1' => self::STUDENT1, 's2' => self::STUDENT2];
	/** Synthetic, stable ids, so the golden files do not change from one database to the next */
	protected const LABEL_IDS = ['admin' => '1', 'tutor' => '2', 'teacher' => '2', 's1' => '3', 's2' => '4', 'outsider' => '5'];

	/** @var array<string,array> label => facts of every case of the running test, for the golden file */
	private array $golden = [];
	private array $failures = [];

	// ------------------------------------------------------------------ fixtures

	/**
	 * A course with its four authors, returns ['course' =>, 'labels' => account_id => label, 'groups' => label => group]
	 *
	 * @param string $kind "groups": a tutor and two students in different groups, "flat": a (non-owner) teacher and two
	 *  students without groups
	 */
	protected function makeCourse(string $kind, bool $with_cats = false) : array
	{
		$course = $this->createCourse(($kind === 'groups' ? ['course_groups' => 2, 'groups_mode' => 'number-auto'] : []) +
			// the default categories (white, green, red, yellow with a like and dislike each) and a special one
			($with_cats ? ['cats' => array_merge(array_map(static fn($cat) => $cat + ['data' => '{}'], Bo::initCategories()), [[
				'cat_id' => 'new_99', 'cat_name' => 'special', 'cat_description' => '', 'course_id' => 0, 'parent_id' => null,
				'cat_color' => 'blue', 'type' => 'sc', 'data' => '{}',
			]])] : []));
		$this->asAccount(self::TEACHER, function() use ($course, $kind)
		{
			(new Bo())->subscribe($course['course_id'], true, $this->accountId(self::TUTOR), null,
				$kind === 'groups' ? Bo::ROLE_TUTOR : Bo::ROLE_TEACHER);
		});
		foreach ([self::STUDENT1, self::STUDENT2] as $lid)
		{
			$this->asAccount($lid, function() use ($course)
			{
				(new Bo())->subscribe($course['course_id']);
			});
		}
		$participants = $this->asAccount(self::TEACHER, function() use ($course)
		{
			return (new Bo())->read($course['course_id'])['participants'];
		});
		$staff = $kind === 'groups' ? 'tutor' : 'teacher';
		$labels = [
			$this->accountId(self::TEACHER) => 'admin', $this->accountId(self::TUTOR) => $staff,
			$this->accountId(self::STUDENT1) => 's1', $this->accountId(self::STUDENT2) => 's2',
		];
		$groups = [];
		foreach ($labels as $account_id => $label)
		{
			$groups[$label] = ($this->findParticipant($participants, $account_id)['participant_group'] ?? null) ?: null;
		}
		if ($kind === 'groups')
		{
			$this->assertNotEmpty($groups['s1'], 'fixture: student 1 needs a group');
			$this->assertNotEquals($groups['s1'], $groups['s2'], 'fixture: the students need different groups');
		}
		else
		{
			$this->assertNull($groups['s1'] ?? null, 'fixture: no groups in a course without groups');
		}
		return ['course' => $course, 'labels' => $labels, 'groups' => $groups, 'staff' => $staff];
	}

	/**
	 * A video of a course with the standard comments, then switched to the given options
	 *
	 * Comments: admin, staff2, s1, s2 (in this order, all at second 0) plus replies by s1 and staff2 on the admin's
	 * comment and by s2 and admin on the one of s1.  They are written while the video is still the plain
	 * default (published, no test, all comments), as the server refuses some of them otherwise.
	 *
	 * @param array $fx course fixture
	 * @param array $options video columns to set after the comments exist
	 * @param string[] $authors which of AUTHORS comment
	 */
	protected function makeVideo(array $fx, array $options = [], array $authors = ['admin', 'staff2', 's1', 's2']) : array
	{
		$course = $fx['course'];
		$video = $this->createVideo($course, ['video_published' => Bo::VIDEO_PUBLISHED, 'video_options' => Bo::COMMENTS_SHOW_ALL]);
		$ids = [];
		foreach ($authors as $label)
		{
			$ids[$label] = $this->saveAs(self::AUTHORS[$label], [
				'course_id' => $course['course_id'], 'video_id' => $video['video_id'], 'action' => 'add',
				'text' => 'c-'.$label, 'comment_starttime' => 0, 'comment_stoptime' => 0,
			]);
		}
		foreach ([['admin', 's1'], ['admin', 'staff2'], ['s1', 's2'], ['s1', 'admin']] as [$on, $by])
		{
			if (isset($ids[$on], $ids[$by]))
			{
				$this->saveAs(self::AUTHORS[$by], [
					'course_id' => $course['course_id'], 'video_id' => $video['video_id'], 'comment_id' => $ids[$on],
					'action' => 'retweet', 'text' => 'r-'.$by.'-on-'.$on,
				]);
			}
		}
		if ($options)
		{
			$this->updateVideo($video, $options);
		}
		return $video;
	}

	protected function saveAs(string $lid, array $comment) : int
	{
		return (int)$this->asAccount($lid, function() use ($comment)
		{
			return (new Bo())->saveComment($comment, true, false);
		});
	}

	protected function updateVideo(array $video, array $options) : void
	{
		$this->asAccount(self::TEACHER, function() use ($video, $options)
		{
			(new Bo())->saveVideo(['video_id' => $video['video_id'], 'course_id' => $video['course_id']] + $options);
		});
	}

	// ------------------------------------------------------------------ capture

	/**
	 * Run Ui::index() for the way the browser's page load does, as the given user, and return what it sends
	 *
	 * @param ?string $lid account to switch to, null: the ambient test user, who is no member of the course
	 * @return array content, readonlys, sel_options and the widget attributes the server changed: mods[id][attribute]
	 */
	protected function index(?string $lid, array $course, ?array $video) : array
	{
		$run = function() use ($course, $video)
		{
			if ($video)
			{
				// without a last video the page shows the course list, whatever it was asked for
				(new Bo())->setLastVideo(['course_id' => $course['course_id'], 'video_id' => $video['video_id']]);
			}
			$_POST['value'] = '{}';
			$property = new \ReflectionProperty(Api\Etemplate::class, 'response');
			$property->setAccessible(true);
			$response = Api\Json\Response::get();
			$response->initResponseArray();
			// with a response set, Etemplate::exec() answers like an ajax call: it hands the data to the response
			$property->setValue(null, $response);
			ob_start();
			try
			{
				(new Ui())->index(['courses' => $course['course_id']] + ($video ? ['videos' => $video['video_id']] : []));
			}
			finally
			{
				ob_end_clean();
				$property->setValue(null, null);
			}
			$array = new \ReflectionProperty($response, 'responseArray');
			$array->setAccessible(true);
			$out = ['content' => null, 'readonlys' => [], 'sel_options' => [], 'mods' => [], 'template' => null];
			foreach ((array)$array->getValue($response) as $chunk)
			{
				$chunk = (array)$chunk;
				$data = (array)($chunk['data'] ?? []);
				if (($chunk['type'] ?? '') === 'assign' && isset($data['id'], $data['key']))
				{
					$out['mods'][$data['id']][$data['key']] = $data['value'];
				}
				elseif (($chunk['type'] ?? '') === 'et2_load')
				{
					$out['template'] = $data['name'];
					$out['content'] = $data['data']['content'];
					$out['readonlys'] = $data['data']['readonlys'];
					$out['sel_options'] = $data['data']['sel_options'];
				}
			}
			return $out;
		};
		return $lid ? $this->asAccount($lid, $run) : $run();
	}

	/**
	 * The facts of a capture the matrix cares about, with the types as they were sent
	 *
	 * @param array $labels account_id => label
	 */
	protected function facts(array $capture, array $labels) : array
	{
		$c = $capture['content'] ?? [];
		$mods = $capture['mods'];
		$video = $c['video'] ?? null;
		$facts = [
			'template' => $capture['template'],
			'is_staff' => $c['is_staff'] ?? null,
			'course_options' => $c['course_options'] ?? null,
			// a video that is not delivered leaves only a layout key (area_class) in content['video']
			// in VIDEO_FACTS order: the order of the video's columns depends on how the table was created and updated
			'video' => !empty($video['video_id']) ?
				array_merge(array_intersect_key(self::VIDEO_FACTS, $video), array_intersect_key($video, self::VIDEO_FACTS)) : null,
			'comments' => null,
			'actions' => isset($mods['comments']['actions']) ? array_keys($mods['comments']['actions']) : null,
			// the css class a comment needs for the action to be enabled in the list's menu (null: always enabled)
			'enable_class' => isset($mods['comments']['actions']) ? array_map(static fn($action) => $action['enableClass'] ?? null,
				$mods['comments']['actions']) : null,
			'cats_options' => isset($capture['sel_options']['catsOptions']) ? count($capture['sel_options']['catsOptions']) : null,
			'cats_free_option' => isset($capture['sel_options']['catsOptions']) ?
				in_array('free', array_column($capture['sel_options']['catsOptions'], 'value'), true) : null,
			'cats_disabled' => isset($capture['sel_options']['catsOptions']) ?
				count(array_filter(array_column($capture['sel_options']['catsOptions'], 'disabled'))) : null,
			'special_actions' => isset($mods['special_category_toolbar']['actions']) ? count($mods['special_category_toolbar']['actions']) : null,
			'empty_cat_label_set' => isset($mods['comment[comment_cat]']['emptyLabel']),
			'add_comment_hidden' => $mods['play_control_bar[add_comment]']['hidden'] ?? null,
			'add_note_hidden' => $mods['play_control_bar[add_note]']['hidden'] ?? null,
			'locked' => $c['locked'] ?? null,
			'countdown' => isset($c['countdown']),
			'timer' => isset($c['timer']) ? (is_int($c['timer']) && $c['timer'] > 0 ? 'positive-int' : $c['timer']) : null,
			'isCommentAllowed' => $c['isCommentAllowed'] ?? null,
			'free_comment_only' => $c['comment']['free_comment_only'] ?? null,
			'questions' => isset($c['questions']),
			'clm' => isset($c['clm']),
			'lf_full_cats' => $c['lf_full_cats'] ?? null,
			'lf_cats' => isset($c['cats']) ? count($c['cats']) : null,
			'pause_readonly' => $capture['readonlys']['pause'] ?? null,
		];
		if (isset($c['comments']))
		{
			$facts['comments'] = [];
			foreach ($c['comments'] as $i => $comment)
			{
				if (!$comment)
				{
					$this->assertSame(0, $i, 'only the first entry is the empty grid header');
					continue;
				}
				$replies = [];
				foreach (array_slice($comment['comment_added'], 1) as $n => $value)
				{
					if ($n % 2 === 0) $replies[] = $labels[$value] ?? '?'.$value;	// [text, user, reply, user, reply, ...]
				}
				$facts['comments'][] = [
					'author' => $labels[$comment['account_id']] ?? '?'.$comment['account_id'],
					'account_id' => is_string($comment['account_id']) ? self::LABEL_IDS[$labels[$comment['account_id']]] : (int)self::LABEL_IDS[$labels[$comment['account_id']]],
					'comment_is_staff' => $comment['comment_is_staff'],
					'class' => implode(' ', array_values(array_filter(explode(' ', $comment['class']),
						static fn($token) => !str_starts_with($token, 'cat-')))),
					'comment_cat' => $comment['comment_cat'] ?? null,
					'comment_cat_type' => $comment['comment_cat_type'] ?? null,
					'text' => $comment['comment_added'][0] ?? null,
					'replies' => $replies,
				];
			}
		}
		return $facts;
	}

	/**
	 * Payload types the client relies on, see the class docblock
	 */
	protected function assertPayloadTypes(string $case, array $facts) : void
	{
		$int_like = static fn($v) => is_int($v) || (is_string($v) && preg_match('/^-?\d+$/', $v));
		$this->check($case, 'is_staff is null or a role name', in_array($facts['is_staff'], [null, 'tutor', 'teacher', 'admin'], true));
		foreach ($facts['video'] ?? [] as $field => $value)
		{
			$ok = match(self::VIDEO_FACTS[$field])
			{
				'int-like' => $int_like($value),
				'int-like-or-null' => $value === null || $int_like($value),
				'accessible' => in_array($value, [true, false, 'readonly', null], true),
				'bool' => is_bool($value),
				'string' => is_string($value),
			};
			$this->check($case, "video.$field is ".self::VIDEO_FACTS[$field].', got '.json_encode($value), $ok);
		}
		foreach ($facts['comments'] ?? [] as $comment)
		{
			$this->check($case, 'comment_is_staff is a bool', is_bool($comment['comment_is_staff']));
		}
		$this->check($case, 'locked is null or true', in_array($facts['locked'], [null, true], true));
		$this->check($case, 'timer is null or the seconds left', $facts['timer'] === null || $facts['timer'] === 'positive-int');
	}

	// ------------------------------------------------------------------ comparing

	protected function check(string $case, string $what, bool $ok) : void
	{
		if (!$ok)
		{
			$this->failures[] = "$case: $what";
		}
	}

	/**
	 * Compare the checked facts of a case with the rule, honouring knownGaps(), and remember the case for the golden file
	 *
	 * @param array $expected fact => rule value, only the facts the case checks
	 */
	protected function verify(string $case, array $options, string $viewer, array $facts, array $expected) : void
	{
		$this->assertPayloadTypes($case, $facts);
		foreach ($expected as $fact => $rule)
		{
			$actual = self::fact($facts, $fact);
			if (isset(static::knownGaps()[$case][$fact]))
			{
				[$current, $why] = static::knownGaps()[$case][$fact];
				$this->check($case, "known gap '$fact' is closed (rule and code agree: ".json_encode($rule)."), remove it from KNOWN_GAPS", $actual !== $rule);
				$this->check($case, "known gap '$fact' changed: $why; expected the current ".json_encode($current).', got '.json_encode($actual), $actual === $current);
				continue;
			}
			$this->check($case, "$fact: rule ".json_encode($rule).', got '.json_encode($actual), $actual === $rule);
		}
		$kind = $facts['is_staff'] ?? ($viewer === 'outsider' ? 'outsider' : 'student');
		$this->golden[$case] = ['case' => $case, 'viewer' => $viewer, 'viewer_kind' => $kind, 'user_id' => self::LABEL_IDS[$viewer],
			'options' => $options, 'payload' => $facts];
	}

	/** A fact by name: "a.b" reaches into arrays, "visible" and "owners" are derived from the comments */
	protected static function fact(array $facts, string $name)
	{
		if ($name === 'visible')
		{
			return $facts['comments'] === null ? null : array_map(
				static fn($c) => $c['author'].($c['replies'] ? '['.implode(',', $c['replies']).']' : ''), $facts['comments']);
		}
		if ($name === 'has_video')
		{
			return $facts['video'] !== null;
		}
		if ($name === 'staff_comment_text' || $name === 'staff_comment_cat' || $name === 'own_comment_cat')
		{
			$which = $name === 'own_comment_cat' ? 's1' : 'admin';
			foreach ($facts['comments'] ?? [] as $comment)
			{
				if ($comment['author'] === $which)
				{
					return $name === 'staff_comment_text' ? $comment['text'] : $comment['comment_cat'];
				}
			}
			return null;
		}
		if ($name === 'cat_types')
		{
			return $facts['comments'] === null ? null : array_values(array_unique(array_column($facts['comments'], 'comment_cat_type')));
		}
		if ($name === 'owners')
		{
			return $facts['comments'] === null ? null : array_values(array_map(static fn($c) => $c['author'],
				array_filter($facts['comments'], static fn($c) => str_contains($c['class'], 'commentOwner'))));
		}
		$value = $facts;
		foreach (explode('.', $name) as $key)
		{
			$value = is_array($value) ? ($value[$key] ?? null) : null;
		}
		return $value;
	}

	/**
	 * End of a test: compare/regenerate the golden file, fail with every mismatch at once
	 */
	protected function finish(string $group) : void
	{
		ksort($this->golden);
		$json = json_encode(array_values($this->golden), JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE)."\n";
		$file = __DIR__.'/fixtures/comment-contract-'.$group.'.json';
		if (getenv('UPDATE_GOLDEN'))
		{
			file_put_contents($file, $json);
		}
		else
		{
			$this->check($group, 'golden file '.basename($file).' exists, run with UPDATE_GOLDEN=1 to create it', file_exists($file));
			if (file_exists($file) && file_get_contents($file) !== $json)
			{
				$old = array_column(json_decode(file_get_contents($file), true), null, 'case');
				foreach ($this->golden as $case => $record)
				{
					if (($old[$case] ?? null) !== json_decode(json_encode($record), true))
					{
						$this->failures[] = "$case: payload differs from the golden file (UPDATE_GOLDEN=1 rewrites it): ".
							self::firstDifferences($old[$case] ?? null, json_decode(json_encode($record), true));
					}
				}
				foreach (array_diff_key($old, $this->golden) as $case => $unused)
				{
					$this->failures[] = "$case: in the golden file but not produced any more";
				}
			}
		}
		$this->assertSame([], $this->failures, count($this->failures).' of '.count($this->golden)." cases of '$group' differ:\n".implode("\n", $this->failures));
		$this->assertNotEmpty($this->golden);
	}

	/**
	 * Where two records differ, as "path: golden value => actual value" for the first few differences
	 *
	 * Without it a failure only names the case, which is no help when the golden file was written on another machine.
	 *
	 * @param mixed $golden value in the golden file (null: case missing)
	 * @param mixed $actual value the server produced
	 * @param string $path
	 * @param int $limit
	 */
	protected static function firstDifferences($golden, $actual, string $path = '', int $limit = 3) : string
	{
		$found = [];
		$walk = static function($golden, $actual, string $path) use (&$walk, &$found, $limit)
		{
			if (count($found) >= $limit) return;
			if (is_array($golden) && is_array($actual))
			{
				if (array_keys($golden) !== array_keys($actual))
				{
					$found[] = ltrim($path, '/').': keys '.json_encode(array_keys($golden)).' => '.json_encode(array_keys($actual));
				}
				foreach (array_unique(array_merge(array_keys($golden), array_keys($actual))) as $key)
				{
					$walk($golden[$key] ?? null, $actual[$key] ?? null, $path.'/'.$key);
				}
			}
			elseif ($golden !== $actual)
			{
				$found[] = ltrim($path, '/').': '.json_encode($golden).' => '.json_encode($actual);
			}
		};
		$walk($golden, $actual, $path);
		return implode('; ', $found);
	}

	// ------------------------------------------------------------------ rules

	/**
	 * Authors a viewer gets to see, from VISIBILITY_RULES, in the order the comments were written
	 *
	 * @param string $viewer label of the viewer
	 * @param string[] $present authors of the video's comments
	 * @param array $fx course fixture
	 */
	protected function visibleAuthors(int $mode, string $viewer, array $present, array $fx) : ?array
	{
		$is_staff = in_array($viewer, ['admin', 'tutor', 'teacher'], true);
		$staff = ['admin', $fx['staff']];
		$labels = array_map(static fn($a) => $a === 'staff2' ? $fx['staff'] : $a, $present);
		if ($mode === Bo::COMMENTS_DISABLED)
		{
			return null;	// Ui::index() sends no comments at all, not even to staff
		}
		if ($is_staff)
		{
			return $labels;	// staff gets everything, whatever the mode
		}
		$group = array_keys(array_filter(['s1' => 1, 's2' => 1], fn($x, $label) => $fx['groups'][$label] === $fx['groups'][$viewer], ARRAY_FILTER_USE_BOTH));
		$set = match(self::VISIBILITY_RULES[$mode])
		{
			'all' => $labels,
			'self+staff' => array_merge([$viewer], $staff),
			'students' => ['s1', 's2'],
			'self' => [$viewer],
			'staff' => $staff,
			'group+staff' => array_merge($group, $staff),
			'group' => $group,
		};
		return array_values(array_filter($labels, static fn($a) => in_array($a, $set, true)));
	}

	/**
	 * The comments a viewer sees as "author[reply authors]", replies filtered like comments
	 */
	protected function expectedVisible(int $mode, string $viewer, array $fx) : ?array
	{
		$authors = ['admin', 'staff2', 's1', 's2'];
		$visible = $this->visibleAuthors($mode, $viewer, $authors, $fx);
		if ($visible === null)
		{
			return null;
		}
		$replies = ['admin' => ['s1', $fx['staff']], 's1' => ['s2', 'admin']];
		$out = [];
		foreach ($visible as $author)
		{
			$of = array_values(array_filter($replies[$author] ?? [], static fn($by) => in_array($by, $visible, true)));
			$out[] = $author.($of ? '['.implode(',', $of).']' : '');
		}
		return $out;
	}

	/** Comments a viewer owns: all (teacher and up) or only their own (tutor, student), among the visible ones */
	protected function expectedOwners(string $viewer, ?array $visible) : ?array
	{
		if ($visible === null)
		{
			return null;
		}
		$authors = array_map(static fn($v) => preg_replace('/\[.*/', '', $v), $visible);
		return in_array($viewer, ['admin', 'teacher'], true) ? $authors : array_values(array_intersect($authors, [$viewer]));
	}

	/** The comment actions the server leaves on the list, Ui::get_actions() minus what a viewer may not do */
	protected function expectedActions(bool $is_staff, int $mode, $accessible) : array
	{
		return (($mode === Bo::COMMENTS_FORBIDDEN_BY_STUDENTS && !$is_staff) || $accessible === 'readonly') ?
			['open'] : ['open', 'add', 'edit', 'retweet', 'delete'];
	}

	/** Ui::showCommentButton(): hidden for students and tutors when forbidden or disabled, never for teachers/admins */
	protected function expectedAddHidden(string $viewer, int $mode) : bool
	{
		return !in_array($viewer, ['admin', 'teacher'], true) &&
			in_array($mode, [Bo::COMMENTS_FORBIDDEN_BY_STUDENTS, Bo::COMMENTS_DISABLED], true);
	}

	// ------------------------------------------------------------------ tests

	/**
	 * Group "visibility": which comments and replies each kind of member receives for each comment visibility option
	 *
	 * Proves, for every viewer x video_options combination of two courses (one with groups, one without), that
	 * Ui::index() sends exactly the comments and replies the rules table gives, flags them commentOwner as the rules
	 * say, strips the actions a student must not have, and shows the add-comment button as showCommentButton() says.
	 * A legacy video_options=8 must read as "all comments" plus the simulated flag.
	 * Viewers: admin (owner), tutor, student with group 1 and 2 (course with groups); teacher, two students
	 * without a group (course without groups); someone who is no member.
	 */
	public function testCommentVisibilityByOption()
	{
		foreach (['groups' => ['admin', 'tutor', 's1', 's2'], 'flat' => ['admin', 'teacher', 's1', 's2']] as $kind => $viewers)
		{
			$fx = $this->makeCourse($kind);
			$lids = ['admin' => self::TEACHER, 'tutor' => self::TUTOR, 'teacher' => self::TUTOR, 's1' => self::STUDENT1, 's2' => self::STUDENT2];
			foreach (array_keys(self::VISIBILITY_RULES) as $mode)
			{
				$video = $this->makeVideo($fx, ['video_options' => $mode]);
				foreach ($viewers as $viewer)
				{
					$case = "visibility/$kind/mode-$mode/$viewer";
					$facts = $this->facts($this->index($lids[$viewer], $fx['course'], $video), $fx['labels']);
					$is_staff = in_array($viewer, ['admin', 'tutor', 'teacher'], true);
					$visible = $this->expectedVisible($mode, $viewer, $fx);
					$this->verify($case, ['course' => $kind, 'video_options' => $mode, 'video_published' => Bo::VIDEO_PUBLISHED], $viewer, $facts, [
						'is_staff' => $is_staff ? ($viewer === 'admin' ? 'admin' : $fx['staff']) : null,
						'video.video_options' => (string)$mode,
						'video.accessible' => true,
						'visible' => $visible,
						'owners' => $this->expectedOwners($viewer, $visible),
						'actions' => $this->expectedActions($is_staff, $mode, true),
						// editing and deleting need the commentOwner class, the other actions are for everybody
						'enable_class' => array_intersect_key(['open' => null, 'add' => null, 'edit' => 'commentOwner', 'retweet' => null,
							'delete' => 'commentOwner'], array_flip($this->expectedActions($is_staff, $mode, true))),
						'add_comment_hidden' => $this->expectedAddHidden($viewer, $mode),
						'locked' => null,
					]);
				}
			}
		}
		// data from before 26.1.002 carries "simulated" as video_options 8, which readVideo() translates
		$fx = $this->makeCourse('flat');
		$video = $this->makeVideo($fx, ['video_options' => Bo::COMMENTS_SIMULATED_LIVE_SESSION]);
		foreach (['admin' => self::TEACHER, 's1' => self::STUDENT1] as $viewer => $lid)
		{
			$facts = $this->facts($this->index($lid, $fx['course'], $video), $fx['labels']);
			$this->verify("visibility/legacy-8/$viewer", ['course' => 'flat', 'video_options' => 8], $viewer, $facts, [
				// translated by readVideo(), so these two are integers, while the database columns are strings
				'video.video_options' => 0,
				'video.video_livefeedback_simulated' => 1,
			]);
		}
		// somebody who is no member of the course is sent to the course's start page: no video, no comments
		$course = $this->createCourse();
		$video = $this->makeVideo(['course' => $course], [], ['admin']);
		$facts = $this->facts($this->index(self::STUDENT1, $course, $video), []);
		$this->verify('visibility/non-member', ['course' => 'not subscribed'], 'outsider', $facts, [
			'template' => 'smallpart.start', 'has_video' => false, 'visible' => null,
		]);
		$this->finish('visibility');
	}

	/**
	 * The constants the client policy module repeats from Bo.php, as the golden file the JS test compares them with
	 *
	 * Proves the numbers in js/CommentPolicy/CommentPolicy.ts (comment visibility modes, test option bits, the test
	 * display "list", the cognitive load bits) are the ones the server uses: the JS test fails if either side changes.
	 */
	public function testSharedConstants()
	{
		$constants = [];
		foreach ((new \ReflectionClass(Bo::class))->getConstants() as $name => $value)
		{
			if (preg_match('/^(COMMENTS_(?!SIMULATED)|TEST_OPTION_|OPTION_CL_MEASUREMENT$)/', $name) || $name === 'TEST_DISPLAY_LIST')
			{
				$constants[$name] = $value;
			}
		}
		$this->assertCount(8 + 6 + 1 + 1, $constants, 'every constant the client repeats');
		$this->golden['constants'] = ['case' => 'constants', 'payload' => $constants];
		$this->finish('constants');
	}

	/**
	 * Group "state": what each kind of member gets for each publishing state of a video (draft, published with and
	 * without a time window, unavailable, read-only, prerequisite not met, target)
	 *
	 * Proves that `accessible`, `locked`/`countdown`, the presence of the video and of its comments and the actions
	 * follow the rules: staff always gets full access (Bo::videoEditable() answers before any state is looked at), a
	 * student gets the video only where the state allows, "readonly" strips the actions, a video that starts later
	 * is locked with a countdown.  Viewers: the admin, the tutor, student 1.
	 *
	 * An unmet prerequisite locks the page for staff too (Ui::index() checks it for whoever opens the video): the rule
	 * records that as it is.  KNOWN GAP: a locked video's comments are sent although the page is locked (see knownGaps()).
	 */
	public function testVideoStateByPublishing()
	{
		$fx = $this->makeCourse('groups');
		$day = 86400;
		$states = [
			'draft' => ['video_published' => Bo::VIDEO_DRAFT],
			'published' => ['video_published' => Bo::VIDEO_PUBLISHED],
			'published-starts-later' => ['video_published' => Bo::VIDEO_PUBLISHED, 'video_published_start' => new Api\DateTime('@'.(time() + $day))],
			'published-ended' => ['video_published' => Bo::VIDEO_PUBLISHED, 'video_published_end' => new Api\DateTime('@'.(time() - $day))],
			'published-in-window' => ['video_published' => Bo::VIDEO_PUBLISHED, 'video_published_start' => new Api\DateTime('@'.(time() - $day)),
				'video_published_end' => new Api\DateTime('@'.(time() + $day))],
			'unavailable' => ['video_published' => Bo::VIDEO_UNAVAILABLE],
			'readonly' => ['video_published' => Bo::VIDEO_READONLY],
			'prerequisite-not-met' => ['video_published' => Bo::VIDEO_PUBLISHED_PREREQUISITE],
			'target' => ['video_published' => Bo::VIDEO_TARGET],
		];
		// what a student gets: ok | locked (starts later) | readonly | gone (video not delivered)
		$student = ['draft' => 'gone', 'published' => 'ok', 'published-starts-later' => 'locked', 'published-ended' => 'gone',
			'published-in-window' => 'ok', 'unavailable' => 'gone', 'readonly' => 'readonly', 'prerequisite-not-met' => 'gone', 'target' => 'ok'];
		$prerequisite = $this->makeVideo($fx, [], ['admin']);
		foreach ($states as $state => $options)
		{
			if ($state === 'prerequisite-not-met')
			{
				$options['video_published_prerequisite'] = (string)$prerequisite['video_id'];
			}
			$video = $this->makeVideo($fx, $options, ['admin', 's1']);
			foreach (['admin' => self::TEACHER, 'tutor' => self::TUTOR, 's1' => self::STUDENT1] as $viewer => $lid)
			{
				$case = "state/$state/$viewer";
				$facts = $this->facts($this->index($lid, $fx['course'], $video), $fx['labels']);
				$outcome = $viewer === 's1' ? $student[$state] : 'ok';
				$both = ['admin[s1]', 's1[admin]'];
				$expected = match($outcome)
				{
					'ok' => ['has_video' => true, 'video.accessible' => true, 'locked' => $state === 'prerequisite-not-met' ? true : null, 'countdown' => false,
						'visible' => $both, 'actions' => $this->expectedActions($viewer !== 's1', 0, true)],
					'locked' => ['has_video' => true, 'video.accessible' => false, 'locked' => true, 'countdown' => true,
						// the page is locked, there is nothing to comment on: the comments should not be sent
						'visible' => null],
					'readonly' => ['has_video' => true, 'video.accessible' => 'readonly', 'locked' => null, 'countdown' => false,
						'visible' => $both, 'actions' => ['open']],
					'gone' => ['has_video' => false, 'visible' => null],
				};
				$this->verify($case, ['course' => 'groups', 'state' => $state, 'video_published' => $options['video_published']],
					$viewer, $facts, $expected);
			}
		}
		$this->finish('state');
	}

	/**
	 * Group "test": a timed test (duration 10 minutes) in each of its states for a student, with each test option on
	 * its own and all of them together; plus the staff view of a test not yet started, and the video_test_display values
	 *
	 * Proves that, per state (not started, running, stopped) and option bit, the payload has the rules table's
	 * `accessible`, `locked`, `timer`, `seekable`, `free_comment_only`, the `pause` readonly flag, and that the
	 * comments carry what the options say: category replaced by "free" (FREE_COMMENT_ONLY for everybody, TEACHER_FREE_COMMENT
	 * for staff's comments while the test runs), staff text and replies hidden while the test runs (HIDE_TEACHER_COMMENT_TEXT).
	 * After a stop the video is gone, unless VIDEO_READONLY_AFTER_TEST is set: then it is "readonly" with the comment
	 * option forced to HIDE_OTHER_STUDENTS.  The questions of the test arrive as a list only for display 3.
	 *
	 * KNOWN GAPS: see knownGaps() (a stopped test with any non-zero option counts as "read-only after the test";
	 * comments of a not yet started test are sent).
	 */
	public function testTestModeByStateAndOption()
	{
		$fx = $this->makeCourse('flat');
		$option_sets = ['none' => 0, 'allow-pause' => Bo::TEST_OPTION_ALLOW_PAUSE, 'forbid-seek' => Bo::TEST_OPTION_FORBID_SEEK,
			'free-comment-only' => Bo::TEST_OPTION_FREE_COMMENT_ONLY, 'readonly-after-test' => Bo::TEST_OPTION_VIDEO_READONLY_AFTER_TEST,
			'teacher-free-comment' => Bo::TEST_OPTION_TEACHER_FREE_COMMENT, 'hide-teacher-text' => Bo::TEST_OPTION_HIDE_TEACHER_COMMENT_TEXT,
			'all' => 63];
		foreach ($option_sets as $name => $bits)
		{
			foreach (['not-started', 'running', 'stopped'] as $state)
			{
				$video = $this->makeVideo($fx, ['video_test_duration' => 10, 'video_test_options' => $bits], ['admin', 's1']);
				if ($state !== 'not-started')
				{
					$this->asAccount(self::STUDENT1, function() use ($video, $state)
					{
						$bo = new Bo();
						$bo->testStart($bo->readVideo($video['video_id']), $time);
						if ($state === 'stopped')
						{
							$bo->testStop($bo->readVideo($video['video_id']), true);
						}
					});
				}
				foreach (['s1' => self::STUDENT1, 'admin' => self::TEACHER] as $viewer => $lid)
				{
					if ($viewer === 'admin' && $state !== 'not-started')
					{
						continue;	// staff does not run the test
					}
					$case = "test/$name/$state/$viewer";
					$facts = $this->facts($this->index($lid, $fx['course'], $video), $fx['labels']);
					$this->verify($case, ['course' => 'flat', 'video_test_duration' => 10, 'video_test_options' => $bits, 'state' => $state],
						$viewer, $facts, $this->testModeRules($state, $bits, $viewer === 'admin'));
				}
			}
		}
		foreach ([0, 1, 2, 3] as $display)
		{
			$video = $this->makeVideo($fx, ['video_test_display' => $display], ['admin']);
			$facts = $this->facts($this->index(self::STUDENT1, $fx['course'], $video), $fx['labels']);
			$this->verify("test/display-$display/s1", ['course' => 'flat', 'video_test_display' => $display], 's1', $facts, [
				'video.video_test_display' => (string)$display,
				'questions' => $display === Bo::TEST_DISPLAY_LIST,
			]);
		}
		$this->finish('test');
	}

	/** Rules of testModeByStateAndOption: expected facts for a viewer of a test in a state with the given option bits */
	protected function testModeRules(string $state, int $bits, bool $staff) : array
	{
		$free_only = (bool)($bits & Bo::TEST_OPTION_FREE_COMMENT_ONLY);
		if ($staff)
		{
			// staff always has access, a test not yet started shows its start page to them too
			return ['has_video' => true, 'video.accessible' => true, 'locked' => true, 'timer' => null,
				'video.seekable' => true, 'free_comment_only' => $free_only, 'visible' => ['admin[s1]', 's1[admin]']];
		}
		$rules = [];
		switch ($state)
		{
			case 'not-started':
				// can be started: accessible is null, the page shows the start button and the duration
				return $rules + ['free_comment_only' => $free_only, 'has_video' => true, 'video.accessible' => null, 'locked' => true, 'timer' => null,
					'video.video_test_duration' => '10', 'video.seekable' => !($bits & Bo::TEST_OPTION_FORBID_SEEK),
					'pause_readonly' => null,
					// the test has not started: comments (staff's text included) should not be sent yet
					'visible' => null];
			case 'running':
				$hide_text = (bool)($bits & Bo::TEST_OPTION_HIDE_TEACHER_COMMENT_TEXT);
				return $rules + ['free_comment_only' => $free_only, 'has_video' => true, 'video.accessible' => true, 'locked' => null, 'timer' => 'positive-int',
					'video.seekable' => !($bits & Bo::TEST_OPTION_FORBID_SEEK),
					'pause_readonly' => !($bits & Bo::TEST_OPTION_ALLOW_PAUSE),
					'visible' => $hide_text ? ['admin', 's1[admin]'] : ['admin[s1]', 's1[admin]'],
					'staff_comment_text' => $hide_text ? null : 'c-admin',
					'staff_comment_cat' => ($bits & Bo::TEST_OPTION_TEACHER_FREE_COMMENT) ? 'free' : null,
					'own_comment_cat' => null,
					'cat_types' => $free_only ? ['free'] : [null]];
			case 'stopped':
				if ($bits & Bo::TEST_OPTION_VIDEO_READONLY_AFTER_TEST)
				{
					return $rules + ['free_comment_only' => $free_only, 'has_video' => true, 'video.accessible' => 'readonly', 'locked' => null, 'timer' => null,
						// readVideo() forces the comment option to "hide other students" and the state to read-only
						'video.video_options' => 1, 'video.video_published' => 3, 'actions' => ['open'],
						'visible' => ['admin[s1]', 's1[admin]']];
				}
				return $rules + ['has_video' => false, 'visible' => null];
		}
	}

	/**
	 * Group "simulated": a video flagged as simulated live session (and a plain one), before and after it was watched
	 * through, for a student and for the admin
	 *
	 * Proves that the flag arrives as the string "0" / "1" (the client's `isSimulated()` depends on it), that a running
	 * simulated session sends no comments, `isCommentAllowed` false and the add button hidden, forces the test option
	 * "forbid seeking" with `seekable` for staff only, and that a finished one is `ended` and sends the comments again.
	 */
	public function testSimulatedLiveSession()
	{
		$fx = $this->makeCourse('flat');
		$plain = $this->makeVideo($fx, [], ['admin', 's1']);
		$sim = $this->makeVideo($fx, ['video_livefeedback_simulated' => 1], ['admin', 's1']);
		$sim_s1_done = $this->makeVideo($fx, ['video_livefeedback_simulated' => 1], ['admin', 's1']);
		$sim_admin_done = $this->makeVideo($fx, ['video_livefeedback_simulated' => 1], ['admin', 's1']);
		foreach ([self::STUDENT1 => $sim_s1_done, self::TEACHER => $sim_admin_done] as $lid => $video)
		{
			$this->asAccount($lid, function() use ($fx, $video)
			{
				$bo = new Bo();
				$bo->simulatedStart($fx['course']['course_id'], $video['video_id']);
				$bo->simulatedFinish($fx['course']['course_id'], $video['video_id'], 60);
			});
		}
		$cases = [
			'plain' => [$plain, $plain],
			'running' => [$sim, $sim],
			'finished' => [$sim_s1_done, $sim_admin_done],	// the one each viewer finished
		];
		foreach ($cases as $state => [$video_s1, $video_admin])
		{
			foreach (['s1' => [self::STUDENT1, $video_s1], 'admin' => [self::TEACHER, $video_admin]] as $viewer => [$lid, $video])
			{
				$staff = $viewer === 'admin';
				$facts = $this->facts($this->index($lid, $fx['course'], $video), $fx['labels']);
				$rules = $state === 'running' ? [
					'video.video_livefeedback_simulated' => '1', 'video.livefeedback_session' => 'simulated',
					'visible' => null, 'isCommentAllowed' => false, 'add_comment_hidden' => true,
					'video.video_test_options' => Bo::TEST_OPTION_FORBID_SEEK, 'video.seekable' => $staff,
				] : [
					'video.video_livefeedback_simulated' => $state === 'plain' ? '0' : '1',
					'video.livefeedback_session' => $state === 'plain' ? null : 'ended',
					'visible' => ['admin[s1]', 's1[admin]'], 'isCommentAllowed' => null, 'video.seekable' => true,
				];
				$this->verify("simulated/$state/$viewer", ['course' => 'flat', 'video_livefeedback_simulated' => (int)($state !== 'plain'), 'state' => $state],
					$viewer, $facts, $rules);
			}
		}
		$this->finish('simulated');
	}

	/**
	 * Group "livefeedback": a real live feedback session in each of its states, seen by the host (admin), a staff
	 * member and a student
	 *
	 * Proves the session state the client switches its layout on (`not-started`, `running`, `hosting` for the one who
	 * hosts, `ended`) and that the categories for the live buttons arrive exactly while a session exists.
	 */
	public function testLivefeedbackSessionStates()
	{
		$fx = $this->makeCourse('flat', true);
		$so = (new \ReflectionProperty(Bo::class, 'so'));
		$so->setAccessible(true);
		$now = Api\DateTime::to('now', 'Y-m-d H:i:s');
		$host = $this->accountId(self::TEACHER);
		$states = [
			'not-started' => [], 'running' => ['session_starttime' => $now, 'host' => $host],
			'ended' => ['session_starttime' => $now, 'session_endtime' => $now, 'host' => $host],
		];
		foreach ($states as $state => $row)
		{
			$video = $this->makeVideo($fx, [], ['admin', 's1']);
			$this->asAccount(self::TEACHER, function() use ($so, $fx, $video, $row, $host)
			{
				$so->getValue(new Bo())->saveLivefeedback($row + ['course_id' => $video['course_id'], 'video_id' => $video['video_id'], 'host' => $host]);
			});
			foreach (['admin' => self::TEACHER, 'teacher' => self::TUTOR, 's1' => self::STUDENT1] as $viewer => $lid)
			{
				$session = $state === 'running' && $viewer === 'admin' ? 'hosting' : $state;
				$facts = $this->facts($this->index($lid, $fx['course'], $video), $fx['labels']);
				$this->verify("livefeedback/$state/$viewer", ['course' => 'flat', 'state' => $state], $viewer, $facts, [
					'video.livefeedback_session' => $session,
					'lf_cats' => 4,
				]);
			}
		}
		$this->finish('livefeedback');
	}

	/**
	 * Group "categories": the category options the comment form gets, with and without the course setting "no free comment"
	 *
	 * Proves `catsOptions` holds the 4 main categories with their like/dislike children, the special one (disabled: it can
	 * not be picked, only turned on) and the "free comment" entry exactly if the course allows free comments, that the
	 * special category gets its add action, and that the form's empty label is set only when free comments are off.
	 */
	public function testCategoryOptions()
	{
		foreach ([false, true] as $no_free)
		{
			$fx = $this->makeCourse('flat', true);
			$this->asAccount(self::TEACHER, function() use ($fx, $no_free)
			{
				(new Bo())->save(['course_id' => $fx['course']['course_id'], 'config' => ['no_free_comment' => $no_free]] + $fx['course']);
			});
			$video = $this->makeVideo($fx, [], ['admin']);
			foreach (['admin' => self::TEACHER, 's1' => self::STUDENT1] as $viewer => $lid)
			{
				$facts = $this->facts($this->index($lid, $fx['course'], $video), $fx['labels']);
				$this->verify('categories/no-free-comment-'.(int)$no_free."/$viewer", ['course' => 'flat', 'no_free_comment' => $no_free], $viewer, $facts, [
					// 4 main + 8 like/dislike + 1 special, plus the free entry
					'cats_options' => 13 + ($no_free ? 0 : 1),
					'cats_free_option' => !$no_free,
					'cats_disabled' => 1,
					'special_actions' => 1,
					'empty_cat_label_set' => $no_free,
				]);
			}
		}
		$this->finish('categories');
	}

	/**
	 * Group "course": the course options the client reads: cognitive load measurement (needs both bits of 5), and
	 * the full live feedback categories (staff, or everybody if the course config says so)
	 *
	 * Proves `course_options` arrives as an integer, `clm` is sent exactly when both CLM bits are set, `lf_full_cats`
	 * is a boolean and follows role and course config.
	 */
	public function testCourseOptions()
	{
		foreach ([0, 1, 4, 5, 7] as $options)
		{
			foreach ([false, true] as $everyone)
			{
				$fx = $this->makeCourse('flat');
				$this->asAccount(self::TEACHER, function() use ($fx, $options, $everyone)
				{
					(new Bo())->save(['course_id' => $fx['course']['course_id'], 'course_options' => $options,
						'config' => ['lf_cats_for_everyone' => $everyone]] + $fx['course']);
				});
				$video = $this->makeVideo($fx, [], ['admin']);
				foreach (['admin' => self::TEACHER, 's1' => self::STUDENT1] as $viewer => $lid)
				{
					$facts = $this->facts($this->index($lid, $fx['course'], $video), $fx['labels']);
					$this->verify("course/options-$options/everyone-".(int)$everyone."/$viewer", ['course' => 'flat',
						'course_options' => $options, 'lf_cats_for_everyone' => $everyone], $viewer, $facts, [
						'course_options' => $options,
						'clm' => ($options & Bo::OPTION_CL_MEASUREMENT) === Bo::OPTION_CL_MEASUREMENT,
						'lf_full_cats' => $viewer === 'admin' || $everyone,
					]);
				}
			}
		}
		$this->finish('course');
	}
}
