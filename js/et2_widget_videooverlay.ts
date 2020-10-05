/**
 * EGroupware SmallPART - Videooverlay
 *
 * @license http://opensource.org/licenses/gpl-license.php GPL - GNU General Public License
 * @package smallpart
 * @subpackage ui
 * @link https://www.egroupware.org
 * @author Ralf Becker <rb@egroupware.org>
 */

/*egw:uses
	et2_core_baseWidget;
*/

import { et2_baseWidget } from "../../api/js/etemplate/et2_core_baseWidget";
import {et2_createWidget, et2_register_widget, et2_widget, WidgetConfig} from "../../api/js/etemplate/et2_core_widget";
import {ClassWithAttributes} from "../../api/js/etemplate/et2_core_inheritance";
import {et2_description} from "../../api/js/etemplate/et2_widget_description";
import {et2_smallpart_videobar} from "./et2_widget_videobar";

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
class et2_smallpart_videooverlay extends et2_baseWidget
{
	static readonly _attributes : any = {
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
		videobar : {
			name: 'videobar',
			type: 'string',
			description: 'videobar this overlay is for',
		}
	};

	/**
	 * Loaded overlay elements
	 */
	protected elements : Array<OverlayElement>;
	/**
	 * Total number of overlay elements (might not all be loaded)
	 */
	protected total : number;
	/**
	 * Attributes
	 */
	protected video_id : number;
	protected get_elements_callback : string;
	protected videobar : et2_smallpart_videobar;

	div: JQuery;

	/**
	 * Constructor
	 */
	constructor(_parent, _attrs? : WidgetConfig, _child? : object)
	{
		// Call the inherited constructor
		super(_parent, _attrs, ClassWithAttributes.extendAttributes(et2_smallpart_videooverlay._attributes, _child || {}));

		this.div = jQuery(document.createElement("div"))
			.addClass("et2_" + this.getType());

		this.setDOMNode(this.div[0]);
	}

	/**
	 * Set video ID
	 *
	 * @param _id
	 */
	set_video_id(_id : number)
	{
		if (_id === this.video_id) return;

		this.iterateOver(function(_widget : et2_IOverlayElement)
		{
			_widget.destroy();
			this.removeChild(_widget);
		}.bind(this), this, et2_IOverlayElement);

		this.elements = [];

		this.video_id = _id;
		this.fetchElements(0);
	}

	/**
	 * Set videobar to use
	 *
	 * @param _id_or_widget
	 */
	set_videobar(_id_or_widget : string|et2_smallpart_videobar)
	{
		if (typeof _id_or_widget === 'string')
		{
			_id_or_widget = <et2_smallpart_videobar>this.getRoot().getWidgetById(_id_or_widget);
		}
		if (_id_or_widget instanceof et2_smallpart_videobar)
		{
			this.videobar = _id_or_widget;
		}
	}

	/**
	 * Load overlay elements from server
	 *
	 * @param _start
	 * @return Promise
	 */
	protected fetchElements(_start : number)
	{
		if (!this.get_elements_callback) return;
		// fetch first chunk of overlay elements
		return this.egw().json(this.get_elements_callback, [this.video_id, _start], function(_data)
		{
			this.elements.concat(..._data.elements);
			this.total = _data.total;
		}.bind(this)).sendRequest();
	}

	/**
	 * Called when video is seeked to a certain position to create and remove elements
	 *
	 * Every running element / child is asked if it want's to keep running.
	 *
	 * @param number _time
	 */
	onSeek(_time : number)
	{
		this.iterateOver(function(_widget : et2_IOverlayElement)
		{
			if (!_widget.keepRunning(_time))
			{
				this.deleteElement(_widget);
			}
		}.bind(this), this, et2_IOverlayElement);

		this.onTimeUpdate(_time);
	}

	/**
	 * Periodically called while video is playing to add new overlay elements
	 *
	 * @param number _time
	 */
	onTimeUpdate(_time : number)
	{
		let running = [];
		this.iterateOver(function(_widget : et2_IOverlayElement)
		{
			running.push(_widget.options.overlay_id);
		}.bind(this), this, et2_IOverlayElement);

		this.elements.forEach(function(_element)
		{
			if (running.indexOf(_element.overlay_id) !== -1 &&
				_element.overlay_start == Math.floor(_time))
			{
				this.createElement(_element);
			}
		}.bind(this));
	}

