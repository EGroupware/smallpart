/**
 * EGroupware - SmallParT - app
 *
 * @link https://www.egroupware.org
 * @package smallpart
 * @subpackage Ui
 * @license http://opensource.org/licenses/gpl-license.php GPL - GNU General Public License
 */


/*egw:uses
	/api/js/jsapi/egw_app.js;
	/smallpart/js/et2_widget_videobar.js;
	/smallpart/js/et2_widget_videooverlay.js;
	/smallpart/js/et2_widget_videooverlay_slider_controller.js;
	/smallpart/js/et2_widget_livefeedback_slider_controller.js;
	/smallpart/js/et2_widget_videotime.js;
	/smallpart/js/et2_widget_comment.js;
	/smallpart/js/et2_widget_color_radiobox.js;
	/smallpart/js/et2_widget_filter_participants.js;
	/smallpart/js/mark_helpers.js;
	/smallpart/js/et2_widget_cl_measurement_L.js;
	/smallpart/js/et2_widget_attachments_list.js;
	/smallpart/js/et2_widget_video_controls.js;
	/smallpart/js/et2_widget_comment_timespan.js;
	/smallpart/js/et2_widget_timer.js;
	/smallpart/js/et2_widget_video_recorder.js;
	/smallpart/js/chart/chart.min.js;
 */

import {EgwApp, PushData} from "../../api/js/jsapi/egw_app";
import {et2_smallpart_videobar} from "./et2_widget_videobar";
import {MarkArea, CommentMarked, MarkWithArea, MarksWithArea} from "./mark_helpers";
import './et2_widget_videooverlay';
import './et2_widget_color_radiobox';
import './et2_widget_comment';
import './et2_widget_filter_participants';
import './et2_widget_attachments_list';
import './et2_widget_cl_measurement_L';
import './et2_widget_video_controls';
import './et2_widget_comment_timespan';
import {et2_grid} from "../../api/js/etemplate/et2_widget_grid";
import {et2_template} from "../../api/js/etemplate/et2_widget_template";
import {et2_textbox} from "../../api/js/etemplate/et2_widget_textbox";
import {et2_dialog} from "../../api/js/etemplate/et2_widget_dialog";
import {et2_selectbox} from "../../api/js/etemplate/et2_widget_selectbox";
import {et2_checkbox} from "../../api/js/etemplate/et2_widget_checkbox";
import {et2_createWidget, et2_widget} from "../../api/js/etemplate/et2_core_widget";
import {et2_button} from "../../api/js/etemplate/et2_widget_button";
import {et2_inputWidget} from "../../api/js/etemplate/et2_core_inputWidget";
import {et2_smallpart_videooverlay} from "./et2_widget_videooverlay";
import {et2_smallpart_comment} from "./et2_widget_comment";
import PseudoFunction = Sizzle.Selectors.PseudoFunction;
import {et2_taglist} from "../../api/js/etemplate/et2_widget_taglist";
import {et2_DOMWidget} from "../../api/js/etemplate/et2_core_DOMWidget";
import {et2_file} from "../../api/js/etemplate/et2_widget_file";
import {et2_video} from "../../api/js/etemplate/et2_widget_video";
import {et2_box, et2_details} from "../../api/js/etemplate/et2_widget_box";
import {et2_tabbox} from "../../api/js/etemplate/et2_widget_tabs";
import {et2_description} from "../../api/js/etemplate/et2_widget_description";
import {et2_smallpart_cl_measurement_L} from "./et2_widget_cl_measurement_L";
import {et2_countdown} from "../../api/js/etemplate/et2_widget_countdown";
import {et2_iframe} from "../../api/js/etemplate/et2_widget_iframe";
import {et2_smallpart_videooverlay_slider_controller} from "./et2_widget_videooverlay_slider_controller";
import {et2_smallpart_livefeedback_slider_controller} from "./et2_widget_livefeedback_slider_controller";
import {et2_smallpart_color_radiobox} from "./et2_widget_color_radiobox";
import {et2_widget_video_recorder} from "./et2_widget_video_recorder";

/**
 * Comment type and it's attributes
 */
export interface VideoType {
	course_id?	: number;
	video_id?	: number;
}
export interface CommentType extends VideoType {
	comment_id?       : number;
	course_id         : number;
	account_id?       : number;
	video_id          : number;
	comment_cat 	  : string;
	comment_starttime : number;
	comment_stoptime? : number;
	comment_color     : string;
	comment_deleted?  : number;
	comment_current?  : string;	// first comment from comment_added
	comment_added     : Array<string|number>;	// ["comment", [account_id1|"nick1", "comment1", ...]]
	comment_history?  : Array<string>;	// ["previous version", ...]
	comment_related_to? : number;		// retweet of given comment_id
	comment_info_alert? : any;
	comment_marked?   : Array<{x: number; y: number; c: string}>	// x, y 0-100, c: color eg. "ff0000"
	action?           : string;	// used to keep client editing state, not in database
	save_label?       : string; // label for save button: "Save and continue" or "Retweet and continue"
	filtered?		  : Array<string>; // array filters applied to the comment
	class?            : string; // class-name(s) for this comment: "commentOwner" and/or "commentMarked"
}
/**
 * Recording of watched videos
 */
export interface VideoWatched extends VideoType {
	course_id : number;
	video_id  : number;
	starttime : Date;
	endtime?  : Date;
	position : number;
	duration? : number;
	paused    : number;
}

/**
 * PHP DateTime JSON serialized
 */
export interface DateTime {
	date : string;
	timezone: string;
	timezone_type: number;
}

/**
 * Pushed course
 */
export interface CourseType {
	course_id : number;
	course_name: string;
	course_owner: number;
	course_org: number;
	course_closed: number;
	course_options: number;
	course_groups: number;
	video_labels: { [key: number]: string };
	videos: { [key: number]: {
		video_options: number;
		video_src: string;
		video_question: string;
		video_test_duration : number;
		video_test_options : number;
		video_test_display : number;
		video_published : number;
		video_published_start : null|DateTime;
		video_published_end : null|DateTime;
	}}
}

export class smallpartApp extends EgwApp
{
	static readonly appname = 'smallpart';
	static readonly default_color = 'ffffff';	// white = neutral
	static readonly commentRowsQuery = 'tr.row.commentBox';
	static readonly playControlBar = 'play_control_bar';
	/**
	 * Undisplayed properties of edited comment: comment_id, etc
	 */
	protected edited : CommentType;
	/**
	 * Currently displayed comments
	 */
	protected comments : Array<CommentType>;
	protected filter : VideoType;

	/**
	 * Active filter classes
	 */
	protected filters : {} = {};

	/**
	 * Set if student is watching a video
	 */
	protected watching : VideoWatched;
	/**
	 * Course options: &1 = record watched videos
	 */
	protected course_options : number = 0;

	/**
	 * Current user belongs to the course staff
	 */
	protected is_staff : "admin"|"teacher"|"tutor"|undefined;

	/**
	 * account_id of current user
	 */
	protected user : number;

	/**
	 * keep livefeedback running Interval ID
	 * @protected
	 */
	protected livefeedbackInterval : number = 0;

	/**
	 * Forbid students to comment
	 */
	static readonly COMMENTS_FORBIDDEN_BY_STUDENTS = 4;
	/**
	 * Show everything withing the group plus staff
	 */
	static readonly COMMENTS_GROUP = 6;
	/**
	 * Show comments within the group, but hide teachers
	 */
	static readonly COMMENTS_GROUP_HIDE_TEACHERS = 7;

	/**
	 * Post Cognitive Load Measurement Type
	 */
	static readonly CLM_TYPE_POST = 'post';

	/**
	 * Process Cognitive Load Measurement Type
	 */
	static readonly CLM_TYPE_PROCESS = 'process';

	/**
	 * stop time type for Cognitive Load Measurement
	 */
	static readonly CLM_TYPE_STOP = 'stop';

	/**
	 * stop time type for Cognitive Load Measurement
	 */
	static readonly CLM_TYPE_UNLOAD = 'unload';

	/**
	 * Learning ("L") response time type for Cognitive Load Measurement
	 */
	static readonly CLM_TYPE_LEARNING = 'learning';

	/**
	 * Constructor
	 *
	 * @memberOf app.status
	 */
	constructor()
	{
		// call parent
		super('smallpart');

		this.user = parseInt(this.egw.user('account_id'));
	}

	/**
	 * Destructor
	 */
	destroy(_app)
	{
		// call parent
		super.destroy(_app)
	}

	/**
	 * This function is called when the etemplate2 object is loaded
	 * and ready.  If you must store a reference to the et2 object,
	 * make sure to clean it up in destroy().
	 *
	 * @param {etemplate2} _et2 newly ready object
	 * @param {string} _name template name
	 */
	et2_ready(_et2, _name)
	{
		// call parent
		super.et2_ready(_et2, _name);
		const content = this.et2.getArrayMgr('content');
		switch(true)
		{
			case (_name.match(/smallpart.student.index/) !== null):

				this.is_staff = content.getEntry('is_staff');
				this.comments = <Array<CommentType>>content.getEntry('comments');
				this._student_setCommentArea(false);

				// don't go further if the test locked screen is on or no video's selected yet, otherwise we would get
				// js errors on widget selections as they're not there yet.
				if (content.getEntry('locked') || !content.getEntry('videos') || !content.getEntry('video')) break;

				const inTestMode = parseInt(content.getEntry('video')?.video_test_duration) > 0 && content.getEntry('timer') > 0;
				const forbidTocomment = content.getEntry('video')?.video_options == smallpartApp.COMMENTS_FORBIDDEN_BY_STUDENTS;
				if (forbidTocomment)
				{
					this.et2.setDisabledById('add_comment', true);
					this.et2.setDisabledById('add_note', false);
				}

				if ((content.getEntry('course_options') & et2_smallpart_videobar.course_options_cognitive_load_measurement)
						== et2_smallpart_videobar.course_options_cognitive_load_measurement && inTestMode)
				{
					if (content.getEntry('clm')['dual']['active'])
					{
						if (forbidTocomment)
						{
							this.student_addNote();
						}
						this._student_clm_l_start();
					}
					else if(content.getEntry('clm')['process']['active'])
					{
						this._student_setProcessCLQuestions();
					}
				}
				else
				{
					let clml = this.et2.getDOMWidgetById('clm-l');
					//disable "L" if we are not in CLM mode/test mode
					if (clml) clml.set_disabled(true);
				}
				// set process CL Questionnaire when the test is running
				if (inTestMode)
				{
					this._student_noneTestAreaMasking(true);
				}
				this.filter = {
					course_id: parseInt(<string>content.getEntry('courses')) || null,
					video_id:  parseInt(<string>content.getEntry('videos')) || null
				}
				if (this.egw.preference('comments_column_state', 'smallpart') == 0 || !this.egw.preference('comments_column_state', 'smallpart'))
				{
					this.egw.set_preference('smallpart', 'comments_column_state', 0);
					this.et2.getDOMWidgetById('comments_column')?.set_value(true);
					this.et2.getDOMWidgetById('comments')?.set_class('hide_column');
				}
				else
				{
					this.et2.getDOMWidgetById('comments_column')?.set_value(false);
					this.et2.getDOMWidgetById('comments')?.getDOMNode().classList.remove('hide_column');
				}
				this.course_options = parseInt(<string>content.getEntry('course_options')) || 0;
				this._student_setFilterParticipantsOptions();
				let self = this;
				jQuery(window).on('resize', function(){
					self._student_resize();
				});
				// record, in case of F5 or window closed
				window.addEventListener("beforeunload", function() {
					self.set_video_position();
					self.record_watched();
					// record unload time, if a CL measurement test is running, in case user did not stop it properly
					if (parseInt(content.getEntry('video')?.video_test_duration)>0 && content.getEntry('timer')>0 &&
						(content.getEntry('course_options') & et2_smallpart_videobar.course_options_cognitive_load_measurement)
							== et2_smallpart_videobar.course_options_cognitive_load_measurement)
					{
						self.egw.json('smallpart.\\EGroupware\\SmallParT\\Student\\Ui.ajax_recordCLMeasurement', [
							content.getEntry('video')?.course_id, content.getEntry('video')?.video_id,
							smallpartApp.CLM_TYPE_UNLOAD, []
						]).sendRequest('keepalive');
					}
				});
				// video might not be loaded due to test having to be started first
				const voloff = this.et2.getWidgetById('voloff');
				if (voloff) voloff.getDOMNode().style.opacity = '0.5';
				const videobar = this.et2.getWidgetById('video');
				if (videobar)
				{
					videobar.video[0].addEventListener('et2_video.onReady.'+videobar.id, _ => {
						this.et2.getWidgetById('volume').set_value('50');
						videobar.set_volume(50);
					});
					const notSeekable = videobar.getArrayMgr('content').getEntry('video')?.video_test_options & et2_smallpart_videobar.video_test_option_not_seekable;
					['forward', 'backward', 'playback', 'playback_slow', 'playback_fast'].forEach(_item=>{
						this.et2.getDOMWidgetById(_item).set_disabled(notSeekable);
					});
				}
				if (this.is_staff) this.et2.getDOMWidgetById('activeParticipantsFilter')?.getDOMNode()?.style?.width = "70%";
				this.et2.getDOMWidgetById(smallpartApp.playControlBar).iterateOver(_w=>{

					if (content.data.video?.video_type.match(/pdf/) && _w && _w.id != '' && typeof _w.set_disabled == 'function')
					{
						switch (_w.id)
						{
							case 'play_control_bar':
							case 'add_comment':
							case 'fullwidth':
							case 'pgnxt':
							case 'pgprv':
								_w.set_disabled(false);
								break;
							case 'volume':
								_w.set_disabled(true);
								break;
							default:
								_w.getDOMNode().style.visibility = 'hidden';
						}
					}
					console.log(_w)
				},this);

				this.setCommentsSlider(this.comments);
				if (content.data.video.livefeedback)
				{
					if (content.data.video.livefeedback_session !='ended')
					{
						this.student_livefeedbackSession();
					}
					else
					{
						this.student_livefeedbackReport();
					}

				}
				break;

			case (_name === 'smallpart.question'):
				if (content.getEntry('max_answers'))
				{
					this.et2.getWidgetById('answers').iterateOver(function(_widget : et2_widget)
					{
						if (_widget.id === '1[checked]' || _widget.id === '1[correct]')
						{
							this.checkMaxAnswers(undefined, <et2_checkbox>_widget, undefined);
						}
					}, this, et2_checkbox);
				}
				this.defaultPoints();
				let vdh = this.et2.getWidgetById("video_data_helper");
				vdh.video[0].addEventListener("et2_video.onReady."+vdh.id, _ =>{this.questionTime();});
				// set markings for mark or mill-out questions
				this.setMarkings();
				break;

			case (_name === 'smallpart.course'):
				// disable import button until a file is selected
				const import_button : et2_button = <et2_button>this.et2.getWidgetById('button[import]');
				import_button?.set_readonly(true);
				(<et2_file>this.et2.getWidgetById('import')).options.onFinish = function(_ev, _count) {
					import_button.set_readonly(!_count);
				};
				// seem because set_value of the grid, we need to defer after, to work for updates/apply too
				window.setTimeout(() => this.disableGroupByRole(), 0);
				this.course_enableCLMTab(null, this.et2.getDOMWidgetById('cognitive_load_measurement'));
				break;

			case (_name === 'smallpart.lti-content-selection'):
				const video_id = <et2_selectbox>this.et2.getWidgetById('video_id');
				if (video_id.getValue())
				{
					this.ltiVideoSelection(undefined, video_id);
				}
				break;
		}
	}

