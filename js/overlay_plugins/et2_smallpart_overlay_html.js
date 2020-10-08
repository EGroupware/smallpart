"use strict";
/**
 * EGroupware SmallPART - Videooverlay html plugin
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
var et2_widget_htmlarea_1 = require("../../../api/js/etemplate/et2_widget_htmlarea");
var et2_core_widget_1 = require("../../../api/js/etemplate/et2_core_widget");
var et2_core_inheritance_1 = require("../../../api/js/etemplate/et2_core_inheritance");
var et2_widget_description_1 = require("../../../api/js/etemplate/et2_widget_description");
/**
 * Overlay element to show some html
 */
var et2_smallpart_overlay_html = /** @class */ (function (_super) {
    __extends(et2_smallpart_overlay_html, _super);
    /**
     * Constructor
     */
    function et2_smallpart_overlay_html(_parent, _attrs, _child) {
        var _this = 
        // Call the inherited constructor
        _super.call(this, _parent, _attrs, et2_core_inheritance_1.ClassWithAttributes.extendAttributes(et2_smallpart_overlay_html._attributes, _child || {})) || this;
        if (_this.options.duration)
            _this.setTimeout();
        return _this;
    }
    /**
     * Destructor
     */
    et2_smallpart_overlay_html.prototype.destroy = function () {
        this.clearTimeout();
        _super.prototype.destroy.call(this);
    };
    /**
     * Clear timeout in case it's set
     */
    et2_smallpart_overlay_html.prototype.clearTimeout = function () {
        if (typeof this.timeout_handle !== 'undefined') {
            window.clearTimeout(this.timeout_handle);
            delete (this.timeout_handle);
        }
    };
    /**
     * Set timeout to observer duration
     *
     * @param _duration in seconds, default options.duration
     */
    et2_smallpart_overlay_html.prototype.setTimeout = function (_duration) {
        this.clearTimeout();
        this.timeout_handle = window.setTimeout(function () {
            this._parent.deleteElement(this);
        }.bind(this), 1000 * (_duration || this.options.duration));
    };
    /**
     * Callback called by parent if user eg. seeks the video to given time
     *
     * @param number _time new position of the video
     * @return boolean true: elements wants to continue, false: element requests to be removed
     */
    et2_smallpart_overlay_html.prototype.keepRunning = function (_time) {
        if (typeof this.options.duration !== 'undefined') {
            if (this.options.overlay_start <= _time && _time < this.options.overlay_start + this.options.duration) {
                this.setTimeout(this.options.overlay_start + this.options.duration - _time);
                return true;
            }
            return false;
        }
        return true;
    };
    et2_smallpart_overlay_html._attributes = {
        overlay_id: {
            name: 'overlay_id',
            type: 'integer',
            description: 'database id of element',
        },
        course_id: {
            name: 'course_id',
            type: 'integer',
            description: 'ID of course'
        },
        video_id: {
            name: 'video_id',
            type: 'integer',
            description: 'ID of video'
        },
        overlay_type: {
            name: 'overlay_type',
            type: 'string',
            description: 'type / class-name of overlay element'
        },
        overlay_start: {
            name: 'overlay_start',
            type: 'integer',
            description: 'start-time of element',
            default: 0
        },
        overlay_player_mode: {
            name: 'overlay_player_mode',
            type: 'integer',
            description: 'bit-field: &1 = pause, &2 = disable controls',
            default: 0
        },
        duration: {
            name: 'duration',
            type: 'integer',
            description: 'how long to show the element, unset of no specific type, eg. depends on user interaction',
            default: 5
        }
    };
    return et2_smallpart_overlay_html;
}(et2_widget_description_1.et2_description));
exports.et2_smallpart_overlay_html = et2_smallpart_overlay_html;
et2_core_widget_1.et2_register_widget(et2_smallpart_overlay_html, ["smallpart-overlay-html"]);
/**
 * Editor widget
 */
var et2_smallpart_overlay_html_editor = /** @class */ (function (_super) {
    __extends(et2_smallpart_overlay_html_editor, _super);
    /**
     * Constructor
     */
    function et2_smallpart_overlay_html_editor(_parent, _attrs, _child) {
        // Call the inherited constructor
        return _super.call(this, _parent, _attrs, et2_core_inheritance_1.ClassWithAttributes.extendAttributes(et2_smallpart_overlay_html_editor._attributes, _child || {})) || this;
    }
    /**
     * Save callback
     * @param _data
     */
    et2_smallpart_overlay_html_editor.prototype.onSaveCallback = function (_data, _onSuccessCallback) {
        var html = this.getValue();
        var data = {
            'course_id': _data.course_id,
            'video_id': _data.video_id,
            'overlay_start': _data.overlay_starttime,
            'overlay_duration': _data.overlay_duration,
            'overlay_type': 'smallpart-overlay-html',
            'data': html
        };
        if (this.options.overlay_id)
            data.overlay_id = this.options.overlay_id;
        egw.json('smallpart.\\EGroupware\\SmallParT\\Overlay.ajax_write', [data], function (_overlay_response) {
            data['overlay_id'] = _overlay_response.overlay_id;
            if (typeof _onSuccessCallback == "function")
                _onSuccessCallback([data]);
        }).sendRequest();
    };
    et2_smallpart_overlay_html_editor._attributes = {
        overlay_id: {
            name: 'overlay_id',
            type: 'integer',
            description: 'database id of element',
        }
    };
    return et2_smallpart_overlay_html_editor;
}(et2_widget_htmlarea_1.et2_htmlarea));
exports.et2_smallpart_overlay_html_editor = et2_smallpart_overlay_html_editor;
et2_core_widget_1.et2_register_widget(et2_smallpart_overlay_html_editor, ["smallpart-overlay-html-editor"]);
//# sourceMappingURL=et2_smallpart_overlay_html.js.map