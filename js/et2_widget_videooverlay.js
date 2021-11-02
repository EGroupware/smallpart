"use strict";
/**
 * EGroupware SmallPART - Videooverlay
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
exports.et2_smallpart_videooverlay = void 0;
/*egw:uses
    et2_core_baseWidget;
    /smallpart/js/et2_videooverlay_interface.js;
    /smallpart/js/overlay_plugins/et2_smallpart_*.js;
*/
var et2_core_baseWidget_1 = require("../../api/js/etemplate/et2_core_baseWidget");
var et2_core_widget_1 = require("../../api/js/etemplate/et2_core_widget");
var et2_core_inheritance_1 = require("../../api/js/etemplate/et2_core_inheritance");
var et2_widget_videobar_1 = require("./et2_widget_videobar");
var et2_widget_button_1 = require("../../api/js/etemplate/et2_widget_button");
var et2_widget_number_1 = require("../../api/js/etemplate/et2_widget_number");
var et2_videooverlay_interface_1 = require("./et2_videooverlay_interface");
var et2_widget_dialog_1 = require("../../api/js/etemplate/et2_widget_dialog");
var et2_widget_checkbox_1 = require("../../api/js/etemplate/et2_widget_checkbox");
/**
 * Videooverlay shows time-synchronious to the video various overlay-elements
 *
 * Overlay-elements have a starttime they get created by this overlay widget as it's children.
 * The overlay widgets informs the elements / it's children if user seeks the video, so they
 * can decide, if they should still be shown or removed by the overlay widget.
 *
 * Overlay-elements have a player_mode attribute telling the overlay widget to eg. stop playing the video
 * and/or disable certain player controls to eg. require the user to answer a question.
 *
 * Overlay-elements can call their parent to get themselfs removed, if they are done eg. user
 * answered a question or the duration of a headline is exceeded.
 *
 * @augments et2_baseWidget
 */
