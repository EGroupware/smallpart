/**
 * EGroupware - SmallParT - videobar widget
 *
 * @link https://www.egroupware.org
 * @package smallpart
 * @subpackage Ui
 * @license http://opensource.org/licenses/gpl-license.php GPL - GNU General Public License
 */

import {et2_video} from "../../api/js/etemplate/et2_widget_video";
import {et2_createWidget, et2_register_widget, WidgetConfig} from "../../api/js/etemplate/et2_core_widget";
import {ClassWithAttributes} from '../../api/js/etemplate/et2_core_inheritance';
import {CommentType} from './app';

type CommentMarked = Array<{x: number; y: number; c: string}>;
export class et2_smallpart_videobar extends et2_video implements et2_IResizeable
{

	static readonly _attributes : any = {
		"marking_enabled": {
			"name": "Marking",
			"type": "boolean",
			"description": "",
			"default": false
		},

		"marking_readonly": {
			"name": "Marking readonly",
			"type": "boolean",
			"description": "",
			"default": true
		},

		"marking_color": {
			"name": "Marking color",
			"type": "string",
			"description": "",
			"default": "ffffff"
		},

		"marking_callback": {

		},

		"slider_callback": {
			"name": "Slider on click callback",
			"type":"js",
			"default": et2_no_init,
			"description": "Callback function to get executed after clicking om slider bar"
		},

		"slider_tags": {
			"name": "slider tags",
			"type": "any",
			"description": "comment tags on slider",
			"default": {}
		},

		"stop_contextmenu": {
			"name": "stop contextmenu",
			"type": "boolean",
			"description": "This would prevent the browser native contextmenu on video tag",
			"default": true
		},
		"ontimeupdate_callback": {
			"name": "ontimeupdate callback",
			"type":"js",
			"default": et2_no_init,
			"description": "Callback function to get executed while video is playing"
		},
		"onresize_callback": {
			"name": "onresize callback",
			'type': "js",
			"default": et2_no_init,
			"description": "Callback function called when video gets resized"
		},
		seekable: {
			name: 'seekable',
			type: 'boolean',
			description: 'Make slider active for seeking in timeline',
			default: true
		},
		watermark: {
			"name": "video watermark",
			"type": "any",
			"description": "Set a text watermark on video in a given position.",
			"default": {}
		}
	};

	private readonly container: JQuery = null;

	private readonly wrapper: JQuery = null;

	private readonly slider: JQuery = null;

	private readonly marking: JQuery = null;

	private readonly timer: et2_smallpart_videotime = null;

	private readonly watermark = null;

	private readonly slider_progressbar: JQuery = null;

	private comments: Array<CommentType> = null;

	private mark_ratio: number = 0;

	private marking_color: string = 'ffffff';

	private marks: CommentMarked = [];

	private marking_readonly: boolean = true;

	private _scrolled: Array <object> = [];

	public ontimeupdate_callback;
	public slider_callback;
	public onresize_callback;

	public static video_test_display_instead_of_comment : number = 0;
	public static video_test_display_dialog : number = 1;
	public static video_test_display_on_video : number = 2;
	public static video_test_option_pauseable : number = 1;
	public static video_test_option_not_seekable : number = 2;
	public static video_test_published_readonly : number = 3;
	public static video_test_published_published : number = 1;
	public static video_test_published_draft : number = 0;
	public static video_test_published_unavailabe : number = 2;
	public static course_options_video_watermark: number = 2;


