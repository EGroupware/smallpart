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
	 * Read overlay elements of a video (no ACL check)
	 *
	 * @param int|array $where video_id or array with more filters
	 * @param int $offset =0 first row to return
	 * @param int $num_rows =50 number of rows to return with full data, others have data === false
	 * @param string $order_by ='overlay_start ASC'
	 * @param bool $get_rows =false true: get_rows specific behavior: allways use $num_rows and return integer as strings
	 * @return array with values for keys "total" and "elements"
	 */
	public static function read($where, $offset=0, $num_rows=50, $order_by='overlay_start ASC', $get_rows=false)
	{
		if (!preg_match('/^([a-z0-9_]+ (ASC|DESC),?)+$/', $order_by) || !is_int($offset) || !is_int($num_rows))
		{
			throw new \InvalidArgumentException("Invalid argument ".__METHOD__."(".json_encode($where).", $offset, $num_rows, '$order_by')");
		}
		if (!is_array($where)) $where = ['video_id' => (int)$where];

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
		unset($where['account_id']);

		if (substr($where['overlay_type'], -2) === '-%')
		{
			$where[] = 'overlay_type LIKE '.self::$db->quote($where['overlay_type']);
			unset($where['overlay_type']);
		}

		$ret = ['elements' => []];
		foreach(self::$db->select(self::TABLE, $cols ?? '*', $where, __LINE__, __FILE__, $get_rows || $offset ? $offset : false, 'ORDER BY '.$order_by, self::APP, $num_rows, $join) as $row)
		{
			$ret['elements'][] = self::db2data($row, $get_rows || !(!$offset && count($ret['elements']) > $num_rows), !$get_rows);
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
	 * @return array
	 */
	static protected function db2data(array $data, $decode_json=true, $convert_int)
	{
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

			if (!empty($data['answer_data']))
			{
				$data['answer_data'] = json_decode($data['answer_data'], true);

				// reintegration multiple choise answers
				foreach($data['answer_data']['answers'] ?: [] as $answer)
				{
					foreach($data['answers'] as &$a)
					{
						if ($a['id'] === $answer['id'])
						{
							$a['check'] = $answer['check'];
							break;
						}
					}
				}
			}
		}
		else
		{
			$data['data'] = false;
		}
		unset($data['overlay_data']);

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
			self::aclCheck($where['course_id'], false);

			Api\Json\Response::get()->data(self::read($where, $offset, $num_rows, $order_by));
		}
		catch(\Exception $e) {
			Api\Json\Response::get()->message($e->getMessage(), 'error');
		}
	}

	/**
	 * Add or update an overlay element (no ACL check)
	 *
	 * @param array $data
	 * @return int overlay_id
	 * @throws Api\Exception\WrongParameter
	 */
	public static function write(array $data)
	{
		static $table_def;
		if (!isset($table_def)) $table_def = self::$db->get_table_definitions(self::APP, self::TABLE);

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
		$data['overlay_data'] = json_encode(array_diff_key($data, $table_def['fd']));
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
		$data['answer_score'] = 0.0;
		$default_score = Questions::defaultScore($data, 10);
		foreach($data['answers'] ?? [] as $n => $answer)
		{
			if ($answer['check'] == $answer['correct'])
			{
				$data['answer_score'] += ($data['answer_data']['answers'][$n]['score'] = $answer['score'] ?: $default_score);
			}
			$data['answer_data']['answers'][$n]['check'] = $answer['check'];
			$data['answer_data']['answers'][$n]['id'] = $answer['id'];
		}
		if (!empty($data['max_score']) && $data['answer_score'] > $data['max_score'])
		{
			$data['answer_score'] = $data['max_score'];
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
	 * Delete overlay elements
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
		self::$db->delete(self::TABLE, array_intersect_key($what, ['course_id'=>1,'video_id'=>1,'overlay_id'=>1]), __LINE__, __FILE__, self::APP);

		return self::$db->affected_rows();
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
	 * Initialise
	 */
	public static function init()
	{
		self::$db = $GLOBALS['egw']->db;
	}
}

Overlay::init();