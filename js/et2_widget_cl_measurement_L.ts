/**
 * EGroupware - SmallParT - cognitive load measurement L widget
 *
 * @link https://www.egroupware.org
 * @package smallpart
 * @subpackage Ui
 * @author Hadi Nategh <hn@egroupware.org>
 * @license http://opensource.org/licenses/gpl-license.php GPL - GNU General Public License
 */

import {et2_createWidget, et2_register_widget, WidgetConfig} from "../../api/js/etemplate/et2_core_widget";
import {ClassWithAttributes} from '../../api/js/etemplate/et2_core_inheritance';
import {et2_button} from "../../api/js/etemplate/et2_widget_button";
import {et2_baseWidget} from "../../api/js/etemplate/et2_core_baseWidget";
import {et2_smallpart_videobar} from "./et2_widget_videobar";
import {smallpartApp} from "./app";
import {et2_dialog} from "../../api/js/etemplate/et2_widget_dialog";

export class et2_smallpart_cl_measurement_L extends et2_baseWidget
{
	static readonly _attributes : any = {
		mode: {
			name: 'mode',
			type: 'string',
			description: 'Defines the stage of the process the widget is running in, calibration and running mode.',
			default: 'calibration'
		},
		active: {
			name: 'active',
			type: 'boolean',
			description: 'Activate/deactivate "L" button color mode and functions',
			default: false
		},
		activation_period: {
			name: 'activation period',
			type: 'integer',
			description: 'Defines the duration of active mode, default is 1s (the time is in millisecond).',
			default: 5000
		},
		steps_className: {
			name: 'steps classname',
			type: 'string',
			description: 'comma separated css class name for defining (hide/show) steps. (steps are based on orders)',
			default: ''
		},
		running_interval: {
			name: 'running interval',
			type: 'integer',
			description: 'Defines interval time in minutes of active mode display',
			default: 5
		},
		running_interval_range: {
			name: 'running interval range',
			type: 'integer',
			description: 'Defines interval time in seconds of active mode display',
			default: 30
		}
	};

	div : HTMLDivElement = null;
	l_button : et2_button = null;
	protected _mode : string = 'calibration';
	protected _active : boolean = false;
	protected _active_start : number = 0;
	protected _content;
	protected _steps : {class:string, node: HTMLElement}[] = [];
	protected _activeCalibrationInterval : any = 0;
	protected _calibrationIsDone: boolean = false;

	private __runningTimeoutId : number = 0;

	static readonly MODE_CALIBRATION = 'calibration';
	static readonly MODE_RUNNING = 'running';

	/**
	 * Constructor
	 */
	constructor(_parent, _attrs? : WidgetConfig, _child? : object)
	{
		// Call the inherited constructor
		super(_parent, _attrs, ClassWithAttributes.extendAttributes(et2_smallpart_cl_measurement_L._attributes, _child || {}));

		this._content = this.getInstanceManager().widgetContainer.getArrayMgr('content');

		// Only run this if the course is running in CML mode.
		if ((this._content.getEntry('course_options') & et2_smallpart_videobar.course_options_cognitive_load_measurement)
			!= et2_smallpart_videobar.course_options_cognitive_load_measurement)
		{
			return;
		}

		// widgte div wrapper
		this.div = document.createElement('div');
		this.div.classList.add('smallpart-cl-measurement-L');

		this.l_button = <et2_button> et2_createWidget('buttononly',{label:egw.lang('L')}, this);

		// bind keydown event handler
		document.addEventListener('keydown', this._keyDownHandler.bind(this));

		this._steps = this.options.steps_className.split(',').map(_class=>{return {class:_class, node:null}});

		this.checkCalibration().then(_=>{this._calibrationIsDone = true;}, _=>{this._calibrationIsDone = false;})
		this.setDOMNode(this.div);
	}

	set_mode(value)
	{
		this._mode = value;
	}

