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
	 * @param int|array $where video_id or array with more filters
	 * @param int $offset =0 first row to return
	 * @param int $num_rows =50 number of rows to return with full data, others have data === false
	 * @param string $order_by ='overlay_start ASC'
	 * @param bool $get_rows =false true: get_rows specific behavior: allways use $num_rows and return integer as strings
	 * @param ?bool $remove_correct true: remove correct answers for sending to client-side, false: dont, null: try to determine
	 * @return array with values for keys "total" and "elements"
	 * @throws Api\Exception\NoPermission if ACL check fails
	 */
	public static function read($where, $offset=0, $num_rows=50, $order_by='overlay_start ASC', $get_rows=false, $remove_correct=null)
	{
		if (!preg_match('/^([a-z0-9_]+ (ASC|DESC),?)+$/', $order_by) || !is_int($offset) || !is_int($num_rows))
		{
			throw new \InvalidArgumentException("Invalid argument ".__METHOD__."(".json_encode($where).", $offset, $num_rows, '$order_by')");
		}
		if (!is_array($where)) $where = ['video_id' => (int)$where];

		// check ACL, if we have video_id
		if (isset($where['video_id']) && !($accessible = (new Bo())->videoAccessible($where['video_id'], $admin)))
		{
			throw new Api\Exception\NoPermission();
		}
		// for non-admins always set account_id (to read their answers)
		if (!$admin)
		{
			$where['account_id'] = $GLOBALS['egw_info']['user']['account_id'];
		}

		// do NOT show hidden/deleted questions
		if (!isset($where['overlay_type']) && !in_array("overlay_type LIKE 'smallpart-question-%"))
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
				['account_id' => $where['account_id']]);

			// make sure filters are not ambigous
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

		// add an ascending question number
		$cols .= ",(SELECT CASE WHEN ".self::TABLE.".overlay_type LIKE 'smallpart-question-%' THEN 1+COUNT(*) ELSE NULL END FROM ".
			self::TABLE.' q WHERE q.video_id='.self::TABLE.".video_id AND q.overlay_type LIKE 'smallpart-question-%' AND q.overlay_start < ".
			self::TABLE.'.overlay_start AND q.overlay_id != '.self::TABLE.'.overlay_id) AS question_n';

		if (substr($where['overlay_type'], -2) === '-%')
		{
			$where[] = 'overlay_type LIKE '.self::$db->quote($where['overlay_type']);
			unset($where['overlay_type']);
		}

		$ret = ['elements' => []];
		foreach(self::$db->select(self::TABLE, $cols ?? '*', $where, __LINE__, __FILE__, $get_rows || $offset ? $offset : false, 'ORDER BY '.$order_by, self::APP, $num_rows, $join) as $row)
		{
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
			// do it manual for all other DB
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
		}), (int)$query['start'], $query['num_rows'], $query['order']?$query['order'].' '.$query['sort']:'', true);

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
					// reintegration multiple choise answers, if we have them
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
				unset($data['answer'], $data['answer_data']['remark'], $data['answer_score']);

				if ($data['shuffle_answers']) shuffle($data['answers']);
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
			return substr($name, 0, 6) !== 'answer_';
		}, ARRAY_FILTER_USE_KEY);

		$overlay_id = $data['overlay_id'];
		$data['overlay_data'] = json_encode(array_diff_key($data, $table_def['fd']+$answer_table_def['fd']+array_flip(['account_id','courseAdmin'])));
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
		$checked = 0;
		foreach($data['answers'] ?? [] as $n => $answer)
		{
			if (!$n) unset($data['answer_score']);
			unset($data['answer_data']['answers'][$n]['score']);
			if ($data[self::ASSESSMENT_METHOD] === self::ASSESSMENT_SCORE_PER_ANSWER)
			{
				if ($answer['check'] == $answer['correct'] && $answer['score'] > 0 ||
					$answer['check'] != $answer['correct'] && $answer['score'] < 0)
				{
					$data['answer_score'] += ($data['answer_data']['answers'][$n]['score'] = $answer['score'] ?: $default_score);
				}
			}
			elseif ($data['answer_score'] !== 0.0)
			{
				$data['answer_score'] = $answer['check'] == $answer['correct'] ? $data['max_score'] : 0.0;
			}
			$data['answer_data']['answers'][$n]['check'] = $answer['check'];
			$data['answer_data']['answers'][$n]['id'] = $answer['id'];

			// check if someone tempered with client-side enforcing max_answers
			if (!empty($data['max_answers']) && $answer['check'] && ++$checked > $data['max_answers'])
			{
				throw new \InvalidArgumentException("more then $data[max_answers] answers checked!");
			}
		}
		if (!empty($data['max_score']) && $data['answer_score'] > $data['max_score'])
		{
			$data['answer_score'] = $data['max_score'];
		}
		elseif (isset($data['min_score']) && $data['min_score'] !== '' && $data['answer_score'] < $data['min_score'])
		{
			$data['answer_score'] = $data['min_score'];
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
		$data['answer_data'] = json_encode($data['answer_data']);
		self::$db->insert(self::ANSWERS_TABLE, $data, empty($answer_id) ? false : ['answer_id' => $answer_id], __LINE__, __FILE__, self::APP);

		return empty($answer_id) ? self::$db->get_last_insert_id(self::ANSWERS_TABLE, 'answer_id') : $answer_id;
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
			self::aclCheck($data['course_id'], true);

			$response->data(['deleted' => self::delete($what)]);
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
	public function types()
	{
		//Api\Cache::unsetInstance(__CLASS__, 'type');
		$types = Api\Cache::getInstance(__CLASS__, 'type', static function()
		{
			$types = [];
			foreach(scandir(EGW_SERVER_ROOT.'/smallpart/js/overlay_plugins') as $file)
			{
				if (preg_match('/^et2_smallpart_(overlay|question)_(.*)\.js$/i', $file, $matches))
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
		$bo = new Bo();

		if (!$bo->isParticipant($course_id) || $update && !$bo->isAdmin($course_id))
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
			$video_time = $json['video_time'];
			// test stopped
			switch ((string)$data['answer_score'])
			{
				case '0':	// paused --> can be started again
					return null;
				case '1':	// stopped --> can NOT be started again
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
	 * Initialise
	 */
	public static function init()
	{
		self::$db = $GLOBALS['egw']->db;
	}
}

Overlay::init();