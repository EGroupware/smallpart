<?php

namespace EGroupware\SmallParT;

use EGroupware\Api\Egw;
use EGroupware\Api\Etemplate;
use EGroupware\Api\Framework;

class Materials
{
	/**
	 * Methods callable via menuaction GET parameter
	 *
	 * @var array
	 */
	public $public_functions = [
		'list' => true,
		'edit' => true,
	];

	public function list(?array $content = null)
	{

		$tpl = new Etemplate('smallpart.material.list');
		$bo = new Bo();
		$sel_options = $readonlys = $preserve = array();

		$course_id = $content['course_id'] ?? (int)$_GET['course_id'];
		if(is_array($content))
		{
			if(!empty($content['videos']['upload']) || !empty($content['add_video_link']) || !empty($content['add_lf_video']))
			{
				$upload = $content['videos']['upload'] ?: $content['video_url'];
				unset($content['videos']['upload'], $content['videos']['video'], $content['video_url'], $content['add_video_link']);
				// Add livefeedback dummy video
				if($content['add_lf_video'])
				{
					$upload = [
						'name'     => 'livefeedback.webm',
						'type'     => 'video/webm',
						'tmp_name' => $upload
					];
				}
				$newVideo = $bo->addVideo($content['course_id'], $upload);
				if($newVideo && $content['add_lf_video'])
				{
					$newVideo['video_name'] = $newVideo['video_id'] . '_livefeedback_' . (new Api\DateTime('now'))->format('Y-m-d H:i') . '.webm';
					$bo->saveVideo($newVideo);
					$bo->addLivefeedback($content['course_id'], $newVideo);
					unset($content['add_lf_video']);
				}
				Framework::message(lang('Video successful uploaded.'));
			}
			elseif(!empty($content['videos']['delete']))
			{
				$bo->deleteVideo(['video_id'  => key($content['videos']['delete']),
								  'course_id' => $content['course_id']]);
				Framework::message(lang('Video deleted.'));
			}
			elseif(!empty($content['videos']['download']))
			{
				$export = new Export($bo);
				$export->downloadComments($content, key($content['videos']['download']));    // won't return unless an error
			}
		}
		$content = $bo->read(['course_id' => $course_id]) ?? [];
		$content['video_count'] = 0;
		$this->filter_material($content, $readonlys, $bo);
		foreach($content['videos'] as &$video)
		{
			$video['direct_link'] = Framework::getUrl(Egw::link('/index.php', [
				'menuaction' => Bo::APPNAME . '.' . Student\Ui::class . '.index',
				'video_id'   => $video['video_id'],
				'ajax'       => 'true',
			]));
		}

		$preserve['course_id'] = $course_id;
		$can_add = $bo->canUpload($content);
		$readonlys['material_link'] = $readonlys['material_upload'] = $readonlys['add_lf_video'] = !$can_add;
		$content['video_count'] = '' . $content['video_count'];

		// Hide video count for teachers
		if($bo->isStaff($course_id))
		{
			$content['student_uploads'] = '';
		}
		if(count($content['videos']) > 0)
		{
			$content['videos'] = array_values($content['videos']);
		}

		$tpl->exec(Bo::APPNAME . '.' . self::class . '.list', $content, $sel_options, $readonlys, $preserve);
	}

	protected function filter_material(array &$content, array &$readonlys, &$bo)
	{
		// If staff get here, they can see all
		if($bo->isStaff($content))
		{
			return;
		}

		foreach($content['videos'] as $id => $video)
		{
			if($video['owner'] == $GLOBALS['egw_info']['user']['account_id'])
			{
				$content['video_count']++;
			}
			else
			{
				unset($content['videos'][$id]);
			}
		}
	}

	/**
	 * Edit materials (videos) for a given course
	 *
	 * @param array|null $content
	 * @param string $msg
	 */
	function edit(?array $content = null)
	{
		$bo = new Bo();
		if(is_array($content))
		{
			$type = empty($content['video_id']) ? 'add' : 'edit';
			switch($button = key($content['button']))
			{
				case 'delete':
					$bo->deleteVideo($content);
					Framework::refresh_opener(
						lang('video deleted.'),
						Bo::APPNAME, $content['video_id'], 'delete'
					);
					Framework::window_close();
					break;
				case 'apply':
				case 'save':
					$this->save_material($bo, $content);
					Framework::refresh_opener(
						'',
						Bo::APPNAME, $content['video_id'], $type
					);
				// Fall through

				case 'cancel':
					if(in_array($button, ['save', 'cancel']))
					{
						Framework::window_close();
					}
			}
			unset($content['button']);
		}
		$video_id = (int)($_GET['video_id'] ?? $content['video_id']);
		$content = $this->load_material($bo, $video_id);

		$course_id = $content['course_id'];
		$preserve = $content;
		$sel_options = $this->select_options($bo, $content);

		$can_edit = $content['owner'] == $GLOBALS['egw_info']['user']['account_id'] || $bo->isStaff($course_id);
		if(!$can_edit)
		{
			$readonlys['__ALL__'] = true;
		}

		$tpl = new Etemplate('smallpart.material.edit');
		$tpl->exec(Bo::APPNAME . '.' . self::class . '.edit', $content, $sel_options, $readonlys, $preserve, 2);
	}

