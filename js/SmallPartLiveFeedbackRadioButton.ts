/*
 * SmallPart LiveFeedback Radio Button
 *
 * @license https://opensource.org/licenses/gpl-license.php GPL - GNU General Public License
 * @package smallpart
 * @subpackage etemplate
 * @link https://www.egroupware.org
 * @author Hadi Nategh
 */

import shoelace from "../../api/js/etemplate/Styles/shoelace";
import {css, html, TemplateResult} from "lit";
import {Et2WidgetWithSelectMixin} from "../../api/js/etemplate/Et2Select/Et2WidgetWithSelectMixin";
import {SelectOption} from "../../api/js/etemplate/Et2Select/FindSelectOptions";
import {SlRadioGroup} from "@shoelace-style/shoelace";

/**
 *
 */
export class SmallPartLiveFeedbackRadioButton extends Et2WidgetWithSelectMixin(SlRadioGroup)
{
	static get styles()
	{
		return [
			super.styles,
			shoelace, css`
			:host {
			  width: 100%;
				max-width: 10em;
			  display: inherit;
			}

				::part(button-group) {
					width: 100%;
					max-width: 10em;
			}

				::part(button-group__base) {
					flex-wrap: wrap;
					flex-direction: column;
				}

				sl-radio-button {
					flex: 1 1 auto;
				}

				/* It's way easier to target the icons here due to the shadowRoot nesting */

				::part(button) {
					border-color: transparent;
				}

				sl-icon {
					min-width: 5em;
					min-height: 5em;
				}

			`];
	}

	static get properties()
	{
		return {
			...super.properties,
			parentId: {type: String},
			onlyLiveFeedback: {type: Boolean}
		}
	}

	constructor(...args : any[])
	{
		super(...args);
		this.size = egwIsMobile() ? 'large' : 'medium';
	}

	connectedCallback()
	{
		super.connectedCallback();
		this.select_options = this.select_options.length>0 ? this.select_options : this._getOptions();
	}


	protected _getOptions()
	{
		let options = this.getInstanceManager().widgetContainer.getArrayMgr('sel_options').getEntry('catsOptions');
		return  options.filter(_item=> {
			if (this.onlyLiveFeedback)
			{
				return _item.parent_id == this.options.parentId && _item?.data?.type == 'lf';
			}
			return _item.parent_id == this.options.parentId;
		})
			// Design requirement: max 3 options shown, only the first 3
			.slice(0, 3);
	}

	render() : TemplateResult
	{
		return html`
            <sl-radio-group
                    exportparts=""
                    label=${this.label} @sl-change=${this._handleChange} value=${this.value}
            >
                ${(this.select_options || []).map((option : SelectOption) => this._optionTemplate(option))}
			</sl-radio-group>
		`;
	}

	protected _optionTemplate(_option)
	{
		let icon = _option.icon;
		if(!icon)
		{
			switch(_option?.data?.value)
			{
				case 'p':
					icon = 'hand-thumbs-up';
					break;
				case 'n':
					icon = 'hand-thumbs-down';
					break;
				default:
					icon = 'hand-index-thumb';
					break;
			}
		}
		return html`
            <sl-radio-button
                    exportparts="label:radio__label, prefix:radio__icon"
                    value=${_option.value} size=${this.size} title=${_option.label}>
                <sl-icon slot="prefix" part="icon" name="${icon}" label=${_option.label}
                         style="color: ${_option.color}"></sl-icon>
                ${_option.label}
            </sl-radio-button>
		`;
	}

	get value() : string
	{
		return this._value;
	}

	set value(new_value)
	{
		let oldValue = this.value;
		this._value = new_value;
		this.requestUpdate("value", oldValue);
	}

	protected _handleChange(ev)
	{
		this._value= ev.currentTarget.value;
		// Trigger a change event
		this.dispatchEvent(new Event("change"));
	}
}

customElements.define("smallpart-lf-radioButton", SmallPartLiveFeedbackRadioButton);