	/**
	 *
	 * @memberOf et2_DOMWidget
	 */
	constructor(_parent, _attrs? : WidgetConfig, _child? : object)
	{
		// Call the inherited constructor
		super(_parent, _attrs, ClassWithAttributes.extendAttributes(et2_smallpart_videobar._attributes, _child || {}));

		// wrapper DIV container for video tag and marking selector
		this.wrapper = jQuery(document.createElement('div'))
			.append(this.video)
			.addClass('videobar_wrapper');

		// widget container
		this.container = jQuery(document.createElement('div'))
			.append(this.wrapper)
			.addClass('et2_smallpart_videobar videobar_container');

		// slider div
		this.slider = jQuery(document.createElement('div'))
			.appendTo(this.container)
			.addClass('videobar_slider');

		// marking div
		this.marking = jQuery(document.createElement('div'))
			.addClass('videobar_marking container');
		this.marking.append(jQuery(document.createElement('div'))
			.addClass('markingMask maskOn'));
		this.marking.append(jQuery(document.createElement('div'))
			.addClass('marksContainer'));

		// slider progressbar span
		this.slider_progressbar = jQuery(document.createElement('span'))
			.addClass('videobar_slider_progressbar')
			.appendTo(this.slider);

		this.wrapper.append(this.marking);

		this._buildHandlers();

		// timer span
		this.timer = <et2_smallpart_videotime>et2_createWidget('smallpart-videotime', {}, this);

		this._setWatermark();

		//@TODO: this should not be necessary but for some reason attach to the dom
		// not working on et2_creatWidget there manully attach it here.
		jQuery(this.timer.getDOMNode()).attr('id',  this.id+"[timer]")
		this.container.append(this.timer.getDOMNode());

		if (this.options.stop_contextmenu) this.video.on('contextmenu', function(){return false;});

		this.setDOMNode(this.container[0]);
	}

	private _buildHandlers()
	{
		let self = this;
		if (this.options.seekable)
		{
			this.getSliderDOMNode().on('click', function(e){
				self.slider_onclick.call(self ,e);
			});
		}
	}
	private _setWatermark()
	{
		let options = this.getArrayMgr('content').getEntry('course_options');
		let self = this;
		let name = '';
		if (options & et2_smallpart_videobar.course_options_video_watermark)
		{
			egw.accountData(egw.user('account_id'), 'account_fullname', false,function(_data){
				name = _data[egw.user('account_id')];
				window.setInterval(function(){
					let node = jQuery(document.createElement('span')).attr('style','display:block !important;' +
						'visibility:visible !important;background:white !important;color:black !important;'+
						'width:auto !important;height:auto !important;position:absolute !important;'+
						'top:20px !important;left:20px !important;bottom:auto !important;right:auto !important;')
						.text(name)
						.appendTo(self.wrapper);
					window.setTimeout(function(){
						node.remove();
					}, 1000);
				}, 10000);
			}, egw);

		}
	}

	public slider_onclick(e:JQueryMouseEventObject)
	{
		if (!this.options.seekable) return;

		this.slider_progressbar.css({width:e.offsetX});
		this._scrolled = [];
		this.previousTime(this.currentTime());
		this.currentTime(e.offsetX * this.duration() / this.getSliderDOMNode().width());
		this.timer.set_value(this.currentTime());
		if (typeof this.slider_callback == "function") this.slider_callback(this, this);
	}

	set_seekable (_value : boolean)
	{
		this.options.seekable = _value;
	}

	doLoadingFinished(): boolean
	{
		super.doLoadingFinished();
		let self = this;

		this.video[0].addEventListener("et2_video.onReady."+this.id, function(){
			self.videoLoadnigIsFinished();
		});
		return false;
	}

	public _vtimeToSliderPosition(_vtime: string | number): number
	{
		return this.slider.width() / this.duration()  * parseFloat(<string>_vtime);
	}

	public set_slider_tags(_comments: Array<CommentType>)
	{
		this.comments = _comments;
		// need to wait video is loaded before setting tags
		if (this.video.width() == 0) return;

		this.slider.empty();
		this.slider.append(this.slider_progressbar);
		for (let i in this.comments)
		{
			if (!this.comments[i]) continue;
			this.slider.append(jQuery(document.createElement('span'))
				.offset({left: this._vtimeToSliderPosition(this.comments[i]['comment_starttime'])})
				.css({'background-color': '#'+this.comments[i]['comment_color']})
				.attr('data-id', this.comments[i]['comment_id'])
				.addClass('commentOnSlider commentColor'+this.comments[i]['comment_color']));
		}
	}

