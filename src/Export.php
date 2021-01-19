<?php
/**
 * EGroupware - SmallParT - business logic
 *
 * @link https://www.egroupware.org
 * @package smallpart
 * @subpackage export
 * @license https://spdx.org/licenses/AGPL-3.0-or-later.html GNU Affero General Public License v3.0 or later
 */

namespace EGroupware\SmallParT;

use EGroupware\Api;
use EGroupware\Api\Acl;

/**
 * smallPART - Export and import
 */
class Export
{
	/**
	 * @var Bo
	 */
	protected $bo;
	
	public function __construct(Bo $bo=null)
	{
		$this->bo = $bo ?? new Bo();
	}

	/**
	 * Export whole course as compressed JSON file
	 *
	 * @param int|array $course
	 * @param array $options values for video_id, exclude_(participant(s|_comments|answers))
	 * @throws Api\Exception\WrongParameter|Api\Exception\NoPermission
	 */
	public function jsonExport($course, array $options)
	{
		if (!($course = $this->bo->read(is_array($course) ? $course['course_id'] : $course)))
		{
			throw new Api\Exception\WrongParameter("Course not found!");
		}
		if (!$this->bo->isAdmin($course))
		{
			throw new Api\Exception\NoPermission();
		}
		if (empty($options['participants']))
		{
			unset($course['participants']);
		}
		$course['videos'] = empty($options['video_id']) ? array_values($course['videos']) : [$course['videos'][$options['video_id']]];
		foreach($course['videos'] as &$video)
		{
			$video['comments'] = array_values($this->bo->listComments($video['video_id'], ['course_id' => $course['course_id']],
				!empty($options['participants']) && !empty($options['participant_comments']) ?
					Bo::COMMENTS_SHOW_ALL : Bo::COMMENTS_OWNER_ONLY));

			if (empty($options['comment_history']))
			{
				foreach($video['comments'] as &$comment)
				{
					unset($comment['comment_history']);
				}
			}

			$overlay = Overlay::read(['video_id' => $video['video_id']], 0, PHP_INT_MAX);
			foreach($overlay['elements'] as $element)
			{
				unset($element['courseAdmin'], $element['account_id'], $element['answer_data'], $element['overlay_player_mode']);

				if (strpos($element['overlay_type'], 'smallpart-overlay-') === 0)
				{
					$video['overlay'][] = $element;
				}
				else
				{
					$video['questions'][] = $element;
					$video['max_score'] = (float)$overlay['max_score'];
				}
			}
		}
		$json = json_encode($course, JSON_PRETTY_PRINT|JSON_UNESCAPED_SLASHES|JSON_UNESCAPED_UNICODE);
		if (function_exists('bzcompress'))
		{
			$json = bzcompress($json);
			$mime_type = 'application/x-bzip2';
			$extension = '.json.bz2';
		}
		elseif (function_exists('gzcompress'))
		{
			$json = gzcompress($json);
			$mime_type = 'application/gzip';
			$extension = '.json.gz';
		}
		else
		{
			$mime_type = 'application/json';
			$extension = '.json';
		}
		Api\Header\Content::type($course['course_name'].$extension, $mime_type, bytes($json));
		echo $json;
		exit;
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
		if (!is_array($course) && !($course = $this->bo->read($course)))
		{
			throw new Api\Exception\WrongParameter("Course not found!");
		}
		if ($this->bo->isAdmin($course))
		{

		}
		elseif ($this->bo->isParticipant($course))
		{
			// do NOT export full names to participants
			unset(self::$export_comment_cols[array_search('account_fullname', self::$export_comment_cols)]);

			// students are limited to export comments of one video, as options are video-specific
			if (!$video_id || !($video = $this->bo->readVideo($video_id)))
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
		foreach($this->bo->listComments($video_id, array_filter($where), $overwrite_options) as $row)
		{
			$row += $course;	// make course values availabe too
			if (!isset($video) || $video['video_id'] != $row['video_id'])
			{
				$video = $this->bo->readVideo($row['video_id']);
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
	protected static function csv_escape(array $row)
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
}