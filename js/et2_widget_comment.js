"use strict";
/**
 * EGroupware - SmallParT - videobar widget
 *
 * @link https://www.egroupware.org
 * @package smallpart
 * @subpackage Ui
 * @license https://spdx.org/licenses/AGPL-3.0-or-later.html GNU Affero General Public License v3.0 or later
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
exports.et2_smallpart_comment = void 0;
var et2_core_widget_1 = require("../../api/js/etemplate/et2_core_widget");
var et2_core_inheritance_1 = require("../../api/js/etemplate/et2_core_inheritance");
var et2_core_valueWidget_1 = require("../../api/js/etemplate/et2_core_valueWidget");
/**
 * Format an array of the following form ["text", account_id1|"nick1", "comment1", ...] like:
 *
 *   text
 * 		--> nick1) comment1
 * 			--> nick2) comment2
 */
var et2_smallpart_comment = /** @class */ (function (_super) {
    __extends(et2_smallpart_comment, _super);
    /**
     * Constructor
     */
    function et2_smallpart_comment(_parent, _attrs, _child) {
        var _this = 
        // Call the inherited constructor
        _super.call(this, _parent, _attrs, et2_core_inheritance_1.ClassWithAttributes.extendAttributes(et2_smallpart_comment._attributes, _child || {})) || this;
        _this.div = null;
        _this.nicks = {};
        _this._time = '';
        _this.value = [''];
        _this.div = jQuery(document.createElement('div'))
            .addClass('et2_smallpart_comment');
        _this.setDOMNode(_this.div[0]);
        return _this;
    }
    et2_smallpart_comment.prototype.getValue = function () {
        return this.value;
    };
    /**
     * Set value
     *
     * @param _value
     */
    et2_smallpart_comment.prototype.set_value = function (_value) {
        var _this = this;
        if (!Array.isArray(_value))
            _value = [_value];
        this.value = _value;
        var self = this;
        this.div.empty();
        this.div.text(this.value[0]);
        if (this._time !== '')
            this.div.prepend(jQuery('<span class="et2_smallpart_comment_time"/>').text(this._time));
        var div = this.div;
        for (var n = 1; n < this.value.length; n += 2) {
            var user = this.value[n];
            if (typeof user === "string" && !parseInt(user)) {
                var match = user.match(/\[(\d+)\]$/); // old: "first name [account_id]"
                if (match && match.length > 1)
                    user = this.value[n] = parseInt(match[1]);
            }
            if (!Object.keys(this.nicks).length) {
                var participants = this.getRoot().getArrayMgr('sel_options').getEntry('account_id');
                participants.forEach(function (participant) {
                    _this.nicks[participant.value] = participant.label;
                });
            }
            div = jQuery(document.createElement('div'))
                .text(this.value[n + 1])
                .addClass('et2_smallpart_comment_retweet')
                .prepend(jQuery('<span class="et2_smallpart_comment_retweeter"/>')
                .text(this.nicks[user] || '#' + user))
                .prepend('<span class="glyphicon glyphicon-arrow-right"/>')
                .appendTo(div);
        }
    };
    et2_smallpart_comment.prototype.set_starttime = function (_time) {
        if (!isNaN(_time)) {
            this._time = sprintf('%02d:%02d:%02d', ~~(_time / 3600), ~~(_time / 60), _time % 60);
        }
        else {
            this._time = '';
        }
    };
    et2_smallpart_comment.prototype.set_stoptime = function (_time) {
        if (!isNaN(_time)) {
            this._time += '-' + sprintf('%02d:%02d:%02d', ~~(_time / 3600), ~~(_time / 60), _time % 60);
        }
        else {
            this._time += '';
        }
    };
    /**
     * Code for implementing et2_IDetachedDOM (data grid)
     *
     * @param {array} _attrs array of attribute-names to push further names onto
     */
    et2_smallpart_comment.prototype.getDetachedAttributes = function (_attrs) {
        _attrs.push('value', 'time');
    };
    et2_smallpart_comment.prototype.getDetachedNodes = function () {
        return [this.div[0]];
    };
    et2_smallpart_comment.prototype.setDetachedAttributes = function (_nodes, _values) {
        this.div = jQuery(_nodes[0]);
        if (typeof _values['value'] != 'undefined') {
            this.set_value(_values['value']);
        }
        this.set_label(_values['time']);
    };
    et2_smallpart_comment._attributes = {
        value: {
            name: 'value',
            type: 'any',
            description: 'SmallParT comment array incl. retweets: ["text", account_id1|"nick1", "comment1", ...]',
            default: et2_no_init
        },
        starttime: {
            name: 'starttime',
            type: 'integer',
            description: 'optional starttime to display before first comment',
            default: et2_no_init
        },
        stoptime: {
            name: 'stoptime',
            type: 'integer',
            description: 'optional stoptime to display before first comment',
            default: et2_no_init
        }
    };
    return et2_smallpart_comment;
}(et2_core_valueWidget_1.et2_valueWidget));
exports.et2_smallpart_comment = et2_smallpart_comment;
et2_core_widget_1.et2_register_widget(et2_smallpart_comment, ["smallpart-comment"]);
//# sourceMappingURL=et2_widget_comment.js.map