<?php
/**
 * EGroupware - SmallParT - hooks
 *
 * @link https://www.egroupware.org
 * @package smallpart
 * @subpackage setup
 * @license https://spdx.org/licenses/AGPL-3.0-or-later.html GNU Affero General Public License v3.0 or later
 */

namespace EGroupware\SmallParT;

use Assert\InvalidArgumentException;
use EGroupware\Api;
use PHPUnit\Exception;

/**
 * Store and load overlay elements
 *
 * Overlay elements can have arbitrary attributes, which are stored json-encoded in overlay_data column.
 *
 * Ajax methods do ACL checks and return message of type error responses on error or data responses on success.
 *
 * Non ajax method are NOT checking any ACL.
 */
class Overlay
{
	const APP = 'smallpart';
	/**
	 * Subtype used in link-system / -registry
	 */
	const SUBTYPE = 'smallpart-overlay';

	/**
	 * Name of overlay table
	 */
	const TABLE = 'egw_smallpart_overlay';
	/**
	 * Name of answers table
	 */
	const ANSWERS_TABLE = 'egw_smallpart_answers';

	/**
	 * @var Api\Db reference to global db object
	 */
	protected static $db;

	/**
	 * Read overlay elements of a video
	 *
	 * ACL check and removing information about correct answer(s) for participants is only performed if video_id given in $where!
	 * (Not for $where === ['overlay_id' => $id] used in serverside code to read or score a single answer!)
	 *
	 * @param int|array $where video_id or array with more filters (you should always specify the course_id, as it's needed for video_id=0 questions!)
	 * @param int $offset =0 first row to return
	 * @param int $num_rows =50 number of rows to return with full data, others have data === false
	 * @param string $order_by ='overlay_start ASC,overlay_id ASC'
	 * @param bool $get_rows =false true: get_rows specific behavior: always use $num_rows and return integer as strings
	 * @param ?bool $remove_correct true: remove correct answers for sending to client-side, false: dont, null: try to determine
	 * @return array with values for keys "total" and "elements"
	 * @throws Api\Exception\NoPermission if ACL check fails
	 */
	public static function read($where, $offset=0, $num_rows=50, $order_by='overlay_start ASC', $get_rows=false, $remove_correct=null)
	{
		if (!preg_match('/^([a-z0-9_]+ (ASC|DESC),?)*$/', $order_by) || !is_int($offset) || !is_int($num_rows))
		{
			throw new \InvalidArgumentException("Invalid argument ".__METHOD__."(".json_encode($where).", $offset, $num_rows, '$order_by')");
		}
		// always add overlay_id to get a stable sort-order
		$order_by = (str_replace('overlay_id', self::TABLE.'.overlay_id', $order_by) ?: 'overlay_start ASC').
			','.self::TABLE.'.overlay_id ASC';

		if (!is_array($where)) $where = ['video_id' => (int)$where];

		// check ACL, if we have video_id
		if (isset($where['video_id']) && !($accessible = Bo::getInstance()->videoAccessible($where['video_id'], $admin)))
		{
			throw new Api\Exception\NoPermission();
		}
		// also read questions for all videos (video_id=0)
		if (isset($where['video_id']) && is_scalar($where['video_id']))
		{
			$where['video_id'] = [0, $video_id=$where['video_id']];

			// check if we have a parent course to inherit questions from
			if (($course_ids = self::$db->select(So::VIDEO_TABLE, So::VIDEO_TABLE.'.course_id,course_parent',
					['video_id' => $video_id], __LINE__, __FILE__, false, '', self::APP,
					1, 'JOIN '.So::COURSE_TABLE.' ON '.So::VIDEO_TABLE.'.course_id='.SO::COURSE_TABLE.'.course_id')->fetch()))
			{
				$where['course_id'] = array_filter(array_values($course_ids));
			}
		}
		// if a single course_id is given, also get its parent course if existing
		elseif (!empty($where['course_id']) && is_scalar($where['course_id']))
		{
			$where['course_id'] = self::getParentToo($where['course_id']);
		}
		// for non-admins always set account_id (to read their answers)
		$admin = Bo::getInstance()->isAdmin($course_id = ((array)$where['course_id'])[0] ?? null);
		if (!$admin)
		{
			$where['account_id'] = $GLOBALS['egw_info']['user']['account_id'];
		}

		// do NOT show hidden/deleted questions
		if (!isset($where['overlay_type']) && !in_array("overlay_type LIKE 'smallpart-question-%", $where))
		{
			$where[] = "overlay_type NOT LIKE 'D-%'";
		}

		if (!isset($remove_correct))
		{
			$remove_correct = empty($where['overlay_id']) && isset($admin) && !$admin && $accessible !== 'readonly';
		}

		if (!empty($account_id=$where['account_id']))
		{
			$join = 'LEFT JOIN '.self::ANSWERS_TABLE.' ON '.
				self::$db->expression(self::ANSWERS_TABLE, self::TABLE.'.overlay_id='.self::ANSWERS_TABLE.'.overlay_id AND ',
					['account_id' => $where['account_id']],
					' AND '.self::ANSWERS_TABLE.'.', ['video_id' => $video_id ?? $where['video_id']]);

			// make sure filters are not ambiguous
			foreach($where as $name => $value)
			{
				if (in_array($name, ['course_id','video_id','overlay_id']))
				{
					$where[] = self::$db->expression(self::TABLE, self::TABLE.'.', [$name => $value]);
					unset($where[$name]);
				}
			}
			$cols = self::ANSWERS_TABLE.'.*,'.self::TABLE.'.*';
		}
		else
		{
			$cols = '*';
		}
		unset($where['account_id']);
		// add the real course id, not the parent course id
		if ((int)$course_id > 0)
		{
			$cols .= ','.(int)$course_id.' AS course_id';
		}
		// add an ascending question number
		$cols .= ",(SELECT CASE WHEN ".self::TABLE.".overlay_type LIKE 'smallpart-question-%' THEN 1+COUNT(*) ELSE NULL END FROM ".
			self::TABLE.' q WHERE q.video_id='.self::TABLE.".video_id AND q.overlay_type LIKE 'smallpart-question-%'".
			// add a fraction of id to start-time to not get identical numbers for questions with the same start-time
			" AND (q.overlay_start+(q.overlay_id % 100000)/100000.0) < (".self::TABLE.'.overlay_start+('.self::TABLE.'.overlay_id % 100000)/100000.0)'.
			' AND q.overlay_id != '.self::TABLE.'.overlay_id) AS question_n';

		if (substr($where['overlay_type'], -2) === '-%')
		{
			$where[] = 'overlay_type LIKE '.self::$db->quote($where['overlay_type']);
			unset($where['overlay_type']);
		}

		$ret = ['elements' => []];
		foreach(self::$db->select(self::TABLE, $cols ?? '*', $where, __LINE__, __FILE__, $get_rows || $offset ? $offset : false, 'ORDER BY '.$order_by, self::APP, $num_rows, $join) as $row)
		{
			if (!$row['video_id'])
			{
				$row['all_videos'] = true;
				$row['video_id'] = $video_id ?? (($last = Bo::getInstance()->lastVideo()) ? $last['video_id'] : null);
			}
			$ret['elements'][] = self::db2data($row, $get_rows || !(!$offset && count($ret['elements']) > $num_rows),
				!$get_rows, $remove_correct);
		}
		if ($offset === 0)
		{
			$ret['total'] = count($ret['elements']);
		}
		else
		{
			$ret['total'] = (int)self::$db->select(self::TABLE, 'COUNT(*)', $where, __LINE__, __FILE__, false, '', self::APP)->fetchColumn();
		}
		// for score sums we only care about questions
		if (!isset($where['overlay_type']))
		{
			$where[] = "overlay_type LIKE 'smallpart-question-%'";
		}
		if (!empty($account_id))
		{
			$ret['sum_score'] = (double)self::$db->select(self::TABLE, 'SUM(answer_score)', $where, __LINE__, __FILE__, false, '', self::APP, null, $join)->fetchColumn();
		}
		try {
			// newer MariaDB JSON function
			$ret['max_score'] = (double)self::$db->select(self::TABLE, "SUM(JSON_VALUE(overlay_data,'$.max_score'))", $where, __LINE__, __FILE__, false, '', self::APP, null, $join)->fetchColumn();
		}
		catch (Api\Db\Exception $e) {
			// do it manually for all other DBs
			$ret['max_score'] = 0.0;
			foreach(self::$db->select(self::TABLE, 'overlay_data', $where, __LINE__, __FILE__, false, '', self::APP, null, $join) as $row)
			{
				$row = json_decode($row['overlay_data'], true);
				$ret['max_score'] += $row['max_score'];
			}
		}
		return $ret;
	}

