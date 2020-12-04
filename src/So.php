<?php
/**
 * EGroupware - SmallParT - storage layer
 *
 * @link https://www.egroupware.org
 * @package smallpart
 * @subpackage storage
 * @license https://spdx.org/licenses/AGPL-3.0-or-later.html GNU Affero General Public License v3.0 or later
 */

namespace EGroupware\SmallParT;

use EGroupware\Api;

/**
 * SmallParT - storage layer
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
	const WATCHED_TABLE = 'egw_smallpart_watched';

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

		// filter by an account_id --> show only subscribed courses
		if (!empty($filter['account_id']))
		{
			$filter[] = $this->db->expression(self::PARTICIPANT_TABLE, ['account_id' => $filter['account_id']]);
			$join = 'JOIN '.self::PARTICIPANT_TABLE.' ON '.self::PARTICIPANT_TABLE.'.course_id='.self::COURSE_TABLE.'.course_id';
		}
		// add a subscribed colum by default or if requested and not account_id filter
		elseif (!$extra_cols || ($subscribed = array_search('subscribed', $extra_cols, true)) !== false)
		{
			if ($extra_cols && $subscribed !== false) unset($extra_cols[$subscribed]);
			$extra_cols[] = 'subscribed.account_id IS NOT NULL AS subscribed';
			$join .= ' LEFT JOIN '.self::PARTICIPANT_TABLE.' subscribed ON '.self::COURSE_TABLE.'.course_id=subscribed.course_id'.
				' AND subscribed.account_id='.(int)$this->user;
		}
		unset($filter['account_id']);

		$this->aclFilter($filter);

		return parent::search($criteria, $only_keys, $order_by, $extra_cols, $wildcard, $empty, $op, $start, $filter, $join, $need_full_no_count);
	}

	/**
	 * Expand course_owner / ACL filter to course_owner OR course_org
	 *
	 * @param array $filter grants as values for key "acl"
	 */
	protected function aclFilter(array &$filter)
	{
		//
		if (isset($filter['acl']))
		{
			$to_or = [];
			// owner only needs to take users into account (and is usually empty for students, saves slow OR query)
			if (($users = array_filter((array)$filter['acl'], function($account_id) { return $account_id > 0; })))
			{
				$to_or[] = $this->db->expression(self::COURSE_TABLE, ['course_owner' => $users]);
			}
			$to_or[] = $this->db->expression(self::COURSE_TABLE, ['course_org' => $filter['acl']]);
			$filter[] = '('.implode(' OR ', $to_or).')';
			unset($filter['acl']);
		}
	}

	/**
	 * reads row matched by key and puts all cols in the data array
	 *
	 * @param array $keys array with keys in form internalName => value, may be a scalar value if only one key
	 * @param string|array $extra_cols ='' string or array of strings to be added to the SELECT, eg. "count(*) as num"
	 * @param string $join ='' sql to do a join, added as is after the table-name, eg. ", table2 WHERE x=y" or
	 * @return array|boolean data if row could be retrived else False
	 */
	function read($keys,$extra_cols='',$join='')
	{
		$this->aclFilter($keys);

		return parent::read($keys, $extra_cols, $join);
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

		if (($data = $json ? json_decode($json, true) : null) &&
			// convert old format, can be removed soon
			isset($data['KursID']))
		{
			$data = [
				'course_id' => $data['KursID'],
				'video_id'  => substr($data['VideoElementId'], 7),
			];
		}
		return $data;
	}

	/**
	 * Set last course, video and other data of a user
	 *
	 * @param array $data values for keys "course_id", "video_id", ...
	 * @param int $account_id =null default $this->user
	 * @return true on success
	 * @throws Api\Exception\WrongParameter
	 */
	public function setLastVideo(array $data=null, $account_id=null)
	{
		if (empty($data) || empty($data['course_id']))
		{
			return $this->db->delete(self::LASTVIDEO_TABLE, [
				'account_id' => $account_id ?: $this->user,
			], __LINE__, __FILE__, self::APPNAME);
		}

		return $this->db->insert(self::LASTVIDEO_TABLE, [
			'last_data' => json_encode($data),
		], [
			'account_id' => $account_id ?: $this->user,
		], __LINE__, __FILE__, self::APPNAME);
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
	 * @return array account_id => array of values for keys "account_id", "primary_group" and "comments" (number of comments)
	 */
	function participants($course_id)
	{
		$participants = [];
		foreach($this->db->select(self::PARTICIPANT_TABLE, self::PARTICIPANT_TABLE.'.account_id,COUNT(comment_id) AS comments',
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
			__LINE__, __FILE__, false, 'ORDER BY video_id', self::APPNAME, 0) as $video)
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

	const JSON_OPTIONS = JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES;

	/**
	 * Read a single comment
	 *
	 * @param int $comment_id
	 * @return array|null
	 */
	public function readComment($comment_id)
	{
		return $this->listComments(['comment_id' => $comment_id])[$comment_id];
	}

	/**
	 * List comments of given video chronological
	 *
	 * @param array $where =[] further query parts eg.
	 * @param ?string $order_by optional order by clause, default 'video_id, comment_starttime, comment_id'
	 * @return array comment_id => array of data pairs
	 */
	public function listComments( array $where=[], $order_by='video_id, comment_starttime, comment_id')
	{
		if (!array_key_exists('comment_deleted', $where) && !array_key_exists('comment_id', $where))
		{
			$where['comment_deleted'] = 0;
		}
		$comments = [];
		foreach($this->db->select(self::COMMENTS_TABLE, '*', $where,
			__LINE__, __FILE__, false, 'ORDER BY '.$order_by, self::APPNAME, 0) as $comment)
		{
			$comment['comment_added'] = json_decode($comment['comment_added'], true) ?: [$comment['comment_added']];
			$comment['comment_marked'] = $comment['comment_marked'] ? json_decode($comment['comment_marked']) : null;
			$comment['comment_history'] = $comment['comment_history'] ? json_decode($comment['comment_history']) : null;
			foreach(['comment_created','comment_updated'] as $col)
			{
				if (isset($comment[$col])) $comment[$col] = Api\DateTime::server2user($comment[$col],'DateTime');
			}
			$comments[$comment['comment_id']] = $comment;
		}
		return $comments;
	}

	/**
	 * Save a comment
	 *
	 * @param array $comment values for keys "course_id", "video_id", "account_id", ...
	 * @return int comment_id
	 * @throws Api\Exception\WrongParameter
	 */
	public function saveComment(array $comment)
	{
		if (empty($comment['course_id']) || empty($comment['video_id']) || empty($comment['account_id']))
		{
			throw new Api\Exception\WrongParameter("Missing course_id, video_id or account_id values");
		}
		if (isset($comment['comment_created']))
		{
			$comment['comment_created'] = Api\DateTime::user2server($comment['comment_created']);
		}
		$comment['comment_updated'] = Api\DateTime::user2server('now');
		$comment['comment_added'] = json_encode((array)$comment['comment_added'], self::JSON_OPTIONS);
		$comment['comment_marked'] = empty($comment['comment_marked']) ? null :
			json_encode($comment['comment_marked'], self::JSON_OPTIONS);
		$comment['comment_history'] = empty($comment['comment_history']) ? null :
			json_encode($comment['comment_history'], self::JSON_OPTIONS);
		$this->db->insert(self::COMMENTS_TABLE, $comment, empty($comment['comment_id']) ? false : [
			'comment_id' => $comment['comment_id']
		],__LINE__, __FILE__, self::APPNAME, 0);

		return !empty($comment['comment_id']) ? $comment['comment_id'] :
			$this->db->get_last_insert_id(self::COMMENTS_TABLE, 'comment_id');
	}

	/**
	 * Delete a comment / mark it as deleted
	 *
	 * @param array $comment values for keys "course_id", "video_id", "account_id", ...
	 * @return int affected rows
	 * @throws Api\Exception\WrongParameter
	 */
	public function deleteComment($comment_id)
	{
		return $this->db->update(self::COMMENTS_TABLE, [
			'comment_deleted' => 1,
		], [
			'comment_id' => $comment_id,
		],__LINE__, __FILE__, self::APPNAME);
	}

	/**
	 * Record student watched (part of) a video
	 *
	 * @param array $data [
	 *	course_id : int
	 *	video_id  : int
	 *	position  : int|float start-position in video in sec
	 *	starttime : string|DateTime start-time
	 *	duration  : int|float duration = end- - start-position
	 *	endtime   : string|DateTime end-time
	 *	paused    : int number of times paused
	 * ]
	 * @param ?int $account_id default current user
	 * @param ?int $watch_id to update existing record
	 * @return int watch_id to update the record
	 * @throws Api\Exception\WrongParameter
	 */
	public function recordWatched($data, $account_id=null, $watch_id=null)
	{
		$this->db->insert(self::WATCHED_TABLE, [
			'course_id' => $data['course_id'],
			'video_id' => $data['video_id'],
			'watch_starttime' => Api\DateTime::user2server($data['starttime']),
			'watch_position' => round($data['position']),
			'watch_endtime' => Api\DateTime::user2server($data['endtime']),
			'watch_duration' => round($data['duration']),
			'watch_paused' => $data['paused'],
			'account_id' => $account_id ?: $this->user,
		], $watch_id ? ['watch_id' => $watch_id] : false, __LINE__, __FILE__, self::APPNAME);

		return $watch_id ?: $this->db->get_last_insert_id(self::WATCHED_TABLE, 'watch_id');
	}
}
