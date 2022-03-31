/**
 * EGroupware - SmallParT - videobar widget
 *
 * @link https://www.egroupware.org
 * @package smallpart
 * @subpackage Ui
 * @license http://opensource.org/licenses/gpl-license.php GPL - GNU General Public License
 */

import {et2_description} from "../../api/js/etemplate/et2_widget_description";
import {et2_register_widget, WidgetConfig} from "../../api/js/etemplate/et2_core_widget";
import {ClassWithAttributes} from '../../api/js/etemplate/et2_core_inheritance';

export class et2_smallpart_videotime extends et2_description
{
	static readonly _attributes : any = {
		value: {
			name: 'Value',
			type: 'integer',
			description: 'Elapsed time in seconds',
			default: 0
		},
		indicator: {
			name: 'indicator',
			type: 'string',
			description: 'Defines the indicator type, time|page. default is video.',
			default: 'time'
		}
	};

	/**
	 * Constructor
	 */
	constructor(_parent, _attrs? : WidgetConfig, _child? : object)
	{
		// Call the inherited constructor
		super(_parent, _attrs, ClassWithAttributes.extendAttributes(et2_smallpart_videotime._attributes, _child || {}));

		this.span.addClass('smallpart-videotime');
	}

	set_value(_value)
	{
		let value = _value;
		switch(this.options.indicator)
		{
			case 'time':
				let time = new Date(null);
				time.setSeconds(parseInt(_value));
				value = time.toISOString().substr(11, 8);
				break;
			case 'page':
				value = egw.lang('page %1', Math.floor(parseInt(_value)));
				break;
		}

		return super.set_value(value);
	}
}
et2_register_widget(et2_smallpart_videotime, ["smallpart-videotime"]);