	/**
	 * Fetch rows to display
	 *
	 * @param array $query
	 * @param array& $rows =null
	 * @param array& $readonlys =null
	 * @return int total number of rows
	 */
	public static function get_rows($query, array &$rows = null, array &$readonlys = null)
	{
		$result = self::read(array_filter($query['col_filter'], static function($val) {
			return $val !== '';	// '' = All
		}), (int)$query['start'], $query['num_rows']??100, $query['order']?$query['order'].' '.$query['sort']:'', true);

		$rows = $result['elements'];
		$rows['max_score'] = $result['max_score'];
		$rows['sum_score'] = $result['sum_score'] ?? '';

		return $result['total'];
	}

	/**
	 * All integer columns to fix their type after reading from DB
	 *
	 * @var string[]
	 */
	static $int_columns = ['overlay_id', 'video_id', 'course_id', 'overlay_start', 'overlay_player_mode', 'overlay_duration','answer_id','account_id','answer_modifier'];
	static $timestamps = ['answer_created', 'answer_modified'];

	/**
	 * Convert from DB to internal representation (incl. ensuring value types)
	 *
	 * @param array $data
	 * @param boolean $decode_json true: decode overlay_data column, false: set ['data'=>false] in return array
	 * @param boolean $convert_int =true true: convert integer columns to int, false: leave as string as returned by DB
	 * @param boolean $remove_correct =true true: remove information about correct answer (to not send to client-side for participants)
	 * @return array
	 */
	static protected function db2data(array $data, $decode_json=true, $convert_int=true, $remove_correct=true)
	{
		static $question_templates = [];

		foreach($convert_int ? self::$int_columns : [] as $col)
		{
			if ($data[$col] !== null) $data[$col] = (int)$data[$col];
		}
		foreach(self::$timestamps as $col)
		{
			if ($data[$col] !== null) $data[$col] = Api\DateTime::server2user($data[$col]);
		}
		if ($decode_json)
		{
			$data += json_decode($data['overlay_data'], true);
			unset($data['overlay_data']);

			$data['answer_data'] = !empty($data['answer_data']) ? json_decode($data['answer_data'], true) : [];

			if (is_array($data['answers']))
			{
				foreach($data['answers'] as &$answer)
				{
					// do not send information about correct answer to client-side
					if ($remove_correct)
					{
						unset($answer['correct'], $answer['score']);
					}
					// reintegration multiple choice answers, if we have them
					if (is_array($data['answer_data']['answers']))
					{
						foreach($data['answer_data']['answers'] as &$a)
						{
							if ($answer['id'] === $a['id'])
							{
								if ($remove_correct)
								{
									unset($a['score']);
								}
								else
								{
									$answer['answer_score'] = $a['score'];
									$answer['answer_scoring'] = $a['scoring'];
								}
								$answer['check'] = $a['check'];
								break;
							}
						}
					}
				}
			}
			// do not send information about correct answer to client-side
			if ($remove_correct)
			{
				unset($data['answer'], $data['answer_data']['remark'], $data['answer_score'], $data['marks']);

				if ($data['shuffle_answers']) shuffle($data['answers']);
			}
			elseif (is_string($data['marks']))
			{
				$data['marks'] = json_decode($data['marks'], true);
			}
			// send client-side url for question-template, to have proper cache-buster and support customizing
			if (substr($data['overlay_type'], 0, 18) === 'smallpart-question')
			{
				if (!isset($question_templates[$data['overlay_type']]))
				{
					$question_templates[$data['overlay_type']] = Api\Etemplate::rel2url(Api\Etemplate::relPath(
						str_replace('-', '.', $data['overlay_type'])));
				}
				$data['template_url'] = $question_templates[$data['overlay_type']];
			}
		}
		else
		{
			$data['data'] = false;
		}
		return $data;
	}

	/**
	 * Read overlay elements of a video
	 *
	 * @param array $where values for course_id AND video_id, optional more filters
	 * @param int $offset =0 first row to return
	 * @param int $num_rows =50 number of rows to return
	 * @param string $order_by ='overlay_start ASC'
	 * @return void JSON data response or message with error message
	 */
	public static function ajax_read(array $where, $offset=0, $num_rows=50, $order_by='overlay_start ASC')
	{
		try {
			// set account_id to read answers
			$where['account_id'] = $GLOBALS['egw_info']['user']['account_id'];

			Api\Json\Response::get()->data(self::read($where, $offset, $num_rows, $order_by));
		}
		catch(\Exception $e) {
			Api\Json\Response::get()->message($e->getMessage(), 'error');
		}
	}

	const ASSESSMENT_METHOD = 'assessment_method';
	const ASSESSMENT_ALL_CORRECT = 'all_correct';
	const ASSESSMENT_SCORE_PER_ANSWER = 'score_per_answer';

	/**
	 * Add or update an overlay element (no ACL check)
	 *
	 * @param array $data
	 * @return int overlay_id
	 * @throws Api\Exception\WrongParameter
	 */
	public static function write(array $data)
	{
		static $table_def, $answer_table_def;
		if (!isset($table_def)) $table_def = self::$db->get_table_definitions(self::APP, self::TABLE);
		if (!isset($answer_table_def)) $answer_table_def = self::$db->get_table_definitions(self::APP, self::ANSWERS_TABLE);

		if (!(is_int($data['course_id']) || is_numeric($data['course_id'])) ||
			!(is_int($data['video_id']) || is_numeric($data['video_id'])))
		{
			throw new \InvalidArgumentException("Invalid argument ".__METHOD__."(".json_encode($data).")");
		}
		// do NOT write answer_* fields, they need to go to egw_smallpart_answers
		$data = array_filter($data, function($name)
		{
			return substr($name, 0, 6) !== 'answer_' &&
				!in_array($name, ['overlay_end', 'template_url', 'question_n']);
		}, ARRAY_FILTER_USE_KEY);

		$overlay_id = $data['overlay_id'];
		$data['overlay_data'] = json_encode(array_diff_key($data, $table_def['fd']+$answer_table_def['fd']+array_flip(['account_id','courseAdmin'])),
			JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES);
		self::$db->insert(self::TABLE, $data, empty($overlay_id) ? false : ['overlay_id' => $overlay_id], __LINE__, __FILE__, self::APP);

		return empty($overlay_id) ? self::$db->get_last_insert_id(self::TABLE, 'overlay_id') : $overlay_id;
	}

