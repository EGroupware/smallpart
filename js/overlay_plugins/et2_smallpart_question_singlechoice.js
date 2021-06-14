/**
 * EGroupware SmallPART - Videooverlay single-choice question plugin
 *
 * @license http://opensource.org/licenses/gpl-license.php GPL - GNU General Public License
 * @package smallpart
 * @subpackage ui
 * @link https://www.egroupware.org
 * @author Ralf Becker <rb@egroupware.org>
 */
import { et2_register_widget } from "../../../api/js/etemplate/et2_core_widget";
import { ClassWithAttributes } from "../../../api/js/etemplate/et2_core_inheritance";
import { et2_smallpart_overlay_html, et2_smallpart_overlay_html_editor } from "./et2_smallpart_overlay_html";
import { egw } from "../../../api/js/jsapi/egw_global";
/**
 * Overlay element to show a single-choice question
 *
 * @ToDo extending et2_smallpart_question_text gives TypeError
 */
export class et2_smallpart_question_singlechoice extends et2_smallpart_overlay_html {
    /**
     * Constructor
     */
    constructor(_parent, _attrs, _child) {
        // Call the inherited constructor
        super(_parent, _attrs, ClassWithAttributes.extendAttributes(et2_smallpart_question_singlechoice._attributes, _child || {}));
    }
    submit(_value, _attrs) {
        console.log(_value, _attrs);
        if (_attrs) {
            return egw.request('smallpart.EGroupware\\SmallParT\\Questions.ajax_answer', [
                jQuery.extend(_attrs, { answer_data: jQuery.extend(true, {}, _attrs.answer_data, _value.answer_data) })
            ]);
        }
    }
}
et2_smallpart_question_singlechoice._attributes = {
    answers: {
        name: 'possible answers',
        type: 'any',
        description: 'array of objects with attributes answer, correct, ...',
    },
};
et2_register_widget(et2_smallpart_question_singlechoice, ["smallpart-question-singlechoice"]);
/**
 * Editor widget for single-choice question
 *
 * @ToDo extending et2_smallpart_question_text_editor gives TypeError
 */
export class et2_smallpart_question_singlechoice_editor extends et2_smallpart_overlay_html_editor {
    /**
     * Constructor
     */
    constructor(_parent, _attrs, _child) {
        // Call the inherited constructor
        super(_parent, _attrs, ClassWithAttributes.extendAttributes(et2_smallpart_question_singlechoice_editor._attributes, _child || {}));
    }
}
et2_smallpart_question_singlechoice_editor._attributes = {};
et2_register_widget(et2_smallpart_question_singlechoice_editor, ["smallpart-question-singlechoice-editor"]);
//# sourceMappingURL=et2_smallpart_question_singlechoice.js.map