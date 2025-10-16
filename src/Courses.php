<?php
/**
 * EGroupware - SmallParT - manage courses
 *
 * @link https://www.egroupware.org
 * @package smallpart
 * @subpackage courses
 * @license https://spdx.org/licenses/AGPL-3.0-or-later.html GNU Affero General Public License v3.0 or later
 */

namespace EGroupware\SmallParT;

use EGroupware\Api;
use EGroupware\Api\Header\ContentSecurityPolicy;
use EGroupware\SmallParT\Student\Ui;

/**
 * SmallParT - manage courses
 *
 *
 */
class Courses
{
	/**
	 * Methods callable via menuaction GET parameter
	 *
	 * @var array
	 */
	public $public_functions = [
		'index' => true,
		'edit'  => true,
		'category' => true
	];

	/**
	 * Instance of our business object
	 *
	 * @var Bo
	 */
	protected $bo;

	/**
	 * Option names and bit-field values
	 *
	 * @var int[]
	 */
	public static $options = [
		'record_watched' => 1,
		'video_watermark' => 2,
		'cognitive_load_measurement' => 5
	];

	/**
	 * Constructor
	 */
	public function __construct()
	{
		$this->bo = new Bo();
	}

	/**
	 * Edit a host
	 *
	 * @param array|int|null $content =null integer course_id
	 * @param ?callable $callback to pass to LTI\Ui::contentSelection instead of calling Framework::window_close
	 * @param ?array $params for callback plus course_id of new created course
	 */
	public function edit($content=null, $callback=null, array $params=null)
	{
		try {
			if (!is_array($content))
			{
				if (!empty($content ?: $_GET['course_id']))
				{
					if (!($content = $this->bo->read(['course_id' => $content ?: $_GET['course_id']])))
					{
						Api\Framework::window_close(lang('Entry not found!'));
					}
					$content['participants']['notify_participants'] = $content['notify_participants'];
					$content['lti_url'] = Api\Header\Http::fullUrl(Api\Egw::link('/smallpart/'));
					$content['lti_key'] = 'course_id='.$content['course_id'];
					// workaround as master regard disabled="!@course_secret" with course_secret===NULL to be true ("" works)
					$content['course_secret'] = (string)$content['course_secret'];
					foreach(self::$options as $name => $mask)
					{
						$content[$name] = ($content['course_options'] & $mask) === $mask;
					}
				}
				else
				{
					$content = $this->bo->init()+[
						'tabs' => 'info',  // open course info tab when adding a new course
					];
				}
				// prepare for autorepeat
				$content['participants'] = array_merge([false, false], $content['participants']);
				array_unshift($content['cats'], false);
				$content['videos'] = array_values($content['videos']);
				$content['callback'] = $callback;
				$content['params'] = $params;
			}
			elseif (!empty($content['participants']['subscribe']) && !empty($content['participants']['account_id']))
			{
				foreach ($content['participants']['account_id'] as $participant)
				{
					$subcribed = 0;
					foreach($participant < 0 ? Api\Accounts::getInstance()->members($participant) : (array)$participant as $account_id)
					{
						// ignore already subscribed participants
						if (!array_filter($content['participants'], static function($participant) use ($account_id)
						{
							return is_array($participant) && $participant['account_id'] == $account_id && empty($participant['participant_unsubscribed']);
						}))
						{
							++$subcribed;
							$this->bo->subscribe($content['course_id'], true, $account_id, true, $content['participants']['participant_role']);
							$this->bo->setNotifyParticipant($content['course_id'], $account_id, $content['participants']['notify_participants']);
							$content['participants'][] = [
								'account_id' => $account_id,
								'participant_role' => $content['participants']['participant_role'],
								'primary_group' => Api\Accounts::id2name($account_id, 'primary_group'),
								'participant_subscribed' => new Api\DateTime('now'),
								'participant_unsubscribed' => null,
								'notify' => $content['participants']['notify_participants']
							];
						}
					}
					Api\Framework::message(lang('%1 subscribed.', $subcribed > 1 ? $subcribed : Bo::participantName(['account_id' => $account_id], true)));
				}
				unset($content['participants']['account_id'], $content['participants']['subscribe']);
			}
			elseif (!empty($content['participants']['unsubscribe']))
			{
				$this->bo->subscribe($content['course_id'], false, $account_id = key($content['participants']['unsubscribe']));
				Api\Framework::message(lang('%1 unsubscribed.', Bo::participantName(['account_id' => $account_id], true)));
				unset($content['participants']['unsubscribe'], $content['videos']['upload']);
				$content['participants'] = array_map(static function($participant) use ($account_id)
				{
					if (is_array($participant) && $participant['account_id'] == $account_id)
					{
						$participant['participant_unsubscribed'] = new Api\DateTime('now');
					}
					return $participant;
				}, $content['participants']);
			}
			elseif(!empty($content['videos']['upload']) || !empty($content['add_video_link']) || !empty($content['add_lf_video']))
			{
				if (empty($content['course_id']))	// need to save course first
				{
					$content = array_merge($content, $this->bo->save($content));
				}
				$upload = $content['videos']['upload'] ?: $content['video_url'];
				unset($content['videos']['upload'], $content['videos']['video'], $content['video_url'], $content['add_video_link']);
				// Add livefeedback dummy video
				if($content['add_lf_video'])
				{
					$upload = [
						'name' => 'livefeedback.webm',
						'type' => 'video/webm',
						'tmp_name' => $upload
					];
				}
				$newVideo = $this->bo->addVideo($content['course_id'], $upload);
				if($newVideo && $content['add_lf_video'])
				{
					$newVideo['video_name'] = $newVideo['video_id'].'_livefeedback_'.(new Api\DateTime('now'))->format('Y-m-d H:i').'.webm';
					$this->bo->saveVideo($newVideo);
					$this->bo->addLivefeedback($content['course_id'], $newVideo);
					unset($content['add_lf_video']);
				}
				$newVideo['class'] = 'new';
				$newVideo['open'] = true;
				$content['videos'] = array_merge([false, $newVideo], array_slice($content['videos'], 1));
				Api\Framework::message(lang('Video successful uploaded.'));
			}
			elseif (!empty($content['videos']['delete']))
			{
				foreach($content['videos'] as $key => $video)
				{
					if (is_array($video) && $video['video_id'] == key($content['videos']['delete']))
					{
						// deleting of videos which already has comments, requires an extra confirmation by clicking delete again
						$confirmed = $content['confirm_delete'] == $video['video_id'];
						$content['confirm_delete'] = $video['video_id'];
						$this->bo->deleteVideo($video, $confirmed);
						Api\Framework::message(lang('Video deleted.'));
						// remove video from our internal data AND renumber rows to have no gaps
						unset($content['videos']['delete'], $content['confirm_delete'], $content['videos']['upload'],
							$content['videos']['hide'], $content['video_url']
						);
						$content['videos'] = self::removeByAttributeValue($content['videos'], 'video_id', $video['video_id']);
						break;
					}
				}
			}
			elseif (!empty($content['videos']['download']))
			{
				$export = new Export($this->bo);
				$export->downloadComments($content, key($content['videos']['download']));	// won't return unless an error
			}
			elseif(!empty($content['clm']['process']['add']) || !empty($content['clm']['post']['add']))
			{
				$section = !empty($content['clm']['process']['add']) ? 'process' : 'post';
				$content['clm'][$section]['questions'][] = ['q' => ''];
			}
			elseif (!empty($content['button']))
			{
				unset($content['edit_course_name']);
				unset($content['edit_course_password']);
				switch ($button = key($content['button']))
				{
					case 'copy_course':
						$content = $this->bo->copyCourse(
							$content['course_id'],
							$content['export']['video_id'] ? (array)$content['export']['video_id'] : null,
							$content['export']['categories'] ? null : [],
							$content['export']['participants'] ? null : [],
							['comments' => $content['export']['participant_comments']]
						);
						array_unshift($content['videos'], false);
						array_unshift($content['cats'], false);
						array_unshift($content['participants'], false);
						$content['tabs'] = "info";
						$content['edit_course_name'] = true;
						Api\Framework::refresh_opener(lang('Copied'), Bo::APPNAME, $content['course_id'], 'add');
						break;
					case 'download':
						$export = new Export($this->bo);
						$export->downloadComments($content);	// won't return unless an error
						break;
					case 'export':
						$export = new Export($this->bo);
						$export->jsonExport($content, $content['export']);
						break;
					case 'import':
						$export = new Export($this->bo);
						if (($course_id = $export->jsonImport($content, $content['import'], $content['import_overwrite'] === 'true', $content['export'])))
						{
							Api\Framework::refresh_opener(lang('Course imported.'),
								Bo::APPNAME, $course_id, empty($content['course_id']) ? 'add' : 'edit');
							// reload everything
							return $this->edit($course_id, $content['callback'], $content['params']);
						}
						break;
					case 'disclaimer_reset':
						unset($content['button']);
						array_walk($content['participants'], function (&$participant) use ($content)
						{
							if(is_array($participant) && $participant['participant_role'] == Bo::ROLE_STUDENT && array_key_exists('participant_agreed', $participant))
							{
								$this->bo->subscribe($content['course_id'], true, $participant['account_id'], true, $participant['participant_role'], null);
							}
						});
						break;
					case 'generate':
						$content['lti_key'] = 'course_id='.$content['course_id'];
						$content['course_secret'] = Api\Auth::randomstring('32');
						// fall-through
					case 'delete-lti':
						if ($button === 'delete-lti') unset($content['course_secret']);
						// fall-through
					case 'save':
					case 'apply':
						$content['cats'] += Api\Etemplate::$contentUnvalidated['cats'];
						$type = empty($content['course_id']) ? 'add' : 'edit';
						$content['course_options'] = 0;
					$content['notify_participants'] = $content['participants']['notify_participants'];
						foreach(self::$options as $name => $mask)
						{
							if ($content[$name]) $content['course_options'] |= $mask;
						}
						// Any of these counts as clm enabled
						foreach(['process', 'post', 'dual'] as $clm_type)
						{
							if($content['clm'][$clm_type]['active'])
							{
								$content['course_options'] |= self::$options['cognitive_load_measurement'];
								break;
							}
						}
						$content = array_merge($content, $this->bo->save($content));
					// Update course timestamp (prevents redirect to previous course)
					$this->bo->setLastVideo(['course_id' => $content['course_id']]);
						// fall-through
					case 'cancel':
						// check if called by LTI course-selection
						if (!empty($content['callback']))
						{
							if (in_array($button, ['cancel', 'save']))
							{
								return (new LTI\Ui())->contentSelection(
									array_intersect_key($content, array_flip(['course_id', 'callback', 'params'])));
							}
						}
						else
						{
							Api\Framework::refresh_opener(lang('Course saved.'),
								Bo::APPNAME, $content['course_id'], $type);
							if(in_array($button, ['save', 'cancel']))
							{
								Api\Framework::redirect_link('/index.php',
									 [
										 'menuaction' => 'smallpart.EGroupware\\SmallParT\\Student\\Ui.start',
										 'ajax'       => true
									 ],
									 'smallpart'
								);
							}
						}
						Api\Framework::message(lang('Course saved.'));
						break;

					case 'reopen':
						$this->bo->close($content, false);
						$content['course_closed'] = '0';
						Api\Framework::refresh_opener(lang('Course reopend.'),
							Bo::APPNAME, $content['course_id'], 'edit');
						break;

					case 'close':
						$this->bo->close([$content]);
						Api\Framework::refresh_opener(lang('Course locked.'),
							Bo::APPNAME, $content['course_id'], 'edit');
						Api\Framework::window_close();    // does NOT return
						break;
				}
				unset($content['button'], $content['videos']['upload'], $content['video_url']);
			}
		}
		catch (\Exception $ex) {
			_egw_log_exception($ex);
			Api\Framework::message($ex->getMessage(), 'error');
		}

		// Put special categories first
		$specials = array_filter($content['cats'], function ($item)
		{
			return $item['type'] === 'sc';
		});
		$normals = array_filter($content['cats'], function ($item)
		{
			return is_array($item) && $item['type'] !== 'sc';
		});
		$content['cats'] = array_merge([false], $specials, $normals);
		// Update course timestamp (prevents redirect to previous course)
		if($content['course_id'])
		{
			$this->bo->setLastVideo(['course_id' => $content['course_id']]);
		}

		// Unpack bitmap for UI
		foreach($content['videos'] as &$video)
		{
			if(!is_array($video) || is_array($video['video_test_options']))
			{
				continue;
			}
			$test_options = (int)$video['video_test_options'] ?? 0;
			$video['video_readonly_after_test'] = (bool)($test_options & Bo::TEST_OPTION_VIDEO_READONLY_AFTER_TEST);
			$video['video_teacher_comments_are_free'] = (bool)($test_options & Bo::TEST_OPTION_TEACHER_FREE_COMMENT);
			$video['video_hide_teacher_comment_text'] = (bool)($test_options & Bo::TEST_OPTION_HIDE_TEACHER_COMMENT_TEXT);
			$video['video_test_options'] = [];
			foreach([Bo::TEST_OPTION_FORBID_SEEK, Bo::TEST_OPTION_ALLOW_PAUSE, Bo::TEST_OPTION_FREE_COMMENT_ONLY] as $mask)
			{
				if(($test_options & $mask) === $mask)
				{
					$video['video_test_options'][] = $mask;
				}
			}
			$video['direct_link'] = Api\Framework::getUrl(Api\Egw::link('/index.php', [
				'menuaction' => Bo::APPNAME.'.'.Ui::class.'.index',
				'video_id' => $video['video_id'],
				'ajax' => 'true',
			]));
			$video += $this->bo->readVideoAttachments($video);
		}
		$content['direct_link'] = Api\Framework::getUrl(Api\Egw::link('/index.php', [
			'menuaction' => Bo::APPNAME.'.'.Ui::class.'.start',
			'course_id' => $content['course_id'],
			'ajax' => 'true',
		]));
		$content['course_preferences'] = [];
		$prefs = new Api\Preferences();
		$prefs->read();
		$course_prefix = 'course_' . $content['course_id'] . '_';
		foreach($prefs->default_prefs('smallpart') as $k => $v)
		{
			if(str_starts_with($k, $course_prefix))
			{
				$content['course_preferences'][str_replace($course_prefix, '', $k)] = $v;
			}
		}

		$sel_options = [
			'video_options' => [
				Bo::COMMENTS_SHOW_ALL => lang('Show all comments'),
				Bo::COMMENTS_GROUP => lang('Show comments from own group incl. teachers'),
				Bo::COMMENTS_HIDE_OTHER_STUDENTS => lang('Hide comments from other students'),
				Bo::COMMENTS_HIDE_TEACHERS => lang('Hide teacher comments'),
				Bo::COMMENTS_GROUP_HIDE_TEACHERS => lang('Show comments from own group hiding teachers'),
				Bo::COMMENTS_SHOW_OWN => lang('Show students only their own comments'),
				Bo::COMMENTS_FORBIDDEN_BY_STUDENTS => lang('Forbid students to comment'),
				Bo::COMMENTS_DISABLED => lang('Disable comments, eg. for tests'),
			],
			'video_published' => Bo::videoStatusLabels(),
			'video_test_display' => [
				Bo::TEST_DISPLAY_COMMENTS => lang('instead of comments'),
				Bo::TEST_DISPLAY_DIALOG => lang('as dialog'),
				Bo::TEST_DISPLAY_VIDEO => lang('as video overlay'),
				Bo::TEST_DISPLAY_LIST => lANG('as permanent list of all questions'),
			],
			'video_published_prerequisite' => array_map(function ($v)
			{
				return [
					'value' => $v['video_id'],
					'label' => $v['video_name']
				];
			}, array_filter($content['videos'], function ($v)
			{
				return $v && $v['video_test_duration'] > 0;
			})),
			'video_test_options' => [
				Bo::TEST_OPTION_ALLOW_PAUSE => lang('allow pause'),
				Bo::TEST_OPTION_FORBID_SEEK => lang('forbid seek'),
				Bo::TEST_OPTION_FREE_COMMENT_ONLY => lang('free comment only'),
				// displayed as separate checkbox currently
				//Bo::TEST_OPTION_VIDEO_READONLY_AFTER_TEST => lang('Allow readonly access after finished test incl. comments of teacher'),
			],
			'participant_role' => [
				Bo::ROLE_STUDENT => lang('Student'),
				Bo::ROLE_TUTOR => [
					'label' => lang('Tutor'),
					'title' => lang('Readonly access to teacher interface'),
				],
				Bo::ROLE_TEACHER => [
					'label' => lang('Teacher'),
					'title' => lang('Full teacher interface, but not locking courses'),
				],
				Bo::ROLE_ADMIN => [
					'label' => lang('Course-admin'),
					'title' => lang('Everything like the course owner'),
				],
			],
			'export_columns' => array_map(function ($label, $value)
			{
				return ['value' => $value, 'label' => $label];
			}, array_keys(Export::$export_comment_cols), array_values(Export::$export_comment_cols))
		];
		for ($n=2; $n <= 10; $n++)
		{
			$sel_options['course_groups'][lang('fixed number of groups')][$n] = lang('%1 groups', $n);
		}
		for($n=2; $n <= 20; $n++)
		{
			$sel_options['course_groups'][lang('fixed group-size')]['-'.$n] = lang('%1 students', $n);
		}
		$sel_options['video_id'][] = ['value' => '-no_video-', 'label' => 'No videos', 'icon' => 'ban'];
		foreach($content['videos'] as $v)
		{
			if (is_array($v) && $v['video_id']) $sel_options['video_id'][] = [
                'value' => $v['video_id'],
                'label' => $v['video_name']
                ];
		}
		$sel_options['copy_custom_videos'] = array_map(function ($o) use ($sel_options)
		{
			return [
				'value'    => $o,
				'label'    => $sel_options['video_id'][$o],
				'checkbox' => ""
			];
		}, array_keys((array)$sel_options['video_id']));
		$content['videos']['hide'] = !$content['videos'] || !array_filter($content['videos'], static function ($data, $key)
		{
			return is_int($key) && $data;
		}, ARRAY_FILTER_USE_BOTH);

		$content['edit_course_name'] = $content['edit_course_name'] || !$content['course_name'];
		$readonlys = [
			'button[close]' => empty($content['course_id']) || $content['course_closed'] || !empty($content['callback']),
			'button[reopen]' => !Bo::isSuperAdmin() || empty($content['course_id']) || empty($content['course_closed']) || !empty($content['callback']),
		];
		// allow only at least tutors to see and teachers to edit course dialog
		if (!(empty($content['course_id']) ? Bo::checkTeacher() : $this->bo->isTutor($content)))
		{
			Api\Framework::window_close(lang('Permission denied'));
		}
		// make everything readonly for tutors
		if (!empty($content['course_id']) && !$this->bo->isTeacher($content))
		{
			$readonlys['__ALL__'] = true;
			$readonlys['button[cancel]'] = false;
		}
		// make participant-role readonly for teachers and don't allow setting or changing admin role
		elseif (!(empty($content['course_id']) ? Bo::checkTeacher() : $this->bo->isAdmin($content)))
		{
			foreach($content['participants'] as $n => $participant)
			{
				if (is_array($participant) && $participant['participant_role'] == Bo::ROLE_ADMIN)
				{
					$readonlys['participants'][$n]['participant_role'] = true;
					$readonlys['participants']['unsubscribe['.$participant['account_id'].']'] = true;
				}
			}
			unset($sel_options['participant_role'][Bo::ROLE_ADMIN]);
		}
		// always show owner and EGw admins with role admin and disable setting something else
		if (!empty($content['course_id']))
		{
			$sel_options['video_limit_access'] = [];
			foreach($content['participants'] as $n => &$participant)
			{
				if (!is_array($participant) || !is_int($n)) continue;
				if ($participant && ($participant['account_id'] == $content['course_owner'] ||
					Bo::isSuperAdmin($participant['account_id'])))
				{
					$content['participants'][$n]['participant_role'] = Bo::ROLE_ADMIN;
					$readonlys['participants'][$n]['participant_role'] = true;
					$readonlys['participants']['unsubscribe['.$participant['account_id'].']'] = !Bo::isSuperAdmin();
				}
				$participant['class'] = empty($participant['participant_unsubscribed']) ? 'isSubscibed' : 'isUnsubscribed';
				if (!empty($participant['participant_unsubscribed']))
				{
					$readonlys['participants'][$n]['participant_role'] = true;
					$readonlys['participants'][$n]['participant_group'] = true;
					$readonlys['participants']['unsubscribe['.$participant['account_id'].']'] = true;
				}
				$sel_options['video_limit_access'][$participant['account_id']] = Bo::participantName($participant, true);
			}
		}

		// change [Cancel] button for LTI content-selection to not close the window, but return
		if (!empty($content['callback']))
		{
			Api\Etemplate::setElementAttribute('button[cancel]', 'onclick', null);
		}

		// set data only for none update operations since regular submits can reset the data in preserved
		if (empty($content['button']) && $content['cats'])
		{
			foreach($content['cats'] as &$cat)
			{
				if (isset($cat['data']) && !isset($cat['cat_id']))
				{
					$cat = $cat + (array)json_decode($cat['data'], true);
				}
				elseif($cat && !isset($cat['data']))
				{
					$cat['data'] = json_encode($cat);
				}
			}
		}

		$tmpl = new Api\Etemplate(Bo::APPNAME.'.course');
		$tmpl->exec(Bo::APPNAME.'.'.self::class.'.edit', $content, $sel_options, $readonlys, ['clm'=>[], 'cats' => []]+$content+[
			'old_groups' => $content['course_groups']
		]);
	}

