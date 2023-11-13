/**
 * EGroupware - SmallParT - videobar widget
 *
 * @link https://www.egroupware.org
 * @package smallpart
 * @subpackage Ui
 * @license https://spdx.org/licenses/AGPL-3.0-or-later.html GNU Affero General Public License v3.0 or later
 */

import {et2_register_widget, WidgetConfig} from "../../api/js/etemplate/et2_core_widget";
import {ClassWithAttributes} from '../../api/js/etemplate/et2_core_inheritance';
import {et2_valueWidget} from "../../api/js/etemplate/et2_core_valueWidget";
import {et2_IDetachedDOM} from "../../api/js/etemplate/et2_core_interfaces";
import {et2_no_init} from "../../api/js/etemplate/et2_core_common";
import {sprintf} from "../../api/js/egw_action/egw_action_common";
import {egw} from "../../api/js/jsapi/egw_global";

/**
 * Format an array of the following form ["text", account_id1|"nick1", "comment1", ...] like:
 *
 *   text
 * 		--> nick1) comment1
 * 			--> nick2) comment2
 */
export class et2_smallpart_comment extends et2_valueWidget implements et2_IDetachedDOM
{
	static readonly _attributes : any = {
		value: {
			name: 'value',
			type: 'any',	// we have no array type, 'any' means leave it as-is
			description: 'SmallParT comment array incl. retweets: ["text", account_id1|"nick1", "comment1", ...]',
			default: et2_no_init
		},
		starttime: {
			name: 'starttime',
			type: 'integer',
			description: 'optional starttime to display before first comment',
			default: et2_no_init
		},
		stoptime: {
			name: 'stoptime',
			type: 'integer',
			description: 'optional stoptime to display before first comment',
			default: et2_no_init
		}
	};

	value : Array<string|number>;
	div : JQuery = null;
	nicks : any = {};
	private _time : string = '';

	/**
	 * Constructor
	 */
	constructor(_parent, _attrs? : WidgetConfig, _child? : object)
	{
		// Call the inherited constructor
		super(_parent, _attrs, ClassWithAttributes.extendAttributes(et2_smallpart_comment._attributes, _child || {}));

		this.value = [''];
		this.div = jQuery(document.createElement('div'))
			.addClass('et2_smallpart_comment');

		this.setDOMNode(this.div[0]);
	}

	getValue()
	{
		return this.value;
	}

	/**
	 * Set value
	 *
	 * @param _value
	 */
	set_value(_value : Array<string|number>)
	{
		if (!Array.isArray(_value)) _value = [_value];
		this.value = _value;

		let self = this;

		this.div.empty();
		this.div.text(this.value[0]);
		if (this._time !== '') this.div.prepend(jQuery('<span class="et2_smallpart_comment_time"/>').text(this._time));
		let div = this.div;

		for (let n=1; n < this.value.length; n += 2)
		{
			let user = this.value[n];
			if (typeof user === "string" && !parseInt(user))
			{
				let match = user.match(/\[(\d+)\]$/);	// old: "first name [account_id]"
				if (match && match.length > 1) user = this.value[n] = parseInt(match[1]);
			}
			if (!Object.keys(this.nicks).length)
			{
				const participants = this.getRoot().getArrayMgr('sel_options').getEntry('account_id');
				participants.forEach((participant) =>
				{
					this.nicks[participant.value] = participant.label;
				});
			}
			div = jQuery(document.createElement('div'))
				.text(this.value[n+1])
				.addClass('et2_smallpart_comment_retweet')
				.prepend(jQuery('<span class="et2_smallpart_comment_retweeter"/>')
					.text(this.nicks[user] || '#'+user))
				.prepend('<span class="glyphicon glyphicon-arrow-right"/>')
				.appendTo(div);
		}
	}

	set_starttime(_time : number)
	{
		if (!isNaN(_time))
		{
			this._time = sprintf('%02d:%02d:%02d', ~~(_time/3600), ~~(_time/60), _time%60);
		}
		else
		{
			this._time = '';
		}
	}

	set_stoptime(_time : number)
	{
		if (!isNaN(_time))
		{
			this._time += '-'+sprintf('%02d:%02d:%02d', ~~(_time/3600), ~~(_time/60), _time%60);
		}
		else
		{
			this._time += '';
		}
	}

	/**
	 * Code for implementing et2_IDetachedDOM (data grid)
	 *
	 * @param {array} _attrs array of attribute-names to push further names onto
	 */
	getDetachedAttributes(_attrs)
	{
		_attrs.push('value', 'time');
	}

	getDetachedNodes()
	{
		return [this.div[0]];
	}

	setDetachedAttributes(_nodes, _values)
	{
		this.div = jQuery(_nodes[0]);
		if(typeof _values['value'] != 'undefined')
		{
			this.set_value(_values['value']);
		}
		this.set_label(_values['time'])
	}
}
et2_register_widget(et2_smallpart_comment, ["smallpart-comment"]);
