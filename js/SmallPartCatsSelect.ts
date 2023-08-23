/*
 * SmallPart cats select widget
 *
 * @license https://opensource.org/licenses/gpl-license.php GPL - GNU General Public License
 * @package smallpart
 * @subpackage etemplate
 * @link https://www.egroupware.org
 * @author Hadi Nategh
 */

import {Et2Select} from "../../api/js/etemplate/Et2Select/Et2Select";
import {css, html, TemplateResult} from "@lion/core";
import {SelectOption} from "../../api/js/etemplate/Et2Select/FindSelectOptions";

/**
 *
 *
 */
export class SmallPartCatsSelect extends Et2Select
{
	static get styles()
	{
		return [
			...super.styles,
			css`
			/* Larger maximum height before scroll*/
			.select__tags {
				max-height: 10em;
			} 
			:host([readonly]) .select__control,
            :host([readonly]) .select__control:hover{
				background: transparent;
				border: none;
			}
			  
			`
		];
	}

	constructor(...args : any[])
	{
		super(...args);
		this.allowFreeEntries = false;
	}

	static get properties()
	{
		return {
			...super.properties,
			noSubs : {
				type: Boolean
			}
		}
	}

	connectedCallback()
	{
		super.connectedCallback();
		this.select_options = this._getOptions();
	}

	private _getOptions()
	{
		let options = this.getInstanceManager().widgetContainer.getArrayMgr('sel_options').getEntry('catsOptions');
		if (this.options.noSubs)
		{
			options = options.filter(_item=>{return !_item.parent_id;});
		}
		return options;
	}

	/**
	 * Builds option template
	 * @param option
	 */
	_optionTemplate(option : SelectOption) : TemplateResult
	{
		return html`
            <sl-menu-item value="${option.value}"
                          title="${!option.title || this.noLang ? option.title : this.egw().lang(option.title)}"
                          class="${option.class}" .option=${option}
                          ?disabled=${option.disabled}
						  style="border-left:6px solid ${option.color}">
                ${this.noLang ? option.label : this.egw().lang(option.label)}
			</sl-menu-item>`;
	}

	/**
	 * Builds Cat tag
	 *
	 * @param item
	 * @protected
	 */
	protected _createTagNode(item)
	{
		let tag = super._createTagNode(item);
		tag.style.setProperty('border-left', `6px solid ${item?.option?.color}`);
		return tag;
	}
}

customElements.define("smallpart-cats-select", SmallPartCatsSelect);