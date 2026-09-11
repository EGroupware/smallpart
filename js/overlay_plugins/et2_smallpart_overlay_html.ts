/**
 * EGroupware SmallPART - Videooverlay html plugin
 *
 * @license http://opensource.org/licenses/gpl-license.php GPL - GNU General Public License
 * @package smallpart
 * @subpackage ui
 * @link https://www.egroupware.org
 * @author Ralf Becker <rb@egroupware.org>
 */

import {Et2HtmlArea} from "../../../api/js/etemplate/Et2HtmlArea/Et2HtmlArea";
import {et2_IOverlayElementEditor} from "../et2_videooverlay_interface";
import {et2_IOverlayElement} from "../et2_videooverlay_interface";
import {Et2Html} from "../../../api/js/etemplate/Et2Html/Et2Html";

/**
 * Overlay element to show some html
 *
 * Ported 2026-09-04 from a legacy et2_html subclass to a real webcomponent - the legacy
 * (_parent, _attrs, _child) constructor pattern is incompatible with a modern, already-registered
 * custom element base (`new` on a class that extends a registered custom element throws "Illegal
 * constructor" unless created via document.createElement()/loadWebComponent()).
 */
export class et2_smallpart_overlay_html extends Et2Html implements et2_IOverlayElement
{
	static get properties()
	{
		return {
			...super.properties,
			overlay_id: {type: Number},
			course_id: {type: Number},
			video_id: {type: Number},
			overlay_type: {type: String},
			overlay_start: {type: Number},
			overlay_player_mode: {type: Number},
			overlay_duration: {type: Number},
			offset: {type: Number}
		};
	}

	overlay_id : number;
	course_id : number;
	video_id : number;
	overlay_type : string;
	overlay_start : number = 0;
	overlay_player_mode : number = 0;
	overlay_duration : number = 1;
	offset : number = 16;

	/** Legacy attribute name for the html content - forwards to Et2Html's own `value`. */
	set data(_value : string)
	{
		this.value = _value;
	}

	get data() : string
	{
		return this.value;
	}

	connectedCallback()
	{
		super.connectedCallback();
		this.classList.add(this.getType());
		this.style.fontSize = String(this.egw().preference('rte_font_size', 'common')) + this.egw().preference('rte_font_unit', 'common');
		this.style.fontFamily = <string>this.egw().preference('rte_font', 'common');
		this.set_offset(this.offset);
	}

	set_offset(_value : number)
	{
		this.offset = _value;
		this.style.margin = this.offset + 'px';
	}

	/**
	 * Callback called by parent if user eg. seeks the video to given time
	 *
	 * @param _time new position of the video
	 * @return boolean true: elements wants to continue, false: element requests to be removed
	 */
	keepRunning(_time : number) : boolean
	{
		if(typeof this.overlay_duration !== 'undefined')
		{
			return this.overlay_start <= _time && _time < this.overlay_start + this.overlay_duration;
		}
		return true;
	}
}
customElements.define("et2-smallpart-overlay-html", et2_smallpart_overlay_html);

/**
 * Editor widget
 *
 * Ported 2026-09-04, same reasoning as et2_smallpart_overlay_html above.
 */
export class et2_smallpart_overlay_html_editor extends Et2HtmlArea implements et2_IOverlayElementEditor
{
	static get properties()
	{
		return {
			...super.properties,
			overlay_id: {type: Number},
			offset: {type: Number}
		};
	}

	overlay_id : number;
	offset : number = 0;

	connectedCallback()
	{
		super.connectedCallback();
		if(this.offset)
		{
			this.set_offset(this.offset);
		}
		this.tinymce.then(() =>
		{
			this.set_offset(this.offset);
		});
	}

	set_offset(_value : number)
	{
		this.offset = _value;
		if(this.editor)
		{
			this.editor.iframeElement.contentWindow.document.body.style.margin = this.offset + 'px';
		}
	}

	/**
	 * Save callback
	 * @param _data
	 * @param _onSuccessCallback
	 */
	onSaveCallback(_data, _onSuccessCallback)
	{
		let html = this.getValue();
		let data = Object.assign(_data, {
			'overlay_type': 'smallpart-overlay-html',
			'data': html
		});
		if(this.overlay_id) data.overlay_id = this.overlay_id;
		this.egw().json('smallpart.\\EGroupware\\SmallParT\\Overlay.ajax_write', [data], function(_overlay_response)
		{
			data['overlay_id'] = _overlay_response.overlay_id;
			if(typeof _onSuccessCallback == "function") _onSuccessCallback([data]);
		}).sendRequest();
	}
}
customElements.define("et2-smallpart-overlay-html-editor", et2_smallpart_overlay_html_editor);
