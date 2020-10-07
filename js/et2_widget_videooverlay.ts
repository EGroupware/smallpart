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
import {et2_button} from "../../api/js/etemplate/et2_widget_button";
import {et2_dropdown_button} from "../../api/js/etemplate/et2_widget_dropdown_button";
import {et2_textbox} from "../../api/js/etemplate/et2_widget_textbox";
import {et2_number} from "../../api/js/etemplate/et2_widget_number";
import {et2_htmlarea} from "../../api/js/etemplate/et2_widget_htmlarea";
import jqXHR = JQuery.jqXHR;

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
		videobar : {
			name: 'videobar',
			type: 'string',
			description: 'videobar this overlay is for',
		},
		toolbar_save : {
			name: 'toolbar save',
			type: 'string',
			description: 'Save button in top bar controller',
		},
		toolbar_edit : {
			name: 'toolbar edit',
			type: 'string',
			description: 'edit button in top bar controller',
		},
		toolbar_cancel : {
			name: 'toolbar cancel',
			type: 'string',
			description: 'cancel button in top bar controller',
		},
		toolbar_delete : {
			name: 'toolbar delete',
			type: 'string',
			description: 'delete button in top bar controller',
		},
		toolbar_add : {
			name: 'toolbar add',
			type: 'string',
			description: 'Add button in top bar controller',
		},
		toolbar_starttime : {
			name: 'toolbar starttime',
			type: 'string',
			description: 'start-time in top bar controller',
		},
		toolbar_duration : {
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
	protected course_id : number;
	protected video_id : number;
	protected get_elements_callback : string;
	protected videobar : et2_smallpart_videobar;
	protected toolbar_save: et2_button;
	protected toolbar_delete: et2_button;
	protected toolbar_edit: et2_button;
	protected toolbar_cancel: et2_button;
	protected toolbar_add: et2_dropdown_button;
	protected toolbar_starttime: et2_number;
	protected toolbar_duration: et2_number;

	private _elementsContainer : et2_widget = null;
	private _slider_progressbar : JQuery = null;
	div: JQuery;

	private add: et2_button = null;

	private _editor: any = null;
	/**
	 * Constructor
	 */
	constructor(_parent, _attrs? : WidgetConfig, _child? : object)
	{
		// Call the inherited constructor
		super(_parent, _attrs, ClassWithAttributes.extendAttributes(et2_smallpart_videooverlay._attributes, _child || {}));

		this.div = jQuery(document.createElement("div"))
			.addClass("et2_" + this.getType());
		if (this.options.editable) this.div.addClass('editable');
		this._elementsContainer = et2_createWidget('hbox', {width:"100%", height:"100%", class:"elementsContainer"}, this);
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
	 * Setter for course_id
	 *
	 * @param _id
	 */
	set_course_id(_id : number)
	{
		this.course_id = _id;
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
	 *
	 * @param _id_or_widget
	 */
	set_toolbar_save(_id_or_widget : string|et2_button)
	{
		if (!this.options.editable) return;

		if (typeof _id_or_widget === 'string')
		{
			_id_or_widget = <et2_button>this.getRoot().getWidgetById(_id_or_widget);
		}
		if (_id_or_widget instanceof et2_button)
		{
			this.toolbar_save = _id_or_widget;
			this.toolbar_save.onclick = jQuery.proxy(function(){
				this._enable_toolbar_edit_mode(false, false);
				this._editor.onSaveCallback({
					'course_id': this.course_id,
					'video_id': this.video_id,
					'overlay_duration': this.toolbar_duration.getValue(),
					'overlay_starttime': this.toolbar_starttime.getType(),
					'videobar':this.videobar
				});
				this._editor.destroy();
			}, this);
		}
	}

	set_toolbar_edit(_id_or_widget : string|et2_button)
	{
		if (!this.options.editable) return;

		if (typeof _id_or_widget === 'string')
		{
			_id_or_widget = <et2_button>this.getRoot().getWidgetById(_id_or_widget);
		}
		if (_id_or_widget instanceof et2_button)
		{
			this.toolbar_edit = _id_or_widget;
			this.toolbar_edit.onclick = jQuery.proxy(function(){
				this._enable_toolbar_edit_mode(true, true);
			}, this);
		}
	}

	private _enable_toolbar_edit_mode(_state : boolean, _deleteEnabled? : boolean)
	{
		this.toolbar_edit.set_disabled(true);

		if (_state)
		{
			this.toolbar_starttime.set_value(Math.floor(this.videobar.video[0].currentTime));
			this.toolbar_duration.set_max(Math.floor(this.videobar.video[0].duration - this.toolbar_starttime.getValue()));

			// slider progressbar span
			this._slider_progressbar = jQuery(document.createElement('span'))
				.addClass('overlay_slider_progressbar')
				.css({
					left:this.videobar._vtimeToSliderPosition(parseInt(this.toolbar_starttime.getValue())),
					width:this.videobar._vtimeToSliderPosition(parseInt(this.toolbar_duration.getValue()))
				})
				.appendTo(this.videobar.getSliderDOMNode());
			jQuery(this.getDOMNode()).addClass('editmode');
		}
		else
		{
			jQuery(this.getDOMNode()).removeClass('editmode');
			if (this._slider_progressbar) this._slider_progressbar.remove();
			this.toolbar_duration.set_value(0);
		}
		this.toolbar_save.set_disabled(!_state);
		this.toolbar_delete.set_disabled(!(_state && _deleteEnabled));
		this.toolbar_add.set_disabled(_state);
		this.toolbar_duration.set_disabled(!_state);
		this.toolbar_starttime.set_disabled(!_state);
		this.toolbar_cancel.set_disabled(!_state);
		this.toolbar_starttime.set_readonly(true);
	}

	set_toolbar_cancel(_id_or_widget : string|et2_button)
	{
		if (!this.options.editable) return;

		if (typeof _id_or_widget === 'string')
		{
			_id_or_widget = <et2_button>this.getRoot().getWidgetById(_id_or_widget);
		}
		if (_id_or_widget instanceof et2_button)
		{
			this.toolbar_cancel = _id_or_widget;
			this.toolbar_cancel.onclick = jQuery.proxy(function(){
				this._enable_toolbar_edit_mode(false, false);
				this._editor.destroy();
			}, this);
		}
	}

	set_toolbar_delete(_id_or_widget : string|et2_button)
	{
		if (!this.options.editable) return;

		if (typeof _id_or_widget === 'string')
		{
			_id_or_widget = <et2_button>this.getRoot().getWidgetById(_id_or_widget);
		}
		if (_id_or_widget instanceof et2_button)
		{
			this.toolbar_delete = _id_or_widget;
			this.toolbar_delete.onclick = jQuery.proxy(function(){
				this._enable_toolbar_edit_mode(false);
				this._editor.destroy();
			}, this);
		}
	}

	set_toolbar_starttime(_id_or_widget : string|et2_number)
	{
		if (!this.options.editable) return;

		if (typeof _id_or_widget === 'string')
		{
			_id_or_widget = <et2_number>this.getRoot().getWidgetById(_id_or_widget);
		}
		if (_id_or_widget instanceof et2_number)
		{
			this.toolbar_starttime = _id_or_widget;
			this.toolbar_starttime.set_min(0);
			this.toolbar_starttime.set_max(this.videobar.video[0].duration);
			this.toolbar_starttime.set_value(this.videobar.video[0].currentTime);
		}
	}

	set_toolbar_duration(_id_or_widget : string|et2_number)
	{
		if (!this.options.editable) return;

		if (typeof _id_or_widget === 'string')
		{
			_id_or_widget = <et2_number>this.getRoot().getWidgetById(_id_or_widget);
		}
		if (_id_or_widget instanceof et2_number)
		{
			this.toolbar_duration = _id_or_widget;
			this.toolbar_duration.set_min(0);
			this.videobar.video[0].addEventListener("loadedmetadata", jQuery.proxy(function(){
				this.toolbar_duration.set_max(this.videobar.video[0].duration - this.toolbar_starttime.getValue());
			}, this));
			this.toolbar_duration.onchange = jQuery.proxy(function(_node, _widget){
				this.videobar.seek_video(parseInt(this.toolbar_starttime.getValue()) + parseInt(_widget.getValue()));
				this._slider_progressbar.css({width: this.videobar._vtimeToSliderPosition(parseInt(_widget.getValue()))});
			}, this);

		}
	}

	set_toolbar_add(_id_or_widget : string|et2_dropdown_button)
	{
		if (!this.options.editable)	return;

		if (typeof _id_or_widget === 'string')
		{
			_id_or_widget = <et2_dropdown_button>this.getRoot().getWidgetById(_id_or_widget);
		}
		if (_id_or_widget instanceof et2_dropdown_button)
		{
			this.toolbar_add = _id_or_widget;

			//TODO: set select options with available plugins
			this.toolbar_add.set_select_options({
				"et2_smallpart_overlay_html_editor":{label:egw.lang("html"), icon:"edit"}
			});

			this.toolbar_add.onchange = jQuery.proxy(function(_node, _widget){
				this._enable_toolbar_edit_mode(true, false);
				this.toolbar_duration.set_value(0);
				switch(_widget.getValue())
				{
					case "et2_smallpart_overlay_html_editor":
						this._editor = et2_createWidget('smallpart-overlay-html-editor', {
							width:"100%",
							height:"100%",
							class:"smallpart-overlay-element",
							mode:"simple",
							statusbar: false
						}, this._elementsContainer);
						this._editor.toolbar = "";
						this._editor.doLoadingFinished();
				}
			}, this);
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
		if (!_start)
		{
			this.elements = [];
			this.total = 0;
		}
		if (!this.get_elements_callback) return;
		// fetch first chunk of overlay elements
		return this.egw().json(this.get_elements_callback, [{
			video_id: this.video_id,
			course_id: this.course_id,
		}, _start], function(_data)
		{
			if (typeof _data === 'object' && Array.isArray(_data.elements))
			{
				this.elements.concat(..._data.elements);
				this.total = _data.total;
			}
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
		// check if we seeking behind the last loaded element and there are more to fetch
		if (this.total > this.elements.length &&
			_time > this.elements[this.elements.length-1].overlay_start)
		{
			this.fetchElements(this.elements.length).then(() => this.onTimeUpdate(_time));
			return;
		}

		let running = [];
		this.iterateOver(function(_widget : et2_IOverlayElement)
		{
			running.push(_widget.options.overlay_id);
		}.bind(this), this, et2_IOverlayElement);

		this.elements.forEach(function(_element, _idx)
		{
			if (running.indexOf(_element.overlay_id) !== -1 &&
				_element.overlay_start == Math.floor(_time))
			{
				this.createElement(_element);

				// fetch more elements, if we are reaching the end of the loaded ones
				if (this.total > this.elements.length && _idx > this.elements.length-10)
				{
					this.fetchElements(this.elements.length);
				}
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
 * Interface for an overlay elements managed by et2_widget_videooverlay
 */
export interface et2_IOverlayElementEditor extends et2_baseWidget
{
	onSaveCallback(_video_id, _starttime, _duration);
}

var et2_IOverLayElementEditor = "et2_IOverLayElementEditor";
function implements_et2_IOverLayElementEditor(obj : et2_widget)
{
	return implements_methods(obj, ["onSaveCallback"]);
}

export class et2_smallpart_overlay_html_editor extends et2_htmlarea implements et2_IOverlayElementEditor
{
	static readonly _attributes : any = {

	};

	/**
	 * Constructor
	 */
	constructor(_parent, _attrs? : WidgetConfig, _child? : object)
	{
		// Call the inherited constructor
		super(_parent, _attrs, ClassWithAttributes.extendAttributes(et2_smallpart_overlay_html._attributes, _child || {}));

	}

	onSaveCallback()
	{
		let data = this.getValue();
	}

}
et2_register_widget(et2_smallpart_overlay_html_editor, ["smallpart-overlay-html-editor"]);

/**
 * Overlay element to show some html
 */
export class et2_smallpart_overlay_html extends et2_description implements et2_IOverlayElement
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
		super(_parent, _attrs, ClassWithAttributes.extendAttributes(et2_smallpart_overlay_html._attributes, _child || {}));

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
et2_register_widget(et2_smallpart_overlay_html, ["smallpart-overlay-html"]);