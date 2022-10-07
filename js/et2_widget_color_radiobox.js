"use strict";
/**
 * EGroupware - SmallParT - color radiobox widget
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
exports.et2_smallpart_color_radiobox = void 0;
/*egw:uses
    /vendor/bower-asset/jquery/dist/jquery.js;
    et2_core_inputWidget;
*/
var et2_widget_radiobox_1 = require("../../api/js/etemplate/et2_widget_radiobox");
var et2_core_inheritance_1 = require("../../api/js/etemplate/et2_core_inheritance");
var et2_core_widget_1 = require("../../api/js/etemplate/et2_core_widget");
/**
 * Class which implements the "radiobox" XET-Tag
 *
 * A radio button belongs to same group by giving all buttons of a group same id!
 *
 * set_value iterates over all of them and (un)checks them depending on given value.
 *
 * @augments et2_inputWidget
 */
var et2_smallpart_color_radiobox = /** @class */ (function (_super) {
    __extends(et2_smallpart_color_radiobox, _super);
    /**
     * Constructor
     *
     * @memberOf et2_radiobox_ro
     */
    function et2_smallpart_color_radiobox(_parent, _attrs, _child) {
        var _this = 
        // Call the inherited constructor
        _super.call(this, _parent, _attrs, et2_core_inheritance_1.ClassWithAttributes.extendAttributes(et2_smallpart_color_radiobox._attributes, _child || {})) || this;
        _this.container = null;
        return _this;
    }
    /**
     * Override the getTooltipElement because the domnode gets manipulated in loading finished
     */
    et2_smallpart_color_radiobox.prototype.getTooltipElement = function () {
        return this.container[0];
    };
    et2_smallpart_color_radiobox.prototype.loadingFinished = function () {
        var self = this;
        this.container = jQuery(document.createElement('span'))
            .addClass('smallpart-color-radiobox');
        this.getSurroundings().prependDOMNode(this.container[0]);
        this.container.empty();
        this.container
            .click(function (e) {
            self.container.addClass('checked');
            self.input.trigger('click');
            self.getValue();
        })
            .css({ 'background-color': this.options.set_value })
            .addClass('smallpart-color-radiobox color' + this.options.set_value);
        this.getSurroundings().update();
        _super.prototype.loadingFinished.call(this);
    };
    et2_smallpart_color_radiobox.prototype.set_value = function (_value) {
        _super.prototype.set_value.call(this, _value);
        this.getRoot().iterateOver(function (radio) {
            if (radio.id == this.id) {
                radio.input.prop('checked', _value == radio.options.set_value).change();
                if (_value == radio.options.set_value)
                    radio.container.addClass('checked');
            }
        }, this, et2_smallpart_color_radiobox);
    };
    et2_smallpart_color_radiobox.prototype.getValue = function () {
        this.getRoot().iterateOver(function (radio) {
            if (radio.id == this.id && radio.input) {
                radio.container.removeClass('checked');
                if (radio.input.prop('checked'))
                    radio.container.addClass('checked');
                radio.getSurroundings().update();
            }
        }, this, et2_smallpart_color_radiobox);
        return _super.prototype.getValue.call(this);
    };
    /**
     * Set radio readonly attribute.
     *
     * @param _readonly Boolean
     */
    et2_smallpart_color_radiobox.prototype.set_readonly = function (_readonly) {
        this.getRoot().iterateOver(function (radio) {
            if (radio.id == this.id && radio.container) {
                if (_readonly) {
                    radio.container.addClass('disabled');
                }
                else {
                    radio.container.removeClass('disabled');
                }
            }
        }, this, et2_smallpart_color_radiobox);
        _super.prototype.set_readonly.call(this, _readonly);
    };
    et2_smallpart_color_radiobox._attributes = {};
    return et2_smallpart_color_radiobox;
}(et2_widget_radiobox_1.et2_radiobox));
exports.et2_smallpart_color_radiobox = et2_smallpart_color_radiobox;
et2_core_widget_1.et2_register_widget(et2_smallpart_color_radiobox, ["smallpart-color-radiobox"]);
//# sourceMappingURL=et2_widget_color_radiobox.js.map