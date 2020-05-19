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
use EGroupware\Api\Acl;

/**
 * smallPART - business logic
 *
 * ACL in smallPART:
 *
 * - to create a new course a user needs an explicit course-admin right (ACL app/location smallpart/admin)
 *
 * - courses are subscribable by
 *   + implicit all members of the organisation (group) set in the course
 *   + members of other groups with a read grant from either the course-owner or the organisation of the course
 *   + individual users with read grant from the course-owner or -organisation
 *
 * - only subscribed users can "watch" / participate in a course
 *
 * - courses are editable / administratable by:
 *   + EGroupware administrators
 *   + course-owner/-admin
 *   + users with explicit edit grant of the course-owner (proxy rights)
 *
 * --> implicit rights match old smallPART app with an organisation field migrated to primary group of the users
 * --> explicit rights allow to make courses available outside the organisation or delegate admin rights to a proxy
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
	 * ACL grants from other users and organisations/groups
	 *
	 * @var array account_id => rights pairs
	 */
	protected $grants;

	/**
	 * Memberships of current user
	 *
	 * @var array of account_id
	 */
	protected $memberships;

	/**
	 * Current use is a course-admin aka can create courses
	 *
	 * A super-admin (EGroupware Admins) or users with explicit smallpart self::ACL_ADMIN_LOCATION ("admin") rights.
	 *
	 * @var boolean
	 */
	protected $is_admin;

	/**
	 * Connstructor
	 *
	 * @param int $account_id =null default current user
	 */
	public function __construct($account_id=null)
	{
		$this->user = $account_id ?: $GLOBALS['egw_info']['user']['account_id'];
		$this->so = new So($this->user);

		$this->grants = $GLOBALS['egw']->acl->get_grants(Bo::APPNAME, false) ?: [];

		// give implicit read/subscribe grants for all memberships
		$this->memberships = $GLOBALS['egw']->accounts->memberships($this->user, true);
		foreach($this->memberships as $account_id)
		{
			$this->grants[$account_id] |= ACL::READ;
		}

		// if now course-admin, remove standard EGroupware grant for own entries
		if (!($this->is_admin = self::isSuperAdmin() || $GLOBALS['egw']->acl->get_rights(self::ACL_ADMIN_LOCATION, self::APPNAME)))
		{
			unset($this->grants[$this->user]);
		}
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
				$query['col_filter']['course_closed'] = '0';
				break;
		}
		$total = $this->so->get_rows($query, $rows, $readonlys);

		foreach($rows as $key => &$row)
		{
			if (!is_int($key)) continue;

			// mark course as subscribed or available
			$row['class'] = $row['subscribed'] ? 'spSubscribed' : 'spAvailable';
			if ($this->isAdmin($row)) $row['class'] .= ' spEditable';
			if (!$row['subscribed']) $row['subscribed'] = '';	// for checkbox to understand

			// do NOT send password to cient-side
			unset($row['course_password']);
		}
		return $total;
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
	 * @return array|NULL array of matching rows (the row is an array of the cols) or NULL
	 */
	function &search($criteria, $only_keys=True, $order_by='', $extra_cols='', $wildcard='', $empty=False, $op='AND',
					 $start=false, $filter=null, $join='')
	{
		// ACL filter (expanded by so->search to (course_owner OR course_org)
		$filter['acl'] = array_keys($this->grants);

		return $this->so->search($criteria, $only_keys, $order_by, $extra_cols, $wildcard, $empty, $op, $start, $filter, $join);
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
	 * Set last course, video and other data of a user
	 *
	 * @param array $data values for keys "course_id", "video_id", ...
	 * @param int $account_id =null default $this->user
	 * @return boolean
	 * @throws Api\Exception\WrongParameter
	 */
	public function setLastVideo(array $data, $account_id=null)
	{
		return $this->so->setLastVideo($data, $account_id ?: $this->user);
	}

	/**
	 * List subscribed courses of current user
	 *
	 * @return array course_id => course_name pairs
	 */
	public function listCourses()
	{
		return $this->so->query_list('course_name', So::COURSE_TABLE.'.course_id AS course_id',
			['account_id' => $this->user], 'course_name ASC');
	}

	/**
	 * List videos
	 *
	 * @param array $where video_id or query eg. ['video_id' => $ids]
	 * @return array video_id => array with data pairs
	 */
	public function listVideos(array $where)
	{
		$videos = $this->so->listVideos($where);
		foreach($videos as $video_id => &$video)
		{
			$video['video_src'] = $this->videoSrc($video);
		}
		return $videos;
	}

	/**
	 * Get src/url to play video
	 *
	 * @param array $video
	 * @return string
	 */
	protected function videoSrc(array $video)
	{
		if (!empty($video['video_hash']))
		{
			return Api\Egw::link('/smallpart/Resources/Videos/Video/'.$video['course_id'].'/'.
				$video['video_hash'].'.'.$video['video_type']);
		}
		return $video['video_url'];
	}

	/**
	 * Read one video
	 *
	 * @param int $video_id
	 * @return array|null with video data
	 */
	public function readVideo($video_id)
	{
		$videos = $this->listVideos(['video_id' => $video_id]);

		return $videos ? $videos[$video_id] : null;
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
		return $dir.'/'.$video['video_hash'].'.'.$video['video_type'];
	}

	/**
	 * Allowed MIME types
	 */
	const VIDEO_MIME_TYPES = '#^video/(mp4|webm)$#i';

	/**
	 * Add a video to a course
	 *
	 * @param int|array $course id or whole array
	 * @param string|array $upload upload array from et2_file widget or url of video
	 * @param string $question =null optional question
	 * @return array with video-data
	 * @throws Api\Exception\WrongParameter
	 * @throws Api\Exception\WrongUserinput
	 * @throws Api\Db\Exception
	 * @throws Api\Exception\NoPermission
	 */
	function addVideo($course, $upload, $question='')
	{
		if (!$this->isAdmin($course))
		{
			throw new Api\Exception\NoPermission();
		}
		$video = [
			'course_id' => is_array($course) ? $course['course_id'] : $course,
			'video_question' => (string)$question
		];

		if (!is_array($upload))
		{
			$video += [
				'video_name' => pathinfo(parse_url($upload, PHP_URL_PATH), PATHINFO_FILENAME),
				'video_type' => substr(self::checkVideoURL($upload), 6),
				'video_url' => $upload,
			];
		}
		else
		{
			if (!(preg_match(self::VIDEO_MIME_TYPES, $mime_type=$upload['type']) ||
				preg_match(self::VIDEO_MIME_TYPES, $mime_type=Api\MimeMagic::filename2mime($upload['name']))))
			{
				throw new Api\Exception\WrongUserinput(lang('Invalid type of video, please use mp4 or webm!'));
			}
			$video += [
				'video_name' => $upload['name'],
				'video_type' => substr($mime_type, 6),	// "video/"
				'video_hash' => Api\Auth::randomstring(64),
			];
			if (!copy($upload['tmp_name'], $this->videoPath($video, true)))
			{
				throw new Api\Exception\WrongUserinput(lang("Failed to store uploaded video!"));
			}
		}
		$video['video_id'] = $this->so->updateVideo($video);
		$video['video_src'] = $this->videoSrc($video);

		return $video;
	}

	/**
	 * Check mime-type and correctness of video URL (using a HEAD request)
	 *
	 * @param string $url
	 * @return string mime-type eg. "video/mp4"
	 * @throws Api\Exception\WrongUserinput if video not accessible or wrong mime-type
	 */
	protected static function checkVideoURL($url)
	{
		if (!preg_match(Api\Etemplate\Widget\Url::URL_PREG, $url) || parse_url($url, PHP_URL_SCHEME) !== 'https')
		{
			throw new Api\Exception\WrongUserinput(lang('Only https URL supported!'));
		}
		if (!($fd = fopen($url, 'rb', false, stream_context_create(array('http' =>array('method'=>'HEAD'))))))
		{
			throw new Api\Exception\WrongUserinput(lang('Can NOT access the requested URL!'));
		}
		$metadata = stream_get_meta_data($fd);
		fclose($fd);

		foreach($metadata['wrapper_data'] as $header)
		{
			if (preg_match('/^Content-Type: *([^ ;]+)/i', $header, $matches))
			{
				break;
			}
		}

		if (!$matches || !preg_match(self::VIDEO_MIME_TYPES, $matches[1]))
		{
			throw new Api\Exception\WrongUserinput(lang('Invalid type of video, please use mp4 or webm!'));
		}
		return $matches[1];
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
		if (!$this->isAdmin($video['course_id']))
		{
			throw new Api\Exception\NoPermission();
		}
		// do we need to check if video has comments, or just delete them
		if (!$confirm_delete_comments && ($comments = $this->so->listComments(['video_id' => $video['video_id']])))
		{
			throw new Api\Exception\WrongParameter(lang('This video has %1 comments! Click on delete again to really delete it.', count($comments)));
		}
		if (!empty($video['video_hash']))
		{
			unlink($this->videoPath($video));
		}
		$this->so->deleteVideo($video['video_id']);
	}

	/**
	 * Show all comments to students, admins/teachers allways get them all
	 */
	const COMMENTS_SHOW_ALL = 0;
	/**
	 * Hide comments of other students
	 */
	const COMMENTS_HIDE_OTHER_STUDENTS = 1;
	/**
	 * Hide comments of the owner / teacher
	 */
	const COMMENTS_HIDE_OWNER = 2;
	/**
	 * Show only own comments
	 */
	const COMMENTS_SHOW_OWN = 3;

	/**
	 * List comments of given video chronological
	 *
	 * @param int $video_id
	 * @param array $where =[] further query parts eg.
	 * @return array comment_id => array of data pairs
	 * @throws Api\Exception\NoPermission
	 * @throws Api\Exception\WrongParameter
	 */
	public function listComments($video_id, array $where=[])
	{
		// ACL check
		if (!($video = $this->readVideo($video_id)) ||
			!($course = $this->read($video['course_id'])))
		{
			throw new Api\Exception\WrongParameter("Video #$video_id not found!");
		}
		if ($this->isAdmin($course))
		{
			// no comment filter for course-admin / teacher
		}
		elseif($this->isParticipant($course))
		{
			$where = array_merge($where, $this->videoOptionsFilter($video['video_options'], $course['course_owner']));
		}
		else
		{
			throw new Api\Exception\NoPermission();
		}
		$where['video_id'] = $video_id;

		return $this->so->listComments($where);
	}

	/**
	 * Filter to list comments based on video-options
	 *
	 * @param int $video_options self::COMMENTS_*
	 * @param int $course_owner course-admin / teacher
	 * @return array
	 */
	protected function videoOptionsFilter($video_options, $course_owner)
	{
		$filter = [];
		switch($video_options)
		{
			case self::COMMENTS_SHOW_ALL:
				break;
			case self::COMMENTS_HIDE_OWNER:
				$filter[] = 'account_id != '.(int)$course_owner;
				break;
			case self::COMMENTS_HIDE_OTHER_STUDENTS:
				$filter['account_id'] = [$this->user, $course_owner];
				break;
			case self::COMMENTS_SHOW_OWN:
				$filter['account_id'] = $this->user;
				break;
		}
		return $filter;
	}

	/**
	 * Save a comment
	 *
	 * ACL:
	 * - participants can add new comments
	 * - owner of comment can edit it
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
	 * @throws Api\Exception\NoPermission
	 * @throws Api\Exception\NotFound
	 * @throws Api\Exception\WrongParameter
	 */
	public function saveComment(array $comment)
	{
		// check required parameters
		if (empty($comment['course_id']) || empty($comment['video_id']))
		{
			throw new Api\Exception\WrongParameter("Missing course_id or video_id values!");
		}
		if (empty($comment['action']) || empty($comment['text']))
		{
			throw new Api\Exception\WrongParameter("Missing action or text values!");
		}
		// check ACL, need to be a participants to comment
		if (!$this->isParticipant($comment['course_id']))
		{
			throw new Api\Exception\NoPermission();
		}
		// new comments allowed by every participant
		if (empty($comment['comment_id']))
		{
			$comment['account_id'] = $this->user;
			$comment['action'] = 'add';
		}
		else
		{
			if (!($old = $this->listComments($comment['video_id'], ['comment_id' => $comment['comment_id']])) ||
				!($old = $old[$comment['comment_id']]))
			{
				throw new Api\Exception\NotFound("Comment #$comment[comment_id] of course #$comment[course_id] and video #$comment[video_id] not found!");
			}
			// only course-admin and comment-writer is allowed to edit, everyone to retweet
			if (!($this->isAdmin($old) || $old['account_id'] == $this->user || $comment['action'] === 'retweet'))
			{
				throw new Api\Exception\NoPermission();
			}
		}
		// build data to save based on old data, action, new text, color and markings (dont trust client-side)
		$to_save = $old;
		switch($comment['action'])
		{
			case 'add':
				$to_save = [
					'course_id'  => $comment['course_id'],
					'video_id'   => $comment['video_id'],
					'account_id' => $this->user,
					'comment_added' => [$comment['text']],
					'comment_starttime' => $comment['comment_starttime'],
					'comment_stoptime' => $comment['comment_stoptime'] ?: $comment['comment_starttime'],
					'comment_color' => $comment['comment_color'],
					'comment_marked' => $comment['comment_marked'],
					'comment_deleted' => 0,
				];
				break;

			case 'edit':
				$to_save['comment_added'] = array_merge([$comment['text']], array_slice($old['comment_added'], 1));
				if (!isset($to_save['comment_history'])) $to_save['comment_history'] = [];
				array_unshift($to_save['comment_history'], $old['comment_added'][0]);
				$to_save['comment_color'] = $comment['comment_color'];
				$to_save['comment_marked'] = $comment['comment_marked'];
				break;

			case 'retweet':
				$to_save['comment_added'][] = $this->user;
				$to_save['comment_added'][] = $comment['text'];
				break;

			default:
				throw new Api\Exception\WrongParameter("Invalid action '$comment[action]!");
		}
		return $this->so->saveComment($to_save);
	}

	/**
	 * Delete a comment
	 *
	 * @param $comment_id
	 * @return int affected rows
	 * @throws Api\Exception\NoPermission
	 * @throws Api\Exception\NotFound
	 * @throws Api\Exception\WrongParameter
	 */
	public function deleteComment($comment_id)
	{
		if (!($comment = $this->so->readComment($comment_id)) ||
			!($course = $this->so->read(['course_id' => $comment['course_id']])))
		{
			throw new Api\Exception\NotFound();
		}
		// only course-admins and owner of comment is allowed to (mark as) delete a comment
		if (!($this->isAdmin($course) || $comment['account_id'] == $this->user))
		{
			throw new Api\Exception\NoPermission();
		}
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
		if (!$this->is_admin)
		{
			return false;
		}
		elseif (!isset($course))
		{
			return true;
		}

		// if a course given check user matches the owner
		if ((!is_array($course) || empty($course['course_owner'])) &&
			!($course = $this->so->read(['course_id' => is_array($course) ? $course['course_id'] : $course])))
		{
			return false;
		}
		// either owner himself or personal edit-rights from owner (deputy rights)
		return !!($this->grants[$course['course_owner']] & ACL::EDIT);
	}

	/**
	 * Check if current user is a participant of a course
	 *
	 * @param int|array $course course_id or course-array with course_id, course_owner and optional participants
	 * @return boolean true if participant or admin, false otherwise
	 * @throws Api\Exception\WrongParameter
	 */
	public function isParticipant($course)
	{
		if ($this->isAdmin($course))
		{
			return true;
		}
		if (!is_array($course) && !($course = $this->so->read(['course_id' => $id=$course])))
		{
			throw new Api\Exception\WrongParameter("Course #$id not found!");
		}
		if (!isset($course['participants']))
		{
			$course['participants'] = $this->so->participants($course['course_id']);
		}
		foreach($course['participants'] as $participant)
		{
			if ($participant['account_id'] == $this->user) return true;
		}
		return false;
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
	 * Prefix of password hash
	 */
	const PASSWORD_HASH_PREFIX = '$2y$';

	/**
	 * Check course password, if one is set
	 *
	 * @param int $course_id
	 * @param string|true $password password to subscribe to password protected courses
	 * 	true to not check the password (used when accessing a course via LTI)
	 * @return bool
	 * @throws Api\Exception\WrongParameter invalid $course_id
	 * @throws Api\Exception\WrongUserinput wrong password
	 */
	public function checkSubscribe($course_id, $password)
	{
		if (!($course = $this->read($course_id, false)))	// false: do not check for subscribed
		{
			throw new Api\Exception\WrongParameter("Course #$course_id not found!");
		}
		if ($course['course_closed'])
		{
			throw new Api\Exception\WrongParameter("Course #$course_id is already closed!");
		}
		if ($password !== true && !empty($course['course_password']) &&
			!(password_verify($password, $course['course_password']) ||
				// ToDo: remove check of cleartext passwords after upgrade (hashes are never exepted as PW)
				substr($course['course_password'], 0, 4) !== self::PASSWORD_HASH_PREFIX &&
					$password === $course['course_password']))
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
	 * @param string|true $password password to subscribe to password protected courses
	 * 	true to not check the password (used when accessing a course via LTI)
	 * @throws Api\Exception\WrongParameter invalid $course_id
	 * @throws Api\Exception\WrongUserinput wrong password
	 * @throws Api\Exception\NoPermission
	 * @throws Api\Db\Exception
	 */
	public function subscribe($course_id, $subscribe=true, $account_id=null, $password=null)
	{
		if ((isset($account_id) && $account_id != $this->user))
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
	 * @param array|int $keys array with keys or scalar course_id
	 * @param string|array $extra_cols ='' string or array of strings to be added to the SELECT, eg. "count(*) as num"
	 * @param string $join ='' sql to do a join, added as is after the table-name, eg. ", table2 WHERE x=y" or
	 * @return array|boolean data if row could be retrived else False
	 * @throws Api\Exception\NoPermission if not subscribed
	 * @throws Api\Exception\WrongParameter
	 */
	function read($keys, $check_subscribed=true)
	{
		if (!is_array($keys)) $keys = ['course_id' => $keys];

		// ACL filter (expanded by so->search to (course_owner OR course_org)
		$filter['acl'] = array_keys($this->grants);

		if (($course = $this->so->read($keys)))
		{
			$course['participants'] = $this->so->participants($course['course_id']);

			// ACL check
			if ($check_subscribed && !$this->isParticipant($course))
			{
				throw new Api\Exception\NoPermission();
			}
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
		// hash password if user changed it
		if (substr($keys['course_password'], 0, 4) !== self::PASSWORD_HASH_PREFIX)
		{
			$keys['course_password'] = password_hash($keys['course_password'], PASSWORD_BCRYPT);
		}
		if (($err = $this->so->save($keys)))
		{
			throw new Ap\Db\Exception(lang('Error saving course!'));
		}
		$course = $this->so->data;

		// subscribe teacher/course-admin to course
		if (empty($keys['course_id'])) $this->bo->subscribe($course['course_id']);

		$course['participants'] = $keys['participants'] ?: [];
		$course['videos'] = $keys['videos'] ?: [];

		foreach($course['videos'] as $key => &$video)
		{
			if (!$video || !is_int($key)) continue;	// leave UI added empty lines or other stuff alone

			$this->so->updateVideo($video);
		}
		return $course;
	}

	/**
	 * Initialize a new course
	 *
	 * @return array
	 */
	function init()
	{
		return $this->data = [
			'course_owner' => $this->user,
			'course_org' => $GLOBALS['egw_info']['user']['account_primary_group'],
			'participants' => [],
			'videos' => [],
		];
	}
}