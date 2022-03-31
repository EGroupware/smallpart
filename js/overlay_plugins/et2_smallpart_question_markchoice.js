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
exports.et2_smallpart_question_markchoice_editor = exports.et2_smallpart_question_markchoice = void 0;
var et2_core_widget_1 = require("../../../api/js/etemplate/et2_core_widget");
var et2_core_inheritance_1 = require("../../../api/js/etemplate/et2_core_inheritance");
var et2_smallpart_overlay_html_1 = require("./et2_smallpart_overlay_html");
var mark_helpers_1 = require("../mark_helpers");
/**
 * Overlay element to show a single-choice question
 *
 * @ToDo extending et2_smallpart_question_text gives TypeError
 */
var et2_smallpart_question_markchoice = /** @class */ (function (_super) {
    __extends(et2_smallpart_question_markchoice, _super);
    //marks = [];
    /**
     * Constructor
     */
    function et2_smallpart_question_markchoice(_parent, _attrs, _child) {
        var _a, _b, _c;
        var _this = 
        // Call the inherited constructor
        _super.call(this, _parent, _attrs, et2_core_inheritance_1.ClassWithAttributes.extendAttributes(et2_smallpart_question_markchoice._attributes, _child || {})) || this;
        _this.videobar = (_b = (_a = app.smallpart) === null || _a === void 0 ? void 0 : _a.et2) === null || _b === void 0 ? void 0 : _b.getWidgetById('video');
        if (_this.videobar) {
            var mark_values = ((_c = _attrs.answer_data) === null || _c === void 0 ? void 0 : _c.marks) || [];
            _this.videobar.setMarks(mark_helpers_1.MarkArea.colorDisjunctiveAreas(mark_helpers_1.MarkArea.markDisjunctiveAreas(mark_values, _this.videobar.video.width() / _this.videobar.video.height()), _this.videobar.get_marking_colors()));
            _this.videobar.set_marking_enabled(true, function (mark) { return console.log(mark); });
            _this.videobar.set_marking_readonly(true);
            _this.videobar.setMarkingMask(true);
        }
        return _this;
    }
    /* controller directly destroys the widget again, no idea why
    destroy()
    {
        if (this.videobar)
        {
            this.videobar.setMarks([]);
            this.videobar.set_marking_enabled(false);
            this.videobar.set_marking_readonly(true);
            this.videobar.setMarkingMask(false);
        }
        super.destroy();
    }*/
    et2_smallpart_question_markchoice.prototype.submit = function (_value, _attrs) {
        var _a;
        console.log(_value, _attrs);
        if (_attrs) {
            var data = jQuery.extend(_attrs, { answer_data: { marks: (_a = this.videobar) === null || _a === void 0 ? void 0 : _a.getMarks(true) } });
            // remove marks and mask as video continues
            this.videobar.setMarks([]);
            this.videobar.set_marking_enabled(false);
            this.videobar.set_marking_readonly(true);
            this.videobar.setMarkingMask(false);
            // send data
            return egw.request('smallpart.EGroupware\\SmallParT\\Questions.ajax_answer', [data]);
        }
    };
    et2_smallpart_question_markchoice._attributes = {
        answers: {
            name: 'possible answers',
            type: 'any',
            description: 'array of objects with attributes answer, correct, ...',
        },
        answer_data: {
            name: 'marking areas for correct answers in attribute "marks"',
            type: 'any',
            description: 'array of markings, see et2_smallpart_videobar.setMarks()'
        }
    };
    return et2_smallpart_question_markchoice;
}(et2_smallpart_overlay_html_1.et2_smallpart_overlay_html));
exports.et2_smallpart_question_markchoice = et2_smallpart_question_markchoice;
et2_core_widget_1.et2_register_widget(et2_smallpart_question_markchoice, ["smallpart-question-markchoice"]);
/**
 * Editor widget for single-choice question
 *
 * @ToDo extending et2_smallpart_question_text_editor gives TypeError
 */
var et2_smallpart_question_markchoice_editor = /** @class */ (function (_super) {
    __extends(et2_smallpart_question_markchoice_editor, _super);
    /**
     * Constructor
     */
    function et2_smallpart_question_markchoice_editor(_parent, _attrs, _child) {
        // Call the inherited constructor
        return _super.call(this, _parent, _attrs, et2_core_inheritance_1.ClassWithAttributes.extendAttributes(et2_smallpart_question_markchoice_editor._attributes, _child || {})) || this;
    }
    et2_smallpart_question_markchoice_editor._attributes = {};
    return et2_smallpart_question_markchoice_editor;
}(et2_smallpart_overlay_html_1.et2_smallpart_overlay_html_editor));
exports.et2_smallpart_question_markchoice_editor = et2_smallpart_question_markchoice_editor;
et2_core_widget_1.et2_register_widget(et2_smallpart_question_markchoice_editor, ["smallpart-question-markchoice-editor"]);
//# sourceMappingURL=et2_smallpart_question_markchoice.js.map