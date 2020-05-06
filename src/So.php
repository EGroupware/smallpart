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
class So
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

	/**
	 * Reference to global DB object
	 *
	 * @var Api\Db
	 */
	protected $db;
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
		$this->user = $account_id ?: $GLOBALS['egw_info']['user']['account_id'];
		$this->db = $db ?: $GLOBALS['egw']->db;
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
		if (isset($where['account_id']))
		{
			$where[] = $this->db->expression(self::PARTICIPANT_TABLE, ['account_id' => $where['account_id']]);
			unset($where['account_id']);
			$join = 'JOIN '.self::PARTICIPANT_TABLE.' ON '.self::PARTICIPANT_TABLE.'.course_id='.self::COURSE_TABLE.'.course_id';
		}
		$courses = [];
		foreach($this->db->select(self::COURSE_TABLE, '*', $where, __LINE__, __FILE__, false,
			'ORDER BY course_name', self::APPNAME, 0, $join) as $row)
		{
			$courses[$row['course_id']] = $row;
		}
		return $courses;
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
