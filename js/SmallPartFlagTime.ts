import {css, html, LitElement} from "lit";
import {Et2Widget} from "../../api/js/etemplate/Et2Widget/Et2Widget";
import {state} from "lit/decorators/state.js";
import {asyncReplace} from "lit/directives/async-replace.js"
import {classMap} from "lit/directives/class-map.js";
import shoelace from "../../api/js/etemplate/Styles/shoelace";

/**
 * Time flag widget marks a time, and provides controls for clearing the marked time
 *
 */
export class SmallPartFlagTime extends Et2Widget(LitElement)
{
	@state() markedTime = null;
	@state() timer;

	// setTimeout
	private _clearTimer = null;
	private clearDelay : number;

	static get styles()
	{

		return [
			shoelace,
			super.styles,
			css`
				:host {
					max-width: fit-content;
				}

				et2-button {
					max-width: fit-content;
				}

				et2-button::part(label), et2-date-duration_ro {
				}

				et2-button::part(label) {
					display: flex;
					flex-direction: row;
					flex-wrap: nowrap;
					align-items: center;
				}

				et2-button-icon::part(base) {
					padding: var(--sl-spacing-small);
					padding-top: 0px;
				}

				.hasValue et2-button-icon[slot="prefix"] {
					color: var(--sl-color-primary-500);
				}

			`];
	}

	public get value()
	{
		return this.markedTime;
	}

	protected handleClearClick(e)
	{
		e.preventDefault();
		e.stopImmediatePropagation();
		this.clearMark(0);
	}

	public markTime(seconds, clearTimer = 0)
	{
		this.clearDelay = clearTimer;
		this.cancelClear();
		this.markedTime = seconds;
		this.requestUpdate();
	}

	public clearMark(delay = this.clearDelay)
	{
		this.timer = countDown(delay)
		if(this._clearTimer)
		{
			clearTimeout(this._clearTimer);
		}
		this._clearTimer = setTimeout(() =>
		{
			this.markedTime = null;
			this.timer = null;
			this.requestUpdate();
			this.updateComplete.then(() =>
			{
				this.dispatchEvent(new Event("clear", {bubbles: true}));
			});
		}, delay * 1000);
		this.requestUpdate("timer");
	}

	public cancelClear()
	{
		if(this._clearTimer)
		{
			clearTimeout(this._clearTimer);
		}
		this.timer = null;
	}

	render()
	{
		const hasValue = (this.markedTime != null)
		return html`
            <et2-button id="flag"
                        noSubmit="true"
                        exportparts="label"
                        class=${classMap({
                            "smallpart-time-flag": true,
                            "hasValue": hasValue
                        })}
                        title=${this.egw().lang(hasValue ? "Shows the reserved time, click on it to end the reservation" : "Reserves the time and allows reflection time")}
            >
                <et2-button-icon name="stopwatch" noSubmit="true" slot="prefix"></et2-button-icon>
                ${hasValue ? html`
                    <et2-date-duration_ro dataFormat="s" displayFormat="hms"
                                          .value=${this.markedTime}></et2-date-duration_ro>
                    <et2-button-icon id="clear_flag"
                                     image=${this.timer ? asyncReplace(this.timer) : "x"}
                                     @click=${this.handleClearClick}
                    >
                    </et2-button-icon>
                ` : this.egw().lang("Flag")}
            </et2-button>
		`;
	}
}

async function* countDown(count : number)
{
	while(count > 0)
	{
		yield count < 10 ? `${count}-circle` : "x-circle";
		count--;
		await new Promise((r) => setTimeout(r, 1000));
	}
	// Should be stopped by now
	yield "x";
}

customElements.define("smallpart-flag-time", SmallPartFlagTime);