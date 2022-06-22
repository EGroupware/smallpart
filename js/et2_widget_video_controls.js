"use strict";
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
exports.et2_smallpart_video_controls = void 0;
var et2_core_baseWidget_1 = require("../../api/js/etemplate/et2_core_baseWidget");
var et2_widget_videobar_1 = require("./et2_widget_videobar");
var et2_core_widget_1 = require("../../api/js/etemplate/et2_core_widget");
var et2_core_inheritance_1 = require("../../api/js/etemplate/et2_core_inheritance");
/**
 * Creates a Video controls
 */
var et2_smallpart_video_controls = /** @class */ (function (_super) {
    __extends(et2_smallpart_video_controls, _super);
    /**
     * Constructor
     */
    function et2_smallpart_video_controls(_parent, _attrs, _child) {
        var _this = 
        // Call the inherited constructor
        _super.call(this, _parent, _attrs, et2_core_inheritance_1.ClassWithAttributes.extendAttributes(et2_smallpart_video_controls._attributes, _child || {})) || this;
        _this.controls = { play: null, backward: null, forward: null };
        _this.div = null;
        _this.div = document.createElement("div");
        _this.div.classList.add("et2_" + _super.prototype.getType.call(_this));
        _this.controls.play = et2_core_widget_1.et2_createWidget('buttononly', {
            statustext: "play/pause",
            class: "glyphicon glyphicon-play button_std_controller",
            onclick: _this._onPlayCallback.bind(_this)
        }, _this);
        _this.controls.backward = et2_core_widget_1.et2_createWidget('buttononly', {
            statustext: "backward",
            class: "glyphicon custom-font-icon-backward button_std_backward button_std_controller",
            onclick: _this._onBackwardCallback.bind(_this)
        }, _this);
        _this.controls.forward = et2_core_widget_1.et2_createWidget('buttononly', {
            statustext: "forward",
            class: "glyphicon custom-font-icon-forward button_std_forward button_std_controller",
            onclick: _this._onForwardCallback.bind(_this)
        }, _this);
        _super.prototype.setDOMNode.call(_this, _this.div);
        return _this;
    }
    /**
     * Set videobar to use
     *
     * @param _id_or_widget
     */
    et2_smallpart_video_controls.prototype.set_videobar = function (_id_or_widget) {
        var _this = this;
        if (typeof _id_or_widget === 'string') {
            _id_or_widget = this.getRoot().getWidgetById(_id_or_widget);
        }
        if (_id_or_widget instanceof et2_widget_videobar_1.et2_smallpart_videobar) {
            this.videobar = _id_or_widget;
            this.videobar.video[0].addEventListener("et2_video.onReady." + this.videobar.id, function (_) {
                _this.getDOMNode().style.width = _this.videobar.video.width() + "px";
            });
        }
    };
    et2_smallpart_video_controls.prototype._onPlayCallback = function (_event, _widget, _node) {
        if (this.videobar.paused()) {
            this.videobar.play();
            this.controls.play.getDOMNode().classList.remove('glyphicon-play');
            this.controls.play.getDOMNode().classList.add('glyphicon-pause');
        }
        else {
            this.videobar.pause_video();
            this.controls.play.getDOMNode().classList.add('glyphicon-play');
            this.controls.play.getDOMNode().classList.remove('glyphicon-pause');
        }
        if (typeof this.options.onplay_callback == 'function') {
            this.options.onplay_callback.call(this, _event, _widget, _node);
        }
    };
    et2_smallpart_video_controls.prototype._onForwardCallback = function (_event, _widget, _node) {
        if (typeof this.options.onforward_callback == 'function') {
            this.options.onforward_callback.call(this, _event, _widget, _node);
        }
        if (this.videobar.currentTime() + 10 <= this.videobar.duration()) {
            this.videobar.seek_video(this.videobar.currentTime() + 10);
        }
    };
    et2_smallpart_video_controls.prototype._onBackwardCallback = function (_event, _widget, _node) {
        if (typeof this.options.onbackward_callback == 'function') {
            this.options.onbackward_callback.call(this, _event, _widget, _node);
        }
        if (this.videobar.currentTime() - 10 >= 0) {
            this.videobar.seek_video(this.videobar.currentTime() - 10);
        }
    };
    et2_smallpart_video_controls._attributes = {
        videobar: {
            name: 'videobar',
            type: 'string',
            description: 'videobar this overlay is for',
        },
        onplay_callback: {
            name: 'play callback',
            type: 'js',
            description: 'callback function on play',
        },
        onpause_callback: {
            name: 'pause callback',
            type: 'js',
            description: 'callback function on pause',
        },
        onforward_callback: {
            name: 'forward callback',
            type: 'js',
            description: 'callback function on forward',
        },
        onbackward_callback: {
            name: 'backward callback',
            type: 'js',
            description: 'callback function on backward',
        }
    };
    return et2_smallpart_video_controls;
}(et2_core_baseWidget_1.et2_baseWidget));
exports.et2_smallpart_video_controls = et2_smallpart_video_controls;
et2_core_widget_1.et2_register_widget(et2_smallpart_video_controls, ["smallpart-video-controls"]);
//# sourceMappingURL=et2_widget_video_controls.js.map