<?php
/**
 * EGroupware - SmallParT - manage questions
 *
 * @link https://www.egroupware.org
 * @package smallpart
 * @subpackage questions
 * @license https://spdx.org/licenses/AGPL-3.0-or-later.html GNU Affero General Public License v3.0 or later
 */

namespace EGroupware\SmallParT;

use EGroupware\Api;
use EGroupware\SmallParT\Student\Ui;

/**
 * SmallParT - manage questions
 *
 * @todo htmlarea menubar|statusbar=false does NOT work
 */
class Questions
{
	/**
	 * Methods callable via menuaction GET parameter
	 *
	 * @var array
	 */
	public $public_functions = [
		'index' => true,
		'edit'  => true,
	];

	/**
	 * Instance of our business object
	 *
	 * @var Bo
	 */
	protected $bo;

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
	 * @param array $content =null
	 */
	public function edit(array $content=null)
	{
		try {
			if (!is_array($content))
			{
				if (!empty($_GET['overlay_id']) && ($data = Overlay::read(['overlay_id' => $_GET['overlay_id']])) && $data['total'])
				{
					$content = $data['elements'][0];
				}
				elseif (!empty($_GET['video_id']) && ($video = $this->bo->readVideo($_GET['video_id'])))
				{
					$content = [
						'course_id' => $video['course_id'],
						'video_id'  => $video['video_id'],
						'answers'   => [],
					];
				}
				else
				{
					Api\Framework::window_close(lang('Entry not found!'));
				}
			}
			else
			{
				switch ($button = key($content['button']))
				{
					case 'save':
					case 'apply':
						$type = empty($content['overlay_id']) ? 'add' : 'edit';
						Overlay::aclCheck($content['course_id']);
						unset($content['button']);
						$content['answers'] = array_values(array_filter($content['answers'], function($answer)
						{
							return !empty($answer['answer']);
						}));
						$content['overlay_id'] = Overlay::write($content);
						Api\Framework::refresh_opener(lang('Question saved.'),
							Bo::APPNAME, $content['overlay_id'], $type);
						if ($button === 'save') Api\Framework::window_close();    // does NOT return
						Api\Framework::message(lang('Question saved.'));
						break;

					case 'delete':
						/*$this->bo->close($content);
						Api\Framework::refresh_opener(lang('Course locked.'),
							Bo::APPNAME, $content['course_id'], 'edit');*/
						Api\Framework::window_close();    // does NOT return
						break;
				}
				unset($content['button']);
			}
		}
		catch (\Exception $ex) {
			Api\Framework::message($ex->getMessage(), 'error');
		}
		$readonlys = [
			'button[delete]' => empty($content['overlay_id']),
		];
		// show only a view, if user is not course-admin/-owner or a super admin
		if (!empty($content['course_id']) && !$this->bo->isAdmin($content))
		{
			$readonlys['__ALL__'] = true;
			$readonlys['button[cancel]'] = false;
		}
		$sel_options = [
			'overlay_type' => Overlay::types(),
		];
		array_unshift($content['answers'], false);
		$content['answers'][] = ['answer' => ''];

		$tmpl = new Api\Etemplate(Bo::APPNAME.'.question');
		$tmpl->exec(Bo::APPNAME.'.'.self::class.'.edit', $content, $sel_options, $readonlys, $content, 2);
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
		if (!($query['col_filter']['video_id'] = $query['filter']))
		{
			$rows = [];
			return 0;
		}
		return Overlay::get_rows($query, $rows, $readonlys);
	}

	/**
	 * Index
	 *
	 * @param array $content =null
	 */
	public function index(array $content=null)
	{
		if (!is_array($content) || empty($content['nm']))
		{
			if (!empty($_GET['video_id']) && ($video = $this->bo->readVideo($_GET['video_id'])))
			{

			}
			elseif (empty($_GET['course_id']) || !($course = $this->bo->read(['course_id' => $_GET['course_id']])))
			{
				Api\Framework::message(lang('Unknown or missing course_id!'), 'error');
				Api\Framework::redirect_link('/index.php', 'menuaction='.$GLOBALS['egw_info']['apps'][Bo::APPNAME]['index']);
			}
			$content = [
				'nm' => [
					'get_rows'       =>	Bo::APPNAME.'.'.self::class.'.get_rows',
					'no_filter2'     => true,	// disable the diverse filters we not (yet) use
					'no_cat'         => true,
					'order'          =>	'overlay_start',// IO name of the column to sort after (optional for the sortheaders)
					'sort'           =>	'ASC',// IO direction of the sort: 'ASC' or 'DESC'
					'row_id'         => 'overlay_id',
					'col_filter'     => ['course_id' => $course['course_id'] ?? $video['course_id']],
					'filter'         => $video['video_id'] ?? '',
					'default_cols'   => '!overlay_id',
					'actions'        => $this->get_actions(),
					'placeholder_actions' => array('add'),
				]
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
			'add' => !$this->bo->isAdmin(),	// only "Admins" are allowed to create courses
		];
		$sel_options = [
			'filter' => [
				'' => lang('Please select a video'),
			]+$this->bo->listVideos(['course_id' => $content['nm']['col_filter']['course_id']], true),
			'overlay_type' => Overlay::types(),
		];
		if (count($sel_options['filter']) === 1) $content['nm']['filter'] = key($sel_options['filter']);

		if ($this->bo->isAdmin())
		{
			unset($sel_options['filter']['deleted']);	// do not show deleted filter to students
		}
		$tmpl = new Api\Etemplate(Bo::APPNAME.'.questions');
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
			'edit' => [
				'caption' => 'Edit',
				'default' => true,
				'allowOnMultiple' => false,
				'url' => 'menuaction='.Bo::APPNAME.'.'.self::class.'.edit&overlay_id=$id',
				'popup' => '800x600',
				'group' => ++$group,
			],
			'add' => [
				'caption' => 'Add',
				'url' => 'menuaction='.Bo::APPNAME.'.'.self::class.'.edit',
				'popup' => '800x600',
				'group' => $group,
			],
			'delete' => [
				'caption' => 'Delete',
				'allowOnMultiple' => true,
				'group' => ++$group,
				'confirm' => 'Delete this question incl. possible answers from students?',
			],
		];

		/* for students: filter out teacher-actions
		if (!$this->bo->isAdmin())
		{
			return array_filter($actions, function($action)
			{
				return empty($action['x-teacher']);
			});
		}*/
		return $actions;
	}

	/**
	 * Execute action on course-list
	 *
	 * @param string $action action-name eg. "subscribe"
	 * @param array|int $selected one or multiple course_id's depending on action
	 * @param boolean $select_all all courses flag
	 * @param string $password =null password to subscribe to password protected courses
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
	 * @param string $password =null password to subscribe to password protected courses
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