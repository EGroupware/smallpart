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
/*egw:uses
    et2_core_baseWidget;
    /smallpart/js/et2_videooverlay_interface.js;
    /smallpart/js/overlay_plugins/et2_smallpart_overlay_html.js;
*/
var et2_core_baseWidget_1 = require("../../api/js/etemplate/et2_core_baseWidget");
var et2_core_widget_1 = require("../../api/js/etemplate/et2_core_widget");
var et2_core_inheritance_1 = require("../../api/js/etemplate/et2_core_inheritance");
var et2_widget_videobar_1 = require("./et2_widget_videobar");
var et2_widget_button_1 = require("../../api/js/etemplate/et2_widget_button");
var et2_widget_dropdown_button_1 = require("../../api/js/etemplate/et2_widget_dropdown_button");
var et2_widget_number_1 = require("../../api/js/etemplate/et2_widget_number");
var et2_videooverlay_interface_1 = require("./et2_videooverlay_interface");
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
        _this._elementsContainer = null;
        _this._slider_progressbar = null;
        _this._elementSlider = null;
        _this.add = null;
        _this._editor = null;
        _this.div = jQuery(document.createElement("div"))
            .addClass("et2_" + _this.getType());
        if (_this.options.editable) {
            _this.div.addClass('editable');
        }
        _this._elementsContainer = et2_core_widget_1.et2_createWidget('hbox', { width: "100%", height: "100%", class: "elementsContainer" }, _this);
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
            this.videobar.slider.on('click', function (e) {
                self_1.onSeek(self_1.videobar.video[0].currentTime);
            });
            this.videobar.onresize_callback = jQuery.proxy(this._onresize_videobar, this);
        }
    };
    et2_smallpart_videooverlay.prototype.doLoadingFinished = function () {
        var ret = _super.prototype.doLoadingFinished.call(this);
        var self = this;
        this.videobar.ontimeupdate_callback = function (_time) {
            self.onTimeUpdate(_time);
        };
        if (this.options.editable) {
            this._elementSlider = et2_core_widget_1.et2_createWidget('smallpart-videooverlay-slider-controller', {
                width: "100%",
                videobar: 'video',
                onclick_callback: jQuery.proxy(this._elementSlider_callback, this)
            }, this);
        }
        return ret;
    };
    /**
     * Click callback called on elements slidebar
     * @param _node
     * @param _widget
     * @private
     */
    et2_smallpart_videooverlay.prototype._elementSlider_callback = function (_node, _widget) {
        var overlay_id = _widget.id.split('slider-tag-')[1];
        var data = this.elements.filter(function (e) { if (e.overlay_id == overlay_id)
            return e; });
        if (data[0] && data[0].overlay_id) {
            this.videobar.seek_video(data[0].overlay_start);
            this.onSeek(data[0].overlay_start);
            this.renderElements(data[0].overlay_id);
            this.toolbar_edit.set_disabled(false);
            this.toolbar_delete.set_disabled(false);
        }
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
                    'overlay_starttime': parseInt(this.toolbar_starttime.getValue()),
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
                this._enable_toolbar_edit_mode(true, true);
                var overlay_id = parseInt(this._elementSlider.get_selected().overlay_id);
                var data = this.elements.filter(function (e) { if (e.overlay_id == overlay_id)
                    return e; });
                switch (data[0].overlay_type) {
                    case "smallpart-overlay-html":
                        this._editor = et2_core_widget_1.et2_createWidget('smallpart-overlay-html-editor', {
                            width: "100%",
                            height: "100%",
                            class: "smallpart-overlay-element",
                            mode: "simple",
                            statusbar: false,
                            overlay_id: data[0].overlay_id
                        }, this._elementsContainer);
                        this._editor.toolbar = "";
                        this._editor.set_value(data[0].data);
                        this._editor.doLoadingFinished();
                }
                this.toolbar_duration.set_value(data[0].overlay_duration);
                this.toolbar_starttime.set_value(data[0].overlay_start);
            }, this);
        }
    };
    et2_smallpart_videooverlay.prototype._enable_toolbar_edit_mode = function (_state, _deleteEnabled) {
        this.toolbar_edit.set_disabled(true);
        if (_state) {
            this.toolbar_starttime.set_value(Math.floor(this.videobar.video[0].currentTime));
            this.toolbar_duration.set_max(Math.floor(this.videobar.video[0].duration - this.toolbar_starttime.getValue()));
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
            this._elementSlider.set_disabled(true);
            this._elementsContainer.getChildren().forEach(function (_widget) { if (_widget.set_disabled)
                _widget.set_disabled(true); });
        }
        else {
            this._elementSlider.set_disabled(false);
            jQuery(this.getDOMNode()).removeClass('editmode');
            if (this.toolbar_duration)
                this.toolbar_duration.set_value(0);
            if (this._slider_progressbar)
                this._slider_progressbar.remove();
            this._elementsContainer.getChildren().forEach(function (_widget) { if (_widget.set_disabled)
                _widget.set_disabled(false); });
        }
        this.toolbar_save.set_disabled(!_state);
        this.toolbar_delete.set_disabled(!(_state && _deleteEnabled));
        this.toolbar_add.set_disabled(_state);
        this.toolbar_duration.set_disabled(!_state);
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
                this._enable_toolbar_edit_mode(false);
                var overlay_id = parseInt(this._elementSlider.get_selected().overlay_id);
                var element = this._get_element(overlay_id);
                var self = this;
                egw.json('smallpart.\\EGroupware\\SmallParT\\Overlay.ajax_delete', [{
                        course_id: this.options.course_id,
                        video_id: this.options.video_id,
                        overlay_id: overlay_id
                    }], function (_overlay_response) {
                    if (element)
                        self.deleteElement(element);
                    self._delete_element(overlay_id);
                    self.renderElements();
                }).sendRequest();
                if (this._is_in_editmode())
                    this._editor.destroy();
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
            this.toolbar_starttime.set_max(this.videobar.video[0].duration);
            this.toolbar_starttime.set_value(this.videobar.video[0].currentTime);
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
            this.videobar.video[0].addEventListener("loadedmetadata", jQuery.proxy(function () {
                this._videoIsLoaded();
            }, this));
            this.toolbar_duration.onchange = jQuery.proxy(function (_node, _widget) {
                this.videobar.seek_video(parseInt(this.toolbar_starttime.getValue()) + parseInt(_widget.getValue()));
                if (this._slider_progressbar)
                    this._slider_progressbar.css({ width: this.videobar._vtimeToSliderPosition(parseInt(_widget.getValue())) });
            }, this);
        }
    };
    et2_smallpart_videooverlay.prototype.set_toolbar_add = function (_id_or_widget) {
        if (!this.options.editable)
            return;
        if (typeof _id_or_widget === 'string') {
            _id_or_widget = this.getRoot().getWidgetById(_id_or_widget);
        }
        if (_id_or_widget instanceof et2_widget_dropdown_button_1.et2_dropdown_button) {
            this.toolbar_add = _id_or_widget;
            //TODO: set select options with available plugins
            this.toolbar_add.set_select_options({
                "et2_smallpart_overlay_html_editor": { label: egw.lang("html"), icon: "edit" }
            });
            this.toolbar_add.onchange = jQuery.proxy(function (_node, _widget) {
                this._enable_toolbar_edit_mode(true, false);
                this.toolbar_duration.set_value(0);
                switch (_widget.getValue()) {
                    case "et2_smallpart_overlay_html_editor":
                        this._editor = et2_core_widget_1.et2_createWidget('smallpart-overlay-html-editor', {
                            width: "100%",
                            height: "100%",
                            class: "smallpart-overlay-element",
                            mode: "simple",
                            statusbar: false
                        }, this._elementsContainer);
                        this._editor.toolbar = "";
                        this._editor.doLoadingFinished();
                }
            }, this);
        }
    };
    /**
     * After video is fully loaded
     * @private
     */
    et2_smallpart_videooverlay.prototype._videoIsLoaded = function () {
        var _this = this;
        this.toolbar_duration.set_max(this.videobar.video[0].duration - this.toolbar_starttime.getValue());
        jQuery(this._elementSlider.getDOMNode()).css({ width: this.videobar.video.width() });
        this.fetchElements(0).then(function () {
            _this.renderElements();
            _this.onSeek(0);
        });
    };
    /**
     * Renders all elements
     * @protected
     */
    et2_smallpart_videooverlay.prototype.renderElements = function (_overlay_id) {
        var self = this;
        if (this._elementsContainer.getChildren().length > 0) {
            this._elementsContainer.getChildren().forEach(function (_widget) {
                if (_overlay_id && _overlay_id == _widget.options.overlay_id) {
                    _widget.destroy();
                    self.fetchElement(_overlay_id).then(function (_attrs) {
                        self.createElement(_attrs);
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
            });
        }
        if (typeof _overlay_id == 'undefined')
            this._elementSlider.set_value(this.elements);
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
     * @param number _time
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
     * Periodically called while video is playing to add new overlay elements
     *
     * @param number _time
     */
    et2_smallpart_videooverlay.prototype.onTimeUpdate = function (_time) {
        var _this = this;
        this._elementSlider.set_seek_position(this.videobar._vtimeToSliderPosition(_time));
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
     * @param _element
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
        var _a;
        // prevent creating an element if already exists
        for (var _i = 0, _b = this._elementsContainer.getChildren(); _i < _b.length; _i++) {
            var _widget = _b[_i];
            if (_widget.options.overlay_id == _attrs.overlay_id) {
                return;
            }
        }
        this._elementsContainer.addChild(et2_core_widget_1.et2_createWidget(_attrs.overlay_type, jQuery.extend(true, {}, _attrs), this._elementsContainer));
        if (_attrs.overlay_player_mode & et2_videooverlay_interface_1.PlayerMode.Pause) {
            (_a = this.videobar) === null || _a === void 0 ? void 0 : _a.pause_video();
        }
        if (_attrs.overlay_player_mode & et2_videooverlay_interface_1.PlayerMode.Disable) {
            // ToDo: this.videobar?.
        }
    };
    et2_smallpart_videooverlay.prototype._onresize_videobar = function (_width, _height, _position) {
        jQuery(this._elementSlider.getDOMNode()).css({ width: _width });
        console.log('video:' + this.videobar.video.width());
        console.log('resize:' + _width);
        this._elementSlider.set_seek_position(_position);
        this.renderElements();
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
        editable: {
            name: 'Editable',
            type: 'boolean',
            description: 'Make overlay editable',
        }
    };
    return et2_smallpart_videooverlay;
}(et2_core_baseWidget_1.et2_baseWidget));
et2_core_widget_1.et2_register_widget(et2_smallpart_videooverlay, ["smallpart-videooverlay"]);
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
        if (typeof _id_or_widget === 'string') {
            _id_or_widget = this.getRoot().getWidgetById(_id_or_widget);
        }
        if (_id_or_widget instanceof et2_widget_videobar_1.et2_smallpart_videobar) {
            this.videobar = _id_or_widget;
        }
    };
    /**
     * set given elements as actual marks on sliderbar
     * @param _elements
     */
    et2_smallpart_videooverlay_slider_controller.prototype.set_value = function (_elements) {
        this.marks_positions = [];
        this.marks = [];
        this.elements = _elements;
        for (var i = this.getChildren().length - 1; i >= 0; i--) {
            this.getChildren()[i].destroy();
        }
        var self = this;
        this.elements.forEach(function (_element, _idx) {
            self.marks[_element.overlay_id] = et2_core_widget_1.et2_createWidget('description', {
                id: et2_smallpart_videooverlay_slider_controller.mark_id_prefix + _element.overlay_id,
            }, self);
            self.marks[_element.overlay_id].onclick = function (_node, _widget) {
                if (typeof self.options.onclick_callback == 'function') {
                    var markWidget = _widget;
                    self.onclick_callback(_node, _widget);
                    self._set_selected(_widget);
                }
            };
            self.marks[_element.overlay_id].doLoadingFinished();
            var pos = self._find_position(self.marks_positions, {
                left: self.videobar._vtimeToSliderPosition(_element.overlay_start),
                width: self.videobar._vtimeToSliderPosition(_element.overlay_duration), row: 0
            }, 0);
            self.marks_positions.push(pos);
            // set its actuall position in DOM
            jQuery(self.marks[_element.overlay_id].getDOMNode()).css({ left: pos.left + 'px', width: pos.width + 'px', top: pos.row != 0 ? pos.row * (5 + 2) : pos.row + 'px' });
        });
    };
    /**
     * set currently selected mark
     * @param _widget
     */
    et2_smallpart_videooverlay_slider_controller.prototype._set_selected = function (_widget) {
        this._selected = _widget;
        _widget.set_class('selected');
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
            overlay_id: this._selected.id.split(et2_smallpart_videooverlay_slider_controller.mark_id_prefix)[1]
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
        }
    };
    et2_smallpart_videooverlay_slider_controller.mark_id_prefix = "slider-tag-";
    return et2_smallpart_videooverlay_slider_controller;
}(et2_core_baseWidget_1.et2_baseWidget));
et2_core_widget_1.et2_register_widget(et2_smallpart_videooverlay_slider_controller, ["smallpart-videooverlay-slider-controller"]);
//# sourceMappingURL=et2_widget_videooverlay.js.map