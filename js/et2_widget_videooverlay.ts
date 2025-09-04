/**
 * EGroupware SmallPART - Videooverlay
 *
 * @license http://opensource.org/licenses/gpl-license.php GPL - GNU General Public License
 * @package smallpart
 * @subpackage ui
 * @link https://www.egroupware.org
 * @author Ralf Becker <rb@egroupware.org>
 */

import {et2_baseWidget} from "../../api/js/etemplate/et2_core_baseWidget";
import {et2_createWidget, et2_register_widget, et2_widget, WidgetConfig} from "../../api/js/etemplate/et2_core_widget";
import {ClassWithAttributes} from "../../api/js/etemplate/et2_core_inheritance";
import {et2_smallpart_videobar} from "./et2_widget_videobar";
import {et2_button} from "../../api/js/etemplate/et2_widget_button";
import {et2_number} from "../../api/js/etemplate/et2_widget_number";
import {et2_IOverlayElement, OverlayElement, PlayerMode} from "./et2_videooverlay_interface";
import {et2_dialog} from "../../api/js/etemplate/et2_widget_dialog";
import {et2_checkbox} from "../../api/js/etemplate/et2_widget_checkbox";
import {et2_DOMWidget} from "../../api/js/etemplate/et2_core_DOMWidget";
import "./et2_widget_videooverlay_slider_controller";
import {et2_hbox} from "../../api/js/etemplate/et2_widget_hbox";
import {egw} from "../../api/js/jsapi/egw_global";
import "./overlay_plugins/et2_smallpart_overlay_html";
import "./overlay_plugins/et2_smallpart_question_multiplechoice";
import "./overlay_plugins/et2_smallpart_question_singlechoice";
import "./overlay_plugins/et2_smallpart_question_rating";
import "./overlay_plugins/et2_smallpart_question_favorite";
import "./overlay_plugins/et2_smallpart_question_markchoice";
import "./overlay_plugins/et2_smallpart_question_millout";
import "./overlay_plugins/et2_smallpart_question_text";
import {et2_smallpart_videooverlay_slider_controller} from "./et2_widget_videooverlay_slider_controller";
import {et2_smallpart_overlay_html_editor} from "./overlay_plugins/et2_smallpart_overlay_html";
import {Et2Button} from "../../api/js/etemplate/Et2Button/Et2Button";
import {Et2Dialog} from "../../api/js/etemplate/Et2Dialog/Et2Dialog";

