import {et2_baseWidget} from "../../api/js/etemplate/et2_core_baseWidget";
import {et2_widget} from "../../api/js/etemplate/et2_core_widget";

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
 */
export interface et2_IOverlayElement extends et2_baseWidget
{
	/**
	 * Callback called by parent if user eg. seeks the video to given time
	 *
	 * @param number _time new position of the video
	 * @return boolean true: elements wants to continue, false: element requests to be removed
	 */
	keepRunning(_time : number) : boolean;
}
var et2_IOverlayElement = "et2_IOverlayElement";
function implements_et2_IOverlayElement(obj : et2_widget)
{
	return implements_methods(obj, ["keepRunning"]);
}
/**
 * Interface for an overlay elements managed by et2_widget_videooverlay
 */
export interface et2_IOverlayElementEditor extends et2_baseWidget
{
	onSaveCallback(_video_id, _starttime, _duration);
}

var et2_IOverlayElementEditor = "et2_IOverlayElementEditor";
function implements_et2_IOverlayElementEditor(obj : et2_widget)
{
	return implements_methods(obj, ["onSaveCallback"]);
}

