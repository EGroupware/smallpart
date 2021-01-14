<?php
/**
 * EGroupware - SmallParT - business logic
 *
 * @link https://www.egroupware.org
 * @package smallpart
 * @subpackage setup
 * @license https://spdx.org/licenses/AGPL-3.0-or-later.html GNU Affero General Public License v3.0 or later
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
	 * Site configuration
	 *
	 * @var array
	 */
	protected $config;

	/**
	 * Connstructor
	 *
	 * @param int $account_id =null default current user
	 */
	public function __construct($account_id = null)
	{
		$this->user = $account_id ?: $GLOBALS['egw_info']['user']['account_id'];
		$this->so = new So($this->user);

		$this->config = Api\Config::read(self::APPNAME);

		$this->grants = $GLOBALS['egw']->acl->get_grants(Bo::APPNAME, false) ?: [];

		// give implicit read/subscribe grants for all memberships
		$this->memberships = $GLOBALS['egw']->accounts->memberships($this->user, true) ?: [];
		foreach ($this->memberships as $account_id)
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
	public function get_rows($query, array &$rows = null, array &$readonlys = null)
	{
		// translated our filter for the storage layer
		switch ($query['filter'])
		{
			case 'subscribed':
				$query['col_filter'][] = 'subscribed.account_id IS NOT NULL';
				break;
			case 'available':
				$query['col_filter'][] = 'subscribed.account_id IS NULL';
				break;
			case 'closed':
				$query['col_filter']['course_closed'] = 1;    // only closed
				break;
			default:    // all NOT closed courses
				$query['col_filter']['course_closed'] = '0';
				break;
		}
		$total = $this->so->get_rows($query, $rows, $readonlys);

		foreach ($rows as $key => &$row)
		{
			if (!is_int($key)) continue;

			// mark course as subscribed or available
			$row['class'] = $row['subscribed'] ? 'spSubscribed' : 'spAvailable';
			if ($this->isAdmin($row)) $row['class'] .= ' spEditable';
			if (!$row['subscribed']) $row['subscribed'] = '';    // for checkbox to understand

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
	 *    comma seperated list or array of columns to return
	 * @param string $order_by ='' fieldnames + {ASC|DESC} separated by colons ',', can also contain a GROUP BY (if it contains ORDER BY)
	 * @param string|array $extra_cols ='' string or array of strings to be added to the SELECT, eg. "count(*) as num"
	 * @param string $wildcard ='' appended befor and after each criteria
	 * @param boolean $empty =false False=empty criteria are ignored in query, True=empty have to be empty in row
	 * @param string $op ='AND' defaults to 'AND', can be set to 'OR' too, then criteria's are OR'ed together
	 * @param mixed $start =false if != false, return only maxmatch rows begining with start, or array($start,$num), or 'UNION' for a part of a union query
	 * @param array $filter =null if set (!=null) col-data pairs, to be and-ed (!) into the query without wildcards
	 * @param string $join ='' sql to do a join, added as is after the table-name, eg. "JOIN table2 ON x=y" or
	 *    "LEFT JOIN table2 ON (x=y AND z=o)", Note: there's no quoting done on $join, you are responsible for it!!!
	 * @return array|NULL array of matching rows (the row is an array of the cols) or NULL
	 */
	function &search($criteria, $only_keys = True, $order_by = '', $extra_cols = '', $wildcard = '', $empty = False, $op = 'AND',
					 $start = false, $filter = null, $join = '')
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
	public function lastVideo($account_id = null)
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
	public function setLastVideo(array $data, $account_id = null)
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
		return $this->so->query_list('course_name', So::COURSE_TABLE . '.course_id AS course_id',
			['account_id' => $this->user], 'course_name ASC');
	}

	/**
	 * List videos
	 *
	 * @param array $where video_id or query eg. ['video_id' => $ids]
	 * @param bool $name_only =false true: return name as value
	 * @return array video_id => array with data pairs or video_name, if $name_only
	 */
	public function listVideos(array $where, $name_only=false)
	{
		$videos = $this->so->listVideos($where);
		foreach ($videos as $video_id => &$video)
		{
			if ($name_only)
			{
				$video = $video['video_name'];
			}
			else
			{
				$video['video_src'] = $this->videoSrc($video);
			}
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
			return Api\Egw::link('/smallpart/Resources/Videos/Video/' . $video['course_id'] . '/' .
				$video['video_hash'] . '.' . $video['video_type']);
		}
		return self::checkVideoURL($video['video_url']);
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
	function videoPath(array $video, $create_dir = false)
	{
		if (empty($video['video_hash'])) throw new Api\Exception\WrongParameter("Missing required value video_hash!");
		if (empty($video['course_id']) || !((int)$video['course_id'] > 0))
		{
			throw new Api\Exception\WrongParameter("Missing required value course_id!");
		}
		$dir = $GLOBALS['egw_info']['server']['files_dir'] . '/' . self::APPNAME . '/Video/' . (int)$video['course_id'];

		if (!file_exists($dir) && (!$create_dir || !mkdir($dir)) || !is_dir($dir))
		{
			throw new Api\Exception\WrongParameter("Video directory '$dir' does not exist!");
		}
		return $dir . '/' . $video['video_hash'] . '.' . $video['video_type'];
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
	function addVideo($course, $upload, $question = '')
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
			self::checkVideoURL($upload, $content_type);
			$video += [
				'video_name' => pathinfo(parse_url($upload, PHP_URL_PATH), PATHINFO_FILENAME),
				'video_type' => substr($content_type, 6),
				'video_url' => $upload,
			];
		}
		else
		{
			if (!(preg_match(self::VIDEO_MIME_TYPES, $mime_type = $upload['type']) ||
				preg_match(self::VIDEO_MIME_TYPES, $mime_type = Api\MimeMagic::filename2mime($upload['name']))))
			{
				throw new Api\Exception\WrongUserinput(lang('Invalid type of video, please use mp4 or webm!'));
			}
			$video += [
				'video_name' => $upload['name'],
				'video_type' => substr($mime_type, 6),    // "video/"
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
	 * Cache positive check video-url for some time
	 */
	const VIDEO_URL_CACHING = 43200;	// 12h
	/**
	 * User-Agent to use for checks, Panopto eg. gives 500, with standard PHP User-Agent ;)
	 */
	const CHECK_USER_AGENT = 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/87.0.4280.88 Safari/537.36';

	/**
	 * Check mime-type and correctness of video URL (using a HEAD request)
	 *
	 * If Url has text/html content-type and $search_html > 0, we search the html for a video url.
	 *
	 * @param string $url
	 * @param string &$content_type=null on return content-type eg. "video/mp4" of returned url
	 * @param int $search_html=2 depth/levels of search for video-url in html content
	 * @return string url to use instead of $url
	 * @throws Api\Exception\WrongUserinput if video not accessible or wrong mime-type
	 */
	protected static function checkVideoURL($url, &$content_type=null, $search_html=2)
	{
		if ($url[0] === '/') return $url;	// our demo video

		if (($cached = Api\Cache::getInstance(__METHOD__, md5($url))))
		{
			list($ret, $content_type) = $cached;
			return $ret;
		}
		if (!preg_match(Api\Etemplate\Widget\Url::URL_PREG, $url) || parse_url($url, PHP_URL_SCHEME) !== 'https')
		{
			throw new Api\Exception\WrongUserinput(lang('Only https URL supported!'));
		}
		if (!($fd = fopen($url, 'rb', false, stream_context_create([
			'http' => [
				'method' => 'HEAD',
				'user_agent' => self::CHECK_USER_AGENT,
		]]))))
		{
			throw new Api\Exception\WrongUserinput(lang('Can NOT access the requested URL!'));
		}
		$metadata = stream_get_meta_data($fd);
		fclose($fd);

		$ret = $url;
		foreach ($metadata['wrapper_data'] as $header)
		{
			if (substr($header, 0, 5) === 'HTTP/' &&
				preg_match('|^HTTP/\d.\d (\d+)|', $header, $matches))
			{
				$headers = [$header];
				$status = $matches[1];
			}
			else
			{
				list($name, $value) = preg_split('/: */', $header, 2);
				$name = strtolower($name);
				if (isset($headers[$name]))
				{
					$headers[$name] .= ', '.$value;
				}
				else
				{
					$headers[$name] = $value;
				}
				if ($status[0] === '3' && preg_match('/^Location: *(https.*)/i', $header, $matches))
				{
					$ret = $matches[1];
				}
			}
		}
		list($content_type) = explode(';', $headers['content-type']);
		if ($search_html > 0 && $content_type === 'text/html')
		{
			$ret = self::searchHtml4VideoUrl($ret, $content_type, $search_html-1);
		}
		if (!isset($content_type) || !preg_match(self::VIDEO_MIME_TYPES, $content_type))
		{
			throw new Api\Exception\WrongUserinput(lang('Invalid type of video, please use mp4 or webm!'));
		}
		Api\Cache::setInstance(__METHOD__, md5($url), [$ret, $content_type], self::VIDEO_URL_CACHING);
		return $ret;
	}

	/**
	 * Search html of given URL for a video-url
	 *
	 * @param string $url url with text/html content type
	 * @param string &$content_type=null on return changed content-type
	 * @param int $search_html depth/levels of search for video-url in html content
	 * @return string new video-url with $content_type set, or old $url if not video-url found
	 */
	public static function searchHtml4VideoUrl($url, &$content_type=null, $search_html=1)
	{
		if (($html = file_get_contents($url, false, stream_context_create([
				'http' => ['user_agent' => self::CHECK_USER_AGENT]
			]), 0, 65636)))
		{
			// html5 video source-tag
			if (preg_match_all('#<source.*\s(src|type)="([^"]+)".*\s(src|type)="([^"]+)".*/?>#i', $html, $matches, PREG_SET_ORDER))
			{
				foreach($matches as $set)
				{
					$u = strtolower($set[1]) === 'src' ? $set[2] : $set[4];
					if (!preg_match('/#https?://#', $u))	// might be just path or relative path
					{
						$parts = parse_url($url);
						$u = $parts['scheme'].'://'.$parts['host'].(!empty($parts['port']) ? ':'.$parts['port'] : '').
							($u[0] !== '/' ? dirname($parts['path']).'/' : '').$u;
					}
					if (preg_match(self::VIDEO_MIME_TYPES, strtolower($set[1]) === 'type' ? $set[2] : $set[4]) &&
						preg_match('#^https://#', $u))
					{
						try {
							return self::checkVideoURL($u, $content_type, $search_html);
						}
						catch (Api\Exception\WrongUserinput $e) {
							// ignore exception and try next match
						}
					}
				}
			}
			// some header meta-tags
			if (preg_match_all('<meta (name="twitter:player:stream"|property="og:url") content="(https://[^"]+)">', $html, $matches))
			{
				foreach($matches[2] as $u)
				{
					try {
						return self::checkVideoURL($u, $content_type, $search_html);
					}
					catch (Api\Exception\WrongUserinput $e) {
						// ignore exception and try next match
					}
				}
			}
		}
		return $url;
	}

	/**
	 * Delete a video
	 *
	 * @param array $video values for keys video_id and optional course_id, video_hash and video_owner
	 * @param boolean $confirm_delete_comments =false true: to delete video, event if it has comments
	 * @throws Api\Exception\WrongParameter
	 * @throws Api\Exception\NoPermission
	 */
	function deleteVideo(array $video, $confirm_delete_comments = false)
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
		// delete overlay
		Overlay::delete(['course_id' => (int)$video['course_id'], 'video_id' => (int)$video['video_id']]);

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
	 * Forbid students to comment
	 */
	const COMMENTS_FORBIDDEN_BY_STUDENTS = 4;

	/**
	 * Video only visible to course-owner and -admins
	 */
	const VIDEO_DRAFT = 0;
	/**
	 * Video is published / fully available during video_published_start and _end (if set, if not unconditional)
	 */
	const VIDEO_PUBLISHED = 1;
	/**
	 * Video / test is unavailable for non-admins eg. for scoring
	 */
	const VIDEO_UNAVAILABLE = 2;
	/**
	 * Video is readonly eg. to allow students to check their scores, no changes allowed
	 */
	const VIDEO_READONLY = 3;

	/**
	 * Display test instead of comments
	 */
	const TEST_DISPLAY_COMMENTS = 0;
	/**
	 * Display test as (movable) dialog
	 */
	const TEST_DISPLAY_DIALOG = 1;
	/**
	 * Display test as overlay on the video
	 */
	const TEST_DISPLAY_VIDEO = 2;

	/**
	 * Allow to pause the test
	 */
	const TEST_OPTION_ALLOW_PAUSE = 1;
	/**
	 * Forbid to seek the video
	 */
	const TEST_OPTION_FORBID_SEEK = 2;

	/**
	 * Question can be skiped
	 */
	const QUESTION_SKIPABLE = 0;
	/**
	 * Question is required, must NOT be skiped
	 */
	const QUESTION_REQUIRED = 1;
	/**
	 * Question is timed, must be answered in given time
	 */
	const QUESTION_TIMED = 2;

	/**
	 * List comments of given video chronological
	 *
	 * @param ?int $video_id or null for comments of all videos
	 * @param array $where =[] further query parts eg.
	 * @param ?int $overwrite_video_options =null overwrite current video option(s) used eg. for disallowing students to export other students comments
	 * @return array comment_id => array of data pairs
	 * @throws Api\Exception\NoPermission
	 * @throws Api\Exception\WrongParameter
	 */
	public function listComments($video_id, array $where = [], $overwrite_video_options=null)
	{
		// ACL check
		if (!empty($video_id) && !($video = $this->readVideo($video_id)) ||
			!($course = $this->read($video['course_id'] ?: $where['course_id'])))
		{
			throw new Api\Exception\WrongParameter("Video #$video_id not found!");
		}
		if ($this->isAdmin($course))
		{
			// no comment filter for course-admin / teacher
		}
		elseif ($this->isParticipant($course))
		{
			$where = array_merge($where, $this->videoOptionsFilter(
				$overwrite_video_options ?? $video['video_options'], $course['course_owner'], $not_or_allowed_array));
		}
		else
		{
			throw new Api\Exception\NoPermission();
		}
		if (!empty($video_id)) $where['video_id'] = $video_id;

		$comments = $this->so->listComments($where);

		// if we filter comments, we also need to filter re-tweets
		if ($not_or_allowed_array)
		{
			foreach($comments as &$comment)
			{
				for ($i=1; $i < count($comment['comment_added']); $i += 2)
				{
					// if the re-tweet is NOT from an allowed user, remove it and all furter ones
					$from = $comment['comment_added'][$i];
					if (is_array($not_or_allowed_array) ? !in_array($from, $not_or_allowed_array) : $from != $not_or_allowed_array)
					{
						$comment['comment_added'] = array_slice($comment['comment_added'], 0, $i);
						break;
					}
				}
			}
		}
		return $comments;
	}

	/**
	 * Filter to list comments based on video-options
	 *
	 * @param int $video_options self::COMMENTS_*
	 * @param int $course_owner course-admin / teacher
	 * @param ?int|array $not_or_allowed_array (not) course_owner or array with allowed account_id or null
	 * @return array
	 */
	protected function videoOptionsFilter($video_options, $course_owner, &$not_or_allowed_array=null)
	{
		$filter = [];
		$not_or_allowed_array = null;
		switch ($video_options)
		{
			case self::COMMENTS_SHOW_ALL:
				break;
			case self::COMMENTS_HIDE_OWNER:
				$filter[] = 'account_id != ' . (int)$course_owner;
				$not_or_allowed_array = (int)$course_owner;
				break;
			case self::COMMENTS_HIDE_OTHER_STUDENTS:
				$filter['account_id'] = $not_or_allowed_array = [$this->user, $course_owner];
				break;
			case self::COMMENTS_SHOW_OWN:
				$filter['account_id'] = $this->user;
				$not_or_allowed_array = (array)$this->user;
				break;
		}
		return $filter;
	}

	static $csv_delimiter = ';';
	static $csv_enclosure = '"';
	static $csv_num_retweets = 5;
	/**
	 * @var array column-label => column-name pairs
	 */
	static $export_comment_cols = [
		'ID video' => 'video_id',
		'ID course' => 'course_id',
		'Videoname' => 'video_name',
		'Course-name' => 'course_name',
		'Date of annotation' => 'comment_created',
		'Videotimestamp' => 'comment_starttime',
		'ID Annotation' => 'comment_id',
		'ID User' => 'account_id',
		'User' => 'account_lid',
		'Last name, First Name' => 'account_fullname',
		'Comment' => 'comment_added[0]',
		'Field marking' => 'comment_marked',
		'Category' => 'comment_color',
		'Task' => 'video_question',
		'Re-Comment %1' => 'comment_added[2*%1]',
	];
	static $color2category = [
		'ffffff' => 'white',
		'ff0000' => 'red',
		'00ff00' => 'green',
	];

	/**
	 * Download comments of a course (and optional video) as CSV file
	 *
	 * @param int|array $course course_id or full course array
	 * @param ?int $video_id video_id to export only comments of a single video, default from all videos
	 * @throws Api\Exception\WrongParameter|Api\Exception\NoPermission
	 */
	public function downloadComments($course, $video_id=null, array $where=[])
	{
		if (!is_array($course) && !($course = $this->read($course)))
		{
			throw new Api\Exception\WrongParameter("Course not found!");
		}
		if ($this->isAdmin($course))
		{

		}
		elseif ($this->isParticipant($course))
		{
			// do NOT export full names to participants
			unset(self::$export_comment_cols[array_search('account_fullname', self::$export_comment_cols)]);

			// students are limited to export comments of one video, as options are video-specific
			if (!$video_id || !($video = $this->readVideo($video_id)))
			{
				throw new Api\Exception\NoPermission();
			}
			// limit students to only export their own comments, even if they are allowed to see other students comments
			if ($video['video_options'] == self::COMMENTS_SHOW_ALL)
			{
				$overwrite_options = self::COMMENTS_HIDE_OTHER_STUDENTS;
			}
		}
		else
		{
			throw new Api\Exception\NoPermission();
		}
		// multiply and translate re-tweet column
		if (isset(self::$export_comment_cols['Re-Comment %1']))
		{
			for ($i=1; $i <= self::$csv_num_retweets; ++$i)
			{
				self::$export_comment_cols[lang('Re-Comment %1', $i)] = 'comment_added['.(2*$i).']';
			}
			unset(self::$export_comment_cols['Re-Comment %1']);
		}
		Api\Header\Content::type($course['course_name'].'.csv', 'text/csv');
		echo self::csv_escape(array_map('lang', array_keys(self::$export_comment_cols)));

		$where['course_id'] = $course['course_id'];
		foreach($this->listComments($video_id, array_filter($where), $overwrite_options) as $row)
		{
			$row += $course;	// make course values availabe too
			if (!isset($video) || $video['video_id'] != $row['video_id'])
			{
				$video = $this->readVideo($row['video_id']);
			}
			$row += $video;

			$values = [];
			foreach(self::$export_comment_cols as $col)
			{
				// allow addressing / index into an array
				if (substr($col, -1) === ']' &&
					preg_match('/^([^\[]+)\[([^\]]+)\]/', $col, $matches) &&
					is_array($row[$matches[1]]))
				{
					$values[$col] = $row[$matches[1]][$matches[2]] ?? '';
				}
				elseif (in_array($col, ['account_lid', 'account_fullname']))
				{
					$values[$col] = $row['account_id'] ?? '';
				}
				else
				{
					$values[$col] = $row[$col] ?? '';
				}
			}
			echo self::csv_escape($values);
		}
		exit;
	}

	/**
	 * Escape csv values
	 *
	 * @param array $row data row name => value pairs
	 * @param array $types optional name => type pairs
	 * @return string
	 */
	public static function csv_escape(array $row)
	{
		foreach($row as $name => &$value)
		{
			switch ((string)$name)
			{
				case 'comment_color':
					$value = self::$csv_enclosure.lang(self::$color2category[$value] ?? $value ?? '').self::$csv_enclosure;
					break;
				case 'comment_marked':
					$value = (int)!empty($value);
					break;
				case 'comment_starttime':	// seconds -> h:mm:ss
					$value = sprintf('%0d:%02d:%02d', floor($value / 3600), floor(($value % 3600) / 60), $value % 60);
					break;
				case 'comment_created':
					if (!empty($value)) $value = Api\DateTime::to($value);
					break;
				case 'video_id':
				case 'course_id':
				case 'comment_id':
				case 'account_id':
					break;	// already an int
				case 'account_lid':
				case 'account_fullname':	// Lastname, Firstname
					$value = $name === 'account_lid' ? Api\Accounts::username($value) :
						Api\Accounts::id2name($value, 'account_lastname').', '.
						Api\Accounts::id2name($value, 'account_firstname');
					// fall-through
				default:	// string
					$value = self::$csv_enclosure.
						str_replace(self::$csv_enclosure, self::$csv_enclosure.self::$csv_enclosure, $value).
						self::$csv_enclosure;
					break;
			}
		}
		$line = implode(self::$csv_delimiter, $row);

		// in case a different csv charset is set, convert to it
		if ($GLOBALS['egw_info']['user']['preferences']['common']['csv_charset'] !== 'utf-8')
		{
			$line = Api\Translation::convert($line, 'utf-8', $GLOBALS['egw_info']['user']['preferences']['common']['csv_charset']);
		}
		return $line."\n";
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
			// check students are allowed to comment
			if (($video = $this->readVideo($comment['video_id'])) &&
			    $video['video_options'] == self::COMMENTS_FORBIDDEN_BY_STUDENTS &&
			    !$this->isAdmin($comment['course_id']))
			{
				throw new Api\Exception\NoPermission();
			}
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
		switch ($comment['action'])
		{
			case 'add':
				$to_save = [
					'course_id' => $comment['course_id'],
					'video_id' => $comment['video_id'],
					'account_id' => $this->user,
					'comment_added' => [$comment['text']],
					'comment_starttime' => $comment['comment_starttime'],
					'comment_stoptime' => $comment['comment_stoptime'] ?: $comment['comment_starttime'],
					'comment_color' => $comment['comment_color'],
					'comment_marked' => $comment['comment_marked'],
					'comment_deleted' => 0,
					'comment_created' => new Api\DateTime('now'),
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
	public function isAdmin($course = null)
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
		if (!is_array($course) && !($course = $this->so->read(['course_id' => $id = $course])))
		{
			throw new Api\Exception\WrongParameter("Course #$id not found!");
		}
		if (!isset($course['participants']))
		{
			$course['participants'] = $this->so->participants($course['course_id']);
		}
		foreach ($course['participants'] as $participant)
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
	public static function setAdmin($account_id, $allow = true)
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
	 *    true to not check the password (used when accessing a course via LTI)
	 * @return bool
	 * @throws Api\Exception\WrongParameter invalid $course_id
	 * @throws Api\Exception\WrongUserinput wrong password
	 */
	public function checkSubscribe($course_id, $password)
	{
		if (!($course = $this->read($course_id, false)))    // false: do not check for subscribed
		{
			throw new Api\Exception\WrongParameter("Course #$course_id not found!");
		}
		if ($course['course_closed'])
		{
			throw new Api\Exception\WrongParameter("Course #$course_id is already closed!");
		}
		if ($password !== true && !empty($course['course_password']) &&
			!(password_verify($password, $course['course_password']) ||
				// check for passwords in cleartext, if configured
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
	 *    true to not check the password (used when accessing a course via LTI)
	 * @throws Api\Exception\WrongParameter invalid $course_id
	 * @throws Api\Exception\WrongUserinput wrong password
	 * @throws Api\Exception\NoPermission
	 * @throws Api\Db\Exception
	 */
	public function subscribe($course_id, $subscribe = true, $account_id = null, $password = null)
	{
		if ((isset($account_id) && $account_id != $this->user))
		{
			foreach ((array)$course_id as $id)
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
		foreach ((array)$course_id as $id)
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
	function read($keys, $check_subscribed = true)
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
	function save($keys = null, $extra_where = null)
	{
		// hash password if not "cleartext" storage is configured and user changed it
		if ($this->config['coursepassword'] !== 'cleartext' &&
			substr($keys['course_password'], 0, 4) !== self::PASSWORD_HASH_PREFIX)
		{
			$keys['course_password'] = password_hash($keys['course_password'], PASSWORD_BCRYPT);
		}
		if (($err = $this->so->save($keys)))
		{
			throw new Ap\Db\Exception(lang('Error saving course!'));
		}
		$course = $this->so->data;

		// subscribe teacher/course-admin to course (true to not check/require password)
		if (empty($keys['course_id'])) $this->subscribe($course['course_id'], true, null, true);

		$course['participants'] = $keys['participants'] ?: [];
		$course['videos'] = $keys['videos'] ?: [];

		foreach ($course['videos'] as $key => &$video)
		{
			if (!$video || !is_int($key)) continue;    // leave UI added empty lines or other stuff alone

			if (is_array($video['video_test_options']))
			{
				$test_options = $video['video_test_options']; $video['video_test_options'] = 0;
				foreach($test_options as $mask)
				{
					$video['video_test_options'] |= $mask;
				}
			}
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
			'course_options' => 0,
		];
	}

	/**
	 * Get name of course identified by $entry
	 *
	 * Is called as hook to participate in the linking
	 *
	 * @param int|array $entry int course_id or array with course data
	 * @return string/boolean string with title, null if course not found, false if no perms to view it
	 */
	function link_title($entry)
	{
		if (!is_array($entry))
		{
			// need to preserve the $this->data
			$backup =& $this->data;
			unset($this->data);
			$entry = $this->read(['course_id' => $entry], false);
			// restore the data again
			$this->data =& $backup;
		}
		if (!$entry)
		{
			return $entry;
		}
		return $entry['course_name'];
	}

	/**
	 * Query smallPART for courses matching $pattern
	 *
	 * Is called as hook to participate in the linking
	 *
	 * @param string $pattern pattern to search
	 * @param array $options Array of options for the search
	 * @return array with course_id - title pairs of the matching entries
	 */
	function link_query($pattern, array &$options = array())
	{
		$limit = false;
		$need_count = false;
		if ($options['start'] || $options['num_rows'])
		{
			$limit = array($options['start'], $options['num_rows']);
			$need_count = true;
		}
		$result = [];
		foreach ($this->search($pattern, false, '', '', '%', false, 'OR', $limit, null, '', $need_count) as $row)
		{
			$result[$row['course_id']] = $this->link_title($row);
		}
		$options['total'] = $need_count ? $this->total : count($result);
		return $result;
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
	public function recordWatched(array $data, $account_id = null, $watch_id = null)
	{
		return $this->so->recordWatched($data, $account_id ?: $this->user, $watch_id);
	}
}