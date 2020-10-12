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
	 * @return array with values for keys "total" and "elements"
	 */
	public static function read($where, $offset=0, $num_rows=50, $order_by='overlay_start ASC')
	{
		if (!preg_match('/^([a-z0-9_]+ (ASC|DESC),?)+$/', $order_by) || !is_int($offset) || !is_int($num_rows))
		{
			throw new \InvalidArgumentException("Invalid argument ".__METHOD__."(".json_encode($where).", $offset, $num_rows, '$order_by')");
		}
		if (!is_array($where)) $where = ['video_id' => (int)$where];

		$elements = [];
		foreach(self::$db->select(self::TABLE, '*', $where, __LINE__, __FILE__, $offset ? $offset : false, 'ORDER BY '.$order_by, self::APP, $num_rows) as $row)
		{
			$elements[] = self::db2data($row, !(!$offset && count($elements) > $num_rows));
		}
		if ($offset === 0)
		{
			$total = count($elements);
		}
		else
		{
			$total = (int)self::$db->select(self::TABLE, 'COUNT(*)', $where, __LINE__, __FILE__, false, '', self::APP)->fetchCol();
		}
		return [
			'total' => $total,
			'elements' => $elements,
		];
	}

	/**
	 * All integer columns to fix their type after reading from DB
	 *
	 * @var string[]
	 */
	static $int_columns = ['overlay_id', 'video_id', 'course_id', 'overlay_start', 'overlay_player_mode', 'overlay_duration'];

	/**
	 * Convert from DB to internal representation (incl. ensuring value types)
	 *
	 * @param array $data
	 * @param boolean $decode_json true: decode overlay_data column, false: set ['data'=>false] in return array
	 * @return array
	 */
	static protected function db2data(array $data, $decode_json=true)
	{
		foreach(self::$int_columns as $col)
		{
			if ($data[$col] !== null) $data[$col] = (int)$data[$col];
		}
		if ($decode_json)
		{
			$data += json_decode($data['overlay_data'], true);
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

		if (!is_int($data['course_id']) || !is_int($data['video_id']))
		{
			throw new \InvalidArgumentException("Invalid argument ".__METHOD__."(".json_encode($data).")");
		}
		$overlay_id = $data['overlay_id'];
		$data['overlay_data'] = json_encode(array_diff_key($data, $table_def['fd']));
		self::$db->insert(self::TABLE, $data, empty($overlay_id) ? false : ['overlay_id' => $overlay_id], __LINE__, __FILE__, self::APP);

		return empty($overlay_id) ? self::$db->get_last_insert_id(self::TABLE, 'overlay_id') : $overlay_id;
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
	 * Check if current user is allowed to read or update a course
	 *
	 * @param int $course_id
	 * @param false $update
	 * @throws Api\Exception\NoPermission
	 */
	protected static function aclCheck($course_id, $update=false)
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