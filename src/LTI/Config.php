<?php
/**
 * EGroupware - SmallParT - LTI Learning Tools Interoperatbility - Configuration
 *
 * @link https://www.egroupware.org
 * @package smallpart
 * @subpackage lti
 * @author Ralf Becker <rb@egroupware.org>
 * @copyright 2020 by Ralf Becker <rb@egroupware.org>
 * @license https://spdx.org/licenses/AGPL-3.0-or-later.html GNU Affero General Public License v3.0 or later
 */

namespace EGroupware\SmallParT\LTI;

use EGroupware\Api;
use EGroupware\Api\Etemplate;
use EGroupware\SmallParT\Bo;

/**
 * LTI Tool Configuration
 *
 * Configuration is stored in EGroupware Configuration under issuer name shortend to 32 char.
 *
 * @package EGroupware\SmallParT\LTI
 */
class Config
{
	public $public_functions = [
		'index' => true,
	];

	/**
	 * Read issue configuration
	 *
	 * @param string $iss issuer url
	 * @param string $lti_version='1.3'
	 * @param string $oauth_key=null for LTI v1.0
	 * @return array|null null if not found
	 */
	public static function read($iss, $lti_version='1.3', string $oauth_key=null)
	{
		$config = Api\Config::read(Bo::APPNAME);
		foreach($config as $key => $data)
		{
			if (!isset($data['lti_version']) || !isset($data['iss'])) continue;	// other config item --> ignore

			if ($lti_version === $data['lti_version'] && ($iss === $data['iss'] ||
				// LTI v1.0 uses Consumer GUID which is the hostname of the URL
				$lti_version === '1.0' && $iss === PARSE_URL($data['iss'], PHP_URL_HOST)))
			{
				if ($lti_version === '1.3')
				{
					return $data;
				}
				if (empty($data['oauth_key']) || $oauth_key === $data['oauth_key'])
				{
					return $data;
				}
			}
		}
		return null;
	}

	/**
	 * Read config by LTI 1.0 oauth-key
	 *
	 * @param $oauth_key
	 * @return array|null
	 */
	public static function readByOauthKey($oauth_key)
	{
		$config = Api\Config::read(Bo::APPNAME);
		foreach($config as $key => $data)
		{
			if ($data['lti_version'] === '1.0' && !empty($config['oauth_key']) && $oauth_key === $config['oauth_key'])
			{
				return $data;
			}
		}
		return null;
	}

	/**
	 * Read config / platform data by id
	 *
	 * @param string $id "$iss:$lti_version" (
	 * @return ?array
	 */
	public static function readById(string $id)
	{
		if (preg_match('/^(.*):([\d.]+)$/', $id, $matches))
		{
			return self::read($matches[1], $matches[2]);
		}
		return null;
	}

	/**
	 * Save configuration
	 *
	 * @param array $content
	 * @return string with success message
	 * @throws Api\Exception\WrongParameter
	 */
	protected function save(array $content)
	{
		$old_config = Api\Config::read(Bo::APPNAME);
		$saved = $removed = 0;
		foreach($content as $key => &$data)
		{
			if (empty(trim($data['iss']))) continue;
			$iss = substr($data['iss'], 0, 28).':'.$data['lti_version'];
			// some validation
			foreach(array_merge(['iss','account_name'], $data['lti_version'] === '1.3' ?
				['client_id','deployment','lti_version','auth_token_url','auth_login_url','key_set_url'] : []) as $name)
			{
				if (empty($data[$name]) || $name !== 'account_name' && empty(trim($data[$name])))
				{
					Etemplate::set_validation_error($key.'['.$name.']', lang('Field must not be empty !!!'));
					error_log(__METHOD__."() $key: $name=".json_encode($data[$name]));
				}
				if ($name !== 'account_name') $data[$name] = trim($data[$name]);
			}
			if (Etemplate::validation_errors())
			{
				throw new Api\Exception\WrongUserinput(lang('Field must not be empty !!!'));
			}
			$data['deployment'] = empty(trim($data['deployment'])) ? [] :
				preg_split('/[ ,;\n\r\t]+/', trim($data['deployment']));
			if (empty($data['created'])) $data['created'] = new Api\DateTime();
			$data['updated'] = new Api\DateTime();
			Api\Config::save_value($iss, $data, Bo::APPNAME);
			unset($old_config[$iss]);
			++$saved;
		}
		foreach(array_keys($old_config) as $iss)
		{
			if (substr($iss, 0, 4) === 'http')
			{
				Api\Config::save_value($iss, null, Bo::APPNAME);
				++$removed;
			}
		}
		return $removed ? lang('%1 LTI configuration saved, %2 removed.', $saved, $removed) :
			lang('%1 LTI configuration saved.', $saved);
	}

	/**
	 * Show LTI tool configuration
	 *
	 * @param array|null $content
	 * @throws Api\Exception\AssertionFailed
	 * @throws Api\Exception\WrongParameter
	 */
	public function index(array $content=null)
	{
		Api\Translation::add_app('smallpart');
		if(!empty($content['button']))
		{
			$button = key($content['button']);
			unset($content['button']);
			try {
				switch ($button)
				{
					case 'save':
					case 'apply':
						Api\Framework::message($this->save($content), 'success');
						unset($content);
						if ($button === 'apply') break;
					// fall-through
					case 'cancel':
						Api\Egw::redirect_link('/index.php', ['menuaction' => 'admin.admin_ui.index', 'ajax' => 'true'], 'admin');
						break;
				}
			}
			catch (\Exception $e) {
				Api\Framework::message($e->getMessage(), 'error');
			}
		}
		if (!isset($content))
		{
			$config = Api\Config::read(Bo::APPNAME);
			$content = [false];
			foreach($config as $iss => $data)
			{
				if (substr($iss, 0, 4) !== 'http') continue;
				$data['label'] = $data['iss'].':LTI v'.$data['lti_version'];
				$data['deployment'] = implode("\n", (array)$data['deployment']);
				$content[] = $data;
			}
			$content[] = [
				'label' => lang('New'),
				'lti_version' => '1.3',
			];
		}
		else
		{
			foreach($content as $iss => &$row)
			{
				if (!is_array($row)) continue;
				$row['label'] = !empty($row['iss']) ? $row['iss'].':LTI v'.$row['lti_version'] : lang('New');
			}
		}

		$tpl = new Etemplate('smallpart.lti-config');
		$tpl->exec(Bo::APPNAME.'.'.self::class.'.index', $content, null, null, $content);
	}
}