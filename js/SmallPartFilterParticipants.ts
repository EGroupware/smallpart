/*
 * SmallPart Filter Participants widget
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
export class SmallPartFilterParticipants extends Et2Select
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
			:host {
				--icon-width: 40px;
			}
			`
		];
	}

	constructor(...args : any[])
	{
		super(...args);
		this.multiple = true;
		this.allowFreeEntries = false;
	}

	static get properties()
	{
		return {
			...super.properties,
			/**
			 * Enables extra admin features
			 */
			is_staff: {
				type: String
			},
			/**
			 * Shows only label and name if it is switched on
			 */
			no_comments: {
				type: Boolean
			}
		}
	}

	_optionTemplate(option : SelectOption) : TemplateResult
	{
		let icon = option.icon ? html`
            <et2-image slot="prefix" part="icon" style="width: var(--icon-width)"
                       src="${option.icon}"></et2-image>` : "";

		return html`
            <sl-menu-item value="${option.value}" title="${option.title}" class="${option.class}" .option=${option}>
                ${icon}
				${this._createStaffOptionTemplate(option)}
            </sl-menu-item>`;
	}

	/**
	 * Create more option template used for staff mode
	 * @param _item
	 * @protected
	 */
	protected _createStaffOptionTemplate(_item)
	{
		let comments, retweets, name, label;
		if (this.is_staff != '')
		{
			if (!this.options.no_comments && (typeof _item.comments != 'undefined' || typeof _item.retweets != 'undefined'))
			{
				comments = html`
					<et2-hbox>
                        <et2-label value="${egw.lang('Comments')}:"></et2-label>
                        <et2-label value="${_item.comments}"></et2-label>
					</et2-hbox>
					
				`;
				retweets = html`
					<et2-hbox>
                        <et2-label value="${egw.lang('Retweets')}:"></et2-label>
                        <et2-label value="${_item.retweets}"></et2-label>
					</et2-hbox>
				`;
			}

			if (_item.name != '')
			{
				name = _item.name;
			}
		}
		label = _item.label;

		return html`
			${label}<br />
			${name}
			<et2-vbox slot="suffix">
                ${comments}
                ${retweets}
			</et2-vbox>
		`;
	}

	/**
	 * Generate Participants tag
	 *
	 * @param item
	 * @protected
	 * @todo build the same staff options for tag
	 */
	protected _createTagNode(item)
	{
		let tag = super._createTagNode(item);
		// return simple tag if it's not a staff member
		if (this.is_staff == '') return tag;

		//@TODO: build the same staff options for tag
		return tag;
	}
}

customElements.define("smallpart-filter-participants", SmallPartFilterParticipants);