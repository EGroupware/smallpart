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
use EGroupware\Api\Etemplate;
use EGroupware\Api\Acl;
use EGroupware\Api\Link;
use EGroupware\Api\Vfs;
use MongoDB\Exception\InvalidArgumentException;

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
 * - courses can be edited / administrated by:
 *   + EGroupware administrators
 *   + course-owner/-admin
 *   + users with explicit edit grant of the course-owner (proxy rights)
 *
 * - videos have following published states
 *   + Draft (not listed or accessible for students)
 *   + Published with optional begin and/or end (always listed, but only accessible inside timeframe to students)
 *   + Unavailable (listed, but not accessible for students, eg. while scoring tests)
 *   + Readonly (listed, accessible, but no longer modifiable)
 *
 * --> implicit rights match old smallPART app with an organisation field migrated to primary group of the users
 * --> explicit rights allow to make courses available outside the organisation or delegate admin rights to a proxy
 */
class Bo
{
	const APPNAME = 'smallpart';
	const ACL_ADMIN_LOCATION = 'admin';

	/**
	 * Allow r/o teacher interface
	 */
	const ACL_READ = 1;
	/**
	 * Allow all modification in teacher interface, but administrative stuff like locking a course
	 */
	const ACL_MODIFY = 2;
	/**
	 * Allow administrative stuff like locking a course
	 */
	const ACL_ADMIN = 4;
	/**
	 * Roles are combinations of ACL_* bits
	 */
	const ROLE_STUDENT = 0;
	const ROLE_TUTOR = self::ACL_READ;
	const ROLE_TEACHER = self::ACL_READ | self::ACL_MODIFY;
	const ROLE_ADMIN = self::ACL_READ | self::ACL_MODIFY | self::ACL_ADMIN;

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
	 * @var self
	 */
	private static $instance;

	/**
	 * Constructor
	 *
	 * @param int $account_id =null default current user
	 */
	public function __construct(int $_account_id = null)
	{
		$this->user = $_account_id ?: (int)$GLOBALS['egw_info']['user']['account_id'];
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

		if ($this->user == $GLOBALS['egw_info']['user']['account_id'])
		{
			self::$instance = $this;
		}
	}

	/**
	 * Singleton to get instance of Bo class (for current user)
	 *
	 * @return Bo
	 */
	public static function getInstance()
	{
		if (!isset(self::$instance))
		{
			self::$instance = new self();
		}
		return self::$instance;
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
		// never show closed courses, unless explicitly filtered by them
		$query['col_filter']['course_closed'] = '0';

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
				if (self::checkTeacher())
				{
					$query['col_filter']['course_closed'] = 1;    // only closed
				}
				break;
		}
		// ACL filter (expanded by so->search to (course_owner OR course_org)
		// as Bo does NOT extend So, $this->so->get_rows() does NOT call Bo::search() and therefore we need the ACL filter here too
		$query['col_filter']['acl'] = array_keys($this->grants);