	protected function select_options(&$bo, array $content)
	{
		$course_id = $content['course_id'];
		$video_id = $content['video_id'];
		$course = $bo->read(['course_id' => $course_id]);

		$sel_options = [
			'video_options'      => [
				Bo::COMMENTS_SHOW_ALL              => lang('Show all comments'),
				Bo::COMMENTS_GROUP                 => lang('Show comments from own group incl. teachers'),
				Bo::COMMENTS_HIDE_OTHER_STUDENTS   => lang('Hide comments from other students'),
				Bo::COMMENTS_HIDE_TEACHERS         => lang('Hide teacher comments'),
				Bo::COMMENTS_GROUP_HIDE_TEACHERS   => lang('Show comments from own group hiding teachers'),
				Bo::COMMENTS_SHOW_OWN              => lang('Show students only their own comments'),
				Bo::COMMENTS_FORBIDDEN_BY_STUDENTS => lang('Forbid students to comment'),
				Bo::COMMENTS_DISABLED              => lang('Disable comments, eg. for tests'),
			],
			'video_published'    => [
				[
					'value' => Bo::VIDEO_DRAFT,
					'label' => lang('Draft'),
					'title' => lang('Only available to course admins'),
				],
				[
					'value' => Bo::VIDEO_PUBLISHED,
					'label' => lang('Published'),
					'title' => lang('Available to participants during optional begin- and end-date and -time'),
				],
				[
					'value' => Bo::VIDEO_PUBLISHED_PREREQUISITE,
					'label' => lang('Prerequisite'),
					'title' => lang('prerequisite completion of the video')
				],
				[
					'value' => Bo::VIDEO_UNAVAILABLE,
					'label' => lang('Unavailable'),
					'title' => lang('Only available to course admins') . ' ' . lang('eg. during scoring of tests'),
				],
				[
					'value' => Bo::VIDEO_READONLY,
					'label' => lang('Readonly'),
					'title' => lang('Available, but no changes allowed eg. to let students view their test scores'),
				],
			],
			'video_test_display' => [
				Bo::TEST_DISPLAY_COMMENTS => lang('instead of comments'),
				Bo::TEST_DISPLAY_DIALOG   => lang('as dialog'),
				Bo::TEST_DISPLAY_VIDEO    => lang('as video overlay'),
				Bo::TEST_DISPLAY_LIST     => lANG('as permanent list of all questions'),
			],
			'video_test_options' => [
				Bo::TEST_OPTION_ALLOW_PAUSE       => lang('allow pause'),
				Bo::TEST_OPTION_FORBID_SEEK       => lang('forbid seek'),
				Bo::TEST_OPTION_FREE_COMMENT_ONLY => lang('free comment only'),
				// displayed as separate checkbox currently
				//Bo::TEST_OPTION_VIDEO_READONLY_AFTER_TEST => lang('Allow readonly access after finished test incl. comments of teacher'),
			],
		];
		foreach($course['videos'] as $v)
		{
			if(is_array($v))
			{
				$sel_options['video_published_prerequisite'][] = [
					'value'    => $v['video_id'],
					'label'    => $v['video_name'],
					'disabled' => $v['video_id'] == $video_id
				];
			}
		}
		$sel_options['video_limit_access'] = [];
		foreach($course['participants'] as $n => &$participant)
		{
			if(!is_array($participant) || !is_int($n))
			{
				continue;
			}
			$sel_options['video_limit_access'][$participant['account_id']] = Bo::participantName($participant, true);
		}
		return $sel_options;
	}

	/**
	 * Load materials for a course
	 *
	 * @param int $course_id
	 * @return array
	 */
	protected function load_material(&$bo, $material_id)
	{
		return $bo->readVideo($material_id);
	}

	/**
	 * Save materials for a course
	 *
	 * @param array $content
	 */
	protected function save_material(&$bo, array $content)
	{
		$materials = $content;

		// Owner check
		if(!$bo->isStaff($materials['course_id']) && $materials['owner'] && $materials['owner'] != $GLOBALS['egw_info']['user']['account_id'])
		{
			return;
		}

		$bo->saveVideo($materials);

	}
}