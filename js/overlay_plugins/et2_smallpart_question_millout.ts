/**
 * EGroupware SmallPART - Videooverlay single-choice question plugin
 *
 * @license http://opensource.org/licenses/gpl-license.php GPL - GNU General Public License
 * @package smallpart
 * @subpackage ui
 * @link https://www.egroupware.org
 * @author Ralf Becker <rb@egroupware.org>
 */

import {et2_register_widget, WidgetConfig} from "../../../api/js/etemplate/et2_core_widget";
import {ClassWithAttributes} from "../../../api/js/etemplate/et2_core_inheritance";
import {egw} from "../../../api/js/jsapi/egw_global";
import {et2_smallpart_videobar} from "../et2_widget_videobar";
import {
	et2_smallpart_question_markchoice,
	et2_smallpart_question_markchoice_editor
} from "./et2_smallpart_question_markchoice";

/**
 * Overlay element to show a single-choice question
 *
 * @ToDo extending et2_smallpart_question_text gives TypeError
 */
export class et2_smallpart_question_millout extends et2_smallpart_question_markchoice
{
	static readonly _attributes : any = {
	};

	/**
	 * Constructor
	 */
	constructor(_parent, _attrs? : WidgetConfig, _child? : object)
	{
		// Call the inherited constructor
		super(_parent, _attrs, ClassWithAttributes.extendAttributes(et2_smallpart_question_millout._attributes, _child || {}));
	}
}
et2_register_widget(et2_smallpart_question_millout, ["smallpart-question-millout"]);

/**
 * Editor widget for single-choice question
 *
 * @ToDo extending et2_smallpart_question_text_editor gives TypeError
 */
export class et2_smallpart_question_millout_editor extends et2_smallpart_question_markchoice_editor
{
	static readonly _attributes : any = {
	};

	/**
	 * Constructor
	 */
	constructor(_parent, _attrs? : WidgetConfig, _child? : object)
	{
		// Call the inherited constructor
		super(_parent, _attrs, ClassWithAttributes.extendAttributes(et2_smallpart_question_millout_editor._attributes, _child || {}));
	}
}
et2_register_widget(et2_smallpart_question_millout_editor, ["smallpart-question-millout-editor"]);
