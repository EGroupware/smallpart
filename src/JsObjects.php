<?php
/**
 * EGroupware - SmallParT - JSON objects for REST API
 *
 * @link https://www.egroupware.org
 * @package smallpart
 * @subpackage setup
 * @license https://spdx.org/licenses/AGPL-3.0-or-later.html GNU Affero General Public License v3.0 or later
 */

namespace EGroupware\SmallParT;

use EGroupware\Api;

/**
 * Rendering SmallParT / Vidoteach objects as JSON for REST API
 *
 * @link https://github.com/EGroupware/smallpart/doc/Rest-API.md
 *
 *
 */
class JsObjects extends Api\CalDAV\JsBase
{
	const APP = 'smallpart';

	protected static Bo $bo;

	const TYPE_COURSE = 'course';
	const TYPE_PARTICIPANT = 'participant';
	const TYPE_MATERIAL = 'material';

	/**
	 * Get JsCourse for given course
	 *
	 * @param int|array $course
	 * @param bool|"pretty" $encode true: JSON encode, "pretty": JSON encode with pretty-print, false: return raw data e.g. from listing
	 * @return string|array
	 * @throws Api\Exception\NotFound|\Exception
	 */
	public static function JsCourse($course, $encode=true)
	{
		if (is_scalar($course) && !($course = self::$bo->read($course)))
		{
			throw new Api\Exception\NotFound();
		}

		$data = array_filter([
			self::AT_TYPE => self::TYPE_COURSE,
			'id' => (int)$course['course_id'],
			'name' => $course['course_name'],
			'info' => $course['course_info'],
			'disclaimer' => $course['course_disclaimer'],
			// NOT returned regular: 'password' => $course['course_password'],
			'owner' => self::account($course['course_owner']),
			'org' => self::account($course['course_org']),
			'closed' => $course['course_closed'] ? true : null,
			'options' => isset($course['course_options']) ? self::courseOptions($course) : null,
			'participants' => $course['participants'] ? self::Participants($course) : null,
			'materials' => $course['videos'] ? self::Materials($course) : null,
			'subscribed' => (bool)$course['subscribed'],
		]);

		if ($encode)
		{
			return Api\CalDAV::json_encode($data, $encode === "pretty");
		}
		return $data;
	}

	protected static function snake_case($name)
	{
		return preg_replace_callback('/[A-Z]/', static function ($matches)
		{
			return '_'.strtolower($matches[0]);
		}, $name);
	}

	protected static function camelCase($name)
	{
		return preg_replace_callback('/_([a-z])/', static function ($matches)
		{
			return strtoupper($matches[1]);
		}, $name);
	}

	/**
	 * @param array $course
	 * @return array
	 */
	protected static function courseOptions(array $course)
	{
		$options = [];
		foreach (Courses::$options as $name => $mask)
		{
			$options[self::camelCase($name)] = ($course['options'] & $mask) === $mask;
		}
		$options['allowNeutralLFcategories'] = (bool)$course['allow_neutral_lf_categories'];
		return $options;
	}

	/**
	 * @param array $course
	 * @param array $object
	 * @return array full-course array incl. $value set
	 */
	protected static function parseCourseOptions(array $course, array $object)
	{
		foreach ($object as $name => $value)
		{
			if (isset(Courses::$options[$snake_case_name=self::snake_case($name)]))
			{
				if ($value)
				{
					$course['course_options'] |= Courses::$options[$snake_case_name];
				}
				else
				{
					$course['course_options'] &= ~Courses::$options[$snake_case_name];
				}
			}
			elseif ($name === 'allowNeutralLFcategories')
			{
				$course['allow_neutral_lf_categories'] = $value;
			}
			else
			{
				throw new Api\CalDAV\JsParseException("Invalid options attribute '$name'!");
			}
		}
		return $course;
	}

	/**
	 * Return participants object / account_id => participant object pairs
	 *
	 * @param array $course
	 * @param bool|null $is_staff
	 * @return array
	 * @throws Api\Exception\NotFound
	 * @throws Api\Exception\WrongParameter
	 */
	protected static function Participants(array $course, ?bool $is_staff=null)
	{
		if (!isset($is_staff)) $is_staff = (bool)self::$bo->isStaff($course);
		$object = [];
		foreach ($course['participants'] as $participant)
		{
			$object[$participant['account_id']] = array_filter([
				self::AT_TYPE => self::TYPE_PARTICIPANT,
				'account' => self::account($participant['account_id']),
				'alias' => $participant['participant_alias'],
				'name' => Bo::participantName($participant, $is_staff),
				'role' => Bo::role2label($participant, $course),
				'group' => $participant['participant_group'] ?? null,
				'subscribed' => self::UTCDateTime($participant['participant_subscribed']),
				'unsubscribed' => self::UTCDateTime($participant['participant_unsubscribed']),
			]);
		}
		return $object;
	}