	/**
	 * Observer method receives update notifications from all applications
	 *
	 * App is responsible for only reacting to "messages" it is interested in!
	 *
	 * @param {string} _msg message (already translated) to show, eg. 'Entry deleted'
	 * @param {string} _app application name
	 * @param {(string|number)} _id id of entry to refresh or null
	 * @param {string} _type either 'update', 'edit', 'delete', 'add' or null
	 * - update: request just modified data from given rows.  Sorting is not considered,
	 *		so if the sort field is changed, the row will not be moved.
	 * - edit: rows changed, but sorting may be affected.  Requires full reload.
	 * - delete: just delete the given rows clientside (no server interaction neccessary)
	 * - add: requires full reload for proper sorting
	 * @param {string} _msg_type 'error', 'warning' or 'success' (default)
	 * @param {object|null} _links app => array of ids of linked entries
	 * or null, if not triggered on server-side, which adds that info
	 * @return {boolean|*} false to stop regular refresh, thought all observers are run
	 */
	observer(_msg, _app, _id, _type, _msg_type, _links) : boolean|any
	{
		if (_app === 'smallpart-overlay')
		{
			let overlay = <et2_smallpart_videooverlay>this.et2.getWidgetById('videooverlay');
			if (overlay)
			{
				overlay.renderElements(_id);
				return false;
			}
		}
	}

	/**
	 * Handle a push notification about entry changes from the websocket
	 *
	 * Get's called for data of all apps, but should only handle data of apps it displays,
	 * which is by default only it's own, but can be for multiple apps eg. for calendar.
	 *
	 * @param  pushData
	 * @param {string} pushData.app application name
	 * @param {(string|number)} pushData.id id of entry to refresh or null
	 * @param {string} pushData.type either 'update', 'edit', 'delete', 'add' or null
	 * - update: request just modified data from given rows.  Sorting is not considered,
	 *		so if the sort field is changed, the row will not be moved.
	 * - edit: rows changed, but sorting may be affected.  Requires full reload.
	 * - delete: just delete the given rows clientside (no server interaction neccessary)
	 * - add: requires full reload for proper sorting
	 * @param {object|null} pushData.acl Extra data for determining relevance.  eg: owner or responsible to decide if update is necessary
	 * @param {number} pushData.account_id User that caused the notification
	 */
	push(pushData : PushData)
	{
		// don't care about other apps data, reimplement if your app does care eg. calendar
		if (pushData.app !== this.appname) return;

		// check if a comment is pushed
		if (typeof pushData.id === 'string' && pushData.id.match(/^\d+:\d+:\d+$/))
		{
			this.pushComment(pushData.id, pushData.type, pushData.acl, pushData.account_id);
		}
		// check if a participant-update is pushed
		else if (typeof pushData.id === 'string' && pushData.id.match(/^\d+:P$/))
		{
			this.pushParticipants(pushData.id, pushData.type, pushData.acl);
		}
		// check if course-update is pushed
		else if (typeof pushData.id === 'number')
		{
			// update watched video / student UI
			if (pushData.id == this.student_getFilter().course_id &&
				(Object.keys(pushData.acl).length || pushData.type === 'delete'))
			{
				this.pushCourse(pushData.id, pushData.type, pushData.acl);
			}
			// call parent to handle course-list
			return super.push(pushData);
		}
		else if(typeof pushData.id === 'string' && pushData.acl['data']['lf_id']
			&& pushData.acl['moderator'] != egw.user('account_id'))
		{
			this.pushLivefeedback(pushData);
		}
	}

	/**
	 * Add or update pushed participants (we're currently not pushing deletes)
	 *
	 * @param course_id
	 * @param type currently only "update"
	 * @param course undefined for type==="delete"
	 */
	pushCourse(course_id: number, type : string, course: CourseType|undefined)
	{
		const filter = this.student_getFilter();
		const course_selection = <et2_selectbox>this.et2.getWidgetById('courses');

		// if course got closed (for students) --> go to manage courses
		if ((course.course_closed == 1 || type === 'delete' || !Object.keys(course).length))
		{
			course_selection.change(course_selection.getDOMNode(), course_selection, 'manage');
			console.log('unselecting no longer accessible or deleted course');
			return;
		}

		const sel_options = this.et2.getArrayMgr('sel_options');

		// update course-name, if changed
		let courses = sel_options.getEntry('courses');
		for(let n in courses)
		{
			if (courses[n].value == course_id)
			{
				courses[n].label = course.course_name;
				course_selection.set_select_options(courses);
				break;
			}
		}

		// update video-names
		const video_selection = <et2_selectbox>this.et2.getWidgetById('videos');
		video_selection?.set_select_options(course.video_labels);

		// currently watched video no longer exist / accessible --> select no video (causing submit to server)
		if (video_selection && typeof course.videos[filter.video_id] === 'undefined')
		{
			video_selection.change(video_selection.getDOMNode(), video_selection, '');
			console.log('unselecting no longer accessible or deleted video');
			return;
		}

		// update currently watched video
		const video = course.videos[filter.video_id];
		const task = <et2_description>this.et2.getWidgetById('video[video_question]');
		task.set_value(video.video_question);
		(<et2_details>task.getParent()).set_statustext(video.video_question);

		// video.video_options or _published* changed --> reload
		const content = this.et2.getArrayMgr('content');
		const old_video = content.getEntry('video');
		if (video.video_options != old_video.video_options ||
			video.video_published != old_video.video_published ||
			video.video_published_start?.date != old_video?.video_published_start?.date ||
			video.video_published_end?.date != old_video?.video_published_end?.date)
		{
			video_selection?.change(video_selection.getDOMNode(), video_selection, undefined);
			console.log('reloading as video_options/_published changed', old_video, video);
			return;
		}

		// add video_test_* (and all other video attributes) to content, so we use them from there
		Object.assign((<any>content.data).video, video);

		// course-options: &1 = record watched, &2 = display watermark
		this.course_options = course.course_options;

		// update groups
		const group = <et2_selectbox>this.et2.getWidgetById('group');
		let group_options = Object.values(this.et2.getArrayMgr('sel_options').getEntry('group') || {}).slice(-2);
		for(let g=1; g <= course.course_groups; ++g)
		{
			group_options.splice(g-1, 0, {value: g, label: this.egw.lang('Group %1', g)});
		}
		group?.set_select_options(group_options);
	}


	/**
	 * Add or update pushed participants (we're currently not pushing deletes)
	 *
	 * @param id "course_id:P"
	 * @param type "add" or "update"
	 * @param participants
	 */
	pushParticipants(id: string, type : string, participants : Array<object>)
	{
		const course_id = id.split(':').shift();
		const sel_options = this.et2.getArrayMgr('sel_options');

		if (this.student_getFilter().course_id != course_id || typeof sel_options === 'undefined')
		{
			return;
		}
		// check if current user is no longer subscribed / has been kicked from the course
		if (type === 'unsubscribe')
		{
			participants.forEach((participant : any) =>
			{
				if (participant.account_id == this.user)
				{
					const course_selection = <et2_selectbox>this.et2.getWidgetById('courses');
					course_selection.change(course_selection.getDOMNode(), course_selection, 'manage');
					console.log('unselecting no longer accessible course');
					return;
				}
			});
			return;
		}
		let account_ids = sel_options.getEntry('account_id');
		const group = account_ids.filter(account => account.value == this.user)[0].group;
		const video = this.et2.getArrayMgr('content').getEntry('video');
		let need_comment_update = false;
		participants.forEach((participant : any) =>
		{
			for (let key in account_ids)
			{
				if (account_ids[key].value == participant.value)
				{
					// current user is a student AND group of an other student changed AND
					if (!need_comment_update && !this.is_staff && participant.group !== account_ids[key].group &&
						// groups matter for this video AND
						(video.video_options == smallpartApp.COMMENTS_GROUP ||
							video.video_options == smallpartApp.COMMENTS_GROUP_HIDE_TEACHERS) &&
						// AND own student-group is affected (student added or removed from current group, other groups wont matter)
						(group == account_ids[key].group || group == participant.group))
					{
						// --> update comments
						need_comment_update = true;
					}
					account_ids[key] = participant;
					return;
				}
			}
			account_ids.push(participant);
		});
		// ArrayMgr seems to have no update method
		(<any>sel_options.data).account_id = account_ids;

		// do we need to update the comments (because student changed group)
		if (need_comment_update)
		{
			this.egw.json('smallpart.\\EGroupware\\SmallParT\\Student\\Ui.ajax_listComments', [
				this.student_getFilter()
			]).sendRequest();
		}
		// or just refresh them to show modified names (no need for new participants without comments yet)
		else if (type !== 'add')
		{
			this.student_updateComments({content: this.comments});
			this._student_setFilterParticipantsOptions();
		}
	}

	/**
	 * Add or update pushed comment
	 *
	 * @param id "course_id:video_id:comment_id"
	 * @param type "add", "update" or "delete"
	 * @param comment undefined for type==='delete'
	 */
	pushComment(id: string, type : string, comment : CommentType|undefined, account_id : number)
	{
		let course_id, video_id, comment_id;
		[course_id, video_id, comment_id] = id.split(':');

		// show message only for re-tweets / edits on own comments
		if (account_id != this.user && comment.account_id == this.user)
		{
			switch (type)
			{
				case 'add':
					this.egw.link_title('api-accounts', account_id, (_nick) => {
						this.egw.message(this.egw.lang('%1 commented on %2: %3 at %4',
								_nick, (<any>comment).course_name, (<any>comment).video_name, this.timeFormat(comment.comment_starttime)) +
							"\n\n" + comment.comment_added[0])
					});
					break;
				case 'edit':
					this.egw.link_title('api-accounts', account_id, (_nick) => {
						this.egw.message(this.egw.lang('%1 edited on %2: %3 at %4',
								_nick, (<any>comment).course_name, (<any>comment).video_name, this.timeFormat(comment.comment_starttime)) +
							"\n\n" + comment.comment_added[0])
					});
					break;
				case 'retweet':
					this.egw.link_title('api-accounts', account_id, (_nick) => {
						this.egw.message(this.egw.lang('%1 retweeted on %2: %3 at %4 to',
								_nick, (<any>comment).course_name, (<any>comment).video_name, this.timeFormat(comment.comment_starttime)) +
							"\n\n" + comment.comment_added[0]+"\n\n" + comment.comment_added[comment.comment_added.length-1])
					});
					break;
			}
		}

		// if we show student UI the comment belongs to, update comments with it
		if (this.et2.getInstanceManager().name.match(/smallpart.student.index/) !== null &&
			this.student_getFilter().course_id == course_id && this.student_getFilter().video_id  == video_id)
		{
			this.addCommentClass(comment);

			// integrate pushed comment in own data and add/update it there
			if (this.comments.length > 1)
			{
				for (let n = 0; n < this.comments.length; ++n)
				{
					if (!this.comments[n] || this.comments[n].length == 0) continue;
					const comment_n = this.comments[n];
					if (type === 'add' && comment_n.comment_starttime > comment.comment_starttime)
					{
						this.comments.splice(n, 0, comment);
						break;
					}
					if (type === 'add' && n == this.comments.length - 1)
					{
						this.comments.push(comment);
						break;
					}
					if (type !== 'add' && comment_n.comment_id == comment.comment_id)
					{
						if (type === 'delete')
						{
							this.comments.splice(n, 1);
						}
						else
						{
							// with limited visibility of comments eg. student can see other students teacher updating
							// their posts would remove retweets --> keep them
							if (comment.comment_added.length === 1 && this.comments[n].comment_added.length > 1)
							{
								comment.comment_added.push(...this.comments[n].comment_added.slice(1));
							}
							this.comments[n] = comment;
						}
						break;
					}
				}
			}
			else if (type === 'add')
			{
				this.comments.push(comment);
			}
		}
		this.student_updateComments({content: this.comments});
	}

