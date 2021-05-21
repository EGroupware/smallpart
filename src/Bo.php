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
 * - videos have following published states
 *   + Draft (not listed or accessible for students)
 *   + Published with optional begin and/or end (always listed, but only accessible inside timeframe to students)
 *   + Unavailble (listed, but not accessible for students, eg. while scoring tests)
 *   + Readonly (listed, accessible, but no longer modifyable)
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
	 * @param ?array $last if given, only write if $data contains a position for same video
	 * @param int $account_id =null default $this->user
	 * @return ?boolean
	 * @throws Api\Exception\WrongParameter
	 */
	public function setLastVideo(array $data, array $last=null, $account_id = null)
	{
		if (!isset($last) || $last['video_id'] == $data['video_id'] && isset($data['position']))
		{
			return $this->so->setLastVideo($data, $account_id ?: $this->user);
		}
		return null;
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
	 * Draft videos/tests are NOT listed for participants, every other state is!
	 *
	 * @param array $where video_id or query eg. ['video_id' => $ids]
	 * @param bool $name_only =false true: return name as value
	 * @return array video_id => array with data pairs or video_name, if $name_only
	 */
	public function listVideos(array $where, $name_only=false)
	{
		// hide draft videos from non-admins
		if (!empty($where['course_id']) && ($no_drafts = !$this->isAdmin($where)))
		{
			$where[] = 'video_published != '.self::VIDEO_DRAFT;
		}
		$videos = $this->so->listVideos($where);
		foreach ($videos as $video_id => &$video)
		{
			if (!isset($no_drafts) && $video['video_published'] == self::VIDEO_DRAFT && !$this->isAdmin($video))
			{
				continue;
			}
			if ($name_only)
			{
				$video = $this->videoLabel($video);
			}
			else
			{
				// do not make sensitive information (video, question) available to participants
				if (!($video['accessible'] = $this->videoAccessible($video, $video['is_admin'], true, $video['error_msg'])))
				{
					unset($video['video_src'], $video['video_url'], $video['video_question']);
				}
				else
				{
					try {
						$video['video_src'] = $this->videoSrc($video);
					}
					catch (\Exception $e) {
						// ignore error to not stall whole UI or other videos
					}
				}
			}
		}
		return $videos;
	}

	/**
	 * Create a video-label by appeding the status after the name
	 *
	 * @param array $video
	 * @return mixed|string
	 */
	public static function videoLabel(array $video)
	{
		$label = $video['video_name'];

		switch($video['video_published'])
		{
			case self::VIDEO_DRAFT:
				$label .= ' ('.lang('Draft').')';
				break;
			case self::VIDEO_PUBLISHED:
				if (isset($video['video_published_start'], $video['video_published_end']) &&
					Api\DateTime::to($video['video_published_start'], 'ts') > Api\DateTime::to('now', 'ts'))
				{
					$label .= ' ('.Api\DateTime::to($video['video_published_start']).' - '.
						Api\DateTime::to($video['video_published_end']).')';
				}
				elseif (isset($video['video_published_end']))
				{
					$label .= ' ( - '.Api\DateTime::to($video['video_published_end']).')';
				}
				elseif (isset($video['video_published_start']))
				{
					$label .= ' ('.Api\DateTime::to($video['video_published_start']).' - )';
				}
				/* don't display unconditional published status
				else
				{
					$label .= ' ('.lang('Published').')';
				}*/
				break;
			case self::VIDEO_UNAVAILABLE:
				$label .= ' ('.lang('Unavailable').')';
				break;
			case self::VIDEO_READONLY:
				$label .= ' ('.lang('Readonly').')';
				break;
		}
		return $label;
	}

	/**
	 * Check if video is accessible by current user
	 *
	 * @param int|array $video video_id or video-data
	 * @param ?boolean& $is_admin =null on return true: for course-admins, false: participants, null: neither
	 * @param bool $check_test_running
	 * @param ?string& $error_msg reason why returning false
	 * @return boolean|"readonly"|null true: accessible by students, false: not accessible, only "readonly" accessible
	 * 	null: test not yet running, but can be started by participant
	 * @throws Api\Exception\WrongParameter
	 */
	public function videoAccessible($video, &$is_admin=null, $check_test_running=true, &$error_msg=null)
	{
		if (is_scalar($video) && !($video = $this->readVideo($video)))
		{
			$is_admin = null;
			$error_msg = lang('Entry not found!');
			return false;
		}
		$is_admin = $this->isAdmin($video['course_id']) ?:
			($this->isParticipant($video['course_id']) ? false : null);

		// no admin or participant --> no access
		if (!isset($is_admin))
		{
			$error_msg = lang('Permission denied!');
			return false;
		}
		// apply readonly for course-admins too, thought they can change the status
		if ($video['video_published'] == self::VIDEO_READONLY)
		{
			return "readonly";
		}
		// course admins always have access to all videos
		if ($is_admin)
		{
			return true;
		}
		$now = new Api\DateTime('now');
		// participants only if video is published AND in (optional) time-frame OR readonly
		if ($video['video_published'] == self::VIDEO_PUBLISHED &&
			(isset($video['video_published_start']) && $video['video_published_start'] > $now ||
			(isset($video['video_published_end']) && $video['video_published_end'] <= $now)))
		{
			$error_msg = lang('Access outside publishing timeframe!');
			return false;
		}
		// if we have a test-duration, check if test is started and still running
		if ($check_test_running && $video['video_test_duration'] > 0)
		{
			return $this->testRunning($video, $time_left, $error_msg);
		}
		if ($video['video_published'] != self::VIDEO_PUBLISHED)
		{
			$error_msg = lang('This video is currently NOT accessible!');
		}
		return $video['video_published'] == self::VIDEO_PUBLISHED;
	}

	/**
	 * Check if video is published: state not draft or before publishing date
	 *
	 * @param int|array $video
	 */
	public function videoPublished($video)
	{
		if (is_scalar($video) && !($video = $this->readVideo($video)))
		{
			throw new Api\Exception\NotFound();
		}
		return $video['video_published'] && (!isset($video['video_published_start']) ||
			$video['video_published_start'] >= new Api\DateTime('now'));
	}

	/**
	 * Check test currently running
	 *
	 * @param int|array $video video_id or full video array incl. course_id
	 * @param int& $time_left=null time left from test duration or overall test time-frame
	 * @param ?string& $error_msg reason why returning false
	 * @return ?bool true: running for $time_left more seconds, null: can be started, false otherwise
	 */
	public function testRunning($video, &$time_left=null, &$error_msg=null)
	{
		if (!is_array($video) && !($video = $this->readVideo($video)))
		{
			throw new Api\Exception\NotFound();
		}
		$now = new Api\DateTime('now');

		if ($video['video_published'] != self::VIDEO_PUBLISHED ||
			isset($video['video_published_start']) && $video['video_published_start'] > $now ||
			isset($video['video_published_end']) && $video['video_published_end'] <= $now)
		{
			$error_msg = lang('Access outside publishing timeframe!');
			return false;	// not ready to start
		}
		$start = Overlay::testStarted($video['video_id'], null, $time);
		$time_left = 60*$video['video_test_duration'] - $time;
		if ($start === false || $time_left < 0)
		{
			$error_msg = lang('You already completed this test!');
		}
		return !$start ? $start : $time_left > 0;
	}

	/**
	 * Start test for current user
	 *
	 * @param int|array $video video_id or full video array incl. course_id
	 * @param ?int& $video_time on return time of video when test was paused/stopped
	 * @return int
	 * @throws Api\Exception\NoPermission not inside test timeframe or no participant
	 * @throws Api\Exception\NotFound wrong video(_id)
	 * @throws Api\Exception\WrongParameter test already started
	 */
	public function testStart($video, &$video_time)
	{
		if (!is_array($video) && !($video = $this->readVideo($video)))
		{
			throw new Api\Exception\NotFound();
		}
		if (!$this->videoAccessible($video, $is_admin, false))
		{
			throw new Api\Exception\NoPermission();
		}
		return Overlay::testStart($video['video_id'], $video['course_id'], null, $is_admin, $video_time);
	}

	/**
	 * Stop (or pause) running test
	 *
	 * Only paused tests can be restarted restarted once stopped.
	 * Pause must be explicitly allowed in video_test_options.
	 *
	 * @param int|array $video video_id or full video array incl. course_id
	 * @param bool $stop =true true: stop, false: pause
	 * @param ?int $video_time
	 * @throws Api\Exception\NotFound video_id not found
	 * @throws Api\Exception\NoPermission video no accessible or pause not allowed
	 * @throws Api\Exception\WrongParameter test not running
	 */
	public function testStop($video, $stop=true, $video_time=null)
	{
		if (!is_array($video) && !($video = $this->readVideo($video)))
		{
			throw new Api\Exception\NotFound();
		}
		if ($video['accessible'] !== true || !$stop && !($video['video_test_options'] & self::TEST_OPTION_ALLOW_PAUSE))
		{
			throw new Api\Exception\NoPermission();
		}
		Overlay::testStop($video['video_id'], $video['course_id'], $stop, $video_time);
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
	const VIDEO_MIME_TYPES = '#(^|, )(video/(mp4|webm))(, |$)#i';

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
	 * Regular expression to validate Youtube URL and extract video-id (last group)
	 */
	const YOUTUBE_PREG = '/^https:\/\/((www\.|m\.)?youtube(-nocookie)?\.com|youtu\.be)\/.*(?:\/|%3D|v=|vi=)([0-9A-z-_]{11})(?:[%#?&]|$)/m';

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
		// validate and parse a Youtube URL
		if (preg_match(self::YOUTUBE_PREG, $url, $matches))
		{
			$config = Api\Config::read(self::APPNAME);
			if (empty($config['youtube_videos']))
			{
				throw new Api\Exception\WrongUserinput(lang('YouTube videos are NOT enabled! To enable go to Admin > Applications > SmallPART > Site configuration'));
			}
			else
			{
				$youtube_url = $url;
				$youtube_id = array_pop($matches);
				// try reading the poster image to validate the id exits
				$url = "https://img.youtube.com/vi/$youtube_id/mqdefault.jpg";
			}
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

		if (isset($youtube_url))
		{
			$content_type = 'video/youtube';	// not realy a content-type ;)
			$ret = $youtube_url;
		}
		else
		{
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
						$headers[$name] .= ', ' . $value;
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
				$ret = self::searchHtml4VideoUrl($ret, $content_type, $search_html - 1);
			}
			if (!isset($content_type) || !preg_match(self::VIDEO_MIME_TYPES, $content_type, $matches))
			{
				throw new Api\Exception\WrongUserinput(lang('Invalid type of video, please use mp4 or webm!'));
			}
			if (!empty($matches[2])) $content_type = $matches[2];
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
					if (!preg_match('#https?://#', $u))	// might be just path or relative path
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
			// see https://ogp.me/ for property="og:*"
			if (preg_match_all('#<meta (name="twitter:player:stream"|property="og:video"|property="og:url") content="(https://[^"]+)"#', $html, $matches))
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
			// Seafile server URL containing javascript object with rawPath: '<url-with-unicode>',
			if (preg_match("/rawPath: *'([^']+)'/", $html, $matches))
			{
				try {
					return self::checkVideoURL(json_decode('"'.$matches[1].'"'), $content_type, $search_html);
				}
				catch (Api\Exception\WrongUserinput $e) {
					// ignore exception and try next match
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
		// do we need to check if video has comments or answers, or just delete them
		if (!$confirm_delete_comments)
		{
			$comments = $this->so->listComments(['video_id' => $video['video_id']]);
			$answers = Overlay::countAnswers($video['video_id']);
			if ($comments || $answers)
			{
				throw new Api\Exception\WrongParameter(lang('This video has %1 comments and %2 answers! Click on delete again to really delete it.',
					count($comments), $answers));
			}
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
	 * Disable comments, eg. for tests
	 */
	const COMMENTS_DISABLED = 5;

	/**
	 * Only list comments of video owner, no student comments
	 */
	const COMMENTS_OWNER_ONLY = 6;

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
			case self::COMMENTS_DISABLED:
				$filter[] = '1=0';
				break;
			case self::COMMENTS_OWNER_ONLY:
				$filter['account_id'] = $not_or_allowed_array = [$course_owner];
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
		// check ACL, need to be a participants to comment AND video need to be full accessible (not just  "readonly")
		if (!$this->isParticipant($comment['course_id']) || $this->videoAccessible($comment['video_id']) !== true)
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
	 * @param ?int $account_id
	 * @return bool
	 */
	public static function checkAdmin($account_id=null)
	{
		static $admins;
		if (!isset($admins))
		{
			$admins = $GLOBALS['egw']->acl->get_ids_for_location(self::ACL_ADMIN_LOCATION, 1, self::APPNAME);
		}
		return self::isSuperAdmin() || in_array($account_id ?? $GLOBALS['egw_info']['user']['account_id'], $admins, false);
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
		// do not check for subscribed, nor for LTI (password === true) check ACL (as handled by LTI platform)
		if (!($course = $this->read($course_id, false, $password !== true)))
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
	 * @param bool $check_subscribed=true false: do NOT check if current user is subscribed, but use ACL to check visibility of course
	 * @param bool $check_acl=true false: do NOT check if current user has read rights to the course
	 * @return array|boolean data if row could be retrieved else False
	 * @throws Api\Exception\NoPermission if not subscribed
	 * @throws Api\Exception\WrongParameter
	 */
	function read($keys, bool $check_subscribed = true, bool $check_acl = true)
	{
		if (!is_array($keys)) $keys = ['course_id' => $keys];

		// ACL filter (expanded by so->search to (course_owner OR course_org)
		if (!$check_subscribed && $check_acl)
		{
			$keys['acl'] = array_keys($this->grants);
		}

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
		if (!$this->isAdmin($keys['course_id']))
		{
			throw new Api\Exception\NoPermission("You have no permission to update course with ID '$keys[course_id]'!");
		}
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
			$video['course_id'] = $course['course_id'];
			$video['video_id'] = $this->so->updateVideo($video);
		}
		return $course;
	}

	/**
	 * Save a single video
	 *
	 * @param array $video
	 * @return int
	 * @throws Api\Db\Exception
	 * @throws Api\Exception\WrongParameter
	 */
	function saveVideo(array $video)
	{
		return $this->so->updateVideo($video);
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
		return $this->so->lastWatched($course_id, $video_id, $account_id);
	}
}