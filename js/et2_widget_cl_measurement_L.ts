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
			default: 1000
		},
		steps_className: {
			name: 'steps classname',
			type: 'string',
			description: 'comma separated css class name for defining (hide/show) steps. (steps are based on orders)',
			default: ''
		}
	};

	div : HTMLDivElement = null;
	l_button : et2_button = null;
	protected _mode : string = 'calibration';
	protected _active : boolean = false;
	protected _active_start : number = 0;
	protected _content;
	protected _steps : string[] = [];
	protected _stepsDOM : HTMLElement[] = [];
	protected _activeInterval : any = 0;
	protected _activeInervalCounter : number = 0;
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

		this._steps = this.options.steps_className.split(',');
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
			}, this.options.activation_period);
		}
		else
		{
			this._active_start = 0;
			this.div.classList.remove('active');
		}
	}

	public start()
	{
		this._activeInervalCounter = 0;
		clearInterval(this._activeInterval);
		this._steps.forEach(className =>{
			this._stepsDOM.push(<HTMLElement>document.getElementsByClassName(className)[0]);
		});
		switch(this._mode)
		{
			case 'calibration':
				this._stepsDOM.forEach(_node =>{
					_node.style.visibility = 'hidden';
				});
				this._activeInterval = setInterval(_ => {
					if (this._activeInervalCounter <= 3)
					{
						this._stepsDOM[this._activeInervalCounter].style.visibility = 'visible';
						this.set_active(true);
					}
					else
					{
						clearInterval(this._activeInterval);
					}
					this._activeInervalCounter++;
				}, (10+Math.floor(0.9 * 6))*1000);
				break;
			case 'running':
				this.set_active(true);
				break;
		}

	}
	protected _keyDownHandler(_ev)
	{
		if (_ev.key === 'Control' && this._active)
		{
			const end = Date.now() - this._active_start;

			this.egw().json('smallpart.\\EGroupware\\SmallParT\\Student\\Ui.ajax_recordCLMeasurement', [
				this._content.getEntry('video')['course_id'], this._content.getEntry('video')['video_id'],
				smallpartApp.CLM_TYPE_LEARNING, [end/1000]
			]).sendRequest();
		}
	}
}
et2_register_widget(et2_smallpart_cl_measurement_L, ["smallpart-cl-measurement-L"]);