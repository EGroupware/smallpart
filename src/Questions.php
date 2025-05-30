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
		'scores' => true,
		'statistics' => true,
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
	 * Edit a question
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
				if (!($admin = $this->bo->isTutor($course)))
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
					if ($this->bo->videoPublished($video ?? $course['video_id']))
					{
						Api\Framework::window_close(lang('You can NOT add questions to a published test!'));
					}
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
				$button = key((array)$content['button']);
				unset($content['couseAdmin'], $content['button']);

				// recheck with every submit, as we might have reached the end of test-timeframe or -duration
				if (!($content['accessible'] = $this->bo->videoAccessible($content['video_id'])) ||
					($button && $content['accessible'] === 'readonly'))
				{
					Api\Framework::window_close(lang('Permission denied!'));
				}
				if (isset($content['marks']) && is_string($content['marks']))
				{
					$content['marks'] = json_decode($content['marks'] ?: '[]', true);
				}
				switch($content['overlay_type'])
				{
					case 'smallpart-question-rating':
						$content['max_score'] = $content['answers'] ? max(array_map(static function($answer)
						{
							return is_array($answer) ? $answer['score'] : null;
						}, $content['answers'])) : 0;
						break;
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

						if ($content['overlay_type'] === 'smallpart-question-singlechoice' && (!$content['answers'] || !$content['answer']))
						{
							$msg = lang('Please mark one answer as correct.');
							Api\Framework::message($msg, 'error');
							break;
						}
						if ($content['overlay_type'] === 'smallpart-question-multiplechoice'
							&& (!$content['answers'] || !array_filter($content['answers'], function ($answer) {
									return $answer['correct'];
								})))
						{
							$msg = lang('Please mark at least one answer as correct.');
							Api\Framework::message($msg, 'error');
							break;
						}

						if ($content['overlay_id'] && $content['account_id'])
						{
							Overlay::writeAnswer($content);
							$msg = lang('Answer saved.');
						}
						else
						{
							Overlay::aclCheck($content['course_id']);
							if (preg_match('/smallpart-question-(singlechoice|multiplechoice|rating)/', $content['overlay_type']))
							{
								self::setMultipleChoiceIds($content['answers'], $content['answer']);
							}
							$content['overlay_id'] = Overlay::write([
								'video_id' => empty($content['all_videos']) ? $content['video_id'] : 0,
							]+$content);
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
			'add' => !empty($content['account_id']),
		];
		// disable regular editing for non-admins or with a participant selected, or when readonly
		if (!$admin || $content['account_id'] || $content['accessible'] === 'readonly')
		{
			$readonlys['__ALL__'] = $readonlys['button[delete]'] = true;
			$readonlys['button[save]'] = $readonlys['button[apply]'] = $content['accessible'] === 'readonly';
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
		// multiple choice: show at least 5, but always one more, answer lines
		if (preg_match('/^smallpart-question-((single|multiple|mark)choice|millout|rating)$/', $content['overlay_type']))
		{
			if ($content['answers'][0] !== false) array_unshift($content['answers'], false);
			if ($admin && !$content['account_id'])
			{
				for($i=count($content['answers']),
					$n=max(3, count($content['answers'])+(int)!empty($content['add'])); $i < $n; ++$i)
				{
					$content['answers'][] = ['answer' => '', 'id' => $i];
				}
				unset($content['add']);
			}
			// enable answers/checkboxes for participants
			if (!$admin && $content['accessible'] !== 'readonly')
			{
				for($i=1; $i < count($content['answers']); ++$i)
				{
					$readonlys['answers'][$i]['check'] = false;
				}
				$readonlys['answer_data[answer]'] = false;
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

		// disallow adding or deleting questions from a published test
		$readonlys['button[delete]'] = $readonlys['button[delete]'] || $this->bo->videoPublished($video ?? $content['video_id']);

		if (!empty($content['exempt']))
		{
			Api\Framework::message(lang('Question is exempt from scoring, score was: ', $content['exempt']));
			$readonlys['answer_score'] = true;
		}
		$tmpl = new Api\Etemplate(Bo::APPNAME.'.question');
		// disable message that QUESTION_TIMED sets TEST_OPTION_FORBID_SEEK
		if (($video || ($video = $this->bo->readVideo($content['video_id']))) &&
			($video['video_test_options'] & Bo::TEST_OPTION_FORBID_SEEK))
		{
			$tmpl->setElementAttribute('overlay_question_mode', 'onchange', '');
		}
		// use this video src in order to fetch video duration in the clientside
		$content = array_merge($content, [
			'video_src' => $video['video_src'],
			'video_type' => $video['video_type'],
			'marks' => json_encode($content['marks'] ?: []),
		]);

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
			if (empty($content['overlay_id']) || empty($content['video_id']) ||
				!($data = Overlay::read([
					'video_id' => $content['video_id'],
					'overlay_id' => $content['overlay_id'],
					'account_id' => $GLOBALS['egw_info']['user']['account_id']])) ||
				!($data['total']))
			{
				throw new Api\Exception\NotFound();
			}
			$element = $data['elements'][0];
			if (empty($element['video_id']) || $this->bo->videoAccessible($element['video_id']) !== true)
			{
				throw new Api\Exception\NoPermission();
			}
			// for singlechoice the selected answer must be the first one as eT fails to validate further ones :(
			if (in_array($element['overlay_type'], ['smallpart-question-singlechoice', 'smallpart-question-rating']) && !empty($content['answer_data']['answer']))
			{
				foreach($element['answers'] as $key => $answer)
				{
					if ($answer['id'] === $content['answer_data']['answer'])
					{
						unset($element['answers'][$key]);
						array_unshift($element['answers'], $answer);
						break;
					}
				}
			}
			// we need to make sure $content['answers'] and $element['answers'] have equal order, which is not the case for shuffle
			elseif (is_array($element['answers']))
			{
				$sort_by_id = static function($a, $b) {return strcmp($a['id'], $b['id']);};
				usort($element['answers'], $sort_by_id);
				if (!empty($content['answers'])) usort($content['answers'], $sort_by_id);
			}
			// remove existing marks, as merging them keeps marks unset on client
			unset($element['answer_data']['marks']);

			$element['account_id'] = $GLOBALS['egw_info']['user']['account_id'];
			$tpl = new Api\Etemplate(str_replace('-', '.', $element['overlay_type']));
			$request = $tpl->exec(Overlay::class.'::writeAnswer', $element, null, null, $element, 5);
			$exec_id = $request->id();
			$request = null;	// force saving of request object
			// process response data from dialog
			$tpl->ajax_process_content($exec_id, $content, false);
			if (!Api\Etemplate::validation_errors())
			{
				$response->message(lang('Answer saved.'));
				$data = Overlay::read(array_intersect_key($content, array_flip(['course_id','video_id','overlay_id']))+[
					'account_id' => $GLOBALS['egw_info']['user']['account_id'],
					], 0, 1, 'overlay_start ASC', false, true);
				$response->data($data['elements'][0]+['summary' => Overlay::summary($element['video_id'])]);
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
	 * Fetch questions to display
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
		$query['col_filter']['video_id'] = $query['filter'];

		// non-course-admins cannot choose an account to view
		if (!($is_admin=$this->bo->isTutor($query['col_filter'])))
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
					implode("\n", array_map(static function($answer)  use ($element)
					{
						return ($answer['check'] || $answer['id'] === $element['answer'] ? "\u{2713}\t" : "\t").$answer['answer'];
					}, $element['answers']));
				unset($element['answer_score']);
				$rows['sum_score'] = '';
			}
			// for rating-question with remark, show just selected rating plus remark
			elseif ($element['overlay_type'] === 'smallpart-question-rating' && $query['col_filter']['account_id'] &&
				!empty($element['show_remark']) && !empty($element['answer_data']['answer']))
			{
				$element['answers'] = array_filter($element['answers'], static function($answer) use ($element)
				{
					return $answer['id'] === $element['answer_data']['answer'];
				});
				$element['answers'] = "\u{2717}\t".current($element['answers'])['answer']."\n".$element['answer_data']['rating_remark'];
			}
			elseif (!empty($element['answers']))
			{
				$default_score = self::defaultScore($element);
				$element['answers'] = implode("\n", array_map(static function($answer) use ($default_score, $query, $element)
				{
					$score = number_format($answer['score'] ?: $default_score, 2);
					if ($query['col_filter']['account_id'])
					{
						switch ($element['overlay_type'])
						{
							case 'smallpart-question-multiplechoice':
								$checked = $answer['check'];
								$correct = $answer['check'] == $answer['correct'];
								$wrong = !$correct;
								break;
							case 'smallpart-question-singlechoice':
								$checked = $answer['id'] === $element['answer_data']['answer'];
								$correct = $element['answer'] === $element['answer_data']['answer'];
								$wrong = !$correct && $answer['id'] === $element['answer'];
								break;
							case 'smallpart-question-markchoice':
								$element[Overlay::ASSESSMENT_METHOD] = Overlay::ASSESSMENT_SCORE_PER_ANSWER;
								// fall through
							case 'smallpart-question-millout':
								$checked = isset($answer['check']);
								$correct = $answer['check'];
								$wrong = !$correct;
								break;
							case 'smallpart-question-rating':
								$checked = $answer['id'] === $element['answer_data']['answer'];
								$correct = '';
								$wrong = '';
								break;
						}
						return ($checked ? ($correct ? (is_int($correct) ? "$correct\t" : "\u{2713}\t") : "\u{2717}\t") :
							(!$wrong || !isset($element['answer_id']) ? "\t" : "\u{25A1}\t")).$answer['answer'].
							(!empty($answer['answer_scoring']) ? ': '.$answer['answer_scoring'] : '').
							(!empty($score) && $element[Overlay::ASSESSMENT_METHOD] === Overlay::ASSESSMENT_SCORE_PER_ANSWER &&
							$answer['answer_score'] ? ' ('.number_format($answer['answer_score'], 2).')' : '');
					}
					switch ($element['overlay_type'])
					{
						case 'smallpart-question-markchoice':
						case 'smallpart-question-millout':
							unset($element['answer']);  // all answers are correct, not just a single one
							break;
					}
					return ($answer['correct'] || $answer['id'] === $element['answer'] ? "\u{2713}\t" : "\t").$answer['answer'].
						(!empty($score) && $element[Overlay::ASSESSMENT_METHOD] === Overlay::ASSESSMENT_SCORE_PER_ANSWER ? " ($score)" : '');
				}, $element['answers']));
			}
			elseif ($element['overlay_type'] === 'smallpart-question-favorite')
			{
				$element['answers'] = (!empty($element['answer_data']['answer']) ? "\u{2715}\t" : "\t").$element['label'];
			}
			elseif ($query['col_filter']['account_id'])
			{
				$element['answers'] = $element['answer_data']['answer'];
			}
			else
			{
				$element['answers'] = $element['answer'];
			}
			if (!$is_admin || !empty($query['col_filter']['video_id']) &&
				($published ?? ($published = $this->bo->videoPublished($query['col_filter']['video_id']))))
			{
				$element['class'] = 'readonly';
			}
			if (!empty($element['exempt']))
			{
				$element['class'] .= ' exempt';
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
		if (!empty($element['answers']) && !empty($element['max_score']))
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
	 * @param ?string& $correct_answer
	 * @throws \Exception
	 */
	public static function setMultipleChoiceIds(array &$answers=null, &$correct_answer=null)
	{
		if (!is_array($answers)) return;

		foreach($answers as $key => &$answer)
		{
			if (!isset($answer['id']) || is_numeric($answer['id']))
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
				if ((string)$correct_answer === (string)$answer['id']) $correct_answer = $id;
				$answer['id'] = $id;
			}
		}
	}

	/**
	 * List of questions
	 *
	 * @param array $content =null
	 */
	public function index(array $content=null)
	{
		// allow framing by LMS (LTI 1.3 without specifying a course_id shows Courses::index which redirects here for open
		if (($lms = Api\Cache::getSession('smallpart', 'lms_origin')))
		{
			Api\Header\ContentSecurityPolicy::add('frame-ancestors', $lms);
		}
		if (!is_array($content) || empty($content['nm']))
		{
			if (!empty($_GET['video_id']) || ($last = $this->bo->lastVideo()))
			{
				$video = $this->bo->readVideo($_GET['video_id'] ?? $last['video_id']);
			}
			if (!($course = $this->bo->read(['course_id' => $video ? $video['course_id'] : ($_GET['course_id'] ?? $last['course_id'])])) ||
				// while question list and edit can work for participants too, it is currently not wanted
				!($admin = $this->bo->isTutor($course)))
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
					'filter2'        => $_GET['account_id'] ?? '',
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
			'add' => !$admin,	// only "Admins" are allowed to add questions
		];
		$sel_options = [
			'filter' => [
				'' => lang('Select material ...'),
			]+$this->bo->listVideos(['course_id' => $content['nm']['col_filter']['course_id']], true),
			'overlay_type' => [
				'smallpart-question-%' => lang('Questiontypes'),
			]+Overlay::types(),
			'overlay_question_mode' => [
				Bo::QUESTION_SKIPABLE => lang('skip-able'),
				Bo::QUESTION_REQUIRED => lang('required'),
				Bo::QUESTION_TIMED    => lang('timed'),
			]
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
	 * Return actions for questions list
	 *
	 * @return array
	 */
	protected function get_actions()
	{
		return [
			'edit' => [
				'caption' => 'Edit',
				'default' => true,
				'allowOnMultiple' => false,
				'url' => Api\Link::get_registry(Overlay::SUBTYPE, 'edit', '$id'),
				'popup' => Api\Link::get_registry(Overlay::SUBTYPE, 'edit_popup'),
				'group' => $group=1,
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
			'exempt' => [
				'caption' => 'Exempt question from scoring',
				'allowOnMultiple' => true,
				'group' => ++$group,
				'disableClass' => 'exempt',
				'hideOnDisabled' => true,
				'icon' => 'cancelled',
			],
			'readd' => [
				'caption' => 'Readd question to scoring',
				'allowOnMultiple' => true,
				'group' => $group,
				'enableClass' => 'exempt',
				'hideOnDisabled' => true,
				'icon' => 'check',
			],
			'delete' => [
				'caption' => 'Delete',
				'allowOnMultiple' => true,
				'group' => ++$group,
				'disableClass' => 'readonly',
				'confirm' => 'Delete this question incl. possible answers from students?',
			],
		];
	}

	/**
	 * Execute action on course-list
	 *
	 * @param string $action action-name eg. "delete"
	 * @param array|int $selected one or multiple everlay_id's depending on action
	 * @param boolean $select_all all courses flag
	 * @param ?array $filter values for course_id and video_id, default use state from session
	 * @return string with success message
	 * @throws Api\Exception\NoPermission
	 */
	protected function action($action, $selected, $select_all, array $filter=null)
	{
		switch($action)
		{
			case 'delete':
				if (!isset($filter)) $filter = (Api\Cache::getSession(__CLASS__, 'state') ?: ['col_filter' => []])['col_filter'];
				[$deleted, $hidden] = Overlay::deleteQuestion([
						'course_id' => (int)$filter['course_id'],
						'video_id' => (int)$filter['video_id'],
					] + ($select_all ? [] : [
						'overlay_id' => $selected,
					]));
				if ($hidden)
				{
					return lang('%1 questions including participant answers deleted.', $deleted+$hidden);
				}
				return lang('%1 questions deleted.', $deleted);

			case 'exempt':
			case 'readd':
				return lang('%1 questions and answers changed', Overlay::exemptQuestion($selected, $action === 'exempt'));

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
	 * @param ?array $filter values for course_id and video_id, default use state from session
	 * @throws Api\Json\Exception
	 */
	public function ajax_action($action, $selected, $select_all, array $filter=null)
	{
		$response = Api\Json\Response::get();
		try {
			$msg = $this->action($action, $selected, $select_all, $filter);
			$response->call('egw.refresh', $msg, 'smallpart', count($selected) > 1 ? null : $selected[1], 'update');
		}
		catch (\Exception $e) {
			$response->message($e->getMessage(), 'error');
		}
	}

	/**
	 * Display test participants and their scores
	 *
	 * @param array|null $content
	 */
	public function scores(array $content=null)
	{
		// allow framing by LMS (LTI 1.3 without specifying a course_id shows Courses::index which redirects here for open
		if (($lms = Api\Cache::getSession('smallpart', 'lms_origin')))
		{
			Api\Header\ContentSecurityPolicy::add('frame-ancestors', $lms);
		}
		if (!is_array($content) || empty($content['nm']))
		{
			if (!empty($_GET['video_id']) || ($last = $this->bo->lastVideo()) && empty($_GET['course_id']))
			{
				$video = $this->bo->readVideo($_GET['video_id'] ?? $last['video_id']);
			}
			if (!($course = $this->bo->read(['course_id' => $video ? $video['course_id'] : ($_GET['course_id'] ?? $last['course_id'])])) ||
				!$this->bo->isTutor($course))
			{
				Api\Framework::redirect_link('/index.php', 'menuaction='.$GLOBALS['egw_info']['apps'][Bo::APPNAME]['index']);
			}
			if (!empty($_GET['video_id']))
			{
				$video = $this->bo->readVideo($_GET['video_id']);
			}
			$content = [
				'nm' => [
					'get_rows'       =>	Bo::APPNAME.'.'.self::class.'.get_scores',
					'no_filter2'     => true,	// disable the diverse filters we not (yet) use
					'no_cat'         => true,
					'order'          =>	'rank',	// IO name of the column to sort after (optional for the sortheaders)
					'sort'           =>	'ASC',	// IO direction of the sort: 'ASC' or 'DESC'
					'row_id'         => 'id',	// account_id::video_id
					'dataStorePrefix' => 'smallpart-scores',
					'col_filter'     => ['course_id' => $course['course_id'] ?? $video['course_id']],
					'filter'         => $video['video_id'] ?? '',
					'default_cols'   => '!scored',
					'actions'        => $this->score_actions(),
				]
			];
		}
		$content['nm']['course'] = ['course_name' => $course['course_name']];
		$sel_options = [
			'filter' => [
					'' => lang('Statistics by material'),
				]+$this->bo->listVideos(['course_id' => $content['nm']['col_filter']['course_id']], true),
		];
		if (count($sel_options['filter']) === 1) $content['nm']['filter'] = key($sel_options['filter']);

		$readonlys = [];

		$tmpl = new Api\Etemplate(Bo::APPNAME.'.scores');
		// set dom_id to "smallpart-overlay" to allow refresh_opener($msg, Overlay::SUBTYPE, $content['overlay_id'], $type)
		//$tmpl->set_dom_id(Overlay::SUBTYPE);
		$tmpl->exec(Bo::APPNAME.'.'.self::class.'.scores', $content, $sel_options, $readonlys, ['nm' => $content['nm']]);
	}

	/**
	 * Fetch participants and scores to display
	 *
	 * @param array $query
	 * @param array& $rows =null
	 * @param array& $readonlys =null
	 * @return int total number of rows
	 */
	public function get_scores($query, array &$rows=null, array &$readonlys=null)
	{
		// switch to statistics
		if (!($query['col_filter']['video_id'] = $query['filter']??null))
		{
			Api\Framework::redirect_link('/index.php', [
				'menuaction' => Bo::APPNAME.'.'.self::class.'.statistics',
				'course_id' => $query['col_filter']['course_id'],
				//'video_id' => '',
				'ajax' => 'true',
			]);
		}

		return Overlay::get_scores($query, $rows, $readonlys);
	}

	/**
	 * Return actions for scores list
	 *
	 * @param array $cont values for keys license_(nation|year|cat)
	 * @return array
	 */
	protected function score_actions()
	{
		return [
			'view' => [
				'caption' => 'View or assess answers',
				'default' => true,
				'allowOnMultiple' => false,
				'onExecute' => 'javaScript:app.smallpart.showQuestions',
			],
		];
	}

	/**
	 * Display test participants and their scores
	 *
	 * @param array|null $content
	 */
	public function statistics(array $content=null)
	{
		// allow framing by LMS (LTI 1.3 without specifying a course_id shows Courses::index which redirects here for open
		if (($lms = Api\Cache::getSession('smallpart', 'lms_origin')))
		{
			Api\Header\ContentSecurityPolicy::add('frame-ancestors', $lms);
		}
		if (!is_array($content) || empty($content['nm']))
		{
			if ((!empty($_GET['course_id']) || ($last = $this->bo->lastVideo())) &&
				!($course = $this->bo->read(['course_id' => $_GET['course_id'] ?? $last['course_id']], true, true, false)) ||
				!$this->bo->isTutor($course))
			{
				Api\Framework::redirect_link('/index.php', 'menuaction='.$GLOBALS['egw_info']['apps'][Bo::APPNAME]['index']);
			}
			$content = [
				'nm' => [
					'get_rows'       =>	Bo::APPNAME.'.'.self::class.'.get_statistics',
					'no_filter2'     => true,	// disable the diverse filters we not (yet) use
					'no_cat'         => true,
					'order'          =>	'rank',	// IO name of the column to sort after (optional for the sortheaders)
					'sort'           =>	'ASC',	// IO direction of the sort: 'ASC' or 'DESC'
					'row_id'         => 'video_id',
					'dataStorePrefix' => 'smallpart-statistic',
					'col_filter'     => ['course_id' => $course['course_id']],
					'filter'         => '',
					'default_cols'   => '!scored',
					'actions'        => $this->statistic_actions(),
				]+array_intersect_key($course, array_flip(['course_name']))
			];
			$content['nm']['course'] = ['course_name' => $course['course_name']];
		}
		elseif (!empty($content['nm']['download']) && !empty($content['nm']['col_filter']['course_id']))
		{
			$this->downloadStatistics($content['nm']['col_filter']['course_id'], $content['nm']['course_name']);   // does NOT return
		}
		$sel_options = [
			'filter' => [
					'' => lang('Select material ...'),
				]+$this->bo->listVideos(['course_id' => $content['nm']['col_filter']['course_id']], true),
		];
		if (count($sel_options['filter']) === 1) $content['nm']['filter'] = key($sel_options['filter']);

		$readonlys = [];

		$tmpl = new Api\Etemplate(Bo::APPNAME.'.statistics');
		$tmpl->exec(Bo::APPNAME.'.'.self::class.'.statistics', $content, $sel_options, $readonlys, ['nm' => $content['nm']]);
	}

	/**
	 * Download statistics
	 *
	 * @param int $course_id
	 * @return void
	 */
	protected function downloadStatistics(int $course_id, string $filename='statistics')
	{
		$columns = [
			'rank' => lang('Rank'),
			'video_name' => lang('Name'),
			'video_id' => lang('ID'),
			'sum' => lang('Sum'),
			'average_sum' => lang('Average score-sum'),
			'percent_average_sum' => '% '.lang('Average score-sum'),
			'account' => lang('Student'),
			'score' => lang('Score'),
			'score_percent' => lang('Score').' %',
			'favorite' => lang('Favorite'),
			'started' => lang('Started'),
			'finished' => lang('Finished'),
			'answered' => lang('Answered'),
			'answered_scored' => '% '.lang('Answered').' & '.lang('scored points'),
			'scored' => '# '.lang('Assessed'),
			'assessed' => '% '.lang('Assessed'),
		];
		if ($course_id > 0 && Overlay::get_statistic(['col_filter' => ['course_id' => $course_id]], $rows, $readonlys, null))
		{
			Api\Header\Content::type($filename.'.csv', 'text/csv');
			foreach($rows as $key => $row)
			{
				if (!$key)
				{
					foreach($row as $name => $value)
					{
						// add text-questions to columns
						if (!isset($columns[$name]) && is_array($value))
						{
							$columns[$name] = $name;
						}
					}
					fputcsv($stdout=fopen('php://output', 'w'), $columns, ';');
				}
				foreach(array_keys($row['account'] ?: [0]) as $array_key)
				{
					fputcsv($stdout, array_map(static function($name) use ($row, $array_key)
					{
						$value = $row[$name] ?? '';
						// not aggregated values are arrays and we need to export the value under $array_key
						if (is_array($value))
						{
							$value = $value[$array_key] ?? '';
						}
						// export aggregated values like video-name only once in first line
						elseif($array_key)
						{
							$value = '';
						}
						return in_array($name, ['percent_average_sum', 'score_colored']) || is_string($value) && $value[0] === '<' ?
							strip_tags($value) : $value;
					}, array_keys($columns)), ';');
				}
			}
			fclose($stdout);
			exit;
		}
	}

	/**
	 * Fetch participants and scores to display
	 *
	 * @param array $query
	 * @param array& $rows =null
	 * @param array& $readonlys =null
	 * @return int total number of rows
	 */
	public function get_statistics($query, array &$rows=null, array &$readonlys=null)
	{
		if (($query['col_filter']['video_id'] = $query['filter']??null))
		{
			Api\Framework::redirect_link('/index.php', [
				'menuaction' => Bo::APPNAME.'.'.self::class.'.scores',
				//'course_id' => $query['col_filter']['course_id'],
				'video_id'  => $query['col_filter']['video_id'],
				'ajax' => 'true',
			]);
		}

		return Overlay::get_statistic($query, $rows, $readonlys);
	}

	/**
	 * Return actions for scores list
	 *
	 * @param array $cont values for keys license_(nation|year|cat)
	 * @return array
	 */
	protected function statistic_actions()
	{
		return [
			'view' => [
				'caption' => 'View scores',
				'default' => true,
				'allowOnMultiple' => false,
				'url' => 'menuaction='.Bo::APPNAME.'.'.self::class.'.scores&video_id=$id'
			],
		];
	}
}