	/**
	 * Called by element to be removed when it's done
	 *
	 * @param _element
	 */
	deleteElement(_element : et2_IOverlayElement)
	{
		_element.destroy();
		this.removeChild(_element);
	}

	/**
	 * Create / show an overlay-element and add it to children
	 *
	 * @param _attrs
	 */
	createElement(_attrs : OverlayElement)
	{
		this.addChild(et2_createWidget(_attrs.overlay_type, _attrs, this));

		if (_attrs.overlay_player_mode & PlayerMode.Pause)
		{
			this.videobar?.pause_video();
		}
		if (_attrs.overlay_player_mode & PlayerMode.Disable)
		{
			// ToDo: this.videobar?.
		}
	}
}
et2_register_widget(et2_smallpart_videooverlay, ["smallpart-videooverlay"]);

/**
 * Data of a overlay element
 */
export interface OverlayElement {
	overlay_id? : number;
	course_id? : number;
	video_id : number;
	overlay_type : string;
	overlay_start : number;
	overlay_player_mode : PlayerMode;
	[propName: string]: any;
}
export enum PlayerMode {
	Unchanged,	// continue playing
	Pause,		// pause the video, if playing
	Disable,	// disable all player controls: start, stop, pause, seek
}

/**
 * Interface for an overlay elements managed by et2_widget_videooverlay
 */
export interface et2_IOverlayElement extends et2_baseWidget
{
	/**
	 * Callback called by parent if user eg. seeks the video to given time
	 *
	 * @param number _time new position of the video
	 * @return boolean true: elements wants to continue, false: element requests to be removed
	 */
	keepRunning(_time : number) : boolean;
}
var et2_IOverlayElement = "et2_IOverlayElement";
function implements_et2_IOverlayElement(obj : et2_widget)
{
	return implements_methods(obj, ["keepRunning"]);
}

/**
 * Overlay element to show some text
 */
export class et2_smallpart_overlay_text extends et2_description implements et2_IOverlayElement
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
		duration: {
			name: 'duration',
			type: 'integer',
			description: 'how long to show the element, unset of no specific type, eg. depends on user interaction',
			default: 5
		}
	};

	protected timeout_handle;

	/**
	 * Constructor
	 */
	constructor(_parent, _attrs? : WidgetConfig, _child? : object)
	{
		// Call the inherited constructor
		super(_parent, _attrs, ClassWithAttributes.extendAttributes(et2_smallpart_overlay_text._attributes, _child || {}));

		if (this.options.duration) this.setTimeout();
	}

	/**
	 * Destructor
	 */
	destroy()
	{
		this.clearTimeout();
		super.destroy();
	}

	/**
	 * Clear timeout in case it's set
	 */
	protected clearTimeout()
	{
		if (typeof this.timeout_handle !== 'undefined')
		{
			window.clearTimeout(this.timeout_handle);
			delete (this.timeout_handle);
		}
	}

	/**
	 * Set timeout to observer duration
	 *
	 * @param _duration in seconds, default options.duration
	 */
	protected setTimeout(_duration? : number)
	{
		this.clearTimeout();
		this.timeout_handle = window.setTimeout(function()
		{
			this.parent.deleteElement(this);
		}.bind(this), 1000 * (_duration || this.options.duration));
	}

	/**
	 * Callback called by parent if user eg. seeks the video to given time
	 *
	 * @param number _time new position of the video
	 * @return boolean true: elements wants to continue, false: element requests to be removed
	 */
	keepRunning(_time : number) : boolean
	{
		if (typeof this.options.duration !== 'undefined')
		{
			if (this.options.overlay_start <= _time && _time < this.options.overlay_start + this.options.duration)
			{
				this.setTimeout(this.options.overlay_start + this.options.duration - _time);
				return true;
			}
			return false;
		}
		return true;
	}
}
et2_register_widget(et2_smallpart_overlay_text, ["smallpart-overlay-text"]);