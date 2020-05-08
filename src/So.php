<?php
/**
 * EGroupware - SmallParT - storage layer
 *
 * @link https://www.egroupware.org
 * @package smallpart
 * @subpackage storage
 * @license http://opensource.org/licenses/gpl-license.php GPL - GNU General Public License
 */

namespace EGroupware\SmallParT;

use EGroupware\Api;

/**
 * SmallParT - storage layer
 *
 *
 */
class So extends Api\Storage\Base
{
	const APPNAME = 'smallpart';
	/**
	 * Names of various tables
	 */
	const COURSE_TABLE = 'egw_smallpart_courses';
	const PARTICIPANT_TABLE = 'egw_smallpart_participants';
	const VIDEO_TABLE = 'egw_smallpart_videos';
	const COMMENTS_TABLE = 'egw_smallpart_comments';
	const LASTVIDEO_TABLE = 'egw_smallpart_lastvideo';
	const ADDRESSBOOK_TABLE = 'egw_addressbook';

	/**
	 * Current user
	 *
	 * @var int
	 */
	protected $user;

	/**
	 * Connstructor
	 *
	 * @param int $account_id =null default current user
	 * @param Api\Db|null $db =null default global DB object
	 */
	public function __construct($account_id=null, Api\Db $db=null)
	{
		$this->user = (int)($account_id ?: $GLOBALS['egw_info']['user']['account_id']);

		parent::__construct(self::APPNAME, self::COURSE_TABLE, $db, '', true);
	}

	/**
	 * Searches db for courses matching searchcriteria
	 *
	 * '*' and '?' are replaced with sql-wildcards '%' and '_'
	 *
	 * For a union-query you call search for each query with $start=='UNION' and one more with only $order_by and $start set to run the union-query.
	 *
	 * @param array|string $criteria array of key and data cols, OR string with search pattern (incl. * or ? as wildcards)
	 * @param boolean|string|array $only_keys =true True returns only keys, False returns all cols. or
	 *	comma seperated list or array of columns to return
	 * @param string $order_by ='' fieldnames + {ASC|DESC} separated by colons ',', can also contain a GROUP BY (if it contains ORDER BY)
	 * @param string|array $extra_cols ='' string or array of strings to be added to the SELECT, eg. "count(*) as num"
	 * @param string $wildcard ='' appended befor and after each criteria
	 * @param boolean $empty =false False=empty criteria are ignored in query, True=empty have to be empty in row
	 * @param string $op ='AND' defaults to 'AND', can be set to 'OR' too, then criteria's are OR'ed together
	 * @param mixed $start =false if != false, return only maxmatch rows begining with start, or array($start,$num), or 'UNION' for a part of a union query
	 * @param array $filter =null if set (!=null) col-data pairs, to be and-ed (!) into the query without wildcards
	 * @param string $join ='' sql to do a join, added as is after the table-name, eg. "JOIN table2 ON x=y" or
	 *	"LEFT JOIN table2 ON (x=y AND z=o)", Note: there's no quoting done on $join, you are responsible for it!!!
	 * @param boolean $need_full_no_count =false If true an unlimited query is run to determine the total number of rows, default false
	 * @todo return an interator instead of an array
	 * @return array|NULL array of matching rows (the row is an array of the cols) or NULL
	 */
	function &search($criteria, $only_keys=True, $order_by='', $extra_cols='', $wildcard='', $empty=False, $op='AND',
					 $start=false, $filter=null, $join='', $need_full_no_count=false)
	{
		if (is_string($extra_cols)) $extra_cols = $extra_cols ? explode(',', $extra_cols) : [];

		// add a subscribed colum by default or if requested
		if (!$extra_cols || ($subscribed = array_search('subscribed', $extra_cols, true)) !== false)
		{
			if ($extra_cols && $subscribed !== false) unset($extra_cols[$subscribed]);
			$extra_cols[] = 'subscribed.account_id IS NOT NULL AS subscribed';
			$join .= ' LEFT JOIN '.self::PARTICIPANT_TABLE.' subscribed ON '.self::COURSE_TABLE.'.course_id=subscribed.course_id'.
				' AND subscribed.account_id='.(int)$this->user;
		}
		return parent::search($criteria, $only_keys, $order_by, $extra_cols, $wildcard, $empty, $op, $start, $filter, $join, $need_full_no_count);
	}

	/**
	 * Get last course, video and other data of a user
	 *
	 * @param int $account_id =null default $this->user
	 * @return array|null array with values or null if nothing saved
	 */
	public function lastVideo($account_id=null)
	{
		$json = $this->db->select(self::LASTVIDEO_TABLE, 'last_data', [
			'account_id' => $account_id ?: $this->user,
		], __LINE__, __FILE__, false, '', self::APPNAME)->fetchColumn();

		return $json ? json_decode($json, true) : null;
	}

	/**
	 * List courses of current user
	 *
	 * @param array $where =null default videos the current user is subscribed to
	 * @return array course_id => array pairs (plus optional attribute videos of type array)
	 */
	public function listCourses($where=null)
	{
		if (empty($where))
		{
			$where = ['account_id' => $this->user];
		}
		if (!empty($where['account_id']))
		{
			$where[] = $this->db->expression(self::PARTICIPANT_TABLE, ['account_id' => $where['account_id']]);
			$join = 'JOIN '.self::PARTICIPANT_TABLE.' ON '.self::PARTICIPANT_TABLE.'.course_id='.self::COURSE_TABLE.'.course_id';
		}
		unset($where['account_id']);

		$courses = [];
		foreach($this->db->select(self::COURSE_TABLE, '*', $where, __LINE__, __FILE__, false,
			'ORDER BY course_name', self::APPNAME, 0, $join) as $row)
		{
			$courses[$row['course_id']] = $row;
		}
		return $courses;
	}