	public set_marking_readonly(_state)
	{
		this.marking_readonly = _state;
	}

	public set_marking_color (_color)
	{
		this.marking_color = _color;
	}

	public set_marking_enabled(_state: boolean, _callback?)
	{
		let self= this;
		let isDrawing = false;
		this.getMarkingNode().toggle(_state);
		let drawing = function(e)
		{
			if (e.target.nodeName !== "SPAN" && !self.marking_readonly)
					{
						let pixelX = Math.floor(e.originalEvent.offsetX / self.mark_ratio) * self.mark_ratio;
						let pixelY = Math.floor(e.originalEvent.offsetY /  self.mark_ratio) * self.mark_ratio;
						let mark = {
							x: self._convertMarkedPixelX2Percent(pixelX),
							y: self._convertMarkedPixelY2Percent(pixelY),
							c: self.marking_color
						};
						self._addMark(mark);
						_callback(mark);
			}
		};
		if (_state)
		{
			this.getMarkingNode().find('.marksContainer')
				.off()
				.on('mousedown', function(){
					console.log('mousedown')
					isDrawing = true;
				})
				.on('mouseup', function(e){
					isDrawing = false;
					drawing(e);
				})
				.on('mousemove', function(e){
					if (isDrawing === true) {
						drawing(e);
					}
				});
		}
	}

	public setMarkingMask(_state: boolean)
	{
		if (_state)
		{
			this.getMarkingNode().find('.markingMask').addClass('maskOn');
		}
		else
		{
			this.getMarkingNode().find('.markingMask').removeClass('maskOn');
		}
	}

	public setMarksState(_state: boolean)
	{
		this.getMarkingNode().find('.marksContainer').toggle(_state);
	}

	public setMarks(_marks: CommentMarked)
	{
		let self = this;
		// clone the array to avoid missing its original content
		let $marksContainer = this.getMarkingNode().find('.marksContainer').empty();
		this.marks = _marks?.slice(0) || [];
		this.mark_ratio = parseFloat((this.video.width() / 80).toPrecision(4));
		for(let i in _marks)
		{
			$marksContainer.append(jQuery(document.createElement('span'))
				.offset({left: this._convertMarkPercentX2Pixel(_marks[i]['x']), top: this._convertMarkPercentY2Pixel(_marks[i]['y'])})
				.css({
					"background-color":"#"+_marks[i]['c'],
					"width": this.mark_ratio,
					"height": this.mark_ratio
				})
				.attr('data-color', _marks[i]['c'])
				.click(function(){
					if (!self.marking_readonly)	self._removeMark(self._getMark(this), this);
				})
				.addClass('marks'));
		}
	}

	public getMarks(): CommentMarked
	{
		if (this.marks) return this.marks;
		let $marks = this.getMarkingNode().find('.marksContainer').find('span.marks');
		let marks = [];
		let self =this;
		$marks.each(function(){
			marks.push({
				x: self._convertMarkedPixelX2Percent(parseFloat(this.style.left)),
				y: self._convertMarkedPixelY2Percent(parseFloat(this.style.top)),
				c: this.dataset['color']
			})
		});
		this.marks = marks;
		return marks;
	}

	private _getMark(_node: HTMLElement): CommentMarked
	{
		return [{
			x: this._convertMarkedPixelX2Percent(parseFloat(_node.style.left)),
			y: this._convertMarkedPixelY2Percent(parseFloat(_node.style.top)),
			c: _node.dataset['color']
		}];
	}

	private _addMark(_mark)
	{
		this.marks.push(_mark);
		this.setMarks(this.marks);
	}

	public removeMarks()
	{
		this.marks = [];
		this.getMarkingNode().find('.marksContainer').find('span.marks').remove();
	}