	/**
	 * Add or update an answer to a question / overlay element
	 *
	 * @param array $data
	 * @return int answer_id
	 * @throws Api\Exception\WrongParameter
	 */
	public static function writeAnswer(array $data)
	{
		static $table_def;
		if (!isset($table_def)) $table_def = self::$db->get_table_definitions(self::APP, self::ANSWERS_TABLE);

		if (!is_int($data['course_id']) || !is_int($data['video_id']) || !($data['account_id'] > 0))
		{
			throw new \InvalidArgumentException("Invalid argument ".__METHOD__."(".json_encode($data).")");
		}
		$default_score = Questions::defaultScore($data, 10);
		switch ($data['overlay_type'])
		{
			case 'smallpart-question-singlechoice':
				$data['answer_score'] = $data['answer_data']['answer'] === $data['answer'] ? (float)$data['max_score'] : (float)$data['min_score'];
				break;

			case 'smallpart-question-multiplechoice':
				$data['answer_score'] = self::scoreMultipleChoice($data['answers'], $data[self::ASSESSMENT_METHOD],
					$data['answer_data'], $default_score, $data['max_score'], $data['max_answers']);
				break;

			case 'smallpart-question-markchoice':
				$data['answer_score'] = self::scoreMarkChoice($data['marks'], $data['answers'], $data['answer_data'], $default_score);
				break;

			case 'smallpart-question-millout':
				$data['answer_score'] = self::scoreMillOut($data['marks'], $data['answers'], $data['answer_data'], $default_score);
				break;

			case 'smallpart-question-rating':
				$data['answer_score'] = null;
				foreach($data['answers'] as $answer)
				{
					if ($data['answer_data']['answer'] === $answer['id'])
					{
						$data['answer_score'] = $answer['score'];
						$data['answer_data']['answer_label'] = $answer['answer'];
						$data['answer_data']['color'] = $answer['color'];
						break;
					}
				}
				break;

			case 'smallpart-question-favorite':
				if ($data['answer_data']['answer'] && ($data['max_materials'] ?? '') !== '')
				{
					$total = 0;
					foreach(self::read([
						'course_id' => $data['course_id'],
						'overlay_id' => $data['overlay_id'],
						'account_id' => $data['account_id'],
						'answer_id<>'.(int)$data['answer_id'],
					]) as $row)
					{
						if ($row['answer_data']['answer']) ++$total;
					}
					if ($total >= $data['max_materials'])
					{
						throw new Api\Exception\WrongUserinput(lang("You can mark only %1 as favorite, answer is NOT saved!", $data['max_materials']));
					}
				}
				$data['answer_score'] = $data['answer_data']['answer'] ? (float)$data['max_score'] : 0;
				break;
		}
		if (!empty($data['max_score']) && $data['answer_score'] > $data['max_score'])
		{
			$data['answer_score'] = $data['max_score'];
		}
		elseif (isset($data['min_score']) && $data['min_score'] !== '' && $data['answer_score'] < $data['min_score'])
		{
			$data['answer_score'] = $data['min_score'];
		}
		// if question is exempt, update answer_data[exempt] instead of answer_score
		if (!empty($data['exempt']))
		{
			$data['answer_data']['exempt'] = $data['answer_score'];
			$data['answer_score'] = 0;
		}
		// do NOT write answer_* fields, they need to go to egw_smallpart_answers
		$data = array_filter($data, function($name) use ($table_def)
		{
			return isset($table_def['fd'][$name]);
		}, ARRAY_FILTER_USE_KEY);

		foreach(self::$timestamps as $col)
		{
			$data[$col] = Api\DateTime::user2server($data[$col] ?: 'now');
		}
		$data['answer_modifier'] = $GLOBALS['egw_info']['user']['account_id'];

		$answer_id = $data['answer_id'];
		$data['answer_data'] = json_encode($data['answer_data'], JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES);
		self::$db->insert(self::ANSWERS_TABLE, $data, empty($answer_id) ? false : ['answer_id' => $answer_id], __LINE__, __FILE__, self::APP);

		return empty($answer_id) ? self::$db->get_last_insert_id(self::ANSWERS_TABLE, 'answer_id') : $answer_id;
	}

	/**
	 * Score a multiple choice questions
	 *
	 * @param array $answers
	 * @param $assesment_method
	 * @param array $answer_data
	 * @param float $default_score
	 * @param int $max_score
	 * @param int $max_answers
	 * @return float score
	 */
	protected static function scoreMultipleChoice(array $answers, $assesment_method, array &$answer_data, $default_score, $max_score, $max_answers)
	{
		$score = 0.0;
		$checked = 0;
		foreach ($answers as $n => $answer)
		{
			unset($answer_data['answers'][$n]['score']);
			if ($assesment_method === self::ASSESSMENT_SCORE_PER_ANSWER)
			{
				if ($answer['check'] == $answer['correct'] && $answer['score'] > 0 ||
					$answer['check'] != $answer['correct'] && $answer['score'] < 0)
				{
					$score += ($answer_data['answers'][$n]['score'] = $answer['score'] ?: $default_score);
				}
			}
			elseif ($score !== 0.0)
			{
				$score = $answer['check'] == $answer['correct'] ? $max_score : 0.0;
			}
			$answer_data['answers'][$n]['check'] = $answer['check'];
			$answer_data['answers'][$n]['id'] = $answer['id'];

			// check if someone tempered with client-side enforcing max_answers
			if (!empty($max_answers) && $answer['check'] && ++$checked > $max_answers)
			{
				throw new \InvalidArgumentException("more then $max_answers answers checked!");
			}
		}
		return $score;
	}

	/**
	 * Score a mark-choice question
	 *
	 * Teach can mark several distinct areas per color, the student has to mark with a least one mark.
	 *
	 * Student get points if the majority of his marks (with matching) color are within the area(s) marked by the teacher.
	 * If the majority of the marks are outside, no points are given. Guards against marking everything!
	 *
	 * Number of points per color depends on the number of distinct areas marked by the student, eg. 2 of 3 areas marked = 2/3 of the points.
	 *
	 * @param array $marks teacher marks for correct answer(s) incl. color (c) and distinctive area (a)
	 * @param array $answers data of answers: id: color
	 * @param array $answer_data student answer: marks (in), answers (out)
	 * @param float $default_score
	 * @return float score
	 */
	protected static function scoreMarkChoice(array $marks, array $answers, array &$answer_data, $default_score)
	{
		$score = 0;
		foreach($answers as $n => $answer)
		{
			unset($answer_data['answers'][$n]['score']);
			$color = (int)$answer['id'];
			$all_marked = array_map('json_encode', self::filterColor($marks, $color));
			$checked = array_map('json_encode', self::filterColor($answer_data['marks'], $color));
			$marked_correct = $areas_correct = 0;
			for($area=0; $area < 100; ++$area)
			{
				if (!($marked = array_map('json_encode', self::filterColor($marks, $color, $area))))
				{
					break;  // no (more) areas
				}
				$marked_correct += count($correct = array_intersect($checked, $marked));
				if ($correct) $areas_correct++;
			}
			$wrong = array_diff($checked, $all_marked);
			$answer_data['answers'][$n]['id'] = $answer['id'];
			// passed if more pixel intersects with the correct answer(s), then don't
			$answer_data['answers'][$n]['check'] = $marked_correct > count($wrong) ? ($area > 1 ? $areas_correct : true) : false;
			$answer_data['answers'][$n]['scoring'] = sprintf("count(correct)=%d %s %d=count(wrong), %d/%d distinct areas",
				$marked_correct, $answer_data['answers'][$n]['check'] ? '>' : '<=', count($wrong), $areas_correct, $area);

			if (($answer_data['answers'][$n]['check']) && $area)
			{
				$score += ($answer_data['answers'][$n]['score'] = $areas_correct * ($answer['score'] ?: $default_score) / $area);
			}
		}
		return $score;
	}

	/**
	 * Percentage of mill-out teacher-solution which need to be milled out AND also percentage of student-solution intersection with teacher-solution
	 */
	const MILLOUT_PERCENTAGE = .8;

