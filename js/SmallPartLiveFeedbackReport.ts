/*
 * SmallPart livefeedback controller widget
 *
 * @license https://opensource.org/licenses/gpl-license.php GPL - GNU General Public License
 * @package smallpart
 * @subpackage etemplate
 * @link https://www.egroupware.org
 * @author Hadi Nategh
 */


import {Et2Widget} from "../../api/js/etemplate/Et2Widget/Et2Widget";
import {css, html, LitElement} from "lit";
import {repeat} from "lit/directives/repeat.js";
import "./chart/chart.min";
import {et2_smallpart_videobar} from "./et2_widget_videobar";

/**
 *
 *
 */
export class SmallPartLiveFeedbackReport extends Et2Widget(LitElement)
{

	protected _configs: any = {};
	protected _videobar: any = null;
	protected _cats: any = [];
	protected _video: any = [];
	protected _interval: any = null;
	protected _comments : any = [];
	private elements : any = [];
	private charts : any = [];
	private __timeSlot : number = 0;
	/**
	 * contains created charts
	 */
	protected _charts : any = [];
	/**
	 * contians charts canvas elements
	 */
	protected _canvases: any = [];

	static get styles()
	{
		return [
			...super.styles,
			css`
				:host {
					display: contents;
					--width: 35%;
					--label-width: 12em;
				}

				/* Larger maximum height before scroll*/

              .select__tags {
                max-height: 10em;
              }

              :host([readonly]) .select__control,
              :host([readonly]) .select__control:hover {
                background: transparent;
                border: none;
              }

				.form-control {
					width: 100%;
					display: flex;
					flex-direction: column;
					align-items: center;
				}

				sl-range {
					min-width: 10em;
					max-width: 80%;
				}

				sl-range::part(form-control-label) {
					width: initial;
					width: var(--label-width, 8em);
				}

				sl-range::part(form-control-input) {
					width: 20em
				}

				.et2_smallpart-livefeedback-report {
					width: var(--width, 50%);
					display: flex;
					flex-direction: column;
					align-items: center;
				}

				canvas {
					min-width: 20em;
					min-height: 10em;
				}
			`
		];
	}
	static get properties()
	{
		return {
			...super.properties,
			/**
			 *
			 */
			comments: {
				type: Array
			},
			/**
			 * videobar this overlay is for
			 */
			videobar: {
				type: String
			},
			/**
			 * Make slider active for seeking in timeline
			 */
			seekable: {
				type: Boolean
			},
			/**
			 * A time slot to divide labels. Default is 60 seconds.
			 */
			timeSlot: {
				type: Number
			},
			/**
			 * Show all divided time labels in the x axis even the ones with no data. default is true.
			 */
			showEmptyLabels: {
				type: Boolean
			},
			/**
			 *
			 */
			sessionStartTime: {
				type: Number
			},
			/**
			 *
			 */
			sessionEndTime: {
				type: Number
			}
		}
	}

	constructor(...args: any[])
	{
		super(...args);
		this._configs = {
			type:'bar',
			options:{
				responsive: true,
				maintainAspectRatio: true,
				scales: {
					x: {
						stacked: true,
					},
					y: {
						stacked: true,
						ticks: {
							// forces step size to be 1 units
							stepSize: 10
						}
					}
				},
				interaction: {
					mode: 'dataset'
				}
			}
		};
		this.timeSlot = 60;
		this.showEmptyLabels = true;

		this.handleZoom = this.handleZoom.bind(this);
		this.handleIntervalChange = this.handleIntervalChange.bind(this);
	}



	connectedCallback()
	{
		super.connectedCallback();
		this._cats = this.getInstanceManager().widgetContainer.getArrayMgr('sel_options').getEntry('catsOptions');
		this._video = this.getInstanceManager().widgetContainer.getArrayMgr('content').getEntry('video');

		this._checkVideoIsLoaded().then(_=>{
			for(let i=this._charts.length - 1; i >= 0; i--)
			{
				this._charts[i].destroy();
			}
			this.requestUpdate();
		});
	}

