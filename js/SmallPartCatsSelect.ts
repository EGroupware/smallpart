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
import {css, html, nothing, TemplateResult} from "lit";
import {Et2Tag} from "../../api/js/etemplate/Et2Select/Tag/Et2Tag";
import {SelectOption} from "../../api/js/etemplate/Et2Select/FindSelectOptions";
import {Et2StaticSelectMixin} from "../../api/js/etemplate/Et2Select/StaticOptions";

/**
 *
 *
 */
export class SmallPartCatsSelect extends Et2StaticSelectMixin(Et2Select)
{
	private __onlysubs : String;
	private static keepTag : Et2Tag;
	static get styles()
	{
		return [
			...super.styles,
			css`
			/* Larger maximum height before scroll*/
			.select__tags {
				max-height: 10em;
			}

				:host([readonly]) {
					.select__combobox, .select__combobox:hover {
						background: transparent;
						border: none;
					}

					::part(form-control-input) {
						padding-inline-start: var(--sl-spacing-medium);
					}

					/* Remove radius to avoid curved color bar */

					::part(combobox), ::part(tag__base) {
						border-radius: 0;
						background-color: transparent;
					}

					::part(chevron) {
						vertical-align: middle;
						padding-top: var(--sl-spacing-medium);
						font-size: var(--sl-font-size-large);
					}
			}
			/* never show scroll-bar */
			:host(:not([rows])) ::part(tags) {
				overflow-y: hidden;
			}

				*::part(tag__base), .tag {
					border-color: transparent;
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

	loadFromXML(node)
	{
		super.loadFromXML(node);
		this._static_options = this.getInstanceManager().widgetContainer.getArrayMgr('sel_options').getEntry('catsOptions');
	}

	get select_options() : SelectOption[]
	{
		// TODO: This filter needs to be used all the time select_options is read.  Maybe make it static select?
		let options = super.select_options
		if(this.noSubs)
		{
			options = options.filter(_item => {return !_item.parent_id;});
		}
		if(this.onlySubs)
		{
			options = options.filter(_item => {return _item.parent_id == this.options.onlySubs;});
		}
		return options;
	}

	set select_options(new_options)
	{
		// @ts-ignore IDE doesn't recognise property
		super.select_options = new_options;
	}

	set onlySubs(_parent_id)
	{
		this.__onlysubs = _parent_id?.toString()?.split(":")[0];
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
		// Tag used must match this.optionTag, but you can't use the variable directly.
		// Pass option along so SearchMixin can grab it if needed
		const value = (<string>option.value).replaceAll(" ", "___");

		return html`
            <sl-option value="${value}"
					   part="option"
					   title="${!option.title || this.noLang ? option.title : this.egw().lang(option.title)}"
					   class="${option.class}" .option=${option}
					   ?disabled=${option.disabled}
                       .selected=${this.getValueAsArray().some(v => v == value)}
					   style="border-left:6px solid ${option.color}">
                ${this.noLang ? option.label : this.egw().lang(option.label)}
			</sl-option>`;
	}

	/**
	 * build tag template
	 * @param option
	 * @param index
	 * @protected
	 */
	protected _tagTemplate(option: Et2Option, index: number): TemplateResult
	{
		const readonly = (this.readonly || option && typeof (option.disabled) != "undefined" && option.disabled);
		const image = this._iconTemplate(option.option);
		if (this.asColorTag && option?.option?.color)
		{
			return html`
				<sl-icon name="bookmark" style="color:${option?.option?.color}"></sl-icon>
			`;
		}
		else
		{
			return html`
            <et2-tag
                    part="tag"
                    exportparts="
                      base:tag__base,
                      content:tag__content,
                      remove-button:tag__remove-button,
                      remove-button__base:tag__remove-button__base,
                      icon:icon
                    "
					style="border-left:6px solid ${option?.option?.color}"
                    ?readonly=${readonly}
                    .value=${option?.option?.value}
            >
                ${image ?? nothing}
                ${option.getTextLabel().trim()}
            </et2-tag>
            ${index >= this.value.length - 1 ? nothing : html`
                <sl-icon name="chevron-right" part="chevron"></sl-icon>`}
		`;
		}

	}
}

customElements.define("smallpart-cats-select", SmallPartCatsSelect);