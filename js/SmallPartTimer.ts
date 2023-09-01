import {Et2Widget} from "../../api/js/etemplate/Et2Widget/Et2Widget";
import {css, html, LitElement, PropertyValues} from "@lion/core";
import shoelace from "../../api/js/etemplate/Styles/shoelace";


export interface StateType {
	id: string,
	paused?: boolean,
	timer?: number
}

/**
 *
 */
export class SmallPartTimer extends Et2Widget(LitElement)
{
	protected timerInterval : number = null;
	protected _timer : number = null;
	protected _state : StateType = {id:undefined, paused:undefined, timer: undefined};
	protected _appname : string = '';
	protected _stateUniqueId : string = '';
	static get styles()
	{
		return [
			super.styles,
			shoelace, css`
            :host {
              width: 100%;
              align-items: center;
              display: flex !important;
              justify-content: center;
            }
			:host::part(buttons) {
              width: 100px;
              height: 100px;
              font-size: 30px;
              border: 4px solid black;
              border-radius: 50%;
			}
			.btn_reset {
              background: #71b78d;
              color: white;
			}
			.btn_pause {
              background: #e56565;
              color: white;
			}
			:host::part(labels) {
			  font-size: 4em;
              max-width: min-content;
              max-height: fit-content;
			}
			
		`];
	}

	static get properties()
	{
		return {
			...super.properties,
			/**
			 * Set a unique id for timer session item incase we have many timers of in a same app
			 */
			uniqueId : {type: String},
			/**
			 * Defines display format; s (Initial letter) or l (Complete word) display, default is s.
			 */
			format : {type: String},
			/**
			 * Only displays none empty values.
			 */
			hideEmpties : {type: Boolean},
			/**
			 * Defines an alarm set when timer is reached to the alarm time, it should be in seconds
			 */
			alarm : {type: Number},
			/**
			 * Defines a callback to gets called at alarm - timer. This only will work if there's an alarm set.
			 */
			onAlarm : {type: Function},
			/**
			 * Callback function to call when the timer is resumed.
			 */
			onResume : {type: Function},
			/**
			 * Callback function to call when the timer is paused.
			 */
			onPause : {type: Function},
			/**
			 * Callback function to call when the timer is reset.
			 */
			onReset : {type: Function},
			/**
			 * Starts the time immediately.
			 */
			autoStart : {type: Boolean},
			/**
			 * Hide reset button.
			 */
			hideReset : {type: Boolean}
		}
	}

	constructor(...args: any[])
	{
		super(...args);
		this.format = 's';
		this.hideEmpties = true;
		this.autoStart = false;
		this.hideReset = false;
		this.uniqueId = "";
	}

	connectedCallback()
	{
		super.connectedCallback();
	}

	/**
	 * Clean up interval
	 */
	destroy()
	{
		clearInterval(this.timerInterval);
	}

	protected firstUpdated(_changedProperties: PropertyValues)
	{
		super.firstUpdated(_changedProperties);

		this._stateUniqueId = this.id + (this.uniqueId ? '-' + this.uniqueId : '');
		this._appname = this.getInstanceManager().app;
		let state = JSON.parse(this.egw().getSessionItem(this._appname, this._stateUniqueId));
		if (state)
		{
			this.value = state.timer;
			this.__displayTimer(state.timer);
		}
		this.state = state ?? {id: this._stateUniqueId, paused: !this.autoStart};
		this.timerInterval = setInterval(_ => {
			this._updateTimer();
		}, 1000);
	}

