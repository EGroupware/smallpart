/*
 * SmallPart Media Recorder
 *
 * @license https://opensource.org/licenses/gpl-license.php GPL - GNU General Public License
 * @package smallpart
 * @subpackage etemplate
 * @link https://www.egroupware.org
 * @author Hadi Nategh
 */

import {css, html, LitElement} from "lit";
import shoelace from "../../api/js/etemplate/Styles/shoelace";
import {Et2Widget} from "../../api/js/etemplate/Et2Widget/Et2Widget";

/**
 * Media options type
 */
interface MediaOptions  {
	video:[],
	audio:[]
}

/**
 *
 */
export class SmallPartMediaRecorder extends Et2Widget(LitElement)
{
	/**
	 * MediaRecorder recorder
	 * @protected
	 */
	protected _recorder = null;
	/**
	 * Media options
	 * @protected
	 */
	protected _mediaOptions : MediaOptions = {video:[], audio:[]};
	/**
	 * contians video source MediaStream
	 * @protected
	 */
	protected _stream : MediaStream = null;
	/**
	 * contains requested Media stream constrains
	 * @protected
	 */
	protected _constraints: MediaStreamConstraints = {video: true, audio: true};

	/**
	 * interval to call ondataavailable event
	 * @protected
	 */
	protected _recordInterval : number = 10000;

	protected _chunkIndex : number = 0;

	static get styles()
	{
		return [
			super.styles,
			shoelace, css`
			:host {
			  width: 100%;
			  display: inherit;
			}
		`];
	}

	static get properties()
	{
		return {
			...super.properties,
			videoName: {type: String},
			constrains: {type: Object}
		}
	}

	constructor(...args : any[])
	{
		super(...args);
		this.constraints = null;
		this.videoName = '';
	}

	firstUpdated()
	{
		super.firstUpdated();

		// we don't want user being prompted for device permissions while the widget is not visible
		if (!this.disabled)
		{
			navigator.mediaDevices.getUserMedia({video:true, audio:true}).then(()=> {
				this._fetchOptions().then((_options : MediaOptions) => {
					this._mediaOptions = _options;
					this.constraints = null;
					this.requestUpdate();
				});
			}).catch(this._errorHandler.bind(this));
		}
	}

	connectedCallback()
	{
		super.connectedCallback();
		window.addEventListener('beforeunload',e =>{
			if (this._recorder.state != 'recording')
			{
				return;
			}
			egw.message(egw.lang("There is an active recording session running! Leaving this page would potentially cause data loss, are you sure that you want to leave?"), "warning");

			// Cancel the event
			e.preventDefault();
			e.returnValue = '';
			return "";
		});
	}

	set constraints(_constraints: object)
	{
		const constraints = _constraints ?? {video: true, audio: true};
		if (this._mediaOptions.audio.length || this._mediaOptions.video.length)
		{
			navigator.mediaDevices.getUserMedia(constraints).then((_stream)=>{
				this._stream = _stream;
				this.requestUpdate();
			});
		}
	}

	protected _errorHandler(_err)
	{
		let msg = '';
		switch(_err.code)
		{
			case 8:
				msg = this.egw().lang('Can not find any connected device to the browser. Please make sure your camera is properly connected to the browser.');
				break;
		}
		this.egw().message(msg, 'error');
	}

	render()
	{
		const captureStream = this._videoNode?.captureStream || this._videoNode?.mozCaptureStream || null;
		const recBtnImg = this._recorder?.state == 'recording' ? 'stop-circle' : 'record-circle';
		const recBtnTitle = this._recorder?.state == 'recording' ? this.egw().lang('stop') : this.egw().lang('record');

		return html`
            <div part="base" .constraints=${this.constraints}>
                <et2-vbox>
					<et2-hbox>
						<et2-select 
								label="${this.egw().lang("Video Source")}" 
								class="select-video-source"
								@change=${this._streamChanged} 
								.select_options=${this._mediaOptions.video ?? []}>
						</et2-select>
						<et2-select
                                label="${this.egw().lang("Audio Source")}"
								class="select-audio-source"
								@change=${this._streamChanged}
                                .select_options=${this._mediaOptions.audio ?? []}>
						</et2-select>
                    </et2-hbox>
					<video 
							.srcObject=${this._stream ?? null}
							class="video-media"
							autoplay="true"
							.captureStream=${captureStream}
							muted="true">
					</video>
					<et2-hbox>
						<et2-hbox .disabled=${!this._recorder}>
							<sl-animation easing="linear" playbackRate="0.5" duration="2000" name="flash" play><sl-icon name="record-circle" class="recorderIcon" style="height: auto;color:red;"></sl-icon></sl-animation>
                            <et2-description value="Recording ..."></et2-description>
						</et2-hbox>
					</et2-hbox>
				</et2-vbox>
            </div> `;
	}

