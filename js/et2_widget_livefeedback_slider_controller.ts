/**
 * EGroupware - SmallParT - livefeedback slider controller widget
 *
 * @link https://www.egroupware.org
 * @package smallpart
 * @subpackage Ui
 * @license http://opensource.org/licenses/gpl-license.php GPL - GNU General Public License
 */

/*egw:uses
	/smallpart/js/chart/chart.min.js;
*/

import {et2_smallpart_videobar} from "./et2_widget_videobar";
import {OverlayElement} from "./et2_videooverlay_interface";
import {et2_register_widget, WidgetConfig} from "../../api/js/etemplate/et2_core_widget";
import {ClassWithAttributes} from "../../api/js/etemplate/et2_core_inheritance";
import "./chart/chart.min";
import {et2_baseWidget} from "../../api/js/etemplate/et2_core_baseWidget";


/**
 * slider-controller creates a sliderbar for demonstrating all elements, consists of marking system
 * and selection.
 */
export class et2_smallpart_livefeedback_slider_controller extends et2_baseWidget {
	static readonly _attributes: any = {
		videobar : {
			name: 'videobar',
			type: 'string',
			description: 'videobar this overlay is for',
		},
		seekable: {
			name: 'seekable',
			type: 'boolean',
			description: 'Make slider active for seeking in timeline',
			default: true
		},
		timeSlot: {
			name: 'time slot',
			type: 'integer',
			description: 'a time slot to devide lables. Default is 60 seconds.',
			default: 60
		},
		positiveCatId: {
			name: 'positive category id',
			type: 'string',
			description: 'Category id that supposed to be used as positive data set',
		},
		negativeCatId: {
			name: 'negative category id',
			type: 'string',
			description: 'Category id that supposed to be used as negative data set',
		}
	}
	/**
	 * videobar widget
	 * @protected
	 */
	protected videobar: et2_smallpart_videobar;

	/**
	 * contains categories
	 * @private
	 */
	private _cats: any = [];

	/**
	 *
	 * @private
	 */
	private _interval: any = null;

	/**
	 * wrap container
	 */
	div:JQuery = null;

	/**
	 * contians charts canvas elements
	 */
	canvases : any = [];

	/**
	 * contains created charts
	 */
	charts : [] = [];

	/**
	 * contains chart configs
	 */
	configs : any = {};

	/**
	 * Constructor
	 */
	constructor(_parent, _attrs? : WidgetConfig, _child? : object)
	{
		// Call the inherited constructor
		super(_parent, _attrs, ClassWithAttributes.extendAttributes(et2_smallpart_livefeedback_slider_controller._attributes, _child || {}));
		this.div = jQuery(document.createElement("div"));
		this.div.addClass('et2_smallpart-livefeedback-slider-controller');
		this.charts = [];
		let self = this;

		this.configs = {
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

		this._cats = this.getInstanceManager().widgetContainer.getArrayMgr('content').getEntry('cats');
		super.setDOMNode(this.div[0]);
	}

	/**
	 * Set videobar to use
	 *
	 * @param _id_or_widget
	 */
	set_videobar(_id_or_widget : string|et2_smallpart_videobar)
	{
		if (typeof _id_or_widget === 'string')
		{
			_id_or_widget = <et2_smallpart_videobar>this.getRoot().getWidgetById(_id_or_widget);
		}
		if (_id_or_widget instanceof et2_smallpart_videobar)
		{
			this.videobar = _id_or_widget;
		}
	}


	/**
	 * set given elements as actual marks on sliderbar
	 *
	 * @param _elements
	 * [{
	 * 	comments: [{}],
	 * 	title: string
	 * }]
	 */
	set_value(_elements:  Array<OverlayElement>)
	{
		this.elements = _elements || [];

		let self = this;
		this._checkVideoIsLoaded().then(_ => {

			for(let i=this.charts.length - 1; i >= 0; i--)
			{
				this.charts[i].destroy();
			}

			this.elements.forEach((_element, _idx) => {
				if (_element && _element.comments)
				{
					let configs = {...this.configs,
						...{
							data:{
								labels:[],
								datasets:[]
							},
							options: {...this.configs.options, ...{
									plugins: {
										animation: false,
										title: {
											display: true,
											text: _element.title,
										}
									}
								},
								onClick: (e, value) =>
								{
									if (!this.options.seekable || !value.length) return;
									const canvasPosition = Chart.helpers.getRelativePosition(e, self.charts[_idx]);
									const labelIndex = self.charts[_idx].scales.x.getValueForPixel(canvasPosition.x);
									self.videobar.seek_video(configs.data.labels[labelIndex]);
								}
							}
						}
					};
					if (!this.canvases[_idx])
					{
						this.canvases[_idx] = document.createElement('canvas');
						this.canvases[_idx].setAttribute('id', this.id+'-canvas-'+_idx);
						this.div.append(this.canvases[_idx]);
					}
					let data = {};
					_element.comments.forEach((_c, _i) => {
						let cat_id = _c['comment_cat'].split(":").pop();
						if (typeof data[cat_id] === 'undefined') data[cat_id] = [];
						data[cat_id].push(_c.comment_starttime - _c.comment_starttime%this.options.timeSlot);
					});
					let negativeCatId = Object.keys(data).pop(); //TODO: read it from set options
					Object.keys(data).forEach(_cat_id =>{
						let cat = this._fetchCatInfo(_cat_id);
						let d = [];
						data[_cat_id].forEach(_d=> {
							let index = this._findIndexofDataItem(d, _d);
							if (index >= 0) {
								d[index]['y'] = d[index]['y'] + ((_cat_id == negativeCatId) ? -1 :1);
							}
							else
							{
								d.push({x:_d, y:(_cat_id == negativeCatId) ? -1 : 1});
								configs.data.labels.push(_d/60); // label the time in minute
							}
						});
						configs.data.datasets.push({
							label: cat.cat_name,
							data: d.sort((a,b)=> a.x > b.x?1:-1),
							backgroundColor: cat.cat_color,
							parsing: {
								yAxisKey: 'y',
								xAxisKey: 'x'
							}
						});
					});
					// labels need to be unique otherwise the charts get messed up
					configs.data.labels = configs.data.labels.filter((v, i, a) => a.indexOf(v) === i).sort((a,b)=> a > b ? 1 : -1);
					this.charts[_idx] = new Chart(this.canvases[_idx], configs);
				}
			});
		});
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

	/**
	 * Fetch category info for the given cat_id
	 * @param _cat_id
	 * @return returns array of cat data
	 * @private
	 */
	public _fetchCatInfo(_cat_id)
	{
		let cats = [];
		this._cats.forEach(_a=>{
			cats.push(_a);
			if (_a.subs) {
				_a.subs.forEach(_c=>{
					cats.push(_c);
				});
			}
		});
		return cats.filter(_cat => {return _cat.cat_id == _cat_id;})[0];
	}

	/**
	 * Promise to check the video is loaded
	 * @private
	 */
	private _checkVideoIsLoaded()
	{
		clearInterval(this._interval);
		return new Promise((_resolved, _rejected) => {
			if (this.videobar.duration()>0)
			{
				clearInterval(this._interval);
				return _resolved();
			}
			this._interval = setInterval(_=>{
				if (this.videobar.duration()>0)
				{
					clearInterval(this._interval);
					_resolved();
					return;
				}
			}, 1000);
		});
	}
}
et2_register_widget(et2_smallpart_livefeedback_slider_controller, ["smallpart-livefeedback-slider-controller"]);
