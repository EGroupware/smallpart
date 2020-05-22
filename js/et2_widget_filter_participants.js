"use strict";
/**
 * EGroupware - SmallParT - filter participants widget
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
var et2_widget_taglist_1 = require("../../api/js/etemplate/et2_widget_taglist");
var et2_core_widget_1 = require("../../api/js/etemplate/et2_core_widget");
var et2_core_inheritance_1 = require("../../api/js/etemplate/et2_core_inheritance");
var et2_smallpart_filter_participants = /** @class */ (function (_super) {
    __extends(et2_smallpart_filter_participants, _super);
    /**
     * Construtor
     */
    function et2_smallpart_filter_participants(_parent, _attrs, _child) {
        var _this = 
        // Call the inherited constructor
        _super.call(this, _parent, _attrs, et2_core_inheritance_1.ClassWithAttributes.extendAttributes(et2_smallpart_filter_participants._attributes, _child || {})) || this;
        _this.div.addClass('smallpart_filter_participants');
        return _this;
    }
    /**
     * Render a single item, taking care of correctly escaping html special chars
     *
     * @param item
     * @returns {String}
     */
    et2_smallpart_filter_participants.prototype.selectionRenderer = function (item) {
        var label = _super.prototype.selectionRenderer.call(this, item);
        // return only label if it's not an admin
        if (!this.options.is_admin)
            return label;
        var container = jQuery('<div>').addClass('et2_smallpart_filter_participants_container');
        var left = jQuery('<div>').addClass('et2_smallpart_filter_participants_left').appendTo(container);
        left.append(label);
        if (item.name != '') {
            jQuery('<span/>')
                .addClass('name')
                .text(item.name)
                .appendTo(left);
        }
        if (!this.options.no_comments && (typeof item.comments != 'undefined' || typeof item.retweets != 'undefined')) {
            var right = jQuery('<div>').addClass('et2_smallpart_filter_participants_right').appendTo(container);
            jQuery('<label/>')
                .text(egw.lang('Comments') + ":")
                .appendTo(right)
                .append(jQuery('<span/>').text(item.comments));
            jQuery('<label/>')
                .text(egw.lang('Retweets') + ":")
                .appendTo(right)
                .append(jQuery('<span/>').text(item.retweets));
        }
        return container;
    };
    et2_smallpart_filter_participants._attributes = {
        is_admin: {
            name: 'Is admin',
            type: 'boolean',
            description: 'Enables extra admin features',
            default: false
        },
        no_comments: {
            name: 'no comments',
            type: 'boolean',
            description: 'shows only label and name if it is switched on',
            default: false
        },
        "minChars": {
            default: 0
        },
        "autocomplete_url": {
            "default": ""
        },
        "autocomplete_params": {
            "default": {}
        },
        allowFreeEntries: {
            "default": false,
            ignore: true
        }
    };
    return et2_smallpart_filter_participants;
}(et2_widget_taglist_1.et2_taglist));
et2_core_widget_1.et2_register_widget(et2_smallpart_filter_participants, ["smallpart-filter-participants"]);
//# sourceMappingURL=et2_widget_filter_participants.js.map