	/**
	 * Client-side equivalent of Student\Ui::_fixComments()
	 *
	 * @param comment
	 * @protected
	 */
	protected addCommentClass(comment : CommentType)
	{
		// add class(es) regular added on server-side
		if (this.is_staff || comment.account_id == this.user)
		{
			comment.class = 'commentOwner';
		}
		else
		{
			comment.class = '';
		}
		if (comment.comment_marked && comment.comment_marked.length)
		{
			comment.class += ' commentMarked';
		}
	}

	/**
	 * Format time in seconds as 0:00 or 0:00:00
	 *
	 * @param secs
	 * @return string
	 */
	protected timeFormat(secs : number) : string
	{
		if (secs < 3600)
		{
			return sprintf('%d:%02d', secs / 60, secs % 60);
		}
		return sprintf('%d:%02d:%02d', secs / 3600, (secs % 3600)/60, secs % 60);
	}

	_student_resize()
	{
		let comments = this.et2?.getWidgetById('comments')?.getDOMNode();
		jQuery(comments).height(jQuery(comments).height()+
		jQuery('form[id^="smallpart-student-index"]').height()
		- jQuery('.rightBoxArea').height() - 40
		);
	}

	student_saveAndCloseCollabora ()
	{
		const content = this.et2.getArrayMgr('content');
		const clml = <et2_smallpart_cl_measurement_L>this.et2.getDOMWidgetById('clm-l');
		const inTestMode = parseInt(content.getEntry('video')?.video_test_duration) > 0 && content.getEntry('timer') > 0;

		if ((content.getEntry('course_options') & et2_smallpart_videobar.course_options_cognitive_load_measurement)
			== et2_smallpart_videobar.course_options_cognitive_load_measurement &&
			inTestMode && clml?.get_mode() === et2_smallpart_cl_measurement_L.MODE_CALIBRATION)
		{
			return;
		}

		document.getElementsByClassName('note_container')[0].style.display = 'none';
		document.querySelector('iframe[id$="_note"]').contentWindow.app.collabora.WOPIPostMessage('Action_Save');
	}

	/**
	 * Click callback called on comments slidebar
	 * @param _node
	 * @param _widget
	 *
	 * @return boolean return false when there's an unanswered question
	 * @private
	 */
	public student_commentsSlider_callback(_node, _widget)
	{
		let id = _widget.id.split('slider-tag-')[1];
		let data = this.comments.filter(function(e){if (e.comment_id == id) return e;})
		if (data[0] && data[0].comment_id)
		{
			this.student_openComment({id:'open'}, [{data:data[0]}], true);
		}
		return true;
	}

	/**
	 * Comment edit button handler
	 * @param _action
	 * @param _comment_id
	 */
	student_editCommentBtn(_action, _comment_id)
	{
		let selected = this.comments.filter(_item=>{return _item.comment_id == _comment_id;});
		this.student_openComment(_action, [{data:selected[0]}]);
	}

	/**
	 * Opend a comment for editing
	 *
	 * @param _action
	 * @param _selected
	 * @param _noHighlight
	 */
	student_openComment(_action, _selected, _noHighlight?)
	{
		if (!isNaN(_selected)) _selected = [{data: this.comments[_selected]}];
		this.edited = jQuery.extend({}, _selected[0].data);
		this.edited.action = _action.id;
		let videobar = <et2_smallpart_videobar>this.et2.getWidgetById('video');
		const comments_slider = <et2_smallpart_videooverlay_slider_controller>this.et2.getDOMWidgetById('comments_slider');
		const videooverlay = <et2_smallpart_videooverlay>this.et2.getDOMWidgetById('videooverlay');
		let comment = <et2_grid>this.et2.getWidgetById('comment');
		let self = this;
		let content = videobar.getArrayMgr('content').data;

		// Do not seek for comments when we are in not seekable
		if (_action.id == 'open' && !content.is_staff && (content.video.video_test_options
			& et2_smallpart_videobar.video_test_option_not_seekable)) return;

		this.et2.getWidgetById(smallpartApp.playControlBar).set_disabled(_action.id !== 'open');

		// record in case we're playing
		this.record_watched();
		videobar.seek_video(this.edited.comment_starttime);
		// start recording again, in case we're playing
		if (!videobar.paused()) this.start_watching();
		videobar.set_marking_enabled(true, function(){
			self._student_controlCommentAreaButtons(false);
		});
		videobar.setMarks(this.edited.comment_marked);
		videobar.setMarksState(true);
		videobar.setMarkingMask(true);
		this.student_playVideo(true);
		this._student_setCommentArea(true);
		if (comment)
		{
			this.edited.save_label = this.egw.lang('Save');
			// readonly --> disable edit and retweet
			if (this.et2.getArrayMgr('content').getEntry('video').accessible === 'readonly')
			{
				_action.id = 'open';
			}
			switch (_action.id)
			{
				case 'retweet':
					this.edited.save_label = this.egw.lang('Retweet');
					// fall through
				case 'edit':
					if (_action.id == 'edit') videobar.set_marking_readonly(false);
					this.edited.video_duration = videobar.duration();
					this.edited.attachments_list = this.edited['/apps/smallpart/'
					+this.edited.course_id+'/'+this.edited.video_id+'/'+this.edited.account_lid
					+'/comments/'+this.edited.comment_id+'/'];

					comment.set_value({content: this.edited});
					comments_slider?.disableCallback(true);
					videooverlay.getElementSlider().disableCallback(true);
					break;

				case 'open':
					this.et2.getWidgetById('hideMaskPlayArea').set_disabled(false);
					document.getElementsByClassName('markingMask')[0].classList.remove('maskOn')
					comment.set_value({content:{
						comment_id: this.edited.comment_id,
						comment_added: this.edited.comment_added,
						comment_starttime: this.edited.comment_starttime,
						comment_stoptime: this.edited.comment_stoptime,
						comment_marked_message: this.color2Label(this.edited.comment_color),
						comment_marked_color: 'commentColor'+this.edited.comment_color,
						action: _action.id,
						video_duration: videobar.duration()
					}});
					this.et2.getWidgetById('comment_editBtn').set_disabled(!(this.is_staff || this.edited.account_id == egw.user('account_id')));
					if (comments_slider)
					{
						comments_slider.disableCallback(false);
						videooverlay.getElementSlider().disableCallback(false);
						const tag = comments_slider._children.filter(_item=>{
							return _item.id === 'slider-tag-'+self.edited.comment_id;
						});
						comments_slider.set_selected(tag.length>0?tag[0]:null);
					}
			}
			this.et2.setDisabledById('comment_timespan', !this.is_staff);

			if (!_noHighlight)
			{
				this._student_highlightSelectedComment(this.edited.comment_id);
			}
			else
			{
				this.et2.getWidgetById('comments').getDOMNode().querySelectorAll(smallpartApp.commentRowsQuery).forEach(_item=>{
					_item.classList.remove('highlight');
				});

			}
		}
		this._student_controlCommentAreaButtons(true);
	}

	/**
	 * Get a label for the used colors: Neutral (white), Positiv (green), Negative (red)
	 *
	 * @param _color
	 * @return string
	 */
	private color2Label(_color : string) : string
	{
		switch(_color)
		{
			case 'ffffff':
				return this.egw.lang('White');
			case '00ff00':
				return this.egw.lang('Green');
			case 'ff0000':
				return this.egw.lang('Red');
			case 'ffff00':
				return this.egw.lang('Yellow');
		}
	}
	/**
	 * set up post_cl_questions dialog
	 * @private
	 */
	private _student_setPostCLQuestions(_callback)
	{
		const content = this.et2.getArrayMgr('content');

		// only run this if we are in CLM mode and Post is active
		if ((content.getEntry('course_options') & et2_smallpart_videobar.course_options_cognitive_load_measurement)
			!= et2_smallpart_videobar.course_options_cognitive_load_measurement || !content.getEntry('clm')['post']['active']) return;

		let dialog = () =>
		{
			let post = content.getEntry('clm')['post'];
			if (typeof post.questions === 'object')
			{
				post.questions = Object.values(post.questions);
				// first index is reserved for grid and question index starts from 1
				if (post.questions[0]['q']) post.questions.unshift({});
			}
			return et2_createWidget("dialog", {
				callback: _callback,
				buttons: [
					{text: this.egw.lang("Continue"), id: "continue"},
				],
				title: '',
				message: '',
				icon: et2_dialog.QUESTION_MESSAGE,
				value:{content:post},
				closeOnEscape: false,
				dialogClass: 'questionnaire',
				width: 500,
				template: egw.webserverUrl+'/smallpart/templates/default/post_cl_questions.xet'
			}, et2_dialog._create_parent('smallpart'));
		};
		dialog();
	}

	public student_CLM_L(mode)
	{
		//disable CLML feature for now.
		const clml = <et2_smallpart_cl_measurement_L>this.et2.getDOMWidgetById('clm-l');
		clml.set_mode(mode);
		return clml.start();
	}

	public student_testFinished(_widget)
	{
		const content = this.et2.getArrayMgr('content');
		const widget = _widget;
		let self = this;
		const callback = (_w) => {
			(<et2_smallpart_cl_measurement_L>self.et2.getDOMWidgetById('clm-l')).stop();
			self._student_noneTestAreaMasking(false);
			if ((content.getEntry('course_options') & et2_smallpart_videobar.course_options_cognitive_load_measurement)
				== et2_smallpart_videobar.course_options_cognitive_load_measurement && content.getEntry('clm')['post']['active'])
			{
				// record a stop time once before post questions and after user decided to finish the test
				self.egw.json('smallpart.\\EGroupware\\SmallParT\\Student\\Ui.ajax_recordCLMeasurement', [
					content.getEntry('video')?.course_id, content.getEntry('video')?.video_id,
					smallpartApp.CLM_TYPE_STOP, []
				]).sendRequest();

				let timer = self.et2.getDOMWidgetById('timer');
				// reset the alarms after the test is finished
				timer.options.alarm = [];

				this._student_setPostCLQuestions(function (_button, _value) {
					if (_button === "continue" && _value) {
						self.egw.json('smallpart.\\EGroupware\\SmallParT\\Student\\Ui.ajax_recordCLMeasurement', [
							content.getEntry('video')?.course_id, content.getEntry('video')?.video_id,
							smallpartApp.CLM_TYPE_POST, _value
						]).sendRequest();
					}
					_w.getRoot().getInstanceManager().submit(_w.id);
				});
			} else {
				_w.getRoot().getInstanceManager().submit(_w.id);
			}
		}
		switch (_widget.id)
		{
			case 'stop':
				et2_dialog.show_dialog((_button)=>{
					if (_button == et2_dialog.YES_BUTTON)
					{
						callback(_widget);
					}
				},this.egw.lang('If you finish the test, you will not be able to enter it again!'), this.egw.lang('Finish test?'));
				break;
			case 'timer':
				callback(_widget);
				break;
		}
	}