	/**
	 * Handle changes that have to happen based on changes to properties
	 *
	 */
	updated(changedProperties)
	{
		super.updated(changedProperties);
		if(changedProperties.has("elements") || changedProperties.has("timeSlot"))
		{
			let self = this;
			this.elements.forEach((_element, _idx) => {
				if (_element && _element.comments) {
					let configs = {
						...this._configs,
						...{
							data: {
								labels: [],
								datasets: []
							},
							options: {
								...this._configs.options, ...{
									plugins: {
										animation: false,
										title: {
											display: true,
											text: _element.title,
										}
									}
								},
								onClick: (e, value) => {
									if (!this.seekable || !value.length) return;
									const canvasPosition = Chart.helpers.getRelativePosition(e, self.charts[_idx]);
									const labelIndex = self.charts[_idx].scales.x.getValueForPixel(canvasPosition.x);
									// convert minute label to second in order to seek right time in video
									self.videobar.seek_video(configs.data.labels[labelIndex] * 60);
								}
							}
						}
					};

					let data = {};
					_element.comments.forEach((_c, _i) => {
						let cat_id = _c['comment_cat'].split(":").pop();
						if (typeof data[cat_id] === 'undefined') data[cat_id] = [];
						data[cat_id].push(_c.comment_starttime - _c.comment_starttime % this.timeSlot);
					});
					let negativeCatId = Object.keys(data).length > 0 ? this._findNegativeSubCat(this._fetchCatInfo(Object.keys(data)[0])['parent_id'])?.value : null;
					Object.keys(data).forEach(_cat_id => {
						let cat = this._fetchCatInfo(_cat_id);
						let d = [];
						data[_cat_id].forEach(_d => {
							let timeVal = _d/this.timeSlot;
							let index = this._findIndexofDataItem(d, timeVal);
							if (index >= 0) {
								d[index]['y'] = d[index]['y'] + ((_cat_id == negativeCatId) ? -1 : 1);
							} else {
								d.push({x: timeVal, y: (_cat_id == negativeCatId) ? -1 : 1});
								configs.data.labels.push(timeVal); // label the time in minute
							}
						});
						configs.data.datasets.push({
							label: cat?.label,
							data: d?.sort((a, b) => a.x > b.x ? 1 : -1),
							backgroundColor: cat?.color,
							parsing: {
								yAxisKey: 'y',
								xAxisKey: 'x'
							}
						});
					});
					if (this.showEmptyLabels) {
						configs.data.labels = Array.from({length: (self._getSessionDuration()) / self.timeSlot + 1}, (_, i) => i * self.timeSlot/60);
					}
					else
					{
						// labels need to be unique otherwise the charts get messed up
						configs.data.labels = configs.data.labels.filter((v, i, a) => a.indexOf(v) === i).sort((a,b)=> a > b ? 1 : -1);
					}
					if (this.charts[_idx]) this.charts[_idx].destroy();
					this.charts[_idx] = new Chart(this._getCanvasNode(_idx), configs);
					this.charts[_idx].resize();
				}
			});
		}
	}

	protected handleIntervalChange(event)
	{
		this.timeSlot = parseInt(event.target.value || 60);
	}

	protected handleZoom(event)
	{
		let width = event.target?.value ?? 35;
		this.style.setProperty("--width", width + "%");

		// Tell charts to resize
		this.charts.forEach(c => c.resize())
	}

	render()
	{
		return html`
            <div class="form-control">
                <sl-range min="20" max="95" step="15"
                          label=${this.egw().lang("zoom")}
                          tooltip="none"
                          @sl-change=${this.handleZoom}
                ></sl-range>
                <sl-range min="30" max="900" step="30"
                          label="sum interval"
                          tooltip="bottom"
                          .tooltipFormatter=${(seconds) =>
                          {
                              // Round to nearest 0.5
                              const minutes = Math.round(parseInt(seconds) / 60 * 2) / 2;
                              return this.egw().lang("%1 min", minutes);
                          }}
                          .value=${this.timeSlot}
                          @sl-change=${this.handleIntervalChange}
                ></sl-range>
			<div class="et2_smallpart-livefeedback-report">
				${repeat(this.elements, (item, _idx) => {
					return html`
						<canvas id=${this.id + '-canvas-' + _idx}/>					
					`;
				})}
			</div>
            </div>
		`;
	}