	/**
	 * Score a mill-out question
	 *
	 * Teacher marks area per color the student has to mill-out too.
	 *
	 * Student get points if he mills out 80 or more percent of the teachers area AND 80 or more percent of his marks are
	 * within the teachers marked area.
	 *
	 * @param array $marks teacher marks for correct answer(s) incl. color (c) and distinctive area (a)
	 * @param array $answers data of answers: id: color
	 * @param array $answer_data student answer: marks (in), answers (out)
	 * @param float $default_score
	 * @return float score
	 */
	protected static function scoreMillOut($marks, array $answers, array &$answer_data, $default_score)
	{
		$score = 0;
		foreach ($answers as $n => $answer)
		{
			unset($answer_data['answers'][$n]['score']);
			$color = (int)$answer['id'];
			$marked = array_map('json_encode', self::filterColor($marks, $color));
			$checked = array_map('json_encode', self::filterColor($answer_data['marks'], $color));
			$correct = array_intersect($checked, $marked);
			$answer_data['answers'][$n]['id'] = $answer['id'];
			$answer_data['answers'][$n]['check'] = count($correct) >= self::MILLOUT_PERCENTAGE*count($marked) &&
				count($correct) >= self::MILLOUT_PERCENTAGE*count($checked);
			$answer_data['answers'][$n]['scoring'] =
				sprintf("count(correct)=%d %s %.1f=%0.1f*(%d=count(teacher)) AND count(correct)=%d %s %.1f=%0.1f*(%d=count(student))",
					count($correct), count($correct) >= self::MILLOUT_PERCENTAGE*count($marked) ? '>=' : '<', self::MILLOUT_PERCENTAGE*count($marked), self::MILLOUT_PERCENTAGE, count($marked),
					count($correct), count($correct) >= self::MILLOUT_PERCENTAGE*count($checked) ? '>=' : '<', self::MILLOUT_PERCENTAGE*count($checked), self::MILLOUT_PERCENTAGE, count($checked));

			if (($answer_data['answers'][$n]['check']))
			{
				$score += ($answer_data['answers'][$n]['score'] = $answer['score'] ?: $default_score);
			}
		}
		return $score;
	}

	/**
	 * Filter marks array by color and optional area, remove evtl. set area
	 *
	 * @param ?array $marks of array for keys x, y and c
	 * @param int $color
	 * @param ?int $area
	 * @return array filtered marks without area ("a") attribute
	 */
	protected static function filterColor(array $marks=null, int $color, int $area=null)
	{
		return array_map(static function ($mark)
		{
			unset($mark['a']);
			return $mark;
		},
		array_filter($marks ?? [], static function($mark) use ($color, $area)
		{
			return $mark['c'] === $color && (!isset($area) || $mark['a'] === $area);
		}));
	}

	/**
	 * Add or update an overlay element via ajax
	 *
	 * @param array $data
	 * @return void JSON data response with overlay_id or message with error message
	 */
	public static function ajax_write(array $data)
	{
		$response = Api\Json\Response::get();
		try {
			self::aclCheck($data['course_id'], true);

			$overlay_id = self::write($data);

			$response->data(['overlay_id' => $overlay_id]);
		}
		catch(\Exception $e) {
			Api\Json\Response::get()->message($e->getMessage(), 'error');
		}
	}

	/**
	 * Get number of existing answers (optional by account_id)
	 *
	 * @param int $video_id
	 * @param array& $by_account_id =null on return account_id => number pairs
	 * @return int
	 */
	public static function countAnswers($video_id, array &$by_account_id=null)
	{
		$total = 0;
		$by_account_id = [];
		foreach(self::$db->select(self::ANSWERS_TABLE, 'account_id,COUNT(*) AS count', ['video_id' => $video_id],
			__LINE__, __FILE__, false, 'GROUP BY account_id', self::APP) as $row)
		{
			$by_account_id[$row['account_id']] = $row['count'];
			$total += $row['count'];
		}
		return $total;
	}

	/**
	 * Delete overlay elements, but NOT questions
	 *
	 * @param array $what values for course_id, video_id and optional overlay_id (without all overlay elements of a video are deleted)
	 * @return int number of deleted elements
	 */
	public static function delete(array $what)
	{
		if (!is_int($what['course_id']) || !is_int($what['video_id']))
		{
			throw new \InvalidArgumentException("Invalid argument ".__METHOD__."(".json_encode($what).")");
		}
		$what = array_intersect_key($what, ['course_id'=>1,'video_id'=>1,'overlay_id'=>1]);
		// never delete questions with (possible) participant answers, they can only by hidding, see self::hide()
		$what[] = "overlay_type NOT LIKE 'smallpart-question-%'";
		self::$db->delete(self::TABLE, $what, __LINE__, __FILE__, self::APP);

		return self::$db->affected_rows();
	}

	/**
	 * Delete questions
	 *
	 * If questions already have participant answers, they are only hidden / marked as deleted, but not removed from the DB!
	 *
	 * @param array $what
	 * @return array ($deleted, $hidden) number of questions
	 * @throws Api\Exception\NoPermission
	 */
	public static function deleteQuestion(array $what)
	{
		$what = array_intersect_key($what, ['course_id'=>1,'video_id'=>1,'overlay_id'=>1]);
		self::aclCheck($what['course_id'], true);

		$delete = $hide = [];
		foreach(self::$db->select(self::TABLE, [
			self::TABLE.'.overlay_id',
			'(SELECT COUNT(*) FROM '.self::ANSWERS_TABLE.' WHERE '.self::ANSWERS_TABLE.'.overlay_id='.self::TABLE.'.overlay_id) AS answers'
		], $what,
			__LINE__, __FILE__, false, '', self::APP) as $row)
		{
			if ($row['answers'])
			{
				$hide[] = $row['overlay_id'];
			}
			else
			{
				$delete[] = $row['overlay_id'];
			}
		}
		$deleted = $hidden = 0;
		if ($delete)
		{
			$what['overlay_id'] = $delete;
			self::$db->delete(self::TABLE, $what, __LINE__, __FILE__, self::APP);
			$deleted = self::$db->affected_rows();
		}
		if ($hide)
		{
			$what['overlay_id'] = $hide;
			self::$db->update(self::TABLE, ['overlay_type='.self::$db->concat("'D-'", 'overlay_type')],
				$what, __LINE__, __FILE__, self::APP);
			$hidden = self::$db->affected_rows();
		}
		return [$deleted, $hidden];
	}

	/**
	 * Add or update an overlay element via ajax
	 *
	 * @param array $what values for course_id, video_id and optional overlay_id (without all overlay elements of a video are deleted)
	 * @return void JSON data response with number of deleted elements under key "deleted" or message with error message
	 */
	public static function ajax_delete(array $what)
	{
		$response = Api\Json\Response::get();
		try {
			self::aclCheck($what['course_id'], true);
			if (str_contains($what['overlay_type'], 'smallpart-question-'))
			{
				$questions = new Questions();
				try
				{
					$questions->ajax_action('delete', [$what['overlay_id']], false, $what);
					$response->data(['deleted' =>'']);
				}
				catch(\Exception $e)
				{
					Api\Json\Response::get()->message($e->getMessage(), 'error');
				}
			}
			else
			{
				$response->data(['deleted' => self::delete($what)]);
			}
		}
		catch(\Exception $e) {
			Api\Json\Response::get()->message($e->getMessage(), 'error');
		}
	}

