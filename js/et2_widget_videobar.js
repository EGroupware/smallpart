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
exports.et2_smallpart_videobar = void 0;
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
        _this.timer = null;
        _this.watermark = null;
        _this.slider_progressbar = null;
        _this.comments = null;
        _this.mark_ratio = 0;
        _this.marking_color = 'ffffff';
        _this.marks = [];
        _this.marking_readonly = true;
        _this._scrolled = [];
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
            .addClass('videobar_marking container');
        _this.marking.append(jQuery(document.createElement('div'))
            .addClass('markingMask maskOn'));
        _this.marking.append(jQuery(document.createElement('div'))
            .addClass('marksContainer'));
        // slider progressbar span
        _this.slider_progressbar = jQuery(document.createElement('span'))
            .addClass('videobar_slider_progressbar')
            .appendTo(_this.slider);
        _this.wrapper.append(_this.marking);
        _this._buildHandlers();
        // timer span
        _this.timer = et2_core_widget_1.et2_createWidget('smallpart-videotime', {}, _this);
        _this._setWatermark();
        //@TODO: this should not be necessary but for some reason attach to the dom
        // not working on et2_creatWidget there manully attach it here.
        jQuery(_this.timer.getDOMNode()).attr('id', _this.id + "[timer]");
        _this.container.append(_this.timer.getDOMNode());
        if (_this.options.stop_contextmenu)
            _this.video.on('contextmenu', function () { return false; });
        _this.setDOMNode(_this.container[0]);
        return _this;
    }
    et2_smallpart_videobar.prototype._buildHandlers = function () {
        var self = this;
        if (this.options.seekable) {
            this.getSliderDOMNode().on('click', function (e) {
                self.slider_onclick.call(self, e);
            });
        }
    };
    et2_smallpart_videobar.prototype._setWatermark = function () {
        var options = this.getArrayMgr('content').getEntry('course_options');
        var self = this;
        var name = '';
        if (options & et2_smallpart_videobar.course_options_video_watermark) {
            egw.accountData(egw.user('account_id'), 'account_fullname', false, function (_data) {
                name = _data[egw.user('account_id')];
                window.setInterval(function () {
                    var node = jQuery(document.createElement('span')).attr('style', 'display:block !important;' +
                        'visibility:visible !important;background:white !important;color:black !important;' +
                        'width:auto !important;height:auto !important;position:absolute !important;' +
                        'top:20px !important;left:20px !important;bottom:auto !important;right:auto !important;')
                        .text(name)
                        .appendTo(self.wrapper);
                    window.setTimeout(function () {
                        node.remove();
                    }, 1000);
                }, 10000);
            }, egw);
        }
    };
    et2_smallpart_videobar.prototype.slider_onclick = function (e) {
        if (!this.options.seekable)
            return;
        this.slider_progressbar.css({ width: e.offsetX });
        this._scrolled = [];
        this.previousTime(this.currentTime());
        this.currentTime(e.offsetX * this.duration() / this.getSliderDOMNode().width());
        this.timer.set_value(this.currentTime());
        if (typeof this.slider_callback == "function")
            this.slider_callback(this, this);
    };
    et2_smallpart_videobar.prototype.set_seekable = function (_value) {
        this.options.seekable = _value;
    };
    et2_smallpart_videobar.prototype.doLoadingFinished = function () {
        _super.prototype.doLoadingFinished.call(this);
        var self = this;
        this.video[0].addEventListener("et2_video.onReady." + this.id, function () {
            self.videoLoadnigIsFinished();
        });
        return false;
    };
    et2_smallpart_videobar.prototype._vtimeToSliderPosition = function (_vtime) {
        return this.slider.width() / this.duration() * parseFloat(_vtime);
    };
    et2_smallpart_videobar.prototype.set_slider_tags = function (_comments) {
        this.comments = _comments;
        // need to wait video is loaded before setting tags
        if (this.video.width() == 0)
            return;
        this.slider.empty();
        this.slider.append(this.slider_progressbar);
        for (var i in this.comments) {
            if (!this.comments[i])
                continue;
            this.slider.append(jQuery(document.createElement('span'))
                .offset({ left: this._vtimeToSliderPosition(this.comments[i]['comment_starttime']) })
                .css({ 'background-color': '#' + this.comments[i]['comment_color'] })
                .attr('data-id', this.comments[i]['comment_id'])
                .addClass('commentOnSlider commentColor' + this.comments[i]['comment_color']));
        }
    };
    et2_smallpart_videobar.prototype.set_marking_readonly = function (_state) {
        this.marking_readonly = _state;
    };
    et2_smallpart_videobar.prototype.set_marking_color = function (_color) {
        this.marking_color = _color;
    };
    et2_smallpart_videobar.prototype.set_marking_enabled = function (_state, _callback) {
        var self = this;
        var isDrawing = false;
        this.getMarkingNode().toggle(_state);
        var drawing = function (e) {
            if (e.target.nodeName !== "SPAN" && !self.marking_readonly) {
                var pixelX = Math.floor(e.originalEvent.offsetX / self.mark_ratio) * self.mark_ratio;
                var pixelY = Math.floor(e.originalEvent.offsetY / self.mark_ratio) * self.mark_ratio;
                var mark = {
                    x: self._convertMarkedPixelX2Percent(pixelX),
                    y: self._convertMarkedPixelY2Percent(pixelY),
                    c: self.marking_color
                };
                self._addMark(mark);
                _callback(mark);
            }
        };
        if (_state) {
            this.getMarkingNode().find('.marksContainer')
                .off()
                .on('mousedown', function () {
                console.log('mousedown');
                isDrawing = true;
            })
                .on('mouseup', function (e) {
                isDrawing = false;
                drawing(e);
            })
                .on('mousemove', function (e) {
                if (isDrawing === true) {
                    drawing(e);
                }
            });
        }
    };
    et2_smallpart_videobar.prototype.setMarkingMask = function (_state) {
        if (_state) {
            this.getMarkingNode().find('.markingMask').addClass('maskOn');
        }
        else {
            this.getMarkingNode().find('.markingMask').removeClass('maskOn');
        }
    };
    et2_smallpart_videobar.prototype.setMarksState = function (_state) {
        this.getMarkingNode().find('.marksContainer').toggle(_state);
    };
    et2_smallpart_videobar.prototype.setMarks = function (_marks) {
        var self = this;
        // clone the array to avoid missing its original content
        var $marksContainer = this.getMarkingNode().find('.marksContainer').empty();
        this.marks = (_marks === null || _marks === void 0 ? void 0 : _marks.slice(0)) || [];
        this.mark_ratio = parseFloat((this.video.width() / 80).toPrecision(4));
        for (var i in _marks) {
            $marksContainer.append(jQuery(document.createElement('span'))
                .offset({ left: this._convertMarkPercentX2Pixel(_marks[i]['x']), top: this._convertMarkPercentY2Pixel(_marks[i]['y']) })
                .css({
                "background-color": "#" + _marks[i]['c'],
                "width": this.mark_ratio,
                "height": this.mark_ratio
            })
                .attr('data-color', _marks[i]['c'])
                .click(function () {
                if (!self.marking_readonly)
                    self._removeMark(self._getMark(this), this);
            })
                .addClass('marks'));
        }
    };
    et2_smallpart_videobar.prototype.getMarks = function () {
        if (this.marks)
            return this.marks;
        var $marks = this.getMarkingNode().find('.marksContainer').find('span.marks');
        var marks = [];
        var self = this;
        $marks.each(function () {
            marks.push({
                x: self._convertMarkedPixelX2Percent(parseFloat(this.style.left)),
                y: self._convertMarkedPixelY2Percent(parseFloat(this.style.top)),
                c: this.dataset['color']
            });
        });
        this.marks = marks;
        return marks;
    };
    et2_smallpart_videobar.prototype._getMark = function (_node) {
        return [{
                x: this._convertMarkedPixelX2Percent(parseFloat(_node.style.left)),
                y: this._convertMarkedPixelY2Percent(parseFloat(_node.style.top)),
                c: _node.dataset['color']
            }];
    };
    et2_smallpart_videobar.prototype._addMark = function (_mark) {
        this.marks.push(_mark);
        this.setMarks(this.marks);
    };
    et2_smallpart_videobar.prototype.removeMarks = function () {
        this.marks = [];
        this.getMarkingNode().find('.marksContainer').find('span.marks').remove();
    };
    et2_smallpart_videobar.prototype._removeMark = function (_mark, _node) {
        for (var i in this.marks) {
            if (this.marks[i]['x'] == _mark[0]['x'] && this.marks[i]['y'] == _mark[0]['y'])
                this.marks.splice(i, 1);
        }
        if (_node)
            jQuery(_node).remove();
    };
    et2_smallpart_videobar.prototype._convertMarkedPixelX2Percent = function (_x) {
        return parseFloat((_x / this.video.width() / 0.01).toPrecision(4));
    };
    et2_smallpart_videobar.prototype._convertMarkedPixelY2Percent = function (_y) {
        return parseFloat((_y / this.video.height() / 0.01).toPrecision(4));
    };
    et2_smallpart_videobar.prototype._convertMarkPercentX2Pixel = function (_x) {
        return _x * this.video.width() * 0.01;
    };
    et2_smallpart_videobar.prototype._convertMarkPercentY2Pixel = function (_y) {
        return _y * this.video.height() * 0.01;
    };
    /**
     * Seek to a time / position
     *
     * @param _vtime in seconds
     */
    et2_smallpart_videobar.prototype.seek_video = function (_vtime) {
        _super.prototype.seek_video.call(this, _vtime);
        this._scrolled = [];
        this.timer.set_value(this.currentTime());
        this.slider_progressbar.css({ width: this._vtimeToSliderPosition(_vtime) });
    };
    /**
     * Play video
     */
    et2_smallpart_videobar.prototype.play_video = function (_ended_callback, _onTagCallback) {
        var self = this;
        var ended_callback = _ended_callback;
        this._scrolled = [];
        return _super.prototype.play_video.call(this).then(function () {
            self.video[0].addEventListener('et2_video.onTimeUpdate.' + self.id, function (_event) {
                var currentTime = self.currentTime();
                self.slider_progressbar.css({ width: Math.round(self._vtimeToSliderPosition(currentTime)) });
                self.timer.set_value(self.currentTime());
                if (typeof ended_callback == "function" && self.ended()) {
                    ended_callback.call();
                    self.pause_video();
                }
                if (typeof _onTagCallback == "function") {
                    for (var i in self.comments) {
                        if (Math.floor(currentTime) == parseInt(self.comments[i]['comment_starttime'])
                            && (self._scrolled.length == 0 || self._scrolled.indexOf(parseInt(self.comments[i]['comment_id'])) == -1)) {
                            _onTagCallback.call(this, self.comments[i]['comment_id']);
                            self._scrolled.push(parseInt(self.comments[i]['comment_id']));
                        }
                    }
                }
                if (typeof self.ontimeupdate_callback == "function") {
                    self.ontimeupdate_callback.call(this, currentTime);
                }
            });
        });
    };
    /**
     * Pause video
     */
    et2_smallpart_videobar.prototype.pause_video = function () {
        _super.prototype.pause_video.call(this);
    };
    et2_smallpart_videobar.prototype.videoLoadnigIsFinished = function () {
        _super.prototype.videoLoadnigIsFinished.call(this);
        // this will make sure that slider and video are synced
        this.slider.width(this.video.width());
        this.set_slider_tags(this.comments);
        this.getMarkingNode().css({ width: this.video.width(), height: this.video.height() });
    };
    et2_smallpart_videobar.prototype.resize = function (_height) {
        this.slider.width('auto');
        this.getMarkingNode().width('auto');
        this.slider.width(this.video.width());
        this.getMarkingNode().css({ width: this.video.width(), height: this.video.height() });
        this.slider_progressbar.css({ width: this._vtimeToSliderPosition(this.currentTime()) });
        //redraw marks and tags to get the right ratio
        this.setMarks(this.getMarks());
        this.set_slider_tags(this.comments);
        if (typeof this.options.onresize_callback == 'function') {
            this.options.onresize_callback.call(this, this.video.width(), this.video.height(), this._vtimeToSliderPosition(this.currentTime()));
        }
    };
    /**
     * return slider dom node as jquery object
     */
    et2_smallpart_videobar.prototype.getSliderDOMNode = function () {
        return this.slider;
    };
    et2_smallpart_videobar.prototype.getMarkingNode = function () {
        return this.marking;
    };
    et2_smallpart_videobar._attributes = {
        "marking_enabled": {
            "name": "Marking",
            "type": "boolean",
            "description": "",
            "default": false
        },
        "marking_readonly": {
            "name": "Marking readonly",
            "type": "boolean",
            "description": "",
            "default": true
        },
        "marking_color": {
            "name": "Marking color",
            "type": "string",
            "description": "",
            "default": "ffffff"
        },
        "marking_callback": {},
        "slider_callback": {
            "name": "Slider on click callback",
            "type": "js",
            "default": et2_no_init,
            "description": "Callback function to get executed after clicking om slider bar"
        },
        "slider_tags": {
            "name": "slider tags",
            "type": "any",
            "description": "comment tags on slider",
            "default": {}
        },
        "stop_contextmenu": {
            "name": "stop contextmenu",
            "type": "boolean",
            "description": "This would prevent the browser native contextmenu on video tag",
            "default": true
        },
        "ontimeupdate_callback": {
            "name": "ontimeupdate callback",
            "type": "js",
            "default": et2_no_init,
            "description": "Callback function to get executed while video is playing"
        },
        "onresize_callback": {
            "name": "onresize callback",
            'type': "js",
            "default": et2_no_init,
            "description": "Callback function called when video gets resized"
        },
        seekable: {
            name: 'seekable',
            type: 'boolean',
            description: 'Make slider active for seeking in timeline',
            default: true
        },
        watermark: {
            "name": "video 0watermark",
            "type": "any",
            "description": "Set a text watermark on video in a given position.",
            "default": {}
        }
    };
    et2_smallpart_videobar.video_test_display_instead_of_comment = 0;
    et2_smallpart_videobar.video_test_display_dialog = 1;
    et2_smallpart_videobar.video_test_display_on_video = 2;
    et2_smallpart_videobar.video_test_option_pauseable = 1;
    et2_smallpart_videobar.video_test_option_not_seekable = 2;
    et2_smallpart_videobar.video_test_published_readonly = 3;
    et2_smallpart_videobar.video_test_published_published = 1;
    et2_smallpart_videobar.video_test_published_draft = 0;
    et2_smallpart_videobar.video_test_published_unavailabe = 2;
    et2_smallpart_videobar.course_options_video_watermark = 2;
    return et2_smallpart_videobar;
}(et2_widget_video_1.et2_video));
exports.et2_smallpart_videobar = et2_smallpart_videobar;
et2_core_widget_1.et2_register_widget(et2_smallpart_videobar, ["smallpart-videobar"]);
//# sourceMappingURL=et2_widget_videobar.js.map