	private _removeMark(_mark: CommentMarked, _node: HTMLElement)
	{
		for (let i in this.marks)
		{
			if (this.marks[i]['x'] == _mark[0]['x'] && this.marks[i]['y'] == _mark[0]['y']) this.marks.splice(<number><unknown>i, 1);
		}
		if (_node) jQuery(_node).remove();
	}

	private _convertMarkedPixelX2Percent(_x: number): number
	{
		return parseFloat((_x / this.video.width() / 0.01).toPrecision(4));
	}

	private _convertMarkedPixelY2Percent(_y: number): number
	{
		return parseFloat((_y / this.video.height() / 0.01).toPrecision(4));
	}

	private _convertMarkPercentX2Pixel(_x: number): number
	{
		return _x * this.video.width() * 0.01;
	}

	private _convertMarkPercentY2Pixel(_y: number): number
	{
		return _y * this.video.height() * 0.01;
	}

	/**
	 * Seek to a time / position
	 *
	 * @param _vtime in seconds
	 */
	public seek_video(_vtime : number)
	{
		super.seek_video(_vtime);
		this._scrolled = [];
		this.timer.set_value(this.currentTime());
		this.slider_progressbar.css({width: this._vtimeToSliderPosition(_vtime)});
	}

	/**
	 * Play video
	 */
	public play_video(_ended_callback?, _onTagCallback?) : Promise<void>
	{
		let self = this;
		if (this.ended() && this.duration() == this.currentTime()
			&& this.getArrayMgr('content').getEntry('video')['video_test_options'] & et2_smallpart_videobar.video_test_option_not_seekable)
		{
			return;
		}
		let ended_callback = _ended_callback;
		this._scrolled = [];
		return super.play_video().then(function(){
			self.video[0].addEventListener('et2_video.onTimeUpdate.'+self.id, function(_event){
				let currentTime = self.currentTime();
				self.slider_progressbar.css({width: Math.round(self._vtimeToSliderPosition(currentTime))});

				self.timer.set_value(self.currentTime());
				if (typeof ended_callback == "function" && self.ended())
				{
					ended_callback.call();
					self.pause_video();
				}
				if (typeof _onTagCallback == "function") {
					for (let i in self.comments)
					{
						if (Math.floor(currentTime) == parseInt(String(self.comments[i]['comment_starttime']))
							&& (self._scrolled.length == 0 || self._scrolled.indexOf(Object(parseInt(String(self.comments[i]['comment_id'])))) == -1 ))
						{
							_onTagCallback.call(this, self.comments[i]['comment_id']);
							self._scrolled.push(Object(parseInt(String(self.comments[i]['comment_id']))));
						}
					}
				}
				if (typeof self.ontimeupdate_callback == "function") {
					self.ontimeupdate_callback.call(this, currentTime);
				}
			});
		});
	}

	/**
	 * Pause video
	 */
	public pause_video()
	{
		super.pause_video();
	}

	public videoLoadnigIsFinished()
	{
		super.videoLoadnigIsFinished();
		// this will make sure that slider and video are synced
		this.slider.width(this.video.width());
		this.set_slider_tags(this.comments);
		this.getMarkingNode().css({width: this.video.width(), height: this.video.height()});
	}

	resize (_height)
	{
		this.slider.width('auto');
		this.getMarkingNode().width('auto');
		this.slider.width(this.video.width());
		this.getMarkingNode().css({width: this.video.width(), height: this.video.height()});
		this.slider_progressbar.css({width: this._vtimeToSliderPosition(this.currentTime())});
		//redraw marks and tags to get the right ratio
		this.setMarks(this.getMarks());
		this.set_slider_tags(this.comments);
		if (typeof this.onresize_callback == 'function')
		{
			this.onresize_callback.call(this, this.video.width(), this.video.height(), this._vtimeToSliderPosition(this.currentTime()))
		}
	}

	/**
	 * return slider dom node as jquery object
	 */
	getSliderDOMNode()
	{
		return this.slider;
	}

	getMarkingNode()
	{
		return this.marking;
	}
}
et2_register_widget(et2_smallpart_videobar, ["smallpart-videobar"]);
