<?php
/**
 * EGroupware - SmallParT - business logic
 *
 * @link https://www.egroupware.org
 * @package smallpart
 * @subpackage setup
 * @license http://opensource.org/licenses/gpl-license.php GPL - GNU General Public License
 */

namespace EGroupware\SmallParT;

use EGroupware\Api;

/**
 * SmallParT - business logic
 *
 * Mapping existing $_SESSION-variables
 *
 * - int $_SESSION['userid']: $GLOBALS['egw_info']['user']['account_id']
 * - string $_SESSION['nickname']: Bo::getNickname()
 * - bool $_SESSION['superadmin']: Bo::isSuperAdmin()
 * - $_SESSION['userrole'] === 'Admin': Bo::isAdmin()
 * - string $_SESSION['userorganisation']: Bo::getOrganisation()
 * - string $_SESSION['useremail']: Bo::getContact('email')
 *
 * - string $_SESSION['ScriptLoaded']
 */
class Bo
{
	const APPNAME = 'smallpart';
	const ACL_ADMIN_LOCATION = 'admin';

	/**
	 * Current user
	 *
	 * @var int
	 */
	protected $user;

	/**
	 * Instance of storage object
	 *
	 * @var So
	 */
	protected $so;

	/**
	 * Connstructor
	 *
	 * @param int $account_id =null default current user
	 */
	public function __construct($account_id=null)
	{
		$this->user = $account_id ?: $GLOBALS['egw_info']['user']['account_id'];
		$this->so = new So($this->user);
	}

	/**
	 * Fetch rows to display
	 *
	 * @param array $query
	 * @param array& $rows =null
	 * @param array& $readonlys =null
	 * @return int total number of rows
	 */
	public function get_rows($query, array &$rows=null, array &$readonlys=null)
	{
		// translated our filter for the storage layer
		switch($query['filter'])
		{
			case 'subscribed':
				$query['col_filter'][] = 'subscribed.account_id IS NOT NULL';
				break;
			case 'available':
				$query['col_filter'][] = 'subscribed.account_id IS NULL';
				break;
			case 'closed':
				$query['col_filter']['course_closed'] = 1;	// only closed
				break;
			default:	// all NOT closed courses
				$query['col_filter']['course_closed'] = 0;
				break;
		}
		$total = $this->so->get_rows($query, $rows, $readonlys);

		foreach($rows as $key => &$row)
		{
			if (!is_int($key)) continue;

			// mark course as subscribed or available
			$row['class'] = $row['subscribed'] ? 'spSubscribed' : 'spAvailable';
			if (!$row['subscribed']) $row['subscribed'] = '';	// for checkbox to understand

			// do NOT send password to cient-side
			unset($row['course_password']);
		}
		return $total;
	}

	/**
	 * Get last course, video and other data of a user
	 *
	 * @param int $account_id =null default $this->user
	 * @return array|null array with values or null if nothing saved
	 */
	public function lastVideo($account_id=null)
	{
		return $this->so->lastVideo($account_id ?: $this->user);
	}

	/**
	 * Get last course, video and other data of a user
	 *
	 * @param array $data values for keys "course_id", "video_id", ...
	 * @param int $account_id =null default $this->user
	 * @return array|null array with values or null if nothing saved
	 */
	public function setLastVideo(array $data, $account_id=null)
	{
		return $this->so->setLastVideo($data, $account_id ?: $this->user);
	}

