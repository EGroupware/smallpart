/**
 * EGroupware - SmallParT - color radiobox widget
 *
 * @link https://www.egroupware.org
 * @package smallpart
 * @subpackage Ui
 * @license http://opensource.org/licenses/gpl-license.php GPL - GNU General Public License
 */


/*egw:uses
	/vendor/bower-asset/jquery/dist/jquery.js;
	et2_core_inputWidget;
*/
import {et2_radiobox} from "../../api/js/etemplate/et2_widget_radiobox";
import {ClassWithAttributes} from "../../api/js/etemplate/et2_core_inheritance";
import {et2_register_widget, WidgetConfig} from "../../api/js/etemplate/et2_core_widget";

/**
 * Class which implements the "radiobox" XET-Tag
 *
 * A radio button belongs to same group by giving all buttons of a group same id!
 *
 * set_value iterates over all of them and (un)checks them depending on given value.
 *
 * @augments et2_inputWidget
 */
export class et2_smallpart_color_radiobox extends et2_radiobox
{

	static readonly _attributes : any = {}

	container : JQuery = null;
	/**
	 * Constructor
	 *
	 * @memberOf et2_radiobox_ro
	 */
	constructor(_parent, _attrs? : WidgetConfig, _child? : object)
	{
		// Call the inherited constructor
		super(_parent, _attrs, ClassWithAttributes.extendAttributes(et2_smallpart_color_radiobox._attributes, _child || {}));


	}

	/**
	 * Override the getTooltipElement because the domnode gets manipulated in loading finished
	 */
	getTooltipElement()
	{
		return this.container[0];
	}

	loadingFinished()
	{
		let self = this;
		this.container = jQuery(document.createElement('span'))
			.addClass('smallpart-color-radiobox');
		this.getSurroundings().prependDOMNode(this.container[0]);
		this.container.empty();
		this.container
			.click(function(e){
				self.container.addClass('checked');
				self.input.trigger('click');
				self.getValue();
			})
			.css({'background-color': this.options.set_value})
			.addClass('smallpart-color-radiobox color' + this.options.set_value);

		this.getSurroundings().update();
		super.loadingFinished();
	}

	set_value(_value)
	{
		super.set_value(_value);
		this.getRoot().iterateOver(function(radio)
		{
			if (radio.id == this.id)
			{
				radio.input.prop('checked', _value == radio.options.set_value).change();
				if (_value == radio.options.set_value) radio.container.addClass('checked');
			}
		}, this, et2_smallpart_color_radiobox);
	}

	getValue()
	{
		this.getRoot().iterateOver(function(radio)
		{
			if (radio.id == this.id && radio.input)
			{
				radio.container.removeClass('checked');
				if (radio.input.prop('checked')) radio.container.addClass('checked');
				radio.getSurroundings().update();
			}
		}, this, et2_smallpart_color_radiobox);

		return super.getValue();
	}


	/**
	 * Set radio readonly attribute.
	 *
	 * @param _readonly Boolean
	 */
	set_readonly(_readonly)
	{
		this.getRoot().iterateOver(function(radio)
		{
			if (radio.id == this.id && radio.container)
			{
				if (_readonly)
				{
					radio.container.addClass('disabled');
				}
				else
				{
					radio.container.removeClass('disabled')
				}
			}
		}, this, et2_smallpart_color_radiobox);
		super.set_readonly(_readonly);
	}
}
et2_register_widget(et2_smallpart_color_radiobox, ["smallpart-color-radiobox"]);