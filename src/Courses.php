<?php
/**
 * EGroupware - SmallParT - manage courses
 *
 * @link https://www.egroupware.org
 * @package smallpart
 * @subpackage courses
 * @license http://opensource.org/licenses/gpl-license.php GPL - GNU General Public License
 */

namespace EGroupware\SmallParT;

use EGroupware\Api;
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
				if (!empty($_GET['course_id']))
				{
					if (!($content = $this->bo->read(['course_id' => $_GET['course_id']])))
					{
						Api\Framework::window_close(lang('Entry not found!'));
					}
					$content['lti_key'] = 'course_id='.$content['course_id'];
					// workaround as master regard disabled="!@course_secret" with course_secret===NULL to be true ("" works)
					$content['course_secret'] = (string)$content['course_secret'];
				}
				else
				{
					$content = $this->bo->init();
				}
				// prepare for autorepeat
				array_unshift($content['participants'], false);
				$content['videos'] = array_merge([false, false], array_values($content['videos']));
			}
			elseif (!empty($content['participants']['unsubscribe']))
			{
				$this->bo->subscribe($content['course_id'], false, $account_id = key($content['participants']['unsubscribe']));
				Api\Framework::message(lang('%1 unsubscribed.', Api\Accounts::username($account_id)));
				unset($content['participants']['unsubscribe'], $content['videos']['upload']);
				$content['participants'] = self::removeByAttributeValue($content['participants'], 'account_id', $account_id);
			}
			elseif (!empty($content['videos']['upload']) || !empty($content['videos']['video']))
			{
				if (empty($content['course_id']))	// need to save course first
				{
					$content = array_merge($content, $this->bo->save($content));
				}
				$upload = $content['videos']['upload'] ?: $content['videos']['video_url'];
				unset($content['videos']['upload'], $content['videos']['video'], $content['videos']['video_url']);
				$content['videos'] = array_merge([false, false, $this->bo->addVideo($content['course_id'], $upload)], array_slice($content['videos'], 2));
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
						unset($content['videos']['delete'], $content['confirm_delete'], $content['videos']['upload']);
						$content['videos'] = self::removeByAttributeValue($content['videos'], 'video_id', $video['video_id']);
						break;
					}
				}
			}
			else
			{
				switch ($button = key($content['button']))
				{
					case 'generate':
						$content['lti_key'] = 'course_id='.$content['course_id'];
						$content['course_secret'] = Api\Auth::randomstring('32');
						// fall-through
					case 'delete-lti':
						if ($button === 'delete-lti') unset($content['course_secret']);
						// fall-through
					case 'save':
					case 'apply':
						$type = empty($content['course_id']) ? 'add' : 'edit';
						$content = array_merge($content, $this->bo->save($content));
						Api\Framework::refresh_opener(lang('Course saved.'),
							Bo::APPNAME, $content['course_id'], $type);
						if ($button === 'save') Api\Framework::window_close();    // does NOT return
						Api\Framework::message(lang('Course saved.'));
						break;

					case 'close':
						$this->bo->close($content);
						Api\Framework::refresh_opener(lang('Course closed.'),
							Bo::APPNAME, $content['course_id'], 'edit');
						Api\Framework::window_close();    // does NOT return
						break;
				}
				unset($content['button'], $content['videos']['upload']);
			}
		}
		catch (\Exception $ex) {
			Api\Framework::message($ex->getMessage(), 'error');
		}
		$readonlys = [
			'button[close]' => empty($content['course_id']),
		];
		// show only a view, if user is not course-admin/-owner or a super admin
		if (!empty($content['course_id']) && !$this->bo->isAdmin($content))
		{
			$readonlys['__ALL__'] = true;
			$readonlys['button[cancel]'] = false;
		}
		$sel_options = [
			'video_options' => [
				Bo::COMMENTS_SHOW_ALL => lang('Show all comments'),
				Bo::COMMENTS_HIDE_OTHER_STUDENTS => lang('Hide comments from other students'),
				Bo::COMMENTS_HIDE_OWNER => lang('Hide teacher comments'),
				Bo::COMMENTS_SHOW_OWN => lang('Show students only their own comments'),
			],
		];
		$content['videos']['hide'] = !(count($content['videos'])-2);

		$tmpl = new Api\Etemplate(Bo::APPNAME.'.course');
		$tmpl->exec(Bo::APPNAME.'.'.self::class.'.edit', $content, $sel_options, $readonlys, $content, 2);
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
		return $this->bo->get_rows($query, $rows, $readonlys);
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
			$content = [
				'nm' => [
					'get_rows'       =>	Bo::APPNAME.'.'.self::class.'.get_rows',
					'no_filter2'     => true,	// disable the diverse filters we not (yet) use
					'no_cat'         => true,
					'order'          =>	'course_id',// IO name of the column to sort after (optional for the sortheaders)
					'sort'           =>	'DESC',// IO direction of the sort: 'ASC' or 'DESC'
					'row_id'         => 'course_id',
					//'row_modified'   => 'host_modified',
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
				'' => lang('All courses'),
				'subscribed' => lang('My courses'),
				'available' => lang('Available courses'),
				'closed' => lang('Closed courses'),
			],
		];
		if ($this->bo->isAdmin())
		{
			unset($sel_options['filter']['deleted']);	// do not show deleted filter to students
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
				'enableClass' => 'spSubscribed',
				'icon' => 'view',
			],
			'subscribe' => [
				'caption' => 'Subscribe',
				'default' => true,
				'allowOnMultiple' => false,
				'onExecute' => 'javaScript:app.smallpart.subscribe',
				'group' => $group,
				'enableClass' => 'spAvailable',
				'icon' => 'check',
			],
			'edit' => [
				'caption' => 'Edit',
				'allowOnMultiple' => false,
				'url' => 'menuaction='.Bo::APPNAME.'.'.self::class.'.edit&course_id=$id',
				'popup' => '800x600',
				'group' => ++$group,
				'enableClass' => 'spEditable',
				'x-teacher' => true,
			],
			'add' => [
				'caption' => 'Add',
				'url' => 'menuaction='.Bo::APPNAME.'.'.self::class.'.edit',
				'popup' => '800x600',
				'group' => $group,
				'x-teacher' => true,
			],
			'unsubscribe' => [
				'caption' => 'Unsubscribe',
				'allowOnMultiple' => true,
				'group' => $group=5,
				'enableClass' => 'spSubscribed',
				'icon' => 'cancel',
				'confirm' => 'Do you want to unsubscribe from these courses?',
				'onExecute' => 'javaScript:app.smallpart.courseAction',
			],
			'close' => [
				'caption' => 'Close',
				'allowOnMultiple' => true,
				'group' => $group,
				'enableClass' => 'spEditable',
				'icon' => 'logout',
				'confirm' => 'Do you want to close this course?',
				'onExecute' => 'javaScript:app.smallpart.courseAction',
				'x-teacher' => true,
			],
			// ToDo: do we need a delete course action?
		];

		// for students: filter out teacher-actions
		if (!$this->bo->isAdmin())
		{
			return array_filter($actions, function($action)
			{
				return empty($action['x-teacher']);
			});
		}
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
			case 'unsubscribe':
				$this->bo->subscribe($selected, false);
				return lang('You have been unsubscribed from the course.');

			case 'subscribe':
				$this->bo->subscribe($selected[0], true, null, $password);
				return lang('You are now subscribed to the course.');

			case 'close':
				$this->bo->close($selected);
				return lang('Course closed.');

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