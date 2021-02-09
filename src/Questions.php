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
 * @todo allow to configure / limit participant changes it's answer at all or at least after correction of the test
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
				if (!empty($_GET['video_id']) && ($video = $this->bo->readVideo($_GET['video_id'])))
				{
					$course = array_intersect_key($video, array_flip(['course_id','video_id','account_id']));
				}
				else
				{
					$state = Api\Cache::getSession(__CLASS__, 'state');
					$course = array_intersect_key($state['col_filter'], array_flip(['course_id','video_id','account_id']));
				}
				// non-course-admins can NOT choose an account to view
				if (!($admin = $this->bo->isAdmin($course)))
				{
					$course['account_id'] = $GLOBALS['egw_info']['user']['account_id'];
				}

				if (!empty($_GET['overlay_id']) && ($data = Overlay::read($course+['overlay_id' => $_GET['overlay_id']])) && $data['total'])
				{
					$content = $data['elements'][0];
					$content['account_id'] = $course['account_id'];
					// shuffle answers for student
					if (!$admin && $content['shuffle_answers'] && $content['answers'])
					{
						shuffle($content['answers']);
					}
				}
				elseif ($admin)
				{
					$content = $course+[
						'answers'   => [],
						'assessment_method' => 'all_correct',
						'min_score' => 0.0,
						'max_score' => 1.0,
						'shuffle_answers' => true,
					]+['overlay_type' => $_GET['overlay_type'] ?? 'smallpart-question-multiplechoice',
						'overlay_start' => $_GET['overlay_start'] ?? '',
						'overlay_duration' => $_GET['overlay_duration'] ?? 5];
				}
				else
				{
					Api\Framework::window_close(lang('Entry not found!'));
				}

				if (!($content['accessible'] = $this->bo->videoAccessible($content['video_id'])))
				{
					Api\Framework::window_close(lang('Permission denied!'));
				}
			}
			else
			{
				$admin = $content['courseAdmin'];
				$button = key($content['button']);
				unset($content['couseAdmin'], $content['button']);

				// recheck with every submit, as we might have reached the end of test-timeframe or -duration
				if (!($content['accessible'] = $this->bo->videoAccessible($content['video_id'])) ||
					($button && $content['accessible'] === 'readonly'))
				{
					Api\Framework::window_close(lang('Permission denied!'));
				}

				switch ($button)
				{
					case 'save':
					case 'apply':
						$type = empty($content['overlay_id']) ? 'add' : 'edit';
						unset($content['button']);
						$content['answers'] = array_values(array_filter($content['answers'], function($answer)
						{
							return !empty($answer['answer']);
						}));
						if ($content['overlay_id'] && $content['account_id'])
						{
							Overlay::writeAnswer($content);
							$msg = lang('Answer saved.');
						}
						else
						{
							Overlay::aclCheck($content['course_id']);
							self::setMultipleChoiceIds($content['answers']);
							$content['overlay_id'] = Overlay::write($content);
							$msg = lang('Question saved.');
							// set TEST_OPTION_FORBID_SEEK for QUESTION_TIMED, if not already set
							if ($content['overlay_question_mode'] == Bo::QUESTION_TIMED &&
								($video = $this->bo->readVideo($content['video_id'])) &&
								!($video['video_test_options'] & Bo::TEST_OPTION_FORBID_SEEK))
							{
								$video['video_test_options'] |= Bo::TEST_OPTION_FORBID_SEEK;
								$this->bo->saveVideo($video);
							}
						}
						Api\Framework::refresh_opener($msg, Overlay::SUBTYPE, $content['overlay_id'], $type);
						if ($button === 'save') Api\Framework::window_close();    // does NOT return
						Api\Framework::message($msg);
						break;

					case 'delete':
						$msg = $this->action('delete', ['overlay_id' => $content['overlay_id']], false);
						Api\Framework::refresh_opener($msg,
							Overlay::SUBTYPE, $content['overlay_id'], 'delete');
						Api\Framework::window_close();    // does NOT return
						break;
				}
			}
		}
		catch (\Exception $ex) {
			_egw_log_exception($ex);
			Api\Framework::message($ex->getMessage(), 'error');
		}
		$readonlys = [
			'button[delete]' => empty($content['overlay_id']),
		];
		// disable regular editing for non-admins or with a participant selected, or when readonly
		if (!$admin || $content['account_id'] || $content['accessible'] === 'readonly')
		{
			$readonlys['__ALL__'] = true;
			$readonlys['button[save]'] = $readonlys['button[apply]'] = $readonlys['button[delete]'] = $content['accessible'] === 'readonly';
			$readonlys['button[cancel]'] = false;
		}
		// enable ability to answer for regular participant, but not admin
		if ($content['account_id'] && !$admin && $content['accessible'] !== 'readonly')
		{
			$readonlys['answer_data[answer]'] = false;
		}
		// enable admins to correct selected participant
		if ($admin && $content['account_id'] && $content['accessible'] !== 'readonly')
		{
			$readonlys['answer_score'] = $content['overlay_type'] !== 'smallpart-question-text';
			$readonlys['answer_data[remark]'] = false;
		}
		$content['courseAdmin'] = $admin;

		$sel_options = [
			'overlay_type' => Overlay::types(),
			'overlay_question_mode' => [
				Bo::QUESTION_SKIPABLE => lang('Question can be skipped'),
				Bo::QUESTION_REQUIRED => lang('Question is required / must be answered'),
				Bo::QUESTION_TIMED    => lang('Question must be answered in given time / duration'),
			]
		];
		// multiple choice: show at least 5, but allways one more, answer lines
		if ($content['overlay_type'] === 'smallpart-question-multiplechoice')
		{
			if ($admin && !$content['account_id'])
			{
				for($i=count($content['answers']), $n=max(5, count($content['answers'])+1); $i < $n; ++$i)
				{
					$content['answers'][] = ['answer' => ''];
				}
			}
			array_unshift($content['answers'], false);
			// enable checkboxes for participants
			if (!$admin && $content['accessible'] !== 'readonly')
			{
				for($i=1; $i < count($content['answers']); ++$i)
				{
					$readonlys['answers'][$i]['check'] = false;
				}
			}
		}

		// do not send correct answers to client, for students before state "readonly"
		$preserve = $content;
		if (!$admin && $content['accessible'] !== 'readonly')
		{
			unset($content['answer'], $content['answer_score'], $content['answer_data']);
			foreach($content['answers'] as &$answer)
			{
				unset($answer['correct'], $answer['score'], $answer['answer_score']);
			}
		}

		$tmpl = new Api\Etemplate(Bo::APPNAME.'.question');
		// disable message that QUESTION_TIMED sets TEST_OPTION_FORBID_SEEK
		if (($video || ($video = $this->bo->readVideo($content['video_id']))) &&
			($video['video_test_options'] & Bo::TEST_OPTION_FORBID_SEEK))
		{
			$tmpl->setElementAttribute('overlay_question_mode', 'onchange', '');
		}
		$tmpl->exec(Bo::APPNAME.'.'.self::class.'.edit', $content, $sel_options, $readonlys, $preserve, 2);
	}

	/**
	 * Validate answer from dialog and save it
	 *
	 * Sends ajax response with either:
	 * - success message
	 * - error message plus data-response with values for keys "error", "errno", "type" from Exception
	 * - generic "et2_validation_error" response
	 *
	 * @param array $content must contain value for key "overlay_id"
	 */
	public function ajax_answer(array $content)
	{
		$response = Api\Json\Response::get();
		try {
			if (empty($content['overlay_id']) ||
				!($data = Overlay::read([
					'overlay_id' => $content['overlay_id'],
					'account_id' => $GLOBALS['egw_info']['user']['account_id']])) ||
				!($data['total']))
			{
				throw new Api\Exception\NotFound();
			}
			$element = $data['elements'][0];
			if ($this->bo->videoAccessible($element['video_id']) !== true)
			{
				throw new Api\Exception\NoPermission();
			}
			// we need to make sure $content['answers'] and $element['answers'] have equal order, which is not the case for shuffle
			if (is_array($element['answers']))
			{
				$sort_by_id = static function($a, $b) {return strcmp($a['id'], $b['id']);};
				usort($element['answers'], $sort_by_id);
				usort($content['answers'], $sort_by_id);
			}
			$element['account_id'] = $GLOBALS['egw_info']['user']['account_id'];
			$tpl = new Api\Etemplate(str_replace('-', '.', $element['overlay_type']));
			$request = $tpl->exec(Overlay::class.'::writeAnswer', $element, null, null, $element, 5);
			$exec_id = $request->id();
			$request = null;	// force saving of request object
			// process response data from dialog
			$tpl->ajax_process_content($exec_id, $content, false);
			if (!Api\Etemplate::validation_errors())
			{
				$response->message(lang('Answer saved'));
				$data = Overlay::read(array_intersect_key($content, array_flip(['course_id','video_id','account_id','overlay_id'])),
					0, 1, 'overlay_start ASC', false, true);
				$response->data($data['elements'][0]);
			}
			else
			{
				// validation error was send by ajax_process_content as $response->generic('et2_validation_error', $errors)
			}
		}
		catch (\Exception $e) {
			$response->message($e->getMessage(), 'error');
			$response->data([
				'error' => $e->getMessage(),
				'errno' => $e->getCode(),
				'type' => get_class($e),
			]);
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
	public function get_rows($query, array &$rows=null, array &$readonlys=null)
	{
		if ($query['filter'] && !($accessible = $this->bo->videoAccessible($query['filter'])))
		{
			Api\Json\Response::get()->message(lang('This video is currently NOT accessible!'));
			Api\Json\Response::get()->apply('app.smallpart.et2.setValueById', ['nm[filter]', $query['filter'] = '']);
		}
		if (!($query['col_filter']['video_id'] = $query['filter']))
		{
			$rows = [];
			return 0;
		}
		// non-course-admins can NOT choose an account to view
		if (!($is_admin=$this->bo->isAdmin($query['col_filter'])))
		{
			$query['filter2'] = $GLOBALS['egw_info']['user']['account_id'];
		}
		$query['col_filter']['account_id'] = $query['filter2'];

		Api\Cache::setSession(__CLASS__, 'state', $query);
		$total = Overlay::get_rows($query, $rows, $readonlys);

		foreach($rows as $key => &$element)
		{
			if (!is_int($key)) continue;

			$element['question'] = html_entity_decode(strip_tags($element['data']));

			// show student scores and correctness of their answers only in "readonly" state, not before
			if (!$is_admin && $accessible !== 'readonly')
			{
				$element['answers'] = empty($element['answers']) ? $element['answer_data']['answer'] :
					implode("\n", array_map(static function($answer) {
						return ($answer['check'] ? "\u{2713}\t" : "\t").$answer['answer'];
					}, $element['answers']));
				unset($element['answer_score']);
				$rows['sum_score'] = '';
			}
			elseif (!empty($element['answers']))
			{
				$default_score = self::defaultScore($element);
				$element['answers'] = implode("\n", array_map(static function($answer) use ($default_score, $query, $element)
				{
					$score = number_format($answer['score'] ?: $default_score, 2);
					if ($query['col_filter']['account_id'])
					{
						return ($answer['check'] ? ($answer['check'] == $answer['correct'] ? "\u{2713}\t" : "\u{2717}\t") :
							($answer['correct'] || !isset($element['answer_id']) ? "\t" : "\u{25A1}\t")).$answer['answer'].
							(!empty($score) && $element[Overlay::ASSESSMENT_METHOD] === Overlay::ASSESSMENT_SCORE_PER_ANSWER &&
							$answer['answer_score'] ? ' ('.number_format($answer['answer_score'], 2).')' : '');
					}
					return ($answer['correct'] ? "\u{2713}\t" : "\t").$answer['answer'].
						(!empty($score) && $element[Overlay::ASSESSMENT_METHOD] === Overlay::ASSESSMENT_SCORE_PER_ANSWER ? " ($score)" : '');
				}, $element['answers']));
			}
			elseif ($query['col_filter']['account_id'])
			{
				$element['answers'] = $element['answer_data']['answer'];
			}
			else
			{
				$element['answers'] = $element['answer'];
			}
			if ($accessible === 'readonly' || !$is_admin)
			{
				$element['class'] = 'readonly';
			}
		}
		return $total;
	}

	/**
	 * Get the default score for a multiple choice questions
	 *
	 * @param array $element
	 * @return float|null
	 */
	public static function defaultScore(array $element, $precision=2)
	{
		if (!empty($element['answers']))
		{
			$have_explict_scores = array_sum(array_map(function ($answer) {
				return (int)(bool)$answer['score'];
			}, $element['answers']));
			if ($have_explict_scores < count($element['answers']))
			{
				$explict_scores = array_sum(array_map(function ($answer) {
					return $answer['score'];
				}, $element['answers']));
				return round(($element['max_score'] - $explict_scores) / (count($element['answers']) - $have_explict_scores), $precision);
			}
		}
		return null;
	}

	/**
	 * Generate stable IDs for multiple choice answers
	 *
	 * In case a choice get's deleted or the got reordered.
	 *
	 * @param array|null &$answers
	 * @throws \Exception
	 */
	public static function setMultipleChoiceIds(array &$answers=null)
	{
		if (!is_array($answers)) return;

		foreach($answers as &$answer)
		{
			if (!isset($answer['id']))
			{
				for ($try=0, $id=md5($answers['answer'] ?: random_bytes(4)); $try < 5; ++$try)
				{
					foreach ($answers as $test)
					{
						if ($test['id'] === $id)
						{
							$id = md5(random_bytes(4));
							continue 2;
						}
					}
					break;
				}
				$answer['id'] = $id;
			}
		}
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
			if (!empty($_GET['video_id']))
			{
				$video = $this->bo->readVideo($_GET['video_id']);
			}
			if (!($course = $this->bo->read(['course_id' => $video ? $video['course_id'] : $_GET['course_id']])))
			{
				Api\Framework::message(lang('Unknown or missing course_id!'), 'error');
				Api\Framework::redirect_link('/index.php', 'menuaction='.$GLOBALS['egw_info']['apps'][Bo::APPNAME]['index']);
			}
			// while question list and edit can work for participants too, it is currently not wanted
			if (!($admin = $this->bo->isAdmin($course)))
			{
				Api\Framework::redirect_link('/index.php', 'menuaction='.$GLOBALS['egw_info']['apps'][Bo::APPNAME]['index']);
			}
			$content = [
				'nm' => [
					'get_rows'       =>	Bo::APPNAME.'.'.self::class.'.get_rows',
					'no_filter2'     => !$admin,	// disable the diverse filters we not (yet) use
					'no_cat'         => true,
					'order'          =>	'overlay_start',// IO name of the column to sort after (optional for the sortheaders)
					'sort'           =>	'ASC',// IO direction of the sort: 'ASC' or 'DESC'
					'row_id'         => 'overlay_id',
					'dataStorePrefix' => 'smallpart-overlay',
					'col_filter'     => ['course_id' => $course['course_id'] ?? $video['course_id'], 'overlay_type' => 'smallpart-question-%'],
					'filter'         => $video['video_id'] ?? '',
					'default_cols'   => '',
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
			'add' => !$admin,	// only "Admins" are allowed to create courses
		];
		$sel_options = [
			'filter' => [
				'' => lang('Please select a video'),
			]+$this->bo->listVideos(['course_id' => $content['nm']['col_filter']['course_id']], true),
			'overlay_type' => [
				'smallpart-question-%' => lang('Questiontypes'),
			]+Overlay::types(),
		];
		if (count($sel_options['filter']) === 1) $content['nm']['filter'] = key($sel_options['filter']);

		if ($admin)
		{
			$content['nm']['options-filter2'][''] = lang('Select participant');
			foreach($course['participants'] as $participant)
			{
				$content['nm']['options-filter2'][$participant['account_id']] = Api\Accounts::username($participant['account_id']);
			}
		}
		else
		{
			$content['nm']['filter2'] = $GLOBALS['egw_info']['user']['account_id'];
		}
		$tmpl = new Api\Etemplate(Bo::APPNAME.'.questions');
		// set dom_id to "smallpart-overlay" to allow refresh_opener($msg, Overlay::SUBTYPE, $content['overlay_id'], $type)
		$tmpl->set_dom_id(Overlay::SUBTYPE);
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
				'url' => Api\Link::get_registry(Overlay::SUBTYPE, 'edit', '$id'),
				'popup' => Api\Link::get_registry(Overlay::SUBTYPE, 'edit_popup'),
				'group' => ++$group,
				'disableClass' => 'readonly',
				'hideOnDisabled' => true,
			],
			'view' => [
				'caption' => 'View',
				'default' => true,
				'allowOnMultiple' => false,
				'url' => Api\Link::get_registry(Overlay::SUBTYPE, 'edit', '$id'),
				'popup' => Api\Link::get_registry(Overlay::SUBTYPE, 'edit_popup'),
				'group' => $group,
				'enableClass' => 'readonly',
				'hideOnDisabled' => true,
			],
			'add' => [
				'caption' => 'Add',
				'url' => Api\Link::get_registry(Overlay::SUBTYPE, 'edit', true),
				'popup' => Api\Link::get_registry(Overlay::SUBTYPE, 'add_popup'),
				'group' => $group,
				'disableClass' => 'readonly',
			],
			'delete' => [
				'caption' => 'Delete',
				'allowOnMultiple' => true,
				'group' => ++$group,
				'disableClass' => 'readonly',
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
	 * @param string $action action-name eg. "delete"
	 * @param array|int $selected one or multiple everlay_id's depending on action
	 * @param boolean $select_all all courses flag
	 * @return string with success message
	 * @throws Api\Exception\NoPermission
	 */
	protected function action($action, $selected, $select_all)
	{
		switch($action)
		{
			case 'delete':
				$state = (array)Api\Cache::getSession(__CLASS__, 'state');
				[$deleted, $hidden] = Overlay::deleteQuestion([
						'course_id' => (int)$state['col_filter']['course_id'],
						'video_id' => (int)$state['col_filter']['video_id'],
					] + ($select_all ? [] : [
						'overlay_id' => $selected,
					]));
				if ($hidden)
				{
					return lang('%1 questions including participant answers deleted.', $deleted+$hidden);
				}
				return lang('%1 questions deleted.', $deleted);

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