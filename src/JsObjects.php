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
			'owner' => (int)$course['course_owner'],
			'org' => $course['course_org'] ? (int)$course['course_org'] : null,
			'closed' => $course['course_closed'] ? true : null,
			'options' => isset($course['course_options']) ? self::courseOptions($course) : null,
			'participants' => $course['participants'] ? self::Participants($course) : null,
			'materials' => $course['videos'] ? self::Materials($course) : null,
		]);

		if ($encode)
		{
			return Api\CalDAV::json_encode($data, $encode === "pretty");
		}
		return $data;
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
			$options[preg_replace_callback('/_(.)/', static function($matches)
			{
				return strtoupper($matches[1]);
			}, $name)] = ($course['options'] & $mask) === $mask;
		}
		$options['allowNeutralLFcategories'] = (bool)$course['allow_neutral_lf_categories'];
		return $options;
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
		if (!isset($is_staff)) $is_staff = self::$bo->isStaff($course);
		$object = [];
		foreach ($course['participants'] as $participant)
		{
			$object[$participant['account_id']] = array_filter([
				'id' => (int)$participant['account_id'],
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
			'commentOptions' => self::commentOptions($video['video_options']),
			'published' => self::published($video['video_published']),
			'publishedStart' => $video['video_published_start'] ? self::UTCDateTime($video['video_published_start']) : null,
			'publishedEnd' => $video['video_published_end'] ? self::UTCDateTime($video['video_published_start']) : null,
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

	/**
	 * @param int $published
	 * @return string
	 */
	protected static function published(int $published)
	{
		static $published2label = [
			Bo::VIDEO_DRAFT => 'draft',
			Bo::VIDEO_PUBLISHED => 'published',
			Bo::VIDEO_UNAVAILABLE => 'unavailable',
			Bo::VIDEO_READONLY => 'readonly',
		];
		return $published2label[$published] ?? throw new \InvalidArgumentException("Invalid published value $published");
	}

	protected static function commentOptions(int $option)
	{
		static $option2label = [
			Bo::COMMENTS_SHOW_ALL => 'show-all',
			Bo::COMMENTS_GROUP => 'show-group',
			Bo::COMMENTS_HIDE_OTHER_STUDENTS => 'hide-other-students',
			Bo::COMMENTS_HIDE_TEACHERS => 'hide-teachers',
			Bo::COMMENTS_GROUP_HIDE_TEACHERS => 'show-group-hide-teachers',
			Bo::COMMENTS_SHOW_OWN => 'show-own',
			Bo::COMMENTS_FORBIDDEN_BY_STUDENTS => 'forbid-students',
			Bo::COMMENTS_DISABLED => 'disabled',
		];
		return $option2label[$option] ?? throw new \InvalidArgumentException("Invalid commentOptions value $option");
	}

	protected static function testOptions(int $option)
	{
		static $option2label = [
			Bo::TEST_OPTION_ALLOW_PAUSE => 'allowPause',
			Bo::TEST_OPTION_FORBID_SEEK => 'forbidSeek',
		];
		$options = [];
		foreach ($option2label as $mask => $name)
		{
			$options[$name] = ($option & $mask) === $option;
		}
		return $options;
	}

	protected static function testDisplay(int $display)
	{
		static $display2label = [
			Bo::TEST_DISPLAY_COMMENTS => 'instead-comments',
			Bo::TEST_DISPLAY_DIALOG => 'dialog',
			Bo::TEST_DISPLAY_VIDEO => 'video-overlay',
		];
		return $display2label[$display] ?? throw new \InvalidArgumentException("Invalid test-display value $display");
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
	 * Parse JsTimesheet
	 *
	 * @param string $json
	 * @param array $old=[] existing contact for patch
	 * @param ?string $content_type=null application/json no strict parsing and automatic patch detection, if method not 'PATCH' or 'PUT'
	 * @param string $method='PUT' 'PUT', 'POST' or 'PATCH'
	 * @return array with "ts_" prefix
	 */
	public static function parseJsTimesheet(string $json, array $old=[], string $content_type=null, $method='PUT')
	{
		try
		{
			$data = json_decode($json, true, 10, JSON_THROW_ON_ERROR);

			// check if we use patch: method is PATCH or method is POST AND keys contain slashes
			if ($method === 'PATCH')
			{
				// apply patch on JsCard of contact
				$data = self::patch($data, $old ? self::JsTimesheet($old, false) : [], !$old);
			}

			//if (!isset($data['uid'])) $data['uid'] = null;  // to fail below, if it does not exist

			// check required fields
			if (!$old || !$method === 'PATCH')
			{
				static $required = ['title', 'start', 'duration'];
				if (($missing = array_diff_key(array_filter(array_intersect_key($data, array_flip($required))), array_flip($required))))
				{
					throw new Api\CalDAV\JsParseException("Required field(s) ".implode(', ', $missing)." missing");
				}
			}

			$course = $method === 'PATCH' ? $old : [];
			foreach ($data as $name => $value)
			{
				switch ($name)
				{
					case 'title':
					case 'description':
					case 'project':
						$course['ts_'.$name] = $value;
						break;

					case 'start':
						$course['ts_start'] = Api\DateTime::server2user($value, 'ts');
						break;

					case 'duration':
						$course['ts_duration'] = self::parseInt($value);
						// set default quantity, if none explicitly given
						if (!isset($course['ts_quantity']))
						{
							$course['ts_quantity'] = $course['ts_duration'] / 60.0;
						}
						break;

					case 'paused':
						$course['ts_paused'] = self::parseInt($value);
						break;

					case 'pricelist':
						$course['pl_id'] = self::parseInt($value);
						break;

					case 'quantity':
					case 'unitprice':
						$course['ts_'.$name] = self::parseFloat($value);
						break;

					case 'owner':
						$course['ts_owner'] = self::parseAccount($value);
						break;

					case 'category':
						$course['cat_id'] = self::parseCategories($value, false);
						break;

					case 'status':
						$course['ts_status'] = self::parseStatus($value);
						break;

					case 'egroupware.org:customfields':
						$course = array_merge($course, self::parseCustomfields($value));
						break;

					case 'prodId':
					case 'created':
					case 'modified':
					case 'modifier':
					case self::AT_TYPE:
					case 'id':
					case 'etag':
						break;

					default:
						error_log(__METHOD__ . "() $name=" . json_encode($value, self::JSON_OPTIONS_ERROR) . ' --> ignored');
						break;
				}
			}
		}
		catch (\Throwable $e) {
			self::handleExceptions($e, 'JsTimesheet', $name, $value);
		}

		return $course;
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