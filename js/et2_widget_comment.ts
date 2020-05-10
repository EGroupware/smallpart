/**
 * EGroupware - SmallParT - videobar widget
 *
 * @link https://www.egroupware.org
 * @package smallpart
 * @subpackage Ui
 * @license http://opensource.org/licenses/gpl-license.php GPL - GNU General Public License
 */

import {et2_register_widget, WidgetConfig} from "../../api/js/etemplate/et2_core_widget";
import {ClassWithAttributes} from '../../api/js/etemplate/et2_core_inheritance';
import {et2_valueWidget} from "../../api/js/etemplate/et2_core_valueWidget";

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
		time: {
			name: 'time',
			type: 'integer',
			description: 'optional starttime to display before first comment',
			default: et2_no_init
		}
	};

	value : Array<string|number>;
	div : JQuery = null;
	nicks : any = {};
	time : string = '';

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
		if (this.time !== '') this.div.prepend(jQuery('<span class="et2_smallpart_comment_time"/>').text(this.time));
		let div = this.div;

		for (let n=1; n < this.value.length; n += 2)
		{
			let user = this.value[n];
			if (typeof user === "string" && !parseInt(user))
			{
				let match = user.match(/\[(\d+)\]$/);	// old: "first name [account_id]"
				if (match && match.length > 1) user = this.value[n] = parseInt(match[1]);
			}
			if (typeof this.nicks[user] === 'undefined')
			{
				egw.link_title('api-accounts', user, function(_nick)
				{
					self.nicks[user] = _nick;
					self.set_value(self.value);
				});
				break;
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

	set_time(_time : number)
	{
		if (!isNaN(_time))
		{
			this.time = sprintf('%02d:%02d:%02d', ~~(_time/3600), ~~(_time/60), _time%60);
		}
		else
		{
			this.time = '';
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