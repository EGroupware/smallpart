/**
 * EGroupware SmallPART - Video Recorder
 *
 * @license http://opensource.org/licenses/gpl-license.php GPL - GNU General Public License
 * @package smallpart
 * @subpackage ui
 * @link https://www.egroupware.org
 * @author Hadi Nategh<hn@egroupware.org>
 */

import {et2_baseWidget} from "../../api/js/etemplate/et2_core_baseWidget";
import {et2_register_widget, WidgetConfig} from "../../api/js/etemplate/et2_core_widget";
import {ClassWithAttributes} from "../../api/js/etemplate/et2_core_inheritance";


export class et2_smallpart_video_recorder extends et2_baseWidget
{
	static readonly _attributes: any = {};

	private _video: HTMLVideoElement;
	private _recorder: any = null;
	private _content: any = [];
	div: HTMLElement;

	/**
	 * Constructor
	 */
	constructor(_parent, _attrs?: WidgetConfig, _child?: object)
	{
		// Call the inherited constructor
		super(_parent, _attrs, ClassWithAttributes.extendAttributes(et2_smallpart_video_recorder._attributes, _child || {}));

		this.div = document.createElement("div");
		this.div.classList.add("et2_" + this.getType());

		this._video = document.createElement("video");
		this._video.classList.add('video-media');
		this._video.setAttribute('autoplay', true);
		this._video.setAttribute('muted', true);
		this.div.append(this._video);
		this._content = this.getInstanceManager()._widgetContainer.getArrayMgr('content');

		this.setDOMNode(this.div);
	}

	destroy()
	{
		this.stopMedia();
	}

	/**
	 * stop media stream
	 */
	stopMedia()
	{
		if (this._video.srcObject)
		{
			this._video.srcObject.getTracks().forEach((track) => track.stop());
			this._video.srcObject = null;
		}
	}

	/**
	 * Initialize media stream
	 */
	initMedia()
	{
		return new Promise((resolve)=> {
			if (this._video.srcObject)
			{
				resolve(this._video.captureStream());
				return;
			}
			navigator.mediaDevices.getUserMedia({
				video: true,
				audio: true
			}).then((stream)=>{
				this._video.srcObject = stream;
				this._video.captureStream = this._video.captureStream || this._video.mozCaptureStream;
				return new Promise((_resolve) => {
					this._video.addEventListener('loadedmetadata', _resolve);
				});
			}).then(()=>{
				resolve(this._video.captureStream());
			});
		});
	}

	/**
	 *
	 */
	startMedia()
	{
		this.initMedia().then(()=>{});
	}

	/**
	 * start recording
	 * @return returns a promise to make sure the media is established and recording started
	 */
	record()
	{
		return new Promise((_resolve) => {
			this.initMedia().then((_stream) => {
				this._recorder = new MediaRecorder(_stream);
				this._recorder.start();
				_resolve();
			});
		});
	}

	/**
	 * stop recording
	 * @return returns a promise, to make sure the recording has stopped
	 */
	stop()
	{
		return new Promise((_resolved) => {
			if (this._video && this._recorder)
			{
				this._recorder.ondataavailable = (event) =>{
					let a = document.createElement('a');
					a.download = this._content?.data?.video?.video_name??
						['livefeedback_', (new Date()+'').slice(4,33), '.webm'].join('');
					a.href = URL.createObjectURL(event.data);
					a.click();
				};
				this._recorder.onstop = _resolved;
				if (this._recorder.state === 'recording') this._recorder.stop();
			}
		});
	}
}
et2_register_widget(et2_smallpart_video_recorder, ["smallpart-video-recorder"]);