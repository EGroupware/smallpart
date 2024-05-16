<?php
/**
 * EGroupware - SmallParT - REST API handler
 *
 * @link https://www.egroupware.org
 * @package smallpart
 * @subpackage setup
 * @license https://spdx.org/licenses/AGPL-3.0-or-later.html GNU Affero General Public License v3.0 or later
 */

namespace EGroupware\SmallParT;

use EGroupware\Api;

/**
 * REST API for Timesheet
 */
class ApiHandler extends Api\CalDAV\Handler
{
	/**
	 * @var Bo
	 */
	protected Bo $bo;

	/**
	 * Extension to append to url/path
	 *
	 * @var string
	 */
	static $path_extension = '';

	/**
	 * Which attribute to use to contruct name part of url/path
	 *
	 * @var string
	 */
	static $path_attr = 'course_id';

	/**
	 * Constructor
	 *
	 * @param string $app 'calendar', 'addressbook' or 'infolog'
	 * @param Api\CalDAV $caldav calling class
	 */
	function __construct($app, Api\CalDAV $caldav)
	{
		parent::__construct(Bo::APPNAME, $caldav);
		self::$path_extension = '';

		// we must NOT set user from path-prefix here, as this would allow to impersonate the user without an ACL check!
		$this->bo = new Bo();
	}

	/**
	 * Options for json_encode of responses
	 */
	const JSON_RESPONSE_OPTIONS = JSON_PRETTY_PRINT|JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES|JSON_THROW_ON_ERROR;

	/**
	 * Handle propfind in the smallpart / get request on the collection itself
	 *
	 * @param string $path
	 * @param array &$options
	 * @param array &$files
	 * @param int $user account_id
	 * @param string $id =''
	 * @return mixed boolean true on success, false on failure or string with http status (eg. '404 Not Found')
	 */
	function propfind($path,&$options,&$files,$user,$id='')
	{
		$filter = [
		];

		// process REPORT filters or multiget href's
		$nresults = null;
		if (($id || $options['root']['name'] != 'propfind') && !$this->_report_filters($options,$filter, $id, $nresults, $user))
		{
			return false;
		}
		if ($id) $path = dirname($path).'/';	// carddav_name get's added anyway in the callback

		if ($this->debug) error_log(__METHOD__."($path,".array2string($options).",,$user,$id) filter=".array2string($filter));

		// rfc 6578 sync-collection report: filter for sync-token is already set in _report_filters
		if ($options['root']['name'] == 'sync-collection')
		{
			return '501 Not Implemented';
			// callback to query sync-token, after propfind_callbacks / iterator is run and
			// stored max. modification-time in $this->sync_collection_token
			$files['sync-token'] = array($this, 'get_sync_collection_token');
			$files['sync-token-params'] = array($path, $user);

			$this->sync_collection_token = null;

			$filter['order'] = 'course_name ASC';	// return oldest modifications first
			$filter['sync-collection'] = true;
		}

		if (isset($nresults))
		{
			$files['files'] = $this->propfind_generator($path, $filter, $files['files'], (int)$nresults);

			// hack to support limit with sync-collection report: contacts are returned in modified ASC order (oldest first)
			// if limit is smaller than full result, return modified-1 as sync-token, so client requests next chunk incl. modified
			// (which might contain further entries with identical modification time)
			if ($options['root']['name'] == 'sync-collection' && $this->bo->total > $nresults)
			{
				--$this->sync_collection_token;
				$files['sync-token-params'][] = true;	// tell get_sync_collection_token that we have more entries
			}
		}
		else
		{
			// return iterator, calling ourselves to return result in chunks
			$files['files'] = $this->propfind_generator($path,$filter, $files['files']);
		}
		return true;
	}

	/**
	 * Chunk-size for DB queries of profind_generator
	 */
	const CHUNK_SIZE = 500;

