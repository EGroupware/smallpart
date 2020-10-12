/**
 * EGroupware SmallPART - Videooverlay html plugin
 *
 * @license http://opensource.org/licenses/gpl-license.php GPL - GNU General Public License
 * @package smallpart
 * @subpackage ui
 * @link https://www.egroupware.org
 * @author Ralf Becker <rb@egroupware.org>
 */

import {et2_htmlarea} from "../../../api/js/etemplate/et2_widget_htmlarea";
import {et2_register_widget, WidgetConfig} from "../../../api/js/etemplate/et2_core_widget";
import {ClassWithAttributes} from "../../../api/js/etemplate/et2_core_inheritance";
import {et2_IOverlayElementEditor} from "../et2_videooverlay_interface";
import {et2_IOverlayElement} from "../et2_videooverlay_interface";
import {et2_html} from "../../../api/js/etemplate/et2_widget_html";

/**
 * Overlay element to show some html
 */
export class et2_smallpart_overlay_html extends et2_html implements et2_IOverlayElement
{
	static readonly _attributes : any = {
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
		data: {
			name: 'html content',
			type: 'html',
			description: 'the html to display',
			default: ''
		}
	};

	/**
	 * Constructor
	 */
	constructor(_parent, _attrs? : WidgetConfig, _child? : object)
	{
		// Call the inherited constructor
		super(_parent, _attrs, ClassWithAttributes.extendAttributes(et2_smallpart_overlay_html._attributes, _child || {}));
		this.set_class(this.getType());
		this.set_value(_attrs.data);
	}

	/**
	 * Callback called by parent if user eg. seeks the video to given time
	 *
	 * @param number _time new position of the video
	 * @return boolean true: elements wants to continue, false: element requests to be removed
	 */
	keepRunning(_time : number) : boolean
	{
		if (typeof this.options.overlay_duration !== 'undefined')
		{
			if (this.options.overlay_start <= _time && _time < this.options.overlay_start + this.options.overlay_duration)
			{
				return true;
			}
			return false;
		}
		return true;
	}
}
et2_register_widget(et2_smallpart_overlay_html, ["smallpart-overlay-html"]);

/**
 * Editor widget
 */
export class et2_smallpart_overlay_html_editor extends et2_htmlarea implements et2_IOverlayElementEditor
{
	static readonly _attributes : any = {
		overlay_id: {
			name: 'overlay_id',
			type: 'integer',
			description: 'database id of element',
		}
	};

	/**
	 * Constructor
	 */
	constructor(_parent, _attrs? : WidgetConfig, _child? : object)
	{
		// Call the inherited constructor
		super(_parent, _attrs, ClassWithAttributes.extendAttributes(et2_smallpart_overlay_html_editor._attributes, _child || {}));

	}

	/**
	 * Save callback
	 * @param _data
	 */
	onSaveCallback(_data, _onSuccessCallback)
	{
		let html = this.getValue();
		let data = {
			'course_id': _data.course_id,
			'video_id': _data.video_id,
			'overlay_start': _data.overlay_starttime,
			'overlay_duration': _data.overlay_duration,
			'overlay_type': 'smallpart-overlay-html',
			'data': html
		};
		if (this.options.overlay_id) data.overlay_id = this.options.overlay_id;
		egw.json('smallpart.\\EGroupware\\SmallParT\\Overlay.ajax_write',[data], function(_overlay_response){
			data['overlay_id'] = _overlay_response.overlay_id;
			if (typeof _onSuccessCallback == "function") _onSuccessCallback([data]);
		}).sendRequest();
	}

}
et2_register_widget(et2_smallpart_overlay_html_editor, ["smallpart-overlay-html-editor"]);

