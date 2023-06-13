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
import {Et2InputWidget} from "../../api/js/etemplate/Et2InputWidget/Et2InputWidget";
import {et2_smallpart_videobar} from "./et2_widget_videobar";
import {Et2DateDuration} from "../../api/js/etemplate/Et2Date/Et2DateDuration";
import {Et2Button} from "../../api/js/etemplate/Et2Button/Et2Button";

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

	public render() : TemplateResult
	{
		// This shows loading template until loadingPromise resolves, then shows _listTemplate
		return html`
            <div>
                <et2-date-duration displayFormat="hms" dataFormat="s" class="starttime"></et2-date-duration>
				<et2-button statustext="start-time picker" class="starttime glyphicon glyphicon-pushpin"></et2-button>
                <et2-date-duration displayFormat="hms" dataFormat="s" class="stoptime"></et2-date-duration>
                <et2-button statustext="stop-time picker" class="stoptime glyphicon glyphicon-pushpin"></et2-button>
            </div>
		`;
	}

	public connectedCallback()
	{
		super.connectedCallback();
		this.widgets = {
			starttime: this.getStarttime(),
			starttimePicker: this.getStarttimePicker(),
			stoptime: this.getStoptime(),
			stoptimePicker: this.getStoptimePicker()
		};

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
	 * @param _node
	 * @param _widget
	 * @private
	 */
	private _timePicker(_node, _widget)
	{
		if (_widget == this.widgets.starttimePicker)
		{
			this.widgets.starttime.value = Math.round(this.videobar.currentTime()).toString();
		}
		else
		{
			this.widgets.stoptime.value = Math.round(this.videobar.currentTime()).toString();
		}
	}
}

customElements.define("smallpart-comment-timespan", SmallPartCommentTimespan);