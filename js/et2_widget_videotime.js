"use strict";
/**
 * EGroupware - SmallParT - videobar widget
 *
 * @link https://www.egroupware.org
 * @package smallpart
 * @subpackage Ui
 * @license http://opensource.org/licenses/gpl-license.php GPL - GNU General Public License
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
var et2_widget_description_1 = require("../../api/js/etemplate/et2_widget_description");
var et2_core_widget_1 = require("../../api/js/etemplate/et2_core_widget");
var et2_core_inheritance_1 = require("../../api/js/etemplate/et2_core_inheritance");
var et2_smallpart_videotime = /** @class */ (function (_super) {
    __extends(et2_smallpart_videotime, _super);
    /**
     * Constructor
     */
    function et2_smallpart_videotime(_parent, _attrs, _child) {
        // Call the inherited constructor
        return _super.call(this, _parent, _attrs, et2_core_inheritance_1.ClassWithAttributes.extendAttributes(et2_widget_description_1.et2_description._attributes, _child || {})) || this;
    }
    et2_smallpart_videotime.prototype.set_value = function (_value) {
        var time = new Date(null);
        time.setSeconds(parseInt(_value));
        return _super.prototype.set_value.call(this, time.toISOString().substr(11, 8));
    };
    return et2_smallpart_videotime;
}(et2_widget_description_1.et2_description));
et2_core_widget_1.et2_register_widget(et2_smallpart_videotime, ["smallpart-videotime"]);
//# sourceMappingURL=et2_widget_videotime.js.map