	/**
	 * Generator for propfind with ability to skip reporting not found ids
	 *
	 * @param string $path
	 * @param array& $filter
	 * @param array $extra extra resources like the collection itself
	 * @param int|null $nresults option limit of number of results to report
	 * @param boolean $report_not_found_multiget_ids=true
	 * @return Generator<array with values for keys path and props>
	 */
	function propfind_generator($path, array &$filter, array $extra=[], $nresults=null, $report_not_found_multiget_ids=true)
	{
		//error_log(__METHOD__."('$path', ".array2string($filter).", ".array2string($start).", $report_not_found_multiget_ids)");
		$starttime = microtime(true);
		$filter_in = $filter;

		// yield extra resources like the root itself
		$yielded = 0;
		foreach($extra as $resource)
		{
			if (++$yielded && isset($nresults) && $yielded > $nresults)
			{
				return;
			}
			yield $resource;
		}

		if (isset($filter['order']))
		{
			$order = $filter['order'];
			unset($filter['order']);
		}
		else
		{
			$order = 'course_id';
		}
		// detect sync-collection report
		$sync_collection_report = $filter['sync-collection'];
		unset($filter['sync-collection']);

		// stop output buffering switched on to log the response, if we should return more than 200 entries
		if (!empty($this->requested_multiget_ids) && ob_get_level() && count($this->requested_multiget_ids) > 200)
		{
			$this->caldav->log("### ".count($this->requested_multiget_ids)." resources requested in multiget REPORT --> turning logging off to allow streaming of the response");
			ob_end_flush();
		}

		$search = $filter['search'] ?? [];
		unset($filter['search']);
		for($chunk=0; ($courses =& $this->bo->search($search, '*', $order, '', '', False, 'AND',
			[$chunk*self::CHUNK_SIZE, self::CHUNK_SIZE], $filter)); ++$chunk)
		{
			/* read custom-fields
			if ($this->bo->customfields)
			{
				$id2keys = array();
				foreach($courses as $key => &$course)
				{
					$id2keys[$course['course_id']] = $key;
				}
				if (($cfs = $this->bo->read_customfields(array_keys($id2keys))))
				{
					foreach($cfs as $id => $data)
					{
						$courses[$id2keys[$id]] += $data;
					}
				}
			}*/
			foreach($courses as &$course)
			{
				$content = JsObjects::JsCourse(['course_options'=>null,'allow_neutral_lf_categories'=>null]+$course, false);

				// remove contact from requested multiget ids, to be able to report not found urls
				if (!empty($this->requested_multiget_ids) && ($k = array_search($course[self::$path_attr], $this->requested_multiget_ids)) !== false)
				{
					unset($this->requested_multiget_ids[$k]);
				}
				// sync-collection report: deleted entry need to be reported without properties
				if (!empty($course['course_closed']))
				{
					if (++$yielded && isset($nresults) && $yielded > $nresults)
					{
						return;
					}
					yield ['path' => $path.urldecode($this->get_path($course))];
					continue;
				}
				$props = array(
					'getcontenttype' => Api\CalDAV::mkprop('getcontenttype', 'application/json'),
					//'getlastmodified' => Api\DateTime::user2server($course['modified']),
					'displayname' => $course['course_name'],
				);
				if (true)
				{
					$props['getcontentlength'] = bytes(is_array($content) ? json_encode($content) : $content);
					$props['data'] = Api\CalDAV::mkprop('data', $content);
				}
				if (++$yielded && isset($nresults) && $yielded > $nresults)
				{
					return;
				}
				yield $this->add_resource($path, $course, $props);
			}
			// sync-collection report --> return modified of last contact as sync-token
			if ($sync_collection_report)
			{
				$this->sync_collection_token = $course['modified'];
			}
		}

		// report not found multiget urls
		if ($report_not_found_multiget_ids && !empty($this->requested_multiget_ids))
		{
			foreach($this->requested_multiget_ids as $id)
			{
				if (++$yielded && isset($nresults) && $yielded > $nresults)
				{
					return;
				}
				yield ['path' => $path.$id.self::$path_extension];
			}
		}

		if ($this->debug)
		{
			error_log(__METHOD__."($path, filter=".json_encode($filter).', extra='.json_encode($extra).
				", nresults=$nresults, report_not_found=$report_not_found_multiget_ids) took ".
				(microtime(true) - $starttime)." to return $yielded resources");
		}
	}

