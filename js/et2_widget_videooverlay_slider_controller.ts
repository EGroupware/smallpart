import {et2_baseWidget} from "../../api/js/etemplate/et2_core_baseWidget";
import {et2_smallpart_videobar} from "./et2_widget_videobar";
import {OverlayElement} from "./et2_videooverlay_interface";
import {et2_register_widget, WidgetConfig} from "../../api/js/etemplate/et2_core_widget";
import {ClassWithAttributes} from "../../api/js/etemplate/et2_core_inheritance";
import {et2_IResizeable} from "../../api/js/etemplate/et2_core_interfaces";

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
export class et2_smallpart_videooverlay_slider_controller extends et2_baseWidget implements et2_IResizeable{
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
	public disable_callback : boolean = false;
	protected marks_positions : [OverlaySliderControllerMarkPositionType] | [] = [];
	protected videobar: et2_smallpart_videobar;
	protected elements:  Array<OverlayElement>;
	protected marks: any = {};
	private _selected: any;
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
		this.div[0].addEventListener('click', this._markClicked, true);
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
					self.set_seek_position(Math.round(self.videobar._vtimeToSliderPosition(self.videobar.currentTime())));
				});
			}
			this.videobar.video[0].addEventListener("et2_video.onReady."+this.videobar.id, _=>{
				this.getDOMNode().style.width = `${this.videobar.video.width()}px`;
				this.videobar.video[0].addEventListener('et2_video.onTimeUpdate.'+this.videobar.id, (_event) => {
					self.onTimeUpdate(this.videobar.currentTime());
				});
			});
		}
	}

	/**
	 * set given elements as actual marks on sliderbar
	 *
	 * A mark is a plain element, not a widget: there can be hundreds of them, and a widget per mark made every
	 * rebuild take seconds.  Clicks are caught by one listener on the bar, see _markClicked().
	 *
	 * @param _elements
	 */
	set_value(_elements:  Array<OverlayElement>)
	{
		this.elements = _elements;

		this._checkVideoIsLoaded().then(_=>{
			this.marks_positions = [];
			this.marks = {};
			this._selected = null;
			const bar = this.div[0];
			bar.replaceChildren();

			// Place the marks left to right, each in the first row where it does not touch another one
			const placed = (this.elements ?? []).map(_element => ({
				element: _element,
				left: this.videobar._vtimeToSliderPosition(_element.starttime),
				width: this.videobar._vtimeToSliderPosition(_element.duration ? _element.duration : 1)
			})).sort((a, b) => a.left - b.left);
			const rowEnds : number[] = [];
			const fragment = document.createDocumentFragment();
			for(const mark of placed)
			{
				let row = rowEnds.findIndex(end => mark.left > end);
				if(row < 0)
				{
					row = rowEnds.length;
					rowEnds.push(-Infinity);
				}
				rowEnds[row] = Math.max(rowEnds[row], mark.left + mark.width);
				this.marks_positions.push(<never>{left: mark.left, width: mark.width, row});

				const _element = mark.element;
				const node = document.createElement('span');
				node.className = 'et2_label ' + (_element.class ?? '');
				node.dataset.id = String(_element.id);
				node.style.left = mark.left + 'px';
				node.style.width = mark.width + 'px';
				node.style.top = ((row * 5) + 3) + 'px';
				if(_element.color) node.style.backgroundColor = _element.color;
				fragment.appendChild(node);
				this.marks[_element.id] = this._createMark(_element.id, node);
			}
			bar.appendChild(fragment);
		});
	}

	/**
	 * A mark as handed to the callbacks: its id and its node
	 */
	private _createMark(_id, _node : HTMLElement) : any
	{
		return {
			id: et2_smallpart_videooverlay_slider_controller.mark_id_prefix + _id,
			node: _node,
			get class() { return _node.className; },
			getDOMNode: () => _node,
			set_class: (_class : string) => { _node.className = _class; }
		};
	}

	/**
	 * The mark of an element, or undefined
	 *
	 * @param _id id of the element
	 */
	getMark(_id : number | string)
	{
		return this.marks?.[_id];
	}

	/**
	 * One click listener for all marks
	 */
	private _markClicked = (_event : MouseEvent) =>
	{
		const node = (<HTMLElement>_event.target)?.closest?.('.et2_label[data-id]');
		const mark = node ? this.marks[(<HTMLElement>node).dataset.id] : null;
		if(!mark || !this.options.seekable) return;
		_event.stopImmediatePropagation();
		if(this.disable_callback) return;
		if(typeof this.options.onclick_callback == 'function' && this.onclick_callback(_event, mark))
		{
			this.set_selected(mark);
		}
	};

	/**
	 * set currently selected mark
	 * @param _widget
	 */
	set_selected(_widget)
	{
		if (!_widget)
		{
			this._clear_selected();
			return;
		}
		this._selected = _widget;
		_widget.node.classList.add('selected');
		Object.values(this.marks).forEach(function(_mark : any){if (_mark.id != _widget.id){
			_mark.node.classList.remove('selected');
		}});
	}

	/**
	 * deselect marked element
	 */
	_clear_selected()
	{
		Object.values(this.marks).forEach(function(_mark : any){_mark.node.classList.remove('selected');});
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
	 * disable on tag callback function
	 * @param _state
	 */
	disableCallback(_state)
	{
		this.disable_callback = _state;
	}

	set_seek_position(_value)
	{
		let value = Math.floor(_value);
		this.div.css({
			background: 'linear-gradient(90deg, var(--sl-color-neutral-700) ' + value + 'px, var(--sl-color-neutral-600) ' + value + 'px, var(--sl-color-neutral-600) 100%)'
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
			if(this.videobar?.duration() > 0)
			{
				clearInterval(this._interval);
				return _resolved();
			}
			this._interval = setInterval(_=>{
				if(this.videobar?.duration() > 0)
				{
					clearInterval(this._interval);
					_resolved();
					return;
				}
			}, 1000);
		});
	}

	resize(_height)
	{
		if(this.videobar)
		{
			this.getDOMNode().style.width = `${this.videobar.video[0].clientWidth}px`;
		}
		this.set_value(this.elements);
	}

	/**
	 * Periodically called while video is playing to add new overlay elements
	 *
	 * @param _time
	 */
	onTimeUpdate(_time : number)
	{
		this.set_seek_position(Math.round(this.videobar._vtimeToSliderPosition(_time)));
	}
}
et2_register_widget(et2_smallpart_videooverlay_slider_controller, ["smallpart-videooverlay-slider-controller"]);
