"use strict";
/**
 * EGroupware SmallPART - Videooverlay single-choice question plugin
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
exports.et2_smallpart_question_millout_editor = exports.et2_smallpart_question_millout = void 0;
var et2_core_widget_1 = require("../../../api/js/etemplate/et2_core_widget");
var et2_core_inheritance_1 = require("../../../api/js/etemplate/et2_core_inheritance");
var et2_smallpart_question_markchoice_1 = require("./et2_smallpart_question_markchoice");
/**
 * Overlay element to show a single-choice question
 *
 * @ToDo extending et2_smallpart_question_text gives TypeError
 */
var et2_smallpart_question_millout = /** @class */ (function (_super) {
    __extends(et2_smallpart_question_millout, _super);
    /**
     * Constructor
     */
    function et2_smallpart_question_millout(_parent, _attrs, _child) {
        // Call the inherited constructor
        return _super.call(this, _parent, _attrs, et2_core_inheritance_1.ClassWithAttributes.extendAttributes(et2_smallpart_question_millout._attributes, _child || {})) || this;
    }
    et2_smallpart_question_millout._attributes = {};
    return et2_smallpart_question_millout;
}(et2_smallpart_question_markchoice_1.et2_smallpart_question_markchoice));
exports.et2_smallpart_question_millout = et2_smallpart_question_millout;
et2_core_widget_1.et2_register_widget(et2_smallpart_question_millout, ["smallpart-question-millout"]);
/**
 * Editor widget for single-choice question
 *
 * @ToDo extending et2_smallpart_question_text_editor gives TypeError
 */
var et2_smallpart_question_millout_editor = /** @class */ (function (_super) {
    __extends(et2_smallpart_question_millout_editor, _super);
    /**
     * Constructor
     */
    function et2_smallpart_question_millout_editor(_parent, _attrs, _child) {
        // Call the inherited constructor
        return _super.call(this, _parent, _attrs, et2_core_inheritance_1.ClassWithAttributes.extendAttributes(et2_smallpart_question_millout_editor._attributes, _child || {})) || this;
    }
    et2_smallpart_question_millout_editor._attributes = {};
    return et2_smallpart_question_millout_editor;
}(et2_smallpart_question_markchoice_1.et2_smallpart_question_markchoice_editor));
exports.et2_smallpart_question_millout_editor = et2_smallpart_question_millout_editor;
et2_core_widget_1.et2_register_widget(et2_smallpart_question_millout_editor, ["smallpart-question-millout-editor"]);
//# sourceMappingURL=et2_smallpart_question_millout.js.map