	/**
	 * Remove elements of an array of array by the value of a given attribute
	 *
	 * @param array $array
	 * @param string $name
	 * @param mixed $value
	 * @return array remaining (renumbered) elements
	 */
	protected static function removeByAttributeValue(array $array, $name, $value)
	{
		foreach($array as $key => $attrs)
		{
			if (is_array($attrs) && $attrs[$name] == $value)
			{
				unset($array[$key]);
			}
		}
		return array_values($array);
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
		$total = $this->bo->get_rows($query, $rows, $readonlys);

		foreach ($rows as $key => &$row)
		{
			if (!is_int($key)) continue;

			// mark course as subscribed or available
			$row['class'] = $row['subscribed'] ? 'spSubscribed' : 'spAvailable';
			if ($this->bo->isTutor($row)) $row['class'] .= ' spEditable';
			if (!$row['subscribed']) $row['subscribed'] = '';    // for checkbox to understand
			if ($this->bo->isAdmin($row))
			{
				$row['class'] .= $row['course_closed'] ? ' spLocked' : ' spLockable';
			}
			// do NOT send password to client-side
			unset($row['course_password']);
		}
		return $total;
	}

	/**
	 * Index
	 *
	 * @param array $content =null
	 */
	public function index(array $content=null)
	{
		// allow framing by LMS (LTI 1.3 without specifying a course_id shows Courses::index which redirects here for open
		if (($lms = Api\Cache::getSession('smallpart', 'lms_origin')))
		{
			ContentSecurityPolicy::add('frame-ancestors', $lms);
		}
		if (!is_array($content) || empty($content['nm']))
		{
			$content = [
				'nm' => [
					'get_rows'       =>	Bo::APPNAME.'.'.self::class.'.get_rows',
					'no_filter2'     => true,	// disable the diverse filters we not (yet) use
					'no_cat'         => true,
					'order'          =>	'course_id',// IO name of the column to sort after (optional for the sortheaders)
					'sort'           =>	'DESC',// IO direction of the sort: 'ASC' or 'DESC'
					'row_id'         => 'course_id',
					'default_cols'   => '!acts',// disable actions column by default
					'actions'        => $this->get_actions(),
					'placeholder_actions' => array('add'),
					'start'          =>	0,			// IO position in list
					'col_filter'     =>	[],
					'filter'         => '',
				],
				'lti' => (bool)$lms,
			];
		}
		elseif(!empty($content['nm']['action']))
		{
			try {
				$msg = Api\Framework::message($this->action($content['nm']['action'],
					$content['nm']['selected'], $content['nm']['select_all']));
				if (!empty($msg))
				{
					Api\Framework::message($msg);
				}
			}
			catch (\Exception $ex) {
				Api\Framework::message($ex->getMessage(), 'error');
			}
		}
		$readonlys = [
			'add' => !Bo::checkTeacher(),	// only teachers are allowed to create courses
		];
		$sel_options = [
			'filter' => [
				'' => lang('All courses'),
				'subscribed' => lang('My courses'),
				'available' => lang('Available courses'),
				'closed' => lang('Locked courses'),
			],
		];
		if (!Bo::checkTeacher())
		{
			unset($sel_options['filter']['closed']);	// only show closed filter to teachers
		}
		$tmpl = new Api\Etemplate(Bo::APPNAME.'.courses');
		$tmpl->exec(Bo::APPNAME.'.'.self::class.'.index', $content, $sel_options, $readonlys, ['nm' => $content['nm']]);
	}

