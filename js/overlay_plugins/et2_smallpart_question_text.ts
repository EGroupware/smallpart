/**
 * EGroupware SmallPART - Videooverlay text question plugin
 *
 * @license http://opensource.org/licenses/gpl-license.php GPL - GNU General Public License
 * @package smallpart
 * @subpackage ui
 * @link https://www.egroupware.org
 * @author Ralf Becker <rb@egroupware.org>
 */

import {et2_IOverlayElement, et2_IOverlayElementEditor} from "../et2_videooverlay_interface";
import {et2_smallpart_overlay_html, et2_smallpart_overlay_html_editor} from "./et2_smallpart_overlay_html";
import {et2_register_widget, WidgetConfig} from "../../../api/js/etemplate/et2_core_widget";
import {ClassWithAttributes} from "../../../api/js/etemplate/et2_core_inheritance";
import {egw} from "../../../api/js/jsapi/egw_global";

/**
 * Overlay element to show a text question: question with ability to answer with some free text
 */
export class et2_smallpart_question_text extends et2_smallpart_overlay_html implements et2_IOverlayElement
{
	static readonly _attributes : any = {
	};

	/**
	 * Constructor
	 */
	constructor(_parent, _attrs? : WidgetConfig, _child? : object)
	{
		// Call the inherited constructor
		super(_parent, _attrs, ClassWithAttributes.extendAttributes(et2_smallpart_question_text._attributes, _child || {}));
	}

	submit(_value, _attrs)
	{
		console.log(_value, _attrs);
		if (_attrs)
		{
			return egw.request('smallpart.EGroupware\\SmallParT\\Questions.ajax_answer', [
				jQuery.extend(_attrs, {answer_data: jQuery.extend(true,  {}, _attrs.answer_data, _value.answer_data)})]);
		}
	}
}
et2_register_widget(et2_smallpart_question_text, ["smallpart-question-text"]);

/**
 * Editor widget for text question
 */
export class et2_smallpart_question_text_editor extends et2_smallpart_overlay_html_editor implements et2_IOverlayElementEditor
{
	static readonly _attributes : any = {
	};

	/**
	 * Constructor
	 */
	constructor(_parent, _attrs? : WidgetConfig, _child? : object)
	{
		// Call the inherited constructor
		super(_parent, _attrs, ClassWithAttributes.extendAttributes(et2_smallpart_question_text_editor._attributes, _child || {}));
	}
}
et2_register_widget(et2_smallpart_question_text_editor, ["smallpart-question-text-editor"]);