	public static function parseParticipant(array $data)
	{
		return [
			'account_id' => self::parseAccount($data['account']),
			'alias' => $data['alias'] ?? null,
			'role' => Bo::label2role($data['role'] ?? 'student'),
			'password' => $data['password'] ?? null,
		];
	}

	protected static function parseRole(?string $role)
	{

	}

	/**
	 * Return object with video_id => video_name pairs
	 *
	 * @param array $course
	 * @return array
	 */
	protected static function Materials(array $course)
	{
		return array_map(function (array $video)
		{
			return $video['video_name'];
		}, $course['videos']);
	}

	/**
	 * Get JsCourse for given course
	 *
	 * @param int|array $video
	 * @param bool|"pretty" $encode true: JSON encode, "pretty": JSON encode with pretty-print, false: return raw data e.g. from listing
	 * @return string|array
	 * @throws Api\Exception\NotFound|\Exception
	 */
	public static function JsMaterial($video, $encode=true)
	{
		if (is_scalar($video) && !($video = self::$bo->readVideo($video)))
		{
			throw new Api\Exception\NotFound();
		}

		$data = array_filter([
			self::AT_TYPE => self::TYPE_MATERIAL,
			'id' => (int)$video['video_id'],
			'course' => (int)$video['course_id'],
			'name' => $video['video_name'],
			'date' => self::UTCDateTime($video['video_date']),
			'question' => $video['video_question'],
			'hash' => $video['video_hash'],
			'url' => Api\Framework::getUrl($video['video_src']),
			'type' => $video['video_type'],
			'commentType' => self::commentType($video['video_options']),
			'published' => self::published($video['video_published']),
			'publishedStart' => $video['video_published_start'] ? self::DateTime($video['video_published_start']) : null,
			'publishedEnd' => $video['video_published_end'] ? self::DateTime($video['video_published_end']) : null,
			'timezone' => $video['video_published_start'] || $video['video_published_end'] ? Api\DateTime::$user_timezone->getName() : null,
			'testDuration' => $video['video_test_duration'],
			'testOptions' => self::testOptions($video['video_test_options']),
			'testDisplay' => self::testDisplay($video['video_test_display']),
			'livefeedback' => $video['livefeedback'] ?? null,
			'livefeedbackSession' => $video['livefeedback_session'] ?? null,
			'attachments' => $video[$path="/apps/smallpart/$video[course_id]/$video[video_id]/all/task/"] ? array_combine(
				array_map(static function($attachment)
				{
					return $attachment['name'];
				}, $video[$path]),
				array_map(JsObjects::class.'::attachment', $video[$path])) : null,
		]);

		if ($encode)
		{
			return Api\CalDAV::json_encode($data, $encode === "pretty");
		}
		return $data;
	}

	protected static $published2label = [
		Bo::VIDEO_DRAFT => 'draft',
		Bo::VIDEO_PUBLISHED => 'published',
		Bo::VIDEO_UNAVAILABLE => 'unavailable',
		Bo::VIDEO_READONLY => 'readonly',
	];

	/**
	 * @param int $published
	 * @return string
	 */
	protected static function published(int $published)
	{
		return self::$published2label[$published] ?? throw new Api\CalDAV\JsParseException("Invalid published value $published");
	}

	protected static function parsePublished(string $published)
	{
		if (($value = array_search($published, self::$published2label)) === false)
		{
			throw new Api\CalDAV\JsParseException("Invalid published value '$published'!");
		}
		return $value;
	}

	protected static $comment_option2label = [
		Bo::COMMENTS_SHOW_ALL => 'show-all',
		Bo::COMMENTS_GROUP => 'show-group',
		Bo::COMMENTS_HIDE_OTHER_STUDENTS => 'hide-other-students',
		Bo::COMMENTS_HIDE_TEACHERS => 'hide-teachers',
		Bo::COMMENTS_GROUP_HIDE_TEACHERS => 'show-group-hide-teachers',
		Bo::COMMENTS_SHOW_OWN => 'show-own',
		Bo::COMMENTS_FORBIDDEN_BY_STUDENTS => 'forbid-students',
		Bo::COMMENTS_DISABLED => 'disabled',
	];

