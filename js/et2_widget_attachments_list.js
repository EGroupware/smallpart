"use strict";
/**
 * EGroupware - SmallParT - attachments list widget
 *
 * @link https://www.egroupware.org
 * @package smallpart
 * @subpackage Ui
 * @author Hadi Nategh<hn@egroupware.org>
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
exports.et2_smallpart_attachments_list = void 0;
var et2_widget_vfs_1 = require("../../api/js/etemplate/et2_widget_vfs");
var et2_core_widget_1 = require("../../api/js/etemplate/et2_core_widget");
var et2_core_inheritance_1 = require("../../api/js/etemplate/et2_core_inheritance");
var et2_widget_dialog_1 = require("../../api/js/etemplate/et2_widget_dialog");
var et2_smallpart_attachments_list = /** @class */ (function (_super) {
    __extends(et2_smallpart_attachments_list, _super);
    /**
     * Constructor
     *
     * @param _parent
     * @param attrs
     * @memberof et2_vfsUpload
     */
    function et2_smallpart_attachments_list(_parent, _attrs, _child) {
        var _this = 
        // Call the inherited constructor
        _super.call(this, _parent, _attrs, et2_core_inheritance_1.ClassWithAttributes.extendAttributes(et2_smallpart_attachments_list._attributes, _child || {})) || this;
        _this.pdf_list = null;
        _this.image_list = null;
        var row = document.createElement('tr');
        // pdf title column
        var c1 = document.createElement('td');
        var pdf = document.createElement('span').textContent = (egw.lang('pdf') + ':');
        // pdf files column
        var c2 = document.createElement('td');
        c2.style.width = '50%';
        // image title column
        var c3 = document.createElement('td');
        var image = document.createElement('span').textContent = (egw.lang('image') + ':');
        // image files column
        var c4 = document.createElement('td');
        c4.style.width = '50%';
        // pdf vfs row container
        _this.pdf_list = document.createElement('div');
        _this.pdf_list.classList.add('pdf-list');
        // image vfs container
        _this.image_list = document.createElement('div');
        _this.image_list.classList.add('image-list');
        row.append(c1);
        c1.append(pdf);
        row.append(c2);
        c2.append(_this.pdf_list);
        row.append(c3);
        c3.append(image);
        row.append(c4);
        c4.append(_this.image_list);
        _this.list[0].append(row);
        return _this;
    }
    /**
     * If there is a file / files in the specified location, display them
     * Value is the information for the file[s] in the specified location.
     * overwrites the vfsUpload in order to add pdf and image dom into the list
     *
     * @param {Object{}} _value
     */
    et2_smallpart_attachments_list.prototype.set_value = function (_value) {
        var _this = this;
        // Remove previous
        while (this._children.length > 0) {
            var node = this._children[this._children.length - 1];
            this.removeChild(node);
            node.destroy();
        }
        this.progress.empty();
        this.pdf_list.innerHTML = '';
        this.image_list.innerHTML = '';
        // Set new
        if (typeof _value == 'object' && _value && Object.keys(_value).length) {
            for (var i in _value) {
                this._add(_value[i]);
            }
        }
        ['pdf', 'image'].forEach(function (_index) {
            if (_this[_index + '_list'].children.length == 0) {
                _this[_index + '_list'].parentElement.previousElementSibling.style.visibility = 'hidden';
            }
        });
        return true;
    };
    /**
     * build a dom consists of vfs icon + title + delete button
     * @param file_data
     * @private
     */
    et2_smallpart_attachments_list.prototype._buildRow = function (file_data) {
        var row = document.createElement("div");
        row.style.display = 'flex';
        row.classList.add('file-row');
        row.setAttribute("data-path", file_data.path.replace(/'/g, '&quot'));
        row.setAttribute("draggable", "true");
        var icon = document.createElement("div");
        icon.classList.add('icon');
        var title = document.createElement("div");
        title.classList.add('title');
        var mime = et2_core_widget_1.et2_createWidget('vfs-mime', { value: file_data }, this);
        // Trigger expose on click, if supported
        var vfs_attrs = { value: file_data, onclick: undefined };
        if (file_data && (typeof file_data.download_url != 'undefined')) {
            var fe_mime = egw_get_file_editor_prefered_mimes(file_data.mime);
            // Check if the link entry is mime with media type, in order to open it in expose view
            if (typeof file_data.mime === 'string' &&
                (file_data.mime.match(mime.mime_regexp, 'ig') || (fe_mime && fe_mime.mime[file_data.mime]))) {
                vfs_attrs.onclick = function (ev) {
                    ev.stopPropagation();
                    // Pass it off to the associated vfsMime widget
                    jQuery('img', this.parentNode.parentNode).trigger("click");
                    return false;
                };
            }
            else {
                // if there's no handling simply try to open the file with egw file handler (download happens if can't find any handler)
                vfs_attrs.onclick = function (e, widget) {
                    widget.egw().open({ path: widget.value.path, type: widget.value.mime }, 'file');
                };
            }
        }
        var vfs = et2_core_widget_1.et2_createWidget('vfs', vfs_attrs, this);
        // Add in delete button
        if (!this.options.readonly) {
            var self_1 = this;
            var delete_button = document.createElement("div");
            var delete_container = document.createElement("div");
            delete_container.classList.add("delete", "icon");
            delete_container.addEventListener('click', function () {
                et2_core_widget_1.et2_createWidget("dialog", {
                    callback: function (button) {
                        if (button == et2_widget_dialog_1.et2_dialog.YES_BUTTON) {
                            egw.json("filemanager_ui::ajax_action", [
                                'delete',
                                [row.getAttribute('data-path').replace(/&quot/g, "'")],
                                ''
                            ], function (data) {
                                if (data && data.errs == 0) {
                                    row.slideUp(null, row.remove);
                                }
                                if (data && data.msg) {
                                    self_1.egw().message(data.msg, data.errs == 0 ? 'success' : 'error');
                                }
                            }).sendRequest();
                        }
                    },
                    message: self_1.egw().lang('Delete file') + '?',
                    title: self_1.egw().lang('Confirmation required'),
                    buttons: et2_widget_dialog_1.et2_dialog.BUTTONS_YES_NO,
                    dialog_type: et2_widget_dialog_1.et2_dialog.QUESTION_MESSAGE,
                    width: 250
                }, self_1);
            });
            delete_button.append(delete_container);
            row.append(delete_button);
        }
        row.prepend(title);
        row.prepend(icon);
        return row;
    };
    /**
     * Adds given file data as DOM into its relative list base on mime type
     * @param file_data
     * @private
     */
    et2_smallpart_attachments_list.prototype._add = function (file_data) {
        // Set up for expose
        if (file_data && typeof file_data.download_url === "undefined") {
            file_data.download_url = "/webdav.php" + file_data.path;
        }
        if (file_data.mime.match(/pdf/)) {
            this.pdf_list.append(this._buildRow(file_data));
        }
        else {
            this.image_list.append(this._buildRow(file_data));
        }
    };
    et2_smallpart_attachments_list._attributes = {
        "listonly": {
            "name": "List Only",
            "description": "Display given file objects only as list (removes span,input and progress from the dom)",
            "type": "boolean",
            "default": true
        }
    };
    return et2_smallpart_attachments_list;
}(et2_widget_vfs_1.et2_vfsUpload));
exports.et2_smallpart_attachments_list = et2_smallpart_attachments_list;
et2_core_widget_1.et2_register_widget(et2_smallpart_attachments_list, ["smallpart-attachments-list"]);
//# sourceMappingURL=et2_widget_attachments_list.js.map