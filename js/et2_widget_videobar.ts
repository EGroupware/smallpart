/**
 * EGroupware - SmallParT - videobar widget
 *
 * @link https://www.egroupware.org
 * @package smallpart
 * @subpackage Ui
 * @license http://opensource.org/licenses/gpl-license.php GPL - GNU General Public License
 */

import {et2_video} from "../../api/js/etemplate/et2_widget_video";
import {et2_register_widget, WidgetConfig} from "../../api/js/etemplate/et2_core_widget";
import {ClassWithAttributes} from '../../api/js/etemplate/et2_core_inheritance';

export class et2_smallpart_videobar extends et2_video
{

	static readonly _attributes : any = {
		"marking_enabled": {
			"name": "Disabled",
			"type": "boolean",
			"description": "",
			"default": false
		},

		"marking_callback": {

		},

		"slider_onclick": {
			"type":"js"
		},

		"slider_tags": {
			"name": "slider tags",
			"type": "any",
			"description": "comment tags on slider",
			"default": {}
		}
	};

	private container: JQuery = null;

	private wrapper: JQuery = null;

	private slider: JQuery = null;

	private marking: JQuery = null;

	private slider_progressbar: JQuery = null;

	private comments:any = null;

	private videoPlayInterval: number = null;
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
			.addClass('videobar_marking markingMask');

		// slider progressbar span
		this.slider_progressbar = jQuery(document.createElement('span'))
			.addClass('videobar_slider_progressbar')
			.appendTo(this.slider);

		if (this.options.marking_enabled) this.wrapper.append(this.marking);

		this._buildHandlers();
		this.setDOMNode(this.container[0]);
	}

	private _buildHandlers()
	{
		var self = this;
		this.slider.on('click', function(e){
			self._slider_onclick.call(self ,e);
		});

	}

	private _slider_onclick(e:JQueryMouseEventObject)
	{
		this.slider_progressbar.css({width:e.offsetX});
		this.video[0]['currentTime'] = e.offsetX * this.video[0]['duration'] / this.slider.width();
	}

	doLoadingFinished()
	{
		super.doLoadingFinished();
		let self = this;

		this.video[0].addEventListener("loadedmetadata", function(){
			// this will make sure that slider and video are synced
			self.slider.width(self.video.width());
			self.set_slider_tags(self.comments);
		});
		return false;
	}

	private _vtimeToSliderPosition(_vtime: string | number): number
	{
		return this.slider.width() / this.video[0]['duration']  * parseInt(<string>_vtime);
	}

	public set_slider_tags(_comments)
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
				.addClass('commentOnSlider'));
		}
	}

	public set_marking_enabled(_state)
	{
		this.marking.toggle(_state);
	}

	public setMarkingMask(_state)
	{
		if (_state)
		{
			this.marking.addClass('markingMask');
		}
		else
		{
			this.marking.removeClass('markingMask');
		}
	}

	/**
	 * Seek to a time / position
	 *
	 * @param _vtime in seconds
	 */
	public seek_video(_vtime : number)
	{
		super.seek_video(_vtime);

		this.slider_progressbar.css({width: this._vtimeToSliderPosition(_vtime)});
	}

	/**
	 * Play video
	 */
	public play_video() : Promise<void>
	{
		let self = this;
		return super.play_video().then(function(){
			self.videoPlayInterval = window.setInterval(function(){
				self.slider_progressbar.css({width: self._vtimeToSliderPosition(self.video[0].currentTime)});
			},1);
		});
	}

	/**
	 * Pause video
	 */
	public pause_video()
	{
		window.clearInterval(this.videoPlayInterval);

		super.pause_video();
	}
}
et2_register_widget(et2_smallpart_videobar, ["smallpart-videobar"]);