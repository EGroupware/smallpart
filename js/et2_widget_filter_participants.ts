/**
 * EGroupware - SmallParT - filter participants widget
 *
 * @link https://www.egroupware.org
 * @package smallpart
 * @subpackage Ui
 * @license http://opensource.org/licenses/gpl-license.php GPL - GNU General Public License
 */

import {et2_taglist} from "../../api/js/etemplate/et2_widget_taglist";
import {et2_register_widget, WidgetConfig} from "../../api/js/etemplate/et2_core_widget";
import {ClassWithAttributes} from '../../api/js/etemplate/et2_core_inheritance';

class et2_smallpart_filter_participants extends et2_taglist
{
	static readonly _attributes : any = {
		is_admin: {
			name: 'Is admin',
			type: 'boolean',
			description: 'Enables extra admin features',
			default: false
		},
		no_comments: {
			name: 'no comments',
			type: 'boolean',
			description: 'shows only label and name if it is switched on',
			default: false
		}
	};

	/**
	 * Construtor
	 */
	constructor(_parent, _attrs? : WidgetConfig, _child? : object)
	{
		// Call the inherited constructor
		super(_parent, _attrs, ClassWithAttributes.extendAttributes(et2_smallpart_filter_participants._attributes, _child || {}));
		this.div.addClass('smallpart_filter_participants');
	}


	/**
	 * Render a single item, taking care of correctly escaping html special chars
	 *
	 * @param item
	 * @returns {String}
	 */
	selectionRenderer(item)
	{
		let label = super.selectionRenderer(item);
		// return only label if it's not an admin
		if (!this.options.is_admin) return label;

		let container = jQuery('<div>').addClass('et2_smallpart_filter_participants_container').append(label);
		let left = jQuery('<div>').addClass('et2_smallpart_filter_participants_left').appendTo(container);
		left.append(label);
		if (item.name != '')
		{
			jQuery('<span/>')
				.addClass('name')
				.text(item.name)
				.appendTo(left);
		}
		if (!this.options.no_comments && (typeof item.comments != 'undefined' || typeof item.retweets != 'undefined'))
		{
			let right = jQuery('<div>').addClass('et2_smallpart_filter_participants_right').appendTo(container);
			jQuery('<label/>')
				.text(egw.lang('Comments')+":")
				.appendTo(right)
				.append(jQuery('<span/>').text(item.comments));

			jQuery('<label/>')
				.text(egw.lang('Retweets')+":")
				.appendTo(right)
				.append(jQuery('<span/>').text(item.retweets));
		}
		return container;
	}
}
et2_register_widget(et2_smallpart_filter_participants, ["smallpart-filter-participants"]);