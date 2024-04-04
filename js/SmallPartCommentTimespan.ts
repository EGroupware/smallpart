/*
 * SmallPart Comment Timespan widget
 *
 * @license https://opensource.org/licenses/gpl-license.php GPL - GNU General Public License
 * @package smallpart
 * @subpackage etemplate
 * @link https://www.egroupware.org
 * @author Hadi Nategh
 */

import {css, html, LitElement, TemplateResult} from "lit";
import {et2_smallpart_videobar} from "./et2_widget_videobar";
import {Et2DateDuration} from "../../api/js/etemplate/Et2Date/Et2DateDuration";
import {Et2Button} from "../../api/js/etemplate/Et2Button/Et2Button";
import {Et2InputWidget} from "../../api/js/etemplate/Et2InputWidget/Et2InputWidget";
import {SlAnimation} from "@shoelace-style/shoelace";

/**
 *
 *
 */
export class SmallPartCommentTimespan extends Et2InputWidget(LitElement)
{
	protected widgets : {
		starttime: Et2DateDuration,
		stoptime: Et2DateDuration,
		starttimePicker: Et2Button,
		stoptimePicker: Et2Button
	} = {starttime:null, stoptime:null, starttimePicker:null, stoptimePicker: null};

	protected _videobar: et2_smallpart_videobar;

	static get styles()
	{
		return [
			...super.styles,
			css`
				div {
					position: relative;
				}
			`
		];
	}

	static get properties()
	{
		return {
			...super.properties,
			/**
			 * comment starttime
			 */
			starttime: {
				type: Number,
			},
			/**
			 * comment stoptime
			 */
			stoptime: {
				type: Number,
			},
			/**
			 * videobar this overlay is for
			 */
			videobar: {
				type: String
			}
		}
	}

	/**
	 *
	 */
	firstUpdated()
	{
		super.firstUpdated();
		//the dom is ready get the widgets
		this.widgets = {
			starttime: this.getStarttime(),
			starttimePicker: this.getStarttimePicker(),
			stoptime: this.getStoptime(),
			stoptimePicker: this.getStoptimePicker()
		};
		this.widgets.starttime.value = this.starttime;
		this.widgets.stoptime.value = this.stoptime ?? this.starttime;
		this.widgets.starttime.max = this._videobar.duration();
		this.widgets.stoptime.max = this._videobar.duration();
	}

	handleChange(event)
	{

		// Start has to be less than stop
		this.set_validation_error(false);
		this._messagesHeldWhileFocused = [];
		this.updateComplete.then(() =>
		{
			if(parseInt(this.widgets.starttime.value) > parseInt(this.widgets.stoptime.value))
			{
				this.set_validation_error(this.egw().lang("starttime has to be before endtime !!!"));
				this.validate();
			}
		});
	}

	public render() : TemplateResult
	{
		// This shows loading template until loadingPromise resolves, then shows _listTemplate
		return html`
            <et2-hbox
                    @change=${this.handleChange}
            >
                <et2-date-duration 
						displayFormat="hms" 
						dataFormat="s" 
						class="starttime"
						.selectUnit=${false}>
				</et2-date-duration>
				<et2-button-icon
						statustext="start-time picker"
						class="starttime"
                        .noSubmit=${true}
						image="align-start"
						@click=${this._timePicker.bind(this, 'starttime')}>
				</et2-button-icon>
                <sl-animation name="flash" iterations="1">
                <et2-date-duration 
						displayFormat="hms"
						dataFormat="s" 
						class="stoptime" 
						.selectUnit=${false}
						@change=${this._checkTimeConflicts}
				></et2-date-duration>
                </sl-animation>
                <et2-button-icon 
						statustext="stop-time picker"
						class="stoptime"
                        .noSubmit=${true}
                        image="align-end"
						@click=${this._timePicker.bind(this, 'stoptime')}
				></et2-button-icon>
                <div>
                    <slot name="feedback"></slot>
                </div>
            </et2-hbox>
		`;
	}

	/**
	 * @return Et2DateDuration
	 */
	getStarttime()
	{
		return <Et2DateDuration> this.shadowRoot.querySelector('et2-date-duration.starttime');
	}

	/**
	 * @return Et2DateDuration
	 */
	getStoptime()
	{
		return <Et2DateDuration> this.shadowRoot.querySelector('et2-date-duration.stoptime');
	}

	/**
	 * @return @Et2Button
	 */
	getStarttimePicker()
	{
		return <Et2Button> this.shadowRoot.querySelector('et2-button.starttime');
	}

	/**
	 * @return Et2Button
	 */
	getStoptimePicker()
	{
		return <Et2Button> this.shadowRoot.querySelector('et2-button.stoptime');
	}

	/**
	 * Set videobar to use
	 *
	 * @param _id_or_widget
	 */
	set videobar(_id_or_widget: string|et2_smallpart_videobar)
	{
		if (typeof _id_or_widget === 'string') {
			_id_or_widget = <et2_smallpart_videobar>this.getRoot().getWidgetById(_id_or_widget);
		}
		if (typeof _id_or_widget !== 'string' && _id_or_widget ) {
			this._videobar = _id_or_widget;
		}
	}

	/**
	 * Re-evaluate starttime/stoptime max&min values
	 * @param _node
	 * @param _widget
	 */
	private _checkTimeConflicts(event)
	{
		const _widget = event.target;

		if (_widget == this.widgets.starttime)
		{
			this.widgets.starttime.max = this.widgets.stoptime.value;
			if (this.widgets.starttime.value < this.widgets.stoptime.value) this.widgets.stoptime.min = this.widgets.starttime.value;
		}
		else
		{
			this.widgets.stoptime.min = this.widgets.starttime.value;
			this.widgets.starttime.max = _widget.value;
		}
	}

	/**
	 * time picker button click handler
	 * @param _type
	 * @param _event
	 * @private
	 */
	private _timePicker(_type, _event)
	{
		const currentTime = Math.round(this._videobar.currentTime());
		if(_type == 'starttime')
		{
			this.widgets.starttime.value = currentTime.toString();
			if(currentTime > parseInt(this.widgets.stoptime.value))
			{
				this.widgets.stoptime.value = currentTime.toString();
				this.widgets.stoptime.requestUpdate();
				(<SlAnimation>this.widgets.stoptime.parentElement).play = true;
			}
		}
		else if(_type == 'stoptime' && currentTime > parseInt(this.widgets.starttime.value))
		{
			this.widgets.stoptime.value = currentTime.toString();
		}
	}
}

customElements.define("smallpart-comment-timespan", SmallPartCommentTimespan);