	/**
	 * @return <promise>
	 * @protected
	 */
	protected _fetchOptions()
	{
		return new Promise((resolve)=> {
			this._getDevices().then(_devices=>{
				const _options = {video:[],audio:[]};
				_devices.forEach(_device =>{
					switch(_device.kind)
					{
						case 'audioinput':
							_options.audio.push({value: _device.deviceId, label: _device.label});
							break
						case'videoinput':
							_options.video.push({value: _device.deviceId, label: _device.label});
							break;
					}
				});
				resolve(_options);
			});
		});
	}

	/**
	 * get video
	 * @private
	 */
	private _getDevices() {
		return navigator.mediaDevices.enumerateDevices();
	}

	/**
	 * get video selectbox dom node
	 * @return HTMLSelectElement | null
	 */
	get _videoSourceNode(): HTMLSelectElement
	{
		return this.shadowRoot ? this.shadowRoot.querySelector('.select-video-source') : null;
	}

	/**
	 * get audio selectbox dom node
	 * @return HTMLSelectElement | null
	 */
	get _audioSourceNode() : HTMLSelectElement
	{
		return this.shadowRoot ? this.shadowRoot.querySelector('.select-audio-source') : null;
	}

	/**
	 * get <video> dom node
	 * @return HTMLVideoElement|null
	 */
	get _videoNode(): HTMLVideoElement
	{
		return this.shadowRoot ? this.shadowRoot.querySelector('.video-media') : null;
	}

	private _streamChanged()
	{
		this.constraints = {
			audio: {deviceId: this._audioSourceNode.value ? {exact: this._audioSourceNode.value} : false},
			video: {deviceId: this._videoSourceNode.value ? {exact: this._videoSourceNode.value} : false}
		};
	}

	private _uploadStream(_data)
	{
		let content = app.smallpart.et2.getArrayMgr('content').data;
		let data = {blob:_data, offset: this._chunkIndex};
		let xhr = new XMLHttpRequest();
		let file = new FormData();
		file.append('file', _data);
		file.append('data', JSON.stringify({video: content.video, offset: this._chunkIndex}));
		xhr.onerror = this._uploadChunkError;
		xhr.onloadend = this._uploadChunkEnded;
		xhr.open('POST', egw.ajaxUrl('EGroupware\\smallpart\\Widgets\\SmallPartMediaRecorder::ajax_upload'), true);
		xhr.send(file);
		this._chunkIndex += _data.size;
	}

	private _uploadChunkEnded(_ev)
	{
		console.log(_ev)
	}

	private _uploadChunkError(_ev)
	{
		console.log(_ev)
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
		if (this._videoNode.srcObject)
		{
			this._videoNode.srcObject.getTracks().forEach((track) => track.stop());
			this._videoNode.srcObject = null;
		}
	}

	/**
	 * start recording
	 * @return returns a promise to make sure the media is established then recording gets started
	 */
	record()
	{
		return new Promise((_resolve) => {
			if (this._stream)
			{
				this._recorder = new MediaRecorder(this._stream);
				this.requestUpdate();
				this._recorder.start(this._recordInterval);
				this._recorder.ondataavailable = (event)=> {
					if (event.data.size>1)
					{
						console.log(' Recorded chunk of size ' + event.data.size + "B");
						this._uploadStream(event.data);
					}
				};
                this._videoNode.addEventListener('loadedmetadata', ()=>{_resolve();});
			}
		});
	}

	/**
	 * stop recording
	 * @return returns a promise, to make sure the recording has stopped
	 */
	stop()
	{
		return new Promise((_resolved) => {
			if (this._videoNode && this._recorder)
			{
				this._recorder.onstop = _resolved;
				if (this._recorder.state === 'recording') this._recorder.stop();
			}
		});
	}
}

customElements.define("smallpart-media-recorder", SmallPartMediaRecorder);