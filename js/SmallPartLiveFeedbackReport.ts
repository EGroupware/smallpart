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
import {css, html, LitElement, render} from "lit";
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
	/**
	 * Chart the tooltip is currently shown for
	 */
	protected _tooltipChart : any = null;

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
					height: 100%;
					width: 100%;
					display: flex;
					flex-direction: column;
					align-items: center;
					flex-wrap: nowrap;
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
					flex: 1 1 auto;
					height: 0px;
					width: 100%;
					display: flex;
					flex-direction: column;
					align-items: center;
					gap: 1em;
					margin-top: var(--sl-spacing-2x-large);
					overflow-y: auto;
				}

				.chart {
					position: relative;
					min-width: 12em;
					width: var(--width);
					display: flex;
					flex-direction: column;
					align-items: center;
				}

				.chart {
					border-top: 2px solid var(--sl-color-gray-400);
					padding-top: 1em;
				}

				.title {
					border: 3px solid var(--cat_color, transparent);
					padding: var(--sl-spacing-small);
					min-width: 12em;
					text-align: center;
				}

				canvas {
				}

				/* Chart tooltip, shown in the top layer so neither the canvas nor the scrolling report list clips it */

				.chart-tooltip {
					position: fixed;
					inset: auto;
					margin: 0;
					border: none;
					box-sizing: border-box;
					max-width: calc(100vw - var(--sl-spacing-medium));
					max-height: calc(100vh - var(--sl-spacing-medium));
					overflow: hidden;
					padding: var(--sl-spacing-x-small);
					border-radius: var(--sl-tooltip-border-radius);
					background: var(--sl-tooltip-background-color);
					color: var(--sl-tooltip-color);
					font-family: var(--sl-tooltip-font-family);
					font-size: var(--sl-tooltip-font-size);
					line-height: var(--sl-tooltip-line-height);
					white-space: nowrap;
					pointer-events: none;
				}

				.chart-tooltip__title {
					font-weight: var(--sl-font-weight-bold);
					margin-bottom: var(--sl-spacing-2x-small);
				}

				/* A list too long for the window height continues in further columns */

				.chart-tooltip__items {
					display: grid;
					grid-auto-flow: column;
					grid-template-rows: repeat(var(--rows, 1), auto);
					column-gap: var(--sl-spacing-medium);
				}

				.chart-tooltip__item {
					display: flex;
					align-items: center;
					gap: var(--sl-spacing-x-small);
				}

				.chart-tooltip__color {
					flex: none;
					width: var(--sl-spacing-small);
					height: var(--sl-spacing-small);
					border: var(--sl-panel-border-width) solid var(--sl-tooltip-color);
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
		this.handleTooltip = this.handleTooltip.bind(this);
	}



	disconnectedCallback()
	{
		super.disconnectedCallback();
		this.hideTooltip();
	}

	connectedCallback()
	{
		super.connectedCallback();
		this._cats = this.getInstanceManager().widgetContainer.getArrayMgr('sel_options').getEntry('catsOptions').filter(c => c.value !== "free" && c.data?.type !== "sc");
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
			let chartConfigs = [];
			this.hideTooltip();
			this.elements.forEach((_element, _idx) => {
				if (_element && _element.comments) {
					let configs = {
						...this._configs,
						...{
							data: {
								labels: [],
								datasets: this.getDatasets(_element.id)
							},
							options: {
								...this._configs.options, ...{
									plugins: {
										animation: false,
										title: {
											display: false,
											text: _element.title,
										},
										// The canvas tooltip gets cut off at the canvas edge, we show our own
										tooltip: {
											enabled: false,
											// Anchor to the hovered bar, not to the middle of all bars of the category
											position: 'nearest',
											external: this.handleTooltip
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
					let negativeCatId = this._findNegativeSubCat(_element.id)?.value;
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
						(configs.data.datasets.find(c => c.value == cat.value) ?? {}).data = d?.sort((a, b) => a.x > b.x ? 1 : -1);
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
					chartConfigs[_idx] = configs;
				}
			});

			// Common y-axis scale to max value
			let max = 0;
			chartConfigs.forEach(configs =>
			{
				configs.data.datasets.forEach(dataset =>
				{
					dataset.data.forEach(datapoint =>
					{
						if(Math.abs(datapoint.y) > max)
						{
							max = Math.abs(datapoint.y);
						}
					})
				})
			})
			// To next 5
			max = Math.ceil(max / 5) * 5;

			chartConfigs.forEach((configs, _idx) =>
			{
				configs.options.scales.y.max = max;
				configs.options.scales.y.min = -max;
				this.charts[_idx] = new Chart(this._getCanvasNode(_idx), configs);
				this.charts[_idx].resize();
			});
		}
	}

	protected getDatasets(parent_cat_id)
	{
		let cat = this._fetchCatInfo(parent_cat_id);
		let dataset = [this._findPositiveSubCat(parent_cat_id), this._findNegativeSubCat(parent_cat_id)]
			.filter(id => id).map(cat =>
			{
				return cat ? {
					value: cat.value,
					label: cat?.label,
					data: [],
					backgroundColor: cat?.color,
					parsing: {
						yAxisKey: 'y',
						xAxisKey: 'x'
					}
				} : null;
			});

		return dataset;
	}

	protected get _tooltipNode() : HTMLElement & { showPopover? : () => void, hidePopover? : () => void }
	{
		return this.shadowRoot?.querySelector(".chart-tooltip") ?? null;
	}

	protected hideTooltip()
	{
		this._tooltipChart = null;
		const node = this._tooltipNode;
		if(node?.matches(":popover-open"))
		{
			node.hidePopover();
		}
	}

	/**
	 * Show the chart tooltip as an element above the page instead of drawn into the canvas
	 *
	 * Hovering a bar lists every time period of that category with its number of votes.  Drawn into the canvas,
	 * a list higher than the chart lost its top and bottom.  Here the list is kept inside the window, and one too
	 * long for the window height continues in further columns.
	 *
	 * @param context Chart.js external tooltip context
	 */
	protected handleTooltip(context : { chart : any, tooltip : any })
	{
		const {chart, tooltip} = context;
		const node = this._tooltipNode;
		if(!node || typeof node.showPopover !== "function")
		{
			return;
		}
		const lines = (tooltip.body ?? []).map((b, i) => ({
			text: (b.lines ?? []).join(" "),
			color: tooltip.labelColors?.[i]?.backgroundColor
		})).filter(l => l.text);
		// All charts share the one tooltip: a chart the mouse has left is still fading its own out, it must neither
		// redraw nor close the tooltip another chart is showing by now
		if(!tooltip.opacity || !lines.length || !tooltip.getActiveElements?.().length)
		{
			if(this._tooltipChart === chart)
			{
				this._tooltipChart = null;
				this.hideTooltip();
			}
			return;
		}
		this._tooltipChart = chart;

		render(html`
            <div class="chart-tooltip__title">${(tooltip.title ?? []).join(" ")}</div>
            <div class="chart-tooltip__items">
                ${lines.map(l => html`
                    <div class="chart-tooltip__item">
                        <span class="chart-tooltip__color" style="background-color: ${l.color}"></span>${l.text}
                    </div>`)}
            </div>
		`, node);
		if(!node.matches(":popover-open"))
		{
			node.showPopover();
		}

		// As many rows as fit the window height, the rest goes into further columns
		const margin = parseFloat(getComputedStyle(node).fontSize) / 2;
		const items = <HTMLElement>node.querySelector(".chart-tooltip__items");
		const rowHeight = items.firstElementChild?.getBoundingClientRect().height || 1;
		node.style.setProperty("--rows", "1");
		const available = window.innerHeight - 2 * margin - (node.getBoundingClientRect().height - rowHeight);
		node.style.setProperty("--rows", "" + Math.max(1, Math.min(lines.length, Math.floor(available / rowHeight))));

		// Beside the hovered bar, on the side with enough room, and always inside the window
		const canvas = chart.canvas.getBoundingClientRect();
		const size = node.getBoundingClientRect();
		const x = canvas.left + tooltip.caretX;
		const y = canvas.top + tooltip.caretY;
		let left = x + margin;
		if(left + size.width > window.innerWidth - margin)
		{
			left = x - margin - size.width;
		}
		left = Math.max(margin, Math.min(left, window.innerWidth - margin - size.width));
		const top = Math.max(margin, Math.min(y - size.height / 2, window.innerHeight - margin - size.height));
		node.style.left = left + "px";
		node.style.top = top + "px";
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
            <div part="base" class="form-control">
                <sl-range min="35" max="95" step="15"
                          label=${this.egw().lang("zoom")}
                          tooltip="none"
                          @sl-change=${this.handleZoom}
                ></sl-range>
                <sl-range min="30" max="900" step="30"
                          label=${this.egw().lang("sum interval")}
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
                <slot></slot>
                <div part="report-list" class="et2_smallpart-livefeedback-report">
				${repeat(this.elements, (item, _idx) => {
					return html`
                        <div class="chart chart_${_idx}">
                            <style>.chart_${_idx} {
                                --cat_color: ${item.color}
                            }</style>
                            <span class="title">${item.title}</span>
                            <canvas id=${this.id + '-canvas-' + _idx}/>
                        </div>
                    `;
				})}
			</div>
                <div class="chart-tooltip" popover="manual"></div>
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
		let max = 0;
		values.forEach((_c) => {
			const split = _c?.comment_cat?.split(":");
			if (_c && _c.comment_cat && split[2] == 'lf')
			{
				_c.comment_cat = _c.comment_cat.replace(':lf','');
				if (!comments[split[0]]) comments[split[0]] = [];
				comments[split[0]].push(_c);
			}
		});
		this._cats.filter(c => c.parent_id == null).forEach(cat =>
		{
			elements.push({title: cat?.label, comments: comments[cat.value] ?? [], color: cat?.color, id: cat.value});
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