	render()
	{
		return html`
			<div ${this.id ? html`id="${this.id}"` : ''} class="et2_timer">
				<et2-hbox part="labels">
					<et2-description class="days" ?disabled=${this.hideEmpties ? this.days == 0 || !this.days : false}>
						${this.days+this._getIndicator("days")}
					</et2-description>
					<et2-description class="hours" ?disabled=${this.hideEmpties ? this.hours == 0 || !this.hours : false}>
						${this.hours+this._getIndicator("hours")}
					</et2-description>
					<et2-description class="minutes" ?disabled=${this.hideEmpties ? this.minutes == 0 || !this.minutes: false}>
						${this.minutes+this._getIndicator("minutes")}
					</et2-description>
					<et2-description class="seconds" ?disabled=${this.seconds == 0 || !this.seconds}>
						${this.seconds+this._getIndicator("seconds")}
					</et2-description>
				</et2-hbox>
				<et2-hbox part="buttons">
					<et2-button-icon image="play-circle" class="btn_resume" ?disabled=${!this.state?.paused}
								 @click=${this._resumeClick} statustext=${this.egw().lang('Start')}></et2-button-icon>
					<et2-button-icon image="pause-circle" class="btn_pause" ?disabled=${this.state?.paused}
									 @click=${this._pauseClick} statustext=${this.egw().lang('Stop')}></et2-button-icon>
					<et2-button-icon image="x-circle" class="btn_reset" ?style="display:${this.hideReset ? "none":""}" 
									 @click=${this._resetClick} statustext=${this.egw().lang('Reset')}></et2-button-icon>
				</et2-hbox>
            </div>
		`;
	}

	get value()
	{
		return this.timer;
	}

	set value(_time)
	{
		if (isNaN(_time)) return;

		this.timer = _time;
		this._state.timer = _time;
		this.state = this._state;
	}

	get timer()
	{
		return this._timer || 0;
	}

	set timer(_time)
	{
		this._timer = _time;
		this.__displayTimer(_time);
	}

	private _updateTimer()
	{
		if (this.state?.paused) return;
		this.timer++;
		this._state.timer = this.timer;
		this.state = this._state;
	}

	/**
	 *
	 * @param timer
	 * @private
	 */
	private __displayTimer(timer)
	{
		if (timer < 0) return 0;
		if (this.alarm > 0 && this.alarm == timer && typeof this.onAlarm == 'function')
		{
			this.onAlarm();
		}
		this.days = Math.floor(timer  / (60 * 60 * 24));
		this.hours = Math.floor((timer % (60 * 60 * 24)) / (60 * 60));
		this.minutes = Math.floor((timer % (60 * 60)) / 60);
		this.seconds = Math.floor((timer %  60));
		this.requestUpdate();
	}

	/**
	 *
	 * @param _v
	 * @private
	 */
	private _getIndicator(_v)
	{
		return this.format == 's' ? this.egw().lang(_v).substr(0,1) : this.egw().lang(_v);
	}

	/**
	 * get state
	 *
	 * @param key providing key would return the state value of the given key
	 * @private
	 * @return return whole state object or particular state key value
	 */
	get state()
	{
		return <StateType> this._state;
	}

	/**
	 * set state
	 *
	 * @param state set the whole state object or null if desire to set/modify only a key value
	 */
	set state(_state : StateType)
	{
		this._state = _state;

		this.egw().setSessionItem(this._appname, this._state.id, JSON.stringify(this._state));
	}

	/**
	 *
	 * @private
	 */
	private _resetClick()
	{
		this.egw().message('Timer cleared', 'warning');
		this.state.id = this._stateUniqueId;
		this.egw().removeSessionItem(this._appname, this.state?.id);
		this.value = 0;
		if (typeof this.onReset === 'function') this.onReset();
	}

	/**
	 *
	 * @private
	 */
	private _resumeClick()
	{
		// don't go further if the timer is not paused
		if (!this.state?.paused) return;

		let sessionState = JSON.parse(this.egw().getSessionItem(this._appname, this.state.id));

		this.value = this.state?.timer;

		// unpause the timer state
		this._state.paused = false;
		this.state = this._state;
		if (typeof this.onResume === 'function') this.onResume(this.state);
		this.requestUpdate();
	}

	/**
	 *
	 * @private
	 */
	private _pauseClick()
	{
		// don't go further if the timer is already paused
		if (this.state?.paused) return;
		this._state.paused = true;
		this.state = this._state;
		this.egw().setSessionItem(this._appname, this.state.id, JSON.stringify(this.state));
		if (typeof this.onPause === 'function') this.onPause(this.state);
		this.requestUpdate();
	}
}
customElements.define("smallpart-timer", SmallPartTimer);