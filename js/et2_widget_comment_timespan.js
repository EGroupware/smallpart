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
exports.et2_smallpart_comment_timespan = void 0;
var et2_widget_videobar_1 = require("./et2_widget_videobar");
var et2_core_widget_1 = require("../../api/js/etemplate/et2_core_widget");
var et2_core_inheritance_1 = require("../../api/js/etemplate/et2_core_inheritance");
var et2_core_baseWidget_1 = require("../../api/js/etemplate/et2_core_baseWidget");
/**
 * Creates a timespan controller
 */
var et2_smallpart_comment_timespan = /** @class */ (function (_super) {
    __extends(et2_smallpart_comment_timespan, _super);
    /**
     * Constructor
     */
    function et2_smallpart_comment_timespan(_parent, _attrs, _child) {
        var _this = 
        // Call the inherited constructor
        _super.call(this, _parent, _attrs, et2_core_inheritance_1.ClassWithAttributes.extendAttributes(et2_smallpart_comment_timespan._attributes, _child || {})) || this;
        _this.widgets = { starttime: null, stoptime: null, starttime_picker: null, stoptime_picker: null };
        _this.div = null;
        _this.div = document.createElement("div");
        _this.div.classList.add("et2_" + _super.prototype.getType.call(_this));
        // starttime
        _this.widgets.starttime = et2_core_widget_1.et2_createWidget('int', {
            statustext: "start-time",
            label: "start-time",
            min: 0,
            onchange: _this._checkTimeConflicts.bind(_this)
        }, _this);
        // starttime picker
        _this.widgets.starttime_picker = et2_core_widget_1.et2_createWidget('buttononly', {
            statustext: "start-time picker",
            class: "glyphicon glyphicon-pushpin",
            onclick: _this._timePicker.bind(_this)
        }, _this);
        // stoptime
        _this.widgets.stoptime = et2_core_widget_1.et2_createWidget('int', {
            statustext: "stop-time",
            label: "stop-time",
            min: 0,
            class: "stoptime",
            onchange: _this._checkTimeConflicts.bind(_this)
        }, _this);
        // stoptime picker
        _this.widgets.stoptime_picker = et2_core_widget_1.et2_createWidget('buttononly', {
            statustext: "stop-time picker",
            class: "glyphicon glyphicon-pushpin",
            onclick: _this._timePicker.bind(_this)
        }, _this);
        _super.prototype.setDOMNode.call(_this, _this.div);
        return _this;
    }
    /**
     * Set videobar to use
     *
     * @param _id_or_widget
     */
    et2_smallpart_comment_timespan.prototype.set_videobar = function (_id_or_widget) {
        var _this = this;
        if (typeof _id_or_widget === 'string') {
            _id_or_widget = this.getRoot().getWidgetById(_id_or_widget);
        }
        if (_id_or_widget instanceof et2_widget_videobar_1.et2_smallpart_videobar) {
            this.videobar = _id_or_widget;
            this.videobar.video[0].addEventListener("et2_video.onReady." + this.videobar.id, function (_) {
                _this.set_widgets();
            });
        }
    };
    et2_smallpart_comment_timespan.prototype.set_starttime = function (_value) {
        this.widgets.starttime.set_value(_value);
    };
    et2_smallpart_comment_timespan.prototype.set_stoptime = function (_value) {
        this.widgets.stoptime.set_value(_value);
    };
    et2_smallpart_comment_timespan.prototype.set_widgets = function () {
        this.widgets.starttime.set_max(this.videobar.duration());
        this.widgets.stoptime.set_max(this.videobar.duration());
        this.widgets.starttime.set_value(this.options.starttime);
        this.widgets.stoptime.set_value(this.options.stoptime);
    };
    /**
     * Re-evaluate starttime/stoptime max&min values
     * @param _node
     * @param _widget
     */
    et2_smallpart_comment_timespan.prototype._checkTimeConflicts = function (_node, _widget) {
        if (_widget == this.widgets.starttime) {
            this.widgets.starttime.set_max(this.widgets.stoptime.get_value());
            if (this.widgets.starttime.get_value() < this.widgets.stoptime.get_value())
                this.widgets.stoptime.set_min(this.widgets.starttime.get_value());
        }
        else {
            this.widgets.stoptime.set_min(this.widgets.starttime.get_value());
            this.widgets.starttime.set_max(_widget.get_value());
        }
    };
    /**
     * time picker button click handler
     * @param _node
     * @param _widget
     * @private
     */
    et2_smallpart_comment_timespan.prototype._timePicker = function (_node, _widget) {
        if (_widget == this.widgets.starttime_picker) {
            this.widgets.starttime.set_value(Math.round(this.videobar.currentTime()));
        }
        else {
            this.widgets.stoptime.set_value(Math.round(this.videobar.currentTime()));
        }
    };
    et2_smallpart_comment_timespan._attributes = {
        videobar: {
            name: 'videobar',
            type: 'string',
            description: 'videobar this overlay is for',
        },
        starttime: {
            name: 'starttime',
            type: 'integer',
            description: 'comment starttime',
        },
        stoptime: {
            name: 'stoptime',
            type: 'integer',
            description: 'comment stoptime',
        }
    };
    return et2_smallpart_comment_timespan;
}(et2_core_baseWidget_1.et2_baseWidget));
exports.et2_smallpart_comment_timespan = et2_smallpart_comment_timespan;
et2_core_widget_1.et2_register_widget(et2_smallpart_comment_timespan, ["smallpart-comment-timespan"]);
//# sourceMappingURL=et2_widget_comment_timespan.js.map