	/**
	 * Subscribe a course or unsubscribe course(s)
	 *
	 * @param int|array $course_id
	 * @param boolean $subscribe true: subscribe, false: unsubscribe
	 * @param int|array|true $account_id true: everyone
	 * @return boolean false on error
	 */
	function subscribe($course_id, $subscribe=true, $account_id=null)
	{
		if ($subscribe)
		{
			return $this->db->insert(self::PARTICIPANT_TABLE, [
				'course_id'  => $course_id,
				'account_id' => $account_id,
			], false, __LINE__, __FILE__, self::APPNAME);
		}
		return $this->db->delete(self::PARTICIPANT_TABLE, [
			'course_id'  => $course_id,
		] + ($account_id !== true ? [
			'account_id' => $account_id,
		] : []), __LINE__, __FILE__, self::APPNAME);
	}

	/**
	 * Close course(s)
	 *
	 * @param int|array $course_id
	 * @return boolean false on error
	 */
	function close($course_id)
	{
		return $this->db->update(self::COURSE_TABLE, [
				'course_closed' => 1,
			], [
				'course_id'  => $course_id,
			] , __LINE__, __FILE__, self::APPNAME);
	}

	/**
	 * Get participants of a course
	 *
	 * @param $course_id
	 * @return array with values for keys "account_id", "primary_group", "org_name" and "comments" (number of comments)
	 */
	function participants($course_id)
	{
		$participants = [];
		foreach($this->db->select(self::PARTICIPANT_TABLE, self::PARTICIPANT_TABLE.'.account_id,org_name,COUNT(comment_id) AS comments',
			$this->db->expression(self::PARTICIPANT_TABLE, self::PARTICIPANT_TABLE.'.', ['course_id' => $course_id]),
			__LINE__, __FILE__, false, 'GROUP BY '.self::PARTICIPANT_TABLE.'.account_id'.
			' ORDER BY n_given, n_family', self::APPNAME, 0,
			'JOIN '.self::ADDRESSBOOK_TABLE.' ON '.self::PARTICIPANT_TABLE.'.account_id='.self::ADDRESSBOOK_TABLE.'.account_id'.
			' LEFT JOIN '.self::COMMENTS_TABLE.' ON '.self::PARTICIPANT_TABLE.'.account_id='.self::COMMENTS_TABLE.'.account_id'.
			' AND '.self::PARTICIPANT_TABLE.'.course_id='.self::COMMENTS_TABLE.'.course_id') as $row)
		{
			$row['primary_group'] = Api\Accounts::id2name($row['account_id'], 'account_primary_group');

			$participants[] = $row;
		}
		return $participants;
	}

	/**
	 * List videos
	 *
	 * @param int|array $where video_id or query eg. ['video_id' => $ids]
	 * @return array video_id => array with data pairs
	 */
	public function listVideos($where)
	{
		$videos = [];
		foreach($this->db->select(self::VIDEO_TABLE, '*', is_array($where) ? $where : ['video_id' => $where],
			__LINE__, __FILE__, false, 'ORDER BY video_name', self::APPNAME, 0) as $video)
		{
			$videos[$video['video_id']] = $video;
		}
		return $videos;
	}

	/**
	 * Add/update a video of a course
	 *
	 * @param array $video
	 * @return int id of (new) video
	 * @throws Api\Db\Exception
	 * @throws Api\Exception\WrongParameter
	 */
	function updateVideo(array $video)
	{
		if (!empty($video['video_id']) && !$this->db->update(self::VIDEO_TABLE, $video, [
				'video_id' => $video['video_id'],
			],__LINE__, __FILE__, self::APPNAME) ||
			empty($video['video_id']) && !$this->db->insert(self::VIDEO_TABLE, $video, false,
				__LINE__, __FILE__, self::APPNAME))
		{
			throw new Api\Db\Exception(lang('Error saving video!'));
		}
		return !empty($video['video_id']) ? $video['video_id'] :
			$this->db->get_last_insert_id(self::VIDEO_TABLE, 'video_id');
	}

	/**
	 * Delete video(s) incl. comments
	 *
	 * @param int|array $video_id
	 * @return int affected rows / videos
	 */
	function deleteVideo($video_id)
	{
		$this->db->delete(self::COMMENTS_TABLE, [
			'video_id'  => $video_id,
		], __LINE__, __FILE__, self::APPNAME);

		$this->db->delete(self::VIDEO_TABLE, [
			'video_id'  => $video_id,
		], __LINE__, __FILE__, self::APPNAME);

		return $this->db->affected_rows();
	}

	/**
	 * List comments of given video chronological
	 *
	 * @param int $video_id
	 * @param array $where =[] further query parts eg.
	 * @return array comment_id => array of data pairs
	 */
	public function listComments($video_id, array $where=[])
	{
		$where['video_id'] = $video_id;
		if (!array_key_exists($where['comment_deleted']))
		{
			$where['comment_deleted'] = 0;
		}
		$comments = [];
		foreach($this->db->select(self::COMMENTS_TABLE, '*', $where,
			__LINE__, __FILE__, false, 'ORDER BY comment_starttime, comment_id', self::APPNAME, 0) as $comment)
		{
			$comments[$comment['comment_id']] = $comment;
		}
		return $comments;
	}
}