	/**
	 * Discover available overlay- and question-types
	 *
	 * @return array widget-name => label pairs
	 */
	public static function types()
	{
		//Api\Cache::unsetInstance(__CLASS__, 'type');
		$types = Api\Cache::getInstance(__CLASS__, 'type', static function()
		{
			$types = [];
			foreach(scandir(EGW_SERVER_ROOT.'/smallpart/js/overlay_plugins') as $file)
			{
				if (preg_match('/^et2_smallpart_(overlay|question)_(.*)\.ts$/i', $file, $matches))
				{
					$name = 'smallpart-'.$matches[1].'-'.$matches[2];
					$label = $matches[1] === 'question' ? lang('%1 question', ucfirst($matches[2])) : lang(ucfirst($matches[2]));
					$types[$name] = $label;
				}
			}
			krsort($types);	// reverse = questions first
			return $types;
		}, [],3600);
		return $types;
	}

	/**
	 * Check if current user is allowed to read or update a course
	 *
	 * @param int $course_id
	 * @param false $update
	 * @throws Api\Exception\NoPermission
	 */
	public static function aclCheck($course_id, $update=false)
	{
		if (!Bo::getInstance()->isParticipant($course_id) || $update && !Bo::getInstance()->isTeacher($course_id))
		{
			throw new Api\Exception\NoPermission();
		}
	}

	/**
	 * Get start-time of test
	 *
	 * @param int $video_id
	 * @param ?int $account_id default current user
	 * @param ?int& $time on return used test-time so far
	 * @param ?int& $video_time on return position of video when paused/stopped
	 * @return ?Api\DateTime|false null: can be started, false: already stoped or start-time of running test
	 */
	public static function testStarted($video_id, $account_id=null, &$time=null, &$video_time=null)
	{
		if (($data = self::$db->select(self::ANSWERS_TABLE, '*', [
			'video_id'   => $video_id,
			'account_id' => $account_id ?: $GLOBALS['egw_info']['user']['account_id'],
			'overlay_id' => 0,
		], __LINE__, __FILE__, false, '', self::APP)->fetch(Api\Db::FETCH_ASSOC)))
		{
			$json = json_decode($data['answer_data'], true) ?: ['time' => 0];
			$time = $json['time'];
			// test stopped
			switch ((string)$data['answer_score'])
			{
				case '0':	// paused --> can be started again
					$video_time = $json['video_time'];
					return null;
				case '1':	// stopped --> can NOT be started again
					$video_time = $json['video_time'];
					return false;
			}
			$started = Api\DateTime::server2user($data['answer_started'], 'object');
			$updated = Api\DateTime::server2user($data['answer_modified'], 'object');
			$time += time() - $updated->getTimestamp();
		}
		return $started ?: null;
	}

	/**
	 * Start a test / record current time as start-time
	 *
	 * @param int $video_id
	 * @param int $course_id
	 * @param ?int $account_id
	 * @param bool $ignore_started false: throws exception if already started, true: resets start-time to now, if already started
	 * @param ?int& $video_time on return time of video when test was paused/stopped
	 * @return int answer_id
	 * @throws Api\Exception\WrongParameter test already started
	 */
	public static function testStart($video_id, $course_id, $account_id=null, $ignore_started=false, &$video_time=null)
	{
		if (!isset($account_id)) $account_id = $GLOBALS['egw_info']['user']['account_id'];
		if (self::testStarted($video_id, $account_id, $time, $video_time) && !$ignore_started)
		{
			throw new Api\Exception\WrongParameter("Test #$video_id already started for user #$account_id");
		}
		self::$db->insert(self::ANSWERS_TABLE, ($time ? [] : [
			'answer_created' => new Api\DateTime('now'),
		])+[
			'answer_modified' => new Api\DateTime('now'),
			'answer_modifier' => $GLOBALS['egw_info']['user']['account_id'],
			'answer_data'    => json_encode(['time' => $time ?? 0, 'video_time' => $video_time]),
			'answer_score'   => null,	// running
		], [
			'course_id'  => $course_id,
			'video_id'   => $video_id,
			'account_id' => $account_id ?: $GLOBALS['egw_info']['user']['account_id'],
			'overlay_id' => 0,
		], __LINE__, __FILE__, self::APP);

		return self::$db->get_last_insert_id(self::ANSWERS_TABLE, 'answer_id');
	}

	/**
	 * Stop running test - can't be restarted once stopped
	 *
	 * @param int $video_id
	 * @param int $course_id
	 * @param bool $stop=true false: pause, true: stop
	 * @param ?int $video_time
	 * @param ?int $account_id
	 * @throws Api\Exception\WrongParameter if test is not running
	 */
	public static function testStop(int $video_id, $course_id, $stop=true, $video_time=null, $account_id=null)
	{
		if (!self::testStarted($video_id, $account_id, $time))
		{
			throw new Api\Exception\WrongParameter("Test not running!");
		}
		self::$db->update(self::ANSWERS_TABLE, [
			'answer_modified' => new Api\DateTime('now'),
			'answer_modifier' => $GLOBALS['egw_info']['user']['account_id'],
			'answer_data' => json_encode(['time' => $time, 'video_time' => (int)$video_time]),
			'answer_score' => (int)$stop,
		], [
			'course_id'  => $course_id,
			'video_id'   => $video_id,
			'account_id' => $account_id ?: $GLOBALS['egw_info']['user']['account_id'],
			'overlay_id' => 0,
		], __LINE__, __FILE__, self::APP);
	}

	/**
	 * Get question-/overlay-data of all questions of a video/material or a whole course
	 *
	 * @param int $course_id
	 * @param ?int $video_id
	 * @return array[] array of json_decoded overlay_data, values for keys "max_score", "answers", ...
	 * @throws Api\Db\Exception
	 * @throws Api\Db\Exception\InvalidSql
	 */
	public static function questionData(int $course_id, ?int $video_id=null)
	{
		$questions = [];
		foreach(self::$db->select(self::TABLE, 'overlay_id,overlay_data', [
			'course_id' => self::getParentToo($course_id),
			"overlay_type LIKE 'smallpart-question-%'",
			'overlay_data IS NOT NULL',
		]+($video_id ? ['video_id IN (0,'.(int)$video_id.')'] : []),
			__LINE__, __FILE__, false, '', self::APP) as $question)
		{
			$questions[$question['overlay_id']] = json_decode($question['overlay_data'], true);
		}
		return $questions;
	}

	const ADDRESSBOOK_TABLE = 'egw_addressbook';