	protected static function commentType(int $option)
	{
		return self::$comment_option2label[$option] ?? throw new Api\CalDAV\JsParseException("Invalid commentOptions value $option");
	}

	protected static function parseCommentType(string $name)
	{
		if (($option = array_search($name, self::$comment_option2label, true)) === false)
		{
			throw new Api\CalDAV\JsParseException("Invalid option value '$name'");
		}
		return $option;
	}

	protected static $testOption2label = [
		Bo::TEST_OPTION_ALLOW_PAUSE => 'allowPause',
		Bo::TEST_OPTION_FORBID_SEEK => 'forbidSeek',
	];

	protected static function testOptions(int $option)
	{
		$options = [];
		foreach (self::$testOption2label as $mask => $name)
		{
			$options[$name] = ($option & $mask) === $option;
		}
		return $options;
	}

	protected static function parseTestOptions(array $video, array $options)
	{
		foreach($options as $name => $value)
		{
			if (($mask = array_search($name, self::$testOption2label, true)) !== false)
			{
				if ($value)
				{
					$video['video_test_options'] |= $mask;
				}
				else
				{
					$video['video_test_options'] &= ~$mask;
				}
			}
			else
			{
				throw new Api\CalDAV\JsParseException("Invalid testOptions attribute '$name'");
			}
		}
		return $video;
	}

	protected static $display2label = [
		Bo::TEST_DISPLAY_COMMENTS => 'instead-comments',
		Bo::TEST_DISPLAY_DIALOG => 'dialog',
		Bo::TEST_DISPLAY_VIDEO => 'video-overlay',
	];

	protected static function testDisplay(int $display)
	{
		return self::$display2label[$display] ?? throw new Api\CalDAV\JsParseException("Invalid test-display value $display");
	}

	protected static function parseTestDisplay(string $name)
	{
		if (($option = array_search($name, self::$display2label, true)) === false)
		{
			throw new Api\CalDAV\JsParseException("Invalid testDisplay value '$name'");
		}
		return $option;
	}

	protected static function attachment($attachment)
	{
		return [
			'name' => $attachment['name'],
			'url'  => Api\Framework::getUrl(Api\Framework::link($attachment['download_url'])),
			'mime' => $attachment['mime'],
			'size' => (int)$attachment['size'],
		];
	}


	/**
	 * Parse JsCourse
	 *
	 * @param string $json
	 * @param array $old=[] existing course for patch
	 * @param ?string $content_type=null application/json no strict parsing and automatic patch detection, if method not 'PATCH' or 'PUT'
	 * @param string $method='PUT' 'PUT', 'POST' or 'PATCH'
	 * @return array with "course_" prefix
	 */
	public static function parseJsCourse(string $json, array $old=[], string $content_type=null, $method='PUT')
	{
		try
		{
			$data = json_decode($json, true, 10, JSON_THROW_ON_ERROR);

			// check if we use patch: method is PATCH or method is POST AND keys contain slashes
			if ($method === 'PATCH')
			{
				// apply patch on JsCard of contact
				$data = self::patch($data, $old ? self::JsCourse($old, false) : [], !$old);
			}

			//if (!isset($data['uid'])) $data['uid'] = null;  // to fail below, if it does not exist

			// check required fields
			if (!$old || $method !== 'PATCH')
			{
				static $required = ['name'];
				if (($missing = array_diff_key(array_filter(array_intersect_key($data, array_flip($required))), array_flip($required))))
				{
					throw new Api\CalDAV\JsParseException("Required field(s) ".implode(', ', $missing)." missing");
				}
			}

			$course = $method === 'PATCH' ? $old : ($old ? array_diff_key($old, array_flip(['course_owner'])) : []);
			foreach ($data as $name => $value)
			{
				switch ($name)
				{
					case 'name':
					case 'info':
					case 'disclaimer':
					case 'password':
						$course['course_'.$name] = $value;
						break;

					case 'org':
						$course['course_'.$name] = self::parseAccount($value, false);
						break;

					case 'closed':
						$course['course_'.$name] = self::parseDateTime($value);
						break;

					case 'start':
						$course['ts_start'] = Api\DateTime::server2user($value, 'ts');
						break;

					case 'options':
						$course = self::parseCourseOptions($course, $value);
						break;

					case 'owner':
					case 'participants':
					case 'materials':
						if ($method !== 'PATCH')
						{
							throw new Api\CalDAV\JsParseException("You must NOT set readonly attribute '$name'");
						}
						break;

					case 'id':
					case self::AT_TYPE:
					case 'etag':
						break;

					default:
						error_log(__METHOD__ . "() $name=" . json_encode($value, self::JSON_OPTIONS_ERROR) . ' --> ignored');
						break;
				}
			}
		}
		catch (\Throwable $e) {
			self::handleExceptions($e, 'JsCourse', $name, $value);
		}

		return $course;
	}