	/**
	 * set up process_cl_questions dialog
	 * @private
	 */
	private _student_setProcessCLQuestions()
	{
		let content = this.et2.getArrayMgr('content');
		let alarms = [];

		// only run this if we are in CLM mode and process is active
		if ((content.getEntry('course_options') & et2_smallpart_videobar.course_options_cognitive_load_measurement)
			!= et2_smallpart_videobar.course_options_cognitive_load_measurement || !content.getEntry('clm')['process']['active']) return;

		let self = this;
		const video_test_duration = parseInt(content.getEntry('video')?.video_test_duration)*60;
		const repeat = content.data['clm']['process']['interval'] ? video_test_duration / (content.data['clm']['process']['interval'] * 60)
			: video_test_duration / 600;

		// keeps the reply timeout id
		let replyTimeout = null;

		for (let i=1;i<repeat;i++) {
			let value = i * Math.floor(video_test_duration/repeat);
			alarms[value] = value;
		}
		const timer = this.et2.getDOMWidgetById('timer');

		// make sure timer is there before accessing it. the widget might not be present in some cases, eg. before test
		// get started.
		if (timer)
		{
			timer.options.alarm = alarms;
			// callback to be called for alarm
			timer.onAlarm = () => {
				let d = dialog();
				replyTimeout = setTimeout(function(){
					this.div.parent().find('.ui-dialog-buttonpane').find('button').click();
				}.bind(d), (content.data['clm']['process']['duration'] ? content.data['clm']['process']['duration'] : 60)*1000);
			};
		}

		let dialog = () =>
		{
			// do not trigger a pause action when the comment editor is open
			if(!this.edited) this.student_playVideo(true);

			let questions = content.getEntry('clm')['process']['questions'];
			if (typeof questions === 'object')
			{
				questions = Object.values(questions);
				// first index is reserved for grid and question index starts from 1
				if (questions[0]['q']) questions.unshift({});
			}
			return et2_createWidget("dialog", {
				callback: function (_button, _value) {
					if (_button === "continue" && _value) {
						self.egw.json('smallpart.\\EGroupware\\SmallParT\\Student\\Ui.ajax_recordCLMeasurement', [
							content.getEntry('video')?.course_id, content.getEntry('video')?.video_id,
							smallpartApp.CLM_TYPE_PROCESS, _value
						]).sendRequest();
						clearTimeout(replyTimeout);
					}
				},
				buttons: [{text: this.egw.lang("Continue"), id: "continue"}],
				title: '',
				message: '',
				icon: et2_dialog.QUESTION_MESSAGE,
				value:{content:{questions: questions}},
				closeOnEscape: false,
				dialogClass: 'questionnaire clm-process',
				width: 400,
				template: egw.webserverUrl+'/smallpart/templates/default/process_cl_questions.xet'
			}, et2_dialog._create_parent('smallpart'));
		};
	}

	private _student_clm_l_start()
	{
		const clml =  <et2_smallpart_cl_measurement_L>this.et2.getDOMWidgetById('clm-l');
		const timer = <et2_countdown>this.et2.getDOMWidgetById('timer');

		const content = this.et2.getArrayMgr('content');
		const self = this;
		if (content.getEntry('video')?.video_options == smallpartApp.COMMENTS_FORBIDDEN_BY_STUDENTS)
		{
			clml.set_steps_className(clml.get_steps_className()+',note_container');
		}
		clml.checkCalibration().then(
			_=> // calibration is already done once
			{
				this._student_setProcessCLQuestions();
				// run the CLM "L" in running mode
				this.student_CLM_L('running');
			},
			_=> //calibration process
			{
				// reset the timer
				clearInterval(timer.timer);

				document.getElementsByClassName('timerBox')[0].style.display = 'none';
				document.querySelector('form[id^="smallpart-student-"]').style.visibility = 'hidden';
				document.getElementsByClassName('commentBoxArea')[0].style.display = 'block';
				et2_createWidget("dialog", {
					callback: function () {
						document.querySelector('form[id^="smallpart-student-"]').style.visibility = '';
						// start the CLM "L" calibration process
						self.student_CLM_L(et2_smallpart_cl_measurement_L.MODE_CALIBRATION).then(_ => {
							// set the timer again
							timer.set_value(content.getEntry('timer'));
							if (!content.getEntry('comments') || content.getEntry('comments').length<=1)
							{
								document.getElementsByClassName('commentBoxArea')[0].style.display = 'none';
							}
							document.getElementsByClassName('timerBox')[0].style.display = 'block';
							self._student_setProcessCLQuestions();
							// run the CLM "L" in running mode
							self.student_CLM_L('running');
						});
					},
					buttons: et2_dialog.BUTTONS_OK,
					title: this.egw.lang('Measurement by dual task (Calibration and measurement of cognitive load)'),
					icon: et2_dialog.QUESTION_MESSAGE,
					value: {content: {value: ''}},
					closeOnEscape: false,
					width: 400,
					dialogClass: 'questionnaire',
					template: egw.webserverUrl + '/smallpart/templates/default/clm_L_calibration_message.xet'
				}, et2_dialog._create_parent('smallpart'));
			}
		);
	}

	private _student_noneTestAreaMasking(state)
	{
		// don't do the masking area if the user has rights
		if (this.is_staff) return;

		['#egw_fw_header', '#egw_fw_sidebar',
			'.egw_fw_ui_tabs_header', '#egw_fw_sidebar_r',
			'.video_list'].forEach(_query => {
			const node =  <HTMLElement>document.querySelector(_query);
			if (node)
			{
				node.style.filter = (state?'blur(2px)':'');
				node.style.pointerEvents = (state?'none':'');
			}
		});
	}

	private _student_setCommentArea(_state)
	{
		try {
			this.et2.setDisabledById('add_comment', _state);
			this.et2.setDisabledById('smallpart.student.comment', !_state);
			this.et2.setDisabledById('hideMaskPlayArea', true);
			this._student_resize();
		}
		catch (e) {}
	}
	public student_playControl(_status: string)
	{
		let videobar = <et2_smallpart_videobar>this.et2.getWidgetById('video');
		let volume = <et2_smallpart_videobar>this.et2.getWidgetById('volume');
		let playback = <et2_smallpart_videobar>this.et2.getWidgetById('playback');
		let videooverlay = <et2_smallpart_videooverlay>this.et2.getWidgetById('videooverlay');
		if (_status && _status._type === 'select')
		{
			videobar.set_playBackRate(parseFloat(_status.getValue()));
			return;
		}

		switch (_status)
		{
			case "playback_fast":

				if (playback.getDOMNode().selectedIndex < playback.getDOMNode().options.length-1)
				{
					playback.getDOMNode().selectedIndex++;
					playback.set_value(playback.getDOMNode().selectedOptions[0].value);
				}
				break;
			case "playback_slow":

				if (playback.getDOMNode().selectedIndex > 0)
				{
					playback.getDOMNode().selectedIndex--;
					playback.set_value(playback.getDOMNode().selectedOptions[0].value);
				}
				break;
			case "forward":
				if (videobar.currentTime()+10 <= videobar.duration())
				{
					videobar.seek_video(videobar.currentTime()+10);
					videooverlay._elementSlider.set_seek_position(Math.round(videobar._vtimeToSliderPosition(videobar.currentTime())));
				}
				break;
			case "backward":
				if (videobar.currentTime()-10 >= 0)
				{
					videobar.seek_video(videobar.currentTime() - 10);
					videooverlay._elementSlider.set_seek_position(Math.round(videobar._vtimeToSliderPosition(videobar.currentTime())));
				}
				break;
			case "volup":
				videobar.set_volume(videobar.get_volume()+10);
				setTimeout(_ => {volume.set_value(videobar.get_volume())}, 100);
				break;
			case "voldown":
				videobar.set_volume(videobar.get_volume()-10);
				setTimeout(_ => {volume.set_value(videobar.get_volume())}, 100);
				break;
			case "voloff":
				videobar.set_mute(!videobar.get_mute());
				setTimeout(_=> {
					if (videobar.get_mute())
					{
						['voldown', 'volup', 'voloff'].forEach(_w => {
							let node = this.et2.getWidgetById(_w).getDOMNode();
							node.style.opacity = _w === 'voloff' ? '1' : '0.5';
							node.style.pointerEvents = _w === 'voloff' ? 'auto' : 'none';
						});
					}
					else
					{
						['voldown', 'volup', 'voloff'].forEach(_w => {
							let node = this.et2.getWidgetById(_w).getDOMNode();
							node.style.opacity = _w == 'voloff' ? '0.5' : '1';
							node.style.pointerEvents = 'auto';
						});
					}
				},100);
				break;
			case "fullwidth":
				let sidebox = document.getElementsByClassName('sidebox_mode_comments');
				let rightBoxArea = document.getElementsByClassName('rightBoxArea');
				let max_mode = document.getElementsByClassName('max_mode_comments');
				let fullwidth = this.et2.getDOMWidgetById('fullwidth');
				let leftBoxArea = document.getElementsByClassName('leftBoxArea');
				let clml = <et2_smallpart_cl_measurement_L>this.et2.getWidgetById('clm-l');
				if (fullwidth.getDOMNode().classList.contains('glyphicon-fullscreen'))
				{
					fullwidth.getDOMNode().classList.replace('glyphicon-fullscreen', 'glyphicon-resize-small');
					max_mode[0].append(rightBoxArea[0]);
					leftBoxArea[0].setAttribute('colspan', '2');
					if (clml)
					{
						clml.getDOMNode().classList.add('fixed-l');
					}
				}
				else
				{
					fullwidth.getDOMNode().classList.replace('glyphicon-resize-small', 'glyphicon-fullscreen');
					sidebox[0].append(rightBoxArea[0]);
					leftBoxArea[0].removeAttribute('colspan');
					if (clml)
					{
						clml.getDOMNode().classList.remove('fixed-l');
					}
				}
				// resize resizable widgets
				[videobar, 'comments_slider'].forEach((_w) => {
					let w : any = (typeof _w === 'string') ? <et2_widget>this.et2.getDOMWidgetById(_w) : _w;
					w?.resize(0);
				});
				break;
			// pdf page controllers
			case "pgnxt":
				if (videobar.duration() > videobar.currentTime())
				{
					videobar.seek_video(videobar.currentTime() + 1);
					videooverlay._elementSlider.set_seek_position(Math.round(videobar._vtimeToSliderPosition(videobar.currentTime())));
				}
				break;
			case "pgprv":
				if (videobar.currentTime() > 1)
				{
					videobar.seek_video(videobar.currentTime() - 1);
					videooverlay._elementSlider.set_seek_position(Math.round(videobar._vtimeToSliderPosition(videobar.currentTime())));
				}
				break;
		}
	}

	public student_attachmentFinish()
	{
		this.et2.getDOMWidgetById('saveAndContinue').set_disabled(false);
	}

	public student_attachmentStart()
	{
		this.et2.getDOMWidgetById('saveAndContinue').set_disabled(true);
		return true;
	}

	/**
	 * Highlights comment row based for the given comment id
	 * @param _comment_id
	 * @private
	 */
	private _student_highlightSelectedComment(_comment_id)
	{
		let commentsGrid = jQuery(this.et2.getWidgetById('comments').getDOMNode());
		let scrolledComment = commentsGrid.find('tr.commentID' + _comment_id);
		if (scrolledComment[0].className.indexOf('hideme')<0)
		{
			commentsGrid.find(smallpartApp.commentRowsQuery).removeClass('highlight');
			scrolledComment.addClass('highlight');
			commentsGrid[0].scrollTop = scrolledComment[0].offsetTop;
		}
	}

	public student_playVideo(_pause: boolean)
	{
		let videobar = <et2_smallpart_videobar>this.et2.getWidgetById('video');
		let $play = jQuery(this.et2.getWidgetById('play').getDOMNode());
		let self = this;
		let content = this.et2.getArrayMgr('content');
		this._student_setCommentArea(false);
		if ($play.hasClass('glyphicon-pause') || _pause)
		{
			videobar.pause_video();
			$play.removeClass('glyphicon-pause glyphicon-repeat');
		}
		else {
			this.start_watching();

			videobar.set_marking_enabled(false);
			if (!content.data.video.video_src.match(/pdf/))
			{
				videobar.play_video(
					function () {
						$play.removeClass('glyphicon-pause');
						if (!(videobar.getArrayMgr('content').getEntry('video')['video_test_options'] & et2_smallpart_videobar.video_test_option_not_seekable)) {
							$play.addClass('glyphicon-repeat');
						}
						// record video watched
						self.record_watched();
					},
					function (_id) {
						self._student_highlightSelectedComment(_id);
						let comments_slider = self.et2.getWidgetById('comments_slider');
						if (comments_slider)
						{
							comments_slider.set_selected(false);
						}
					});
			}
			$play.removeClass('glyphicon-repeat');
			$play.addClass('glyphicon-pause');
		}
	}

	public student_dateFilter(_subWidget, _widget)
	{
		let value = _widget.getValue();
		if (_subWidget.id === 'comment_date_filter[from]' || _subWidget.id === 'comment_date_filter[to]')
		{
			this._student_dateFilterSearch();
		}
	}

	private _student_dateFilterSearch()
	{
		let rows = jQuery(smallpartApp.commentRowsQuery, this.et2.getWidgetById('comments').getDOMNode());
		let ids = [];
		const comments = this.et2.getArrayMgr('content').getEntry('comments');
		const date = this.et2.getDOMWidgetById('comment_date_filter').getValue();
		const from = new Date(date.from);
		const to = new Date(date.to);

		rows.each(function(){
			let id = this.classList.value.match(/commentID.*[0-9]/)[0].replace('commentID','');
			let comment = comments.filter(_item=>{return _item.comment_id == id;});
			if (comment && comment.length>0) {
				let date_updated = new Date(comment[0].comment_updated.date);
				if ((from <= date_updated && to >= date_updated)
					|| (date.from && !date.to && from <= date_updated)
					|| (date.to && !date.from && to >= date_updated)) ids.push(id);
			}
		});
		this._student_commentsFiltering('date', ids.length == 0 && (date.to || date.from) ? ['ALL'] : ids);
	}

