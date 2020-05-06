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
var et2_widget_video_1 = require("../../api/js/etemplate/et2_widget_video");
var et2_core_widget_1 = require("../../api/js/etemplate/et2_core_widget");
var et2_core_inheritance_1 = require("../../api/js/etemplate/et2_core_inheritance");
var et2_smallpart_videobar = /** @class */ (function (_super) {
    __extends(et2_smallpart_videobar, _super);
    /**
     *
     * @memberOf et2_DOMWidget
     */
    function et2_smallpart_videobar(_parent, _attrs, _child) {
        var _this = 
        // Call the inherited constructor
        _super.call(this, _parent, _attrs, et2_core_inheritance_1.ClassWithAttributes.extendAttributes(et2_smallpart_videobar._attributes, _child || {})) || this;
        _this.container = null;
        _this.wrapper = null;
        _this.slider = null;
        _this.marking = null;
        _this.slider_progressbar = null;
        // wrapper DIV container for video tag and marking selector
        _this.wrapper = jQuery(document.createElement('div'))
            .append(_this.video)
            .addClass('videobar_wrapper');
        // widget container
        _this.container = jQuery(document.createElement('div'))
            .append(_this.wrapper)
            .addClass('et2_smallpart_videobar videobar_container');
        // slider div
        _this.slider = jQuery(document.createElement('div'))
            .appendTo(_this.container)
            .addClass('videobar_slider');
        // marking div
        _this.marking = jQuery(document.createElement('div'))
            .addClass('videobar_marking');
        // slider progressbar span
        _this.slider_progressbar = jQuery(document.createElement('span'))
            .addClass('videobar_slider_progressbar')
            .appendTo(_this.slider);
        if (_this.options.marking_enabled)
            _this.wrapper.append(_this.marking);
        _this._buildHandlers();
        _this.setDOMNode(_this.container[0]);
        return _this;
    }
    et2_smallpart_videobar.prototype._buildHandlers = function () {
        var self = this;
        this.slider.on('click', function (e) {
            self._slider_onclick.call(self, e);
        });
    };
    et2_smallpart_videobar.prototype._slider_onclick = function (e) {
        this.slider_progressbar.css({ width: e.offsetX });
        this.video[0]['currentTime'] = e.offsetX * this.video[0]['duration'] / this.slider.width();
    };
    et2_smallpart_videobar.prototype.set_src = function (_value) {
        _value = 'smallpart/' + _value;
        _super.prototype.set_src.call(this, _value);
    };
    et2_smallpart_videobar.prototype.doLoadingFinished = function () {
        _super.prototype.doLoadingFinished.call(this);
        var self = this;
        this.video[0].addEventListener("loadedmetadata", function () {
            // this will make sure that slider and video are synced
            self.slider.width(self.video.width());
        });
        return false;
    };
    et2_smallpart_videobar.prototype._vtimeToSliderPosition = function (_vtime) {
        return this.slider.width() / this.video[0]['duration'] * parseInt(_vtime);
    };
    et2_smallpart_videobar.prototype.set_slider_tags = function (_comments) {
        this.slider.empty();
        for (var i in _comments) {
            this.slider.append(jQuery(document.createElement('span'))
                .offset({ left: this._vtimeToSliderPosition(_comments[i]['comment_starttime']) })
                .addClass('commentOnSlider'));
        }
    };
    et2_smallpart_videobar.prototype.set_marking_enabled = function (_state) {
        this.marking.toggle(_state);
    };
    et2_smallpart_videobar._attributes = {
        "marking_enabled": {
            "name": "Disabled",
            "type": "boolean",
            "description": "Defines whether this widget is visible.  Not to be confused with an input widget's HTML attribute 'disabled'.",
            "default": false
        },
        "marking_callback": {},
        "slider_onclick": {
            "type": "js"
        },
        "slider_tags": {
            "name": "slider tags",
            "type": "any",
            "description": "comment tags on slider",
            "default": {}
        }
    };
    return et2_smallpart_videobar;
}(et2_widget_video_1.et2_video));
exports.et2_smallpart_videobar = et2_smallpart_videobar;
et2_core_widget_1.et2_register_widget(et2_smallpart_videobar, ["smallpart-videobar"]);
//# sourceMappingURL=et2_widget_videobar.js.map