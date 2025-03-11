/**
 * EGroupware - SmallParT - attachments list widget
 *
 * @link https://www.egroupware.org
 * @package smallpart
 * @subpackage Ui
 * @author Hadi Nategh<hn@egroupware.org>
 * @license http://opensource.org/licenses/gpl-license.php GPL - GNU General Public License
 */

import {et2_vfs, et2_vfsMime, et2_vfsUpload} from "../../api/js/etemplate/et2_widget_vfs";
import {et2_createWidget, et2_register_widget, WidgetConfig} from "../../api/js/etemplate/et2_core_widget";
import {ClassWithAttributes} from "../../api/js/etemplate/et2_core_inheritance";
import {Et2Dialog} from "../../api/js/etemplate/Et2Dialog/Et2Dialog";

export class et2_smallpart_attachments_list extends et2_vfsUpload
{

	static readonly _attributes : any = {
		"listonly": {
			"name": "List Only",
			"description": "Display given file objects only as list (removes span,input and progress from the dom)",
			"type": "boolean",
			"default": true
		}
	};

	pdf_list : HTMLDivElement = null;
	image_list : HTMLDivElement = null;

	/**
	 * Constructor
	 *
	 * @param _parent
	 * @param attrs
	 * @memberof et2_vfsUpload
	 */
	constructor(_parent, _attrs?: WidgetConfig, _child?: object)
	{
		// Call the inherited constructor
		super(_parent, _attrs, ClassWithAttributes.extendAttributes(et2_smallpart_attachments_list._attributes, _child || {}));

		let row = document.createElement('tr');
		// pdf title column
		let c1 = document.createElement('td');
		// pdf files column
		let c2 = document.createElement('td');
		c2.style.width = '50%';

		// image title column
		let c3 = document.createElement('td');
		// image files column
		let c4 = document.createElement('td');
		c4.style.width = '50%';

		// pdf vfs row container
		this.pdf_list = document.createElement('div');
		this.pdf_list.classList.add('pdf-list');
		// image vfs container
		this.image_list =  document.createElement('div');
		this.image_list.classList.add('image-list');

		row.append(c1);
		row.append(c2);
		c2.append(this.pdf_list);
		row.append(c3);
		row.append(c4);
		c4.append(this.image_list);

		this.list[0].append(row);
	}

	/**
	 * If there is a file / files in the specified location, display them
	 * Value is the information for the file[s] in the specified location.
	 * overwrites the vfsUpload in order to add pdf and image dom into the list
	 *
	 * @param {Object{}} _value
	 */
	set_value(_value) {
		// Remove previous
		while(this._children.length > 0)
		{
			var node = this._children[this._children.length - 1];
			this.removeChild(node);
			node.destroy();
		}
		this.progress.empty();
		this.pdf_list.innerHTML = '';
		this.image_list.innerHTML = '';

		// Set new
		if(typeof _value == 'object' && _value && Object.keys(_value).length)
		{
			for(let i in _value)
			{
				this._add(_value[i]);
			}
		}
		['pdf','image'].forEach(_index => {
			if (this[_index+'_list'].children.length == 0)
			{
				this[_index+'_list'].parentElement.previousElementSibling.style.visibility = 'hidden';
			}
		});
		return true;
	}

	/**
	 * build a dom consists of vfs icon + title + delete button
	 * @param file_data
	 * @private
	 */
	private _buildRow(file_data)
	{
		let row =document.createElement("div");
		row.style.display = 'flex';
		row.classList.add('file-row');
		row.setAttribute("data-path", file_data.path.replace(/'/g, '&quot'));
		row.setAttribute("draggable", "true");

		let icon = document.createElement("div");
		icon.classList.add('icon')

		let title = document.createElement("div");
		title.classList.add('title')

		let mime = <et2_vfsMime> et2_createWidget('vfs-mime',{value: file_data}, this);

		// Trigger expose on click, if supported
		let vfs_attrs = {value: file_data, onclick: undefined};
		if (file_data && (typeof file_data.download_url != 'undefined'))
		{
			var fe_mime = egw.file_editor_prefered_mimes(file_data.mime);
			// Check if the link entry is mime with media type, in order to open it in expose view
			if (typeof file_data.mime === 'string' &&
				(file_data.mime.match(mime.mime_regexp,'ig') || (fe_mime && fe_mime.mime[file_data.mime])))
			{
				vfs_attrs.onclick = function(ev) {
					ev.stopPropagation();
					// Pass it off to the associated vfsMime widget
					jQuery('img',this.parentNode.parentNode).trigger("click");
					return false;
				};
			}
			else
			{
				// if there's no handling simply try to open the file with egw file handler (download happens if can't find any handler)
				vfs_attrs.onclick = function(e, widget){
					widget.egw().open({path:widget.value.path, type:widget.value.mime}, 'file');
				}
			}
		}
		let vfs = <et2_vfs> et2_createWidget('vfs', vfs_attrs, this);


		// Add in delete button
		if (!this.options.readonly) {
			let self = this;
			let delete_button = document.createElement("div");
			let delete_container = document.createElement("div");
			delete_container.classList.add("delete", "icon");
			delete_container.addEventListener('click', function () {
				const dialog = <Et2Dialog>et2_createWidget("et2-dialog", {
						callback: function (button) {
							if(button == Et2Dialog.YES_BUTTON)
							{
								egw.json("filemanager_ui::ajax_action", [
										'delete',
										[row.getAttribute('data-path').replace(/&quot/g, "'")],
										''
									],
									function (data) {
										if (data && data.errs == 0) {
											row.remove();
										}
										if (data && data.msg) {
											self.egw().message(data.msg, data.errs == 0 ? 'success' : 'error');
										}
									}
								).sendRequest();
							}
						},
						message: self.egw().lang('Delete file') + '?',
						title: self.egw().lang('Confirmation required'),
					buttons: Et2Dialog.BUTTONS_YES_NO,
					dialog_type: Et2Dialog.QUESTION_MESSAGE,
						width: 250
					}, self);
				document.body.append(dialog);
				});
			delete_button.append(delete_container);
			row.append(delete_button);
		}

		row.prepend(title);
		row.prepend(icon);

		return row;
	}

	/**
	 * Adds given file data as DOM into its relative list base on mime type
	 * @param file_data
	 * @private
	 */
	private _add(file_data) {

		// Set up for expose
		if(file_data && typeof file_data.download_url === "undefined")
		{
			file_data.download_url = "/webdav.php" + file_data.path;
		}

		file_data.mime = file_data.mime ?? file_data.type
		if(file_data.mime.match(/pdf/))
		{
			this.pdf_list.append(this._buildRow(file_data));
		}
		else
		{
			this.image_list.append(this._buildRow(file_data));
		}
	}
}
et2_register_widget(et2_smallpart_attachments_list, ["smallpart-attachments-list"]);