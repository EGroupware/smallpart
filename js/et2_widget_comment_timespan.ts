import {et2_smallpart_videobar} from "./et2_widget_videobar";
import {et2_createWidget, et2_register_widget, WidgetConfig} from "../../api/js/etemplate/et2_core_widget";
import {ClassWithAttributes} from "../../api/js/etemplate/et2_core_inheritance";
import {et2_button} from "../../api/js/etemplate/et2_widget_button";
import {et2_number} from "../../api/js/etemplate/et2_widget_number";
import {et2_baseWidget} from "../../api/js/etemplate/et2_core_baseWidget";

/**
 * Creates a timespan controller
 */
export class et2_smallpart_comment_timespan extends et2_baseWidget
{
	static readonly _attributes: any = {
		videobar: {
			name: 'videobar',
			type: 'string',
			description: 'videobar this overlay is for',
		},
		starttime: {
			name: 'starttime',
			type: 'integer',
			description: 'comment starttime',
		},
		stoptime: {
			name: 'stoptime',
			type: 'integer',
			description: 'comment stoptime',
		}
	}
	protected videobar: et2_smallpart_videobar;
	protected widgets : {
		starttime: et2_number,
		stoptime: et2_number,
		starttime_picker: et2_button,
		stoptime_picker: et2_button
	} = {starttime:null, stoptime:null, starttime_picker:null, stoptime_picker: null};

	protected div : HTMLElement = null;

	/**
	 * Constructor
	 */
	constructor(_parent, _attrs?: WidgetConfig, _child?: object)
	{
		// Call the inherited constructor
		super(_parent, _attrs, ClassWithAttributes.extendAttributes(et2_smallpart_comment_timespan._attributes, _child || {}));
		this.div = document.createElement("div");
		this.div.classList.add(`et2_${super.getType()}`);
		// starttime
		this.widgets.starttime = <et2_number> et2_createWidget('int', {
			statustext: "start-time",
			label: "start-time",
			min: 0,
			onchange: this._checkTimeConflicts.bind(this)
		}, this);
		// starttime picker
		this.widgets.starttime_picker = <et2_button> et2_createWidget('buttononly', {
			statustext: "start-time picker",
			class: "glyphicon glyphicon-pushpin",
			onclick: this._timePicker.bind(this)
		}, this);
		// stoptime
		this.widgets.stoptime = <et2_number> et2_createWidget('int', {
			statustext: "stop-time",
			label: "stop-time",
			min: 0,
			class: "stoptime",
			onchange: this._checkTimeConflicts.bind(this)
		}, this);
		// stoptime picker
		this.widgets.stoptime_picker = <et2_button> et2_createWidget('buttononly', {
			statustext: "stop-time picker",
			class: "glyphicon glyphicon-pushpin",
			onclick: this._timePicker.bind(this)
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
				this.set_widgets();
			});
		}
	}

	set_starttime(_value)
	{
		this.widgets.starttime.set_value(_value);
	}

	set_stoptime(_value)
	{
		this.widgets.stoptime.set_value(_value);
	}

	set_widgets()
	{
		this.widgets.starttime.set_max(this.videobar.duration());
		this.widgets.stoptime.set_max(this.videobar.duration());
		this.widgets.starttime.set_value(this.options.starttime);
		this.widgets.stoptime.set_value(this.options.stoptime);
	}

	/**
	 * Re-evaluate starttime/stoptime max&min values
	 * @param _node
	 * @param _widget
	 */
	private _checkTimeConflicts(_node, _widget)
	{
		if (_widget == this.widgets.starttime)
		{
			this.widgets.starttime.set_max(this.widgets.stoptime.get_value());
			if (this.widgets.starttime.get_value() < this.widgets.stoptime.get_value()) this.widgets.stoptime.set_min(this.widgets.starttime.get_value());
		}
		else
		{
			this.widgets.stoptime.set_min(this.widgets.starttime.get_value());
			this.widgets.starttime.set_max(_widget.get_value());
		}
	}

	/**
	 * time picker button click handler
	 * @param _node
	 * @param _widget
	 * @private
	 */
	private _timePicker(_node, _widget)
	{
		if (_widget == this.widgets.starttime_picker)
		{
			this.widgets.starttime.set_value(Math.round(this.videobar.currentTime()));
		}
		else
		{
			this.widgets.stoptime.set_value(Math.round(this.videobar.currentTime()));
		}
	}
}
et2_register_widget(et2_smallpart_comment_timespan, ["smallpart-comment-timespan"]);
