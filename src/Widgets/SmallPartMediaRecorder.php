<?php

namespace EGroupware\SmallParT\Widgets;

use EGroupware\Api\Json\Response;
use EGroupware\SmallParT\Bo;

class SmallPartMediaRecorder
{

	static function ajax_upload()
	{
		$response = Response::get();

		$bo = new Bo((int)$GLOBALS['egw_info']['user']['account_id']);
		$success = false;
		$data = json_decode($_POST['data'], true);

		if (!$bo->isTeacher($data['video']['course_id']))
		{
			throw new Api\Exception\NoPermission();
		}
		if (!isset($_FILES['file']) || empty($_FILES['file']) || empty($_FILES['file']['tmp_name']))
		{

		}
		else
		{
			if ($data['video']['video_hash'])
			{
				$filePath = $bo->videoPath($data['video'], true);
				if ($data['offset'] == 0)
				{
					$success = copy($_FILES['file']['tmp_name'], $filePath) ? 0 : false;
				}
				else
				{
					$tmpFile = file_get_contents($_FILES['file']['tmp_name']);
					$stream = fopen($filePath,'r+b');
					fseek($stream, $data['offset']);
					$success = fwrite($stream, $tmpFile);
				}
			}
		}

		$response->data($success);
	}

}