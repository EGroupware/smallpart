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
import Dexie from '../../node_modules/dexie/dist/dexie.js';

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
	 * interval period to call ondataavailable event
	 * @protected
	 */
	protected _recordInterval : number = 10000;

	/**
	 * interval to send the recorded chuncks to server
	 * @protected
	 */
	protected _uploadInterval : number = null;

	/**
	 * interval period to send the recorded chuncks to server
	 * @protected
	 */
	protected _uploadIntervalTimeout : number = 5000;

	/**
	 * keeps the last recorded chunk offset
	 * @protected
	 */
	protected _lastChunkOffset : number = 0;

	/**
	 * keeps number of recorded chunks in db
	 * @protected
	 */
	protected _recordedChunks : number = 0;

	/**
	 * keeps number of upload chunks (reads them from db.uploaded)
	 * @protected
	 */
	protected _uploadedChunks : number = 0;

	/**
	 * keeps number of waiting for being uploaded chunks (max in queue is 10 chunks)
	 * @protected
	 */
	protected _queuedChunks : Array<MediaStream> = [];

	/**
	 * db instance
	 * @protected
	 */
	protected _db : any = null;

	/**
	 * video encoded mime type
	 */
	static readonly MimeType : String = "video/webm;";

	/**
	 * video db structure
	 * id: auto increamental
	 * data: blob of recorded video
	 * offset: offset position of the blob (chunk) in bytes, offset 0 blob contains the header of the video
	 * uploaded: flag to keep track of the chunk that being uploaded successfuly to the server
	 */
	static readonly DbTable: any = {
		video: "++id,data,offset,uploaded"
	};

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
			/**
			 * video name
			 */
			videoName: {type: String},
			/**
			 * custom constrains to be set for MediaRecorder
			 * @default it's set by user via select media
			 */
			constrains: {type: Object},
			/**
			 * automatic upload to the server while recording the video
			 * @default false
			 */
			autoUpload: {type: Boolean}
		}
	}

	constructor(...args : any[])
	{
		super(...args);
		this.constraints = null;
		this.videoName = '';
		this.autoUpload = false;
	}

	firstUpdated()
	{
		super.firstUpdated();

		// we don't want user being prompted for device permissions while the widget is not visible (either hidden or disabled)
		if (!this.disabled && !this.hidden)
		{
			this._db = new Dexie(this.id);
			this._db.version(1).stores(SmallPartMediaRecorder.DbTable);
			this._db.video.clear();
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
			this.egw().message(this.egw().lang("There is an active recording session running! Leaving this page would potentially cause data loss, are you sure that you want to leave?"), "warning");

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

	render()
	{
		const captureStream = this._videoNode?.captureStream || this._videoNode?.mozCaptureStream || null;
		const showRecording = this._recorder?.state == 'recording';

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
						<et2-hbox .disabled=${!showRecording}>
							<sl-animation easing="linear" playbackRate="0.5" duration="2000" name="flash" play><sl-icon name="record-circle" class="recorderIcon" style="height: auto;color:red;"></sl-icon></sl-animation>
                            <et2-description value="Recording ..."></et2-description>
                            <et2-hbox>
								<et2-label value="Recorded Chunks" label="%s:"></et2-label>
								<et2-label class="recorded" value="0"></et2-label>
                                <et2-button-icon
                                        title=${this.egw().lang('download')}
                                        image="box-arrow-down"
                                        @click=${this._downloadHandler}
                                        class="button-download"
                                        .disabled=${!this._recorder}
                                        noSubmit="true"></et2-button-icon>
                                <et2-label value="Uploaded Chunks" label="%s:"></et2-label>
                                <et2-label class="uploaded" value="0/0"></et2-label>
							</et2-hbox>
						</et2-hbox>
					</et2-hbox>
				</et2-vbox>
            </div> `;
	}

	destroy()
	{
		clearInterval(this._uploadInterval);
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
				this._recorder = new MediaRecorder(this._stream, {mimeType:SmallPartMediaRecorder.MimeType});
				this.requestUpdate();
				this._recorder.start(this._recordInterval);
				if (this.autoUpload) this._initUploadStream();
				this._recorder.ondataavailable = (event)=> {
					if (event.data.size>1)
					{
						console.log(' Recorded chunk of size ' + event.data.size + "B");
						this._db.video.add({data: event.data, offset: this._lastChunkOffset, uploaded: 0}).catch(this._dbErrorHandler);
						this._db.video.count().then((_count)=>{
							this._recordedChunks = _count;
							this.__updateUploadIndication();
						}).catch(this._dbErrorHandler);
						this._lastChunkOffset += event.data.size;
					}
				};
				this._videoNode.addEventListener('loadedmetadata', ()=>{_resolve();});
			}
		});
	}

	/**
	 * Check if the uploading is done
	 * @private
	 * @return return a promise
	 */
	private uploadingIsfinished()
	{
		return new Promise((_resolve) => {
			if (!this.autoUpload)
			{
				_resolve();
				return;
			}
			let interval = setInterval(()=>{
				if (this._recorder?.state == 'inactive' && this._recordedChunks == this._uploadedChunks)
				{
					clearInterval(interval);
					_resolve();
				}
			}, 100)
		});
	}

	/**
	 * offer download if there was a recording
	 */
	download()
	{
		if (this._recorder)
		{
			this.uploadingIsfinished().then(this._downloadHandler.bind(this));
		}
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
				this._recorder.onstop = ()=>{
					if (this.autoUpload)
					{
						_resolved();
					}
					else
					{
						_resolved()
						// always offer a download for none auto upload mode
						this._downloadHandler();
					}
				};
				if (this._recorder.state === 'recording')
				{
					this._recorder.stop();
					this.requestUpdate();
				}
			}
		});
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
	 * Media recorder error handler
	 * @param _err MediaDevices Exceptions
	 * @protected
	 */
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

	/**
	 * Register indexed Db (Dexie) errors into console logs
	 * @param _error DixieError
	 * @protected
	 */
	protected _dbErrorHandler(_error)
	{
		let msg ='';
		switch (_error.name) {
			case Dexie.errnames.Schema:
				msg = "Schemad error"
				console.error (msg);
				break;
			default:
				msg = _error.message;
				console.error ("error: " + msg);
		}
		this.egw().message(`Something went wrong with Recording! ${msg}`, "error");
	}

	/**
	 * hendle download given video blob
	 * @param _data
	 * @private
	 */
	private _downloadHandler()
	{
		if (this._recorder?.state == 'recording') this._recorder.requestData();
		this._db.video.orderBy('offset').toArray().then((_data)=>{
			let blobs = [];
			_data.forEach(_item=>{
				blobs.push(_item.data);
			});
			let blob = new Blob(blobs, {type:SmallPartMediaRecorder.MimeType});
			let a = document.createElement('a');
			a.download = this.videoName ?? ['livefeedback_', (new Date()+'').slice(4,33), '.webm'].join('');
			a.href = URL.createObjectURL(blob);
			a.click();
		}).catch(this._dbErrorHandler);
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

	/**
	 * initialize interval for uploading
	 * @private
	 */
	private _initUploadStream()
	{
		// make sure not triggering it if we are not in autoUpload mode
		if (!this.autoUpload) return;

		this._uploadInterval = setInterval(_=>{
			if (this._queuedChunks.length == 0)
			{
				this._db.video.where({uploaded:0}).limit(10).toArray().then(_values=>{
					this._queuedChunks = _values;
				}).catch(this._dbErrorHandler);
			}

			if (this._queuedChunks.length>0)
			{
				let chunk = this._queuedChunks.shift();
				this._buildRequest(chunk).then(this.__resolvedRequest.bind(this)).catch(this._dbErrorHandler);
			}

			this.__updateUploadIndication();
		}, this._uploadIntervalTimeout);
	}

	private __updateUploadIndication()
	{
		const uploaded = this.autoUpload ? this._uploadedChunks : this.egw().lang('auto upload is deactive');
		this.shadowRoot.querySelector('.uploaded').value = `${uploaded}/ ${this._recordedChunks}`;
		this.shadowRoot.querySelector('.recorded').value = `${this._recordedChunks}`;
	}

	/**
	 * resolver of request's promise
	 * @param _offset
	 * @private
	 */
	private __resolvedRequest(_offset)
	{
		this._db.video.where({offset:_offset}).modify({uploaded:1}).catch(this._dbErrorHandler);
		this._db.video.where({uploaded:1}).count((_count)=>{this._uploadedChunks = _count}).catch(this._dbErrorHandler);
	}

	private _buildRequest(_data)
	{
		return new Promise((_resolve) => {
			let content = app.smallpart.et2.getArrayMgr('content').data;
			let xhr = new XMLHttpRequest();
			let file = new FormData();
			file.append('file', _data.data);
			file.append('data', JSON.stringify({video: content.video, offset: _data.offset}));
			xhr.onerror = ()=> {
				// retry to send the request again
				window.setTimeout(()=> {
					this._db.video.where({offset:_data.offset}).toArray().then(_value=>{
						// check first the status then retry
						if (!_value?.uploaded)
						{
							this._buildRequest(_data).then(this.__resolvedRequest.bind(this));
						}
					}).catch(this._dbErrorHandler)
				}, 1000 * (this._queuedChunks.length??1)); // decrease the retry ratio base on queued chunks
			};
			xhr.onreadystatechange = () => {
				if (xhr.readyState == 4 && xhr.status == 200)
				{
					_resolve(_data.offset);
				}
			};
			xhr.open('POST', egw.ajaxUrl('EGroupware\\smallpart\\Widgets\\SmallPartMediaRecorder::ajax_upload'), true);
			xhr.send(file);
		});
	}


}

customElements.define("smallpart-media-recorder", SmallPartMediaRecorder);