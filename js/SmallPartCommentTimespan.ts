/*
 * SmallPart Comment Timespan widget
 *
 * @license https://opensource.org/licenses/gpl-license.php GPL - GNU General Public License
 * @package smallpart
 * @subpackage etemplate
 * @link https://www.egroupware.org
 * @author Hadi Nategh
 */

import {css, html, LitElement, TemplateResult, until} from "@lion/core";
import {et2_smallpart_videobar} from "./et2_widget_videobar";
import {Et2DateDuration} from "../../api/js/etemplate/Et2Date/Et2DateDuration";
import {Et2Button} from "../../api/js/etemplate/Et2Button/Et2Button";
import {Et2Widget} from "../../api/js/etemplate/Et2Widget/Et2Widget";

/**
 *
 *
 */
export class SmallPartCommentTimespan extends Et2Widget(LitElement)
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

	constructor(...args : any[])
	{
		super(...args);

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
		this.widgets.starttime.value = this.options.starttime;
		this.widgets.stoptime.value = this.options.stoptime;
	}

	public render() : TemplateResult
	{
		// This shows loading template until loadingPromise resolves, then shows _listTemplate
		return html`
            <et2-hbox>
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
                <et2-date-duration 
						displayFormat="hms"
						dataFormat="s" 
						class="stoptime" 
						.selectUnit=${false}
						@change=${this._checkTimeConflicts}
				></et2-date-duration>
                <et2-button-icon 
						statustext="stop-time picker"
						class="stoptime"
                        .noSubmit=${true}
                        image="align-end"
						@click=${this._timePicker.bind(this, 'stoptime')}
				></et2-button-icon>
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
			this._videobar.video[0].addEventListener("et2_video.onReady." + this._videobar.id, _ => {
				this._set_widgets();
			});
		}

	}


	private _set_widgets()
	{

	}

	/**
	 * Re-evaluate starttime/stoptime max&min values
	 * @param _node
	 * @param _widget
	 */
	private _checkTimeConflicts(_node, _widget)
	{


	}

	/**
	 * time picker button click handler
	 * @param _type
	 * @param _event
	 * @private
	 */
	private _timePicker(_type, _event)
	{
		debugger;
		if (_type == 'starttime')
		{
			this.widgets.starttime.value = Math.round(this._videobar.currentTime()).toString();
		}
		else
		{
			this.widgets.stoptime.value = Math.round(this._videobar.currentTime()).toString();
		}
	}
}

customElements.define("smallpart-comment-timespan", SmallPartCommentTimespan);