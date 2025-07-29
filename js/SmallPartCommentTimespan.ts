/*
 * SmallPart Comment Timespan widget
 *
 * @license https://opensource.org/licenses/gpl-license.php GPL - GNU General Public License
 * @package smallpart
 * @subpackage etemplate
 * @link https://www.egroupware.org
 * @author Hadi Nategh
 */

import {css, html, LitElement, nothing, TemplateResult} from "lit";
import {et2_smallpart_videobar} from "./et2_widget_videobar";
import {Et2DateDuration} from "../../api/js/etemplate/Et2Date/Et2DateDuration";
import {Et2Button} from "../../api/js/etemplate/Et2Button/Et2Button";
import {Et2InputWidget} from "../../api/js/etemplate/Et2InputWidget/Et2InputWidget";
import {SlAnimation} from "@shoelace-style/shoelace";
import {property} from "lit/decorators/property.js";

/**
 *
 *
 */
export class SmallPartCommentTimespan extends Et2InputWidget(LitElement)
{
	static get styles()
	{
		return [
			...super.styles,
			css`
				div {
					position: relative;
				}

				et2-vbox::part(base) {
					row-gap: 0;
				}

				et2-hbox::part(base) {
					align-items: center;
				}

				et2-date-duration, et2-date-duration_ro {
					flex-grow: 1;
				}

				et2-date-duration_ro {
					padding: var(--sl-spacing-x-small);
					text-align: right;
				}
			`
		];
	}

	@property({type: Number})
	starttime : number;

	@property({type: Number})
	stoptime : number;

	@property({type: String})

	protected _videobar : et2_smallpart_videobar;


	constructor()
	{
		super();
		this.handleDropdownClick = this.handleDropdownClick.bind(this);
		this.handleTimepickerClick = this.handleTimepickerClick.bind(this);
	}

	willUpdate(changedProperties : Map<string, any>)
	{
		// Check start < stop if times change
		if(this.starttime && this.stoptime && (changedProperties.has('starttime') || changedProperties.has('stoptime')))
		{
			this._checkTimeConflicts();
		}
	}

	public timePicker(type : "starttime" | "stoptime" = "starttime", time : null | number = null)
	{
		const currentTime = time == null ? Math.round(this._videobar.currentTime()) : time;
		if (typeof this.stoptime === "undefined")
		{
			this.stoptime = this.getStoptime().value;
		}
		if(type == 'starttime')
		{
			this.starttime = currentTime;
			if(currentTime > this.stoptime)
			{
				this.stoptime = currentTime;
				(<SlAnimation>this.getStoptime().closest('sl-animation')).play = true;
			}
		}
		else if(type == 'stoptime' && Math.abs(currentTime - this.starttime) < 1)
		{
			this.stoptime = this.starttime;
		}
		else if(type == 'stoptime' && currentTime > this.starttime)
		{
			this.stoptime = currentTime;
		}
		else
		{
			(<SlAnimation>this.getStoptime().closest('sl-animation')).play = true;
		}
	}

	handleChange(event)
	{
		this._checkTimeConflicts();
	}

	/**
	 * Clicked save in dropdown, update value
	 * @param event
	 */
	handleDropdownClick(event)
	{
		if(!(event.target instanceof Et2Button))
		{
			return;
		}
		event.stopPropagation();

		const dropdown = event.target.closest("et2-dropdown");
		const duration = dropdown.querySelector("et2-date-duration");
		const old_value = this[duration.dom_id];
		if(event.target.id == "save")
		{
			this[duration.dom_id] = duration.value;
		}
		duration.value = this[duration.dom_id] ?? this.starttime ?? 0;
		this.requestUpdate(duration.dom_id, old_value);
		dropdown.hide();
	}

	/**
	 * time picker button click handler
	 * @param _type
	 * @param _event
	 * @private
	 */
	handleTimepickerClick(_event)
	{
		const _type = _event.target.getAttribute("name");
		this.timePicker(_type);
	}

