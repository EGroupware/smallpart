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
import {et2_inputWidget} from "../../api/js/etemplate/et2_core_inputWidget";
import {et2_valueWidget} from "../../api/js/etemplate/et2_core_valueWidget";
import {et2_description} from "../../api/js/etemplate/et2_widget_description";

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
	private _elementSlider: et2_smallpart_videooverlay_slider_controller = null;
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
		if (this.options.editable)
		{
			this.div.addClass('editable');
		}
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
			let self = this;
			this.videobar.slider.on('click', function(e){
				self.onSeek(self.videobar.video[0].currentTime);
			});

		}
	}

	doLoadingFinished(): boolean | JQueryPromise<unknown> {
		let ret = super.doLoadingFinished();
		if (this.options.editable)
		{
			this._elementSlider = <et2_smallpart_videooverlay_slider_controller> et2_createWidget('smallpart-videooverlay-slider-controller', {
				width:"100%",
				videobar: 'video',
				onclick_callback: this._elementSlider_callback
				}, this);
		}
		return ret;
	}

	/**
	 * Click callback called on elements slidebar
	 * @param _node
	 * @param _widget
	 * @private
	 */
	private _elementSlider_callback(_node, _widget)
	{
		let overlay_id = _widget.id.split('slider-tag-')[1];
		let data = this.elements.filter(function(e){if (e.overlay_id == overlay_id) return e;})
		if (data[0] && data[0].overlay_id)
		{
			this.videobar.seek_video(data[0].overlay_start);
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
				let data = {
					'course_id': this.course_id,
					'video_id': this.video_id,
					'overlay_duration': parseInt(this.toolbar_duration.getValue()),
					'overlay_starttime': parseInt(this.toolbar_starttime.getValue()),
				};
				let self = this;
				this._editor.onSaveCallback(data, function(_data){
					self.elements = self.elements.concat(..._data);

				});
				this._enable_toolbar_edit_mode(false, false);
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
			this._elementSlider.set_disabled(true);
		}
		else
		{
			this._elementSlider.set_disabled(false);
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
				this._videoIsLoaded();

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
	 * After video is fully loaded
	 * @private
	 */
	private _videoIsLoaded()
	{
		this.toolbar_duration.set_max(this.videobar.video[0].duration - this.toolbar_starttime.getValue());
		jQuery(this._elementSlider.getDOMNode()).css({width:this.videobar.video.width()});
		this.fetchElements(0);
	}

	/**
	 * Renders all elements
	 * @protected
	 */
	protected renderElements()
	{
		let self = this;
		this._elementsContainer.getChildren().forEach(function(_widget){
			_widget.destroy();
		});
		this._elementSlider.set_value(this.elements);
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
		if (!this.options.get_elements_callback) return;
		// fetch first chunk of overlay elements
		return this.egw().json(this.options.get_elements_callback, [{
			video_id: this.video_id,
			course_id: this.course_id,
		}, _start], function(_data)
		{
			if (typeof _data === 'object' && Array.isArray(_data.elements))
			{
				this.elements = this.elements.concat(..._data.elements);
				this.total = _data.total;
				this.renderElements();
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
 * type of position used in sliderbar controller
 */
export interface OverlaySliderControllerMarkPositionType {
	left:number;
	width:number;
	row:number
};

/**
 * slider-controller creates a sliderbar for demonstrating all elements, consists of marking system
 * and selection.
 */
class et2_smallpart_videooverlay_slider_controller extends et2_baseWidget {
	static readonly _attributes: any = {
		onclick_callback: {
			name: 'click callback',
			type: 'js',
			description: 'callback function on elements',
		},
		videobar : {
			name: 'videobar',
			type: 'string',
			description: 'videobar this overlay is for',
		}
	}

	protected marks_positions : [OverlaySliderControllerMarkPositionType] | [] = [];
	protected videobar: et2_smallpart_videobar;
	protected elements:[];
	div:JQuery = null;
	/**
	 * Constructor
	 */
	constructor(_parent, _attrs? : WidgetConfig, _child? : object)
	{
		// Call the inherited constructor
		super(_parent, _attrs, ClassWithAttributes.extendAttributes(et2_smallpart_videooverlay_slider_controller._attributes, _child || {}));
		this.div = jQuery(document.createElement("div"))
			.addClass("et2_" + super.getType());

		super.setDOMNode(this.div[0]);
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
	 * set given elements as actual marks on sliderbar
	 * @param _elements
	 */
	set_value(_elements)
	{
		this.marks_positions = [];
		this.elements = _elements;
		this.getChildren().forEach(function(_widget){
			_widget.destroy();
		});
		let self = this;
		this.elements.forEach(function(_element, _idx){
			let mark = et2_createWidget('description', {
				id:"slider-tag-"+_element.overlay_id,
			}, self);
			mark.onclick=function(_node, _widget){
				if (typeof self.options.onclick_callback == 'function')
				{
					self.onclick_callback(_node, _widget);
				}
			};
			mark.doLoadingFinished();
			let pos : OverlaySliderControllerMarkPositionType = self._find_position(self.marks_positions, {
				left:self.videobar._vtimeToSliderPosition(_element.overlay_start),
				width:self.videobar._vtimeToSliderPosition(_element.overlay_duration), row: 0
			}, 0);
			self.marks_positions.push(<never>pos);

			// set its actuall position in DOM
			jQuery(mark.getDOMNode()).css({left:pos.left+'px', width:pos.width+'px', top:pos.row != 0 ? pos.row*(5+2) : pos.row+'px'});
		});
	}

	/**
	 * find a free spot on sliderbar for given mark's position
	 * @param _marks_postions all current occupide positions
	 * @param _pos mark position
	 * @param _row initial row to start with
	 *
	 * @return OverlaySliderControllerMarkPositionType
	 * @private
	 */
	private _find_position(_marks_postions : [OverlaySliderControllerMarkPositionType] | [], _pos: OverlaySliderControllerMarkPositionType, _row: number) : OverlaySliderControllerMarkPositionType
	{
		if (_marks_postions.length == 0) return {left:_pos.left, width:_pos.width, row: _row};
		let conflict = false;
		for (let i of _marks_postions)
		{
			if (i.row == _row)
			{
				if ((_pos.left > i.left+i.width) || (_pos.left+_pos.width < i.left))
				{
					conflict = false;
				}
				else
				{
					conflict = true;
					break;
				}
			}
		}
		if (!conflict) return {left:_pos.left, width:_pos.width, row: _row};
		return this._find_position(_marks_postions, _pos, _row+1)
	}
}
et2_register_widget(et2_smallpart_videooverlay_slider_controller, ["smallpart-videooverlay-slider-controller"]);
