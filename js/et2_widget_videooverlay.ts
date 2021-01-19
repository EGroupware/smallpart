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
	/smallpart/js/overlay_plugins/et2_smallpart_*.js;
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
import {et2_dialog} from "../../api/js/etemplate/et2_widget_dialog";
import {etemplate2} from "../../api/js/etemplate/etemplate2";

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
		toolbar_add_question : {
			name: 'toolbar add question',
			type: 'string',
			description: 'Add question button in top bar controller',
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
		toolbar_offset : {
			name: 'toolbar offset',
			type: 'string',
			description: 'offset margin',
			default: 16
		},
		editable: {
			name: 'Editable',
			type: 'boolean',
			description: 'Make overlay editable',
		},
		stop_contextmenu: {
			name: "stop contextmenu",
			type: "boolean",
			description: "This would prevent the browser native contextmenu on video tag",
			default: true
		},
	};

	/**
	 * Loaded overlay elements
	 */
	protected elements : Array<OverlayElement>;

	/**
	 * Keeps current rendered question dialog
	 * @protected
	 */
	protected questionDialog: et2_widget;

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
	protected toolbar_offset: et2_number;
	protected toolbar_add_question: et2_button;

	private _elementsContainer : et2_widget = null;
	private _slider_progressbar : JQuery = null;
	private _elementSlider: et2_smallpart_videooverlay_slider_controller = null;

	private static overlay_question_mode_skipable : number = 0;
	private static overlay_question_mode_reqires : number = 1;
	private static overlay_question_mode_required_limitted_time : number = 2;


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
		this._elementsContainer = et2_createWidget('hbox', {width:"100%", height:"100%", class:"elementsContainer", id:"elementsContainer"}, this);

		if (this.options.stop_contextmenu) this.div.on('contextmenu', function(){return false;});

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

		for(let i=this._elementsContainer.getChildren().length - 1; i >= 0; i--)
		{
			this._elementsContainer.getChildren()[i].destroy();
		}

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
			this.videobar.onresize_callback = jQuery.proxy(this._onresize_videobar, this);
			this.videobar.video[0].addEventListener("loadedmetadata", jQuery.proxy(function(){
				this._videoIsLoaded();
			}, this));
		}
	}

	doLoadingFinished(): boolean | JQueryPromise<unknown> {
		let ret = super.doLoadingFinished();
		let self = this;
		this.set_disabled(!this.video_id);
		this.videobar.ontimeupdate_callback = function(_time){
			self.onTimeUpdate(_time);
		};
		this._elementSlider = <et2_smallpart_videooverlay_slider_controller> et2_createWidget('smallpart-videooverlay-slider-controller', {
			width:"100%",
			videobar: 'video',
			onclick_callback: jQuery.proxy(this._elementSlider_callback, this),
			onclick_slider_callback: jQuery.proxy(function(e){this.onSeek(this.videobar.video[0].currentTime)}, this)
		}, this);

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
			this.onSeek(data[0].overlay_start);
			this.renderElements(data[0].overlay_id);
			this.toolbar_edit?.set_disabled(false);
			this.toolbar_delete?.set_disabled(false);
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
					'overlay_start': parseInt(this.toolbar_starttime.getValue()),
					'offset': parseInt(this.toolbar_offset.getValue()),
					'width': this.videobar.video.width()
				};
				let self = this;
				this._editor.onSaveCallback(data, function(_data){
					let exist = false;
					self.elements.forEach(function(_e, _index){
						if (_e.overlay_id == _data[0].overlay_id)
						{
							exist = true;
							self.elements[_index] = _data[0];
						}
					});
					if (!exist) self.elements = self.elements.concat(..._data);
					self.renderElements();
					self.renderElements(_data[0].overlay_id);
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
				let overlay_id = parseInt(this._elementSlider?.get_selected().overlay_id);
				let data = this.elements.filter(function(e){if (e.overlay_id == overlay_id) return e;});
				switch(data[0].overlay_type)
				{
					case "smallpart-overlay-html":
						this._editor = et2_createWidget('smallpart-overlay-html-editor', {
							width:"100%",
							height:"100%",
							class:"smallpart-overlay-element",
							mode:"simple",
							offset: data[0].offset,
							statusbar: false,
							overlay_id: data[0].overlay_id
						}, this._elementsContainer);
						this._editor.toolbar = "";
						this._editor.set_value(data[0].data);
						this._editor.doLoadingFinished();
						break;
					case "smallpart-question-text":
					case "smallpart-question-multiplechoice":
						this._enable_toolbar_edit_mode(false, false);
						egw.open_link(egw.link('/index.php', {
							menuaction: 'smallpart.EGroupware\\SmallParT\\Questions.edit',
							overlay_id: data[0].overlay_id,
							video_id: this.video_id
						}), '_blank', '800x600', 'smallpart');
						return;
				}
				this.toolbar_offset.set_value(data[0].offset);
				this.toolbar_duration.set_value(data[0].overlay_duration);
				this.toolbar_starttime.set_value(data[0].overlay_start);
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
			this.videobar.pause_video();
			// slider progressbar span
			this._slider_progressbar = jQuery(document.createElement('span'))
				.addClass('overlay_slider_progressbar')
				.css({
					left:this.videobar._vtimeToSliderPosition(parseInt(this.toolbar_starttime.getValue())),
					width:this.videobar._vtimeToSliderPosition(parseInt(this.toolbar_duration.getValue()))
				})
				.appendTo(this.videobar.getSliderDOMNode());
			jQuery(this.getDOMNode()).addClass('editmode');
			this._elementSlider?.set_disabled(true);
			this._elementsContainer.getChildren().forEach(_widget =>{if (_widget.set_disabled) _widget.set_disabled(true);});
		}
		else
		{
			this._elementSlider?.set_disabled(false);
			jQuery(this.getDOMNode()).removeClass('editmode');
			if (this.toolbar_duration) this.toolbar_duration.set_value(1);
			if (this._slider_progressbar) this._slider_progressbar.remove();
			this._elementsContainer.getChildren().forEach(_widget =>{if (_widget.set_disabled) _widget.set_disabled(false);});
		}
		this.toolbar_save.set_disabled(!_state);
		this.toolbar_delete.set_disabled(!(_state && _deleteEnabled));
		this.toolbar_add.set_disabled(_state);
		this.toolbar_add_question.set_disabled(_state);
		this.toolbar_duration.set_disabled(!_state);
		this.toolbar_offset.set_disabled(!_state);
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
				let self = this;
				et2_dialog.show_dialog(function(_btn){
					if (_btn == et2_dialog.YES_BUTTON) {
						self._enable_toolbar_edit_mode(false);
						let overlay_id = parseInt(self._elementSlider?.get_selected().overlay_id);
						let element = self._get_element(overlay_id);
						egw.json('smallpart.\\EGroupware\\SmallParT\\Overlay.ajax_delete', [{
							course_id: self.options.course_id,
							video_id: self.options.video_id,
							overlay_id: overlay_id
						}], function (_overlay_response) {
							if (element) self.deleteElement(element);
							self._delete_element(overlay_id);
							self.renderElements();
						}).sendRequest();
						if (self._is_in_editmode()) self._editor.destroy();
					}
				}, "Are you sure you want to delete this element?", "Delete overlay", null, et2_dialog.BUTTONS_YES_NO, egw);

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

			this.toolbar_duration.onchange = jQuery.proxy(function(_node, _widget){
				if (this._slider_progressbar) this._slider_progressbar.css({width: this.videobar._vtimeToSliderPosition(parseInt(_widget.getValue()))});
			}, this);

		}
	}

	set_toolbar_offset(_id_or_widget : string|et2_number) {
		if (!this.options.editable) return;

		if (typeof _id_or_widget === 'string') {
			_id_or_widget = <et2_number>this.getRoot().getWidgetById(_id_or_widget);
		}
		if (_id_or_widget instanceof et2_number) {
			this.toolbar_offset = _id_or_widget;
			this.toolbar_offset.onchange = jQuery.proxy(function(_node, _widget){
				if (this._editor && this._editor.set_offset)
				{
					this._editor.set_offset(_widget.getValue());
				}
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
			this.toolbar_add.onclick = jQuery.proxy(function(_node, _widget){
				if (_widget.getValue())
				{
					_widget.onchange(_node, _widget);
				}
				else
				{
					_widget.arrow.click();
				}
				}, this);
			this.toolbar_add.onchange = jQuery.proxy(function(_node, _widget){
				if (!_widget.getValue()) return;
				this._enable_toolbar_edit_mode(true, false);
				this.toolbar_duration.set_value(1);
				this.toolbar_offset.set_value(16);
				switch(_widget.getValue())
				{
					case "et2_smallpart_overlay_html_editor":
						this._editor = et2_createWidget('smallpart-overlay-html-editor', {
							width:"100%",
							height:"100%",
							class:"smallpart-overlay-element",
							mode:"simple",
							offset: this.toolbar_offset.getValue(),
							statusbar: false
						}, this._elementsContainer);
						this._editor.toolbar = "";
						this._editor.doLoadingFinished();
				}
			}, this);
		}
	}

	set_toolbar_add_question(_id_or_widget : string|et2_button)
	{
		if (!this.options.editable) return;

		if (typeof _id_or_widget === 'string')
		{
			_id_or_widget = <et2_button>this.getRoot().getWidgetById(_id_or_widget);
		}
		if (_id_or_widget instanceof et2_button)
		{
			this.toolbar_add_question = _id_or_widget;
			this.toolbar_add_question.onclick = jQuery.proxy(function(){
				egw.open_link(egw.link('/index.php', {
					menuaction: 'smallpart.EGroupware\\SmallParT\\Questions.edit',
					overlay_start:Math.floor(this.videobar.video[0].currentTime),
					overlay_duration: 1,
					overlay_type: "smallpart-question-text",
					video_id: this.video_id
				}), '_blank', '800x600', 'smallpart');
			}, this);
		}
	}

	/**
	 * After video is fully loaded
	 * @private
	 */
	private _videoIsLoaded()
	{
		this.toolbar_duration?.set_max(this.videobar.video[0].duration - this.toolbar_starttime.getValue());
		if (this._elementSlider) jQuery(this._elementSlider.getDOMNode()).css({width:this.videobar.video.width()});
		this.fetchElements(0).then(() => {
			this.renderElements();
			this.onSeek(0);

			if (!this.options.editable && !this.elements.length)
			{
				this._elementSlider?.set_disabled(true);
			}
			else
			{
				this._elementSlider?.set_disabled(false);
				this.div?.css({'margin-bottom':'40px'});
			}

		});
	}

	/**
	 * Renders all elements
	 * @protected
	 */
	protected renderElements(_overlay_id?: number)
	{
		let self = this;
		if (this._elementsContainer.getChildren().length > 0)
		{
			this._elementsContainer.getChildren().forEach(function(_widget){
				if (_overlay_id && _overlay_id == _widget.options.overlay_id)
				{
					_widget.destroy();
					self.fetchElement(_overlay_id).then(function(_attrs){
						self.createElement(_attrs);
					});
				}
				else
				{
					_widget.destroy();
				}
			});
		}
		if(this._elementsContainer.getChildren().length == 0 && _overlay_id)
		{
			this.fetchElement(_overlay_id).then(function(_attrs){
				self.createElement(_attrs);
			});
		}

		if (typeof _overlay_id == 'undefined') this._elementSlider?.set_value(this.elements);
	}



	/**
	 * Load overlay elements from server
	 *
	 * @param _start
	 * @return Promise<Array<OverlayElement>>
	 */
	protected fetchElements(_start : number) : Promise<Array<OverlayElement>>
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
				if (this.elements.length === 0)
				{
					this.elements = jQuery.extend(true, [], _data.elements);
				}
				else
				{
					_data.elements.forEach(function(element)
					{
						for(let i in this.elements)
						{
							if (this.elements[i].overlay_id === element.overlay_id)
							{
								this.elements[i] = jQuery.extend(true, {}, element);
								return;
							}
						}
						this.elements.concat(jQuery.extend(true, {}, element));
					}.bind(this));
				}
				this.total = _data.total;
				return Promise.resolve(this.elements);
			}
		}.bind(this)).sendRequest();
	}

	/**
	 * Return given overlay element, load it if neccessary from server
	 *
	 * @param _overlay_id
	 * @return Promise<OverlayElement>
	 */
	protected fetchElement(_overlay_id : number) : Promise<OverlayElement>
	{
		let element = this.elements.filter((_element) => _element.overlay_id === _overlay_id)[0];

		if (typeof element !== "undefined" && element.data !== false)
		{
			return Promise.resolve(jQuery.extend(true, {},element));
		}
		if (this.elements.length === this.total)
		{
			return Promise.reject("No overlay_id {_overlay_id}!");
		}
		this.fetchElements(this.elements.length).then(function()
		{
			return this.fetchElement(_overlay_id);
		}.bind(this));
	}

	/**
	 * check if the editor is active
	 * @private
	 */
	private _is_in_editmode()
	{
		return this._editor && this._editor.getDOMNode();
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
		if (this._is_in_editmode()) // update startime if it's in editmode
		{
			this.toolbar_starttime.set_value(Math.floor(_time));
			this._slider_progressbar.css({
				left:this.videobar._vtimeToSliderPosition(parseInt(this.toolbar_starttime.getValue())),
				width:this.videobar._vtimeToSliderPosition(parseInt(this.toolbar_duration.getValue()))
			})
			return;
		}

		this.onTimeUpdate(_time);
	}

	/**
	 * Periodically called while video is playing to add new overlay elements
	 *
	 * @param number _time
	 */
	onTimeUpdate(_time : number)
	{
		this._elementSlider?.set_seek_position(this.videobar._vtimeToSliderPosition(_time));
		// check if we seeking behind the last loaded element and there are more to fetch
		if (this.total > this.elements.length &&
			_time > this.elements[this.elements.length-1].overlay_start)
		{
			this.fetchElements(this.elements.length).then(() => this.onTimeUpdate(_time));
			return;
		}

		let running = [];
		this._elementsContainer.iterateOver(function(_widget : et2_IOverlayElement)
		{
			if (!_widget.keepRunning(_time))
			{
				this.deleteElement(_widget);
				return;
			}
			running.push(_widget.options.overlay_id);
		}.bind(this), this, et2_IOverlayElement);

		this.elements.forEach(function(_element, _idx)
		{
			if (running.indexOf(_element.overlay_id) === -1 &&
				_element.overlay_start <= _time && _time < _element.overlay_start+(_element.overlay_duration||1))
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
	deleteElement(_widget : et2_IOverlayElement)
	{
		_widget.destroy();
		this._elementsContainer.removeChild(_widget);
	}

	/**
	 * Create / show an overlay-element and add it to children
	 *
	 * @param _attrs
	 */
	createElement(_attrs : OverlayElement) {
		let self = this;
		let isQuestionOverlay = _attrs.overlay_type.match(/-question-/);
		// prevent creating an element if already exists
		for (let _widget of this._elementsContainer.getChildren()) {
			if (_widget.options.overlay_id == _attrs.overlay_id) {
				return;
			}
		}
		// let other overlays being created as normal
		if (isQuestionOverlay)
		{
			if (this.questionDialog && this.questionDialog.options.value.content.overlay_id != _attrs.overlay_id) {
				this.questionDialog.destroy();
			}
			if (this.questionDialog?.div) {
				return;
			}
		}

		let widget = <et2_IOverlayElement> et2_createWidget(_attrs.overlay_type, jQuery.extend(true, {} ,_attrs), this._elementsContainer);
		this._elementsContainer.addChild(widget);
		this._elementsContainer.getChildren().forEach(_w=>{
			let zoom = this.videobar.video.width()/_attrs.width;
			jQuery(_w.getDOMNode()).children().css({
				'zoom': zoom
			});
		});
		if (_attrs.overlay_player_mode & PlayerMode.Pause)
		{
			this.videobar?.pause_video();
		}
		if (_attrs.overlay_player_mode & PlayerMode.Disable)
		{
			// ToDo: this.videobar?.
		}
		if (isQuestionOverlay)
		{
			this.questionDialog = this._createQuestionElement(<OverlayElement>_attrs, widget);
		}
	}

	/**
	 *
	 * @param _attrs
	 * @param _widget
	 * @private
	 */
	private _createQuestionElement(_attrs : OverlayElement, _widget: et2_IOverlayElement)
	{
		let video = this.getArrayMgr('content').getEntry('video');
		_attrs.account_id = egw.user('account_id');
		let pause_timeout = null;
		let is_readonly = video.video_published != et2_smallpart_videobar.video_test_published_readonly;
		let modal = false;
		let self = this;
		let buttons = [
			{"button_id": 1, "text": 'submit', id: 'submit', image: 'check', "default": true},
			{"button_id": 2, "text": 'skip', id: 'skip', image: 'cancel'}
		].filter(b=>{
			if (is_readonly)
			{
				return  b.id == "skip";
			}
			switch (parseInt(_attrs.overlay_question_mode))
			{
				case et2_smallpart_videooverlay.overlay_question_mode_skipable:
					return true;
				case et2_smallpart_videooverlay.overlay_question_mode_reqires:
				case et2_smallpart_videooverlay.overlay_question_mode_required_limitted_time:
					modal = true;
					return b.id != "skip";
			}
		});

		switch (parseInt(_attrs.overlay_question_mode))
		{
			case et2_smallpart_videooverlay.overlay_question_mode_skipable:
			case et2_smallpart_videooverlay.overlay_question_mode_reqires:
				if (!is_readonly)
				{
					// pasue the video at the end of the question
					pause_timeout = window.setTimeout(function (){self.videobar.pause_video();},_attrs.overlay_duration * 1000);
				}
				break;
			case et2_smallpart_videooverlay.overlay_question_mode_required_limitted_time:
				break;
		}

		let dialog = et2_createWidget("dialog", {
			callback: function (_btn, _value) {
				if (video.video_test_options == et2_smallpart_videobar.video_test_option_pauseable
					&& (_btn == 'skip' || _btn == 'submit') && self.videobar.video[0].paused)
				{
					self.videobar.video[0].play();
				}
				if (_btn == 'submit' && _value && !is_readonly)
				{
					let data = _widget.submit(_value, _attrs);
					self._update_element(_attrs.overlay_id, data);
				}
				clearTimeout(pause_timeout);
			},
			title: egw.lang('Question number %1', _attrs.overlay_id),
			buttons: buttons,
			value: {
				content:_attrs
			},
			modal:modal,
			width: 500,
			appendTo: video.video_test_display != et2_smallpart_videobar.video_test_display_dialog ? ".rightBoxArea": '',
			draggable: video.video_test_display != et2_smallpart_videobar.video_test_display_dialog ? false : true,
			resizable: false,
			closeOnEscape: false,
			dialogClass: 'questionDisplayBox',
			template: _attrs.template_url || egw.webserverUrl + '/smallpart/templates/default/question.'+_attrs.overlay_type.replace('smallpart-question-','')+'.xet'
		}, et2_dialog._create_parent('smallpart'));

		return dialog;
	}
	_onresize_videobar(_width: number, _height: number, _position: number) {
		if (this._elementSlider) jQuery(this._elementSlider.getDOMNode()).css({width:_width});
		this._elementSlider?.set_seek_position(_position);
		this.renderElements();
		this.onSeek(this.videobar.video[0].currentTime);
	}

	/**
	 * get element widget from elements container
	 * @param _overlay_id
	 *
	 * @return et2_IOverlayElement
	 */
	_get_element(_overlay_id: number) : et2_IOverlayElement
	{
		let element = null;
		this._elementsContainer.iterateOver(function(_widget : et2_IOverlayElement)
		{
			if (_widget.options.overlay_id == _overlay_id)
			{
				element = _widget;
			}
		}.bind(this), this, et2_IOverlayElement);
		return element;
	}

	/**
	 * delete given overlay id from fetched elements object
	 * @param _overlay_id
	 */
	_delete_element(_overlay_id: number)
	{
		for(let i =0; i < this.elements.length; i++)
		{
			if (this.elements[i]['overlay_id'] == _overlay_id)
			{
				this.elements.splice(i, 1);
			}
		}
	}

	/**
	 * client-side update update element data
	 * @param _overlay_id
	 * @param _data
	 */
	_update_element(_overlay_id: number, _data: OverlayElement)
	{
		for(let i =0; i < this.elements.length; i++)
		{
			if (this.elements[i]['overlay_id'] == _overlay_id)
			{
				this.elements[i] = _data;
			}
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
}

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
		},
		onclick_slider_callback: {
			name: 'on slider click callback',
			type: 'js',
			description: 'callback function on slider bar',
		}
	}

	protected marks_positions : [OverlaySliderControllerMarkPositionType] | [] = [];
	protected videobar: et2_smallpart_videobar;
	protected elements:  Array<OverlayElement>;
	protected marks: any = [];
	private _selected: et2_description;
	private static mark_id_prefix: string = "slider-tag-";
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
			let self = this;
			this.div.on('click', function(e){
				self.videobar._slider_onclick.call(self.videobar ,e);
				if (typeof self.onclick_slider_callback == 'function') self.onclick_slider_callback.call(self, e);
			});
		}
	}

	/**
	 * set given elements as actual marks on sliderbar
	 * @param _elements
	 */
	set_value(_elements:  Array<OverlayElement>)
	{
		this.marks_positions = [];
		this.marks = [];
		this.elements = _elements;

		for(let i=this.getChildren().length - 1; i >= 0; i--)
		{
			this.getChildren()[i].destroy();
		}
		let self = this;

		this.elements.forEach(function(_element, _idx){
			self.marks[_element.overlay_id] = et2_createWidget('description', {
				id:et2_smallpart_videooverlay_slider_controller.mark_id_prefix+_element.overlay_id,
			}, self);
			self.marks[_element.overlay_id].onclick=function(_event, _widget){
				_event.stopImmediatePropagation()
				if (typeof self.options.onclick_callback == 'function')
				{
					let markWidget = _widget;
					self.onclick_callback(_event, _widget);
					self._set_selected(_widget);
				}
			};
			self.marks[_element.overlay_id].doLoadingFinished();
			let pos : OverlaySliderControllerMarkPositionType = self._find_position(self.marks_positions, {
				left:self.videobar._vtimeToSliderPosition(_element.overlay_start),
				width:self.videobar._vtimeToSliderPosition(_element.overlay_duration), row: 0
			}, 0);
			self.marks_positions.push(<never>pos);

			// set its actuall position in DOM
			jQuery(self.marks[_element.overlay_id].getDOMNode())
				.css({left:pos.left+'px', width:pos.width+'px', top:pos.row != 0 ? pos.row*(5+2) : pos.row+'px'})
				.addClass(_element.overlay_type.match(/-question-/)?'overlay-question':'');
		});
	}

	/**
	 * set currently selected mark
	 * @param _widget
	 */
	_set_selected(_widget)
	{
		this._selected = _widget;
		_widget.set_class('selected');
		this.marks.forEach(function(_mark){if (_mark.id != _widget.id){
			jQuery(_mark.getDOMNode()).removeClass('selected');
		}});
	}

	/**
	 * get current selected mark
	 */
	get_selected()
	{
		return {
			widget:this._selected,
			overlay_id: this._selected.id.split(et2_smallpart_videooverlay_slider_controller.mark_id_prefix)[1]
		};
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

	set_seek_position(_value)
	{
		let value = Math.floor(_value);
		this.div.css({
			background:'linear-gradient(90deg, rgb(174 173 173) '+ value + 'px, rgb(206 206 206) '+ value + 'px, rgb(206 206 206) 100%)'
		});
	}
}
et2_register_widget(et2_smallpart_videooverlay_slider_controller, ["smallpart-videooverlay-slider-controller"]);
