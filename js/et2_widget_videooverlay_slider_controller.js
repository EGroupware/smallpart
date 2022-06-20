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
exports.et2_smallpart_videooverlay_slider_controller = void 0;
var et2_core_baseWidget_1 = require("../../api/js/etemplate/et2_core_baseWidget");
var et2_widget_videobar_1 = require("./et2_widget_videobar");
var et2_core_widget_1 = require("../../api/js/etemplate/et2_core_widget");
var et2_core_inheritance_1 = require("../../api/js/etemplate/et2_core_inheritance");
/**
 * slider-controller creates a sliderbar for demonstrating all elements, consists of marking system
 * and selection.
 */
var et2_smallpart_videooverlay_slider_controller = /** @class */ (function (_super) {
    __extends(et2_smallpart_videooverlay_slider_controller, _super);
    /**
     * Constructor
     */
    function et2_smallpart_videooverlay_slider_controller(_parent, _attrs, _child) {
        var _this = 
        // Call the inherited constructor
        _super.call(this, _parent, _attrs, et2_core_inheritance_1.ClassWithAttributes.extendAttributes(et2_smallpart_videooverlay_slider_controller._attributes, _child || {})) || this;
        _this.marks_positions = [];
        _this.marks = [];
        _this._interval = null;
        _this.div = null;
        _this.div = jQuery(document.createElement("div"))
            .addClass("et2_" + _super.prototype.getType.call(_this));
        _super.prototype.setDOMNode.call(_this, _this.div[0]);
        return _this;
    }
    /**
     * Set videobar to use
     *
     * @param _id_or_widget
     */
    et2_smallpart_videooverlay_slider_controller.prototype.set_videobar = function (_id_or_widget) {
        var _this = this;
        if (typeof _id_or_widget === 'string') {
            _id_or_widget = this.getRoot().getWidgetById(_id_or_widget);
        }
        if (_id_or_widget instanceof et2_widget_videobar_1.et2_smallpart_videobar) {
            this.videobar = _id_or_widget;
            var self_1 = this;
            if (this.options.seekable) {
                this.div.on('click', function (e) {
                    self_1.videobar.slider_onclick.call(self_1.videobar, e);
                    if (typeof self_1.onclick_slider_callback == 'function')
                        self_1.onclick_slider_callback.call(self_1, e);
                });
            }
            this.videobar.video[0].addEventListener("et2_video.onReady." + this.videobar.id, function (_) {
                _this.getDOMNode().style.width = _this.videobar.video.width() + "px";
            });
        }
    };
    /**
     * set given elements as actual marks on sliderbar
     * @param _elements
     */
    et2_smallpart_videooverlay_slider_controller.prototype.set_value = function (_elements) {
        var _this = this;
        this.elements = _elements;
        var self = this;
        this._checkVideoIsLoaded().then(function (_) {
            _this.marks_positions = [];
            _this.marks = [];
            for (var i = _this.getChildren().length - 1; i >= 0; i--) {
                _this.getChildren()[i].destroy();
            }
            _this.elements.forEach(function (_element, _idx) {
                self.marks[_element.id] = et2_core_widget_1.et2_createWidget('description', {
                    id: et2_smallpart_videooverlay_slider_controller.mark_id_prefix + _element.id,
                    class: 'et2_label'
                }, self);
                if (self.options.seekable) {
                    self.marks[_element.id].onclick = function (_event, _widget) {
                        _event.stopImmediatePropagation();
                        if (typeof self.options.onclick_callback == 'function' && self.onclick_callback(_event, _widget)) {
                            self._set_selected(_widget);
                        }
                    };
                }
                self.marks[_element.id].doLoadingFinished();
                var pos = self._find_position(self.marks_positions, {
                    left: self.videobar._vtimeToSliderPosition(_element.starttime),
                    width: self.videobar._vtimeToSliderPosition(_element.duration ? _element.duration : 1),
                    row: 0
                }, 0);
                self.marks_positions.push(pos);
                // set its actuall position in DOM
                jQuery(self.marks[_element.id].getDOMNode())
                    .css({
                    left: pos.left + 'px',
                    width: pos.width + 'px',
                    top: pos.row != 0 ? pos.row * (5 + 2) : pos.row + 'px',
                    "background-color": "#" + (_element.color ? _element.color : '')
                })
                    .addClass(_element.class);
            });
        });
    };
    /**
     * set currently selected mark
     * @param _widget
     */
    et2_smallpart_videooverlay_slider_controller.prototype._set_selected = function (_widget) {
        this._selected = _widget;
        _widget.set_class(_widget.class + " selected");
        this.marks.forEach(function (_mark) {
            if (_mark.id != _widget.id) {
                jQuery(_mark.getDOMNode()).removeClass('selected');
            }
        });
    };
    /**
     * get current selected mark
     */
    et2_smallpart_videooverlay_slider_controller.prototype.get_selected = function () {
        return {
            widget: this._selected,
            id: this._selected.id.split(et2_smallpart_videooverlay_slider_controller.mark_id_prefix)[1]
        };
    };
    /**
     * find a free spot on sliderbar for given mark's position
     * @param _marks_postions all current occupide positions
     * @param _pos mark position
     * @param _row initial row to start with
     *
     * @return OverlaySliderControllerMarkPositionType
     * @private
     */
    et2_smallpart_videooverlay_slider_controller.prototype._find_position = function (_marks_postions, _pos, _row) {
        if (_marks_postions.length == 0)
            return { left: _pos.left, width: _pos.width, row: _row };
        var conflict = false;
        for (var _i = 0, _marks_postions_1 = _marks_postions; _i < _marks_postions_1.length; _i++) {
            var i = _marks_postions_1[_i];
            if (i.row == _row) {
                if ((_pos.left > i.left + i.width) || (_pos.left + _pos.width < i.left)) {
                    conflict = false;
                }
                else {
                    conflict = true;
                    break;
                }
            }
        }
        if (!conflict)
            return { left: _pos.left, width: _pos.width, row: _row };
        return this._find_position(_marks_postions, _pos, _row + 1);
    };
    et2_smallpart_videooverlay_slider_controller.prototype.set_seek_position = function (_value) {
        var value = Math.floor(_value);
        this.div.css({
            background: 'linear-gradient(90deg, rgb(174 173 173) ' + value + 'px, rgb(206 206 206) ' + value + 'px, rgb(206 206 206) 100%)'
        });
    };
    /**
     * Promise to check the video is loaded
     * @private
     */
    et2_smallpart_videooverlay_slider_controller.prototype._checkVideoIsLoaded = function () {
        var _this = this;
        clearInterval(this._interval);
        return new Promise(function (_resolved, _rejected) {
            if (_this.videobar.duration() > 0) {
                clearInterval(_this._interval);
                return _resolved();
            }
            _this._interval = setInterval(function (_) {
                if (_this.videobar.duration() > 0) {
                    clearInterval(_this._interval);
                    _resolved();
                    return;
                }
            }, 1000);
        });
    };
    et2_smallpart_videooverlay_slider_controller.prototype.resize = function (_height) {
        this.getDOMNode().style.width = this.videobar.video[0].clientWidth + "px";
        this.set_value(this.elements);
    };
    et2_smallpart_videooverlay_slider_controller._attributes = {
        onclick_callback: {
            name: 'click callback',
            type: 'js',
            description: 'callback function on elements',
        },
        videobar: {
            name: 'videobar',
            type: 'string',
            description: 'videobar this overlay is for',
        },
        onclick_slider_callback: {
            name: 'on slider click callback',
            type: 'js',
            description: 'callback function on slider bar',
        },
        seekable: {
            name: 'seekable',
            type: 'boolean',
            description: 'Make slider active for seeking in timeline',
            default: true
        }
    };
    et2_smallpart_videooverlay_slider_controller.mark_id_prefix = "slider-tag-";
    return et2_smallpart_videooverlay_slider_controller;
}(et2_core_baseWidget_1.et2_baseWidget));
exports.et2_smallpart_videooverlay_slider_controller = et2_smallpart_videooverlay_slider_controller;
et2_core_widget_1.et2_register_widget(et2_smallpart_videooverlay_slider_controller, ["smallpart-videooverlay-slider-controller"]);
//# sourceMappingURL=et2_widget_videooverlay_slider_controller.js.map