	/**
	 * Process filter GET parameter:
	 * - filter[<json-attribute-name>]=<value>
	 * - filter[%23<custom-field-name]=<value>
	 * - filter[search]=<pattern> with string pattern like for search in the UI
	 * - filter[search][%23<custom-field-name]=<value>
	 * - filter[search][<db-column>]=<value>
	 *
	 * @param array $filter
	 * @return array
	 */
	protected function filter2col_filter(array $filter, int $user)
	{
		$cols = [];
		foreach($filter as $name => $value)
		{
			switch($name)
			{
				case 'search':
					$cols = array_merge($cols, $this->bo->search2criteria($value));
					break;
				default:
					if ($name[0] === '#')
					{
						$cols[$name] = $value;
					}
					else
					{
						switch($name)
						{
							case 'subscribed':
								$value = $user;
								// fall-through
							case 'account_id':
								if ((!is_int($value) || !is_numeric($value)) &&
									!($value = Api\Accounts::getInstance()->name2id($value, strpos($value, '@')!==false ? 'account_email' : 'account_name')))
								{
									break;
								}
								$cols['account_id'] = $value;
								break;

							default:
								$cols['course_'.$name] = $value;
								break;
						}
					}
					break;
			}
		}
		return $cols;
	}

	/**
	 * Process the filters from the CalDAV REPORT request
	 *
	 * @param array $options
	 * @param array &$filters
	 * @param string $id
	 * @param int &$nresult on return limit for number or results or unchanged/null
	 * @return boolean true if filter could be processed
	 */
	function _report_filters($options, &$filters, $id, &$nresults, $user)
	{
		// in case of JSON/REST API pass filters to report
		if (Api\CalDAV::isJSON() && !empty($options['filters']) && is_array($options['filters']))
		{
			$filters += $this->filter2col_filter($options['filters'], $user);
		}
		elseif (!empty($options['filters']))
		{
			/* Example of a complex filter used by Mac Addressbook
			  <B:filter test="anyof">
			    <B:prop-filter name="FN" test="allof">
			      <B:text-match collation="i;unicode-casemap" match-type="contains">becker</B:text-match>
			      <B:text-match collation="i;unicode-casemap" match-type="contains">ralf</B:text-match>
			    </B:prop-filter>
			    <B:prop-filter name="EMAIL" test="allof">
			      <B:text-match collation="i;unicode-casemap" match-type="contains">becker</B:text-match>
			      <B:text-match collation="i;unicode-casemap" match-type="contains">ralf</B:text-match>
			    </B:prop-filter>
			    <B:prop-filter name="NICKNAME" test="allof">
			      <B:text-match collation="i;unicode-casemap" match-type="contains">becker</B:text-match>
			      <B:text-match collation="i;unicode-casemap" match-type="contains">ralf</B:text-match>
			    </B:prop-filter>
			  </B:filter>
			*/
			$filter_test = isset($options['filters']['attrs']) && isset($options['filters']['attrs']['test']) ?
				$options['filters']['attrs']['test'] : 'anyof';
			$prop_filters = array();

			$matches = $prop_test = $column = null;
			foreach($options['filters'] as $n => $filter)
			{
				if (!is_int($n)) continue;	// eg. attributes of filter xml element

				switch((string)$filter['name'])
				{
					case 'param-filter':
						$this->caldav->log(__METHOD__."(...) param-filter='{$filter['attrs']['name']}' not (yet) implemented!");
						break;
					case 'prop-filter':	// can be multiple prop-filter, see example
						if ($matches) $prop_filters[] = implode($prop_test=='allof'?' AND ':' OR ',$matches);
						$matches = array();
						$prop_filter = strtoupper($filter['attrs']['name']);
						$prop_test = isset($filter['attrs']['test']) ? $filter['attrs']['test'] : 'anyof';
						if ($this->debug > 1) error_log(__METHOD__."(...) prop-filter='$prop_filter', test='$prop_test'");
						break;
					case 'is-not-defined':
						$matches[] = '('.$column."='' OR ".$column.' IS NULL)';
						break;
					case 'text-match':	// prop-filter can have multiple text-match, see example
						if (!isset($this->filter_prop2cal[$prop_filter]))	// eg. not existing NICKNAME in EGroupware
						{
							if ($this->debug || $prop_filter != 'NICKNAME') error_log(__METHOD__."(...) text-match: $prop_filter {$filter['attrs']['match-type']} '{$filter['data']}' unknown property '$prop_filter' --> ignored");
							$column = false;	// to ignore following data too
						}
						else
						{
							switch($filter['attrs']['collation'])	// todo: which other collations allowed, we are always unicode
							{
								case 'i;unicode-casemap':
								default:
									$comp = ' '.$GLOBALS['egw']->db->capabilities[Api\Db::CAPABILITY_CASE_INSENSITIV_LIKE].' ';
									break;
							}
							$column = $this->filter_prop2cal[strtoupper($prop_filter)];
							if (strpos($column, '_') === false) $column = 'contact_'.$column;
							if (!isset($filters['order'])) $filters['order'] = $column;
							$match_type = $filter['attrs']['match-type'];
							$negate_condition = isset($filter['attrs']['negate-condition']) && $filter['attrs']['negate-condition'] == 'yes';
						}
						break;
					case '':	// data of text-match element
						if (isset($filter['data']) && isset($column))
						{
							if ($column)	// false for properties not known to EGroupware
							{
								$value = str_replace(array('%', '_'), array('\\%', '\\_'), $filter['data']);
								switch($match_type)
								{
									case 'equals':
										$sql_filter = $column . $comp . $GLOBALS['egw']->db->quote($value);
										break;
									default:
									case 'contains':
										$sql_filter = $column . $comp . $GLOBALS['egw']->db->quote('%'.$value.'%');
										break;
									case 'starts-with':
										$sql_filter = $column . $comp . $GLOBALS['egw']->db->quote($value.'%');
										break;
									case 'ends-with':
										$sql_filter = $column . $comp . $GLOBALS['egw']->db->quote('%'.$value);
										break;
								}
								$matches[] = ($negate_condition ? 'NOT ' : '').$sql_filter;

								if ($this->debug > 1) error_log(__METHOD__."(...) text-match: $prop_filter $match_type' '{$filter['data']}'");
							}
							unset($column);
							break;
						}
					// fall through
					default:
						$this->caldav->log(__METHOD__."(".array2string($options).",,$id) unknown filter=".array2string($filter).' --> ignored');
						break;
				}
			}
			if ($matches) $prop_filters[] = implode($prop_test=='allof'?' AND ':' OR ',$matches);
			if ($prop_filters)
			{
				$filters[] = $filter = '(('.implode($filter_test=='allof'?') AND (':') OR (', $prop_filters).'))';
				if ($this->debug) error_log(__METHOD__."(path=$options[path], ...) sql-filter: $filter");
			}
		}
		// parse limit from $options['other']
		/* Example limit
		  <B:limit>
		    <B:nresults>10</B:nresults>
		  </B:limit>
		*/
		foreach((array)$options['other'] as $option)
		{
			switch($option['name'])
			{
				case 'nresults':
					$nresults = (int)$option['data'];
					//error_log(__METHOD__."(...) options[other]=".array2string($options['other'])." --> nresults=$nresults");
					break;
				case 'limit':
					break;
				case 'href':
					break;	// from addressbook-multiget, handled below
				// rfc 6578 sync-report
				case 'sync-token':
					if (!empty($option['data']))
					{
						$parts = explode('/', $option['data']);
						$sync_token = array_pop($parts);
						$filters[] = 'contact_modified>'.(int)$sync_token;
						$filters['tid'] = null;	// to return deleted entries too
					}
					break;
				case 'sync-level':
					if ($option['data'] != '1')
					{
						$this->caldav->log(__METHOD__."(...) only sync-level {$option['data']} requested, but only 1 supported! options[other]=".array2string($options['other']));
					}
					break;
				default:
					$this->caldav->log(__METHOD__."(...) unknown xml tag '{$option['name']}': options[other]=".array2string($options['other']));
					break;
			}
		}
		// multiget --> fetch the url's
		$this->requested_multiget_ids = null;
		if ($options['root']['name'] == 'addressbook-multiget')
		{
			$this->requested_multiget_ids = [];
			foreach($options['other'] as $option)
			{
				if ($option['name'] == 'href')
				{
					$parts = explode('/',$option['data']);
					if (($id = urldecode(array_pop($parts))))
					{
						$this->requested_multiget_ids[] = self::$path_extension ? basename($id,self::$path_extension) : $id;
					}
				}
			}
			if ($this->requested_multiget_ids) $filters[self::$path_attr] = $this->requested_multiget_ids;
			if ($this->debug) error_log(__METHOD__."(...) addressbook-multiget: ids=".implode(',', $this->requested_multiget_ids));
		}
		elseif ($id)
		{
			$filters[self::$path_attr] = self::$path_extension ? basename($id,self::$path_extension) : $id;
		}
		//error_log(__METHOD__."() options[other]=".array2string($options['other'])." --> filters=".array2string($filters));
		return true;
	}