	protected _getCanvasNode(_idx)
	{
		return this.shadowRoot ? this.shadowRoot.querySelector('#'+this.id+'-canvas-'+_idx) : null;
	}

	set comments(_comments)
	{
		const values = _comments.map(obj => ({ ...obj }));
		let comments = {};
		let elements = [];
		values.forEach((_c) => {
			const split = _c?.comment_cat?.split(":");
			if (_c && _c.comment_cat && split[2] == 'lf')
			{
				_c.comment_cat = _c.comment_cat.replace(':lf','');
				if (!comments[split[0]]) comments[split[0]] = [];
				comments[split[0]].push(_c);
			}
		});
		Object.keys(comments).forEach(_cat_id => {
			let cat = this._fetchCatInfo(_cat_id);
			elements.push({title:cat?.label, comments: comments[_cat_id], color: cat?.color});
		});
		this.elements = elements;
		this.requestUpdate('elements');
	}

	set timeSlot(_time)
	{
		this.__timeSlot = _time;
		this.requestUpdate('timeSlot');
	}

	get timeSlot()
	{
		if (!this.__timeSlot)
		{
			return this._video && this._video['livefeedback'] && this._video['livefeedback']['session_interval']
				? parseInt(this._video['livefeedback']['session_interval']) * 60 : 60;
		}
		return this.__timeSlot;
	}

	set videobar(_widget : string|et2_smallpart_videobar)
	{
		if (typeof _widget === 'string')
		{
			_widget = <et2_smallpart_videobar>this.getRoot().getWidgetById(_widget);
		}
		if (_widget instanceof et2_smallpart_videobar)
		{
			this._videobar = _widget;
		}
	}
	get videobar()
	{
		return this._videobar;
	}

	/**
	 * Calculate session duration
	 */
	_getSessionDuration()
	{
		let d = (Date.parse(this._video.livefeedback.session_endtime)
			- Date.parse(this._video.livefeedback.session_starttime)) / 1000;
		if (this.sessionEndTime) d = this.sessionEndTime;
		return d > 10 ? d : this.videobar.duration();
	}

	/**
	 * Find the index number for the given value in data array
	 * @param _data array of data
	 * @param _value value to look for
	 * @return returns index number
	 * @private
	 */
	private _findIndexofDataItem(_data, _value)
	{
		let index = 0;
		return _data.findIndex(_d=>{return _d.x == _value});
	}

	private _findNegativeSubCat(_parent_id)
	{
		let cat = this._cats.filter(_cat=>{return _cat.parent_id == _parent_id && _cat?.data?.type == 'lf' && _cat?.data?.value =='n';});
		return cat ? cat[0] : [];
	}

	private _findPositiveSubCat(_parent_id)
	{
		let cat = this._cats.filter(_cat=>{return _cat.parent_id == _parent_id && _cat?.data?.type == 'lf' && _cat?.data?.value =='p';});
		return cat ? cat[0] : [];
	}

	/**
	 * Fetch category info for the given cat_id
	 * @param _cat_id
	 * @return returns array of cat data
	 * @private
	 */
	public _fetchCatInfo(_cat_id)
	{
		return this._cats.filter(_cat => {return _cat.value == _cat_id;})[0];
	}

	/**
	 * Promise to check the video is loaded
	 * @private
	 */
	private _checkVideoIsLoaded()
	{
		clearInterval(this._interval);
		return new Promise((_resolved, _rejected) => {
			if (this.videobar?.duration()>0)
			{
				clearInterval(this._interval);
				return _resolved();
			}
			this._interval = setInterval(_=>{
				if (this.videobar?.duration()>0)
				{
					clearInterval(this._interval);
					_resolved();
					return;
				}
			}, 1000);
		});
	}
}

customElements.define("smallpart-livefeedback-report", SmallPartLiveFeedbackReport);