	public student_filter_tools_actions(_action, _selected)
	{
		switch (_action.id)
		{
			case 'mouseover':
				this.student_onmouseoverFilter(_action.checked);
				break;
			case 'download':
				this.et2.getInstanceManager().postSubmit('download');
				break;
			case 'pauseaftersubmit':

				break;
			case 'searchall':
				if (_action.checked)
				{
					this.et2.getDOMWidgetById('comment_search_filter').getDOMNode().classList.add('searchall');
				}
				else
				{
					this.et2.getDOMWidgetById('comment_search_filter').getDOMNode().classList.remove('searchall');
				}
				break;
			case 'date':
				let date = this.et2.getDOMWidgetById('comment_date_filter');
				date.set_disabled(!_action.checked);
				if (!_action.checked) date.set_value({from:'null',to:'null'});
				break;
			case 'attachments':
				this._student_filterAttachments(_action.checked);
				break;
			case 'marked':
				this._student_filterMarked(_action.checked);
				break;
		}
	}

	public student_top_tools_actions(_action, _selected)
	{
		let video_id=this.et2.getValueById('videos');
		const content = this.et2.getArrayMgr('content');
		switch (_action.id)
		{
			case 'course':
				egw.open(this.et2.getValueById('courses'),'smallpart','edit');
				break;
			case 'question':
				if (video_id) egw.open_link(egw.link('/index.php','menuaction=smallpart.EGroupware\\SmallParT\\Questions.index&video_id='+video_id+'&ajax=true&cd=popup'));
				break;
			case 'score':
				if (video_id) egw.open_link(egw.link('/index.php','menuaction=smallpart.EGroupware\\SmallParT\\Questions.scores&video_id='+video_id+'&ajax=true&cd=popup'));
				break;
			case 'note':
				if (video_id)
				{
					const iframe = <et2_iframe>this.et2.getDOMWidgetById('note');
					egw.request('EGroupware\\smallpart\\Student\\Ui::ajax_createNote',
						[content.getEntry('courses'),video_id]).then(_data =>
						{
							if (_data.path)
							{
								const clm_l = <et2_smallpart_cl_measurement_L>this.et2.getDOMWidgetById('clm-l');
								if (clm_l)
								{
									iframe.getDOMNode().onload = () =>
									{
										// we need to wait until Collabora messages it's ready, before binding our keydown handler
										(<HTMLIFrameElement>iframe.getDOMNode()).contentWindow.addEventListener('message', e =>
										{
											const message = JSON.parse(e.data);
											if (message.MessageId === 'App_LoadingStatus' && message.Values.Status === 'Document_Loaded')
											{
												try {
													const egw_co_document = (<HTMLIFrameElement>iframe.getDOMNode()).contentDocument;
													clm_l.bindKeyHandler((<HTMLIFrameElement>egw_co_document.querySelector('iframe#loleafletframe')).contentDocument);
												}
												catch (e) {
													console.error('Can NOT bind keydown handler on Colloabora: '+e.message);
												}
											}
										});
									}
								}
								iframe.set_value(
									egw.link('/index.php', {
										'menuaction': 'collabora.EGroupware\\collabora\\Ui.editor',
										'path': _data.path,
										'cd': 'no'	// needed to not reload framework in sharing
								}));
								document.getElementsByClassName('note_container')[0].style.display = 'block';
							}
							egw.message(_data.message);
						})
				}
		}
	}

	public student_addNote()
	{
		this.student_top_tools_actions({id:'note'}, null);
	}

	/**
	 * Add new comment / edit button callback
	 */
	public student_addComment()
	{
		let comment = <et2_grid>this.et2.getWidgetById('comment');
		let videobar = <et2_smallpart_videobar>this.et2.getWidgetById('video');
		let comments_slider = <et2_smallpart_videooverlay_slider_controller>this.et2.getDOMWidgetById('comments_slider');
		let videooverlay = <et2_smallpart_videooverlay>this.et2.getDOMWidgetById('videooverlay');
		let self = this;
		this.student_playVideo(true);
		self.et2.getWidgetById(smallpartApp.playControlBar).set_disabled(true);

		this._student_setCommentArea(true);
		videobar.set_marking_enabled(true, function(){
			self._student_controlCommentAreaButtons(false);
		});
		videobar.set_marking_readonly(false);
		videobar.setMarks(null);
		this.edited = jQuery.extend(this.student_getFilter(), {
			account_lid: this.egw.user('account_lid'),
			comment_starttime: Math.round(videobar.currentTime()),
			comment_added: [''],
			comment_color: smallpartApp.default_color,
			action: 'edit',
			save_label: this.egw.lang('Save'),
			video_duration: videobar.duration()
		});

		comment.set_value({content: this.edited});
		comment.getWidgetById('deleteComment').set_disabled(true);
		this.et2.setDisabledById('comment_timespan', !this.is_staff);
		this._student_controlCommentAreaButtons(true);
		comments_slider?.disableCallback(true);
		videooverlay.getElementSlider().disableCallback(true);
	}

	/**
	 * Cancel edit and continue button callback
	 */
	public student_cancelAndContinue()
	{
		let videobar = <et2_smallpart_videobar>this.et2.getWidgetById('video');
		let filter_toolbar = this.et2.getDOMWidgetById('filter-toolbar');
		let comments_slider = <et2_smallpart_videooverlay_slider_controller>this.et2.getDOMWidgetById('comments_slider');
		let videooverlay = <et2_smallpart_videooverlay>this.et2.getDOMWidgetById('videooverlay');
		videobar.removeMarks();
		this.student_playVideo(filter_toolbar._actionManager.getActionById('pauseaftersubmit').checked);
		delete this.edited;
		this.et2.getWidgetById(smallpartApp.playControlBar).set_disabled(false);

		this.et2.getWidgetById('smallpart.student.comment').set_disabled(true);
		comments_slider?.disableCallback(false);
		videooverlay.getElementSlider().disableCallback(false);
	}

	/**
	 * Save comment/retweet and continue button callback
	 */
	public student_saveAndContinue()
	{
		let comment = <et2_grid>this.et2.getWidgetById('comment');
		let videobar = <et2_smallpart_videobar>this.et2.getWidgetById('video');

		let text = this.edited.action === 'retweet' ? comment.getWidgetById('retweet')?.get_value() :
			comment.getWidgetById('comment_added[0]')?.get_value();

		if (text)	// ignore empty comments
		{
			this.egw.json('smallpart.\\EGroupware\\SmallParT\\Student\\Ui.ajax_saveComment', [
				this.et2.getInstanceManager().etemplate_exec_id,
				jQuery.extend(this.edited, {
					// send action and text to server-side to be able to do a proper ACL checks
					action: this.edited.action,
					text: text,
					comment_color: comment.getWidgetById('comment_color')?.get_value() || this.edited.comment_color,
					comment_starttime: comment.getWidgetById('comment_timespan')?.widgets.starttime.get_value() || videobar.currentTime(),
					comment_stoptime: comment.getWidgetById('comment_timespan')?.widgets.stoptime.get_value() || 1,
					comment_marked: videobar.getMarks()
				}),
				this.student_getFilter()
			]).sendRequest();
		}
		this.student_cancelAndContinue();
	}

	/**
	 * Delete comment (either as action from list or by button for currently edited comment)
	 *
	 * @param _action
	 * @param _selected
	 */
	public student_deleteComment(_action, _selected)
	{
		let self = this;
		let comment_id = _action.id === 'delete' ? _selected[0].data.comment_id : self.edited.comment_id;

		et2_dialog.show_dialog(function(_button)
		{
			if (_button === et2_dialog.YES_BUTTON) {
				self.egw.json('smallpart.\\EGroupware\\SmallParT\\Student\\Ui.ajax_deleteComment', [
					self.et2.getInstanceManager().etemplate_exec_id,
					comment_id,
					self.student_getFilter()
				]).sendRequest();

				// do we need to clean up the edit-area
				if (comment_id == self.edited?.comment_id) self.student_cancelAndContinue();
			}
		}, this.egw.lang('Delete this comment?'), this.egw.lang('Delete'), null, et2_dialog.BUTTONS_YES_NO);
	}

	/**
	 * Get current active filter
	 */
	protected student_getFilter()
	{
		return {
			course_id: this.et2?.getWidgetById('courses')?.get_value() || this.filter?.course_id,
			video_id: this.et2?.getWidgetById('videos')?.get_value() || this.filter?.video_id,
		}
	}

	/**
	 * apply group filter
	 * @param _node
	 * @param _widget
	 */
	public student_filterGroup(_node, _widget)
	{
		let rows = jQuery(smallpartApp.commentRowsQuery, this.et2.getWidgetById('comments').getDOMNode());
		let ids = [];
		const accounts = this.et2.getArrayMgr('sel_options').getEntry('account_id');
		const comments = this.et2.getArrayMgr('content').getEntry('comments');
		const group = _widget.get_value();
		rows.each(function(){
			let id = this.classList.value.match(/commentID.*[0-9]/)[0].replace('commentID','');
			let found = [];
			let comment = comments.filter(_item=>{return _item.comment_id == id;});
			if (comment && comment.length>0)
			{
				let account_id = comment[0]['account_id'];
				found =accounts.filter(_item => {
					if (_item.value == account_id)
					{
						switch (group)
						{
							case 'unsub':
								return !_item.active;
							case 'sub':
								return _item.active;
							default:
								return (_item.group == group || _item.group == null || typeof _item.group == 'undefined');
						}
					}
					return false;
				});
			}
			if (found?.length>0 || group == '') ids.push(id);
		});
		if ((group == 'unsub' || group == 'sub') && ids.length == 0) ids = ['ALL'];
		this._student_commentsFiltering('group', ids);
	}

	/**
	 * Apply (changed) comment filter by marking
	 *
	 * Filter is applied by hiding filtered rows client-side
	 */
	public _student_filterMarked(_state)
	{
		let rows = jQuery( smallpartApp.commentRowsQuery, this.et2.getWidgetById('comments').getDOMNode()).filter('.commentMarked');
		let ids = [];
		rows.each(function(){
			ids.push(this.classList.value.match(/commentID.*[0-9]/)[0].replace('commentID',''));
		});
		this._student_commentsFiltering('marked', _state?ids:[]);
	}

	/**
	 * Apply (changed) comment filter by attachments
	 *
	 * Filter is applied by hiding filtered rows client-side
	 */
	public _student_filterAttachments(_state)
	{
		let rows = jQuery( smallpartApp.commentRowsQuery, this.et2.getWidgetById('comments').getDOMNode()).filter('.commentAttachments');
		let ids = [];
		rows.each(function(){
			ids.push(this.classList.value.match(/commentID.*[0-9]/)[0].replace('commentID',''));
		});
		this._student_commentsFiltering('attachments', _state?ids:[]);
	}

	/**
	 * Apply (changed) comment filter
	 *
	 * Filter is applied by hiding filtered rows client-side
	 */
	public student_filterComments()
	{
		let color = this.et2.getWidgetById('comment_color_filter').get_value();
		let rows = jQuery( smallpartApp.commentRowsQuery, this.et2.getWidgetById('comments').getDOMNode()).filter('.commentColor'+color);
		let ids = [];
		rows.each(function(){
			ids.push(this.classList.value.match(/commentID.*[0-9]/)[0].replace('commentID',''));
		});
		this._student_commentsFiltering('color', (ids.length ? ids : (color!="" ? ['ALL'] : [])));
	}

	public student_clearFilter()
	{
		this.et2.getWidgetById('comment_color_filter').set_value("");
		this.et2.getWidgetById('comment_search_filter').set_value("");
		this.et2.getWidgetById('activeParticipantsFilter').set_value("");
		this.et2.getWidgetById('group').set_value("");
		this.et2.getDOMWidgetById('comment_date_filter').set_value({from:'null', to:'null'});
		for (let f in this.filters)
		{
			this._student_commentsFiltering(f,[]);
		}
	}

	public student_searchFilter(_widget)
	{
		let query = _widget.get_value();
		let rows = jQuery(smallpartApp.commentRowsQuery, this.et2.getWidgetById('comments').getDOMNode());
		let ids = [];
		let filter_toolbar = this.et2.getDOMWidgetById('filter-toolbar');
		rows.each(function(){
			jQuery.extend (
				jQuery.expr[':'].containsCaseInsensitive = <pseudoFunction>function (a, i, m) {
					let t   = (a.textContent || a.innerText || "");
					let reg = new RegExp (m[3], 'i');
					return reg.test (t) && (!filter_toolbar._actionManager.getActionById('searchall').checked ? a.classList.contains('et2_smallpart_comment') : true);
				}
			);

			if (query != '' && jQuery(this).find('*:containsCaseInsensitive("'+query+'")').length>=1)
			{
				ids.push(this.classList.value.match(/commentID.*[0-9]/)[0].replace('commentID',''));
			}
		});
		this._student_commentsFiltering('search', ids.length == 0 && query != ''? ['ALL']:ids);
	}

	public student_onmouseoverFilter(_state)
	{
		let self = this;
		let videobar = <et2_smallpart_videobar>this.et2.getWidgetById('video');
		let comments = jQuery(this.et2.getWidgetById('comments').getDOMNode());
		if (_state)
		{
			comments.on('mouseenter', function(){
				if (jQuery(self.et2.getWidgetById('play').getDOMNode()).hasClass('glyphicon-pause')
					&& (!self.edited || self.edited?.action != 'edit')) videobar.pause_video();
			})
			.on('mouseleave', function(){
				if (jQuery(self.et2.getWidgetById('play').getDOMNode()).hasClass('glyphicon-pause')
					&& (!self.edited || self.edited?.action != 'edit')) videobar.play();
			});
		}
		else
		{
			comments.off('mouseenter mouseleave');
		}

	}