	/**
	 * Handle get request for an applications entry
	 *
	 * @param array &$options
	 * @param int $id
	 * @param int $user =null account_id
	 * @return mixed boolean true on success, false on failure or string with http status (eg. '404 Not Found')
	 */
	function get(&$options,$id,$user=null)
	{
		header('Content-Type: application/json');

		if (!is_array($course = $this->_common_get_put_delete('GET',$options,$id)))
		{
			return $course;
		}

		try
		{
			// only JSON, no *DAV
			if (($type=Api\CalDAV::isJSON($type)))
			{
				$options['mimetype'] = 'application/json';

				if (!preg_match('#/smallpart/(\d+)(/(participants|materials|\d+)(/(\d+|attachments(/(.*))$)?)?)?#', $options['path'], $matches))
				{
					return '404 Not Found';
				}
				[, $course_id, , $video_id, , $attachments, , $attachment] = $matches+[null, null, null, null, null, null, null];

				if (!is_numeric($video_id))
				{
					$options['data'] = JsObjects::JsCourse($course, false);
					// just participants or materials
					if (!empty($video_id))
					{
						$options['data'] = $options['data'][$video_id] ?? [];
						if (!empty($attachments))
						{
							$options['data'] = $options['data'][$attachments] ?? [];
						}
					}
					$options['data'] = Api\CalDAV::json_encode($options['data'], $type === 'pretty');
				}
				elseif (!($video = $this->bo->readVideo($video_id)) || $video['course_id'] != $course['course_id'])
				{
					return '404 Not Found';
				}
				else
				{
					$options['data'] = JsObjects::JsMaterial($this->bo->readVideoAttachments($video), empty($attachments) ? $type : false);
					if ($attachments)
					{
						$options['data'] = $options['data']['attachments'] ?? [];
						if ($attachment)
						{
							if (!isset($options['data'][$attachment]))
							{
								return '404 Not Found';
							}
							header('Location: '.$options['data'][$attachment]['url']);
							return '301 Moved Permanently';
						}
						else
						{
							$options['data'] = Api\CalDAV::json_encode($options['data'] ?? [], $type === 'pretty');
						}
					}
				}

				header('Content-Encoding: identity');
				// ToDo: header('ETag: "'.$this->get_etag($course).'"');
				return true;
			}
		}
		catch (\Throwable $e) {
			return self::handleException($e);
		}
		return '501 Not Implemented';
	}