	/**
	 * Return actions for cup list
	 *
	 * @param array $cont values for keys license_(nation|year|cat)
	 * @return array
	 */
	protected function get_actions()
	{
		$actions = [
			'open' => [
				'caption' => 'Open',
				'default' => true,
				'allowOnMultiple' => false,
				'group' => $group=0,
				'icon' => 'view',
				'url' => Api\Link::get_registry(Bo::APPNAME, 'view', true),
			],
			'edit' => [
				'caption' => 'Edit',
				'allowOnMultiple' => false,
				'url' => Api\Link::get_registry(Bo::APPNAME, 'edit', '$id'),
				'popup' => Api\Link::get_registry(Bo::APPNAME, 'edit_popup'),
				'group' => ++$group,
				'enableClass' => 'spEditable',
				'x-teacher' => true,
			],
			'questions' => [
				'caption' => 'Questions',
				'allowOnMultiple' => false,
				'url' => Api\Link::get_registry(Overlay::SUBTYPE, 'list', ['course_id' => '$id']),
				'group' => $group,
				'enableClass' => 'spEditable',
				'icon' => 'edit',
				'x-teacher' => true,
			],
			'add' => [
				'caption' => 'Add',
				'url' => Api\Link::get_registry(Bo::APPNAME, 'add', true),
				'popup' => Api\Link::get_registry(Bo::APPNAME, 'add_popup'),
				'group' => $group,
				'x-teacher' => true,
			],
			'copy_course'          => [
				'caption'         => 'Copy Course',
				'allowOnMultiple' => false,
				'group'           => $group,
				'x-teacher'       => true,
				'icon'            => 'copy'
			],
			'copy_no_participants' => [
				'caption'         => 'Copy Course without participants',
				'allowOnMultiple' => false,
				'group'           => $group,
				'x-teacher'       => true,
				'icon'            => 'person-slash'
			],
			'documents' => Merge::document_action(
				$GLOBALS['egw_info']['user']['preferences']['smallpart']['document_dir'] ?? '/templates/smallpart',
				$group, 'Insert in document', 'document_',
				$GLOBALS['egw_info']['user']['preferences']['smallpart']['default_document'] ?? null
			),
			'unsubscribe' => [
				'caption' => 'Unsubscribe',
				'allowOnMultiple' => true,
				'group' => $group=5,
				'enableClass' => 'spSubscribed',
				'icon' => 'cancel',
				'confirm' => 'Do you want to unsubscribe from these courses?',
				'onExecute' => 'javaScript:app.smallpart.courseAction',
			],
			'reopen' => [
				'caption' => 'Reopen',
				'allowOnMultiple' => true,
				'group' => $group,
				'enableClass' => 'spLocked',
				'hideOnDisabled' => true,
				'icon' => 'logout',
				'onExecute' => 'javaScript:app.smallpart.courseAction',
				'x-teacher' => true,
			],
			'close' => [
				'caption' => 'Lock',
				'allowOnMultiple' => true,
				'group' => $group,
				'enableClass' => 'spLockable',
				'hideOnDisabled' => true,
				'icon' => 'logout',
				'confirm' => 'Do you want to closes the course permanent, disallowing students to enter it?',
				'onExecute' => 'javaScript:app.smallpart.courseAction',
				'x-teacher' => true,
			],
			'delete' => [
				'caption'         => 'Delete',
				'allowOnMultiple' => true,
				'group'           => $group,
				'enableClass' => 'spLocked',
				'hideOnDisabled'  => true,
				'icon'            => 'delete',
				'confirm'         => 'Do you want to permanently remove the course?',
				'onExecute'       => 'javaScript:app.smallpart.courseAction',
				'x-teacher'       => true,
			]
		];

		// for students: filter out teacher-actions
		if (!Bo::checkTeacher())
		{
			return array_filter($actions, function($action)
			{
				return empty($action['x-teacher']);
			});
		}
		// allow only EGw admins to reopen courses
		if (!Bo::isSuperAdmin())
		{
			unset($actions['reopen'], $actions['delete']);
		}
		return $actions;
	}