	/**
	 * List courses of current user
	 *
	 * @param boolean $include_videos =false
	 * @param array $where =null default videos the current user is subscribed to
	 * @return array course_id => array pairs (plus optional attribute videos of type array)
	 */
	public function listCourses($include_videos=false, $where=null)
	{
		if (empty($where))
		{
			$where = ['account_id' => $this->user];
		}
		$courses = $this->so->listCourses($where);

		if ($include_videos)
		{
			foreach($this->listVideos(['course_id' => array_keys($courses)]) as $video)
			{
				$courses[$video['course_id']]['videos'] = $video;
			}
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
		$videos = $this->so->listVideos($where);
		foreach($videos as $video_id => &$video)
		{
			$video['video_ext'] = pathinfo($video['video_name'], PATHINFO_EXTENSION);
			$video['video_src'] = Api\Egw::link('/smallpart/Resources/Videos/Video/'.$video['course_id'].'/'.
				$video['video_hash'].'.'.$video['video_ext']);
		}
		return $videos;
	}


	/**
	 * Get filesystem path of a video
	 *
	 * Optionally create the directory, if it does not exist
	 *
	 * @param array $video array with values for keys
	 * @param boolean $create_dir =false true: create directory if not existing
	 * @return string
	 * @throws Api\Exception\WrongParameter
	 */
	function videoPath(array $video, $create_dir=false)
	{
		if (empty($video['video_hash'])) throw new Api\Exception\WrongParameter("Missing required value video_hash!");
		if (empty($video['course_id']) || !((int)$video['course_id'] > 0))
		{
			throw new Api\Exception\WrongParameter("Missing required value course_id!");
		}
		$dir = $GLOBALS['egw_info']['server']['files_dir'].'/'.self::APPNAME.'/Video/'.(int)$video['course_id'];

		if (!file_exists($dir) && (!$create_dir || !mkdir($dir)) || !is_dir($dir))
		{
			throw new Api\Exception\WrongParameter("Video directory '$dir' does not exist!");
		}
		return $dir.'/'.$video['video_hash'].'.'.pathinfo($video['video_name'], PATHINFO_EXTENSION);
	}

	/**
	 * Allowed MIME types
	 */
	const VIDEO_MIME_TYPES = '#^video/(mp4|webm)$#i';

	/**
	 * Add a video to a course
	 *
	 * @param int|array $course id or whole array
	 * @param array $upload upload array from et2_file widget
	 * @param string $question =null optional question
	 * @return array with video-data
	 * @throws Api\Exception\WrongParameter
	 * @throws Api\Exception\WrongUserinput
	 * @throws Api\Db\Exception
	 * @throws Api\Exception\NoPermission
	 */
	function addVideo($course, array $upload, $question=null)
	{
		if (!$this->isAdmin($course))
		{
			throw new Api\Exception\NoPermission();
		}
		if (!(preg_match(self::VIDEO_MIME_TYPES, $upload['type']) ||
			preg_match(self::VIDEO_MIME_TYPES, Api\MimeMagic::filename2mime($upload['name']))))
		{
			throw new Api\Exception\WrongUserinput(lang('Invalid type of video, please use mp4 or webm!'));
		}
		$video = [
			'course_id' => is_array($course) ? $course['course_id'] : $course,
			'video_name' => $upload['name'],
			'video_hash' => Api\Auth::randomstring(64),
			'video_question' => $question
		];
		if (!copy($upload['tmp_name'], $this->videoPath($video, true)))
		{
			throw new Api\Exception\WrongUserinput(lang("Failed to store uploaded video!"));
		}
		$video['video_id'] = $this->so->updateVideo($video);

		return $video;
	}

	/**
	 * Delete a video
	 *
	 * @param array $video values for keys video_id and optional course_id, video_hash and video_owner
	 * @param boolean $confirm_delete_comments =false true: to delete video, event if it has comments
	 * @throws Api\Exception\WrongParameter
	 * @throws Api\Exception\NoPermission
	 */
	function deleteVideo(array $video, $confirm_delete_comments=false)
	{
		if (empty($video['video_id'])) throw new Api\Exception\WrongParameter("Missing required value video_id");
		if (empty($video['course_id']) || empty($video['video_hash']))
		{
			$videos = $this->so->listVideos(['video_id' => $video['video_id'], 'course_id' => $video['course_id']]);
			if (!$videos || !isset($videos[$video['video_id']]))
			{
				throw new Api\Exception\WrongParameter("Video #$video[video_id] not found!");
			}
			$video = $videos[$video['video_id']];
		}
		if (!$this->isAdmin($video))
		{
			throw new Api\Exception\NoPermission();
		}
		// do we need to check if video has comments, or just delete them
		if (!$confirm_delete_comments && ($comments = $this->listComments($video['video_id'])))
		{
			throw new Api\Exception\WrongParameter(lang('This video has %1 comments! Click on delete again to really delete it.', count($comments)));
		}
		unlink($this->videoPath($video));

		$this->so->deleteVideo($video['video_id']);
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
		// ToDo: ACL check
		$where['video_id'] = $video_id;

		return $this->so->listComments($where);
	}

	/**
	 * Save a comment
	 *
	 * ACL:
	 * - participants can add new comments
	 * - owner of comment can edit it (ToDo: what about course-admin or EGw admin?)
	 * - participants can retweet (account_id and comment_id stay unchanged!)
	 *
	 * History:
	 * - seems to only account for main comment, not retweets
	 *
	 * Retweet:
	 * - account_id and comment is added after original text in comment_added area
	 *
	 * @param array $comment values for keys "course_id", "video_id", "account_id", ...
	 * @return int comment_id
	 * @throws Api\Exception\WrongParameter
	 */
	public function saveComment(array $comment)
	{
		if (empty($comment['course_id']) || empty($comment['video_id']))
		{
			throw new Api\Exception\WrongParameter("Missing course_id or video_id values!");
		}
		// ToDo: check ACL
		if (empty($comment['account_id']))
		{
			$comment['account_id'] = $this->user;
		}
		if (!array_key_exists($comment['comment_deleted']))
		{
			$comment['comment_deleted'] = 0;
		}
		if (!array_key_exists($comment['comment_stoptime']))
		{
			$comment['comment_stoptime'] = $comment['comment_starttime'];
		}
		return $this->so->saveComment($comment);
	}

	/**
	 * Delete a comment
	 *
	 * @param $comment_id
	 * @return int affected rows
	 * @throws Api\Exception\WrongParameter
	 */
	public function deleteComment($comment_id)
	{
		// ToDo: ACL check
		return $this->so->deleteComment($comment_id);
	}

	/**
	 * Current user is an admin/owner of a given course or can create courses
	 *
	 * @param int|array|null $course =null default check for creating new courses
	 * @return bool
	 */
	public function isAdmin($course=null)
	{
		// EGroupware Admins are always allowed
		if (self::isSuperAdmin()) return true;

		// deny if no SmallParT Admin / teacher rights
		if (!$GLOBALS['egw']->acl->get_rights(self::ACL_ADMIN_LOCATION, self::APPNAME))
		{
			return false;
		}
		elseif (!isset($course))
		{
			return true;
		}

		// if a course given check user matches the owner
		if ((!is_array($course) || empty($course['course_owner'])) &&
			!($course = $this->read($course['course_id'])))
		{
			return false;
		}
		return $course['course_owner'] == $this->user;
	}

	/**
	 * Set or remove admin rights / rights to create lectures
	 *
	 * @param int $account_id
	 * @param bool $allow true: make user and admin, false: remove admin rights
	 */
	public static function setAdmin($account_id, $allow=true)
	{
		if ($allow)
		{
			$GLOBALS['egw']->acl->add_repository(self::APPNAME, self::ACL_ADMIN_LOCATION, $account_id, 1);
		}
		else
		{
			$GLOBALS['egw']->acl->delete_repository(self::APPNAME, self::ACL_ADMIN_LOCATION, $account_id);
		}
	}

	/**
	 * Check if a given user is an admin / can create courses
	 *
	 * @param int $account_id
	 * @return bool
	 */
	public static function checkAdmin($account_id)
	{
		static $admins;
		if (!isset($admins))
		{
			$admins = $GLOBALS['egw']->acl->get_ids_for_location(self::ACL_ADMIN_LOCATION, 1, self::APPNAME);
		}
		return in_array($account_id, $admins, false);
	}

	/**
	 * Current user can edit accounts or reset passwords aka is an EGroupware admin
	 *
	 * @return bool
	 */
	public static function isSuperAdmin()
	{
		return !empty($GLOBALS['egw_info']['user']['apps']['admin']);
	}

	/**
	 * Get nickname of a user
	 *
	 * We might need to change that in future, as account_lid might be needed as unique name for Shibboleth auth.
	 *
	 * @param int $account_id =null default current user
	 * @return string
	 */
	public static function getNickname($account_id=null)
	{
		return Api\Accounts::username($account_id ?: $GLOBALS['egw_info']['user']['account_id']);
	}

	/**
	 * Contact data of current user use via Bo::getContact($name)
	 *
	 * @var array
	 */
	protected static $contact;

	/**
	 * Get contact-data of current user
	 *
	 * @param string $name ='org_name'
	 * @return string|null
	 */
	public static function getContact($name='org_name')
	{
		if (!isset(self::$contact))
		{
			$contacts = new Api\Contacts();
			self::$contact = $contacts->read('account:'.$GLOBALS['egw_info']['user']['account_id']);
		}
		return is_array(self::$contact) ? self::$contact[$name] : null;
	}

	/**
	 * Get organisation name of current user
	 *
	 * @return string|null
	 */
	public static function getOrganisation()
	{
		return self::getContact('org_name');
	}

	/**
	 * Check course password, if one is set
	 *
	 * @param int $course_id
	 * @param string $password
	 * @return bool
	 * @throws Api\Exception\WrongParameter invalid $course_id
	 * @throws Api\Exception\WrongUserinput wrong password
	 */
	public function checkSubscribe($course_id, $password)
	{
		if (!($course = $this->so->read(['course_id' => $course_id])))
		{
			throw new Api\Exception\WrongParameter("Course #$course_id not found!");
		}
		if ($course['course_closed'])
		{
			throw new Api\Exception\WrongParameter("Course #$course_id is already closed!");
		}
		if (!empty($course['course_password']) && $password !== $course['course_password'])
		{
			throw new Api\Exception\WrongUserinput(lang('You entered a wrong course password!'));
		}
		return true;
	}

	/**
	 * Subscribe or unsubscribe from a course
	 *
	 * Only (course) admins can (un)subscribe others!
	 *
	 * @param int|array $course_id one or multiple course_id's, subscribe only supported for a single course_id (!)
	 * @param boolean $subscribe =true true: subscribe, false: unsubscribe
	 * @param int $account_id =null default current user
	 * @param string $password password to subscribe to password protected courses
	 * @throws Api\Exception\WrongParameter invalid $course_id
	 * @throws Api\Exception\WrongUserinput wrong password
	 * @throws Api\Exception\NoPermission
	 * @throws Api\Db\Exception
	 */
	public function subscribe($course_id, $subscribe=true, $account_id=null, $password=null)
	{
		if ((!isset($account_id) && $account_id != $this->user))
		{
			foreach((array)$course_id as $id)
			{
				if (!$this->isAdmin($id))
				{
					throw new Api\Exception\NoPermission("Only admins are allowed to (un)subscribe others!");
				}
			}
		}
		if ($subscribe && is_array($course_id))
		{
			throw new Api\Exception\WrongParameter("Can only subscribe to single courses!");
		}
		if ($subscribe)
		{
			$this->checkSubscribe($course_id, $password);
		}
		if (!$this->so->subscribe($course_id, $subscribe, $account_id ?: $this->user))
		{
			throw new Api\Db\Exception(lang('Error (un)subscribing!'));
		}
	}

	/**
	 * Close given course(s)
	 *
	 * @param int|array $course_id one or more couse_id
	 * @throws Api\Exception\NoPermission
	 * @throws Api\Db\Exception
	 */
	public function close($course_id)
	{
		// need to check every single course, as rights depend on being the owner/admin of a course
		foreach((array)$course_id as $id)
		{
			if (!$this->isAdmin($id))
			{
				throw new Api\Exception\NoPermission("Only admins are allowed to close courses!");
			}
		}
		if (!$this->so->close($course_id))
		{
			throw new Api\Db\Exception(lang('Error closing course!'));
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
		if (!is_array($keys)) $keys = ['course_id' => $keys];

		if (($course = $this->so->read($keys, $extra_cols, $join)))
		{
			// ToDo: ACL check

			$course['participants'] = $this->so->participants($course['course_id']);
			$course['videos'] = $this->listVideos(['course_id' => $course['course_id']]);
		}
		return $course;
	}

	/**
	 * saves the content of data to the db
	 *
	 * @param array $keys =null if given $keys are copied to data before saveing => allows a save as
	 * @param string|array $extra_where =null extra where clause, eg. to check an etag, returns true if no affected rows!
	 * @return array saved data
	 * @throws Api\Db\Exception on error
	 * @throws Api\Exception\WrongParameter
	 */
	function save($keys=null,$extra_where=null)
	{
		if (($err = $this->so->save($keys)))
		{
			throw new Ap\Db\Exception(lang('Error saving course!'));
		}
		$course = $this->so->data;
		$course['participants'] = $keys['participants'] ?: [];
		$course['videos'] = $keys['videos'] ?: [];

		foreach($course['videos'] as &$video)
		{
			if (!$video) continue;	// leave UI added empty lines alone

			$this->so->updateVideo($video);
		}
		return $course;
	}
}