	/**
	 * Handle exception by returning an appropriate HTTP status and JSON content with an error message
	 *
	 * @param \Throwable $e
	 * @return string
	 */
	protected function handleException(\Throwable $e) : string
	{
		_egw_log_exception($e);
		header('Content-Type: application/json');
		if (is_a($e, Api\Exception\NoPermission::class))
		{
			$e = new \Exception('Forbidden', 403, $e);
		}
		echo json_encode(array_filter([
				'error'   => $code = $e->getCode() ?: 500,
				'message' => $e->getMessage(),
				'details' => $e->details ?? null,
				'script'  => $e->script ?? null,
			]+(empty($GLOBALS['egw_info']['server']['exception_show_trace']) ? [] : [
				'trace' => array_map(static function($trace)
				{
					$trace['file'] = str_replace(EGW_SERVER_ROOT.'/', '', $trace['file']);
					return $trace;
				}, $e->getTrace())
			])), self::JSON_RESPONSE_OPTIONS);
		return (400 <= $code && $code < 600 ? $code : 500).' '.$e->getMessage();
	}

	/**
	 * Handle PUT & POST request for videoteach/smallpart
	 *
	 * @param array &$options
	 * @param int $id
	 * @param int $user =null account_id of owner, default null
	 * @param string $prefix =null user prefix from path (eg. /ralf from /ralf/addressbook)
	 * @param string $method='PUT' also called for POST and PATCH
	 * @param string $content_type=null
	 * @return mixed boolean true on success, false on failure or string with http status (eg. '404 Not Found')
	 */
	function put(&$options, $id, $user=null, $prefix=null, string $method='PUT', string $content_type=null)
	{
		if ($this->debug) error_log(__METHOD__."($id, $user)".print_r($options,true));
		if (empty($user))
		{
			$user = $GLOBALS['egw_info']['user']['account_id'];
		}
		header('Content-Type: application/json');

		try {
			if (!preg_match('#/smallpart/(\d+)?(/(participants|materials|\d+)(/(\d+|attachments(/(.*))$)?)?)?#', $options['path'], $matches))
			{
				return '404 Not Found';
			}
			[, $course_id, , $video_id, , $attachments, , $attachment] = $matches + [null, null, null, null, null, null, null];

			$return_no_access = $video_id === 'participants';   // no not check for edit-access in participants, to allow regular users to subscribe
			if ($course_id && !is_array($course = $this->_common_get_put_delete($method, $options, $id, $return_no_access)))
			{
				return $course;
			}
			// only JSON, no *DAV
			if (($type=Api\CalDAV::isJSON()))
			{
				// create a new course, update or patch an existing one
				if (empty($course_id) || empty($video_id) && $method !== 'POST')
				{
					if ($method !== 'POST' && empty($course_id))
					{
						return '400 Bad Request';
					}
					$course = JsObjects::parseJsCourse($options['content'], $course ?? [], null, $method);
					$course = $this->bo->save($course+['course_onwer' => $user]);
					if ($method === 'POST')
					{
						$this->new_id = $course['course_id'];
						header('Location: '.Api\Framework::link('/groupdav.php/smallpart/'.$this->new_id));
						return '201 Created';
					}
					return '204 No Content';
				}

				// add new participant or update existing one
				if ($video_id === 'participants')
				{
					if (empty($attachments) && $method !== 'POST' || $method === 'POST' && !empty($attachments))
					{
						return '400 Bad Request';
					}
					if (!empty($attachments) && !($participant=current(array_filter($course['participants'], static function($participant) use ($attachments)
						{
							return $participant['account_id'] == $attachments;
						}))))
					{
						return '404 Not Found';
					}
					if (empty($options['content']))
					{
						$data = ['account_id' => $attachments ?: $user, 'password' => null, 'role' => Bo::ROLE_STUDENT];
					}
					elseif (!($json = json_decode($options['content'], true)))
					{
						return '422 Unprocessable Entity';
					}
					else
					{
						$data = JsObjects::parseParticipant($json+['account' => $attachments ?: $user]);
						// check path of PUT request does NOT contain (a different) account_id
						if (!empty($attachments))
						{
							if (!empty($json['account']) && $data['account_id'] != $attachments)
							{
								return '422 Unprocessable Entity';
							}
							$data['account_id'] = $attachments;
						}
					}
					try {
						if (empty($participant) || $participant['participant_role'] != ($data['role']??Bo::ROLE_STUDENT))
						{
							$this->bo->subscribe($course['course_id'], true, $data['account_id'], $data['password'], $data['role']);
							// do we need to set the alias too
							if (!empty($data['alias']) && $data['account_id'] == $GLOBALS['egw_info']['user']['account_id'])
							{
								$this->bo->changeNickname($course['course_id'], $data['alias']);
							}
							header('Location: '.Api\Framework::link('/groupdav.php/smallpart/'.$course['course_id'].'/participants/'.($this->new_id=$data['account_id'])));
							return '201 Created';
						}
						elseif (!empty($data['alias']) && $data['account_id'] == $GLOBALS['egw_info']['user']['account_id'])
						{
							$this->bo->changeNickname($course['course_id'], $data['alias']);
							return '200 Ok';
						}
						return '400 Bad Request';
					}
					catch (\Throwable $e) {
						return '403 Forbidden';
					}
				}

				if ($video_id && !($video = $this->bo->readVideo($video_id)))
				{
					return '404 Not Found';
				}

				// create a new material, update or patch an existing one
				if (empty($video_id) || empty($attachments) && $method !== 'POST')
				{
					if ($method !== 'POST' && empty($video_id))
					{
						return '400 Bad Request';
					}
					$video = JsObjects::parseJsMaterial($options['content'], $video ?? [], null, $method);
					$video['course_id'] = $course_id;
					$video_id = $this->bo->saveVideo($video);
					if ($method === 'POST')
					{
						$this->new_id = $video_id;
						header('Location: '.Api\Framework::link('/groupdav.php/smallpart/'.$course['course_id'].'/'.$this->new_id));
						return '201 Created';
					}
					return '204 No Content';
				}

				// add new participant
				return '501 Not Implemented yet ;)';
			}
			// add a new or update an existing attachment
			elseif ($attachments)
			{
				if ($method === 'POST')
				{
					return '405 Method Not Allowed';
				}
				if (empty($course_id) || empty($video_id) || empty($attachment) ||
					!($ext = Api\MimeMagic::mime2ext($options['content_type'])))
				{
					return '400 Bad Request';
				}
				if (!($video = $this->bo->readVideo($video_id)) || $course_id != $video['course_id'])
				{
					return '404 Not Found';
				}
				if (!str_ends_with($attachment, '.'.$ext))
				{
					$attachment .= '.'.$ext;
				}
				if (!Api\Vfs::file_exists($dir='/apps/smallpart/'.$course['course_id'].'/'.$video['video_id'].'/all/task') &&
					!Api\Vfs::mkdir($dir, 0777, true))
				{
					return '403 Forbidden';
				}
				header('Location: '.Api\Framework::link($path='/webdav.php'.$dir.'/'.$attachment));
				return '307 Temporary Redirect';
			}
			// create new or update material by posting main document
			elseif (preg_match(Bo::VIDEO_MIME_TYPES, $options['content_type']))
			{
				if ($video_id && !($video = $this->bo->readVideo($video_id)))
				{
					return '404 Not Found';
				}
				if (!is_resource($options['stream']) && isset($options['content']) &&
					($options['stream'] = fopen('php://temp', 'r+')))
				{
					fwrite($options['stream'], $options['content']);
					fseek($options['stream'], 0);
				}
				if (!is_resource($options['stream']))
				{
					return '422 Unprocessable Content';
				}
				$upload = [
					'tmp_name' => $options['stream'],
					'type' => $options['content_type'],
					'name' => isset($_SERVER['HTTP_CONTENT_DISPOSITION']) &&
						substr($this->_SERVER['HTTP_CONTENT_DISPOSITION'], 0, 10) === 'attachment' &&
						preg_match('/;\s*filename="([^"]+)"/', $_SERVER['HTTP_CONTENT_DISPOSITION'], $matches) ? $matches[1] : 'No name',
				];
				if (empty($video_id))
				{
					$video = $this->bo->addVideo($course, $upload);
					$this->new_id = $video['video_id'];
					header('Location: '.Api\Framework::link('/groupdav.php/smallpart/'.$course['course_id'].'/'.$this->new_id));
					// otherwise "Prefer: return=representation" won't work
					if (empty($_SERVER['HTTP_ACCEPT']) || !preg_match('#application/(pretty\+)?json#', $_SERVER['HTTP_ACCEPT']))
					{
						$_SERVER['HTTP_ACCEPT'] = 'application/json';
					}
					return '201 Created';
				}
				$this->bo->updateVideo($video, $upload);
				return '204 No Content';
			}
		}
		catch (\Throwable $e) {
			return self::handleException($e);
		}
		return '400 Bad Request';
	}

