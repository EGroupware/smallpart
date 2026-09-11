import {et2_widget} from "../../api/js/etemplate/et2_core_widget";
import {et2_implements_registry, implements_methods} from "../../api/js/etemplate/et2_core_interfaces";

/**
 * Data of a overlay element
 */
export interface OverlayElement {
	overlay_id? : number;
	course_id? : number;
	video_id : number;
	overlay_type : string;
	overlay_start : number;
	overlay_player_mode : PlayerMode;
	[propName: string]: any;
}
export enum PlayerMode {
	Unchanged,	// continue playing
	Pause,		// pause the video, if playing
	Disable,	// disable all player controls: start, stop, pause, seek
}

/**
 * Interface for an overlay elements managed by et2_widget_videooverlay
 *
 * Not tied to a single class hierarchy (et2_baseWidget) - overlay element plugins can be either
 * legacy widgets or real webcomponents (eg. et2_smallpart_overlay_html, a modern Et2Html
 * subclass) - both satisfy this shape via Et2Widget's own compat methods/`.options` getter.
 */
export interface et2_IOverlayElement
{
	id? : string;
	options : { overlay_id? : number, [propName : string] : any };
	getDOMNode(_sender? : et2_widget) : HTMLElement;
	destroy() : void;
	set_disabled(_disabled : boolean) : void;

	/**
	 * Callback called by parent if user eg. seeks the video to given time
	 *
	 * @param  _time new position of the video
	 * @return boolean true: elements wants to continue, false: element requests to be removed
	 */
	keepRunning(_time : number) : boolean;
}
export var et2_IOverlayElement = "et2_IOverlayElement";
et2_implements_registry.et2_IOverlayElement = function(obj : et2_widget)
{
	return implements_methods(obj, ["keepRunning"]);
}

/**
 * Interface for an overlay elements managed by et2_widget_videooverlay
 */
export interface et2_IOverlayElementEditor
{
	options : { overlay_id? : number, [propName : string] : any };
	getDOMNode(_sender? : et2_widget) : HTMLElement;
	destroy() : void;
	getValue() : any;
	set_value(_value : any) : void;
	set_offset?(_value : number) : void;
	onSaveCallback(_data, _onSuccessCallback);
}

export var et2_IOverlayElementEditor = "et2_IOverlayElementEditor";
et2_implements_registry.et2_IOverlayElementEditor = function(obj : et2_widget)
{
	return implements_methods(obj, ["onSaveCallback"]);
}

