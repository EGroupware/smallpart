import {et2_baseWidget} from "../../api/js/etemplate/et2_core_baseWidget";
import {et2_smallpart_videobar} from "./et2_widget_videobar";
import {et2_createWidget, et2_register_widget, WidgetConfig} from "../../api/js/etemplate/et2_core_widget";
import {ClassWithAttributes} from "../../api/js/etemplate/et2_core_inheritance";
import {et2_button} from "../../api/js/etemplate/et2_widget_button";

/**
 * Creates a Video controls
 */
export class et2_smallpart_video_controls extends et2_baseWidget
{
	static readonly _attributes: any = {
		videobar: {
			name: 'videobar',
			type: 'string',
			description: 'videobar this overlay is for',
		},
		onplay_callback: {
			name: 'play callback',
			type: 'js',
			description: 'callback function on play',
		},
		onpause_callback: {
			name: 'pause callback',
			type: 'js',
			description: 'callback function on pause',
		},
		onforward_callback: {
			name: 'forward callback',
			type: 'js',
			description: 'callback function on forward',
		},
		onbackward_callback: {
			name: 'backward callback',
			type: 'js',
			description: 'callback function on backward',
		}
	}
	protected videobar: et2_smallpart_videobar;
	protected controls : {
		play: et2_button,
		forward: et2_button,
		backward: et2_button
	} = {play:null,backward:null, forward:null};

	protected div : HTMLElement = null;

	/**
	 * Constructor
	 */
	constructor(_parent, _attrs?: WidgetConfig, _child?: object)
	{
		// Call the inherited constructor
		super(_parent, _attrs, ClassWithAttributes.extendAttributes(et2_smallpart_video_controls._attributes, _child || {}));
		this.div = document.createElement("div");
		this.div.classList.add(`et2_${super.getType()}`);

		this.controls.play = <et2_button> et2_createWidget('buttononly', {
			statustext: "play/pause",
			class:"glyphicon glyphicon-play button_std_controller",
			onclick: this._onPlayCallback.bind(this)
		}, this);
		this.controls.backward = <et2_button> et2_createWidget('buttononly', {
			statustext: "backward",
			class:"glyphicon custom-font-icon-backward button_std_backward button_std_controller",
			onclick: this._onBackwardCallback.bind(this)
		}, this);
		this.controls.forward = <et2_button> et2_createWidget('buttononly', {
			statustext: "forward",
			class:"glyphicon custom-font-icon-backward button_std_backward button_std_controller",
			onclick: this._onForwardCallback.bind(this)
		}, this);

		super.setDOMNode(this.div);
	}

	/**
	 * Set videobar to use
	 *
	 * @param _id_or_widget
	 */
	set_videobar(_id_or_widget: string | et2_smallpart_videobar)
	{
		if (typeof _id_or_widget === 'string') {
			_id_or_widget = <et2_smallpart_videobar>this.getRoot().getWidgetById(_id_or_widget);
		}
		if (_id_or_widget instanceof et2_smallpart_videobar) {
			this.videobar = _id_or_widget;
			this.videobar.video[0].addEventListener("et2_video.onReady." + this.videobar.id, _ => {
				this.getDOMNode().style.width = `${this.videobar.video.width()}px`;
			});
		}
	}

	private _onPlayCallback(_event : Event, _widget : et2_button, _node : HTMLElement)
	{
		if (this.videobar.paused())
		{
			this.videobar.play();
		}
		else
		{
			this.videobar.pause_video();
		}

		if (typeof this.options.onplay_callback == 'function')
		{
			this.options.onplay_callback.call(this, _event, _widget, _node);
		}
	}

	private _onForwardCallback(_event : Event, _widget : et2_button, _node : HTMLElement)
	{
		if (typeof this.options.onforward_callback == 'function')
		{
			this.options.onforward_callback.call(this, _event, _widget, _node);
		}
	}

	private _onBackwardCallback(_event : Event, _widget : et2_button, _node : HTMLElement)
	{
		if (typeof this.options.onbackward_callback == 'function')
		{
			this.options.onbackward_callback.call(this, _event, _widget, _node);
		}
	}
}
et2_register_widget(et2_smallpart_video_controls, ["smallpart-video-controls"]);
