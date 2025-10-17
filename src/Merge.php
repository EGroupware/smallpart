<?php
/**
 * EGroupware - ViDoTeach - Merge print
 *
 * @link https://www.egroupware.org
 * @author Ralf Becker <rb-AT-egroupware.org>
 * @package smallpart
 * @license https://www.egroupware.org/EPL EPL - EGroupware Enterprise Line
 * @copyright (c) 2024 by Ralf Becker <rb-AT-egroupware.org>
 */

namespace EGroupware\SmallParT;

use EGroupware\Api;
use EGroupware\SmallParT\Student\Ui;

class Merge extends Api\Storage\Merge
{
	const APPNAME = 'smallpart';

	/**
	 * Functions that can be called via menuaction
	 *
	 * @var array
	 */
	public $public_functions = [
		'download_by_request'	=> true,
		'show_replacements'		=> true,
		'smallpart_replacements'	=> true,
		'merge_entries'			=> true
	];

	/**
	 * Fields that are numeric, for special numeric handling
	 */
	protected $numeric_fields = [
	];

	public $date_fields = [
		'video_date' => 'MaterialDate',
		'video_published_start' => 'MaterialPublishedStart',
		'video_published_end' => 'MaterialPublishedEnd',
	];

	/**
	 * Business object to pull records from
	 */
	protected Bo $bo;

	/**
	 * Cache materials per course to reduce database hits
	 */
	protected $materials_cache = [];

	/**
	 * Constructor
	 *
	 */
	function __construct()
	{
		parent::__construct();

		// register our table-plugin for positions
		$this->table_plugins['Materials'] = 'materials';

		$this->bo = new Bo();
	}

	/**
	 * Get replacements
	 *
	 * @param int $id id of entry
	 * @param string &$content=null content to create some replacements only if they are use
	 * @return array|boolean
	 */
	protected function get_replacements($id,&$content=null)
	{
		[$course_id,$video_id] = explode(':', $id)+[null, null];

		if (!($replacements = $this->course_replacements($course_id, '', $content)))
		{
			return false;
		}
		// if we have a video, add its replacements (specially custom-fields!) with higher precedence
		if ($video_id && ($video_replacements = $this->video_replacements($video_id, '', $content)))
		{
			$replacements = $video_replacements + $replacements;
		}
		// Set any missing custom fields, or the marker will stay
		foreach(Api\Storage\Customfields::get(self::APPNAME) as $name => $field)
		{
			if (!isset($replacements['$$#'.$name.'$$']))
			{
				$replacements['$$#'.$name.'$$'] = '';
			}
		}
		return $replacements;
	}

	/**
	 * Get course replacements
	 *
	 * @param int|string $id id of course or "<course_id>:<video_id>"
	 * @param string $prefix='' prefix like eg. 'erole'
	 * @return array|boolean
	 */
	protected function course_replacements($id, $prefix='', &$content = null)
	{
		if (!($course = $this->bo->read((int)$id)))
		{
			return false;
		}

		// fill table plugins caches to not have to re-read their data
		$this->get_materials($id, $course['videos'] ?? []);

		$info = [];
		foreach($course as $name => $value)
		{
			// create nicer placeholder-names
			$parts = explode('_', $name);
			$placeholder = '$$'.implode('', array_map('ucfirst', $parts)).'$$';

			switch($name)
			{
				case 'course_closed':
				case 'course_secret':
				case 'course_options':
				case 'allow_neutral_lf_categories':
				case 'export_columns':
				case 'course_parent':
				case 'notify_participants':
				case 'student_uploads':
				case 'videos':
				case 'participants':
				case 'clm':
				case 'cats':
				case 'config':
				case 'user_timezone_read':
					continue 2; // do NOT export/expose

				case 'course_owner':
				case 'course_org':
					$info[$placeholder] = Api\Accounts::id2name($value, $name === 'course_org' ? 'account_lid' : 'account_fullname');
					break;

				case 'course_id':
					$info['$$CourseLink$$'] = Api\Framework::getUrl(Api\Egw::link('/index.php', [
						'menuaction' => Bo::APPNAME.'.'.Ui::class.'.start',
						'course_id' => $value,
						'ajax' => 'true',
					]));
					$info['$$CourseLink/href$$'] = Api\Html::a_href(Api\Html::htmlspecialchars($course['course_name']), $info['$$CourseLink$$']);
					$info['$$CourseID$$'] = $value;
					break;

				case 'course_password': // dont print hash
					$info[$placeholder] = str_starts_with($value, '$2y$') ? '' : $value;
					break;

				default:
					$info[$placeholder] = $value;
					break;
			}
		}

		// Links
		$info += $this->get_all_links(self::APPNAME, $id, $prefix, $content);

		return $info;
	}

