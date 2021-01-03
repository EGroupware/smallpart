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
var et2_smallpart_question_text_1 = require("./et2_smallpart_question_text");
/**
 * Overlay element to show a multiple-choice question
 */
var et2_smallpart_question_multiplechoice = /** @class */ (function (_super) {
    __extends(et2_smallpart_question_multiplechoice, _super);
    function et2_smallpart_question_multiplechoice() {
        return _super !== null && _super.apply(this, arguments) || this;
    }
    return et2_smallpart_question_multiplechoice;
}(et2_smallpart_question_text_1.et2_smallpart_question_text));
exports.et2_smallpart_question_multiplechoice = et2_smallpart_question_multiplechoice;
/**
 * Editor widget for multiple-choice question
 */
var et2_smallpart_question_multiplechoice_editor = /** @class */ (function (_super) {
    __extends(et2_smallpart_question_multiplechoice_editor, _super);
    function et2_smallpart_question_multiplechoice_editor() {
        return _super !== null && _super.apply(this, arguments) || this;
    }
    return et2_smallpart_question_multiplechoice_editor;
}(et2_smallpart_question_text_1.et2_smallpart_question_text_editor));
exports.et2_smallpart_question_multiplechoice_editor = et2_smallpart_question_multiplechoice_editor;
//# sourceMappingURL=et2_smallpart_question_multiplechoice.js.map