	/**
	 * Fetch scores to display
	 *
	 * @param array $query
	 * @param array& $rows =null
	 * @param array& $readonlys =null
	 * @return int total number of rows
	 */
	public static function get_scores($query, array &$rows = null, array &$readonlys = null)
	{
		if (!preg_match('/^[a-z0-9_]+$/i', $query['order']) || !in_array(strtolower($query['sort']), ['asc','desc']))
		{
			$query['order'] = 'score';
			$query['sort'] = 'DESC';
		}
		if ($query['order'] === 'rank')
		{
			$query['order'] = 'score';
			$query['sort'] = $query['sort'] === 'ASC' ? 'DESC' : 'ASC';
		}
		$scorable_questions = implode(',', array_keys(array_filter(self::questionData($query['col_filter']['course_id'], $query['col_filter']['video_id']), static function($data)
		{
			return !empty($data['max_score']);
		}))) ?: '0';
		$rows = [];
		foreach(self::$db->select(So::PARTICIPANT_TABLE, [
			So::PARTICIPANT_TABLE.'.account_id AS account_id',
			self::ANSWERS_TABLE.'.video_id AS video_id',
			self::ANSWERS_TABLE.'.course_id AS course_id',
			self::$db->concat(So::PARTICIPANT_TABLE.'.account_id', "'::'", self::ANSWERS_TABLE.'.video_id').' AS id',
			'n_family AS n_family', 'n_given AS n_given',
			'SUM(CASE overlay_id WHEN 0 THEN 0 ELSE answer_score END) AS score',
			'MIN(answer_created) AS started',
			'MAX(answer_modified) AS finished',
			'COUNT(CASE WHEN overlay_id > 0 THEN overlay_id ELSE null END) AS answered',
			'COUNT(CASE WHEN overlay_id > 0 AND answer_score > 0 THEN overlay_id ELSE null END) AS answered_scored',
			'COUNT(CASE WHEN overlay_id > 0 AND answer_score IS NOT NULL THEN overlay_id ELSE null END) AS scored',
			"COUNT(CASE WHEN overlay_id IN ($scorable_questions) THEN overlay_id ELSE null END) AS counting",
			'CASE WHEN COUNT(CASE WHEN overlay_id > 0 THEN overlay_id ELSE null END) > 0 THEN '.
				'100.0 * COUNT(CASE WHEN overlay_id > 0 AND answer_score IS NOT NULL THEN overlay_id ELSE null END) / '.
				'COUNT(CASE WHEN overlay_id > 0 THEN overlay_id ELSE null END) ELSE NULL END AS assessed',
		], self::$db->expression(So::PARTICIPANT_TABLE, So::PARTICIPANT_TABLE.'.', [
				'course_id' => $query['col_filter']['course_id'],
			]).(empty($query['col_filter']['video_id']) ? '' : ' AND ('.self::$db->expression(self::ANSWERS_TABLE, self::ANSWERS_TABLE.'.', [
				'video_id'  => $query['col_filter']['video_id'],
			]).' OR video_id IS NULL)'.(empty($query['col_filter']['account_id'])?'':' AND '.self::$db->expression(So::PARTICIPANT_TABLE, So::PARTICIPANT_TABLE.'.', [
				'account_id' => $query['col_filter']['account_id'],
			]))), __LINE__, __FILE__, 0 /*(int)$query[start]*/,
			' GROUP BY '.(empty($query['col_filter']['video_id']) ? self::ANSWERS_TABLE.'.video_id,' : '').
				So::PARTICIPANT_TABLE.'.account_id,'.self::ADDRESSBOOK_TABLE.'.n_family,'.self::ADDRESSBOOK_TABLE.'.n_given'.
			($query['order'] ? ' ORDER BY '.$query['order'].' '.$query['sort'] : ''),
			self::APP, -1 /*=all $query['num_rows']*/,
			' JOIN '.self::ADDRESSBOOK_TABLE.' ON '.So::PARTICIPANT_TABLE.'.account_id='.self::ADDRESSBOOK_TABLE.'.account_id'.
			' LEFT JOIN '.self::ANSWERS_TABLE.' ON '.So::PARTICIPANT_TABLE.'.account_id='.self::ANSWERS_TABLE.'.account_id'.
				' AND '.So::PARTICIPANT_TABLE.'.course_id='.self::ANSWERS_TABLE.'.course_id'
		) as $row)
		{
			foreach(['started', 'finished'] as $time)
			{
				if (!empty($row[$time])) $row[$time] = Api\DateTime::server2user($row[$time]);
			}
			$rows[] = $row;
		}
		// generate rank
		uasort($rows, static function($a, $b)
		{
			return $b['score'] <=> $a['score'] ?: strcasecmp($a['n_family'], $b['n_family']) ?: strcasecmp($a['n_given'], $b['n_given']);
		});
		$last_rank = $rank = 1; $last_score = null;
		foreach($rows as &$row)
		{
			if (!isset($last_score) || $last_score === $row['score'])
			{
				$row['rank'] = $last_rank;
			}
			else
			{
				$last_rank = $row['rank'] = $rank;
			}
			$last_score = $row['score'];
			++$rank;
		}
		ksort($rows);

		return count($rows);
	}

	const FAVORITE_SYMBOLE = "\u{272C}";