		return $this->so->get_rows($query, $rows, $readonlys);
	}

	/**
	 * Return criteria array for a given search pattern
	 *
	 * We handle quoted text, wildcards and boolean operators (+/-, AND/OR).  If
	 * the pattern is '#' followed by an integer, the search is limited to just
	 * the primary key.
	 *
	 * @param string $_pattern search pattern incl. * or ? as wildcard, if no wildcards used we append and prepend one!
	 * @param string &$wildcard ='' on return wildcard char to use, if pattern does not already contain wildcards!
	 * @param string &$op ='AND' on return boolean operation to use, if pattern does not start with ! we use OR else AND
	 * @param string $extra_col =null extra column to search
	 * @param array $search_cols =[] List of columns to search.  If not provided, all columns in $this->db_cols will be considered
	 *  allows to specify $search_cfs parameter with key 'search_cfs', which has precedence over $search_cfs parameter
	 * @param null|bool|string|string[] $search_cfs null: do it only for Api\Storage, false: never do it, or string type(s) of cfs to search, e.g. "url-email"
	 * @return array or column => value pairs
	 */
	public function search2criteria($_pattern,&$wildcard='',&$op='AND',$extra_col=null, $search_cols=[],$search_cfs=null)
	{
		return $this->so->search2criteria($_pattern, $wildcard, $op, $extra_col, $search_cols, $search_cfs);
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
	 * List (subscribed) courses of current user
	 *
	 * @param bool $subscribed=true true: show only subscribed course, show all courses user has rights to see
	 * @return array course_id => course_name pairs
	 */
	public function listCourses($subscribed=true)
	{
		return $this->so->query_list('course_name', So::COURSE_TABLE . '.course_id AS course_id',
			$subscribed ? ['account_id' => $this->user] : [], 'course_name ASC');
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
		// hide draft videos from non-staff
		if (!empty($where['course_id']) && ($no_drafts = !$this->isTutor($where)))
		{
			$where[] = 'video_published != '.self::VIDEO_DRAFT;
		}
		$videos = $this->so->listVideos($where);
		foreach ($videos as $video_id => &$video)
		{
			if (!isset($no_drafts) && $video['video_published'] == self::VIDEO_DRAFT && !$this->isTutor($video) ||
				// if access to material is limited (beyond course-participants), check current user has access (staff always has!)
				$video['video_limit_access'] && !$this->isTutor($video) && !in_array($this->user, $video['video_limit_access']))
			{
				unset($videos[$video_id]);
				continue;
			}
			if ($name_only)
			{
				$video = self::videoLabel($video);
			}
			else
			{
				$video['status'] = self::videoStatus($video);
				switch($video['video_type'])
				{
					case 'pdf':
						$video['mime_type'] = 'application/pdf';
						break;
					case 'youtube':
						$video['mime_type'] = 'video/x-youtube';
						break;
					default:
						$video['mime_type'] = 'video/'.$video['video_type'];
						break;
				}
				if (($lf = $this->so->readLivefeedback($video['course_id'], $video_id)))
				{
					$video['livefeedback'] = $lf;
					$video['livefeedback_session'] = !empty($lf['session_endtime']) ? 'ended' : (!empty($lf['session_starttime']) ? 'running' : 'not-started');
					$video['mime_type'] = 'video/x-livefeedback';
				}
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

				if ($video['video_type'] == 'webm' && is_array($lf) && !empty($lf['session_endtime']) && !empty($lf['session_starttime']))
				{
					// webm video has issues with providing duration because browser needs to load the whole file before being able to
					// show its duration. In order to tackle this issue we just calculate the duration time base on session time and send it
					// via duration url param to be processed in client-side video widget
					$video['video_src'] = $video['video_src'].'?duration='. (Api\DateTime::to($lf['session_endtime'], 'ts') - Api\DateTime::to($lf['session_starttime'], 'ts'));
				}
			}
		}
		return $videos;
	}

	/**
	 * Create a video-label by appending the status in brackets after the name
	 *
	 * @param array $video
	 * @return mixed|string
	 */
	public static function videoLabel(array $video)
	{
		$label = $video['video_name'];

		if (($status = self::videoStatus($video)) !== lang('Published'))
		{
			$label .= ' (' . $status . ')';
		}
		return $label;
	}

	/**
	 * Get the translated status of a material: draft, published, ...
	 *
	 * @param array $video
	 * @return string
	 */
	public static function videoStatus(array $video)
	{
		switch($video['video_published'])
		{
			case self::VIDEO_DRAFT:
				$status = lang('Draft');
				break;
			case self::VIDEO_PUBLISHED:
				if (isset($video['video_published_start'], $video['video_published_end']) &&
					Api\DateTime::to($video['video_published_start'], 'ts') > Api\DateTime::to('now', 'ts'))
				{
					$status = Api\DateTime::server2user($video['video_published_start'], '').' - '.
						Api\DateTime::server2user($video['video_published_end'], '');
				}
				elseif (isset($video['video_published_end']))
				{
					$status = '- '.Api\DateTime::server2user($video['video_published_end'], '');
				}
				elseif (isset($video['video_published_start']))
				{
					$status = Api\DateTime::server2user($video['video_published_start'], '').' -';
				}
				else
				{
					$status = lang('Published');
				}
				break;
			case self::VIDEO_UNAVAILABLE:
				$status = lang('Unavailable');
				break;
			case self::VIDEO_READONLY:
				$status = lang('Readonly');
				break;
		}
		if ($video['video_test_duration'] || $video['video_test_options'] || $video['video_test_display'])
		{
			$status = ($video['video_test_duration'] ? lang('Test %1min', $video['video_test_duration']) : lang('Test')).
				', '.$status;
		}
		return $status;
	}

	/**
	 * Check if video is accessible by current user
	 *
	 * @param int|array $video video_id or video-data
	 * @param ?boolean& $is_admin =null on return true: for course-admins, false: participants, null: neither
	 * @param bool $check_test_running
	 * @param ?string& $error_msg reason why returning false
	 * @param bool $check_as_student =false true: check for student/participant ignoring possible higher role of current user
	 * @return boolean|"readonly"|null true: accessible by students, false: not accessible, only "readonly" accessible
	 * 	null: test not yet running, but can be started by participant
	 * @throws Api\Exception\WrongParameter
	 */
	public function videoAccessible($video, &$is_admin=null, $check_test_running=true, &$error_msg=null, $check_as_student=false)
	{
		if (is_scalar($video) && !($video = $this->readVideo($video)))
		{
			$is_admin = null;
			$error_msg = lang('Entry not found!');
			return false;
		}
		$is_admin = !$check_as_student && $this->isTutor($video['course_id']) ?:
			($this->isParticipant($video['course_id']) ? false : null);

		// no admin or participant --> no access
		if(!isset($is_admin) && !$this->isParticipant($video['course_id']))
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
			(isset($video['video_published_start']) && $video['video_published_start'] && $video['video_published_start'] > $now ||
				(isset($video['video_published_end']) && $video['video_published_end'] && $video['video_published_end'] <= $now)))
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

	const OPTION_CL_MEASUREMENT = 5;

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
		// check ACL, do NOT allow to start test on "readonly" video
		if ($this->videoAccessible($video, $is_admin, false) !== true)
		{
			throw new Api\Exception\NoPermission();
		}
		$ret = Overlay::testStart($video['video_id'], $video['course_id'], null, $is_admin, $video_time);

		if (($course = $this->read($video['course_id'])) && ($course['course_options'] & self::OPTION_CL_MEASUREMENT) === self::OPTION_CL_MEASUREMENT)
		{
			$this->recordCLMeasurement($video['course_id'], $video['video_id'], 'start', []);
		}
		return $ret;
	}

	/**
	 * Stop (or pause) running test
	 *
	 * Only paused tests can be restarted once stopped.
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

		if (($course = $this->read($video['course_id'])) && ($course['course_options'] & self::OPTION_CL_MEASUREMENT) === self::OPTION_CL_MEASUREMENT)
		{
			$this->recordCLMeasurement($video['course_id'], $video['video_id'], $stop ? 'stop' : 'pause', []);
		}
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
	 * Read video incl. attachments
	 *
	 * @param int|array $video video_id or video array
	 * @return array
	 */
	public function readVideoAttachments($video)
	{
		if (!is_array($video))
		{
			$video = $this->readVideo($video);
		}
		$upload_path = '/apps/smallpart/' . (int)$video['course_id'] . '/' . (int)$video['video_id'] . '/all/task/';
		if(Api\Vfs::file_exists($upload_path) && !empty($attachments = Etemplate\Widget\Vfs::findAttachments($upload_path)))
		{
			$video[$upload_path] = $attachments;
		}

		return $video;
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

		if (!file_exists($dir) && (!$create_dir || !mkdir($dir, 0755, true)) || !is_dir($dir))
		{
			throw new Api\Exception\WrongParameter("Video directory '$dir' does not exist!");
		}
		return $dir . '/' . $video['video_hash'] . '.' . $video['video_type'];
	}

	/**
	 * Allowed MIME types
	 */
	const VIDEO_MIME_TYPES = '#(^|, )(video/(mp4|webm))|(application/pdf)(, |$)#i';

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
		if (!$this->isTeacher($course))
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
				'video_type' => explode('/', $mime_type)[1],
				'video_hash' => Api\Auth::randomstring(64),
			];
			if (!is_resource($upload['tmp_name']) ?
				!copy($upload['tmp_name'], $this->videoPath($video, true)) :
				(($fp=fopen($this->videoPath($video, true), 'w+')) ?
					stream_copy_to_stream($upload['tmp_name'], $fp) && fclose($fp) : false) === false)
			{
				throw new Api\Exception\WrongUserinput(lang("Failed to store uploaded video!"));
			}
		}
		$video['video_id'] = $this->so->updateVideo($video);
		$video['video_src'] = $this->videoSrc($video);

		return $video;
	}

	/**
	 * @param int|array $video
	 * @param array $upload array with values for keys "tmp_name" (path or resource) and "type" (content-type)
	 * @return void
	 * @throws Api\Db\Exception
	 * @throws Api\Exception\NotFound
	 * @throws Api\Exception\WrongParameter
	 * @throws Api\Exception\WrongUserinput
	 */
	public function updateVideo($video, array $upload)
	{
		if (is_scalar($video) && !($video = $this->readVideo($video)))
		{
			throw new Api\Exception\NotFound();
		}
		$old_video_path = $video['video_hash'] ? $this->videoPath($video) : null;
		$type = explode('/', $upload['type'])[1];
		$video_path = $this->videoPath($video=[
			'video_type' => $type,
			'video_hash' => $video['video_hash']??Api\Auth::randomstring(64),
			'video_url' => null,
		]+$video, true);

		if (!is_resource($upload['tmp_name']) ? !copy($upload['tmp_name'], $video_path) :
			(($fp = fopen($video_path, 'w+')) ?
				stream_copy_to_stream($upload['tmp_name'], $fp) && fclose($fp) : false) === false ||
			!file_exists($video_path))
		{
			throw new Api\Exception\WrongUserinput(lang("Failed to store uploaded video!"));
		}
		$this->so->updateVideo($video);
		if ($old_video_path && $old_video_path != $video_path && file_exists($old_video_path))
		{
			unlink($old_video_path);
		}
	}

	function addLivefeedback($course, $video)
	{
		if (!$this->isTeacher($course))
		{
			throw new Api\Exception\NoPermission();
		}
		$data = [
			'course_id' => $course,
			'video_id' => $video['video_id']
		];
		$this->so->saveLivefeedback($data);
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
	public static function checkVideoURL($url, &$content_type=null, $search_html=2)
	{
		if ($url[0] === '/') return $url;	// our demo video

		$cache_location = md5($url);
		if(($cached = Api\Cache::getInstance(__METHOD__, $cache_location)))
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
			$content_type = 'video/youtube';	// not really a content-type ;)
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
			if (preg_match('/^application\/pdf/i', $content_type, $matches))
			{
				$content_type = 'video/pdf'; // content type expects to have video/ as prefix
			}
		}
		Api\Cache::setInstance(__METHOD__, $cache_location, [$ret, $content_type], self::VIDEO_URL_CACHING);
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
	 * Completely remove a course and all associated information
	 *
	 * @param int|int[] $course_id
	 * @return false|void
	 * @throws Api\Db\Exception
	 * @throws Api\Exception
	 * @throws Api\Exception\NoPermission
	 * @throws Api\Exception\WrongParameter
	 * @throws Api\Json\Exception
	 */

	function deleteCourse($course_id)
	{
		foreach((array)$course_id as $id)
		{
			$course = [];
			if(!($course = $this->read(['course_id' => $id])))
			{
				continue;
			}
			if(!$this->isAdmin($course['course_id']))
			{
				throw new Api\Exception\NoPermission("Only admins are allowed to delete courses!");
			}

			foreach($course['videos'] as $video)
			{
				$this->deleteVideo($video, true);
			}
			if(!$this->so->deleteCourse((int)$course['course_id']))
			{
				throw new Api\Db\Exception(lang('Error deleting course!'));
			}
			// Clean VFS
			if(!Link::delete_attached(self::APPNAME, $course['course_id']))
			{
				throw new Api\Exception(lang('Error deleting course!'));
			}
			// push deleted courses
			$this->pushAll((int)$course['course_id'], 'delete', []);
		}
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
		if (!$this->isTeacher($video['course_id']))
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
		if (!empty($video['video_hash']) && empty($video['video_url']))
		{
			try {
				unlink($this->videoPath($video));
			}
			catch (\Exception $e) {
				// ignore exception, if video-directory does not exist (eg. broken import)
			}
		}
		// delete overlay
		Overlay::delete(['course_id' => (int)$video['course_id'], 'video_id' => (int)$video['video_id']]);

		$this->so->deleteVideo($video['video_id']);

		// we push deleting a video as course-update, not delete, as course still exists!
		$this->pushCourse((int)$video['course_id']);
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
	const COMMENTS_HIDE_TEACHERS = 2;
	/**
	 * Show only own comments
	 */
	const COMMENTS_SHOW_OWN = 3;
	/**
	 * Show everything withing the group plus staff
	 */
	const COMMENTS_GROUP = 6;
	/**
	 * Show comments within the group, but hide teachers
	 */
	const COMMENTS_GROUP_HIDE_TEACHERS = 7;

	/**
	 * Forbid students to comment, only list comments of teachers
	 */
	const COMMENTS_FORBIDDEN_BY_STUDENTS = 4;

	/**
	 * Disable comments, eg. for tests
	 */
	const COMMENTS_DISABLED = 5;

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
	 * Display all test questions as permanent list
	 */
	const TEST_DISPLAY_LIST = 3;

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
		if ($this->isTutor($course))
		{
			// no comment filter for course-admin / teacher
		}
		elseif ($this->isParticipant($course))
		{
			if (in_array($overwrite_video_options ?? $video['video_options'], [self::COMMENTS_GROUP, self::COMMENTS_GROUP_HIDE_TEACHERS]))
			{
				$participants = $this->so->participants($course['course_id'], true);
				$staff = array_keys(array_filter($participants, static function($participant)
				{
					return $participant['participant_role'] != self::ROLE_STUDENT;
				}));
				$group = $participants[$this->user]['participant_group'];
				$groupmembers = array_keys(array_filter($participants, static function($participant) use ($group)
				{
					return $participant['participant_group'] == $group && $participant['participant_role'] == self::ROLE_STUDENT;
				}));
			}
			else
			{
				$staff = array_keys($this->so->participants($course['course_id'], true, true, self::ROLE_TUTOR));
				$groupmembers = [];
			}
			$where = array_merge($where, $this->videoOptionsFilter(
				$overwrite_video_options ?? $video['video_options'],
				$staff, $allowed, $deny, $groupmembers));
		}
		else
		{
			throw new Api\Exception\NoPermission();
		}
		if (!empty($video_id)) $where['video_id'] = $video_id;

		$comments = $this->so->listComments($where);
		// add account_lid of commenter
		foreach($comments as &$comment)
		{
			$comment['account_lid'] = Api\Accounts::id2name($comment['account_id']);
		}

		// if we filter comments, we also need to filter re-tweets
		self::filterRetweets($comments, $allowed, $deny);

		return $comments;
	}

	/**
	 * Filter re-tweets by allowed or denied users
	 *
	 * @param array& $comments
	 * @param int[] $allowed
	 * @param ?bool $deny =null
	 */
	protected static function filterRetweets(array &$comments, array $allowed=null, bool $deny=null)
	{
		if (!isset($allowed))
		{
			return;	// nothing to do
		}
		foreach($comments as &$comment)
		{
			for ($i=1; $i < count($comment['comment_added']); $i += 2)
			{
				// if the re-tweet is NOT from an allowed user, remove it and all further ones
				$from = $comment['comment_added'][$i];
				if (isset($allowed) && in_array($from, $allowed) === $deny)
				{
					$comment['comment_added'] = array_slice($comment['comment_added'], 0, $i);
					break;
				}
			}
		}
	}

	/**
	 * Filter to list comments based on video-options
	 *
	 * @param int $video_options self::COMMENTS_*
	 * @param int|int[] $staff course-admin / teacher
	 * @param ?int[] &$allowed array with $not allowed account_id or null
	 * @param bool &$deny =null true: negate above condition
	 * @param ?int[] $groupmembers of current user
	 * @return array
	 */
	protected function videoOptionsFilter($video_options, $staff, array &$allowed=null, bool &$deny = null, array $groupmembers=[])
	{
		$filter = [];
		$allowed = null;
		$deny = false;
		switch ($video_options)
		{
			default:
			case self::COMMENTS_SHOW_ALL:
				break;
			case self::COMMENTS_GROUP:
				$filter['account_id'] = $allowed = array_merge($groupmembers, $staff);
				break;
			case self::COMMENTS_GROUP_HIDE_TEACHERS:
				$filter['account_id'] = $allowed = $groupmembers;
				break;
			case self::COMMENTS_HIDE_TEACHERS:
				$filter[] = $GLOBALS['egw']->db->expression(So::COMMENTS_TABLE, 'NOT ', ['account_id' => $staff]);
				$deny = true;
				$allowed = (array)$staff;
				break;
			case self::COMMENTS_HIDE_OTHER_STUDENTS:
				$filter['account_id'] = $allowed = array_unique(array_merge((array)$this->user, (array)$staff));
				break;
			case self::COMMENTS_SHOW_OWN:
				if (!in_array($this->user, $staff))
				{
					$filter['account_id'] = $allowed = (array)$this->user;
				}
				break;
			case self::COMMENTS_DISABLED:
				$filter[] = '1=0';
				$allowed = [];
				break;
			case self::COMMENTS_FORBIDDEN_BY_STUDENTS:
				$filter['account_id'] = $allowed = (array)$staff;
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
	 * @param bool $ignore_acl=false true: no acl check, eg. for import
	 * @param bool|string $push True to push, false to skip push, or string to push with that as update type
	 * @return int comment_id
	 * @throws Api\Exception\NoPermission
	 * @throws Api\Exception\NotFound
	 * @throws Api\Exception\WrongParameter
	 */
	public function saveComment(array $comment, bool $ignore_acl = false, bool|string $push = true)
	{
		// check required parameters
		if (empty($comment['course_id']) || empty($comment['video_id']))
		{
			throw new Api\Exception\WrongParameter("Missing course_id or video_id values!");
		}
		if(empty($comment['action']))
		{
			throw new Api\Exception\WrongParameter("Missing action or text values!");
		}
		// check ACL, need to be a participants to comment AND video need to be full accessible (not just  "readonly")
		if (!$ignore_acl && (!$this->isParticipant($comment['course_id']) || $this->videoAccessible($comment['video_id']) !== true))
		{
			throw new Api\Exception\NoPermission();
		}
		// new comments allowed by every participant
		if (empty($comment['comment_id']))
		{
			// check students are allowed to comment
			if (($video = $this->readVideo($comment['video_id'])) &&
			    $video['video_options'] == self::COMMENTS_FORBIDDEN_BY_STUDENTS &&
			    !$this->isTutor($comment['course_id']))
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
			// only teacher and comment-writer is allowed to edit, everyone to retweet
			if (!($this->isTeacher($old) || $old['account_id'] == $this->user || $comment['action'] === 'retweet'))
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
					'comment_starttime' => round($comment['comment_starttime']),
					'comment_stoptime' => round($comment['comment_stoptime']) ?: round($comment['comment_starttime']),
					'comment_color' => $comment['comment_color'],
					'comment_marked' => $comment['comment_marked'],
					'comment_deleted' => 0,
					'comment_created' => new Api\DateTime('now'),
					'comment_cat' => $comment['comment_cat']
				];
				break;

			case 'edit':
				$to_save['comment_added'] = array_merge([$comment['text']], array_slice($old['comment_added'], 1));
				if (!isset($to_save['comment_history'])) $to_save['comment_history'] = [];
				array_unshift($to_save['comment_history'], $old['comment_added'][0]);
				$to_save['comment_color'] = $comment['comment_color'];
				$to_save['comment_marked'] = $comment['comment_marked'];
				$to_save['comment_starttime'] = $comment['comment_starttime'];
				$to_save['comment_stoptime'] = $comment['comment_stoptime'];
				$to_save['comment_cat'] = $comment['comment_cat'];
				break;

			case 'retweet':
				$to_save['comment_added'][] = $this->user;
				$to_save['comment_added'][] = $comment['text'];
				break;

			default:
				throw new Api\Exception\WrongParameter("Invalid action '$comment[action]!");
		}
		if(($to_save['comment_id'] = (string)$this->so->saveComment($to_save)) && $push)
		{
			$this->pushComment($to_save, is_string($push) ? $push : $comment['action']);
		}
		return $to_save['comment_id'];
	}


	/**
	 * Push comment to online participants
	 *
	 * @param array $comment
	 * @param string $action "add", "edit" or "retweet"
	 *
	 * @throws Api\Exception\WrongParameter
	 * @throws Api\Json\Exception
	 */
	protected function pushComment(array $comment, string $action)
	{
		if (Api\Json\Push::onlyFallback() ||
			!($course = $this->so->read(['course_id' => $comment['course_id']])) ||	// so->read for no ACL check and no participants, videos, ...
			!($video = $this->readVideo($comment['video_id'])))
		{
			return;
		}
		$required_role = $this->videoAccessible($video, $is_admin, false, $error_msg, true) ?
			self::ROLE_STUDENT : self::ROLE_TUTOR;

		// if comments are not visible to everyone, we need to further filter to whom we push them
		$deny = false;
		$staff = array_keys($this->so->participants($course['course_id'], true, true, self::ROLE_TUTOR));
		switch ($video['video_options'])
		{
			default:
			case self::COMMENTS_SHOW_ALL:
			case self::COMMENTS_FORBIDDEN_BY_STUDENTS:	// --> push comments to everyone
				$deny = true; $users = [];    // all = deny no one
				break;

			case self::COMMENTS_GROUP_HIDE_TEACHERS:
				if ($this->isTutor($course))
				{
					$users = $staff;
					break;
				}
				// fall through
			case self::COMMENTS_GROUP:
				$group = $this->so->participants($course['course_id'], $comment['account_id'], true, $required_role)
					[$comment['account_id']]['participant_group'];
				// use 0 not null, for students without group, to not treat them as teachers and push to everyone
				if ($group === null && !$this->isTutor($course))
				{
					$group = 0;
				}
				$users = $this->participantsOnline($course['course_id'], $required_role, $group, $video['video_options'] == self::COMMENTS_GROUP);
				break;

			case self::COMMENTS_HIDE_OTHER_STUDENTS:
				$users = array_unique(array_merge((array)$comment['account_id'], (array)$staff));
				break;

			case self::COMMENTS_HIDE_TEACHERS:
				// push teacher comments only to teachers
				if (in_array($comment['account_id'], $staff))
				{
					$users = $staff;
				}
				else
				{
					$deny = true; $users = [];    // all = deny no one
				}
				break;

			case self::COMMENTS_SHOW_OWN:	// show students only their own comments
				// for student allow only own comments
				if (!in_array($this->user, $staff))
				{
					$users = (array)$this->user;
				}
				// for teachers allow everything
				else
				{
					$deny = true; $users = [];    // all = deny no one
				}
				break;
		}

		// Include attachments
		$upload_path = '/apps/smallpart/' . (int)$comment['course_id'] . '/' . (int)$comment['video_id'] . '/' . $comment['account_lid'] . '/comments/' . (int)$comment['comment_id'] . '/';
		if(!empty($attachments = Etemplate\Widget\Vfs::findAttachments($upload_path)))
		{
			$comment[$upload_path] = $attachments;
			$comment['class'] .= ' commentAttachments';
		}
		
		// we also need to filter re-tweets
		$comments = [&$comment];
		self::filterRetweets($comments, $users, $deny);

		// hide other students and comment from staff --> send everyone (deny no one)
		if ($video['video_options'] == self::COMMENTS_HIDE_OTHER_STUDENTS && in_array($comment['account_id'], $staff))
		{
			$deny = true; $users = [];    // all = deny no one
		}

		// for show students only their own push to staff and current user
		if ($video['video_options'] == self::COMMENTS_SHOW_OWN && !in_array($comment['account_id'], $staff))
		{
			$deny = false;
			$users = $staff;
			$users[] = $this->user;
		}

		if ($deny)
		{
			$participants = $this->participantsOnline($course['course_id'], $required_role);
			$users = array_diff($participants, $users);
		}
		// always add current user, as we won't refresh otherwise
		if (!in_array($this->user, $users))
		{
			$users[] = $this->user;
		}
		$this->pushOnline($users,
			$comment['course_id'] . ':' . $comment['video_id'] . ':' . $comment['comment_id'],
			$action, $comment + [
				// send some extra data to show a message, even if video is not loaded
				'course_name' => $course['course_name'],
				'video_name' => $video['video_name'],
				// only push comments of published videos to students
			], $required_role);
	}

	/**
	 * Push changed participants of a course to client-side
	 *
	 * We need to push different data for staff and students!
	 *
	 * @param int $course_id
	 * @param string $type "add", "update"
	 * @param array $participants of array with values for keys account_id, participant_(role|group)
	 * @param ?bool $to_staff null: to both, true: only staff, false: only students
	 * @throws Api\Exception\NotFound
	 * @throws Api\Exception\WrongParameter
	 */
	protected function pushParticipants(int $course_id, string $type, array $participants, bool $to_staff=null)
	{
		if (!isset($to_staff))
		{
			$this->pushParticipants($course_id, $type, $participants, true);
		}
		$data = array_values(array_map(static function($participant) use ($to_staff)
			{
				return self::participantClientside($participant, (bool)$to_staff);
			}, $participants));

		if ($to_staff)
		{
			$this->pushOnline($course_id, $course_id.':P', $type, $data, self::ROLE_TUTOR);
		}
		else
		{
			$this->pushOnline(array_keys(array_filter($this->so->participants($course_id, true),
				static function($participant)
				{
					return $participant['participant_role'] == 0;
				})), $course_id.':P', $type, $data);
		}
	}

	/**
	 * Push course updates to online participants
	 * @param array|int $course int course_id or whole course-array
	 * @param string $type
	 * @param ?bool $to_staff null: to both, true: only staff, false: only students
	 */
	protected function pushCourse($course, string $type="update", bool $to_staff=null)
	{
		if (!is_array($course) && !($course = $this->read($course)))
		{
			throw new \InvalidArgumentException();
		}
		if (!isset($to_staff))
		{
			$this->pushCourse($course, $type, true);
		}
		// student (not staff) remove eg. draft videos not shown to participants
		if (!$to_staff)
		{
			foreach($course['videos'] as $n => &$video)
			{
				// hide draft videos from students
				if (!is_array($video) || $video['video_published'] == self::VIDEO_DRAFT)
				{
					unset($course['videos'][$n]);
				}
			}
			$users = array_filter(array_map(static function($participant)
			{
				return is_array($participant) && $participant['participant_role'] == self::ROLE_STUDENT ? (int)$participant['account_id'] : false;
			}, $course['participants']));
		}
		else
		{
			$users = array_filter(array_map(static function($participant)
			{
				return is_array($participant) && $participant['participant_role'] != self::ROLE_STUDENT ? (int)$participant['account_id'] : false;
			}, $course['participants']));
		}
		// remove stuff not meant / needed for client-side and participant handled separate
		unset($course['course_password'], $course['course_secret'], $course['participants']);

		// send video-labels separate
		$videos = $course['videos'];
		$course['video_labels'] = $course['videos'] = [];
		foreach($videos as $n => &$video)
		{
			if (!is_array($video)) continue;

			$course['video_labels'][$video['video_id']] = self::videoLabel($video);
			$video['status'] = self::videoStatus($video);

			// only send certain attributes from accessible videos
			if ($this->videoAccessible($video, $is_admin, true,$video['error_msg'], !$to_staff) !== false)
			{
				// add summery for start-page
				if ($video['video_test_duration'] || $video['video_test_display'] == self::TEST_DISPLAY_LIST)
				{
					$video['summary'] = Overlay::summary($video);
				}
				// only send given attributes
				$course['videos'][$video['video_id']] = array_intersect_key($video,
					array_flip(['video_src', 'video_options', 'video_question', 'video_test_duration', 'video_test_options',
						'video_test_display', 'video_published', 'video_published_start', 'video_published_end', 'video_name',
						'video_type', 'summary', 'accessible', 'status', 'error_msg', 'mime_type']));
			}
		}
		asort($course['video_labels'], SORT_STRING|SORT_FLAG_CASE|SORT_ASC);
		$course['video_labels'] = array_map(static function($value, $label)
		{
			return ['value' => $value, 'label' => $label];
		}, array_keys($course['video_labels']), array_values($course['video_labels']));

		$this->pushOnline($users, (int)$course['course_id'], $type, $course);
	}

	/**
	 * Push given data to all participants currently online
	 *
	 * @param int|int[] $users_or_course_id course_id for all participants (taking $required_role into account) or explicit array of account_id(s)
	 * @param int|string $id push-id eg. "$course_id:$video_id:$comment_id"
	 * @param string $type "add", "update", "edit", "retweet", ...
	 * @param array $data
	 * @param int $required_role=0 required ACL/role eg. Bo::ROLE_TUTOR, only if $users is a course_id!!!
	 * @param bool $on_shutdown =true false: send direct, true: send after response to client
	 * @throws Api\Json\Exception
	 */
	public function pushOnline($users_or_course_id, $id, string $type, array $data, int $required_role=Bo::ROLE_STUDENT, bool $on_shutdown=true)
	{
		if ($on_shutdown)
		{
			Api\Egw::on_shutdown([$this, __FUNCTION__], [$users_or_course_id, $id, $type, $data, $required_role, false]);
			return;
		}
		if (($online = $this->participantsOnline($users_or_course_id, $required_role)))
		{
			$push = new Api\Json\Push($online);
			$push->apply("egw.push", [[
				'app'   => self::APPNAME,
				'id'    => $id,
				'type'  => $type,
				'acl'   => $data,
				'account_id' => $GLOBALS['egw_info']['user']['account_id'],
			]]);
		}
	}

	/**
	 * Return account_id of participants of a course who are currently online
	 *
	 * @param int|int[] $users_or_course_id course_id for all participants (taking $required_role into account) or explicit array of account_id(s)
	 * @param int $required_role=0 required ACL/role eg. Bo::ROLE_TUTOR, only if $users is a course_id!!!
	 * @param ?int $group return only given group, use 0 for students without a group, requires course-id given!
	 * @param bool $staff =true include staff or not
	 * @return int[]
	 */
	public function participantsOnline($users_or_course_id, int $required_role=Bo::ROLE_STUDENT, ?int $group=null, bool $staff=true)
	{
		// get participants meeting required ACL/role
		if (!is_array($users_or_course_id))
		{
			$participants = $this->so->participants($users_or_course_id, true, true, $required_role);
			if (isset($group))
			{
				$participants = array_filter($participants, static function($participant) use ($group, $staff)
				{
					return $participant['participant_role'] == self::ROLE_STUDENT ?
						(int)$participant['participant_group'] === $group : $staff;
				});
			}
			$users_or_course_id = array_keys($participants);
		}

		// for push via fallback (no native push) we use the heartbeat (constant polling of notification app)
		if (Api\Json\Push::onlyFallback())
		{
			return array_map(static function($row)
			{
				return (int)$row['account_id'];
			}, Api\Session::session_list(0, 'DESC', 'session_dla', true, [
				'account_id' => $users_or_course_id,
			]));
		}
		// for native push we ask the push-server who is active
		return array_intersect($users_or_course_id, (array)Api\Json\Push::online());
	}

	/**
	 * Push given data to everyone online
	 *
	 * Do NOT send private data, use pushOnline pushing to only online participants of a course!
	 *
	 * @param int $course_id
	 * @param int|string $id push-id eg. "$course_id:$video_id:$comment_id"
	 * @param string $type "add", "update", ...
	 * @param array $data
	 * @param bool $on_shutdown =true false: send direct, true: send after response to client
	 * @throws Api\Json\Exception
	 */
	protected function pushAll($id, string $type, array $data)
	{
		$push = new Api\Json\Push(Api\Json\Push::ALL);
		$push->apply("egw.push", [[
			'app'   => self::APPNAME,
			'id'    => $id,
			'type'  => $type,
			'acl'   => $data,
			'account_id' => $GLOBALS['egw_info']['user']['account_id'],
		]]);
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
		// notify everyone about deleted comment
		if (($ret = $this->so->deleteComment($comment_id)))
		{
			$this->pushAll($comment['course_id'].':'.$comment['video_id'].':'.$comment['comment_id'],
				'delete', []);
		}
		return $ret;
	}

	protected static $role2label = [
		self::ROLE_ADMIN => 'admin',
		self::ROLE_TEACHER => 'teacher',
		self::ROLE_TUTOR => 'tutor',
		self::ROLE_STUDENT => 'student',
	];

	/**
	 * Return role-label for a participant
	 *
	 * @param array $participant with values for key "account_id" and "pariticipant_role"
	 * @param array|null $course
	 * @return string
	 */
	public static function role2label(array $participant, ?array $course=null)
	{
		if ($course && $participant['account_id'] == $course['course_owner'])
		{
			return 'admin';
		}
		return self::$role2label[$participant['participant_role']] ?? throw new \InvalidArgumentException("Invalid participant_role value $participant[participant_role]");
	}

	public static function label2role(?string $role=null)
	{
		if (empty($role))
		{
			return self::ROLE_STUDENT;
		}
		if (($value = array_search($role, self::$role2label, true)) === false)
		{
			throw new \InvalidArgumentException("Invalid participant role '$role'!");
		}
		return $value;
	}

	/**
	 * Current user is an admin or (co-)owner of a given course
	 *
	 * @param int|array $course =null default check for creating new courses
	 * @return bool
	 */
	public function isAdmin($course)
	{
		// EGroupware Admins are always allowed
		if (self::isSuperAdmin()) return true;

		// if no course given --> deny
		if (empty($course))
		{
			return false;
		}

		// if a course given check it exists
		if ((!is_array($course) || empty($course['course_owner'])) &&
			!($course = $this->so->read(['course_id' => is_array($course) ? $course['course_id'] : $course])))
		{
			return false;
		}
		// owner himself or personal edit-rights from owner (deputy rights)
		if (!!($this->grants[$course['course_owner']] & ACL::EDIT))
		{
			return true;
		}
		// user has co-owner role on course
		return $this->isParticipant($course, self::ROLE_ADMIN);
	}

	/**
	 * Check if current user is at least a teacher of the given course (or admin)
	 *
	 * @param int|array $course course_id or course-array with course_id, course_owner and optional participants
	 * @return boolean true if teacher or admin, false otherwise
	 * @throws Api\Exception\WrongParameter
	 */
	public function isTeacher($course)
	{
		return $this->isParticipant($course, self::ROLE_TEACHER);
	}

	/**
	 * Check if current user is at lease a tutor of a course (or teacher or admin)
	 *
	 * @param int|array $course course_id or course-array with course_id, course_owner and optional participants
	 * @return boolean true if teacher or admin, false otherwise
	 * @throws Api\Exception\WrongParameter
	 */
	public function isTutor($course)
	{
		return $this->isParticipant($course, self::ROLE_TUTOR);
	}

	/**
	 * Check if current user is a participant of a course (or has at least required_rights)
	 *
	 * @param int|array $course course_id or course-array with course_id, course_owner and optional participants
	 * @param int $required_acl =0 self::ROLE_* or self::ACL_*
	 * @param bool $check_agreed =false true: check and return false, if course has a disclaimer and user has NOT agreed to it
	 * @return boolean true if participant or admin, false otherwise
	 * @throws Api\Exception\WrongParameter
	 */
	public function isParticipant($course, int $required_acl=0, bool $check_agreed=false)
	{
		static $course_acl = [];	// some per-request caching for $this->user
		// if we have participant infos put $this->user ACL in cache
		if (is_array($course) && isset($course['participants']))
		{
			$user = $this->user;
			$participants = array_filter($course['participants'], static function($participant) use ($user)
			{
				return is_array($participant) && $participant['account_id'] == $user && !isset($participant['participant_unsubscribed']);
			});
			$course_acl[$course['course_id']] = $participants ? current($participants)['participant_role'] : null;
		}
		if ($check_agreed)
		{
			if (!is_array($course))
			{
				$course = $this->read(['course_id' => $course], false, false, false);
			}
			$has_disclaimer = !empty($course['course_disclaimer']);
		}
		if(is_array($course))
		{
			$course_id = $course['course_id'];
		}
		if(!$course)
		{
			return false;
		}

		// no cached ACL --> read it from DB
		if(!array_key_exists($course_id, $course_acl) || $check_agreed)
		{
			$participants = $this->so->participants($course_id, $this->user);
			$course_acl[$course_id] = $participants[$this->user]['participant_role'];
		}
		$is_participant = isset($course_acl[$course_id]) && ($course_acl[$course_id] & $required_acl) === $required_acl ||
			// course-owner is always regarded as subscribed, while others need to explicitly subscribe
			is_array($course) && $course['course_owner'] == $this->user ||
			// as isAdmin() calls isParticipant($course, self::ROLE_ADMIN) we must NOT check/call isAdmin() again!
			$required_acl && $required_acl !== self::ROLE_ADMIN && $this->isAdmin($course);

		if ($is_participant && $check_agreed && $has_disclaimer && empty($participants[$this->user]['participant_agreed']))
		{
			return false;
		}
		return $is_participant;
	}

	/**
	 * Check if current user belongs to the staff of a course
	 *
	 * @param int|array $course
	 * @param bool $role_name=true true: return name of role, false: return integer Bo::ROLE_*
	 * @return ?string|int "admin", "teacher", "tutor" or null for student, integer self::ROLE_*
	 * @throws Api\Exception\WrongParameter
	 */
	public function isStaff($course, bool $role_name=true)
	{
		return $this->isAdmin($course) ? ($role_name ? 'admin' : self::ROLE_ADMIN) :
			($this->isTeacher($course) ? ($role_name ? 'teacher' : self::ROLE_TEACHER) :
				($this->isTutor($course) ? ($role_name ? 'tutor' : self::ROLE_TUTOR) :
					($role_name ? null : self::ROLE_STUDENT)));
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
	 * Check if a given user is a teacher / can create courses
	 *
	 * @param ?int $account_id default current user
	 * @return bool
	 */
	public static function checkTeacher($account_id=null)
	{
		if (self::isSuperAdmin($account_id))
		{
			return true;
		}
		static $admins;
		if (!isset($admins))
		{
			$admins = $GLOBALS['egw']->acl->get_ids_for_location(self::ACL_ADMIN_LOCATION, 1, self::APPNAME);
		}
		if (empty($account_id))
		{
			$account_id = $GLOBALS['egw_info']['user']['account_id'];
		}
		foreach($admins as $admin)
		{
			if ($admin > 0 ? $account_id == $admin : in_array($account_id, Api\Accounts::getInstance()->members($admin, true)))
			{
				return true;
			}
		}
		return false;
	}

	/**
	 * Current user can edit accounts or reset passwords aka is an EGroupware admin
	 *
	 * @param ?int $account_id
	 * @return bool
	 */
	public static function isSuperAdmin(int $account_id=null)
	{
		if (empty($account_id))
		{
			return !empty($GLOBALS['egw_info']['user']['apps']['admin']);
		}
		static $admins;
		if (!isset($admins))
		{
			$admins = $GLOBALS['egw']->acl->get_ids_for_location('run', 1, 'admin');
		}
		$memberships = Api\Accounts::getInstance()->memberships($account_id, true);
		$memberships[] = $account_id;
		return (bool)array_intersect($memberships, $admins);
	}

	/**
	 * Prefix of password hash
	 */
	const PASSWORD_HASH_PREFIX = '$2y$';

	/**
	 * Check course access code, if one is set
	 *
	 * @param int $course_id
	 * @param string|true $password Course access code to subscribe to password protected courses
	 *    true to not check the code (used when accessing a course via LTI)
	 * @param ?int& $group on return group to join, if configured
	 * @return bool
	 * @throws Api\Exception\WrongParameter invalid $course_id
	 * @throws Api\Exception\WrongUserinput wrong access code
	 */
	public function checkSubscribe($course_id, $password, ?int &$group=null)
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
			throw new Api\Exception\WrongUserinput(lang('You entered a wrong course access code!'));
		}

		// should we assign a group, we need to check the existing students assignments
		if (!empty($course['course_groups']) && substr($course['groups_mode'], 4) === 'auto' &&
			($participants = $this->so->participants($course_id)))
		{
			$groups = [];
			// if we want N groups, make sure they all exist
			for($g=1; $g <= $course['course_groups']; ++$g)
			{
				$groups[$g] = 0;
			}
			// count participants per group
			foreach($participants as $participant)
			{
				if ($participant['participant_role'] == self::ROLE_STUDENT && !empty($participant['participant_group']))
				{
					$groups[$participant['participant_group']]++;
				}
			}
			// sort the smallest group first
			asort($groups, SORT_NUMERIC|SORT_ASC);
			// if we want N groups, pick the first one (with the least number of students)
			if ($course['course_groups'] > 0)
			{
				// sort
				$group = array_key_first($groups);
			}
			else
			{
				// if we want max N per group, check if all existing groups (the smallest first) are full
				foreach($groups as $group => $num)
				{
					if ($num < abs($course['course_groups']))
					{
						break;
					}
				}
				// if all existing groups are full, start a new one
				if ($num >= abs($course['course_groups']))
				{
					for($group=1; isset($groups[$group]) && $groups[$group] >= abs($course['course_groups']); ++$group)
					{

					}
				}
			}
		}
		return true;
	}

	/**
	 * Subscribe or unsubscribe from a course
	 *
	 * Only teachers can (un)subscribe others!
	 *
	 * @param int|int[] $course_id one or multiple course_id's, subscribe only supported for a single course_id (!)
	 * @param boolean $subscribe =true true: subscribe, false: unsubscribe
	 * @param int $account_id =null default current user
	 * @param string|true $password password to subscribe to password protected courses
	 *    true to not check the password (used when accessing a course via LTI)
	 * @param int $role=0 role to set
	 * @param ?Api\DateTime $agreed=null time user agreed to disclaimer
	 * @throws Api\Exception\WrongParameter invalid $course_id
	 * @throws Api\Exception\WrongUserinput wrong password
	 * @throws Api\Exception\NoPermission
	 * @throws Api\Db\Exception
	 */
	public function subscribe($course_id, $subscribe = true, int $account_id = null, $password = null, int $role=0, ?Api\DateTime $agreed=null)
	{
		if ((isset($account_id) && $account_id != $this->user))
		{
			foreach ((array)$course_id as $id)
			{
				if (!$this->isTeacher($id))
				{
					throw new Api\Exception\NoPermission("Only teachers are allowed to (un)subscribe others!");
				}
			}
		}
		if ($subscribe && is_array($course_id))
		{
			throw new Api\Exception\WrongParameter("Can only subscribe to single courses!");
		}
		if ($subscribe)
		{
			$this->checkSubscribe($course_id, $password, $group);
		}
		if (Bo::isSuperAdmin($account_id))
		{
			$role = Bo::ROLE_ADMIN;
		}
		if (!$this->so->subscribe($course_id, $subscribe, $account_id ?: $this->user, $role, $group, $agreed))
		{
			throw new Api\Db\Exception(lang('Error (un)subscribing!'));
		}
		if ($subscribe)
		{
			$this->pushParticipants($course_id, 'add', [[
				'account_id' => $this->user,
				'participant_role'  => $role,
				'participant_group' => null,
			]]);
		}
		else
		{
			foreach ((array)$course_id as $course_id)
			{
				$this->pushAll($course_id.':P', 'unsubscribe', [[
					'account_id' => $account_id ?: $this->user,
				]]);
			}
		}
	}

	/**
	 * Change nickname of current user
	 *
	 * @param int $course_id
	 * @param string $nickname
	 * @return string nickname set
	 * @throws Api\Exception\NoPermission
	 * @throws Api\Exception\WrongUserinput for invalid nicknames
	 */
	public function changeNickname(int $course_id, string $nickname)
	{
		if (!$this->isParticipant($course_id))
		{
			throw new Api\Exception\NoPermission();
		}
		if (preg_match('/\[\d+\]$/', $nickname))
		{
			throw new Api\Exception\WrongUserinput(lang('Nickname is already been taken, choose an other one'));
		}
		$nickname_lc = strtolower(trim($nickname));
		$participants = $this->so->participants($course_id);
		foreach($participants as $participant)
		{
			if (strtolower(self::participantName($participant, true)) === $nickname_lc ||
				strtolower(self::participantName($participant, false)) === $nickname_lc)
			{
				throw new Api\Exception\WrongUserinput(lang('Nickname is already been taken, choose an other one'));
			}
			if ($this->user === (int)$participant['account_id'])
			{
				$user_participant = $participant;
			}
		}
		if (empty($user_participant))
		{
			throw new Api\Exception\NotFound();
		}
		$this->so->changeNickname($course_id, $nickname, $this->user);

		// push changed nick to everyone currently online
		$this->pushParticipants($course_id, 'edit', [[
			'participant_alias' => $nickname,
		]+$user_participant]);

		return $nickname;
	}

	/**
	 * Close given course(s)
	 *
	 * @param int|int[] $course_id one or more course_id
	 * @param bool $close=true false: reopen course
	 * @throws Api\Exception\NoPermission
	 * @throws Api\Db\Exception
	 */
	public function close($course_id, bool $close=true)
	{
		// need to check every single course, as rights depend on being the owner/admin of a course
		foreach ((array)$course_id as $id)
		{
			if (!$this->isAdmin($id))
			{
				throw new Api\Exception\NoPermission("Only admins are allowed to close courses!");
			}
		}
		if (!$this->so->close($course_id, $close))
		{
			throw new Api\Db\Exception(lang('Error closing course!'));
		}
		// push locked courses as delete (ignoring re-opened courses for now)
		foreach ((array)$course_id as $id)
		{
			$this->pushAll((int)$id, $close ? 'delete' : 'update', []);
		}
	}

	/**
	 * reads row matched by key and puts all cols in the data array
	 *
	 * @param array|int $keys array with keys or scalar course_id
	 * @param bool $check_subscribed=true false: do NOT check if current user is subscribed, but use ACL to check visibility of course
	 * @param bool $check_acl=true false: do NOT check if current user has read rights to the course
	 * @param bool $list_videos=true false: do NOT add videos
	 * @return array|boolean data if row could be retrieved else False
	 * @throws Api\Exception\NoPermission if not subscribed
	 * @throws Api\Exception\WrongParameter
	 */
	function read($keys, bool $check_subscribed = true, bool $check_acl = true, bool $list_videos=true)
	{
		if (!is_array($keys)) $keys = ['course_id' => $keys];

		// ACL filter (expanded by so->search to (course_owner OR course_org)
		if (!$check_subscribed && $check_acl)
		{
			$keys['acl'] = array_keys($this->grants);
		}

		if (($course = $this->so->read($keys)))
		{
			$course = $this->db2data($course);

			$course['participants'] = $this->so->participants($keys['course_id'], false, null);

			// ACL check (we check isAdmin($course) too, to not error out for super-admins)
			if ($check_subscribed && !$this->isParticipant($course) && !self::isSuperAdmin())
			{
				throw new Api\Exception\NoPermission();
			}
			if ($list_videos)
			{
				$course['videos'] = $this->listVideos(['course_id' => $course['course_id']]);
			}
			$clm = json_decode($this->so->readCLMeasurementsConfig($course['course_id']), true);
			$course['clm'] = is_array($clm) ? $clm : self::init()['clm'];

			$course['cats'] = $this->so->readCategories($course['course_id']);
		}
		return $course;
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
		return $this->so->readCategories($course_id, $index_by_cat_id);
	}

	/**
	 * Transform DB to internal data
	 *
	 * @param array $course
	 * @return array
	 */
	protected function db2data(array $course)
	{
		if (!empty($course['course_groups']))
		{
			$course['groups_mode'] = $course['course_groups'] < 0 ? 'size' : 'number';
			if (abs($course['course_groups']) >= 64) $course['groups_mode'] .= '-auto';
			$course['course_groups'] = abs($course['course_groups']) & 63;
		}
		$course['allow_neutral_lf_categories'] = $course['allow_neutral_lf_categories'] ? true : false;
		return $course;
	}

	/**
	 * Transform internal data to DB
	 *
	 * @param array $course
	 * @return array
	 */
	protected function data2db(array $course)
	{
		if (!empty($course['groups_mode']))
		{
			list($mode, $auto) = explode('-', $course['groups_mode']);
			$course['course_groups'] = ($mode === 'size' ? -1 : 1) * ($course['course_groups'] + ($auto === 'auto' ? 64 : 0));
		}
		$course['allow_neutral_lf_categories'] = intval($course['allow_neutral_lf_categories']);
		return $course;
	}

	/**
	 * Get display-name of a participant
	 *
	 * @param array $participant values for keys account_id (required), and optional participant_role and participant_alias
	 * @param bool $is_staff true: formatting is for staff (always full name) or student (only full name for staff members)
	 * @return string
	 * @throws Api\Exception\NotFound
	 * @throws Api\Exception\WrongParameter
	 */
	public static function participantName(array $participant, bool $is_staff)
	{
		if ($is_staff || $participant['participant_role'] != self::ROLE_STUDENT)
		{
			$account = Api\Accounts::getInstance()->read($participant['account_id']);
			return $account['account_firstname'].' '.$account['account_lastname'];
		}
		if (!empty($participant['participant_alias']))
		{
			return $participant['participant_alias'];
		}
		return (Api\Accounts::id2name($participant['account_id'], 'account_firstname') ?: '').' ['.$participant['account_id'].']';
	}

	/**
	 * Get clientside participant object
	 *
	 * @param array $participant values for keys account_id, participant_role and participant_alias
	 * @param bool $is_staff formatting for staff or students
	 * @return array values for keys value, label, role and group (plus title for staff with nickname)
	 * @throws Api\Exception\NotFound
	 * @throws Api\Exception\WrongParameter
	 */
	public static function participantClientside(array $participant, bool $is_staff)
	{
		return [
			'value' => (int)$participant['account_id'],
			'label' => self::participantName($participant, $is_staff),
			'role' => (int)$participant['participant_role'],
			'group' => (int)$participant['participant_group'] ?: null,
			'active' => !isset($participant['participant_unsubscribed']),
		]+($is_staff ? [
			'title' => self::participantName($participant, false),
		] : []);
	}

	/**
	 * saves the content of data to the db
	 *
	 * @param array $keys =null if given $keys are copied to data before saving => allows a save as
	 * @param string|array $extra_where =null extra where clause, eg. to check an etag, returns true if no affected rows!
	 * @return array saved data
	 * @throws Api\Db\Exception on error
	 * @throws Api\Exception\WrongParameter
	 */
	function save($keys = null, $extra_where = null)
	{

		if (empty($keys['course_id']) ? !self::checkTeacher() : !$this->isTeacher($keys['course_id']))
		{
			throw new Api\Exception\NoPermission("You have no permission to update course with ID '$keys[course_id]'!");
		}
		// hash password if not "cleartext" storage is configured and user changed it
		if (!empty($keys['course_password']) && $this->config['coursepassword'] !== 'cleartext' &&
			substr($keys['course_password'], 0, 4) !== self::PASSWORD_HASH_PREFIX)
		{
			$keys['course_password'] = password_hash($keys['course_password'], PASSWORD_BCRYPT);
		}
		if (!empty($keys['course_id']) &&
			($modified = $this->so->participantsModified($keys['course_id'], $keys['participants'], $keys['course_owner'])) &&
			!$this->isTeacher($keys['course_id']))
		{
			throw new Api\Exception\NoPermission("Only teachers are allowed to modify participants!");
		}
		$keys = $this->data2db($keys);
		// only update modified participants
		if (($err = $this->so->save((isset($modified) ? ['participants' => $modified] : []) + $keys)))
		{
			throw new Ap\Db\Exception(lang('Error saving course!'));
		}
		$course = $this->db2data($this->so->data);

		// subscribe teacher/course-admin to course (true to not check/require password)
		if (empty($keys['course_id'])) $this->subscribe($course['course_id'], true, null, true, Bo::ROLE_ADMIN);

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
			if (!empty($keys['clm']) && $keys['clm']['tests_duration_check'])
			{
				$video['video_test_duration'] = empty($keys['clm']['tests_duration_times']) ? 10080 : $keys['clm']['tests_duration_times'];
			}
			if (!empty($video['video_upload']))
			{
				if (!(preg_match(self::VIDEO_MIME_TYPES, $mime_type = $video['video_upload']['type']) ||
					preg_match(self::VIDEO_MIME_TYPES, $mime_type = Api\MimeMagic::filename2mime($video['video_upload']['name']))))
				{
					throw new Api\Exception\WrongUserinput(lang('Invalid type of video, please use mp4 or webm!'));
				}
				$video = array_merge($video, [
					'video_name' => $video['video_upload']['name'],
					'video_type' => explode('/', $mime_type)[1],    // "video/"
					'video_hash' => $video['video_hash']??Api\Auth::randomstring(64),
				]);
				if (!copy($video['video_upload']['tmp_name'], $this->videoPath($video, true)))
				{
					throw new Api\Exception\WrongUserinput(lang("Failed to store uploaded video!"));
				}
			}
			$video['course_id'] = $course['course_id'];
			$video['video_id'] = $this->so->updateVideo($video);
			if (!empty($video['livefeedback']) && !empty($video['livefeedback']['session_interval']))
			{
				$this->so->saveLivefeedback($video['livefeedback']);
			}
			// Remove start & end dates if not set, other places expect them to have a value if present
			foreach(['video_published_start', 'video_published_end'] as $pub_date)
			{
				if(isset($video[$pub_date]) && !$video[$pub_date])
				{
					unset($video[$pub_date]);
				}
			}
		}
		if (!empty($keys['clm']))
		{

			// add ids base on array index, client side doesn't send the id part as it's a readonly textbox
			if (!empty($keys['clm']['process']['questions']))
			{
				foreach ($keys['clm']['process']['questions'] as $index => &$q)
				{
					if ($index == 0) continue;
					if (empty($q['id'])) $q['id'] = $index;
				}
			}
			// add ids base on array index, client side doesn't send the id part as it's a readonly textbox
			if (!empty($keys['clm']['post']['questions']))
			{
				foreach ($keys['clm']['post']['questions'] as $index => &$q)
				{
					if ($index == 0) continue;
					if (empty($q['id'])) $q['id'] = $index;
				}
			}

			$this->so->updateCLMeasurementsConfig($course['course_id'], $keys['clm']);
		}

		if (!empty($keys['cats']))
		{
			$cat_ids = [];
			foreach($keys['cats'] as $key => &$cat)
			{
				$cat['course_id'] = $course['course_id'];
				$cat += (array)json_decode($cat['data'], true);
				$original_cat_id = $cat['cat_id'];
				$cat['parent_id'] = !empty($cat['parent_id']) && isset($cat_ids[$cat['parent_id']]) ? $cat_ids[$cat['parent_id']] : null;
				$cat['cat_id'] = $this->so->updateCategory($cat);
				if($original_cat_id)
				{
					$cat_ids[$original_cat_id] = $cat['cat_id'];
				}
				else
				{
					// Adding a new cat, don't delete it in deleteCategories
					$cat_ids[] = $cat['cat_id'];
				}
				// encode the newly generated value back into data
				$cat['data'] = json_encode($cat);
			}
			$this->so->deleteCategories($course['course_id'], $cat_ids, true);
			array_unshift($keys['cats'], false);
			$course['cats'] = $keys['cats'];
		}

		// push course updates to participants (new course are ignored for now)
		if (!empty($keys['course_id']))
		{
			$this->pushCourse($keys['course_id'], 'update');
		}
		// push modified participants eg. changed roles or groups
		if (!empty($keys['course_id']) && $modified)
		{
			$this->pushParticipants($course['course_id'], 'update', $modified);
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
		if (is_array($video['video_limit_access']))
		{
			$video['video_limit_access'] = $video['video_limit_access'] ? implode(',', $video['video_limit_access']) : null;
		}
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
			'clm' => ['process' => ['questions' => [[]]], 'post' => ['questions' => [[]]]],
			'cats' => Bo::initCategories()
		];
	}

	/**
	 * Generates predefined categories for newly created course
	 * @return array
	 */
	static function initCategories()
	{
		$cats = [];
		$predefined = ["white", "green", "red", "yellow"];
		$index = 0;
		foreach($predefined as $key => $item)
		{
			$parentIndex = $index+1;
			$cats[] = [
				"cat_id" => "new_".$parentIndex,
				"cat_name"=> $item,
				"cat_description" => "",
				"course_id"=>0,
				"parent_id" => null,
				"cat_color" => $item,
			];
			$index++;
			foreach (["like", "dislike"] as $sub)
			{
				++$index;
				$cats[] = [
					"cat_id" => "new_".$index,
					"cat_name"=> $sub,
					"cat_description" => "",
					"course_id"=>0,
					"parent_id" => "new_".$parentIndex,
					"cat_color" => $sub == "like" ? "#00ff00" : "#ff0000",
					"type" => "lf",
					"value"=> $sub == "like" ? "p" : "n",
				];
			}
		}
		return $cats;
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
			$entry = $this->read(['course_id' => $entry], false, true, false);
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
	 * @throws Api\Exception\WrongParameter|Api\Exception\NoPermission
	 */
	public function recordCLMeasurement(int $course_id, int $video_id, string $cl_type, array $data, int $account_id=null, int $cl_id=null)
	{
		// check ACL, "readonly" videos are not allowed for update
		// we can't check test running because this particular post request can run after stop.
		if (!$this->isParticipant($course_id) || $this->videoAccessible($video_id, $admin, false) !== true)
		{
			throw new Api\Exception\NoPermission();
		}
		return $this->so->recordCLMeasurement($course_id, $video_id, $cl_type, $data, $account_id, $cl_id);
	}

	/**
	 * Move newly uploaded files into its relative comment dir
	 *
	 * @param $course_id
	 * @param $video_id
	 * @param $comment_id
	 *
	 * @todo user file access needs to be considered here before any file operation is permitted
	 */
	public function save_comment_attachments($course_id, $video_id, $comment_id)
	{
		// don't do any file operations if there's no course, video or comment info provided
		if (empty($course_id) || empty($video_id) || empty($comment_id)) return;

		$path = "/apps/smallpart/$course_id/$video_id/{$GLOBALS['egw_info']['user']['account_lid']}/comments/";

		$files = Api\Vfs::find("{$path}.new/",	array('type' => 'f', 'maxdepth' => 1));

		foreach($files as &$file)
		{
			$file_name = is_array($file) && $file['name'] ? $file['name'] : Api\Vfs::basename($file);
			$file_path = is_array($file) ? ($file['tmp_name'] ?? $file['path']) : $file;
			if (!is_dir($target_dir=$path.$comment_id))
			{
				Api\Vfs::mkdir($target_dir, 0755, true);
			}
			Api\Vfs::rename($file_path, $target_dir.'/'.$file_name);
		}
		// remove the temp new directory
		Api\Vfs::rmdir("{$path}.new/");
	}

	/**
	 * Check access to files and directories
	 *
	 * We currently support a $video_id subdirectory under the course-directory (/apps/smallpart/$course_id) to which
	 * students have only access, if the video is accessible, eg. not draft.
	 *
	 * In video-directory with support the following sub-directories
	 * - "all": staff write, students read
	 *  - $account_lid: owner: write, staff: write, other students: read, if not comments from other students are hidden
	 *
	 * @param int $course_id
	 * @param int $check Acl::READ for read and Acl::EDIT for write or delete access
	 * @param string $rel_path path relative to course-director directory
	 * @param ?int $user =null for which user to check, default current user
	 * @return bool|int true if access is granted or false otherwise AND result independent of $rel_path, int=0|1 if result depends on rel_path
	 * we only return:
	 * - false: if not a participant and therefore NO access is not depending on $rep_path
	 * - true: if staff with full access, therefore not depending on $rel_path
	 * - otherwise we return 0 or 1, to not cache the result, as it depends on $rel_path
	 */
	public static function file_access($course_id, int $check, $rel_path, int $user=null)
	{
		if (!is_numeric($course_id) || $course_id <= 0)
		{
			return false;   // no a valid course-id
		}
		// instantiate for given user or current
		$bo = new self($user);

		// course directory: participants read, staff write
		if (empty($rel_path))
		{
			return $bo->isParticipant($course_id, $check == Acl::EDIT ? self::ROLE_TUTOR : self::ROLE_STUDENT) ? 1 : false;
		}

		// staff has all rights (also to student dirs!)
		if ($bo->isTutor($course_id))
		{
			return true;
		}

		list($video_id, $account_lid) = explode('/', $rel_path);

		// check video is accessible eg. not draft for students
		if (!is_numeric($video_id) || $video_id <= 0 ||
			!($video = $bo->readVideo($video_id)) || !$bo->videoAccessible($video))
		{
			return 0;
		}

		// students have read-rights to "all"
		if ($account_lid === 'all')
		{
			return (int)($check == Acl::READ);
		}

		// owner has all rights to his directory
		if ($account_lid === Api\Accounts::id2name($bo->user))
		{
			return 1;
		}

		// other students read rights, if not comments of other students are hidden
		return (int)($check == Acl::READ && $video['video_options'] != self::COMMENTS_HIDE_OTHER_STUDENTS);
	}

	/**
	 * Read CLM records
	 *
	 * @param int $course_id
	 * @param int $video_id
	 * @param string $cl_type
	 * @param int|null $account_id
	 * @param string $extra_where
	 * @return array|null
	 * @throw Exception\NoPermission| WrongParameter
	 */
	public function readCLMeasurementRecords(int $course_id, int $video_id, string $cl_type, int $account_id=null, string $extra_where= '')
	{
		// check required parameters
		if (empty($course_id) || empty($video_id) || empty($cl_type))
		{
			throw new Api\Exception\WrongParameter("Missing course_id or video_id or cl_type values!");
		}
		// check ACL, allowing "readonly" videos
		if (!$this->isParticipant($course_id) || !$this->videoAccessible($video_id))
		{
			throw new Api\Exception\NoPermission();
		}

		$records =  $this->so->readCLMeasurementRecords($course_id, $video_id, $cl_type, $account_id, $extra_where);
		return is_array($records) ? $records : null;
	}

	/**
	 * Read livefeedback records
	 *
	 * @param int $course_id
	 * @param int $video_id
	 * @return array|Api\ADORecordSet|null
	 * @throws Api\Exception\WrongParameter
	 */
	public function readLivefeedback(int $course_id, int $video_id)
	{
		// check required parameters
		if (empty($course_id) || empty($video_id))
		{
			throw new Api\Exception\WrongParameter("Missing course_id or video_id values!");
		}
		$records = $this->so->readLivefeedback($course_id, $video_id);
		return is_array($records) ? $records : null;
	}

	public function updateLivefeedback($data)
	{
		$this->so->saveLivefeedback($data);
	}
}