"use strict";
/**
 * EGroupware SmallPART - Videooverlay multiple-choice question plugin
 *
 * @license http://opensource.org/licenses/gpl-license.php GPL - GNU General Public License
 * @package smallpart
 * @subpackage ui
 * @link https://www.egroupware.org
 * @author Ralf Becker <rb@egroupware.org>
 */
var __extends = (this && this.__extends) || (function () {
    var extendStatics = function (d, b) {
        extendStatics = Object.setPrototypeOf ||
            ({ __proto__: [] } instanceof Array && function (d, b) { d.__proto__ = b; }) ||
            function (d, b) { for (var p in b) if (b.hasOwnProperty(p)) d[p] = b[p]; };
        return extendStatics(d, b);
    };
    return function (d, b) {
        extendStatics(d, b);
        function __() { this.constructor = d; }
        d.prototype = b === null ? Object.create(b) : (__.prototype = b.prototype, new __());
    };
})();
Object.defineProperty(exports, "__esModule", { value: true });
var et2_core_widget_1 = require("../../../api/js/etemplate/et2_core_widget");
var et2_core_inheritance_1 = require("../../../api/js/etemplate/et2_core_inheritance");
var et2_smallpart_overlay_html_1 = require("./et2_smallpart_overlay_html");
/**
 * Overlay element to show a multiple-choice question
 *
 * @ToDo extending et2_smallpart_question_text gives TypeError
 */
var et2_smallpart_question_multiplechoice = /** @class */ (function (_super) {
    __extends(et2_smallpart_question_multiplechoice, _super);
    /**
     * Constructor
     */
    function et2_smallpart_question_multiplechoice(_parent, _attrs, _child) {
        // Call the inherited constructor
        return _super.call(this, _parent, _attrs, et2_core_inheritance_1.ClassWithAttributes.extendAttributes(et2_smallpart_question_multiplechoice._attributes, _child || {})) || this;
    }
    et2_smallpart_question_multiplechoice._attributes = {
        answers: {
            name: 'possible answers',
            type: 'any',
            description: 'array of objects with attributes answer, correct, ...',
        },
    };
    return et2_smallpart_question_multiplechoice;
}(et2_smallpart_overlay_html_1.et2_smallpart_overlay_html));
exports.et2_smallpart_question_multiplechoice = et2_smallpart_question_multiplechoice;
et2_core_widget_1.et2_register_widget(et2_smallpart_question_multiplechoice, ["smallpart-question-multiplechoice"]);
/**
 * Editor widget for multiple-choice question
 *
 * @ToDo extending et2_smallpart_question_text_editor gives TypeError
 */
var et2_smallpart_question_multiplechoice_editor = /** @class */ (function (_super) {
    __extends(et2_smallpart_question_multiplechoice_editor, _super);
    /**
     * Constructor
     */
    function et2_smallpart_question_multiplechoice_editor(_parent, _attrs, _child) {
        // Call the inherited constructor
        return _super.call(this, _parent, _attrs, et2_core_inheritance_1.ClassWithAttributes.extendAttributes(et2_smallpart_question_multiplechoice_editor._attributes, _child || {})) || this;
    }
    et2_smallpart_question_multiplechoice_editor._attributes = {};
    return et2_smallpart_question_multiplechoice_editor;
}(et2_smallpart_overlay_html_1.et2_smallpart_overlay_html_editor));
exports.et2_smallpart_question_multiplechoice_editor = et2_smallpart_question_multiplechoice_editor;
et2_core_widget_1.et2_register_widget(et2_smallpart_question_multiplechoice_editor, ["smallpart-question-multiplechoice-editor"]);
//# sourceMappingURL=et2_smallpart_question_multiplechoice.js.map