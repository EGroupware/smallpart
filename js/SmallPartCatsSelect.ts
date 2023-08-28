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
	private __onlysubs : String;
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
			},
			onlySubs: {
				type: String
			},
			asColorTag: {
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
		if (this.onlySubs)
		{
			options = options.filter(_item=>{return _item.parent_id == this.options.onlySubs;});
		}
		return options;
	}

	set onlySubs(_parent_id)
	{
		this.__onlysubs = _parent_id?.toString()?.split(":")[0];
		this.select_options = this._getOptions();
		this.requestUpdate();
	}

	get onlySubs()
	{
		return this.__onlysubs;
	}

	set_value(_val)
	{
		let values = [];
		if (typeof _val == "string" && _val.toString().split(":").length>1)
		{
			values = _val.toString().split(':');
			if (values.indexOf('lf') > 0) values.splice(2,1);
			if (this.noSubs) values.splice(1,values.length);
			if (this.onlySubs) values.splice(0,1);
		}
		else
		{
			values = _val;
		}
		super.set_value(values);
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
		if (this.asColorTag && item?.option?.color)
		{
			tag = document.createElement('sl-icon');
			tag.name = "bookmark";
			tag.style.setProperty('color', `${item.option.color}`);
			tag.textContent=' ';
		}
		else
		{
			tag.style.setProperty('border-left', `6px solid ${item?.option?.color}`);
		}

		return tag;
	}
}

customElements.define("smallpart-cats-select", SmallPartCatsSelect);