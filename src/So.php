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

use Assert\InvalidArgumentException;
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
	const CLMEASUREMENT_TABLE = 'egw_smallpart_clmeasurements';
	const CLMEASUREMENT_CONFIG_TABLE = 'egw_smallpart_clmeasurements_config';
	const LIVEFEEDBACK_TABLE = 'egw_smallpart_livefeedback';
	const CATEGORIES_TABLE = 'egw_smallpart_categories';

	/**
	 * Current user
	 *
	 * @var int
	 */
	protected $user;

	/**
	 * @var string[] name of timestamp columns for user-server TZ conversation
	 */
	public static $video_timestamps = ['video_date','video_published_start','video_published_end'];

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
	 * @todo return an iterator instead of an array
	 * @return array|NULL array of matching rows (the row is an array of the cols) or NULL
	 */
	function &search($criteria, $only_keys=True, $order_by='', $extra_cols='', $wildcard='', $empty=False, $op='AND',
					 $start=false, $filter=null, $join='', $need_full_no_count=false)
	{
		// never show closed courses, unless explicitly filtered by them
		if (!isset($filter['course_closed']) || $filter['course_closed'] === '') $filter['course_closed'] = '0';

		if (is_string($extra_cols)) $extra_cols = $extra_cols ? explode(',', $extra_cols) : [];

		// filter by an account_id --> show only subscribed courses
		if (!empty($filter['account_id']))
		{
			$filter[] = $this->db->expression(self::PARTICIPANT_TABLE, ['account_id' => $filter['account_id']]);
			$join = 'JOIN '.self::PARTICIPANT_TABLE.' ON '.self::PARTICIPANT_TABLE.'.course_id='.self::COURSE_TABLE.'.course_id AND '.
				self::PARTICIPANT_TABLE.'.participant_unsubscribed IS NULL';
		}
		// add a subscribed colum by default or if requested and not account_id filter
		elseif (!$extra_cols || ($subscribed = array_search('subscribed', $extra_cols, true)) !== false)
		{
			if ($extra_cols && $subscribed !== false) unset($extra_cols[$subscribed]);
			$extra_cols[] = 'subscribed.account_id IS NOT NULL AS subscribed';
			$join .= ' LEFT JOIN '.self::PARTICIPANT_TABLE.' subscribed ON '.self::COURSE_TABLE.'.course_id=subscribed.course_id'.
				' AND subscribed.account_id='.(int)$this->user.' AND subscribed.participant_unsubscribed IS NULL';
			$fix_subscribed = $this->db->Type === 'pgsql';
		}
		unset($filter['account_id']);

		$this->aclFilter($filter);

		$rows = parent::search($criteria, $only_keys, $order_by, $extra_cols, $wildcard, $empty, $op, $start, $filter, $join, $need_full_no_count);

		if (!empty($fix_subscribed))
		{
			foreach($rows as &$row)
			{
				$row['subscribed'] = Api\Db::from_bool($row['subscribed']);
			}
		}
		return $rows;
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
	function read($keys, $extra_cols='', $join='')
	{
		$this->aclFilter($keys);

		return parent::read($keys, $extra_cols, $join);
	}

	/**
	 * Save a course
	 *
	 * @param array $keys =null if given $keys are copied to data before saveing => allows a save as
	 * @param string|array $extra_where =null extra where clause, eg. to check an etag, returns true if no affected rows!
	 * @return int|boolean 0 on success, or errno != 0 on error, or true if $extra_where is given and no rows affected
	 */
	function save($keys=null,$extra_where=null)
	{
		if (!($err = parent::save($keys)) && !empty($keys['participants']))
		{
			// check if we need to update participants
			foreach($keys['participants'] as $participant)
			{
				if (empty($participant) || empty($participant['account_id'])) continue;

				$where = [
					'course_id'  => $keys['course_id'],
					'account_id' => $participant['account_id'],
				];
				unset($participant['account_id'], $participant['participant_subscribed'], $participant['participant_unsubscribed']);
				if (isset($participant['participant_group']) && is_array($participant['participant_group']))
				{
					$participant['participant_group'] = array_shift($participant['participant_group']);
				}
				$this->db->update(self::PARTICIPANT_TABLE, $participant, $where, __LINE__, __FILE__, self::APPNAME);
			}
		}
		return $err;
	}

	/**
	 * Filter given participants and only return modified ones
	 *
	 * @param int $course_id
	 * @param array $participants
	 */
	function participantsModified(int $course_id, array $participants, int $course_owner=null)
	{
		$unmodified = $this->participants($course_id, true);
		// filter out unmodified (or invalid) participants
		return array_filter($participants, static function(&$participant) use ($unmodified, $course_owner)
		{
			if (empty($participant) || empty($participant['account_id'])) return false;

			if (!isset($unmodified[$participant['account_id']])) return true;

			foreach($participant as $name => $value)
			{
				switch($name)
				{
					case 'participant_subscribed':
					case 'participant_unsubscribed':
					case 'participant_agreed':
					case 'class':
						continue 2; // ignore timestamps, they are not considered here (only changed via subscribe method)
					case 'participant_group':
						if (is_array($value)) $value = array_shift($value);
						break;
					case 'participant_role':
						if ((Bo::isSuperAdmin($participant['account_id']) || isset($course_owner) && $participant['account_id'] == $course_owner) &&
							$value != Bo::ROLE_ADMIN)
						{
							return true;
						}
						break;
				}
				if ($value != $unmodified[$participant['account_id']][$name])
				{
					return true;
				}
			}
			return false;
		});
	}

	/**
	 * Get last course, video and other data of a user
	 *
	 * @param int $account_id =null default $this->user
	 * @return array|null array with values or null if nothing saved
	 */
	public function lastVideo($account_id=null)
	{
		$data = $this->db->select(self::LASTVIDEO_TABLE, '*', [
			'account_id' => $account_id ?: $this->user,
		],                        __LINE__, __FILE__, 0, 'ORDER BY last_updated DESC', self::APPNAME, 1)->fetch();

		if (!$data['course_id']) $data['course_id'] = 'manage';

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
			'account_id' => $account_id ?: $this->user,
			'course_id'  => $data['course_id'] === 'manage' ? 0 : $data['course_id'],
			'video_id'   => $data['video_id'] ?? 0,
			'position'   => $data['position'] ?? null,
		],                       [
									 'account_id' => $account_id ?: $this->user,
									 'course_id'  => $data['course_id'] === 'manage' ? 0 : $data['course_id'],
									 'video_id'   => $data['video_id'] ?? 0,
								 ], __LINE__, __FILE__, self::APPNAME);
	}

	/**
	 * Subscribe a course or unsubscribe course(s)
	 *
	 * @param int|int[] $course_id
	 * @param boolean $subscribe true: subscribe, false: unsubscribe
	 * @param int|int[]|true $account_id true: everyone
	 * @param int $role
	 * @param ?int $group
	 * @return bool true on success or false on error
	 */
	function subscribe($course_id, $subscribe=true, $account_id=null, int $role=0, int $group=null, ?Api\DateTime $agreed=null)
	{
		if ($subscribe)
		{
			return (bool)$this->db->insert(self::PARTICIPANT_TABLE, [
				'participant_subscribed' => new Api\DateTime('now'),
				'participant_unsubscribed' => null,	// in case he had/was unsubscribed before
				'participant_agreed' => $agreed,
				'participant_role' => $role,
				'participant_group' => $group,
			], [
				'course_id'  => $course_id,
				'account_id' => $account_id,
			], __LINE__, __FILE__, self::APPNAME);
		}
		return (bool)$this->db->update(self::PARTICIPANT_TABLE, [
			'participant_unsubscribed' => new Api\DateTime('now'),
		], [
			'course_id'  => $course_id,
		] + ($account_id !== true ? [
			'account_id' => $account_id,
		] : []), __LINE__, __FILE__, self::APPNAME);
	}

	/**
	 * Change (course-specific) nickname of a participant
	 *
	 * @param int $course_id
	 * @param string $nickname
	 * @param int $account_id
	 * @return bool
	 * @throws Api\Exception\WrongParameter
	 */
	function changeNickname(int $course_id, string $nickname, int $account_id)
	{
		if ($account_id <= 0)
		{
			throw new Api\Exception\WrongParameter();
		}
		return (bool)$this->db->update(self::PARTICIPANT_TABLE, [
			'participant_alias' => $nickname,
		], [
			'course_id'  => $course_id,
			'account_id' => $account_id,
		], __LINE__, __FILE__, self::APPNAME);
	}

	function setNotifyParticipant($course_id, $account_id, $notify)
	{
		return $this->db->update(
			self::PARTICIPANT_TABLE,
			['notify' => (int)$notify],
			['course_id' => (int)$course_id, 'account_id' => (int)$account_id],
			__LINE__, __FILE__, self::APPNAME
		);
	}

	/**
	 * Close course(s)
	 *
	 * @param int|int[] $course_id
	 * @param bool $closed=true
	 * @return boolean false on error
	 */
	function close($course_id, bool $closed=true)
	{
		return $this->db->update(self::COURSE_TABLE, [
				'course_closed' => (int)$closed,
			], [
				'course_id'  => $course_id,
			] , __LINE__, __FILE__, self::APPNAME);
	}

	function deleteCourse(int $course_id)
	{
		$success = true;
		static $table_list = [
			self::COURSE_TABLE,
			self::PARTICIPANT_TABLE,
			self::VIDEO_TABLE,
			self::COMMENTS_TABLE,
			self::WATCHED_TABLE,
			self::CLMEASUREMENT_TABLE,
			self::CLMEASUREMENT_CONFIG_TABLE,
			self::LIVEFEEDBACK_TABLE,
			self::CATEGORIES_TABLE
		];
		foreach($table_list as $table)
		{
			$success = $success && $this->db->delete($table, ['course_id' => $course_id], __LINE__, __FILE__, self::APPNAME);
		}
		$this->db->delete(
			self::LASTVIDEO_TABLE,
			['last_data ' . $this->db->capabilities[$this->db::CAPABILITY_CASE_INSENSITIV_LIKE] . ' \'%"course_id":"' . $course_id . '"%\''],
			__LINE__, __FILE__, self::APPNAME
		);
		return $success;
	}


	/**
	 * Get participants of a course
	 *
	 * @param int $course_id
	 * @param bool|int $by_account_id false: return array, true: return array with account_id as key, int: return only given account_id
	 * @param ?bool $subscribed true: show only subscribed, false: only unsubscribed, null: show all
	 * @param int $required_role limit participants to a required role
	 * @param ?bool $agreed true: return only participants agreed to disclaimer, false: not aggreed, null: all
	 * @return array (account_id =>) array of values for keys "account_id", "primary_group" and "comments" (number of comments)
	 */
	function participants(int $course_id, $by_account_id = false, ?bool $subscribed=true, int $required_role=Bo::ROLE_STUDENT, ?bool $agreed=null)
	{
		$where = [$this->db->expression(self::PARTICIPANT_TABLE, self::PARTICIPANT_TABLE.'.', ['course_id' => $course_id])];
		if (!is_bool($by_account_id))
		{
			$where[] = $this->db->expression(self::PARTICIPANT_TABLE, self::PARTICIPANT_TABLE.'.', ['account_id' => $by_account_id]);
		}
		if (is_bool($subscribed))
		{
			$where[] = 'participant_unsubscribed IS '.($subscribed ? 'NULL' : 'NOT NULL');
		}
		if (is_bool($agreed))
		{
			$where[] = 'participant_agreed IS '.($subscribed ? 'NULL' : 'NOT NULL');
		}
		if ($required_role)
		{
			$where[] = '(participant_role & '.(int)$required_role.')='.(int)$required_role;
		}
		$participants = [];
		foreach($this->db->select(self::PARTICIPANT_TABLE, self::PARTICIPANT_TABLE.'.*,COUNT(comment_id) AS comments',
			$where, __LINE__, __FILE__, false,
			'GROUP BY '.self::PARTICIPANT_TABLE.'.account_id,'.self::PARTICIPANT_TABLE.'.course_id,'.
				self::ADDRESSBOOK_TABLE.'.n_given,'.self::ADDRESSBOOK_TABLE.'.n_family'.
			' ORDER BY participant_role DESC, n_given, n_family', self::APPNAME, 0,
			'JOIN '.self::ADDRESSBOOK_TABLE.' ON '.self::PARTICIPANT_TABLE.'.account_id='.self::ADDRESSBOOK_TABLE.'.account_id'.
			' LEFT JOIN '.self::COMMENTS_TABLE.' ON '.self::PARTICIPANT_TABLE.'.account_id='.self::COMMENTS_TABLE.'.account_id'.
			' AND '.self::PARTICIPANT_TABLE.'.course_id='.self::COMMENTS_TABLE.'.course_id') as $row)
		{
			$row['primary_group'] = Api\Accounts::id2name($row['account_id'], 'account_primary_group');
			if (empty($row['participant_group'])) unset($row['participant_group']);

			$participants[$row['account_id']] = $row;
		}
		return $by_account_id ? $participants : array_values($participants);
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
		$join = 'LEFT JOIN ' . self::LASTVIDEO_TABLE . ' AS lastvideo ON lastvideo.course_id = ' . self::VIDEO_TABLE . '.course_id AND ' .
			'lastvideo.video_id = ' . self::VIDEO_TABLE . '.video_id AND lastvideo.account_id = ' . $this->user;
		$where = is_array($where) ? $where : ['video_id' => $where];
		$db_cols = $this->db->get_table_definitions(self::APPNAME, self::VIDEO_TABLE)['fd'];
		$db_cols = array_combine(array_keys($db_cols), array_keys($db_cols));
		foreach($where as $col => $val)
		{
			if($val !== '')
			{
				// check if a db-internal name conversation necessary
				if(!is_int($col) && ($c = array_search($col, $db_cols)))
				{
					unset($where[$col]);
					$where[self::VIDEO_TABLE . '.' . $c] = $val;
				}
			}
		}
		foreach($this->db->select(
            self::VIDEO_TABLE,
            [self::VIDEO_TABLE.'.*', 'lastvideo.last_updated'],
            $where,
								  __LINE__, __FILE__, false,
            'ORDER BY video_name, ' . self::VIDEO_TABLE . '.video_id', self::APPNAME, 0, $join
		) as $video)
		{
			foreach(self::$video_timestamps as $col)
			{
				if (isset($video[$col])) $video[$col] = Api\DateTime::server2user($video[$col], 'object');
			}
			if(isset($video['last_updated']))
			{
				$video['last_updated'] = Api\DateTime::server2user($video['last_updated'], 'object');
			}
			if ($video['video_limit_access'])
			{
				$video['video_limit_access'] = explode(',', $video['video_limit_access']);
			}
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
	 * @param array $where =[] further query with column-name as key incl. "search" to search in comments and retweets
	 * @param ?string $order_by optional order by clause, default 'video_id, comment_starttime, comment_id'
	 * @return array comment_id => array of data pairs
	 */
	public function listComments( array $where=[], $order_by='video_id, comment_starttime, comment_id')
	{
		if (!array_key_exists('comment_deleted', $where) && !array_key_exists('comment_id', $where))
		{
			$where['comment_deleted'] = 0;
		}

		if (!empty($where['search']))
		{
			$where[] = 'comment_added '.$this->db->capabilities[Api\Db::CAPABILITY_CASE_INSENSITIV_LIKE].' '.
				$this->db->quote('%'.$where['search'].'%');
		}
		unset($where['search']);

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
	 * Get a count of comments on each material since the last time the material was accessed
	 *
	 * @param $course_id
	 * @param array|null $video_ids
	 * @param array|null $from_accounts Material limitations filter (video_id = # AND account_id IN (...))
	 * @return array VideoID => unread comment count
	 * @throws Api\Db\Exception
	 * @throws Api\Db\Exception\InvalidSql
	 */
	public function materialNewCommentCount($course_id, ?array $video_ids, ?array $from_accounts) : array
	{
		$join = 'LEFT JOIN ' . self::LASTVIDEO_TABLE . ' AS lastvideo ON
        lastvideo.course_id = egw_smallpart_comments.course_id
		AND lastvideo.account_id = ' . $this->user . '
		AND (lastvideo.video_id = egw_smallpart_comments.video_id)';
		$count = [];
		$where = [
			self::COMMENTS_TABLE . '.course_id' => $course_id,
			'comment_deleted'                   => 0,
			'(comment_updated > lastvideo.last_updated OR lastvideo.last_updated IS NULL)'
		];
		if(!empty($video_ids))
		{
			$where[self::COMMENTS_TABLE . '.video_id'] = $video_ids;
		}
		if(!empty($from_accounts))
		{
			$where[] = '(' . $this->db->column_data_implode(' OR ', $from_accounts) . ')';
		}

		foreach($this->db->select(
			self::COMMENTS_TABLE, self::COMMENTS_TABLE . '.video_id, COUNT(*) AS count', $where,
			__LINE__, __FILE__, false,
			' GROUP BY ' . (empty($video_ids) ? SELF::COMMENTS_TABLE . '.course_id' : 'video_id'), self::APPNAME, 0, $join
		) as $comment)
		{
			$count[$comment['video_id']] = $comment['count'];
		}
		return $count;
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

	/**
	 * Get data of last time a video was watched eg. it's watch_position
	 *
	 * @param int $course_id
	 * @param int $video_id
	 * @param ?int $account_id
	 * @return array|false
	 */
	public function lastWatched($course_id, $video_id, $account_id=null)
	{
		return $this->db->select(self::WATCHED_TABLE, '*', [
			'course_id' => $course_id,
			'video_id' => $video_id,
			'account_id' => $account_id ?: $this->user,
		], __LINE__, __FILE__,0, 'ORDER BY watch_endtime DESC', self::APPNAME, 1)->fetch();
	}

	/**
	 * Check that a video / videos have been completed
	 *
	 * @return boolean
	 */
	public function checkComplete($video_id, $account_id = null)
	{
		if(!$video_id)
		{
			return [];
		}
		$complete = [];
		if(is_string($video_id))
		{
			$video_id = explode(',', $video_id);
		}
		$rs = $this->db->select(
			'egw_smallpart_answers', ['video_id'],
			[
				'video_id'     => $video_id,
				'account_id'   => $account_id ?: $this->user,
				'overlay_id'   => 0,
				'answer_score' => [1, null]
			],
			__LINE__, __FILE__, self::APPNAME
		);
		foreach($rs as $r)
		{
			$complete[] = $r['video_id'];
		}

		return array_diff($video_id, $complete);
	}

	/**
	 * Record a Cognitive Load Measurement
	 *
	 * @param int $course_id
	 * @param int $video_id
	 * @param string $cl_type measurement type
	 * @param array $data measurement data JSON encoded
	 * @param int|null $account_id default current user
	 * @param int|null $cl_id id to update existing records
	 * @return false|int
	 * @throws Api\Exception\WrongParameter
	 */
	public function recordCLMeasurement(int $course_id, int $video_id, string $cl_type, array $data, int $account_id=null, int $cl_id=null)
	{
		$this->db->insert(self::CLMEASUREMENT_TABLE, [
			'course_id' => $course_id,
			'video_id' => $video_id,
			'account_id' => $account_id ?: $this->user,
			'cl_type' => $cl_type,
			'cl_data' => json_encode($data, JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES),
		], $cl_id ? ['cl_id' => $cl_id] : false, __LINE__, __FILE__, self::APPNAME);

		return $cl_id ?: $this->db->get_last_insert_id(self::CLMEASUREMENT_TABLE, 'cl_id');
	}

	/**
	 * Read CLMeasurement record
	 *
	 * @param int $course_id
	 * @param int $video_id
	 * @param string $cl_type
	 * @param int|null $account_id
	 * @param string $extra_where
	 * @return array records
	 */
	public function readCLMeasurementRecords(int $course_id, int $video_id, string $cl_type, int $account_id=null, string $extra_where='')
	{
		$table_def = $this->db->get_table_definitions(self::APPNAME, self::CLMEASUREMENT_TABLE);
		$where = $this->db->column_data_implode(' AND ',[
			'course_id' => $course_id,
			'video_id' => $video_id,
			'account_id' => $account_id ?: $this->user,
			'cl_type' => $cl_type,
		],True,False, $table_def ? $table_def['fd'] : null);
		$where .= $extra_where;
		return  $this->db->select(self::CLMEASUREMENT_TABLE, '*', $where, __LINE__, __FILE__,0, 'ORDER BY cl_timestamp DESC', self::APPNAME, 0)->GetAll();
	}

	/**
	 * inser/update CLM config
	 *
	 * @param int $course_id
	 * @param $data array
	 * @return void
	 */
	public function updateCLMeasurementsConfig(int $course_id, array $data)
	{
		if(empty($this->readCLMeasurementsConfig($course_id)))
		{
			$this->db->insert(self::CLMEASUREMENT_CONFIG_TABLE, [
				'course_id' => $course_id,
				'config_data' => json_encode($data, JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES),
			], false, __LINE__, __FILE__, self::APPNAME);
		}
		else
		{
			$this->db->update(self::CLMEASUREMENT_CONFIG_TABLE, [
				'course_id'=>$course_id,
				'config_data' => json_encode($data, JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES),
			], ['course_id'=>$course_id], __LINE__, __FILE__, self::APPNAME);
		}
	}

	/**
	 * Read CLM config for the given course
	 *
	 * @param int $course_id
	 * @return string encoded json data
	 */
	public function readCLMeasurementsConfig(int $course_id)
	{
		return  $this->db->select(self::CLMEASUREMENT_CONFIG_TABLE, '*', [
			'course_id' => $course_id
		], __LINE__, __FILE__,0, '', self::APPNAME)->fetch()['config_data'];
	}

	/**
	 * @param array $data
	 * @return false|int|mixed reutrns livefeedback record id
	 * @throws Api\Exception\WrongParameter
	 */
	public function saveLivefeedback(array $data)
	{
		if (empty($data['course_id']) || empty($data['video_id']))
		{
			throw new Api\Exception\WrongParameter("Missing course_id or video_id values");
		}

		if (isset($data['session_created']))
		{
			$data['session_created'] = Api\DateTime::user2server($data['session_created']);
		}
		if (!empty($data['lf_id']))
		{
			$this->db->update(self::LIVEFEEDBACK_TABLE, $data, empty($data['lf_id']) ? false : [
				'lf_id' => $data['lf_id']
			],__LINE__, __FILE__, self::APPNAME, 0);

		}
		else
		{
			$this->db->insert(self::LIVEFEEDBACK_TABLE, $data, empty($data['lf_id']) ? false : [
				'lf_id' => $data['lf_id']
			],__LINE__, __FILE__, self::APPNAME, 0);

		}

		return !empty($data['lf_id']) ? $data['lf_id'] :
			$this->db->get_last_insert_id(self::LIVEFEEDBACK_TABLE, 'lf_id');
	}

	/**
	 * Fetch livefeedback record from given courseId and videoId
	 * @param $course_id
	 * @param $video_id
	 * @return Api\ADORecordSet|false|int|string
	 */
	public function readLivefeedback($course_id, $video_id)
	{
		return $this->db->select(self::LIVEFEEDBACK_TABLE, '*', [
			'course_id' => $course_id,
			'video_id' => $video_id
		], __LINE__, __FILE__,0, '', self::APPNAME)->fetch();
	}

	/**
	 * Read categories
	 *
	 * @param int $course_id
	 * @param bool $index_by_cat_id true: use cat_id as index, false: use index 0, 1, ...
	 * @return array returns array of categories
	 */
	public function readCategories(int $course_id, bool $index_by_cat_id=false)
	{
		$cats = [];
		foreach ($this->db->select(self::CATEGORIES_TABLE, '*', [
			'course_id' => $course_id
		], __LINE__, __FILE__,false, 'ORDER BY COALESCE(parent_id,cat_id),cat_id', self::APPNAME) as $cat)
		{
			$cat += (array) json_decode($cat['cat_data'] ?? '[]', true);
			unset($cat['cat_data']);
			$cats[$cat['cat_id']]= $cat;
		}
		return $index_by_cat_id ? $cats : array_values($cats);
	}

	/**
	 * Update category
	 * @param $_data
	 * @return false|int|void returns cat_id
	 * @throws Api\Db\Exception\InvalidSql
	 * @throws Api\Exception\WrongParameter
	 */
	public function updateCategory($_data)
	{
		if ($_data['course_id'] && !empty($_data['cat_name']))
		{
			$_data['cat_data'] = json_encode(array_diff_key($_data, array_flip(['cat_id', 'course_id', 'parent_id',
																				'cat_name', 'cat_description',
																				'cat_color', 'cat_data', 'data'])),
				JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES);
			if (empty($_data['cat_id']) || !is_numeric($_data['cat_id']))
			{
				unset($_data['cat_id']);
				$this->db->insert(self::CATEGORIES_TABLE, $_data, false, __LINE__, __FILE__, self::APPNAME);
				return $this->db->get_last_insert_id(self::CATEGORIES_TABLE, 'cat_id');
			}
			else
			{
				$this->db->update(self::CATEGORIES_TABLE, $_data, [
					'cat_id' => $_data['cat_id']
				], __LINE__, __FILE__, self::APPNAME);
				return $_data['cat_id'];
			}
		}
	}

	/**
	 * Delete categories of course with are (not) in given cat_ids
	 *
	 * @param int $course_id
	 * @param array $cat_ids
	 * @param bool $not_in_cat_ids
	 * @return int deleted categories
	 * @throws Api\Db\Exception
	 * @throws Api\Db\Exception\InvalidSql
	 */
	public function deleteCategories(int $course_id, array $cat_ids, bool $not_in_cat_ids=true)
	{
		$this->db->delete(self::CATEGORIES_TABLE, [
			'course_id' => $course_id,
			$this->db->expression(self::CATEGORIES_TABLE, $not_in_cat_ids ? ' NOT ' : '', ['cat_id' => $cat_ids]),
		], __LINE__, __FILE__, self::APPNAME);

		return $this->db->affected_rows();
	}
}