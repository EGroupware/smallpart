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
	/smallpart/js/et2_videooverlay_interface.js;
	/smallpart/js/overlay_plugins/et2_smallpart_overlay_html.js;
*/

import { et2_baseWidget } from "../../api/js/etemplate/et2_core_baseWidget";
import {et2_createWidget, et2_register_widget, et2_widget, WidgetConfig} from "../../api/js/etemplate/et2_core_widget";
import {ClassWithAttributes} from "../../api/js/etemplate/et2_core_inheritance";
import {et2_smallpart_videobar} from "./et2_widget_videobar";
import {et2_button} from "../../api/js/etemplate/et2_widget_button";
import {et2_dropdown_button} from "../../api/js/etemplate/et2_widget_dropdown_button";
import {et2_number} from "../../api/js/etemplate/et2_widget_number";
import {et2_IOverlayElement, OverlayElement, PlayerMode} from "./et2_videooverlay_interface";

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
