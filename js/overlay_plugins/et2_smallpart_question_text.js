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
var et2_smallpart_overlay_html_1 = require("./et2_smallpart_overlay_html");
/**
 * Overlay element to show a text question: question with ability to answer with some free text
 */
var et2_smallpart_question_text = /** @class */ (function (_super) {
    __extends(et2_smallpart_question_text, _super);
    function et2_smallpart_question_text() {
        return _super !== null && _super.apply(this, arguments) || this;
    }
    return et2_smallpart_question_text;
}(et2_smallpart_overlay_html_1.et2_smallpart_overlay_html));
exports.et2_smallpart_question_text = et2_smallpart_question_text;
/**
 * Editor widget for text question
 */
var et2_smallpart_question_text_editor = /** @class */ (function (_super) {
    __extends(et2_smallpart_question_text_editor, _super);
    function et2_smallpart_question_text_editor() {
        return _super !== null && _super.apply(this, arguments) || this;
    }
    return et2_smallpart_question_text_editor;
}(et2_smallpart_overlay_html_1.et2_smallpart_overlay_html_editor));
exports.et2_smallpart_question_text_editor = et2_smallpart_question_text_editor;
//# sourceMappingURL=et2_smallpart_question_text.js.map