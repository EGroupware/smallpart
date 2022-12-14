"use strict";
/**
 * EGroupware - SmallParT - livefeedback slider controller widget
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
var __assign = (this && this.__assign) || function () {
    __assign = Object.assign || function(t) {
        for (var s, i = 1, n = arguments.length; i < n; i++) {
            s = arguments[i];
            for (var p in s) if (Object.prototype.hasOwnProperty.call(s, p))
                t[p] = s[p];
        }
        return t;
    };
    return __assign.apply(this, arguments);
};
Object.defineProperty(exports, "__esModule", { value: true });
exports.et2_smallpart_livefeedback_slider_controller = void 0;
/*egw:uses
    /smallpart/js/chart/chart.min.js;
*/
var et2_widget_videobar_1 = require("./et2_widget_videobar");
var et2_core_widget_1 = require("../../api/js/etemplate/et2_core_widget");
var et2_core_inheritance_1 = require("../../api/js/etemplate/et2_core_inheritance");
require("./chart/chart.min");
var et2_core_baseWidget_1 = require("../../api/js/etemplate/et2_core_baseWidget");
/**
 * slider-controller creates a sliderbar for demonstrating all elements, consists of marking system
 * and selection.
 */
var et2_smallpart_livefeedback_slider_controller = /** @class */ (function (_super) {
    __extends(et2_smallpart_livefeedback_slider_controller, _super);
    /**
     * Constructor
     */
    function et2_smallpart_livefeedback_slider_controller(_parent, _attrs, _child) {
        var _this = 
        // Call the inherited constructor
        _super.call(this, _parent, _attrs, et2_core_inheritance_1.ClassWithAttributes.extendAttributes(et2_smallpart_livefeedback_slider_controller._attributes, _child || {})) || this;
        /**
         * contains categories
         * @private
         */
        _this._cats = [];
        /**
         * contains video data
         * @private
         */
        _this._video = [];
        /**
         *
         * @private
         */
        _this._interval = null;
        /**
         * wrap container
         */
        _this.div = null;
        /**
         * contians charts canvas elements
         */
        _this.canvases = [];
        /**
         * contains created charts
         */
        _this.charts = [];
        /**
         * contains chart configs
         */
        _this.configs = {};
        _this.div = jQuery(document.createElement("div"));
        _this.div.addClass('et2_smallpart-livefeedback-slider-controller');
        _this.charts = [];
        var self = _this;
        _this.configs = {
            type: 'bar',
            options: {
                responsive: true,
                maintainAspectRatio: true,
                scales: {
                    x: {
                        stacked: true,
                    },
                    y: {
                        stacked: true,
                        ticks: {
                            // forces step size to be 1 units
                            stepSize: 10
                        }
                    }
                },
                interaction: {
                    mode: 'dataset'
                }
            }
        };
        _this._cats = _this.getInstanceManager().widgetContainer.getArrayMgr('content').getEntry('cats');
        _this._video = _this.getInstanceManager().widgetContainer.getArrayMgr('content').getEntry('video');
        _this.options.timeSlot = _this._video && _this._video['livefeedback'] ? parseInt(_this._video['livefeedback']['session_interval']) * 60 : 60;
        _super.prototype.setDOMNode.call(_this, _this.div[0]);
        return _this;
    }
    /**
     * Set videobar to use
     *
     * @param _id_or_widget
     */
    et2_smallpart_livefeedback_slider_controller.prototype.set_videobar = function (_id_or_widget) {
        if (typeof _id_or_widget === 'string') {
            _id_or_widget = this.getRoot().getWidgetById(_id_or_widget);
        }
        if (_id_or_widget instanceof et2_widget_videobar_1.et2_smallpart_videobar) {
            this.videobar = _id_or_widget;
        }
    };
    /**
     * set given elements as actual marks on sliderbar
     *
     * @param _elements
     * [{
     * 	comments: [{}],
     * 	title: string
     * }]
     */
    et2_smallpart_livefeedback_slider_controller.prototype.set_value = function (_elements) {
        var _this = this;
        this.elements = _elements || [];
        var self = this;
        this._checkVideoIsLoaded().then(function (_) {
            for (var i = _this.charts.length - 1; i >= 0; i--) {
                _this.charts[i].destroy();
            }
            _this.elements.forEach(function (_element, _idx) {
                if (_element && _element.comments) {
                    var configs_1 = __assign(__assign({}, _this.configs), {
                        data: {
                            labels: [],
                            datasets: []
                        },
                        options: __assign(__assign(__assign({}, _this.configs.options), {
                            plugins: {
                                animation: false,
                                title: {
                                    display: true,
                                    text: _element.title,
                                }
                            }
                        }), { onClick: function (e, value) {
                                if (!_this.options.seekable || !value.length)
                                    return;
                                var canvasPosition = Chart.helpers.getRelativePosition(e, self.charts[_idx]);
                                var labelIndex = self.charts[_idx].scales.x.getValueForPixel(canvasPosition.x);
                                // convert minute label to second in order to seek right time in video
                                self.videobar.seek_video(configs_1.data.labels[labelIndex] * 60);
                            } })
                    });
                    if (!_this.canvases[_idx]) {
                        _this.canvases[_idx] = document.createElement('canvas');
                        _this.canvases[_idx].setAttribute('id', _this.id + '-canvas-' + _idx);
                        _this.div.append(_this.canvases[_idx]);
                    }
                    var data_1 = {};
                    _element.comments.forEach(function (_c, _i) {
                        var cat_id = _c['comment_cat'].split(":").pop();
                        if (typeof data_1[cat_id] === 'undefined')
                            data_1[cat_id] = [];
                        data_1[cat_id].push(_c.comment_starttime - _c.comment_starttime % _this.options.timeSlot);
                    });
                    var negativeCatId_1 = Object.keys(data_1).length > 1 ? Object.keys(data_1).pop() : null; //TODO: read it from set options
                    Object.keys(data_1).forEach(function (_cat_id) {
                        var cat = _this._fetchCatInfo(_cat_id);
                        var d = [];
                        data_1[_cat_id].forEach(function (_d) {
                            var timeVal = _d / _this.options.timeSlot;
                            var index = _this._findIndexofDataItem(d, timeVal);
                            if (index >= 0) {
                                d[index]['y'] = d[index]['y'] + ((_cat_id == negativeCatId_1) ? -1 : 1);
                            }
                            else {
                                d.push({ x: timeVal, y: (_cat_id == negativeCatId_1) ? -1 : 1 });
                                configs_1.data.labels.push(timeVal); // label the time in minute
                            }
                        });
                        configs_1.data.datasets.push({
                            label: cat.cat_name,
                            data: d.sort(function (a, b) { return a.x > b.x ? 1 : -1; }),
                            backgroundColor: cat.cat_color,
                            parsing: {
                                yAxisKey: 'y',
                                xAxisKey: 'x'
                            }
                        });
                    });
                    if (_this.options.showEmptyLabels) {
                        configs_1.data.labels = Array.from({ length: (self.videobar.duration()) / self.options.timeSlot + 1 }, function (_, i) { return i * self.options.timeSlot / 60; });
                    }
                    else {
                        // labels need to be unique otherwise the charts get messed up
                        configs_1.data.labels = configs_1.data.labels.filter(function (v, i, a) { return a.indexOf(v) === i; }).sort(function (a, b) { return a > b ? 1 : -1; });
                    }
                    _this.charts[_idx] = new Chart(_this.canvases[_idx], configs_1);
                }
            });
        });
    };
    /**
     * Find the index number for the given value in data array
     * @param _data array of data
     * @param _value value to look for
     * @return returns index number
     * @private
     */
    et2_smallpart_livefeedback_slider_controller.prototype._findIndexofDataItem = function (_data, _value) {
        var index = 0;
        return _data.findIndex(function (_d) { return _d.x == _value; });
    };
    /**
     * Fetch category info for the given cat_id
     * @param _cat_id
     * @return returns array of cat data
     * @private
     */
    et2_smallpart_livefeedback_slider_controller.prototype._fetchCatInfo = function (_cat_id) {
        var cats = [];
        this._cats.forEach(function (_a) {
            cats.push(_a);
            if (_a.subs) {
                _a.subs.forEach(function (_c) {
                    cats.push(_c);
                });
            }
        });
        return cats.filter(function (_cat) { return _cat.cat_id == _cat_id; })[0];
    };
    /**
     * Promise to check the video is loaded
     * @private
     */
    et2_smallpart_livefeedback_slider_controller.prototype._checkVideoIsLoaded = function () {
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
    et2_smallpart_livefeedback_slider_controller._attributes = {
        videobar: {
            name: 'videobar',
            type: 'string',
            description: 'videobar this overlay is for',
        },
        seekable: {
            name: 'seekable',
            type: 'boolean',
            description: 'Make slider active for seeking in timeline',
            default: true
        },
        timeSlot: {
            name: 'time slot',
            type: 'integer',
            description: 'a time slot to devide lables. Default is 60 seconds.',
            default: 60
        },
        positiveCatId: {
            name: 'positive category id',
            type: 'string',
            description: 'Category id that supposed to be used as positive data set',
        },
        negativeCatId: {
            name: 'negative category id',
            type: 'string',
            description: 'Category id that supposed to be used as negative data set',
        },
        showEmptyLabels: {
            name: 'show empty labels',
            type: 'boolean',
            description: 'Show all devided time labels in the x axis even the ones with no data',
            default: true
        }
    };
    return et2_smallpart_livefeedback_slider_controller;
}(et2_core_baseWidget_1.et2_baseWidget));
exports.et2_smallpart_livefeedback_slider_controller = et2_smallpart_livefeedback_slider_controller;
et2_core_widget_1.et2_register_widget(et2_smallpart_livefeedback_slider_controller, ["smallpart-livefeedback-slider-controller"]);
//# sourceMappingURL=et2_widget_livefeedback_slider_controller.js.map