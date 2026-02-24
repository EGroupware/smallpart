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

		if (!$bo->isTeacher((int)$data['video']['course_id']) ||
			!($video = $bo->readVideo((int)$data['video']['video_id'])))
		{
			throw new Api\Exception\NoPermission();
		}
		if (empty($_FILES['file']['tmp_name']))
		{

		}
		else
		{
			if ($video['video_hash'])
			{
				$filePath = $bo->videoPath($video, true);
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

		$response->data(['status' => $success, 'offset' => $data['offset']]);
	}
}