	/**
	 * Handle post request to allow creating material by posting it to course (automatic calling put only works for application/json!)
	 *
	 * @param array &$options
	 * @param int $id
	 * @param int $user =null account_id of owner, default null
	 * @return mixed boolean true on success, false on failure or string with http status (eg. '404 Not Found')
	 */
	function post(&$options,$id,$user=null)
	{
		$status = $this->put($options, $id, $user, null, 'POST');

		// CalDAV::POST() does NOT handle that automatic like CalDAV::PUT()
		if (((string)$status)[0] === '2' || $status === true)
		{
			// we can NOT use 204 No content (forbids a body) with return=representation, therefore we need to use 200 Ok instead!
			if ($this->check_return_representation($options, $id ?: $this->new_id, $user) && (int)$status == 204)
			{
				$status = '200 Ok';
			}
		}
		return $status;
	}

	/**
	 * Handle delete request for an applications entry
	 *
	 * @param array &$options
	 * @param int $id
	 * @param int $user account_id of collection owner
	 * @return mixed boolean true on success, false on failure or string with http status (eg. '404 Not Found')
	 */
	function delete(&$options,$id,$user)
	{
		if (!preg_match('#/smallpart/(\d+)?(/(participants|materials|\d+)(/(\d+|attachments(/(.*))$)?)?)?#', $options['path'], $matches))
		{
			return '404 Not Found';
		}
		[, $course_id, , $video_id, , $account_id, , $attachment] = $matches + [null, null, null, null, null, null, null];

		$return_no_access = $video_id === 'participants';   // do NOT check delete rights for participants unsubscribing
		if (!is_array($course = $this->_common_get_put_delete('DELETE',$options,$id, $return_no_access)))
		{
			return $course;
		}

		if (!empty($video_id) && is_numeric($video_id))
		{
			if (!($video = $this->bo->readVideo($video_id)))
			{
				return '404 Not Found';
			}
			if ($attachment)
			{
				$dir='/apps/smallpart/'.$course['course_id'].'/'.$video['video_id'].'/all/task';
				header('Location: '.Api\Framework::link($path='/webdav.php'.$dir.'/'.$attachment));
				return '307 Temporary Redirect';
			}
			if ($this->bo->deleteVideo($video))
			{
				return '204 No Content';
			}
			return '403 Forbidden';
		}
		elseif ($video_id === 'participants')
		{
			// delete / unsubscribe participant
			if (!is_numeric($account_id) || !array_filter($course['participants'], static function ($participant) use ($account_id)
				{
					return $participant['account_id'] == $account_id;
				}))
			{
				return '404 Not Found';
			}
			try
			{
				$this->bo->subscribe($course_id, false, $account_id);
				return '204 No Content';
			}
			catch (Api\Exception\NoPermission $e) {
				return '403 Forbidden';
			}

		}
		// delete / close course
		$course['course_closed'] = new Api\DateTime('now');
		if ($this->bo->save($course))
		{
			return '204 No Content';
		}
		return '403 Forbidden';
	}

	/**
	 * Read an entry
	 *
	 * @param string|int $id
	 * @return array|boolean array with entry, false if no read rights, null if $id does not exist
	 */
	function read($id)
	{
		try {
			return $this->bo->read($id, false);
		}
		catch (Api\Exception\NoPermission $e) {
			return false;
		}
	}

	/**
	 * Check if user has the necessary rights on an entry / course
	 *
	 * Read requires to be a participant, Edit or Delete requires a course-admin.
	 *
	 * @param int $acl Api\Acl::READ, Api\Acl::EDIT or Api\Acl::DELETE
	 * @param array|int $entry entry-array or id
	 * @return boolean null if entry does not exist, false if no access, true if access permitted
	 */
	function check_access($acl, $entry)
	{
		return $this->bo->isParticipant($entry, $acl == Api\Acl::READ ? Bo::ROLE_STUDENT : Bo::ROLE_ADMIN);
	}
}