	/**
	 * Fetch statistic by material
	 *
	 * @param array $query
	 * @param array& $rows =null
	 * @param array& $readonlys =null
	 * @param ?string $implode ="\n" implode not aggregated values, null to return them as array plus answers to text- and rating-questions
	 * @return int total number of rows
	 * @noinspection UnsupportedStringOffsetOperationsInspection
	 */
	public static function get_statistic(array $query, array &$rows = null, array &$readonlys = null, ?string $implode="\n", bool $show_linked=true)
	{
		if (!preg_match('/^[a-z0-9_]+$/i', $query['order']??'') || !in_array(strtolower($query['sort']??'asc'), ['asc','desc']))
		{
			$query['order'] = 'rank';
			$query['sort'] = 'ASC';
		}
		self::get_scores([
			'order' => 'video_id',
			'sort' => 'ASC',
			'col_filter' => [
				'course_id' => $query['col_filter']['course_id'],
			],
		], $scores, $readonlys);
		$video_scores = $account_ids = [];
		foreach($scores as $score)
		{
			if (empty($score['video_id'])) continue;
			$video_scores[$score['video_id']][$score['account_id']] = $score;
			$account_ids[$score['account_id']] = $score['account_id'];
		}
		$videos = Bo::getInstance()->listVideos(['course_id' => $query['col_filter']['course_id']], true);
		// add all not yet rated videos
		foreach($videos as $video_id => $video)
		{
			if (!isset($video_scores[$video_id]))
			{
				$video_scores[$video_id] = null;
			}
		}
		// should we show statistics of linked videos --> query the statistics of their courses
		if ($show_linked)
		{
			try
			{
				$linked_statistics = self::get_linked_statistics(array_keys($videos), $query);
			}
			catch(\Exception $e) {
				Api\Json\Response::get()->message(lang('Could not query linked statistics').': '.$e->getMessage(), 'error');
			}
		}
		// get favorites, text- and rating-questions
		$favorites = $text_questions = $text_answers = [];
		foreach(self::$db->select(self::ANSWERS_TABLE, '*,'.self::ANSWERS_TABLE.'.video_id AS video_id,'.self::ANSWERS_TABLE.'.course_id AS course_id', [
			self::ANSWERS_TABLE.'.course_id='.(int)$query['col_filter']['course_id'],
			$implode ? self::TABLE.".overlay_type IN ('smallpart-question-favorite','smallpart-question-rating')" :
				self::TABLE.".overlay_type IN ('smallpart-question-favorite','smallpart-question-rating','smallpart-question-text')",
		], __LINE__, __FILE__, false, '', self::APP, false,
			'JOIN '.self::TABLE.' ON '.self::TABLE.'.overlay_id='.self::ANSWERS_TABLE.'.overlay_id') as $row)
		{
			if ($row['overlay_type'] === 'smallpart-question-favorite')
			{
				$favorites[$row['video_id']][$row['account_id']] = true;
			}
			else
			{
				$row['answer_data'] = json_decode($row['answer_data'], true);
				$row['overlay_data'] = json_decode($row['overlay_data'], true);
				if (!isset($text_questions[$row['overlay_id']]))
				{
					$text_questions[$row['overlay_id']] = explode("\n", html_entity_decode(strip_tags($row['overlay_data']['data']??''), ENT_QUOTES, 'utf-8'))[0];
				}
				switch($row['overlay_type'])
				{
					case 'smallpart-question-text':
						$text_answers[$row['video_id']][$row['account_id']][$row['overlay_id']] = $row['answer_data']['answer']??'';
						break;

					case 'smallpart-question-rating':
						if (!empty($row['answer_data']['answer']))
						{
							$text_answers[$row['video_id']][$row['account_id']][$row['overlay_id']] = $row['answer_data']['answer_label'] ??
								current(array_filter($row['overlay_data']['answers'], static function($answer) use ($row)
								{
									return $answer['id'] === $row['answer_data']['answer'];
								}))['answer'] ?? $row['answer_data']['answer'];

							if (!empty($row['answer_data']['rating_remark']))
							{
								$text_answers[$row['video_id']][$row['account_id']][$row['overlay_id']] .= "\n".$row['answer_data']['rating_remark'];
							}
						}
						break;
				}
			}
		}
		$lines = [
			'account' => static function($account_id) {
				return Api\Accounts::id2name($account_id, 'account_fullname'); },
			'score' => static function($account_id, $account_scores) {
				return $account_scores[$account_id]['score']; },
			'score_percent' => static function($account_id, $account_scores) {
				if (!$account_scores[$account_id]['counting'])
				{
					return lang('not counting');   // answered only questions without scoring
				}
				$percent = 100.0 * $account_scores[$account_id]['score'] /
					self::questionsPerVideo($account_scores[$account_id]['course_id'], $account_scores[$account_id]['video_id'], 'sum_scores');
				return self::colorPercent($percent, number_format($percent, 1));
			},
			'favorite' => static function($account_id, $account_scores) use ($favorites) {
				return !empty($favorites[$account_scores[$account_id]['video_id']][$account_id]) ? self::FAVORITE_SYMBOLE : ''; },
			'started' => static function($account_id, $account_scores) {
				return Api\DateTime::to($account_scores[$account_id]['started']); },
			'finished' => static function($account_id, $account_scores) {
				return Api\DateTime::to($account_scores[$account_id]['finished']); },
			'answered' => static function($account_id, $account_scores) {
				return $account_scores[$account_id]['answered']; },
			'answered_scored' => static function($account_id, $account_scores) {
				$questions_with_score = self::questionsPerVideo($account_scores[$account_id]['course_id'], $account_scores[$account_id]['video_id'], 'questions_with_score');
				return !$questions_with_score ? '' :
					number_format(100.0 * $account_scores[$account_id]['answered_scored'] / $questions_with_score, 1);
			},
			'scored' => static function($account_id, $account_scores) {
				return $account_scores[$account_id]['scored']; },
			'assessed' => static function($account_id, $account_scores) {
				return number_format($account_scores[$account_id]['assessed'], 1); },
		]+array_map(static function($id) use ($text_answers) {
			return static function($account_id, $account_scores) use ($id, $text_answers) {
				return $text_answers[$account_scores[$account_id]['video_id']][$account_id][$id] ?? '';
			};
		}, array_flip($text_questions));
		$rows = [];
		foreach($video_scores as $video_id => $account_scores)
		{
			$row = [
				'rank' => 0,
				'video_name' => $videos[$video_id],
				'video_id' => $video_id,
				'course_id' => $query['col_filter']['course_id'],
				'sum' => 0,
				'average_sum' => 0,
			]+array_map(static function() { return []; }, $lines);
			if (is_array($account_scores))
			{
				$counting = 0;
				foreach($account_scores as $score)
				{
					$row['sum'] += $score['score'];
					// answered at least one questions with scoring
					if ($score['counting'])
					{
						$counting++;
					}
				}
				if ($counting)
				{
					$row['average_sum'] = number_format($row['sum'] / $counting, 1);
					$percent = number_format(100.0*$row['average_sum']/self::questionsPerVideo(current($account_scores)['course_id'], $video_id, 'sum_scores'), 1);
					$row['percent_average_sum'] = self::colorPercent($percent, $percent);
				}
				// account-specific pre-formatted columns
				foreach($account_ids as $account_id)
				{
					if (isset($account_scores[$account_id]))
					{
						foreach($lines as $line => $method)
						{
							$row[$line][] = $method($account_id, $account_scores);
						}
					}
				}
			}
			// merge statistics from linked videos, if existing
			$num_rows = count($row['account'] ?? ['']) + 1; // +1 to have an empty line between categories
			foreach($linked_statistics[$row['video_id']] ?? [] as $linked_row)
			{
				foreach(array_merge(array_keys($lines), ['percent_average_sum']) as $line)
				{
					$row[$line] = array_pad((array)$row[$line], $num_rows, in_array($line, ['favorite','account']) ?
						'' : '<span>&nbsp;</span>');

					switch($line)
					{
						case 'percent_average_sum':
							$extra_line = '<span>'.$linked_row['rank'].'. '.Api\Link::title('smallpart', $linked_row['course_id']).'</span>';
							$row[$line] = array_merge($row[$line], array_pad([$linked_row[$line], $extra_line], count($linked_row['account']), '<span>&nbsp;</span>'));
							break;
						default:
							$row[$line] = array_merge($row[$line], (array)$linked_row[$line]);
							break;
					}
				}
			}
			if ($implode)
			{
				foreach(array_merge(array_keys($lines), ['percent_average_sum']) as $line)
				{
					$row[$line] = implode("\n", (array)$row[$line]);
				}
			}
			$rows[] = $row;
		}
		// generate rank
		usort($rows, static function($a, $b)
		{
			return $b['average_sum'] <=> $a['average_sum'] ?: strcasecmp($a['video_name'], $b['video_name']);
		});
		$last_rank = $rank = 1; $last_score = null;
		foreach($rows as &$row)
		{
			if (!isset($last_score) || $last_score === $row['average_sum'])
			{
				$row['rank'] = $last_rank;
			}
			else
			{
				$last_rank = $row['rank'] = $rank;
			}
			$last_score = $row['average_sum'];
			++$rank;

			// for linked statistics, add the current rank and category below percent-average-sum value
			if (!empty($linked_statistics))
			{
				$row['percent_average_sum'] = preg_replace("#^(<span.*?</span>)(\n<span>&nbsp;</span>)?#",
					'$1'."\n<span>".$row['rank'].'. '.Api\Link::title('smallpart', $row['course_id']).'</span>',
					$row['percent_average_sum']);
			}
		}
		// sort as requested
		usort($rows, static function($a, $b) use ($query)
		{
			if ($query['sort'] === 'ASC')
			{
				return $a[$query['order']] <=> $b[$query['order']] ?: strcasecmp($a['video_name'], $b['video_name']);
			}
			return $b[$query['order']] <=> $a[$query['order']] ?: strcasecmp($a['video_name'], $b['video_name']);
		});
		return count($rows);
	}

	/**
	 * Get statistic rows of linked videos
	 *
	 * @param int[] $videos video_id
	 * @param array $query
	 * @return array[] video_id => [linked_video_id => array with statistic-columns]
	 * @throws Api\Db\Exception
	 * @throws Api\Db\Exception\InvalidSql
	 */
	public static function get_linked_statistics(array $videos, array $query)
	{
		if (!($linked_videos = Api\Link::get_links_multiple('smallpart-video', $videos, false, 'smallpart-video')))
		{
			return [];
		}
		$linked_courses = iterator_to_array(self::$db->select(So::VIDEO_TABLE, 'DISTINCT course_id', [
			'video_id' => array_unique(array_merge(...array_values($linked_videos))),
		], __LINE__, __FILE__, false, '', self::APP));
		$linked_statistics = [];
		foreach(array_merge_recursive(...$linked_courses)['course_id'] ?? [] as $linked_course_id)
		{
			$linked_statistics[$linked_course_id] = [];
			$query['col_filter']['course_id'] = $linked_course_id;
			self::get_statistic($query, $linked_statistics[$linked_course_id], $dummy, null, false);
		}
		if (!$linked_statistics)
		{
			return [];
		}
		// reverse linked_videos (video_id => linked_video_ids)
		$reverse_linked_videos = [];
		foreach($linked_videos as $video_id => $linked_video_ids)
		{
			foreach($linked_video_ids as $linked_video_id)
			{
				$reverse_linked_videos[$linked_video_id][] = $video_id;
			}
		}
		// now find statistic-rows of linked videos
		$ret = [];
		foreach(array_merge(...array_values($linked_statistics)) as $row)
		{
			foreach($reverse_linked_videos[$row['video_id']] ?? [] as $linked_id)
			{
				$ret[$linked_id][$row['video_id']] = $row;
			}
		}
		return $ret;
	}

