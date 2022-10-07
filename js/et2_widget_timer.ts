/**
 * EGroupware SmallPART - timer
 *
 * @license http://opensource.org/licenses/gpl-license.php GPL - GNU General Public License
 * @package smallpart
 * @subpackage ui
 * @link https://www.egroupware.org
 * @author Hadi Nategh<hn@egroupware.org>
 */


import '/api/js/etemplate/et2_core_common';
import {ClassWithAttributes} from "../../api/js/etemplate/et2_core_inheritance";
import {et2_register_widget, WidgetConfig} from "../../api/js/etemplate/et2_core_widget";
import {et2_valueWidget} from "../../api/js/etemplate/et2_core_valueWidget";
import {et2_button} from "../../api/js/etemplate/et2_widget_button";
import {et2_createWidget} from "../../api/js/etemplate/et2_core_widget";

export interface StateType {
	id: string,
	paused?: boolean,
	timer?: number
}

/**
 * Class which implements the "achelper-timer" XET-Tag
 *
 */
export class et2_widget_timer extends et2_valueWidget
{
	static readonly _attributes: any = {
		uniqueid: {
			name: "uniqueid",
			type: "string",
			default: "",
			description: "Set a unique id for timer session item incase we have many timers of in a same app"
		},
		format: {
			name: "display format",
			type: "string",
			default: "s", // s or l
			description: "Defines display format; s (Initial letter) or l (Complete word) display, default is s."
		},
		onPause: {
			name: "on pause timer",
			type: "js",
			default: et2_no_init,
			description: "Callback function to call when the timer is paused."
		},
		onReset: {
			name: "on reset timer",
			type: "js",
			default: et2_no_init,
			description: "Callback function to call when the timer is reset."
		},
		onResume: {
			name: "on resume timer",
			type: "js",
			default: et2_no_init,
			description: "Callback function to call when the timer is resumed."
		},
		hideEmpties: {
			name: "hide empties",
			type: "string",
			default: true,
			description: "Only displays none empty values."
		},
		precision: {
			name: "how many counters to show",
			type: "integer",
			default: 0,	// =all
			description: "Limit number of counters, eg. 2 does not show minutes and seconds, if days are displayed"
		},
		alarm: {
			name: "alarm",
			type: "any",
			default: "",
			description: "Defines an alarm set when timer is reached to the alarm time, it should be in seconds"
		},
		onAlarm: {
			name: "alarm callback",
			type: "js",
			default: "",
			description: "Defines a callback to gets called at alarm - timer. This only will work if there's an alarm set."
		},
		autoStart: {
			name: "auto start",
			type: "string",
			default: false,
			description: "Starts the time immediately."
		},
		hideReset: {
			name: "hide reset button",
			type: "string",
			default: false,
			description: "Hide reset button."
		}
	};

	private timerInterval = null;
	private timer = null;
	private readonly container: JQuery = null;
	private days: JQuery = null;
	private hours: JQuery = null;
	private minutes: JQuery = null;
	private seconds: JQuery = null;

	private resetBtn: et2_button = null;
	private resumeBtn: et2_button = null;
	private pauseBtn: et2_button = null;

	protected _state: StateType = null;
	protected _state_unique_id: string = '';
	protected _appname : string = '';

	/**
	 * Constructor
	 */
	constructor(_parent, _attrs?: WidgetConfig, _child?: object)
	{
		// Call the inherited constructor
		super(_parent, _attrs, ClassWithAttributes.extendAttributes(et2_widget_timer._attributes, _child || {}));

		// Build countdown dom container
		this.container = jQuery(document.createElement("div"))
			.addClass("et2_timer");
		this.days = jQuery(document.createElement("span"))
			.addClass("et2_timer_days").appendTo(this.container);
		this.hours = jQuery(document.createElement("span"))
			.addClass("et2_timer_hours").appendTo(this.container);
		this.minutes = jQuery(document.createElement("span"))
			.addClass("et2_timer_minutes").appendTo(this.container);
		this.seconds = jQuery(document.createElement("span"))
			.addClass("et2_timer_seconds").appendTo(this.container);
		this.timerInterval = setInterval(_ => {
			this._updateTimer();
		}, 1000);



		this.resumeBtn = <et2_button> et2_createWidget('button', {
			statustext: this.egw().lang('Start livefeedback session'),
			label: this.egw().lang('Start'),
			onclick: this._resumeClicked.bind(this),
			class: 'btn_resume'
		},this);

		this.pauseBtn = <et2_button> et2_createWidget('button', {
			statustext: this.egw().lang('Stop livefeedback session'),
			label: this.egw().lang('Stop'),
			onclick: this._pauseClicked.bind(this),
			class: 'btn_pause'
		},this);

		this.resetBtn = <et2_button> et2_createWidget('button', {
			statustext: this.egw().lang('Reset'),
			onclick: this._resetClicked.bind(this),
			class: 'btn_clear_timer' + this.options.hideReset ? ' hideme' : ''
		},this);

		this._state_unique_id = this.id + '-' + this.options.uniqueid;
		this._appname = this.getInstanceManager().app;
		let state = JSON.parse(this.egw().getSessionItem(this._appname, this._state_unique_id));
		if (state)
		{
			this.set_value(state.timer);
			this.__displayTimer(state.timer);
		}
		this._set_state(state ?? {id: this._state_unique_id, paused: !this.options.autoStart});
		this.setDOMNode(this.container[0]);
	}
	/**
	 * Clean up interval
	 */
	destroy()
	{
		clearInterval(this.timerInterval);
	}