	set_active(value)
	{
		this._active = value;
		if (this._active)
		{
			this.div.classList.add('active');
			this._active_start = Date.now();
			setTimeout(_=>{
				this.set_active(false);
			}, this._mode == et2_smallpart_cl_measurement_L.MODE_CALIBRATION ? 1000 : this.options.activation_period);
		}
		else
		{
			this._active_start = 0;
			this.div.classList.remove('active');
		}
	}

	public start()
	{
		return new Promise((_resolve) => {
			let activeInervalCounter = 1;
			clearInterval(this._activeCalibrationInterval);
			this._steps.forEach(step =>{
				step.node = (<HTMLElement>document.getElementsByClassName(step.class)[0]);
			});

			if (this._mode === et2_smallpart_cl_measurement_L.MODE_CALIBRATION && this._calibrationIsDone)
			{
				this._mode = et2_smallpart_cl_measurement_L.MODE_RUNNING;
			}

			switch(this._mode)
			{
				case et2_smallpart_cl_measurement_L.MODE_CALIBRATION:
					this._steps.forEach(_step =>{
						_step.node.style.visibility = 'hidden';
					});
					let index = 0;
					this._activeCalibrationInterval = setInterval(_ => {
						if ((activeInervalCounter/4)%1 != 0) this.set_active(true)

						if ((activeInervalCounter/4)%1 == 0)
						{
							this._steps[index].node.style.visibility = 'visible';
							index++;
						}

						if (activeInervalCounter >= 4 * this._steps.length)
						{
							clearInterval(this._activeCalibrationInterval);
							this._calibrationIsDone = true;
							et2_dialog.show_dialog(_=>{
								_resolve();
							}, 'Calibration procedure is finished. After pressing "Ok" the actual test will start.', 'Cognitive Measurement Load Learning Calibration', null, et2_dialog.BUTTONS_OK, et2_dialog.INFORMATION_MESSAGE);
						}
						activeInervalCounter++;
					}, (Math.floor(0.9 * 6))*1000);
					break;
				case et2_smallpart_cl_measurement_L.MODE_RUNNING:
					this.__runningTimeoutId = window.setTimeout(_=>{
							this.set_active(true);
							this.start();
						},
						((this.options.running_interval*60)+((Math.round(Math.random()) * 2 - 1) * this.options.running_interval_range))*1000)
					_resolve();
					break;
			}
		});

	}

	public stop()
	{
		clearTimeout(this.__runningTimeoutId);
	}

	/**
	 * Check if calibration is done
	 * @protected
	 */
	public checkCalibration()
	{
		return new Promise((_resolve, _reject) => {
			//don't ask server if the calibration is already done.
			if (this._calibrationIsDone)
			{
				_resolve();
				return;
			}

			this.egw().json('smallpart.\\EGroupware\\SmallParT\\Student\\Ui.ajax_readCLMeasurement', [
				this._content.getEntry('video')['course_id'], this._content.getEntry('video')['video_id'],
				smallpartApp.CLM_TYPE_LEARNING, egw.user('account_id')
			], (_records) => {
				let resolved = false;
				if (_records)
				{
					_records.forEach(_record => {
						const data = JSON.parse(_record.cl_data)[0];
						if (data.mode && data.mode === et2_smallpart_cl_measurement_L.MODE_CALIBRATION) resolved = true;
					});
				}
				if (resolved)
				{
					_resolve();
				}
				else
				{
					_reject();
				}
			}).sendRequest();
		});
	}

	protected _keyDownHandler(_ev)
	{
		if (_ev.key === 'Control' && this._active)
		{
			const end = Date.now() - this._active_start;

			this.egw().json('smallpart.\\EGroupware\\SmallParT\\Student\\Ui.ajax_recordCLMeasurement', [
				this._content.getEntry('video')['course_id'], this._content.getEntry('video')['video_id'],
				smallpartApp.CLM_TYPE_LEARNING, [{mode:this._mode, time: end/1000}]
			]).sendRequest();
		}
	}
}
et2_register_widget(et2_smallpart_cl_measurement_L, ["smallpart-cl-measurement-L"]);