/**
 * Videooverlay shows time-synchronous to the video various overlay-elements
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
export class et2_smallpart_videooverlay extends et2_baseWidget
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
		toolbar_play : {
			name: 'toolbar play',
			type: 'string',
			description: 'play button',
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
		test_display: {
			name: "test display",
			type: "integer",
			description: "0: instead comments, 1: dialog, 2: overlay, 3: list (switches controller off!)",
			default: 0
		},
	};

	/**
	 * Loaded overlay elements
	 */
	protected elements : Array<OverlayElement>;

	/**
	 * Keeps current rendered question dialogs
	 * @protected
	 */
	protected questionDialogs: [{id: number, dialog: et2_widget, question_n: number}?] = [];

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
	protected toolbar_save: et2_button|Et2Button;
	protected toolbar_delete: et2_button|Et2Button;
	protected toolbar_edit: et2_button|Et2Button;
	protected toolbar_cancel: et2_button|Et2Button;
	protected toolbar_add: et2_button|Et2Button;
	protected toolbar_starttime: et2_number;
	protected toolbar_duration: et2_number;
	protected toolbar_offset: et2_number;
	protected toolbar_add_question: et2_button|Et2Button;
	protected toolbar_play: et2_button|Et2Button;

	private _elementsContainer : et2_hbox = null;
	private _slider_progressbar : JQuery = null;
	private _elementSlider: et2_smallpart_videooverlay_slider_controller = null;

	public static overlay_question_mode_skipable : number = 0;
	public static overlay_question_mode_reqires : number = 1;
	public static overlay_question_mode_required_limitted_time : number = 2;


	div: JQuery;

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
		this._elementsContainer = <et2_hbox>et2_createWidget('hbox', {width:"100%", height:"100%", class:"elementsContainer", id:"elementsContainer"}, this);

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
			this.videobar = <et2_smallpart_videobar> _id_or_widget;
			let self = this;
			let content : any = this.videobar.getArrayMgr('content').data;
			let seekable = (!!content.is_staff || content.video && !(content.video.video_test_options & et2_smallpart_videobar.video_test_option_not_seekable));
			this.videobar.set_seekable(seekable);

			// allow user to close "more videos" box from youtube iframe
			if (this.videobar.options.src_type.match('youtube'))
			{
				jQuery(this.videobar.getDOMNode()).on('mouseleave', function() {
					jQuery(self._elementsContainer.getDOMNode()).removeClass('shiftUp');
				})
				jQuery(this._elementsContainer.getDOMNode())
					.on('mouseenter', function(){
						jQuery(this).addClass('shiftUp');
					})
					.on('mouseleave', function(e){
						if (e?.toElement?.localName != "iframe") jQuery(this).removeClass('shiftUp');
					});
			}

			if (seekable)
			{
				this.videobar.getSliderDOMNode().on('click', function(){
					self.onSeek(self.videobar.currentTime());
				});
			}
			this.videobar.onresize_callback = jQuery.proxy(this._onresize_videobar, this);
			this.videobar.video[0].addEventListener("et2_video.onReady."+this.videobar.id, jQuery.proxy(function(){
				this._videoIsLoaded();
			}, this));
		}
	}

	doLoadingFinished(): boolean | JQueryPromise<unknown> {
		let ret = super.doLoadingFinished();
		let self = this;
		let content : any = this.videobar.getArrayMgr('content').data;
		this.set_disabled(!this.video_id);
		this.videobar.ontimeupdate_callback = function(_time){
			self.onTimeUpdate(_time);
		};
		this._elementSlider = <et2_smallpart_videooverlay_slider_controller> et2_createWidget('smallpart-videooverlay-slider-controller', {
			id: 'text_slider',
			class: 'bi-exclamation-square',
			width:"100%",
			videobar: 'video',
			seekable: (!!content.is_staff || content.video && !(content.video.video_test_options & et2_smallpart_videobar.video_test_option_not_seekable)),
			onclick_callback: jQuery.proxy(this._elementSlider_callback, this),
			onclick_slider_callback: jQuery.proxy(function(){this.onSeek(this.videobar.currentTime())}, this)
		}, this);

		return ret;
	}

	/**
	 * Click callback called on elements slidebar
	 * @param _node
	 * @param _widget
	 *
	 * @return boolean return false when there's an unanswered question
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
			if (this._anyUnansweredRequiredQuestions(data[0].overlay_start)) return false;
			this.renderElements(data[0].overlay_id);
			this.toolbar_edit?.set_disabled(false);
			this.toolbar_delete?.set_disabled(false);
		}
		return true;
	}

	/**
	 *
	 * @param _id_or_widget
	 */
	set_toolbar_save(_id_or_widget : string|et2_button|Et2Button)
	{
		if (!this.options.editable) return;

		if ((this.toolbar_save = this.getButton(_id_or_widget)))
		{
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

	set_toolbar_edit(_id_or_widget : string|et2_button|Et2Button)
	{
		if (!this.options.editable) return;

		if ((this.toolbar_edit = this.getButton(_id_or_widget)))
		{
			this.toolbar_edit.onclick = jQuery.proxy(function(){
				this._enable_toolbar_edit_mode(true, true);
				let overlay_id = parseInt(this._elementSlider?.get_selected().id);
				let data = this.elements.filter(function(e){if (e.overlay_id == overlay_id) return e;});
				switch(data[0].overlay_type)
				{
					case "smallpart-overlay-html":
						this._editor = <et2_smallpart_overlay_html_editor> et2_createWidget('smallpart-overlay-html-editor', {
							width:"100%",
							height:"100%",
							class:"smallpart-overlay-element",
							mode:"simple",
							offset: data[0].offset,
							statusbar: false,
							overlay_id: data[0].overlay_id,
							imageUpload: 'html_editor_upload'
						}, this._elementsContainer);
						this._editor.toolbar = "";
						this._editor.set_value(data[0].data);
						this._editor.doLoadingFinished();
						break;
					default:
					case "smallpart-question-text":
					case "smallpart-question-singlechoice":
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

	// noinspection JSUnusedLocalSymbols
	/**
	 * Set state for toolbar actions
	 * @param _state
	 * @param _deleteEnabled
	 * @private
	 */
	private _enable_toolbar_edit_mode(_state : boolean, _deleteEnabled? : boolean)
	{
		this.toolbar_edit.set_disabled(true);
		this.getDOMNode().querySelector(".overlay_toolbar").hidden = !_state;

		if (_state)
		{
			this.toolbar_starttime.set_value(Math.floor(this.videobar.currentTime()));
			this.toolbar_duration.set_max(Math.floor(this.videobar.duration() - this.toolbar_starttime.getValue()));
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
			this._elementsContainer.getChildren().forEach((_widget: et2_DOMWidget) =>{if (_widget.set_disabled) _widget.set_disabled(true);});
		}
		else
		{
			this._elementSlider?.set_disabled(false);
			jQuery(this.getDOMNode()).removeClass('editmode');
			if (this.toolbar_duration) this.toolbar_duration.set_value(1);
			if (this._slider_progressbar) this._slider_progressbar.remove();
			this._elementsContainer.getChildren().forEach((_widget: et2_DOMWidget) =>{if (_widget.set_disabled) _widget.set_disabled(false);});
		}
		this.toolbar_save.set_disabled(!_state);
		this.toolbar_delete.set_disabled(!(_state && _deleteEnabled));
		this.toolbar_duration.set_disabled(!_state);
		this.toolbar_offset.set_disabled(!_state);
		this.toolbar_starttime.set_disabled(!_state);
		this.toolbar_cancel.set_disabled(!_state);
		this.toolbar_starttime.set_readonly(true);
	}

	set_toolbar_cancel(_id_or_widget : string|et2_button|Et2Button)
	{
		if (!this.options.editable) return;

		if ((this.toolbar_cancel = this.getButton(_id_or_widget)))
		{
			this.toolbar_cancel.onclick = jQuery.proxy(function(){
				this._enable_toolbar_edit_mode(false, false);
				this._editor.destroy();
			}, this);
		}
	}

	set_toolbar_delete(_id_or_widget : string|et2_button|Et2Button)
	{
		if (!this.options.editable) return;

		if ((this.toolbar_delete = this.getButton(_id_or_widget)))
		{
			this.toolbar_delete.onclick = jQuery.proxy(function(){
				let self = this;
				let overlay_id = parseInt(this._elementSlider?.get_selected().id);
				let data = this.elements.filter(_el => {if (_el.overlay_id == overlay_id) return _el;});
				let message = data[0].overlay_type.match(/smallpart-question-/) ?
					'Delete this question incl. possible answers from students?' : 	'Are you sure you want to delete this element?';
				et2_dialog.show_dialog(function(_btn){
					if (_btn == et2_dialog.YES_BUTTON) {
						self._enable_toolbar_edit_mode(false);
						let element = self._get_element(overlay_id);
						egw.json('smallpart.\\EGroupware\\SmallParT\\Overlay.ajax_delete', [{
							course_id: self.options.course_id,
							video_id: self.options.video_id,
							overlay_id: overlay_id,
							overlay_type: data[0].overlay_type
						}], function (_overlay_response) {
							if (element) self.deleteElement(element);
							self._delete_element(overlay_id);
							self.renderElements();
						}).sendRequest();
						if (self._is_in_editmode()) self._editor.destroy();
					}
				}, message, data[0].overlay_type.match(/smallpart-question-/) ? "Delete question" : "Delete overlay", null, et2_dialog.BUTTONS_YES_NO);

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
			this.toolbar_starttime.set_max(this.videobar.duration());
			this.toolbar_starttime.set_value(this.videobar.currentTime());
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

	set_toolbar_add(_id_or_widget : string|et2_button|Et2Button)
	{
		if (!this.options.editable)	return;

		if ((this.toolbar_add = this.getButton(_id_or_widget)))
		{
			this.toolbar_add.onclick = jQuery.proxy(function(_node, _widget){
					this._enable_toolbar_edit_mode(true, false);
					this.toolbar_duration.set_value(1);
					this.toolbar_offset.set_value(16);
					this._editor = et2_createWidget('smallpart-overlay-html-editor', {
						width:"100%",
						height:"100%",
						class:"smallpart-overlay-element",
						mode:"simple",
						offset: this.toolbar_offset.getValue(),
						statusbar: false,
						imageUpload:"html_editor_upload"
					}, this._elementsContainer);
					this._editor.toolbar = "";
					this._editor.doLoadingFinished();
				}, this);
		}
	}

	/**
	 * Add text to the video
	 */
	addText()
	{
		this._enable_toolbar_edit_mode(true, false);
		this.toolbar_duration.set_value(1);
		this.toolbar_offset.set_value(16);
		this._editor = et2_createWidget('smallpart-overlay-html-editor', {
			width: "100%",
			height: "100%",
			class: "smallpart-overlay-element",
			mode: "simple",
			offset: this.toolbar_offset.getValue(),
			statusbar: false,
			imageUpload: "html_editor_upload"
		}, this._elementsContainer);
		this._editor.toolbar = "";
		this._editor.doLoadingFinished();
	}

	set_toolbar_add_question(_id_or_widget : string|et2_button|Et2Button)
	{
		if (!this.options.editable) return;

		if ((this.toolbar_add_question = this.getButton(_id_or_widget)))
		{
			this.toolbar_add_question.onclick = jQuery.proxy(function(){
				egw.open_link(egw.link('/index.php', {
					menuaction: 'smallpart.EGroupware\\SmallParT\\Questions.edit',
					overlay_start:Math.floor(this.videobar.currentTime()),
					overlay_duration: 1,
					overlay_type: "smallpart-question-text",
					video_id: this.video_id
				}), '_blank', '800x600', 'smallpart');
				if (!this.videobar.paused()) app.smallpart.et2.getDOMWidgetById('play').getDOMNode().click();
			}, this);
		}
	}

	addQuestion()
	{
		egw.open_link(egw.link('/index.php', {
			menuaction: 'smallpart.EGroupware\\SmallParT\\Questions.edit',
			overlay_start: Math.floor(this.videobar.currentTime()),
			overlay_duration: 1,
			overlay_type: "smallpart-question-text",
			video_id: this.video_id
		}), '_blank', '800x600', 'smallpart');
		if(!this.videobar.paused())
		{
			app.smallpart.et2.getDOMWidgetById('play').getDOMNode().click();
		}
	}

	set_toolbar_play(_id_or_widget : string|et2_button|Et2Button)
	{
		this.toolbar_play = this.getButton(_id_or_widget);
	}

	private getButton(_id_or_widget : string|et2_button|Et2Button)
	{
		if (typeof _id_or_widget === 'string')
		{
			_id_or_widget = <et2_button>this.getRoot().getWidgetById(_id_or_widget);
		}
		if(_id_or_widget?.tagName === 'ET2-BUTTON' || _id_or_widget?.tagName === 'ET2-BUTTON-ICON' || _id_or_widget instanceof et2_button)
		{
			return _id_or_widget;
		}
	}

	// noinspection JSUnusedLocalSymbols
	/**
	 * After video is fully loaded
	 * @private
	 */
	private _videoIsLoaded()
	{
		this.toolbar_duration?.set_max(this.videobar.duration() - this.toolbar_starttime.getValue());
		this.fetchElements(0).then(() => {
			this.renderElements();
			this.onSeek(parseFloat(this.videobar.options.starttime));

			if (!this.options.editable && !this.elements.length)
			{
				this._elementSlider?.set_disabled(true);
			}
			else
			{
				this._elementSlider?.set_disabled(false);
			}
		});

	}

	/**
	 * Renders all elements
	 *
	 * @param _overlay_id id of changed overlay element to fetch, otherwise nothing will be fetched
	 */
	public renderElements(_overlay_id?: number)
	{
		if (this.options.test_display == 3) return;	// nothing to do
		let self = this;
		if (this._elementsContainer.getChildren().length > 0)
		{
			this._elementsContainer.getChildren().forEach(function(_widget){
				if (_overlay_id && _overlay_id == _widget.options.overlay_id)
				{
					_widget.destroy();
					self.fetchElement(_overlay_id).then(function(_attrs){
						self.createElement(_attrs);
					}, function(){
						// try to fetch elements maybe added from outside
						self.fetchElements(0).then(function(){
							self.renderElements()
						});
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
			}, function(){
				// try to fetch elements maybe added from outside
				self.fetchElements(0).then(function(){
					self.renderElements()
				});
			});
		}

		if (typeof _overlay_id == 'undefined')
		{
			const sliderData = this.elements.map(_el => {
				return {
					id:_el.overlay_id,
					starttime: _el.overlay_start,
					duration: _el.overlay_duration,
					class: _el.overlay_type.match(/-question-/) ?
						(_el.overlay_question_mode != et2_smallpart_videooverlay.overlay_question_mode_skipable ?
							'overlay-question-required' : 'overlay-question'):''
				}
			});
			this._elementSlider?.set_value(sliderData);
		}
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
		if (!this.options.get_elements_callback || this.options.test_display == 3)
		{
			return Promise.resolve([]);
		}
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
	 * Return given overlay element, load it if necessary from server
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
	 * @param _time
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
	 *
	 * @param time
	 * @return OverlayElement|undefined returns overlay object
	 * @private
	 */
	private _anyUnansweredRequiredQuestions (time : number) : undefined | OverlayElement
	{
		let overlay = undefined;
		let video = this.getArrayMgr('content')?.getEntry('video');
		if (video && (video['video_published'] == et2_smallpart_videobar.video_test_published_readonly
			|| video['video_published'] == et2_smallpart_videobar.video_test_published_draft)) return overlay;
		this.elements.forEach((el)=>{
			if ( el.overlay_start + el.overlay_duration < time
				&& el.overlay_question_mode != et2_smallpart_videooverlay.overlay_question_mode_skipable
				&& !el.answer_created && el.question_n)
			{
				overlay = el;
				return;
			}
		});
		// makes sure the video stops when there's an overlay found
		if (overlay && !this.videobar.paused()) this.toolbar_play.click(null);
		return overlay;
	}

	/**
	 * Periodically called while video is playing to add new overlay elements
	 *
	 * @param _time
	 */
	onTimeUpdate(_time : number)
	{
		let ol;
		if ((ol = this._anyUnansweredRequiredQuestions(_time))) {
			// duration must be set 0.1 sec before the end time otherwise we can't create the element
			let ol_duration = ol.overlay_start+ol.overlay_duration-0.1;
			this.videobar.seek_video(ol_duration);
			this.onTimeUpdate(ol_duration);
			_time = ol_duration;
			return false;
		}
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
	 * @param _widget
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
		// do not create overlays when slider is in disabled mode (e.g. a comment being edited)
		if (this.getElementSlider().disable_callback) return;

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
			this._removeExcessiveDialogs();
			let isSorted = true;
			for (let i=0;i<this.questionDialogs.length;i++)
			{
				if (i>1 && this.questionDialogs[i].question_n < this.questionDialogs[i-1].question_n)
				{
					isSorted = false;
					break;
				}
			}

			if (!isSorted)
			{
				let sorted = [];
				Object.assign(sorted, this.questionDialogs);
				this.questionDialogs.sort((a,b)=>{return (a.question_n < b.question_n) ? -1 :1;});
				Object.assign(sorted, this.questionDialogs);

				sorted.forEach(_d=>{
					this._questionDialogs(_d.id)._remove();

				});
				sorted.forEach(_d=>{this.createElement(<OverlayElement>_d.dialog.options.value.content);});

			}
			if (this._questionDialogs(_attrs.overlay_id)._get()) {
				return;
			}
		}

		let widget = <et2_IOverlayElement> et2_createWidget(_attrs.overlay_type, jQuery.extend(true, {} ,_attrs), this._elementsContainer);
		this._elementsContainer.addChild(widget);
		this._elementsContainer.getChildren().forEach((_w:et2_DOMWidget)=>{
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
			this._questionDialogs(_attrs.overlay_id)._add(this._createQuestionElement(<OverlayElement>_attrs, widget));
		}
	}

	/**
	 * Manages question dialogs objects
	 * @param _overlay_id
	 *
	 * @return returns as object of controllers
	 *  _add : add dialog of overlay
	 *  _remove : remove the dialg of given overlay
	 *  _get : get dialog of givern overlay
	 * @private
	 */
	private _questionDialogs(_overlay_id : number)
	{
		let self = this;
		return {
			_add: function(_dialog? : et2_dialog){
				if (!self._questionDialogs(_overlay_id)._get())
				{
					self.questionDialogs.push({id:_overlay_id, dialog:_dialog, question_n: parseInt(_dialog.options.value.content.question_n), content:_dialog.options.value.content});
				}
			},
			_remove: function(){
				if (self.questionDialogs)
				{
					self.questionDialogs.forEach((o,i) =>{
						if (o.id == _overlay_id)
						{
							o.dialog.destroy();
							self.questionDialogs.splice(i, 1);
						}
					});
				}
			},
			_get: function (){
				if (self.questionDialogs?.length>0)
				{
					let res = self.questionDialogs.filter(o=>{
						return o.id == _overlay_id;
					});
					return res?.length>0 ? res : false;
				}
				else
				{
					return false;
				}
			}
		};
	}
	/**
	 *
	 * @param _attrs
	 * @param _widget
	 *
	 * @return Et2Dialog
	 * @private
	 */
	private _createQuestionElement(_attrs : OverlayElement, _widget : et2_IOverlayElement) : Et2Dialog
	{
		_widget.set_disabled(true);
		let video = this.getArrayMgr('content').getEntry('video');
		_attrs.account_id = egw.user('account_id');
		let pauseSwitch = false;
		let attrs = _attrs;
		let pause_callback = function() {
			if (pauseSwitch && self.videobar.currentTime() >= attrs.overlay_start + attrs.overlay_duration && !attrs.answer_created)
			{
				// pasue the video at the end of the question
				self.toolbar_play.click(null);
				if (parseInt(attrs.overlay_question_mode) == et2_smallpart_videooverlay.overlay_question_mode_skipable)
				{
					self.toolbar_play.getDOMNode().addEventListener('click', function ()
					{
						self._questionDialogs(attrs.overlay_id)._remove();
					}, {once:true});
				}
				self.videobar.video[0].removeEventListener('et2_video.onTimeUpdate.'+self.videobar.id, pause_callback);
			}
		};

		let is_readonly = video.video_published == et2_smallpart_videobar.video_test_published_readonly;
		let modal = false;
		let self = this;
		let buttons = [
			{"text": this.egw().lang('Save'), id: 'submit', image: 'check', "default": true},
			{"text": this.egw().lang('Skip'), id: 'skip', image: 'cancel'}
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
					return b.id != "skip";
			}
		});

		switch (parseInt(_attrs.overlay_question_mode))
		{
			case et2_smallpart_videooverlay.overlay_question_mode_skipable:
			case et2_smallpart_videooverlay.overlay_question_mode_reqires:
				if (!is_readonly)
				{
					pauseSwitch = true;
					this.videobar.video[0].addEventListener('et2_video.onTimeUpdate.'+this.videobar.id, pause_callback);
				}
				if (_attrs.overlay_question_mode == et2_smallpart_videooverlay.overlay_question_mode_skipable || _attrs.answer_created){
					this.videobar.video[0].addEventListener('et2_video.onTimeUpdate.'+this.videobar.id, function(_time){
						self._removeExcessiveDialogs();
					});
				}
				break;
			case et2_smallpart_videooverlay.overlay_question_mode_required_limitted_time:
				break;
		}

		let error_msg;
		let dialog = <Et2Dialog>et2_createWidget("et2-dialog", {
			callback: function (_btn, _value) {
				if (error_msg) {
					error_msg.close();
					error_msg = null;
				}
				// check required minimum number of answers are given
				// ToDo: this should come from the et2_smallpart_question_multiplechoice object or app.ts
				if (_attrs.min_answers && _attrs.overlay_type === 'smallpart-question-multiplechoice')
				{
					let checked = 0;
					this.eTemplate.widgetContainer.getWidgetById('answers')?.iterateOver(function(_checkbox : et2_checkbox)
					{
						if (_checkbox.get_value()) checked++;
					}, this, et2_checkbox);
					if (checked < _attrs.min_answers)
					{
						error_msg = egw(parent).message(egw(parent).lang('A minimum of %1 answers need to be checked!', _attrs.min_answers), 'error');
						return false;
					}
				}
				if ((_btn == 'skip' || _btn == 'submit') && self.videobar.paused() && !self.videobar.ended())
				{
					if (self.questionDialogs.length == 1)
					{
						self.toolbar_play.click(null);
					}
					else {
						self._questionDialogs(_attrs.overlay_id)._remove();
					}
				}
				if (_btn == 'submit' && _value && !is_readonly)
				{
					egw.request('smallpart.EGroupware\\SmallParT\\Questions.ajax_answer', [
						jQuery.extend(_attrs, {answer_data: jQuery.extend(true,  {}, _attrs.answer_data, _value.answer_data)})])
					.then((_result) => {
						if (_result && typeof _result.error === 'undefined')
						{
							self._update_element(_attrs.overlay_id, _result);
						}
					});
				}
				pauseSwitch = false;
			},
			title: this.egw().lang('Question number %1', _attrs.question_n),
			buttons: buttons,
			value: {
				content:_attrs,
				readonlys: is_readonly ? { '__ALL__' : true} : {}
			},
			draggable: video.video_test_display == et2_smallpart_videobar.video_test_display_dialog,
			resizable: false,
			hideOnEscape: false,
			noCloseButton: true,
			dialogClass: 'questionDisplayBox',
			template: _attrs.template_url || egw.webserverUrl + '/smallpart/templates/default/question.'+_attrs.overlay_type.replace('smallpart-question-','')+'.xet'
		}, et2_dialog._create_parent('smallpart'));

		const dialogParent = video.video_test_display != et2_smallpart_videobar.video_test_display_dialog ?
							 _widget.getWidgetById(video.video_test_display == et2_smallpart_videobar.video_test_display_on_video ? ".et2_smallpart-videooverlay" : ".rightBoxArea") : '';
		(dialogParent || document.body).append(dialog);
		return dialog;
	}
	_removeExcessiveDialogs () {
		if (this.questionDialogs)
		{
			// go through all dialogs and remove which are not in display time
			this.questionDialogs.forEach(d=>{
				if (d.content && this.videobar.currentTime() < d.content?.overlay_start
					|| this.videobar.currentTime() > d.content?.overlay_start+d.content?.overlay_duration+1)
				{
					this._questionDialogs(d.content?.overlay_id)._remove();
				}
			});
		}
	}
	_onresize_videobar(_width: number, _height: number, _position: number) {
		if (this._elementSlider) jQuery(this._elementSlider.getDOMNode()).css({width:_width});
		this._elementSlider?.set_seek_position(_position);
		this.renderElements();
		this.onSeek(this.videobar.currentTime());
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

	public getElementSlider()
	{
		return this._elementSlider;
	}
}
et2_register_widget(et2_smallpart_videooverlay, ["smallpart-videooverlay"]);