	/**
	 * Return html fragment coloring $content from green=100% to red=0%
	 *
	 * @param float $percent
	 * @param string $content
	 * @return string
	 */
	public static function colorPercent(float $percent, string $content): string
	{
		$lightness = $percent <= 80 ? 50 : 50-(int)($percent-80); // 100% --> 30%, from 80% -> 50%
		return "<span style='background-color: hsl(".number_format(15+$percent*105/100, 1)." 100% $lightness%)'>$content</span>";
	}

	/**
	 * Get number of questions (with score) per video
	 *
	 * @param int $course_id
	 * @param int $video_id
	 * @param string $what "questions", "questions_with_score", "sum_scores"
	 * @return int depending on $what
	 * @throws Api\Db\Exception
	 * @throws Api\Db\Exception\InvalidSql
	 */
	public static function questionsPerVideo(int $course_id, int $video_id, string $what)
	{
		static $course=null;
		static $videos=[];
		if ($course !== $course_id)
		{
			$videos = [];
			$course = $course_id;
			foreach(self::$db->select(self::TABLE, '*', [
				'course_id' => self::getParentToo($course_id),
				"overlay_type LIKE 'smallpart-question-%'",
			], __LINE__, __FILE__, false, '', self::APP) as $row)
			{
				$row += json_decode($row['overlay_data'] ?? '[]', true);
				$videos[$row['video_id']]['questions'] += 1;
				if (empty($row['max_score']))
				{
					$row['max_score'] = 0;
					foreach($row['answers'] ?? [] as $answer)
					{
						if ($answer['score'] > $row['max_score'])
						{
							$row['max_score'] = (float)$answer['score'];
						}
					}
				}
				$videos[$row['video_id']]['sum_scores'] += $row['max_score'];
				$videos[$row['video_id']]['questions_with_score'] += (int)($row['max_score'] > 0);
			}
		}
		return ($videos[0][$what]??0) + ($videos[$video_id][$what]??0);
	}

	/**
	 * Generate a short question score summary of a test: X% answered with Y/Z points
	 *
	 * @param int|array $video video_id or full video array
	 * @return string
	 */
	public static function summary($video)
	{
		if (!is_array($video))
		{
			$video = Bo::getInstance()->readVideo($video);
		}
		if (!$video || !($video['video_test_duration'] || $video['video_test_display'] == Bo::TEST_DISPLAY_LIST))
		{
			return '';
		}
		if (!self::get_scores(['col_filter' => [
				'course_id' => $video['course_id'],
				'video_id' => $video['video_id'],
				'account_id' => $GLOBALS['egw_info']['user']['account_id'],
			]], $rows))
		{
			return '';
		}
		$num_questions = self::get_rows(['col_filter' => [
			'course_id' => $video['course_id'],
			'video_id'=>$video['video_id'],
			"overlay_type LIKE 'smallpart-question-%'",
			'account_id' => $GLOBALS['egw_info']['user']['account_id'],
		]], $questions);
		$total_score = array_sum(array_map(static function($question)
		{
			return (float)($question['max_score'] ?? null ?: max(array_map(static function(array $answer)
			{
				return (float)($answer['score'] ?? 0);
			}, $question['answers'] ?? []) ?: [0]));
		}, $questions ?: []));
		$num_questions_with_score = count(array_filter($questions, function($question)
		{
			return $question['max_score'] > 0;
		}));
		$answered_with_score = count(array_filter($questions, function($question)
		{
			return $question['max_score'] > 0 && !empty($question['answer_id']);
		}));

		//$summary = $rows[0]['answered'] ? number_format(100.0*$rows[0]['answered']/$num_questions, 0).'%' : '';
		$summary = $rows[0]['answered'] && $num_questions_with_score ?
			number_format(100.0*$answered_with_score/$num_questions_with_score, 0).'%' : '';

		if (!($safe_to_show_scores = Bo::getInstance()->isStaff($video['course_id'])))
		{
			$safe_to_show_scores = !array_filter($questions, static function($question)
			{
				return $question['overlay_type'] !== 'smallpart-question-rating' && !empty($question['max_score']);
			});
		}
		if ($rows[0]['answered'] && $safe_to_show_scores)
		{
			$percent = number_format(100.0*$rows[0]['score']/$total_score, 1);
			$summary .= ($summary ? "\u{00A0}" : '').self::colorPercent($percent, $rows[0]['score'].'/'.$total_score.' ('.$percent.')');
		}
		// mark favorite with an asterisk
		if (array_filter($questions, static function($question)
		{
			return $question['overlay_type'] === 'smallpart-question-favorite' && !empty($question['answer_data']['answer']);
		}))
		{
			$summary .= "\u{00A0}".self::FAVORITE_SYMBOLE;
		}
		return $summary;
	}

	/**
	 * Excempt a question from scoring
	 *
	 * (Un)Sets egw_smallpart_overlay.overlay_data.exempt and
	 * egw_smallpart_answers.answer_data.exempt_score = answer_score, answer_score = null,
	 * so the exempt can be reverted without the need to re-assess the answers / loosing data.
	 *
	 * @param int|array $overlay_id one or more overlay_id(s)
	 * @param bool $exempt=true true: exempt, false: re-add
	 * @return int number of changed questions and answers
	 */
	public static function exemptQuestion($overlay_id, $exempt=true)
	{
		if (!($elements = self::read(['overlay_id' => $overlay_id])) || !$elements['total'])
		{
			throw new Api\Exception\NotFound();
		}
		$changed = 0;
		$now = new Api\DateTime('now');
		foreach($elements['elements'] as $question)
		{
			if (!empty($question['exempt']) === $exempt) continue;	// nothing to change

			foreach(self::$db->select(self::ANSWERS_TABLE, '*', ['overlay_id' => $question['overlay_id']],
				__LINE__, __FILE__, false, '', self::APP) as $answer)
			{
				$answer['answer_data'] = json_decode($answer['answer_data'], true);
				if ($exempt)
				{
					$answer['answer_data']['exempt'] = $answer['answer_score'];
					$answer['answer_score'] = 0;
				}
				else
				{
					$answer['answer_score'] = $answer['answer_data']['exempt'];
					unset($answer['answer_data']['exempt']);
				}
				self::$db->update(self::ANSWERS_TABLE, [
					'answer_data' => json_encode($answer['answer_data'], JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES),
					'answer_score' => $answer['answer_score'],
					'answer_modified' => $now,
					'answer_modifier' => $GLOBALS['egw_info']['user']['account_id'],
				], ['answer_id' => $answer['answer_id']], __LINE__, __FILE__, self::APP);
				++$changed;
			}
			$question['exempt'] = $exempt;
			self::write($question);
			++$changed;
		}
		return $changed;
	}

	/**
	 * Get course parent
	 *
	 * @param int $course_id
	 * @param bool $return_only_parent false: return $course_id plus parent if there's one, false: return only parent
	 * @return null|int|int[]
	 * @throws Api\Db\Exception
	 * @throws Api\Db\Exception\InvalidSql
	 */
	public static function getParentToo(int $course_id, bool $return_only_parent=false)
	{
		static $parents = [];
		if (!array_key_exists($course_id, $parents))
		{
			$parents[$course_id] = self::$db->select(So::COURSE_TABLE, 'course_parent', ['course_id' => $course_id],
				__LINE__, __FILE__, false, '', self::APP)->fetchColumn();
		}
		if ($return_only_parent)
		{
			return $parents[$course_id];
		}
		return $parents[$course_id] ? [$parents[$course_id], $course_id] : $course_id;
	}

	/**
	 * Initialise
	 */
	public static function init()
	{
		self::$db = $GLOBALS['egw']->db;
	}
}

Overlay::init();