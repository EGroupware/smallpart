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
exports.et2_smallpart_overlay_html_editor = exports.et2_smallpart_overlay_html = void 0;
var et2_widget_htmlarea_1 = require("../../../api/js/etemplate/et2_widget_htmlarea");
var et2_core_widget_1 = require("../../../api/js/etemplate/et2_core_widget");
var et2_core_inheritance_1 = require("../../../api/js/etemplate/et2_core_inheritance");
var et2_widget_html_1 = require("../../../api/js/etemplate/et2_widget_html");
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
        _this.set_class(_this.getType());
        _this.set_value(_attrs.data);
        jQuery(_this.getDOMNode()).css({ 'font-size': egw.preference('rte_font_size', 'common')
                + egw.preference('rte_font_unit', 'common'), 'font-family': egw.preference('rte_font', 'common') });
        if (typeof _attrs.offset != 'undefined')
            _this.set_offset(_attrs.offset);
        return _this;
    }
    et2_smallpart_overlay_html.prototype.set_offset = function (_value) {
        jQuery(this.getDOMNode()).css({ margin: this.options.offset + 'px' });
    };
    /**
     * Callback called by parent if user eg. seeks the video to given time
     *
     * @param _time new position of the video
     * @return boolean true: elements wants to continue, false: element requests to be removed
     */
    et2_smallpart_overlay_html.prototype.keepRunning = function (_time) {
        if (typeof this.options.overlay_duration !== 'undefined') {
            return this.options.overlay_start <= _time && _time < this.options.overlay_start + this.options.overlay_duration;
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
        overlay_duration: {
            name: 'duration',
            type: 'integer',
            description: 'how long to show the element, unset of no specific type, eg. depends on user interaction',
            default: 1
        },
        offset: {
            name: 'offset margin',
            type: 'string',
            description: 'offset margin',
            default: 16
        },
        data: {
            name: 'html content',
            type: 'html',
            description: 'the html to display',
            default: ''
        }
    };
    return et2_smallpart_overlay_html;
}(et2_widget_html_1.et2_html));
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
        var _this = 
        // Call the inherited constructor
        _super.call(this, _parent, _attrs, et2_core_inheritance_1.ClassWithAttributes.extendAttributes(et2_smallpart_overlay_html_editor._attributes, _child || {})) || this;
        _this.offset = 0;
        if (_this.options.offset)
            _this.set_offset(_this.options.offset);
        return _this;
    }
    et2_smallpart_overlay_html_editor.prototype.set_offset = function (_value) {
        this.offset = _value;
        if (this.editor) {
            jQuery(this.editor.iframeElement.contentWindow.document.body).css({ margin: this.offset + 'px' });
        }
    };
    et2_smallpart_overlay_html_editor.prototype.doLoadingFinished = function () {
        var ret = _super.prototype.doLoadingFinished.call(this);
        var self = this;
        this.tinymce.then(function () {
            self.set_offset(self.offset);
        });
        return ret;
    };
    /**
     * Save callback
     * @param _data
     * @param _onSuccessCallback
     */
    et2_smallpart_overlay_html_editor.prototype.onSaveCallback = function (_data, _onSuccessCallback) {
        var html = this.getValue();
        var data = jQuery.extend(true, _data, {
            'overlay_type': 'smallpart-overlay-html',
            'data': html
        });
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
        },
        offset: {
            name: 'offset margin',
            type: 'string',
            description: 'offset margin',
            default: 16
        }
    };
    return et2_smallpart_overlay_html_editor;
}(et2_widget_htmlarea_1.et2_htmlarea));
exports.et2_smallpart_overlay_html_editor = et2_smallpart_overlay_html_editor;
et2_core_widget_1.et2_register_widget(et2_smallpart_overlay_html_editor, ["smallpart-overlay-html-editor"]);
//# sourceMappingURL=et2_smallpart_overlay_html.js.map