	public get_value()
	{
		return this.timer;
	}

	public set_value(_time)
	{
		if (isNaN(_time)) return;

		this.timer = _time;
	}


	private _resetClicked()
	{
		this.egw().message('Timer cleared', 'warning');
		this._set_state({id: this._state_unique_id});
		this.egw().removeSessionItem(this._appname, this._get_state('id'));
		this.set_value(0);
		if (typeof this.options.onReset === 'function') this.options.onReset();
	}

	private _resumeClicked()
	{
		// don't go further if the timer is not paused
		if (!this._get_state('paused')) return;

		let sessionState = JSON.parse(this.egw().getSessionItem(this._appname, this._get_state('id')));
		this._set_state(sessionState);
		this.egw().removeSessionItem(this._appname, this._get_state('id'));
		this.set_value(this._get_state('timer'));

		// unpause the timer state
		this._set_state(null, 'paused',  false);
		if (typeof this.options.onResume === 'function') this.options.onResume(this._get_state());
	}

	private _pauseClicked()
	{
		// don't go further if the timer is already paused
		if (this._get_state('paused')) return;
		this._set_state(null, 'paused',  true);
		this.egw().setSessionItem(this._appname, this._get_state().id, JSON.stringify(this._get_state()));
		if (typeof this.options.onPause === 'function') this.options.onPause(this._get_state());
	}

	/**
	 * get state
	 *
	 * @param key providing key would return the state value of the given key
	 * @private
	 * @return return whole state object or particular state key value
	 */
	private _get_state(key? : string)
	{
		return  key ? <any> this._state[key] : <StateType> this._state;
	}

	/**
	 * set state
	 *
	 * @param state set the whole state object or null if desire to set/modify only a key value
	 * @param key state key
	 * @param value value
	 * @private
	 */
	private _set_state(state? : StateType, key? : string, value? : any)
	{
		if (state)
		{
			this._state = state;
		}
		else if(key)
		{
			this._state[key] = value;
		}

		this._showHideButtons(this._state);

		this.egw().setSessionItem(this._appname, this._state.id, JSON.stringify(this._state));
	}

	private _showHideButtons(state?)
	{
		if (state && state.paused)
		{
			jQuery(this.pauseBtn.getDOMNode()).hide();
			jQuery(this.resumeBtn.getDOMNode()).show();
		}
		else
		{
			jQuery(this.pauseBtn.getDOMNode()).show();
			jQuery(this.resumeBtn.getDOMNode()).hide();
		}
	}

	private _updateTimer()
	{
		if (this._get_state('paused')) return;
		this.timer++;
		this._set_state(null, 'timer', this.timer);
		this.__displayTimer(this.timer);
	}

	private __displayTimer(timer)
	{
		if (timer < 0) return 0;
		if (this.options.alarm > 0 && this.options.alarm == timer && typeof this.options.onAlarm == 'function')
		{
			this.options.onAlarm();
		}
		let values = {
			days: Math.floor(timer  / (60 * 60 * 24)),
			hours: Math.floor((timer % (60 * 60 * 24)) / (60 * 60)),
			minutes: Math.floor((timer % (60 * 60)) / 60),
			secounds: Math.floor((timer %  60))
		};

		this.days.text(values.days+this._getIndicator("days"));
		this.hours.text(values.hours+this._getIndicator("hours"))
		this.minutes.text(values.minutes+this._getIndicator("minutes"));
		this.seconds.text(values.secounds+this._getIndicator("seconds"));

		if (this.options.hideEmpties)
		{
			if (values.days == 0)
			{
				this.days.hide();
				if(values.hours == 0)
				{
					this.hours.hide();
					if(values.minutes == 0)
					{
						this.minutes.hide();
						if(values.secounds == 0) this.seconds.hide();
					}
					else
					{
						this.minutes.show();
					}
				}
				else
				{
					this.hours.show();
				}
			}
			else
			{
				this.days.show();
			}
		}
		if (this.options.precision)
		{
			const units = ['days','hours','minutes','seconds'];
			for (let u=0; u < 4; ++u)
			{
				if (values[units[u]])
				{
					for(let n=u+this.options.precision; n < 4; n++)
					{
						this[units[n]].hide();
					}
					break;
				}
				else
				{
					this[units[u]].hide();
				}
			}
		}
	}

	private _getIndicator(_v)
	{
		return this.options.format == 's' ? this.egw().lang(_v).substr(0,1) : this.egw().lang(_v);
	}
}
et2_register_widget(et2_widget_timer, ["timer"]);
