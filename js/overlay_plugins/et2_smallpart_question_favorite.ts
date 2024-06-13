/**
 * EGroupware SmallPART - Videooverlay single-choice question plugin
 *
 * @license http://opensource.org/licenses/gpl-license.php GPL - GNU General Public License
 * @package smallpart
 * @subpackage ui
 * @link https://www.egroupware.org
 * @author Ralf Becker <rb@egroupware.org>
 */

import {et2_IOverlayElement, et2_IOverlayElementEditor} from "../et2_videooverlay_interface";
import {et2_register_widget, WidgetConfig} from "../../../api/js/etemplate/et2_core_widget";
import {ClassWithAttributes} from "../../../api/js/etemplate/et2_core_inheritance";
import {et2_smallpart_overlay_html, et2_smallpart_overlay_html_editor} from "./et2_smallpart_overlay_html";
import {egw} from "../../../api/js/jsapi/egw_global";

/**
 * Overlay element to show a single-choice question
 *
 * @ToDo extending et2_smallpart_question_text gives TypeError
 */
export class et2_smallpart_question_favorite extends et2_smallpart_overlay_html implements et2_IOverlayElement
{
	static readonly _attributes : any = {
	};

	/**
	 * Constructor
	 */
	constructor(_parent, _attrs? : WidgetConfig, _child? : object)
	{
		// Call the inherited constructor
		super(_parent, _attrs, ClassWithAttributes.extendAttributes(et2_smallpart_question_favorite._attributes, _child || {}));
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
et2_register_widget(et2_smallpart_question_favorite, ["smallpart-question-favorite"]);

/**
 * Editor widget for single-choice question
 *
 * @ToDo extending et2_smallpart_question_text_editor gives TypeError
 */
export class et2_smallpart_question_favorite_editor extends et2_smallpart_overlay_html_editor implements et2_IOverlayElementEditor
{
	static readonly _attributes : any = {
	};

	/**
	 * Constructor
	 */
	constructor(_parent, _attrs? : WidgetConfig, _child? : object)
	{
		// Call the inherited constructor
		super(_parent, _attrs, ClassWithAttributes.extendAttributes(et2_smallpart_question_favorite_editor._attributes, _child || {}));
	}
}
et2_register_widget(et2_smallpart_question_favorite_editor, ["smallpart-question-favorite-editor"]);