	/**
	 * Parse JsMaterial
	 *
	 * @param string $json
	 * @param array $old=[] existing course for patch
	 * @param ?string $content_type=null application/json no strict parsing and automatic patch detection, if method not 'PATCH' or 'PUT'
	 * @param string $method='PUT' 'PUT', 'POST' or 'PATCH'
	 * @return array with "course_" prefix
	 */
	public static function parseJsMaterial(string $json, array $old=[], string $content_type=null, $method='PUT')
	{
		try
		{
			$data = json_decode($json, true, 10, JSON_THROW_ON_ERROR);

			// check if we use patch: method is PATCH or method is POST AND keys contain slashes
			if ($method === 'PATCH')
			{
				// apply patch on JsCard of contact
				$data = self::patch($data, $old ? self::JsMaterial($old, false) : [], !$old);
			}

			//if (!isset($data['uid'])) $data['uid'] = null;  // to fail below, if it does not exist

			// check required fields
			if (!$old || $method !== 'PATCH')
			{
				static $required = ['name'];
				if (($missing = array_diff_key(array_filter(array_intersect_key($data, array_flip($required))), array_flip($required))))
				{
					throw new Api\CalDAV\JsParseException("Required field(s) ".implode(', ', $missing)." missing");
				}
			}

			$video = $method === 'PATCH' ? $old : ($old ? array_diff_key($old, array_flip(['course_owner'])) : []);
			foreach ($data as $name => $value)
			{
				switch ($name)
				{
					case 'name':
					case 'question':
						$video['video_'.$name] = $value;
						break;

					case 'url':
						Bo::checkVideoURL($value, $video['video_type']);
						$video['video_'.$name] = $value;
						$video['video_type'] = substr($video['video_type'], 6); // remove "video/"
						break;

					case 'commentType':
						$video['video_options'] = self::parseCommentType($value);
						break;

					case 'published':
						$video['video_'.$name] = self::parsePublished($value);
						break;

					case 'publishedStart':
					case 'publishedEnd':
						$video['video_'.self::snake_case($name)] = self::parseDateTime($value, $data['timezone'] ?? null);
						break;

					case 'testDuration':
						$video['video_test_duration'] = parseInt($value);
						break;

					case 'testOptions':
						$video = self::parseTestOptions($video, $value);
						break;

					case 'testDisplay':
						$video['video_test_display'] = self::parseTestDisplay($value);
						break;

					case 'livefeedback':
					case 'livefeedbackSession':
					$video[self::snake_case($name)] = $value;
						break;

					case 'course':
					case 'hash':
					case 'attachments':
					case 'type':
						if ($method !== 'PATCH')
						{
							throw new Api\CalDAV\JsParseException("You must NOT set readonly attribute '$name'");
						}
						break;

					case self::AT_TYPE:
					case 'id':
					case 'etag':
					case 'date':
					case 'timezone':
						break;

					default:
						error_log(__METHOD__ . "() $name=" . json_encode($value, self::JSON_OPTIONS_ERROR) . ' --> ignored');
						break;
				}
			}
		}
		catch (\Throwable $e) {
			self::handleExceptions($e, 'JsMaterial', $name, $value);
		}

		return $video;
	}

	/**
	 * Parse a status label into it's numerical ID
	 *
	 * @param string $value
	 * @return int|null
	 * @throws Api\CalDAV\JsParseException
	 */
	protected static function parseStatus(string $value)
	{
		static $bo=null;
		if (!isset($bo)) $bo = new \timesheet_bo();

		if (($status_id = array_search($value, $bo->status_labels)) === false)
		{
			throw new Api\CalDAV\JsParseException("Invalid status value '$value', allowed '".implode("', '", $bo->status_labels)."'");
		}
		return $status_id;
	}

	public static function initStatic()
	{
		self::$bo = new Bo();
	}
}
JsObjects::initStatic();