	/**
	 * Execute action on course-list
	 *
	 * @param string $action action-name eg. "subscribe"
	 * @param array|int $selected one or multiple course_id's depending on action
	 * @param boolean $select_all all courses flag
	 * @param string $password =null Course access code to subscribe to courses with an access code
	 * @return string with success message
	 * @throws Api\Db\Exception
	 * @throws Api\Exception\AssertionFailed
	 * @throws Api\Exception\NoPermission
	 * @throws Api\Exception\WrongParameter
	 * @throws Api\Exception\WrongUserinput
	 */
	protected function action($action, $selected, $select_all, $password=null)
	{
		switch($action)
		{
			case 'copy_course':
			case 'copy_no_participants':
				$course = $this->bo->copyCourse($selected[0], $action == 'copy_course' ? null : []);
				Api\Framework::redirect_link("/index.php", Api\Link::get_registry(Bo::APPNAME, 'edit', $course['course_id']));
				exit;

			case 'unsubscribe':
				$this->bo->subscribe($selected, false);
				return lang('You have been unsubscribed from the course.');

			case 'subscribe':
				$this->bo->subscribe($selected[0], true, null, $password);
				return lang('You are now subscribed to the course.');

			case 'reopen':
				$this->bo->close($selected, false);
				return lang('Course reopened.');

			case 'close':
				$this->bo->close($selected);
				return lang('Course closed.');
			case 'delete':
				$this->bo->deleteCourse($selected);
				return lang('Course deleted.');
			case 'open':	// switch to student UI of selected course in ajax request to work with LTI
				$ui = new Ui();
				$ui->index(['courses' => $selected[0]]);
				exit;

			default:
				throw new Api\Exception\AssertionFailed("Unknown action '$action'!");
		}
	}

	/**
	 * Execute action on course-list via AJAX request
	 *
	 * @param string $action action-name eg. "subscribe"
	 * @param array|int $selected one or multiple course_id's depending on action
	 * @param boolean $select_all all courses flag
	 * @param string $password =null Course access code to subscribe to courses with an access code
	 * @throws Api\Json\Exception
	 */
	public function ajax_action($action, $selected, $select_all, $password=null)
	{
		$response = Api\Json\Response::get();
		try {
			$msg = $this->action($action, $selected, $select_all, $password);
			$response->call('egw.refresh', $msg, 'smallpart', count($selected) > 1 ? null : $selected[1], 'update');
		}
		catch (\Exception $e) {
			$response->message($e->getMessage(), 'error');
		}
	}
}