	/**
	 *
	 *
	 * @param _comments
	 */
	setCommentsSlider(_comments)
	{
		const comments_slider = <et2_smallpart_videooverlay_slider_controller>this.et2.getDOMWidgetById('comments_slider');
		const account_ids = this.et2.getArrayMgr('sel_options').data.account_id;

		comments_slider.set_value(_comments.map(_item => {
			return {
				id: _item.comment_id,
				starttime: _item.comment_starttime,
				duration: _item.comment_stoptime - _item.comment_starttime,
				color: _item.comment_color,
				account_id: _item.account_id
			};
		}).filter(_item =>{
			return _item.id && account_ids.find(_id=>{
				return _item.account_id == _id.value && _id.role>0
			});
		}));
	}

	/**
	 * Update comments
	 *
	 * @param _data see et2_grid.set_value
	 */
	public student_updateComments(_data)
	{
		// update our internal data
		this.comments = _data.content;

		// the first index (an empty array) in comments is reserved for action grid therefore ignore it.
		(<et2_box>this.et2.getWidgetById('smallpart.student.comments_list')?.getParent())?.set_disabled(this.comments?.length<=1);

		// update grid
		let comments = <et2_grid>this.et2.getWidgetById('comments');
		comments.set_value(_data);

		// update slider-tags
		let videobar = <et2_smallpart_videobar>this.et2.getWidgetById('video');
		videobar.set_slider_tags(this.comments);

		// update comments slider
		this.setCommentsSlider(this.comments);

		// re-apply the filter, if not "all"
		let applyFilter = false;
		['comment_color_filter', 'comment_search_filter', 'group', 'comment_date_filter'].forEach((_id) => {
			if (this.et2.getWidgetById(_id).get_value()) applyFilter = true;
		});
		if (applyFilter) this.student_filterComments();

		this._student_setFilterParticipantsOptions();
	}

	public student_revertMarks(_event, _widget)
	{
		let videobar = <et2_smallpart_videobar>this.et2.getWidgetById('video');
		videobar.setMarks(this.edited.comment_marked);
		this._student_controlCommentAreaButtons(true)
	}

	public student_hideBackground(_node: HTMLElement, _widget)
	{
		let videobar = <et2_smallpart_videobar>this.et2.getWidgetById('video');
		videobar.setMarkingMask(_widget.getValue() !="");
	}

	public student_hideMarkedArea(_node: HTMLElement, _widget)
	{
		let videobar = <et2_smallpart_videobar>this.et2.getWidgetById('video');
		let is_readonly = _widget.getValue() !="";
		videobar.setMarksState(is_readonly);
		let ids = ['markedColorRadio', 'revertMarks' , 'deleteMarks', 'backgroundColorTransparency'];
		for(let i in ids)
		{
			let widget = (<et2_template><unknown>this.et2.getWidgetById('comment')).getWidgetById(ids[i]);
			let state = is_readonly;
			if (widget && typeof widget.set_readonly == "function")
			{
				switch(ids[i])
				{

					case 'revertMarks':
						state = is_readonly ? is_readonly :!((!this.edited.comment_marked && videobar.getMarks().length>0) ||
						(this.edited.comment_marked && videobar.getMarks().length>0
						&& this.edited.comment_marked.length != videobar.getMarks().length));
						break;
					case 'deleteMarks':
						state = is_readonly ? is_readonly : !(this.edited.comment_marked || videobar.getMarks().length>0)
						break;
				}
				widget.set_readonly(state);
			}
		}
	}

	public student_deleteMarks()
	{
		let videobar = <et2_smallpart_videobar>this.et2.getWidgetById('video');
		videobar.removeMarks()
		this._student_controlCommentAreaButtons(true);
	}

	public student_setMarkingColor(_input: HTMLElement, _widget)
	{
		let videobar = <et2_smallpart_videobar>this.et2.getWidgetById('video');
		videobar.set_marking_color(_widget.get_value());
	}

	public student_sliderOnClick(_video: et2_smallpart_videobar)
	{
		// record, in case we're playing
		this.record_watched(_video.previousTime());
		if (!_video.paused()) this.start_watching();

		this.et2.getWidgetById('play').getDOMNode().classList.remove('glyphicon-repeat')
	}

	public student_comments_column_switch(_node, _widget)
	{
		const comments = this.et2.getDOMWidgetById('comments');
		if (_widget.getValue())
		{
			comments.set_class('hide_column');
			this.egw.set_preference('smallpart', 'comments_column_state', 0);
		}
		else
		{
			this.egw.set_preference('smallpart', 'comments_column_state', 1);
			comments.set_class('');
			comments.getDOMNode().classList.remove('hide_column');
		}
	}

	private _student_controlCommentAreaButtons(_state: boolean)
	{
		let readonlys = ['revertMarks', 'deleteMarks'];
		for(let i in readonlys)
		{
			let widget = <et2_button><unknown>(<et2_template><unknown>this.et2.getWidgetById('comment')).getWidgetById(readonlys[i]);
			if (readonlys[i] == 'deleteMarks')
			{
				_state = _state ? !this.et2.getWidgetById('video').getMarks().length??false:_state;
			}
			else if (this.edited.comment_marked)
			{
				//
			}
			if (widget?.set_readonly) widget.set_readonly(_state);
		}
	}

	/**
	 * filters comments
	 *
	 * @param _filter filter name
	 * @param _value array comment ids to be filtered, given array of['ALL']
	 * makes all rows hiden and empty array reset the filter.
	 */
	private _student_commentsFiltering(_filter: string, _value: Array<string>)
	{
		let rows = jQuery(smallpartApp.commentRowsQuery, this.et2.getWidgetById('comments').getDOMNode());
		let tags = jQuery('.videobar_slider span.commentOnSlider');
		let self = this;
		if (_filter && _value)
		{
			this.filters[_filter] = _value;
		}
		else
		{
			delete(this.filters[_filter]);
		}

		for (let f in this.filters)
		{
			for (let c in this.comments)
			{
				if (!this.comments[c] || this.comments[c].length == 0) continue;
				if (typeof this.comments[c].filtered == 'undefined') this.comments[c].filtered = [];

				if (this.filters[f]?.length > 0)
				{
					if (this.comments[c].filtered.indexOf(f) != -1) this.comments[c].filtered.splice(this.comments[c].filtered.indexOf(f) ,1);
					if (this.filters[f].indexOf(this.comments[c].comment_id) == -1 || this.filters[f][0] === "ALL")
					{
						this.comments[c].filtered.push(f);
					}
				}
				else
				{
					if (this.comments[c].filtered.indexOf(f) != -1) this.comments[c].filtered.splice(this.comments[c].filtered.indexOf(f) ,1);
				}
			}
		}


		for (let i in this.comments)
		{
			if (!this.comments[i] || this.comments[i].length == 0) continue;
			if (this.comments[i].filtered.length > 0)
			{
				rows.filter('.commentID' + this.comments[i]?.comment_id).addClass('hideme');
				tags.filter(function () {return this.dataset.id == self.comments[i]?.comment_id?.toString();}).addClass('hideme');
			}
			else
			{
				rows.filter('.commentID' + this.comments[i]?.comment_id).removeClass('hideme');
				tags.filter(function () {return this.dataset.id == self.comments[i]?.comment_id?.toString();}).removeClass('hideme');
			}
		}
	}

	public student_filterParticipants(_e, _widget)
	{
		let values : Array<string> = _widget.getValue();
		let data = [], value = [];

		for (let i in values)
		{
			value = values[i].split(',');
			if (value) data = data.concat(value.filter(x => data.every(y => y !== x)));
		}
		this._student_commentsFiltering('participants', data);
	}

	private _student_fetchAccountData(_id, _stack, _options, _resolved)
	{
		let self = this;
		egw.accountData(parseInt(_id), 'account_fullname', null, function(_d){
			if (Object.keys(_d).length>0)
			{
				let id = parseInt(Object.keys(_d)[0]);
				_options[id].label = _d[id];
			}
			egw.accountData(_id, 'account_firstname', null, function(_n){
				if (Object.keys(_n).length>0)
				{
					let id = parseInt(Object.keys(_n)[0]);
					_options[id].name = _n[id]+'['+id+']';
					let newId = _stack.pop();
					if (newId)
					{
						self._student_fetchAccountData(newId, _stack, _options, _resolved);
					}
					else
					{
						_resolved(_options);
					}
				}
			}, egw(window));
		}, egw(window));
	}

	private _student_setFilterParticipantsOptions()
	{
		const activeParticipants = <et2_taglist>this.et2.getWidgetById('activeParticipantsFilter');
		const passiveParticipantsList = <et2_taglist>this.et2.getWidgetById('passiveParticipantsList');
		let options = {};
		const participants: any = this.et2.getArrayMgr('sel_options').getEntry('account_id');
		const commentHeaderMessage = this.et2.getWidgetById('commentHeaderMessage');
		const staff = this.et2.getArrayMgr('sel_options').getEntry('staff');
		let roles = {};
		staff.forEach((staff) => roles[staff.value] = staff.label);

		let _foundInComments = (_id) =>
		{
			for (let k in this.comments)
			{
				if (this.comments[k]['account_id'] == _id) return true;
			}
		};

		let _countComments = (_id) =>
		{
			let c = 0;
			for (let i in this.comments)
			{
				if (this.comments[i]['account_id'] == _id) c++;
			}
			return c;
		};

		const _getNames = (_account_id) =>
		{
			for(let p in participants)
			{
				if (participants[p].value == _account_id)
				{
					return {
						label: participants[p].label,
						name:  roles[participants[p].role] || participants[p].title,
						icon:  egw.link('/api/avatar.php',{account_id: _account_id})
					}
				}
			}
			return {
				name: '',
				label: '#'+_account_id,
				icon: egw.link('/api/avatar.php',{account_id: _account_id})
			}
		};

		if (activeParticipants)
		{
			for (let i in this.comments)
			{
				if (!this.comments[i]) continue;
				let comment = this.comments[i];
				if (typeof options[comment.account_id] === 'undefined')
				{
					options[comment.account_id] = _getNames(comment.account_id);
				}
				options[comment.account_id] = jQuery.extend(options[comment.account_id], {
					value: options[comment.account_id] && typeof options[comment.account_id]['value'] != 'undefined' ?
						(options[comment.account_id]['value'].indexOf(comment.comment_id)
						? options[comment.account_id]['value'].concat(comment.comment_id) : options[comment.account_id]['value'])
							: [comment.comment_id],
					comments: _countComments(comment.account_id),
				});

				if (comment.comment_added)
				{
					for (let j in comment.comment_added)
					{
						let comment_added = comment.comment_added[j];
						if (Number.isInteger(<number>comment_added))
						{
							if (typeof options[comment_added] == 'undefined'
								&& !_foundInComments(comment_added))
							{
								options[comment_added] = _getNames(comment_added);
								options[comment_added].value = [comment.comment_id];
							}
							else if (typeof options[comment_added] == 'undefined')
							{
								options[comment_added] = _getNames(comment_added);
								options[comment_added].value = [];
							}
							options[comment_added]['retweets'] =
								options[comment_added]['retweets']
									? options[comment_added]['retweets']+1 : 1;

							options[comment_added]['value'] = options[comment_added]['value'].indexOf(comment.comment_id) == -1
								? options[comment_added]['value'].concat(comment.comment_id) : options[comment_added]['value'];
						}
					}
				}
			}

			for(let i in options)
			{
				if (options[i]?.value?.length>0)
				{
					options[i].value = options[i].value.join(',');
				}
			}
			// set options after all accounts info are fetched
			activeParticipants.set_select_options(options);

			let passiveParticipants = [{}];
			for (let i in participants)
			{
				if (!options[participants[i].value]) passiveParticipants.push({account_id:participants[i].value});
			}
			passiveParticipantsList.set_value({content:passiveParticipants});
			commentHeaderMessage.set_value(this.egw.lang("%1/%2 participants already answered",
				Object.keys(options).length, Object.keys(options).length+passiveParticipants.length-1));
		}
	}

	/**
	 * Subscribe to a course / ask course password
	 *
	 * @param _action
	 * @param _senders
	 */
	subscribe(_action, _senders)
	{
		let self = this;
		et2_dialog.show_prompt(function (_button_id, _password)
		{
			if (_button_id == et2_dialog.OK_BUTTON )
			{
				self.courseAction(_action, _senders, _password);
			}
		}, this.egw.lang("Please enter the course password"),
			this.egw.lang("Subscribe to course"), {}, et2_dialog.BUTTONS_OK_CANCEL, et2_dialog.QUESTION_MESSAGE);
	}