	/**
	 * Get video/material replacements
	 *
	 * @param int|string|array $video id of video or video-array
	 * @param string $prefix='' prefix like eg. 'erole'
	 * @return array|boolean
	 */
	protected function video_replacements($video, $prefix='', &$content = null)
	{
		if (!is_array($video) && !($video = $this->bo->readVideo($video)))
		{
			return false;
		}

		$info = [];
		foreach($video as $name => $value)
		{
			// create nicer placeholder-names
			$parts = explode('_', $name);
			switch($parts[0])
			{
				case 'video':
					$parts[0] = 'material';
					break;
			}
			$placeholder = '$$'.implode('', array_map('ucfirst', $parts)).'$$';

			switch($name)
			{
				case 'video_hash':
				case 'video_options':
				case 'video_test_options':
				case 'video_test_display':
				case 'video_limit_access':
				case 'video_published_prerequisite':
				case 'video_src':   // fallback in video_url
				case 'is_admin':
				case 'error_msg':
				case 'accessible':
				case 'last_updated':
					continue 2; // do NOT export/expose

				case 'owner':
					$info[$placeholder] = $value ? Api\Accounts::id2name($value, $name === 'course_org' ? 'account_lid' : 'account_fullname') : '';
					break;

				case 'video_id':
					$info['$$MaterialLink$$'] = Api\Framework::getUrl(Api\Egw::link('/index.php', [
						'menuaction' => Bo::APPNAME.'.'.Ui::class.'.index',
						'video_id' => $video['video_id'],
						'ajax' => 'true',
					]));
					$info['$$MaterialLink/href$$'] = Api\Html::a_href(Api\Html::htmlspecialchars($video['video_name']), $info['$$MaterialLink$$']);
					$info['$$MaterialID$$'] = $value;
					break;

				case 'video_url':
					$info['$$MaterialURL$$'] = $value = Api\Framework::getUrl($value ?? $video['video_src']);
					$info['$$MaterialURL/href$$'] = Api\Html::a_href(Api\Html::htmlspecialchars($video['video_name']), $value);
					break;

				case 'video_date':
				case 'video_published_start':
				case 'video_published_end':
					$info[$placeholder] = $value ? Api\DateTime::to($value, '') : '';
					break;

				case 'video_test_duration':
					$info[$placeholder] = $value ? sprintf('%d:%02d', $value/60, $value%60) : '';
					break;

				case 'video_published':
					$info[$placeholder] = sprintf('%d:%02d', $value/60, $value%60);
					break;

				case 'status':
					$info['$$MaterialStatus$$'] = $value;
					break;

				case 'mime_type':
					$info['$$MaterialMimeType$$'] = $value;
					break;

				default:
					$info[$placeholder] = $value;
					break;
			}
		}

		// Links
		$info += $this->get_all_links(self::APPNAME, $id, $prefix, $content);

		return $info;
	}

	/**
	 * Get a list of placeholders provided.
	 *
	 * Placeholders are grouped logically.  Group key should have a user-friendly translation.
	 */
	public function get_placeholder_list($prefix = '')
	{
		$placeholders = array_merge([
			'Course' => [],
			'custom fields' => [],
			'Material' => [],
			'Materials' => [],
		], parent::get_placeholder_list($prefix));

		$fields = [
			'CourseID' => lang('Course ID'),
			'CourseName' => lang('Course name'),
			'CourseLink' => lang('Direct link').': '.lang('URL of current record'),
			'CourseLink/href' => lang('Direct link').': '.lang('HTML link to the current record'),
			'CoursePassword' => lang('Password'),
			'CourseInfo' => lang('Course information'),
			'CourseDisclaimer' => lang('Disclaimer'),
			'CourseOwner' => lang('Owner'),
			'CourseOrg' => lang('Organization'),
		];

		$fields += [
			'MaterialID' => lang('Material').' '.lang('ID'),
			'MaterialName' => lang('Material Name'),
			'MaterialLink' => lang('Direct link').': '.lang('URL of current record'),
			'MaterialLink/href' => lang('Direct link').': '.lang('HTML link to the current record'),
			'MaterialURL' => lang('URL of Material: Video, PDF, ...').': '.lang('HTML link to the current record'),
			'MaterialURL/href' => lang('URL of Material: Video, PDF, ...'),
			'MaterialQuestion' => lang('Question'),
			'MaterialType' => lang('Type').': MPEG, PDF, YouTube, ...',
			'MaterialMimeType' => lang('Mime type'),
			'MaterialStatus' => lang('status'),
			'MaterialPublishedStart' => lang('Start date'),
			'MaterialPublishedEnd' => lang('End date'),
		];

		// materials table
		$fields["table/Materials"] = '';
		$fields["\tMaterialID"] = lang('Material').' '.lang('ID');
		$fields["\tMaterialName"] = lang('Material Name');
		$fields["\tMaterialStatus"] = lang('status');
		$fields["\tMaterial..."] = lang('See full list to the left');
		$fields["endtable/Materials"] = '';

		foreach($fields as $name => $label)
		{
			$marker = ($name[0] === "\t" ? "\t" : '').$this->prefix($prefix, preg_replace('/(^\t|(endtable)\/.*$)/', '$2', $name), '{');
			if(!array_filter($placeholders, static function ($a) use ($marker)
			{
				return array_key_exists($marker, $a);
			}))
			{
				// group placeholders by Course, Materials
				preg_match('/^(\t|table\/|endtable\/)?(Course|Materials|Material)/', $name, $matches);
				$group = $matches[3] ?? $matches[2];
				if ($matches[1] === "\t" && $group === "Material") $group .= 's';
				$placeholders[$group][] = [
					'value' => $marker,
					'label' => $label
				];
			}
		}

		return $placeholders;
	}

	/**
	 * Table plugin for materials
	 *
	 * @param string $plugin
	 * @param int $course_id
	 * @param int $n
	 * @return array
	 */
	public function materials($plugin, $course_id, $n)
	{
		$materials = $this->get_materials($course_id);

		return $materials[$n] ?? null;
	}

	/**
	 * Get the materials for a course
	 */
	protected function get_materials($id, array $materials = null)
	{
		$course_id = (int)(explode(':', $id)[0]);
		if (!empty($this->materials_cache[$course_id]))
		{
			return $this->materials_cache[$course_id];
		}

		// Clear it to keep memory down - just this invoice
		$this->materials_cache[$course_id] = [];
		foreach($materials ?? $this->bo->listVideos(['course_id' => $course_id]) as $video_id => $material)
		{
			$this->materials_cache[$course_id][] = $this->video_replacements($material);
		}
		return $this->materials_cache[$course_id];
	}
}