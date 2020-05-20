<?php
/**
 * EGroupware - SmallParT - LTI Learning Tools Interoperatbility - Configuration
 *
 * @link https://www.egroupware.org
 * @package smallpart
 * @subpackage lti
 * @author Ralf Becker <rb@egroupware.org>
 * @copyright 2020 by Ralf Becker <rb@egroupware.org>
 * @license http://opensource.org/licenses/gpl-license.php GPL - GNU General Public License
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
	 * @return array|null null if not found
	 */
	public static function read($iss)
	{
		$config = Api\Config::read(Bo::APPNAME);
		$data = $config[substr($iss, 0, 32)];
		return isset($data) && $data['iss'] === $iss ? $data : null;
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
			$iss = substr($data['iss'], 0, 32);
			if (empty(trim($iss))) continue;
			// some validation
			foreach(array_merge(['iss','client_id','deployment','lti_version','auth_token_url','auth_login_url','account_name'],
				$data['lti_version'] === '1.3' ? ['key_set_url'] : ['oauth_key','oauth_secret']) as $name)
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
				$data['label'] = $data['iss'];
				$data['deployment'] = implode("\n", $data['deployment']);
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
				$row['label'] = !empty($row['iss']) ? $row['iss'] : lang('New');
			}
		}

		$tpl = new Etemplate('smallpart.config');
		$tpl->exec(Bo::APPNAME.'.'.self::class.'.index', $content);
	}
}