	/**
	 * course- or video-selection changed
	 *
	 * @param _node
	 * @param _widget
	 */
	courseSelection(_node : HTMLSelectElement, _widget : et2_selectbox)
	{
		this.record_watched();

		// remove excessive dialogs left over from previous video selection
		this.et2.getDOMWidgetById('videooverlay')?.questionDialogs.forEach(_o => {_o.dialog.destroy()});

		if (_widget.id === 'courses' && _widget.getValue() === 'manage')
		{
			this.egw.open(null, 'smallpart', 'list', '', '_self');
		}
		else
		{
			// sent video_id via hidden text field, in case new video was added on client-side via push (will be ignored by eT2 validation)
			if (_widget.id === 'videos')
			{
				_widget.getRoot().setValueById('video2', _widget.getValue());
			}
			// submit to server-side
			_widget.getInstanceManager().submit(null, false, true);
		}
		return false;
	}

	/**
	 * Execute a server-side action on a course
	 *
	 * @param _action
	 * @param _senders
	 * @param _password
	 */
	courseAction(_action, _senders, _password)
	{
		let ids = [];
		_senders.forEach(function(_sender)
		{
			ids.push(_sender.id.replace('smallpart::', ''));
		});
		switch (_action.id)
		{
			case 'open':
				this.egw.open(ids[0], 'smallpart', 'view', {cd: "no"}, '_self');
				break;

			default:
				this.egw.json('smallpart.\\EGroupware\\SmallParT\\Courses.ajax_action',
					[_action.id, ids, false, _password])
					.sendRequest();
				break;
		}
	}

	/**
	 * Distribute course-groups
	 *
	 * @param _node
	 * @param _widget
	 */
	changeCourseGroups(_node : HTMLSelectElement, _widget : et2_button)
	{
		const groups = (<et2_textbox>_widget.getParent().getWidgetById('course_groups'))?.get_value();
		const mode = (<et2_selectbox>_widget.getParent().getWidgetById('groups_mode'))?.get_value();
		if (mode && !groups)
		{
			et2_dialog.alert(this.egw.lang('You need to set a number or size first!'));
		}
		(<et2_tabbox>_widget.getRoot().getWidgetById('tabs'))?.setActiveTab(1);
		// unfortunately we can not getWidgetById widgets in an auto-repeated grid
		const content = _widget.getArrayMgr('content').getEntry('participants');
		const values = _widget.getInstanceManager().getValues(_widget.getRoot().getWidgetById('participants')).participants;
		for(let row=1,student=0; typeof content[row] === 'object' && content[row] !== null; ++row)
		{
			content[row] = Object.assign(content[row], values[row]||{});
			const participant = content[row];
			if (participant && participant.participant_unsubscribed !== null)
			{
				// do not modify unsubscribed participants
			}
			else if (participant && !parseInt(participant.participant_role) && mode)
			{
				if (mode.substr(0, 6) === 'number')
				{
					content[row].participant_group = 1+(student % groups);
				}
				else
				{
					content[row].participant_group = 1+(student/groups|0);
				}
				++student;
			}
			else
			{
				content[row].participant_group = '';
			}
		}
		(<et2_grid>_widget.getRoot().getWidgetById('participants')).set_value({content: content});
		// need to run it again, after above set_value, recreating all the widgets
		this.disableGroupByRole();
	}

	/**
	 * Disable group selection if a staff-role is selected
	 *
	 * @param _node
	 * @param _widget
	 */
	changeRole(_node : HTMLSelectElement, _widget : et2_selectbox)
	{
		const grid = _widget.getParent();
		const group = <et2_textbox>grid.getWidgetById(_widget.id.replace('role', 'group'));
		const role = _widget.get_value();

		if (group)
		{
			if (role !== '0') group.set_value('');
			group.set_disabled(role !== '0');
		}
	}

	/**
	 * Disable all group inputs, if a role is set
	 */
	disableGroupByRole()
	{
		const grid = this.et2.getWidgetById('participants');
		for (let role,row=1; role = grid.getWidgetById(''+row+'[participant_role]'); ++row)
		{
			if (role.get_value() !== '0')
			{
				this.changeRole(undefined, role);
			}
		}
	}