	/**
	 * Show one picker, start or stop
	 *
	 * Handles associated edit dropdown
	 *
	 * @param name
	 * @param value
	 * @param {string} icon
	 * @param {any} max
	 * @return {TemplateResult<1>}
	 * @protected
	 */
	protected _pickerTemplate(name, value, icon = "clock-history", max = undefined)
	{
		const clock = (this.disabled || this.readonly) ?
					  html`
                          <et2-image part="button" src="${icon}" class="${name}"></et2-image>` :
					  html`
                        <et2-button-icon
                                  part="button"
                                  statustext="${name} picker"
                                  class="${name}"
                                  name="${name}"
                                .noSubmit=${true}
                                  image="${icon}"
                                  @click=${this.handleTimepickerClick}
                          >
                          </et2-button-icon>`;

		return html`
            ${clock}
            <et2-date-duration_ro
                    part="duration"
                    class=${name}
                    displayFormat="hms" dataFormat="s" emptyNot0
                    .value=${parseInt(value)}
            >
                ${(this.disabled || this.readonly) ? nothing : html`
                    <et2-dropdown slot="suffix">
                        <et2-image slot="trigger" src="edit"></et2-image>
                            <et2-date-duration
                                part="duration"
                                id="${name}"
                                    displayFormat="hms"
                                    dataFormat="s"
                                class="${name}"
                                ?max=${max}
                                    .selectUnit=${false}
                                .value=${parseInt(value) || 0}
                                @change=${name == "stoptime" ? this.handleStopChange : nothing}
                            ></et2-date-duration>
                        <et2-hbox>
                            <et2-button id="save" label=${this.egw().lang("save")} image="save" noSubmit
                                        @click=${this.handleDropdownClick}></et2-button>
                            <et2-button id="cancel" label=${this.egw().lang("cancel")} image="cancel" noSubmit
                                        @click="${this.handleDropdownClick}"></et2-button>
                        </et2-hbox>
                    </et2-dropdown>`
                }
            </et2-date-duration_ro>`
	}

	public render() : TemplateResult
	{
		// This shows loading template until loadingPromise resolves, then shows _listTemplate
		return html`
            <et2-vbox
                    @change=${this.handleChange}
            >
                <et2-description label="Start"></et2-description>
                <et2-hbox>
                    ${this._pickerTemplate("starttime", this.starttime, "clock", this._videobar?.duration())}
                </et2-hbox>
                <et2-description label="End"></et2-description>
                <et2-hbox>
                    <sl-animation name="flash" iterations="1">
                        ${this._pickerTemplate("stoptime", this.stoptime ?? this.starttime, "clock-history", this._videobar?.duration())}
                    </sl-animation>
                </et2-hbox>
                <div>
                    <slot name="feedback"></slot>
                </div>
            </et2-vbox>
		`;
	}

	/**
	 * @return Et2DateDuration
	 */
	getStarttime()
	{
		return <Et2DateDuration>this.shadowRoot.querySelector('et2-date-duration.starttime') ?? this.shadowRoot.querySelector('et2-date-duration_ro.starttime');
	}

	/**
	 * @return Et2DateDuration
	 */
	getStoptime()
	{
		return <Et2DateDuration>this.shadowRoot.querySelector('et2-date-duration.stoptime') ?? this.shadowRoot.querySelector('et2-date-duration_ro.stoptime');
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
	@property({type: String})
	set videobar(_id_or_widget: string|et2_smallpart_videobar)
	{
		if (typeof _id_or_widget === 'string') {
			_id_or_widget = <et2_smallpart_videobar>this.getRoot().getWidgetById(_id_or_widget);
		}
		if (typeof _id_or_widget !== 'string' && _id_or_widget ) {
			this._videobar = _id_or_widget;
		}
	}

	get videobar()
	{
		return this._videobar;
	}

	/**
	 * Re-evaluate starttime/stoptime values, show error startime > stoptime
	 *
	 * @param _node
	 * @param _widget
	 */
	private _checkTimeConflicts()
	{
		if(this.readonly || this.disabled)
		{
			return;
		}

		// Start has to be less than stop
		this.set_validation_error(false);
		this._messagesHeldWhileFocused = [];
		this.updateComplete.then(() =>
		{
			if(this.starttime > this.stoptime)
			{
				(<SlAnimation>this.getStoptime().closest('sl-animation')).play = true;
				this.set_validation_error(this.egw().lang("starttime has to be before endtime !!!"));
				this.validate();
			}
		});
	}
}

customElements.define("smallpart-comment-timespan", SmallPartCommentTimespan);