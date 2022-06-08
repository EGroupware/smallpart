import {et2_baseWidget} from "../../api/js/etemplate/et2_core_baseWidget";
import {et2_smallpart_videobar} from "./et2_widget_videobar";
import {et2_smallpart_videooverlay} from "./et2_widget_videooverlay";
import {OverlayElement} from "./et2_videooverlay_interface";
import {et2_description} from "../../api/js/etemplate/et2_widget_description";
import {et2_createWidget, et2_register_widget, WidgetConfig} from "../../api/js/etemplate/et2_core_widget";
import {ClassWithAttributes} from "../../api/js/etemplate/et2_core_inheritance";

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
export class et2_smallpart_videooverlay_slider_controller extends et2_baseWidget {
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
		},
		seekable: {
			name: 'seekable',
			type: 'boolean',
			description: 'Make slider active for seeking in timeline',
			default: true
		}
	}
	public onclick_slider_callback;
	public onclick_callback;
	protected marks_positions : [OverlaySliderControllerMarkPositionType] | [] = [];
	protected videobar: et2_smallpart_videobar;
	protected elements:  Array<OverlayElement>;
	protected marks: any = [];
	private _selected: et2_description;
	private _interval: any = null;
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
			if (this.options.seekable)
			{
				this.div.on('click', function(e){
					self.videobar.slider_onclick.call(self.videobar ,e);
					if (typeof self.onclick_slider_callback == 'function') self.onclick_slider_callback.call(self, e);
				});
			}
			this.videobar.video[0].addEventListener("et2_video.onReady."+this.videobar.id, _=>{
				this.getDOMNode().style.width = `${this.videobar.video.width()}px`;
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
			this.getChildren()[i].remove();
		}
		let self = this;

		this._checkVideoIsLoaded().then(_=>{
			this.elements.forEach(function(_element, _idx){
				self.marks[_element.id] = et2_createWidget('description', {
					id:et2_smallpart_videooverlay_slider_controller.mark_id_prefix+_element.id,
					class: 'et2_label'
				}, self);
				if (self.options.seekable)
				{
					self.marks[_element.id].onclick=function(_event, _widget){
						_event.stopImmediatePropagation()
						if (typeof self.options.onclick_callback == 'function' && self.onclick_callback(_event, _widget))
						{
							self._set_selected(_widget);
						}
					};
				}

				//self.marks[_element.id].doLoadingFinished();
				let pos : OverlaySliderControllerMarkPositionType = self._find_position(self.marks_positions, {
					left:self.videobar._vtimeToSliderPosition(_element.starttime),
					width:self.videobar._vtimeToSliderPosition(_element.duration ? _element.duration : 1),
					row: 0
				}, 0);
				self.marks_positions.push(<never>pos);

				// set its actuall position in DOM
				jQuery(self.marks[_element.id].getDOMNode())
					.css({
						left:pos.left+'px',
						width:pos.width+'px',
						top:pos.row != 0 ? pos.row*(5+2) : pos.row+'px',
						"background-color": _element.color ?? ''
					})
					.addClass(_element.class);
			});
		});
	}

	/**
	 * set currently selected mark
	 * @param _widget
	 */
	_set_selected(_widget)
	{
		this._selected = _widget;
		_widget.set_class(`${_widget.class} selected`);
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
			id: this._selected.id.split(et2_smallpart_videooverlay_slider_controller.mark_id_prefix)[1]
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

	/**
	 * Promise to check the video is loaded
	 * @private
	 */
	private _checkVideoIsLoaded()
	{
		clearInterval(this._interval);
		return new Promise((_resolved, _rejected) => {
			if (this.videobar.duration()>0)
			{
				clearInterval(this._interval);
				return _resolved();
			}
			this._interval = setInterval(_=>{
				if (this.videobar.duration()>0)
				{
					clearInterval(this._interval);
					_resolved();
					return;
				}
			}, 1000);
		});
	}
}
et2_register_widget(et2_smallpart_videooverlay_slider_controller, ["smallpart-videooverlay-slider-controller"]);
