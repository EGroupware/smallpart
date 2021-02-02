"use strict";
/**
 * EGroupware SmallPART - Videooverlay text question plugin
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
exports.et2_smallpart_question_text_editor = exports.et2_smallpart_question_text = void 0;
var et2_smallpart_overlay_html_1 = require("./et2_smallpart_overlay_html");
var et2_core_widget_1 = require("../../../api/js/etemplate/et2_core_widget");
var et2_core_inheritance_1 = require("../../../api/js/etemplate/et2_core_inheritance");
/**
 * Overlay element to show a text question: question with ability to answer with some free text
 */
var et2_smallpart_question_text = /** @class */ (function (_super) {
    __extends(et2_smallpart_question_text, _super);
    /**
     * Constructor
     */
    function et2_smallpart_question_text(_parent, _attrs, _child) {
        // Call the inherited constructor
        return _super.call(this, _parent, _attrs, et2_core_inheritance_1.ClassWithAttributes.extendAttributes(et2_smallpart_question_text._attributes, _child || {})) || this;
    }
    et2_smallpart_question_text.prototype.submit = function (_value, _attrs) {
        console.log(_value, _attrs);
        if (_attrs) {
            return egw.request('smallpart.EGroupware\\SmallParT\\Questions.ajax_answer', [
                jQuery.extend(_attrs, { answer_data: jQuery.extend(true, _attrs.answer_data, _value.answer_data) })
            ]);
        }
    };
    et2_smallpart_question_text._attributes = {};
    return et2_smallpart_question_text;
}(et2_smallpart_overlay_html_1.et2_smallpart_overlay_html));
exports.et2_smallpart_question_text = et2_smallpart_question_text;
et2_core_widget_1.et2_register_widget(et2_smallpart_question_text, ["smallpart-question-text"]);
/**
 * Editor widget for text question
 */
var et2_smallpart_question_text_editor = /** @class */ (function (_super) {
    __extends(et2_smallpart_question_text_editor, _super);
    /**
     * Constructor
     */
    function et2_smallpart_question_text_editor(_parent, _attrs, _child) {
        // Call the inherited constructor
        return _super.call(this, _parent, _attrs, et2_core_inheritance_1.ClassWithAttributes.extendAttributes(et2_smallpart_question_text_editor._attributes, _child || {})) || this;
    }
    et2_smallpart_question_text_editor._attributes = {};
    return et2_smallpart_question_text_editor;
}(et2_smallpart_overlay_html_1.et2_smallpart_overlay_html_editor));
exports.et2_smallpart_question_text_editor = et2_smallpart_question_text_editor;
et2_core_widget_1.et2_register_widget(et2_smallpart_question_text_editor, ["smallpart-question-text-editor"]);
//# sourceMappingURL=et2_smallpart_question_text.js.map