	/**
	 * Set nickname for user
	 */
	changeNickname()
	{
		const course_id = this.student_getFilter().course_id;
		if (!course_id) return;
		const participants = this.et2.getArrayMgr('sel_options').getEntry('account_id');
		const user = participants.filter(participant => participant.value == this.user).pop();
		et2_dialog.show_prompt(function(button, nickname)
		{
			if (button === et2_dialog.OK_BUTTON && (nickname = nickname.trim()) && nickname !== user.label)
			{
				const nickname_lc = nickname.toLowerCase();
				if (nickname.match(/\[\d+\]$]/) || participants.filter(participant =>
					participant.label.toLowerCase() === nickname_lc && participant.value != this.user).length)
				{
					this.egw.message(this.egw.lang('Nickname is already been taken, choose an other one'));
					return this.changeNickname();
				}
				this.egw.request('EGroupware\\SmallPART\\Student\\Ui::ajax_changeNickname', [course_id, nickname]);
			}
		}.bind(this), this.egw.lang('How do you want to be called?'), this.egw.lang('Change nickname'), user.label, et2_dialog.BUTTONS_OK_CANCEL);
	}

	/**
	 * Subscribe or open a course (depending on already subscribed)
	 *
	 * @param _id
	 * @param _subscribed
	 */
	openCourse(_id, _subscribed)
	{
		if (!_subscribed)
		{
			this.subscribe({id: 'subscribe'}, [{id: 'smallpart::'+_id}]);
		}
		else
		{
			this.egw.open(_id, 'smallpart', 'view', '', '_self')
		}
	}

	/**
	 * Clickhandler to copy given text or widget content to clipboard
	 * @param _widget
	 * @param _text default widget content
	 */
	copyClipboard(_widget : et2_DOMWidget, _text? : string)
	{
		let value = _text || (typeof _widget.get_value === 'function' ? _widget.get_value() : _widget.options.value);
		if (_widget.getDOMNode()?.nodeName === 'INPUT')
		{
			jQuery(_widget.getDOMNode()).val(value).select();
			document.execCommand('copy');
		}
		else
		{
			let input = jQuery(_widget.getDOMNode()?.nodeName === 'INPUT' ? _widget.getDOMNode() : document.createElement('input'))
				.appendTo(_widget.getDOMNode())
				.val(value)
				.select();
			document.execCommand('copy');
			input.remove();
		}
		this.egw.message(this.egw.lang("Copied '%1' to clipboard", value), 'success');
	}

	/**
	 * add/remove questions into post/process edit dialog
	 *
	 * @param _type
	 * @param _delete
	 * @param _id
	 *
	 * @todo: fix client-side content update base on actual current grid data
	 */
	public course_clmTab_addQ(_type, _delete, _id)
	{
		const clmQuestions = <et2_grid>this.et2.getDOMWidgetById('clm['+_type+'][questions]');
		let data = [];
		clmQuestions.cells.forEach((cell,index)=>{
			data.push(index == 0 || !cell[1]['widget']['get_value'] ? [] : {
				id:index,
				q:cell[1]['widget'].get_value(),
				al:cell[2]['widget'].get_value(),
				ar:cell[3]['widget'].get_value()
			});
		});

		if (_delete && _id)
		{
			data.splice(_id,1);
		}
		else
		{
			data.push({id: data.length, q:'', al:'', ar:''});
		}

		clmQuestions.set_value({content:jQuery.extend([], data)});
	}



	/**
	 * enable/disable clm tab based on clm checkbox
	 * @param _node
	 * @param _widget clm checkbox
	 */
	public course_enableCLMTab(_node?, _widget)
	{
		const checked = _widget.get_value() == 'true' ? true : false;
		const tab = (<et2_tabbox>this.et2.getWidgetById('tabs')).tabData.filter(_tab =>{return _tab.id =="clm";})[0];
		tab.flagDiv[0].style.visibility = checked ? '' : 'hidden';
		tab.widget.set_disabled(!checked);
	}

	public course_enableLiveFeedBack(_node?, _widget)
	{
		const checked = _widget.get_value() == 'true' ? true : false;
		this.et2.getDOMWidgetById('lfbUploadSection').set_disabled(!checked);

	}

	/**
	 * onclick callback used in course.xet
	 * @param _event
	 * @param _widget
	 */
	course_addVideo_btn(_event, _widget)
	{
		let url = this.et2.getWidgetById('video_url');
		let file = this.et2.getWidgetById('upload');
		let name = '';
		let videos = this.et2.getArrayMgr('content').getEntry('videos');
		let warning = false;
		if (url.getValue() !='')
		{
			let parts = url.getValue().split('/');
			name = parts[parts.length-1];
		}
		else if (file.getValue())
		{
			name = Object.values(file.getValue())[0]['name'];
		}

		for(let i in videos)
		{
			if (videos[i] && videos[i]['video_name'] == name)
			{
				warning = true;
			}
		}
		if (warning)
		{
			et2_dialog.confirm(_widget, "There's already a video with the same name, would you still like to upload it?", "Duplicate name", false);
		}
		else
		{
			_widget.getInstanceManager().submit();
		}
	}

	public course_addLivefeedback_btn(_event, _widget)
	{
		let url = this.et2.getWidgetById('video_url');
		url.set_value('http://'+window.location.host+egw.webserverUrl+'/smallpart/setup/livefeedback.mp4');
		_widget.getInstanceManager().submit();
	}


	/**
	 * Called when student started watching a video
	 */
	public start_watching()
	{
		if (!(this.course_options & 1))	 return;	// not recording watched videos for this course

		let videobar = <et2_smallpart_videobar>this.et2.getWidgetById('video');
		if (typeof this.watching === 'undefined' && videobar)
		{
			this.watching = <VideoWatched>this.student_getFilter();
			this.watching.starttime = new Date();
			this.watching.position = videobar.currentTime();
			this.watching.paused = 0;
		}
		else
		{
			this.watching.paused++;
		}
	}

	/**
	 * Called when student finished watching a video
	 *
	 * @param _time optional video-time, default videobar.currentTime()
	 */
	public record_watched(_time? : number)
	{
		if (!(this.course_options & 1))	 return;	// not recording watched videos for this course

		let videobar = <et2_smallpart_videobar>this.et2?.getWidgetById('video');
		if (typeof this.watching === 'undefined')	// video not playing, nothing to record
		{
			return;
		}
		this.watching.endtime = new Date();
		this.watching.duration = (_time || videobar?.currentTime()) - this.watching.position;

		//console.log(this.watching);
		this.egw.json('smallpart.EGroupware\\SmallParT\\Student\\Ui.ajax_recordWatched', [this.watching]).sendRequest('keepalive');

		// reset recording
		delete this.watching;
	}

	/**
	 * Record video & position to restore it
	 */
	public set_video_position()
	{
		let videobar = <et2_smallpart_videobar>this.et2?.getWidgetById('video');
		let data : any = this.student_getFilter();
		data.position = videobar?.currentTime();

		if (data.video_id && typeof data.position !== 'undefined')
		{
			//console.log('set_video_position', data);
			this.egw.json('smallpart.EGroupware\\SmallParT\\Student\\Ui.ajax_setLastVideo', [data]).sendRequest('keepalive');
		}
	}

	/**
	 * Confirm import should overwrite whole course or just add videos
	 */
	public confirmOverwrite(_ev : JQuery.Event, _widget : et2_button, _node : HTMLButtonElement)
	{
		let widget = _widget;
		// if we have no course_id / add used, no need to confirm overwrite
		if (!widget.getArrayMgr('content').getEntry('course_id'))
		{
			widget.getInstanceManager().submit(widget, true, true);
			return;
		}
		et2_createWidget("dialog", {
			callback: function (_button) {
				if (_button !== "cancel") {
					widget.getRoot().setValueById('import_overwrite', _button === "overwrite");
					widget.getInstanceManager().submit(widget, true, true); // last true = no validation
				}
			},
			buttons: [
				{text: this.egw.lang("Add videos"), id: "add", class: "ui-priority-primary", default: true},
				{text: this.egw.lang("Overwrite course"), id: "overwrite", image: "delete" },
				{text: this.egw.lang("Cancel"), id: "cancel", class: "ui-state-error"},
			],
			title: this.egw.lang('Overwrite exiting course?'),
			message: this.egw.lang('Just add videos, or overwrite whole course?'),
			icon: et2_dialog.QUESTION_MESSAGE
		});
	}

	/**
	 * Number of checked siblings
	 */
	private childrenChecked(_widget : et2_widget) : number
	{
		let answered = 0;
		_widget.iterateOver(function (_checkbox)
		{
			if (_checkbox.get_value()) ++answered;
		}, this, et2_checkbox);
		return answered;
	}

	/**
	 * OnChange for multiple choice checkboxes to implement max_answers / max. number of checked answers
	 */
	public checkMaxAnswers(_ev : JQuery.Event, _widget : et2_checkbox, _node : HTMLInputElement)
	{
		let max_answers = _widget.getRoot().getArrayMgr('content').getEntry('max_answers');
		try {
			max_answers = _widget.getRoot().getValueById('max_answers');
		}
		catch (e) {}

		if (max_answers)
		{
			let checked = this.childrenChecked(_widget.getParent());
			// for dialog method is not called on load, therefore it can happen that already max_answers are checked
			if (checked > max_answers)
			{
				_widget.set_value(false);
				checked--;
			}
			_widget.getParent().iterateOver(function (_checkbox : et2_checkbox)
			{
				if (!_checkbox.get_value())
				{
					_checkbox.set_readonly(checked >= max_answers);
				}
			}, this, et2_checkbox);
		}
	}

	protected checkMinAnswer_error;

	/**
	 * Check min. number of multiplechoice answers are given, before allowing to submit
	 */
	public checkMinAnswers(_ev : JQuery.Event, _widget : et2_button, _node : HTMLInputElement) : false|null
	{
		let contentMgr = _widget.getRoot().getArrayMgr('content');
		let min_answers = contentMgr.getEntry('min_answers');

		if (this.checkMinAnswer_error)
		{
			this.checkMinAnswer_error.close();
			this.checkMinAnswer_error = null;
		}
		if (min_answers && contentMgr.getEntry('overlay_type') === 'smallpart-question-multiplechoice')
		{
			let checked = this.childrenChecked(this.et2.getWidgetById('answers'));
			if (checked < min_answers)
			{
				this.checkMinAnswer_error = this.egw.message(this.egw.lang('A minimum of %1 answers need to be checked!', min_answers), 'error');
				return false;
			}
		}
		_widget.getInstanceManager().submit(_widget);
	}

	/**
	 * Calculate blur-text for answer specific points of multiple choice questions
	 *
	 * For assessment-method score per answer, and no explicit points given, the max_points are equally divided on the answers.
	 *
	 * @param _ev
	 * @param _widget
	 * @param _node
	 */
	public defaultPoints(_ev? : JQuery.Event, _widget? : et2_widget, _node? : HTMLInputElement)
	{
		let method;
		try {
			method = this.et2.getValueById('assessment_method');
		}
		catch (e) {
			return;	// eg. text question
		}
		let max_score = parseFloat(this.et2.getValueById('max_score'));
		let question_type = this.et2.getValueById('overlay_type');

		if (method === 'all_correct' || question_type !== 'smallpart-question-multiplechoice')
		{
			jQuery('.scoreCol').hide();
		}
		else
		{
			let explicit_points = 0,explicit_set = 0,num_answers = 0;
			for(let i=1,w=null; (w=this.et2.getWidgetById(''+i+'[score]')); ++i)
			{
				// ignore empty questions
				if (!this.et2.getValueById(''+i+'[answer]')) continue;

				let val = parseFloat((<et2_textbox>w).getValue());
				if (!isNaN(val))
				{
					++explicit_set;
					if (val > 0) explicit_points += val;
				}
				++num_answers;
			}
			const default_points = ((max_score - explicit_points) / (num_answers - explicit_set)).toFixed(2);
			for(let i=1,w=null; (w=this.et2.getWidgetById(''+i+'[score]')); ++i)
			{
				// ignore empty questions
				if (!this.et2.getValueById(''+i+'[answer]')) continue;

				(<et2_textbox>w).set_blur(default_points);
			}
			jQuery('.scoreCol').show();
		}
	}

	/**
	 * Pause test by submitting it to server incl. current video position
	 *
	 * @param _ev
	 * @param _widget
	 * @param _node
	 */
	public pauseTest(_ev : JQuery.Event, _widget : et2_widget, _node : HTMLInputElement)
	{
		let videobar = <et2_smallpart_videobar>this.et2.getWidgetById('video');
		let videotime = this.et2.getInputWidgetById('video_time');

		if (videotime) videotime.set_value(videobar.currentTime());

		//disable the masking
		this._student_noneTestAreaMasking(false);

		let timer = this.et2.getDOMWidgetById('timer');
		// reset the alarms while the test is paused
		timer.options.alarm = [];

		let clml = <et2_smallpart_cl_measurement_L>this.et2.getDOMWidgetById('clm-l');
		if (clml) clml.stop();
		_widget.getInstanceManager().submit(_widget);
	}

	/**
	 * Link question start-time, duration and end-time
	 *
	 * @param _ev
	 * @param _widget
	 * @param _node
	 */
	public questionTime(_ev? : JQuery.Event, _widget? : et2_inputWidget, _node? : HTMLInputElement)
	{
		let start = this.et2.getInputWidgetById('overlay_start');
		let duration = this.et2.getInputWidgetById('overlay_duration');
		let end = this.et2.getInputWidgetById('overlay_end');
		let apply = this.et2.getDOMWidgetById('button[apply]');
		let save = this.et2.getDOMWidgetById('button[save]');

		if (!start || !duration || !end) return;	// eg. not editable
		if (_widget === end)
		{
			duration.set_value(parseInt(end.get_value())-parseInt(start.get_value()));
		}
		else
		{
			let video = this.et2.getWidgetById("video_data_helper");
			if (video && video.duration() < parseInt(start.get_value())+parseInt(duration.get_value()))
			{
				end.set_value(Math.floor(video.duration()));
				end.set_validation_error(egw.lang('Lenght of question cannot exceed the lenght of video %1', end._convert_to_display(end.get_value()).value));
				save?.set_readonly(true);
				apply?.set_readonly(true);
			}
			else
			{
				end.set_validation_error(false);
				save?.set_readonly(false);
				apply?.set_readonly(false);
			}
			end.set_value(parseInt(start.get_value())+parseInt(duration.get_value()));
		}
	}

	/**
	 * Show individual questions and answers of an account_id eg. to assess them
	 */
	public showQuestions(_action, _senders)
	{
		this.egw.open('', 'smallpart-overlay', 'list', {
			course_id: this.et2.getArrayMgr('content').getEntry('nm[col_filter][course_id]'),
			video_id: this.et2.getValueById('filter'),
			account_id: _senders[0].id.split('::')[1],
		}, '_self');
	}

	/**
	 * Check if we edit a mark or mill-out questions and load the existing markings
	 */
	public setMarkings()
	{
		const videobar = <et2_smallpart_videobar>window.opener?.app?.smallpart?.et2?.getWidgetById('video');
		const marks = <et2_textbox>this.et2.getWidgetById('marks');
		if (!videobar || !marks) return;	// eg. called from the list or no mark or mill-out question

		const mark_values : MarksWithArea = JSON.parse(marks.getValue() || '[]');
		videobar.setMarks(MarkArea.colorDisjunctiveAreas(mark_values, videobar.get_marking_colors()));
		videobar.set_marking_enabled(true, (mark) => console.log(mark));
		videobar.set_marking_readonly(true);
		videobar.setMarkingMask(mark_values.length > 0);

		// store marks before saving in hidden var again
		['button[save]', 'button[apply]'].forEach((name) =>
		{
			const button = <et2_button>this.et2.getWidgetById(name);
			if (button)
			{
				button.onclick = (e) => {
					marks.set_value(JSON.stringify(MarkArea.markDisjunctiveAreas(videobar.getMarks(true),
						videobar.video.width() / videobar.video.height())));
					return true;
				};
			}
		});

		// clear marks before unloading
		window.addEventListener("beforeunload", () => {
			videobar.setMarks([]);
			videobar.set_marking_readonly(true);
			videobar.setMarkingMask(false);
		});
	}

	/**
	 * Mark the answer area of a question
	 *
	 * @param _ev
	 * @param _widget
	 * @param _node
	 */
	public markAnswer(_ev? : JQuery.Event, _widget? : et2_inputWidget, _node? : HTMLInputElement)
	{
		const videobar = <et2_smallpart_videobar>window.opener?.app?.smallpart?.et2?.getWidgetById('video') ||
			<et2_smallpart_videobar>this.et2.getWidgetById('video');

		if (!videobar)
		{
			this.egw.message(this.egw.lang('You have to open the question from the video, to be able to mark answers!', 'error'));
			return;
		}
		videobar.set_marking_color(parseInt(_widget.options.set_value));
		videobar.set_marking_readonly(false);
		videobar.set_marking_enabled(true, (mark) => {
			let mark_values = MarkArea.markDisjunctiveAreas(videobar.getMarks(true), videobar.video.width() / videobar.video.height());
			videobar.setMarks(MarkArea.colorDisjunctiveAreas(mark_values, videobar.get_marking_colors()));
		});
		videobar.setMarksState(true);
		videobar.setMarkingMask(true);

		// mark current row as active and unmark all others
		const tr = jQuery(_widget.parentNode).closest('tr');
		tr.siblings().removeClass('markActiveRow');
		tr.addClass('markActiveRow');
	}

	/**
	 * Close LTI platform iframe
	 */
	public ltiClose()
	{
		(window.opener || window.parent).postMessage({subject:'org.imsglobal.lti.close'}, '*');
		return true;
	}

	/**
	 * Show video in LTI content selection
	 *
	 * @param _node
	 * @param _widget
	 */
	public ltiVideoSelection(_node : HTMLSelectElement, _widget : et2_selectbox)
	{
		const video = <et2_video>this.et2.getWidgetById('video');
		const video_id = _widget.getValue();
		if (video_id)
		{
			const videos = <any>this.et2.getArrayMgr('content').getEntry('videos');
			if (typeof videos[video_id] !== 'undefined')
			{
				video.set_src_type('video/'+videos[video_id].video_type);
				video.set_src(videos[video_id].video_src);
				video.set_disabled(false);
			}
		}
		else
		{
			video.set_disabled(true);
		}
	}

	public livefeedback_timerStart(_state)
	{
		let content = this.et2.getArrayMgr('content');
		let lf_recorder = <et2_widget_video_recorder>this.et2.getWidgetById('lf_recorder');
		document.getElementsByClassName('commentEditArea')[1].style.display = 'block';
		lf_recorder.record().then(()=>{
			this.egw.request('smallpart.\\EGroupware\\SmallParT\\Student\\Ui.ajax_livefeedbackSession', [
				true, {'course_id':content.getEntry('video')?.course_id, 'video_id':content.getEntry('video')?.video_id}
			]);
		});
	}

	public livefeedback_timerStop(_state)
	{
		let content = this.et2.getArrayMgr('content');
		let self = this;
		let lf_recorder = <et2_widget_video_recorder>this.et2.getWidgetById('lf_recorder');
		lf_recorder.stop().then(()=>{
			this.egw.request('smallpart.\\EGroupware\\SmallParT\\Student\\Ui.ajax_livefeedbackSession', [
				false, {'course_id':content.getEntry('video')?.course_id, 'video_id':content.getEntry('video')?.video_id}
			]).then((_data) => {
				self.egw.message(_data.msg);
				if (_data.session === 'ended')
				{
					self.et2.getInstanceManager().submit();
				}
			});
		});
	}

	public livefeedback_sessionRefreshed(_data)
	{
		self.egw.message(_data.msg);
		if (_data.session === 'ended')
		{
			self.et2.getInstanceManager().submit();
		}
	}

	public student_livefeedbackSubCatClick(_event, _widget)
	{
		let content = this.et2.getArrayMgr('content');
		let cats = this.et2.getArrayMgr('content').getEntry('cats');
		let ids = _widget.id.split(':');
		if (ids)
		{
			const cat = <et2_smallpart_color_radiobox>this.et2.getDOMWidgetById(ids[0]);
			cat.container.click();
			const row = cat.parentNode.parentElement;
			this.egw.request('smallpart.\\EGroupware\\SmallParT\\Student\\Ui.ajax_livefeedbackSaveComment', [
				this.et2.getInstanceManager().etemplate_exec_id,
				{
					// send action and text to server-side to be able to do a proper ACL checks
					action: 'add',
					course_id: content.data.video.livefeedback.course_id,
					video_id: content.data.video.livefeedback.video_id,
					text: <et2_textbox>this.et2.getDOMWidgetById(ids[0]+':comment')?.get_value()||' ',
					comment_color: cat?.get_value().replace('#', ''),
					comment_starttime: null,
					comment_stoptime: null,
					comment_marked: '',
					comment_cat: _widget.id
				}
			]).then((_data) => {
				row.classList.add('disabled');

				setTimeout(_=>{
					row.classList.remove('disabled');
					cat.set_value('');
				}, 5000);
			});
		}
	}

	public	student_livefeedbackSession()
	{
		let recorder = this.et2.getDOMWidgetById('lf_recorder');
		if (recorder && !egwIsMobile()) recorder.startMedia();

	}

	public student_livefeedbackReport()
	{
		let lf_comments_slider = <et2_smallpart_livefeedback_slider_controller>this.et2.getDOMWidgetById('lf_comments_slider');
		let comments = {};
		let elements = [];
		this.comments.forEach(_c => {
			if (_c && _c.comment_cat)
			{
				if (!comments[_c.comment_cat.split(":")[0]]) comments[_c.comment_cat.split(":")[0]] = [];
				comments[_c.comment_cat.split(":")[0]].push(_c);
			}
		});
		Object.keys(comments).forEach(_cat_id => {
			let cat = lf_comments_slider._fetchCatInfo(_cat_id);
			elements.push({title:cat.cat_name, comments: comments[_cat_id], color: cat.cat_color});
		});
		lf_comments_slider.set_value(elements);
	}

	public pushLivefeedback(_data)
	{
		if (_data && _data.acl.data?.['session_starttime'])
		{
			this.et2.getInstanceManager().submit();
		}
	}
}

app.classes.smallpart = smallpartApp;