var et2_smallpart_videooverlay = /** @class */ (function (_super) {
    __extends(et2_smallpart_videooverlay, _super);
    /**
     * Constructor
     */
    function et2_smallpart_videooverlay(_parent, _attrs, _child) {
        var _this = 
        // Call the inherited constructor
        _super.call(this, _parent, _attrs, et2_core_inheritance_1.ClassWithAttributes.extendAttributes(et2_smallpart_videooverlay._attributes, _child || {})) || this;
        /**
         * Keeps current rendered question dialogs
         * @protected
         */
        _this.questionDialogs = [];
        _this._elementsContainer = null;
        _this._slider_progressbar = null;
        _this._elementSlider = null;
        _this._editor = null;
        _this.div = jQuery(document.createElement("div"))
            .addClass("et2_" + _this.getType());
        if (_this.options.editable) {
            _this.div.addClass('editable');
        }
        _this._elementsContainer = et2_core_widget_1.et2_createWidget('hbox', { width: "100%", height: "100%", class: "elementsContainer", id: "elementsContainer" }, _this);
        if (_this.options.stop_contextmenu)
            _this.div.on('contextmenu', function () { return false; });
        _this.setDOMNode(_this.div[0]);
        return _this;
    }
    /**
     * Set video ID
     *
     * @param _id
     */
    et2_smallpart_videooverlay.prototype.set_video_id = function (_id) {
        if (_id === this.video_id)
            return;
        for (var i = this._elementsContainer.getChildren().length - 1; i >= 0; i--) {
            this._elementsContainer.getChildren()[i].destroy();
        }
        this.elements = [];
        this.video_id = _id;
    };
    /**
     * Setter for course_id
     *
     * @param _id
     */
    et2_smallpart_videooverlay.prototype.set_course_id = function (_id) {
        this.course_id = _id;
    };
    /**
     * Set videobar to use
     *
     * @param _id_or_widget
     */
    et2_smallpart_videooverlay.prototype.set_videobar = function (_id_or_widget) {
        if (typeof _id_or_widget === 'string') {
            _id_or_widget = this.getRoot().getWidgetById(_id_or_widget);
        }
        if (_id_or_widget instanceof et2_widget_videobar_1.et2_smallpart_videobar) {
            this.videobar = _id_or_widget;
            var self_1 = this;
            var content = this.videobar.getArrayMgr('content').data;
            var seekable = (content.is_admin || content.video && !(content.video.video_test_options & et2_widget_videobar_1.et2_smallpart_videobar.video_test_option_not_seekable));
            this.videobar.set_seekable(seekable);
            // allow user to close "more videos" box from youtube iframe
            if (this.videobar.options.src_type.match('youtube')) {
                jQuery(this.videobar.getDOMNode()).on('mouseleave', function () {
                    jQuery(self_1._elementsContainer.getDOMNode()).removeClass('shiftUp');
                });
                jQuery(this._elementsContainer.getDOMNode())
                    .on('mouseenter', function () {
                    jQuery(this).addClass('shiftUp');
                })
                    .on('mouseleave', function (e) {
                    var _a;
                    if (((_a = e === null || e === void 0 ? void 0 : e.toElement) === null || _a === void 0 ? void 0 : _a.localName) != "iframe")
                        jQuery(this).removeClass('shiftUp');
                });
            }
            if (seekable) {
                this.videobar.getSliderDOMNode().on('click', function () {
                    self_1.onSeek(self_1.videobar.currentTime());
                });
            }
            this.videobar.onresize_callback = jQuery.proxy(this._onresize_videobar, this);
            this.videobar.video[0].addEventListener("et2_video.onReady." + this.videobar.id, jQuery.proxy(function () {
                this._videoIsLoaded();
            }, this));
        }
    };
    et2_smallpart_videooverlay.prototype.doLoadingFinished = function () {
        var ret = _super.prototype.doLoadingFinished.call(this);
        var self = this;
        var content = this.videobar.getArrayMgr('content').data;
        this.set_disabled(!this.video_id);
        this.videobar.ontimeupdate_callback = function (_time) {
            self.onTimeUpdate(_time);
        };
        this._elementSlider = et2_core_widget_1.et2_createWidget('smallpart-videooverlay-slider-controller', {
            width: "100%",
            videobar: 'video',
            seekable: (content.is_admin || content.video && !(content.video.video_test_options & et2_widget_videobar_1.et2_smallpart_videobar.video_test_option_not_seekable)),
            onclick_callback: jQuery.proxy(this._elementSlider_callback, this),
            onclick_slider_callback: jQuery.proxy(function () { this.onSeek(this.videobar.currentTime()); }, this)
        }, this);
        return ret;
    };
    /**
     * Click callback called on elements slidebar
     * @param _node
     * @param _widget
     *
     * @return boolean return false when there's an unanswered question
     * @private
     */
    et2_smallpart_videooverlay.prototype._elementSlider_callback = function (_node, _widget) {
        var _a, _b;
        var overlay_id = _widget.id.split('slider-tag-')[1];
        var data = this.elements.filter(function (e) { if (e.overlay_id == overlay_id)
            return e; });
        if (data[0] && data[0].overlay_id) {
            this.videobar.seek_video(data[0].overlay_start);
            this.onSeek(data[0].overlay_start);
            if (this._anyUnansweredRequiredQuestions(data[0].overlay_start))
                return false;
            this.renderElements(data[0].overlay_id);
            (_a = this.toolbar_edit) === null || _a === void 0 ? void 0 : _a.set_disabled(false);
            (_b = this.toolbar_delete) === null || _b === void 0 ? void 0 : _b.set_disabled(false);
        }
        return true;
    };
    /**
     *
     * @param _id_or_widget
     */
    et2_smallpart_videooverlay.prototype.set_toolbar_save = function (_id_or_widget) {
        if (!this.options.editable)
            return;
        if (typeof _id_or_widget === 'string') {
            _id_or_widget = this.getRoot().getWidgetById(_id_or_widget);
        }
        if (_id_or_widget instanceof et2_widget_button_1.et2_button) {
            this.toolbar_save = _id_or_widget;
            this.toolbar_save.onclick = jQuery.proxy(function () {
                var data = {
                    'course_id': this.course_id,
                    'video_id': this.video_id,
                    'overlay_duration': parseInt(this.toolbar_duration.getValue()),
                    'overlay_start': parseInt(this.toolbar_starttime.getValue()),
                    'offset': parseInt(this.toolbar_offset.getValue()),
                    'width': this.videobar.video.width()
                };
                var self = this;
                this._editor.onSaveCallback(data, function (_data) {
                    var _a;
                    var exist = false;
                    self.elements.forEach(function (_e, _index) {
                        if (_e.overlay_id == _data[0].overlay_id) {
                            exist = true;
                            self.elements[_index] = _data[0];
                        }
                    });
                    if (!exist)
                        self.elements = (_a = self.elements).concat.apply(_a, _data);
                    self.renderElements();
                    self.renderElements(_data[0].overlay_id);
                });
                this._enable_toolbar_edit_mode(false, false);
                this._editor.destroy();
            }, this);
        }
    };
    et2_smallpart_videooverlay.prototype.set_toolbar_edit = function (_id_or_widget) {
        if (!this.options.editable)
            return;
        if (typeof _id_or_widget === 'string') {
            _id_or_widget = this.getRoot().getWidgetById(_id_or_widget);
        }
        if (_id_or_widget instanceof et2_widget_button_1.et2_button) {
            this.toolbar_edit = _id_or_widget;
            this.toolbar_edit.onclick = jQuery.proxy(function () {
                var _a;
                this._enable_toolbar_edit_mode(true, true);
                var overlay_id = parseInt((_a = this._elementSlider) === null || _a === void 0 ? void 0 : _a.get_selected().overlay_id);
                var data = this.elements.filter(function (e) { if (e.overlay_id == overlay_id)
                    return e; });
                switch (data[0].overlay_type) {
                    case "smallpart-overlay-html":
                        this._editor = et2_core_widget_1.et2_createWidget('smallpart-overlay-html-editor', {
                            width: "100%",
                            height: "100%",
                            class: "smallpart-overlay-element",
                            mode: "simple",
                            offset: data[0].offset,
                            statusbar: false,
                            overlay_id: data[0].overlay_id,
                            imageUpload: 'html_editor_upload'
                        }, this._elementsContainer);
                        this._editor.toolbar = "";
                        this._editor.set_value(data[0].data);
                        this._editor.doLoadingFinished();
                        break;
                    case "smallpart-question-text":
                    case "smallpart-question-multiplechoice":
                        this._enable_toolbar_edit_mode(false, false);
                        egw.open_link(egw.link('/index.php', {
                            menuaction: 'smallpart.EGroupware\\SmallParT\\Questions.edit',
                            overlay_id: data[0].overlay_id,
                            video_id: this.video_id
                        }), '_blank', '800x600', 'smallpart');
                        return;
                }
                this.toolbar_offset.set_value(data[0].offset);
                this.toolbar_duration.set_value(data[0].overlay_duration);
                this.toolbar_starttime.set_value(data[0].overlay_start);
            }, this);
        }
    };
    // noinspection JSUnusedLocalSymbols
    /**
     * Set state for toolbar actions
     * @param _state
     * @param _deleteEnabled
     * @private
     */
    et2_smallpart_videooverlay.prototype._enable_toolbar_edit_mode = function (_state, _deleteEnabled) {
        var _a, _b;
        this.toolbar_edit.set_disabled(true);
        if (_state) {
            this.toolbar_starttime.set_value(Math.floor(this.videobar.currentTime()));
            this.toolbar_duration.set_max(Math.floor(this.videobar.duration() - this.toolbar_starttime.getValue()));
            this.videobar.pause_video();
            // slider progressbar span
            this._slider_progressbar = jQuery(document.createElement('span'))
                .addClass('overlay_slider_progressbar')
                .css({
                left: this.videobar._vtimeToSliderPosition(parseInt(this.toolbar_starttime.getValue())),
                width: this.videobar._vtimeToSliderPosition(parseInt(this.toolbar_duration.getValue()))
            })
                .appendTo(this.videobar.getSliderDOMNode());
            jQuery(this.getDOMNode()).addClass('editmode');
            (_a = this._elementSlider) === null || _a === void 0 ? void 0 : _a.set_disabled(true);
            this._elementsContainer.getChildren().forEach(function (_widget) { if (_widget.set_disabled)
                _widget.set_disabled(true); });
        }
        else {
            (_b = this._elementSlider) === null || _b === void 0 ? void 0 : _b.set_disabled(false);
            jQuery(this.getDOMNode()).removeClass('editmode');
            if (this.toolbar_duration)
                this.toolbar_duration.set_value(1);
            if (this._slider_progressbar)
                this._slider_progressbar.remove();
            this._elementsContainer.getChildren().forEach(function (_widget) { if (_widget.set_disabled)
                _widget.set_disabled(false); });
        }
        this.toolbar_save.set_disabled(!_state);
        this.toolbar_delete.set_disabled(!(_state && _deleteEnabled));
        this.toolbar_add.set_disabled(_state);
        this.toolbar_add_question.set_disabled(_state);
        this.toolbar_duration.set_disabled(!_state);
        this.toolbar_offset.set_disabled(!_state);
        this.toolbar_starttime.set_disabled(!_state);
        this.toolbar_cancel.set_disabled(!_state);
        this.toolbar_starttime.set_readonly(true);
    };
    et2_smallpart_videooverlay.prototype.set_toolbar_cancel = function (_id_or_widget) {
        if (!this.options.editable)
            return;
        if (typeof _id_or_widget === 'string') {
            _id_or_widget = this.getRoot().getWidgetById(_id_or_widget);
        }
        if (_id_or_widget instanceof et2_widget_button_1.et2_button) {
            this.toolbar_cancel = _id_or_widget;
            this.toolbar_cancel.onclick = jQuery.proxy(function () {
                this._enable_toolbar_edit_mode(false, false);
                this._editor.destroy();
            }, this);
        }
    };
    et2_smallpart_videooverlay.prototype.set_toolbar_delete = function (_id_or_widget) {
        if (!this.options.editable)
            return;
        if (typeof _id_or_widget === 'string') {
            _id_or_widget = this.getRoot().getWidgetById(_id_or_widget);
        }
        if (_id_or_widget instanceof et2_widget_button_1.et2_button) {
            this.toolbar_delete = _id_or_widget;
            this.toolbar_delete.onclick = jQuery.proxy(function () {
                var _a;
                var self = this;
                var overlay_id = parseInt((_a = this._elementSlider) === null || _a === void 0 ? void 0 : _a.get_selected().overlay_id);
                var data = this.elements.filter(function (_el) { if (_el.overlay_id == overlay_id)
                    return _el; });
                var message = data[0].overlay_type.match(/smallpart-question-/) ?
                    'Delete this question incl. possible answers from students?' : 'Are you sure you want to delete this element?';
                et2_widget_dialog_1.et2_dialog.show_dialog(function (_btn) {
                    if (_btn == et2_widget_dialog_1.et2_dialog.YES_BUTTON) {
                        self._enable_toolbar_edit_mode(false);
                        var element_1 = self._get_element(overlay_id);
                        egw.json('smallpart.\\EGroupware\\SmallParT\\Overlay.ajax_delete', [{
                                course_id: self.options.course_id,
                                video_id: self.options.video_id,
                                overlay_id: overlay_id,
                                overlay_type: data[0].overlay_type
                            }], function (_overlay_response) {
                            if (element_1)
                                self.deleteElement(element_1);
                            self._delete_element(overlay_id);
                            self.renderElements();
                        }).sendRequest();
                        if (self._is_in_editmode())
                            self._editor.destroy();
                    }
                }, message, data[0].overlay_type.match(/smallpart-question-/) ? "Delete question" : "Delete overlay", null, et2_widget_dialog_1.et2_dialog.BUTTONS_YES_NO);
            }, this);
        }
    };
    et2_smallpart_videooverlay.prototype.set_toolbar_starttime = function (_id_or_widget) {
        if (!this.options.editable)
            return;
        if (typeof _id_or_widget === 'string') {
            _id_or_widget = this.getRoot().getWidgetById(_id_or_widget);
        }
        if (_id_or_widget instanceof et2_widget_number_1.et2_number) {
            this.toolbar_starttime = _id_or_widget;
            this.toolbar_starttime.set_min(0);
            this.toolbar_starttime.set_max(this.videobar.duration());
            this.toolbar_starttime.set_value(this.videobar.currentTime());
        }
    };
    et2_smallpart_videooverlay.prototype.set_toolbar_duration = function (_id_or_widget) {
        if (!this.options.editable)
            return;
        if (typeof _id_or_widget === 'string') {
            _id_or_widget = this.getRoot().getWidgetById(_id_or_widget);
        }
        if (_id_or_widget instanceof et2_widget_number_1.et2_number) {
            this.toolbar_duration = _id_or_widget;
            this.toolbar_duration.set_min(0);
            this.toolbar_duration.onchange = jQuery.proxy(function (_node, _widget) {
                if (this._slider_progressbar)
                    this._slider_progressbar.css({ width: this.videobar._vtimeToSliderPosition(parseInt(_widget.getValue())) });
            }, this);
        }
    };
    et2_smallpart_videooverlay.prototype.set_toolbar_offset = function (_id_or_widget) {
        if (!this.options.editable)
            return;
        if (typeof _id_or_widget === 'string') {
            _id_or_widget = this.getRoot().getWidgetById(_id_or_widget);
        }
        if (_id_or_widget instanceof et2_widget_number_1.et2_number) {
            this.toolbar_offset = _id_or_widget;
            this.toolbar_offset.onchange = jQuery.proxy(function (_node, _widget) {
                if (this._editor && this._editor.set_offset) {
                    this._editor.set_offset(_widget.getValue());
                }
            }, this);
        }
    };
    et2_smallpart_videooverlay.prototype.set_toolbar_add = function (_id_or_widget) {
        if (!this.options.editable)
            return;
        if (typeof _id_or_widget === 'string') {
            _id_or_widget = this.getRoot().getWidgetById(_id_or_widget);
        }
        if (_id_or_widget instanceof et2_widget_button_1.et2_button) {
            this.toolbar_add = _id_or_widget;
            this.toolbar_add.onclick = jQuery.proxy(function (_node, _widget) {
                this._enable_toolbar_edit_mode(true, false);
                this.toolbar_duration.set_value(1);
                this.toolbar_offset.set_value(16);
                this._editor = et2_core_widget_1.et2_createWidget('smallpart-overlay-html-editor', {
                    width: "100%",
                    height: "100%",
                    class: "smallpart-overlay-element",
                    mode: "simple",
                    offset: this.toolbar_offset.getValue(),
                    statusbar: false,
                    imageUpload: "html_editor_upload"
                }, this._elementsContainer);
                this._editor.toolbar = "";
                this._editor.doLoadingFinished();
            }, this);
        }
    };
    et2_smallpart_videooverlay.prototype.set_toolbar_add_question = function (_id_or_widget) {
        if (!this.options.editable)
            return;
        if (typeof _id_or_widget === 'string') {
            _id_or_widget = this.getRoot().getWidgetById(_id_or_widget);
        }
        if (_id_or_widget instanceof et2_widget_button_1.et2_button) {
            this.toolbar_add_question = _id_or_widget;
            this.toolbar_add_question.onclick = jQuery.proxy(function () {
                egw.open_link(egw.link('/index.php', {
                    menuaction: 'smallpart.EGroupware\\SmallParT\\Questions.edit',
                    overlay_start: Math.floor(this.videobar.currentTime()),
                    overlay_duration: 1,
                    overlay_type: "smallpart-question-text",
                    video_id: this.video_id
                }), '_blank', '800x600', 'smallpart');
                if (!this.videobar.paused())
                    app.smallpart.et2.getDOMWidgetById('play').getDOMNode().click();
            }, this);
        }
    };
    et2_smallpart_videooverlay.prototype.set_toolbar_play = function (_id_or_widget) {
        if (typeof _id_or_widget === 'string') {
            _id_or_widget = this.getRoot().getWidgetById(_id_or_widget);
        }
        if (_id_or_widget instanceof et2_widget_button_1.et2_button) {
            this.toolbar_play = _id_or_widget;
        }
    };
    // noinspection JSUnusedLocalSymbols
    /**
     * After video is fully loaded
     * @private
     */
    et2_smallpart_videooverlay.prototype._videoIsLoaded = function () {
        var _this = this;
        var _a;
        (_a = this.toolbar_duration) === null || _a === void 0 ? void 0 : _a.set_max(this.videobar.duration() - this.toolbar_starttime.getValue());
        if (this._elementSlider)
            jQuery(this._elementSlider.getDOMNode()).css({ width: this.videobar.video.width() });
        this.fetchElements(0).then(function () {
            var _a, _b, _c;
            _this.renderElements();
            _this.onSeek(parseFloat(_this.videobar.options.starttime));
            if (!_this.options.editable && !_this.elements.length) {
                (_a = _this._elementSlider) === null || _a === void 0 ? void 0 : _a.set_disabled(true);
            }
            else {
                (_b = _this._elementSlider) === null || _b === void 0 ? void 0 : _b.set_disabled(false);
                (_c = _this.div) === null || _c === void 0 ? void 0 : _c.css({ 'margin-bottom': '40px' });
            }
        });
    };
    /**
     * Renders all elements
     *
     * @param _overlay_id id of changed overlay element to fetch, otherwise nothing will be fetched
     */
    et2_smallpart_videooverlay.prototype.renderElements = function (_overlay_id) {
        var _a;
        var self = this;
        if (this._elementsContainer.getChildren().length > 0) {
            this._elementsContainer.getChildren().forEach(function (_widget) {
                if (_overlay_id && _overlay_id == _widget.options.overlay_id) {
                    _widget.destroy();
                    self.fetchElement(_overlay_id).then(function (_attrs) {
                        self.createElement(_attrs);
                    }, function () {
                        // try to fetch elements maybe added from outside
                        self.fetchElements(0).then(function () {
                            self.renderElements();
                        });
                    });
                }
                else {
                    _widget.destroy();
                }
            });
        }
        if (this._elementsContainer.getChildren().length == 0 && _overlay_id) {
            this.fetchElement(_overlay_id).then(function (_attrs) {
                self.createElement(_attrs);
            }, function () {
                // try to fetch elements maybe added from outside
                self.fetchElements(0).then(function () {
                    self.renderElements();
                });
            });
        }
        if (typeof _overlay_id == 'undefined')
            (_a = this._elementSlider) === null || _a === void 0 ? void 0 : _a.set_value(this.elements);
    };
    /**
     * Load overlay elements from server
     *
     * @param _start
     * @return Promise<Array<OverlayElement>>
     */
    et2_smallpart_videooverlay.prototype.fetchElements = function (_start) {
        if (!_start) {
            this.elements = [];
            this.total = 0;
        }
        if (!this.options.get_elements_callback)
            return;
        // fetch first chunk of overlay elements
        return this.egw().json(this.options.get_elements_callback, [{
                video_id: this.video_id,
                course_id: this.course_id,
            }, _start], function (_data) {
            if (typeof _data === 'object' && Array.isArray(_data.elements)) {
                if (this.elements.length === 0) {
                    this.elements = jQuery.extend(true, [], _data.elements);
                }
                else {
                    _data.elements.forEach(function (element) {
                        for (var i in this.elements) {
                            if (this.elements[i].overlay_id === element.overlay_id) {
                                this.elements[i] = jQuery.extend(true, {}, element);
                                return;
                            }
                        }
                        this.elements.concat(jQuery.extend(true, {}, element));
                    }.bind(this));
                }
                this.total = _data.total;
                return Promise.resolve(this.elements);
            }
        }.bind(this)).sendRequest();
    };
    /**
     * Return given overlay element, load it if neccessary from server
     *
     * @param _overlay_id
     * @return Promise<OverlayElement>
     */
    et2_smallpart_videooverlay.prototype.fetchElement = function (_overlay_id) {
        var element = this.elements.filter(function (_element) { return _element.overlay_id === _overlay_id; })[0];
        if (typeof element !== "undefined" && element.data !== false) {
            return Promise.resolve(jQuery.extend(true, {}, element));
        }
        if (this.elements.length === this.total) {
            return Promise.reject("No overlay_id {_overlay_id}!");
        }
        this.fetchElements(this.elements.length).then(function () {
            return this.fetchElement(_overlay_id);
        }.bind(this));
    };
    /**
     * check if the editor is active
     * @private
     */
    et2_smallpart_videooverlay.prototype._is_in_editmode = function () {
        return this._editor && this._editor.getDOMNode();
    };
    /**
     * Called when video is seeked to a certain position to create and remove elements
     *
     * Every running element / child is asked if it want's to keep running.
     *
     * @param _time
     */
    et2_smallpart_videooverlay.prototype.onSeek = function (_time) {
        if (this._is_in_editmode()) // update startime if it's in editmode
         {
            this.toolbar_starttime.set_value(Math.floor(_time));
            this._slider_progressbar.css({
                left: this.videobar._vtimeToSliderPosition(parseInt(this.toolbar_starttime.getValue())),
                width: this.videobar._vtimeToSliderPosition(parseInt(this.toolbar_duration.getValue()))
            });
            return;
        }
        this.onTimeUpdate(_time);
    };
    /**
     *
     * @param time
     * @return OverlayElement|undefined returns overlay object
     * @private
     */
    et2_smallpart_videooverlay.prototype._anyUnansweredRequiredQuestions = function (time) {
        var overlay = undefined;
        var video = this.getArrayMgr('content').getEntry('video');
        if (video && video['video_published'] == et2_widget_videobar_1.et2_smallpart_videobar.video_test_published_readonly)
            return overlay;
        this.elements.forEach(function (el) {
            if (el.overlay_start + el.overlay_duration < time
                && el.overlay_question_mode != et2_smallpart_videooverlay.overlay_question_mode_skipable
                && !el.answer_created && el.question_n) {
                overlay = el;
                return;
            }
        });
        // makse sure the video stops when there's an overlay found
        if (overlay && !this.videobar.paused())
            this.toolbar_play.click(null);
        return overlay;
    };
    /**
     * Periodically called while video is playing to add new overlay elements
     *
     * @param _time
     */
    et2_smallpart_videooverlay.prototype.onTimeUpdate = function (_time) {
        var _this = this;
        var _a;
        var ol;
        if ((ol = this._anyUnansweredRequiredQuestions(_time))) {
            // duration must be set 0.1 sec before the end time otherwise we can't create the element
            var ol_duration = ol.overlay_start + ol.overlay_duration - 0.1;
            this.videobar.seek_video(ol_duration);
            this.onTimeUpdate(ol_duration);
            _time = ol_duration;
            return false;
        }
        (_a = this._elementSlider) === null || _a === void 0 ? void 0 : _a.set_seek_position(Math.round(this.videobar._vtimeToSliderPosition(_time)));
        // check if we seeking behind the last loaded element and there are more to fetch
        if (this.total > this.elements.length &&
            _time > this.elements[this.elements.length - 1].overlay_start) {
            this.fetchElements(this.elements.length).then(function () { return _this.onTimeUpdate(_time); });
            return;
        }
        var running = [];
        this._elementsContainer.iterateOver(function (_widget) {
            if (!_widget.keepRunning(_time)) {
                this.deleteElement(_widget);
                return;
            }
            running.push(_widget.options.overlay_id);
        }.bind(this), this, et2_IOverlayElement);
        this.elements.forEach(function (_element, _idx) {
            if (running.indexOf(_element.overlay_id) === -1 &&
                _element.overlay_start <= _time && _time < _element.overlay_start + (_element.overlay_duration || 1)) {
                this.createElement(_element);
                // fetch more elements, if we are reaching the end of the loaded ones
                if (this.total > this.elements.length && _idx > this.elements.length - 10) {
                    this.fetchElements(this.elements.length);
                }
            }
        }.bind(this));
    };
    /**
     * Called by element to be removed when it's done
     *
     * @param _widget
     */
    et2_smallpart_videooverlay.prototype.deleteElement = function (_widget) {
        _widget.destroy();
        this._elementsContainer.removeChild(_widget);
    };
    /**
     * Create / show an overlay-element and add it to children
     *
     * @param _attrs
     */
    et2_smallpart_videooverlay.prototype.createElement = function (_attrs) {
        var _this = this;
        var _a;
        var isQuestionOverlay = _attrs.overlay_type.match(/-question-/);
        // prevent creating an element if already exists
        for (var _i = 0, _b = this._elementsContainer.getChildren(); _i < _b.length; _i++) {
            var _widget = _b[_i];
            if (_widget.options.overlay_id == _attrs.overlay_id) {
                return;
            }
        }
        // let other overlays being created as normal
        if (isQuestionOverlay) {
            this._removeExcessiveDialogs();
            var isSorted = true;
            for (var i = 0; i < this.questionDialogs.length; i++) {
                if (i > 1 && this.questionDialogs[i].question_n < this.questionDialogs[i - 1].question_n) {
                    isSorted = false;
                    break;
                }
            }
            if (!isSorted) {
                var sorted = [];
                Object.assign(sorted, this.questionDialogs);
                this.questionDialogs.sort(function (a, b) { return (a.question_n < b.question_n) ? -1 : 1; });
                Object.assign(sorted, this.questionDialogs);
                sorted.forEach(function (_d) {
                    _this._questionDialogs(_d.id)._remove();
                });
                sorted.forEach(function (_d) { _this.createElement(_d.dialog.options.value.content); });
            }
            if (this._questionDialogs(_attrs.overlay_id)._get()) {
                return;
            }
        }
        var widget = et2_core_widget_1.et2_createWidget(_attrs.overlay_type, jQuery.extend(true, {}, _attrs), this._elementsContainer);
        this._elementsContainer.addChild(widget);
        this._elementsContainer.getChildren().forEach(function (_w) {
            var zoom = _this.videobar.video.width() / _attrs.width;
            jQuery(_w.getDOMNode()).children().css({
                'zoom': zoom
            });
        });
        if (_attrs.overlay_player_mode & et2_videooverlay_interface_1.PlayerMode.Pause) {
            (_a = this.videobar) === null || _a === void 0 ? void 0 : _a.pause_video();
        }
        if (_attrs.overlay_player_mode & et2_videooverlay_interface_1.PlayerMode.Disable) {
            // ToDo: this.videobar?.
        }
        if (isQuestionOverlay) {
            this._questionDialogs(_attrs.overlay_id)._add(this._createQuestionElement(_attrs, widget));
        }
    };
    /**
     * Manages question dialogs objects
     * @param _overlay_id
     *
     * @return returns as object of controllers
     *  _add : add dialog of overlay
     *  _remove : remove the dialg of given overlay
     *  _get : get dialog of givern overlay
     * @private
     */
    et2_smallpart_videooverlay.prototype._questionDialogs = function (_overlay_id) {
        var self = this;
        return {
            _add: function (_dialog) {
                if (!self._questionDialogs(_overlay_id)._get()) {
                    self.questionDialogs.push({ id: _overlay_id, dialog: _dialog, question_n: parseInt(_dialog.options.value.content.question_n) });
                }
            },
            _remove: function () {
                if (self.questionDialogs) {
                    self.questionDialogs.forEach(function (o, i) {
                        if (o.id == _overlay_id) {
                            o.dialog.destroy();
                            self.questionDialogs.splice(i, 1);
                        }
                    });
                }
            },
            _get: function () {
                var _a;
                if (((_a = self.questionDialogs) === null || _a === void 0 ? void 0 : _a.length) > 0) {
                    var res = self.questionDialogs.filter(function (o) {
                        return o.id == _overlay_id;
                    });
                    return (res === null || res === void 0 ? void 0 : res.length) > 0 ? res : false;
                }
                else {
                    return false;
                }
            }
        };
    };
    /**
     *
     * @param _attrs
     * @param _widget
     *
     * @return et2_dialog
     * @private
     */
    et2_smallpart_videooverlay.prototype._createQuestionElement = function (_attrs, _widget) {
        _widget.set_disabled(true);
        var video = this.getArrayMgr('content').getEntry('video');
        _attrs.account_id = egw.user('account_id');
        var pauseSwitch = false;
        var attrs = _attrs;
        var pause_callback = function () {
            if (pauseSwitch && self.videobar.currentTime() >= attrs.overlay_start + attrs.overlay_duration && !attrs.answer_created) {
                // pasue the video at the end of the question
                self.toolbar_play.click(null);
                if (parseInt(attrs.overlay_question_mode) == et2_smallpart_videooverlay.overlay_question_mode_skipable) {
                    self.toolbar_play.getDOMNode().addEventListener('click', function () {
                        self._questionDialogs(attrs.overlay_id)._remove();
                    }, { once: true });
                }
                self.videobar.video[0].removeEventListener('et2_video.onTimeUpdate.' + self.videobar.id, pause_callback);
            }
        };
        var is_readonly = video.video_published == et2_widget_videobar_1.et2_smallpart_videobar.video_test_published_readonly;
        var modal = false;
        var self = this;
        var buttons = [
            { "button_id": 1, "text": this.egw().lang('Save'), id: 'submit', image: 'check', "default": true },
            { "button_id": 2, "text": this.egw().lang('Skip'), id: 'skip', image: 'cancel' }
        ].filter(function (b) {
            if (is_readonly) {
                return b.id == "skip";
            }
            switch (parseInt(_attrs.overlay_question_mode)) {
                case et2_smallpart_videooverlay.overlay_question_mode_skipable:
                    return true;
                case et2_smallpart_videooverlay.overlay_question_mode_reqires:
                case et2_smallpart_videooverlay.overlay_question_mode_required_limitted_time:
                    return b.id != "skip";
            }
        });
        switch (parseInt(_attrs.overlay_question_mode)) {
            case et2_smallpart_videooverlay.overlay_question_mode_skipable:
            case et2_smallpart_videooverlay.overlay_question_mode_reqires:
                if (!is_readonly) {
                    pauseSwitch = true;
                    this.videobar.video[0].addEventListener('et2_video.onTimeUpdate.' + this.videobar.id, pause_callback);
                }
                if (_attrs.overlay_question_mode == et2_smallpart_videooverlay.overlay_question_mode_skipable || _attrs.answer_created) {
                    this.videobar.video[0].addEventListener('et2_video.onTimeUpdate.' + this.videobar.id, function (_time) {
                        self._removeExcessiveDialogs();
                    });
                }
                break;
            case et2_smallpart_videooverlay.overlay_question_mode_required_limitted_time:
                break;
        }
        var error_msg;
        var dialog = et2_core_widget_1.et2_createWidget("dialog", {
            callback: function (_btn, _value) {
                var _a;
                if (error_msg) {
                    error_msg.close();
                    error_msg = null;
                }
                // check required minimum number of answers are given
                // ToDo: this should come from the et2_smallpart_question_multiplechoice object or app.ts
                if (_attrs.min_answers && _attrs.overlay_type === 'smallpart-question-multiplechoice') {
                    var checked_1 = 0;
                    (_a = this.template.widgetContainer.getWidgetById('answers')) === null || _a === void 0 ? void 0 : _a.iterateOver(function (_checkbox) {
                        if (_checkbox.get_value())
                            checked_1++;
                    }, this, et2_widget_checkbox_1.et2_checkbox);
                    if (checked_1 < _attrs.min_answers) {
                        error_msg = egw(parent).message(egw(parent).lang('A minimum of %1 answers need to be checked!', _attrs.min_answers), 'error');
                        return false;
                    }
                }
                if ((_btn == 'skip' || _btn == 'submit') && self.videobar.paused() && !self.videobar.ended()) {
                    if (self.questionDialogs.length == 1) {
                        self.toolbar_play.click(null);
                    }
                    else {
                        self._questionDialogs(_attrs.overlay_id)._remove();
                    }
                }
                if (_btn == 'submit' && _value && !is_readonly) {
                    // @ts-ignore
                    var data = _widget.submit(_value, _attrs);
                    Promise.resolve(data).then(function (_result) {
                        if (_result && typeof _result.error === 'undefined') {
                            self._update_element(_attrs.overlay_id, _result);
                        }
                    });
                }
                pauseSwitch = false;
            },
            title: this.egw().lang('Question number %1', _attrs.question_n),
            buttons: buttons,
            value: {
                content: _attrs,
                readonlys: is_readonly ? { '__ALL__': true } : {}
            },
            modal: modal,
            width: 500,
            appendTo: video.video_test_display != et2_widget_videobar_1.et2_smallpart_videobar.video_test_display_dialog ?
                (video.video_test_display == et2_widget_videobar_1.et2_smallpart_videobar.video_test_display_on_video ? ".et2_smallpart-videooverlay" : ".rightBoxArea") : '',
            draggable: video.video_test_display == et2_widget_videobar_1.et2_smallpart_videobar.video_test_display_dialog,
            resizable: false,
            closeOnEscape: false,
            dialogClass: 'questionDisplayBox',
            template: _attrs.template_url || egw.webserverUrl + '/smallpart/templates/default/question.' + _attrs.overlay_type.replace('smallpart-question-', '') + '.xet'
        }, et2_widget_dialog_1.et2_dialog._create_parent('smallpart'));
        return dialog;
    };
    et2_smallpart_videooverlay.prototype._removeExcessiveDialogs = function () {
        var _this = this;
        if (this.questionDialogs) {
            // go through all dialogs and remove which are not in display time
            this.questionDialogs.forEach(function (d) {
                var content = d.dialog.options.value.content;
                if (_this.videobar.currentTime() < content.overlay_start
                    || _this.videobar.currentTime() > content.overlay_start + content.overlay_duration + 1) {
                    _this._questionDialogs(content.overlay_id)._remove();
                }
            });
        }
    };
    et2_smallpart_videooverlay.prototype._onresize_videobar = function (_width, _height, _position) {
        var _a;
        if (this._elementSlider)
            jQuery(this._elementSlider.getDOMNode()).css({ width: _width });
        (_a = this._elementSlider) === null || _a === void 0 ? void 0 : _a.set_seek_position(_position);
        this.renderElements();
        this.onSeek(this.videobar.currentTime());
    };
    /**
     * get element widget from elements container
     * @param _overlay_id
     *
     * @return et2_IOverlayElement
     */
    et2_smallpart_videooverlay.prototype._get_element = function (_overlay_id) {
        var element = null;
        this._elementsContainer.iterateOver(function (_widget) {
            if (_widget.options.overlay_id == _overlay_id) {
                element = _widget;
            }
        }.bind(this), this, et2_IOverlayElement);
        return element;
    };
    /**
     * delete given overlay id from fetched elements object
     * @param _overlay_id
     */
    et2_smallpart_videooverlay.prototype._delete_element = function (_overlay_id) {
        for (var i = 0; i < this.elements.length; i++) {
            if (this.elements[i]['overlay_id'] == _overlay_id) {
                this.elements.splice(i, 1);
            }
        }
    };
    /**
     * client-side update update element data
     * @param _overlay_id
     * @param _data
     */
    et2_smallpart_videooverlay.prototype._update_element = function (_overlay_id, _data) {
        for (var i = 0; i < this.elements.length; i++) {
            if (this.elements[i]['overlay_id'] == _overlay_id) {
                this.elements[i] = _data;
            }
        }
    };
    et2_smallpart_videooverlay._attributes = {
        course_id: {
            name: 'course_id',
            type: 'integer',
            description: 'ID of course, required for server-side ACL check',
        },
        video_id: {
            name: 'video_id',
            type: 'integer',
            description: 'ID of video to load overlay for',
        },
        get_elements_callback: {
            name: 'get_elements_callback',
            type: 'string',
            description: 'menuaction to request elements of given video_id starting from given overlay_start time',
        },
        videobar: {
            name: 'videobar',
            type: 'string',
            description: 'videobar this overlay is for',
        },
        toolbar_save: {
            name: 'toolbar save',
            type: 'string',
            description: 'Save button in top bar controller',
        },
        toolbar_edit: {
            name: 'toolbar edit',
            type: 'string',
            description: 'edit button in top bar controller',
        },
        toolbar_cancel: {
            name: 'toolbar cancel',
            type: 'string',
            description: 'cancel button in top bar controller',
        },
        toolbar_delete: {
            name: 'toolbar delete',
            type: 'string',
            description: 'delete button in top bar controller',
        },
        toolbar_add: {
            name: 'toolbar add',
            type: 'string',
            description: 'Add button in top bar controller',
        },
        toolbar_add_question: {
            name: 'toolbar add question',
            type: 'string',
            description: 'Add question button in top bar controller',
        },
        toolbar_starttime: {
            name: 'toolbar starttime',
            type: 'string',
            description: 'start-time in top bar controller',
        },
        toolbar_duration: {
            name: 'toolbar duration',
            type: 'string',
            description: 'Duration time button in top bar controller',
        },
        toolbar_offset: {
            name: 'toolbar offset',
            type: 'string',
            description: 'offset margin',
            default: 16
        },
        toolbar_play: {
            name: 'toolbar play',
            type: 'string',
            description: 'play button',
        },
        editable: {
            name: 'Editable',
            type: 'boolean',
            description: 'Make overlay editable',
        },
        stop_contextmenu: {
            name: "stop contextmenu",
            type: "boolean",
            description: "This would prevent the browser native contextmenu on video tag",
            default: true
        },
    };
    et2_smallpart_videooverlay.overlay_question_mode_skipable = 0;
    et2_smallpart_videooverlay.overlay_question_mode_reqires = 1;
    et2_smallpart_videooverlay.overlay_question_mode_required_limitted_time = 2;
    return et2_smallpart_videooverlay;
}(et2_core_baseWidget_1.et2_baseWidget));
exports.et2_smallpart_videooverlay = et2_smallpart_videooverlay;
et2_core_widget_1.et2_register_widget(et2_smallpart_videooverlay, ["smallpart-videooverlay"]);
//# sourceMappingURL=et2_widget_videooverlay.js.map