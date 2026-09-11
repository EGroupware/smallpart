/**
 * EGroupware - SmallParT - app
 *
 * @link https://www.egroupware.org
 * @package smallpart
 * @subpackage Ui
 * @license http://opensource.org/licenses/gpl-license.php GPL - GNU General Public License
 */


import {EgwApp} from "../../api/js/jsapi/egw_app";
import type {PushData} from "../../api/js/jsapi/egw_app";
import {et2_smallpart_videobar} from "./et2_widget_videobar";
import {MarkArea} from "./mark_helpers";
import type {MarksWithArea} from "./mark_helpers";
import {VideoEdit} from "./VideoEdit";
import './et2_widget_videooverlay';
import './et2_widget_color_radiobox';
import './et2_widget_comment';
import './et2_widget_cl_measurement_L';
import './SmallPartVideoControls';
import './SmallPartComment';
import './SmallPartCommentTimespan';
import './SmallPartLivefeedbackButton';
import './SmallPartLiveFeedbackRadioButton';
import './SmallPartFilterParticipants';
import './SmallPartFlagTime';
import './SmallPartCatsSelect';
import './SmallPartMediaRecorder';
import './SmallPartLiveFeedbackReport';
import './SmallPartTimer';
import type {et2_grid} from "../../api/js/etemplate/et2_widget_grid";
import type {Et2Template} from "../../api/js/etemplate/Et2Template/Et2Template";
import type {Et2Textbox} from "../../api/js/etemplate/Et2Textbox/Et2Textbox";
import type {et2_selectbox_ro} from "../../api/js/etemplate/legacy-shims/et2_widget_selectbox";
import {Et2Checkbox} from "../../api/js/etemplate/Et2Checkbox/Et2Checkbox";
import type {et2_widget} from "../../api/js/etemplate/et2_core_widget";
import type {Et2Button} from "../../api/js/etemplate/Et2Button/Et2Button";
import type {Et2ButtonIcon} from "../../api/js/etemplate/Et2Button/Et2ButtonIcon";
import type {et2_inputWidget} from "../../api/js/etemplate/et2_core_inputWidget";
import type {Et2InputWidgetInterface} from "../../api/js/etemplate/Et2InputWidget/Et2InputWidget";
import type {et2_smallpart_videooverlay} from "./et2_widget_videooverlay";
import type {et2_taglist} from "../../api/js/etemplate/legacy-shims/et2_widget_taglist";
import type {et2_DOMWidget} from "../../api/js/etemplate/et2_core_DOMWidget";
import type {et2_video} from "../../api/js/etemplate/et2_widget_video";
import {sprintf} from "../../api/js/egw_action/egw_action_common"
import type {Et2Description} from "../../api/js/etemplate/Et2Description/Et2Description";
import {et2_smallpart_cl_measurement_L} from "./et2_widget_cl_measurement_L";
import type {et2_countdown} from "../../api/js/etemplate/et2_widget_countdown";
import type {et2_iframe} from "../../api/js/etemplate/legacy-shims/et2_widget_iframe";
import type {et2_smallpart_videooverlay_slider_controller} from "./et2_widget_videooverlay_slider_controller";
import {Et2Dialog} from "../../api/js/etemplate/Et2Dialog/Et2Dialog";
import type {et2_arrayMgr} from "../../api/js/etemplate/et2_core_arrayMgr";
import type {Et2Textarea} from "../../api/js/etemplate/Et2Textarea/Et2Textarea";
import type {Et2Box, Et2HBox, Et2VBox} from "../../api/js/etemplate/Layout/Et2Box/Et2Box";
import type {SmallPartFlagTime} from "./SmallPartFlagTime";
import type {SmallPartLiveFeedbackReport} from "./SmallPartLiveFeedbackReport";
import type {Et2File} from "../../api/js/etemplate/Et2File/Et2File";
import type {SmallPartMediaRecorder} from "./SmallPartMediaRecorder";
import {et2_IInput} from "../../api/js/etemplate/et2_core_interfaces";
import {Et2Select} from "../../api/js/etemplate/Et2Select/Et2Select";
import type {SelectOption} from "../../api/js/etemplate/Et2Select/FindSelectOptions";
import type {EgwAction} from "../../api/js/egw_action/EgwAction";

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
	account_lid?      : string; // account login-id, used to build vfs attachment paths
	video_id          : number;
	comment_cat 	  : string;
	comment_cat_sub?  : string; // sub-category, split off comment_cat's "cat:sub" value
	comment_cat_type : string;
	comment_starttime : number;
	comment_stoptime? : number;
	comment_color     : string;
	comment_deleted?  : number;
	comment_current?  : string;	// first comment from comment_added
	comment_added     : Array<string|number>;	// ["comment", [account_id1|"nick1", "comment1", ...]]
	comment_history?  : Array<string>;	// ["previous version", ...]
	comment_related_to? : number;		// retweet of given comment_id
	comment_updated?  : DateTime;	// PHP DateTime of last update, used for the "new" comment filter
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
	video_labels: Array<{value: number; label: string}>;
	course_info: string;
	course_disclaimer: string;
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
		label : string;
		published : string|null;
	}}
}

export class smallpartApp extends EgwApp
{
	static readonly appname = 'smallpart';
	static readonly default_color = 'ffffff';	// white = neutral
	static readonly commentRowsQuery = 'tr.row.commentBox';
	static readonly playControlBar = 'play_control_bar';

	public VideoEdit : VideoEdit;

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
	 * Current user is allowed to comment
	 */
	protected isCommentAllowed : boolean = false;

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
	 * Disable comments, eg. for tests
	 */
	static readonly COMMENTS_DISABLED = 5;

	/**
	 * Show everything withing the group plus staff
	 */
	static readonly COMMENTS_GROUP = 6;
	/**
	 * Show comments within the group, but hide teachers
	 */
	static readonly COMMENTS_GROUP_HIDE_TEACHERS = 7;

	/**
	 * Pending ajax_simulatedFinished() request, so the videobar's repeating ended-callback only
	 * records and reloads the run once
	 */
	protected _simulatedFinishing : Promise<any> = null;

	/**
	 * Controls a simulated live session must not offer: it runs once, start to finish, so the video
	 * can not be played again, scrubbed with the 10s buttons, sped up or used to time a comment.
	 */
	static readonly simulatedHiddenControls = [
		'play', 'backward', 'forward', 'playback', 'playback_slow', 'playback_fast',
		'start-time-picker', 'stop-time-picker'
	];

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
		this.VideoEdit = new VideoEdit(this);
		this.user = parseInt(this.egw.user('account_id'));
	}

	/**
	 * Destructor
	 */
	destroy(_app)
	{
		// Update video time, if possible, before et2 is gone
		if(this.et2 && this.et2.getInstanceManager().name == 'smallpart.student.index')
		{
			this.set_video_position();
		}
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
			case (_name === 'smallpart.start'):
				this.is_staff = content.getEntry('is_staff');
				this.filter = {course_id: content.getEntry('course_id')};
				break;

			case (_name.match(/smallpart.student.index/) !== null):
				this.is_staff = content.getEntry('is_staff');
				this.comments = <Array<CommentType>>content.getEntry('comments');
				this._student_setCommentArea(false);

				// don't go further if the test locked screen is on or no video's selected yet, otherwise we would get
				// js errors on widget selections as they're not there yet.
				if (content.getEntry('locked') || !content.getEntry('videos') || !content.getEntry('video')) break;

				const inTestMode = parseInt(content.getEntry('video')?.video_test_duration) > 0 && content.getEntry('timer') > 0;
				const forbidTocomment = (!this.is_staff && content.getEntry('video')?.video_options == smallpartApp.COMMENTS_FORBIDDEN_BY_STUDENTS)
					|| smallpartApp.COMMENTS_DISABLED == <number>(parseInt(content.getEntry('video')?.video_options) || false)
					// a simulated session comments through its livefeedback categories, not the comment area
					|| !!content.getEntry('video')?.video_livefeedback_simulated
						&& content.getEntry('video')?.livefeedback_session != 'ended';

				// Is the current user allowed to comment on this video
				this.isCommentAllowed = !forbidTocomment;

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
					let clml = <any>this.et2.getDOMWidgetById('clm-l');
					//disable "L" if we are not in CLM mode/test mode
					if (clml) clml.set_disabled(true);
				}
				// set process CL Questionnaire when the test is running
				if (inTestMode)
				{
					this._student_noneTestAreaMasking(true);
				}
				// HIDE add comment buttons if user is not allowed to comment
				// (guarded: 'smallpart.student.comments_list' is a lazily-loaded sub-template, whose own children
				// aren't guaranteed to exist yet at et2_ready() time - getWidgetById() can legitimately return null)
				['add_comment'].forEach(_w =>
				{
					const widget = this.et2.getWidgetById(smallpartApp.playControlBar).getWidgetById(_w);
					if (widget) widget.hidden = !this.isCommentAllowed;
				});
				const comments_list_add_comment = this.et2.getWidgetById('smallpart.student.comments_list')?.getWidgetById('add_comment');
				if (comments_list_add_comment) comments_list_add_comment.hidden = !this.isCommentAllowed;

				this.filter = {
					course_id: parseInt(<string>content.getEntry('courses')) || null,
					video_id:  parseInt(<string>content.getEntry('videos')) || null
				}
				if (this.egw.preference('comments_column_state', 'smallpart') == 0 || !this.egw.preference('comments_column_state', 'smallpart'))
				{
					this.egw.set_preference('smallpart', 'comments_column_state', 0);
					(<any>this.et2.getDOMWidgetById('comments_column'))?.set_value(false);
					(<any>this.et2.getDOMWidgetById('comments'))?.set_class('hide_column');
				}
				else
				{
					(<any>this.et2.getDOMWidgetById('comments_column'))?.set_value(true);
					(<any>this.et2.getDOMWidgetById('comments'))?.getDOMNode().classList.remove('hide_column');
				}
				this.course_options = parseInt(<string>content.getEntry('course_options')) || 0;
				this._student_setFilterParticipantsOptions();
				window.addEventListener('resize', () => {
					this._student_resize();
				});
				// record, in case of F5 or window closed
				window.addEventListener("beforeunload", () => {
					this.set_video_position();
					this.record_watched();
					// record unload time, if a CL measurement test is running, in case user did not stop it properly
					if (parseInt(content.getEntry('video')?.video_test_duration)>0 && content.getEntry('timer')>0 &&
						(content.getEntry('course_options') & et2_smallpart_videobar.course_options_cognitive_load_measurement)
							== et2_smallpart_videobar.course_options_cognitive_load_measurement)
					{
						// kept as sendRequest('keepalive') - a beforeunload request needs the fetch keepalive flag to
						// survive page unload; egw.request() has no equivalent option, only plain async
						this.egw.json('smallpart.\\EGroupware\\SmallParT\\Student\\Ui.ajax_recordCLMeasurement', [
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
					// Video may already be initialized, maybe not
					const initVolume = () =>
					{
						this.et2.getWidgetById('volume').set_value('50');
						videobar.set_volume(50);
					}
					if(videobar.isReady)
					{
						initVolume();
					}
					else
					{
						videobar.video[0].addEventListener('et2_video.onReady.' + videobar.id, initVolume);
					}

					const notSeekable = videobar.getArrayMgr('content').getEntry('video')?.video_test_options & et2_smallpart_videobar.video_test_option_not_seekable;
					['forward', 'backward', 'playback', 'playback_slow', 'playback_fast'].forEach(_item=>{
						(<any>this.et2.getDOMWidgetById(_item)).set_disabled(notSeekable);
					});
				}
				(<any>this.et2.getDOMWidgetById(smallpartApp.playControlBar)).iterateOver(_w=>{

					if(content.data.video?.video_type.match(/pdf/) && _w && _w.id != '')
					{
						switch (_w.id)
						{
							case 'add_comment':
								_w.hidden = !this.isCommentAllowed;
								break;
							case 'play_control_bar':
							case 'fullwidth':
							case 'pgnxt':
							case 'pgprv':
								_w.hidden = false;
								break;
							default:
								_w.hidden = true;
						}
					}
					else if(_w.id)
					{
						switch(_w.id)
						{
							case 'add_comment':
							case 'add_note':
								// Don't change
								break;
							case 'pgnxt':
							case 'pgprv':
								_w.hidden = true;
								break;
							default:
								_w.hidden = false;
						}
					}
				},this);

				// Enable / disable according to preferences
				['pauseaftersubmit', 'mouseover', 'comment_on_top', 'hide_question_bar', 'hide_text_bar'].forEach(item =>
				{
					this.student_filter_tools_actions(this.et2.getWidgetById(item), null);
				})
				this.setCommentsSlider(this.comments);
				if (content.data.video.livefeedback)
				{
					if (content.data.video.livefeedback_session == 'ended')
					{
						this.student_livefeedbackReport();
					}
					// a simulated session has no host, recorder or publish-box, so it can not go through
					// student_livefeedbackSession(), which addresses exactly those
					else if (content.data.video.livefeedback_session == 'simulated')
					{
						this.student_simulatedSession();
					}
					else
					{
						this.student_livefeedbackSession();
					}
				}
				this.et2.getWidgetById('comment_color_filter')?.set_value("all");
				this.student_filterComments();
				// install save-on-change for video_test_display=3 (list)
				if (content.getEntry('video')?.video_test_display == 3)
				{
					this.installSaveAnswerOnChange();
				}
				break;

			case (_name === 'smallpart.question'):
				if (content.getEntry('max_answers'))
				{
					this.et2.getWidgetById('answers').iterateOver((_widget : et2_widget) =>
					{
						if (_widget.id === '1[checked]' || _widget.id === '1[correct]')
						{
							this.checkMaxAnswers(undefined, <Et2Checkbox><unknown>_widget, undefined);
						}
					}, this, Et2Checkbox);
				}
				this.defaultPoints();
				let vdh = this.et2.getWidgetById("video_data_helper");
				vdh.video[0].addEventListener("et2_video.onReady."+vdh.id, _ =>{this.questionTime();});
				// set markings for mark or mill-out questions
				this.setMarkings();
				break;

			case (_name === 'smallpart.course'):
				// disable import button until a file is selected
				const import_button : Et2Button = <Et2Button>this.et2.getWidgetById('button[import]');
				import_button?.set_readonly(true);
				const import_file = <Et2File>this.et2.getWidgetById("import");
				import_file.addEventListener("change", () =>
				{
					import_button.set_readonly(Object.values(import_file.value).length == 0);
				});
				// seem because set_value of the grid, we need to defer after, to work for updates/apply too
				window.setTimeout(() => this.disableGroupByRole(), 0);

				// Only allow 1 details in course info to open at a time
				const container : Et2Template = this.et2.querySelector(".details-group");
				container.addEventListener("sl-show", (event) =>
				{
					if((<Element>event.target).localName === 'et2-details')
					{
						Array.from(container.querySelectorAll<HTMLDetailsElement>('et2-details')).map(details => (details.open = event.target === details));
					}
				});

				// Scroll to current video
				const tabs = this.et2.getWidgetById("tabs");
				if(tabs && tabs.value == "videos")
				{
					tabs.querySelector("#smallpart-course_videos")?.querySelector("et2-details[open]")?.scrollIntoView();
				}
				break;

			case (_name === 'smallpart.lti-content-selection'):
				const video_id = <Et2Select>this.et2.getWidgetById('video_id');
				if (video_id.getValue())
				{
					this.ltiVideoSelection(undefined, video_id);
				}
				break;
			case (_name === 'smallpart.material.edit'):
				_et2.widgetContainer.getWidgetById("video_name").disabled = true;
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
		else if(typeof pushData.id === "number" && !pushData?.acl?.data?.lf_id)
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
		else if(pushData.id && pushData.acl['data']['lf_id']
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

		// if course got closed (for students) --> go to course-list
		if ((course.course_closed == 1 || type === 'delete' || !Object.keys(course).length))
		{
			egw.open(null, 'smallpart', 'list');
			console.log('unselecting no longer accessible or deleted course');
			return;
		}

		const sel_options = this.et2.getArrayMgr('sel_options');

		// update course-name, if changed
		if (this.et2.getWidgetById('course_name'))
		{
			this.et2.getWidgetById('course_name').value = course.course_name;
		}
		let courses : Array<any> = sel_options.getEntry('courses');
		for(let n in courses)
		{
			if (courses[n].value == course_id)
			{
				courses[n].label = course.course_name;
				const course_selection = <Et2Select>this.et2.getWidgetById('courses');
				if (course_selection) course_selection.select_options = courses;
				break;
			}
		}

		// update video-names
		const video_selection : Et2Select|et2_grid|undefined = this.et2.getWidgetById('videos');
		if (video_selection instanceof Et2Select) video_selection.select_options = <SelectOption[]><unknown>course.video_labels;

		// currently watched video no longer exist / accessible --> go to course start-page
		if(video_selection instanceof Et2Select && filter.video_id && typeof course.videos[filter.video_id] === 'undefined')
		{
			egw.open(filter.course_id, 'smallpart', 'view');
			console.log('unselecting no longer accessible or deleted video');
			return;
		}

		// update currently watched video
		const video = course.videos[filter.video_id];
		const task = <Et2Description>this.et2.getWidgetById('video[video_question]');
		if(video != null && task != null)
		{
			task.set_value(video.video_question);
			(<Et2VBox>task.getParent()).set_statustext(video.video_question);
		}

		// video.video_options or _published* changed --> reload
		const content = this.et2.getArrayMgr('content');
		const old_video = content.getEntry('video');
		if(video && old_video)
		{
			if(video.video_options != old_video.video_options ||
				video.video_published != old_video.video_published ||
				video.video_published_start?.date != old_video?.video_published_start?.date ||
				video.video_published_end?.date != old_video?.video_published_end?.date)
			{
				(<Et2Select>video_selection).value = '';
				this.courseSelection(null, <Et2Select>video_selection);
				console.log('reloading as video_options/_published changed', old_video, video);
				return;
			}
		}

		// add video_test_* (and all other video attributes) to content, so we use them from there
		if(video)
		{
			Object.assign((<any>content.data).video, video);
		}

		// course-options: &1 = record watched, &2 = display watermark
		this.course_options = course.course_options;

		// update groups
		const group = <Et2Select>this.et2.getWidgetById('group');
		if (group && typeof group.set_select_options === "function")
		{
			let group_options : any[] = Object.values(this.et2.getArrayMgr('sel_options').getEntry('group') || {}).slice(-2);
			for(let g=1; g <= course.course_groups; ++g)
			{
				group_options.splice(g-1, 0, {value: g, label: this.egw.lang('Group %1', g)});
			}
			group.set_select_options(group_options);
		}

		// update start-page
		if (!filter.video_id)
		{
			// do NOT overwrite help-text for teachers with empty course_info/disclaimer
			if (course.course_info)
			{
				this.et2.setValueById('course_info', course.course_info);
			}
			if (course.course_disclaimer)
			{
				this.et2.setValueById('course_disclaimer', course.course_disclaimer);
			}
			// only update list of material, if user is already subscribed
			if (this.et2.getArrayMgr('content').getEntry('subscribed'))
			{
				// get videos grid, sharing id with selectbox, but requiring it as namespace :(
				const material = <et2_grid>this.et2.getWidgetById('material')?.getWidgetById('videos');
				const old_videos : Array<any> = this.et2.getArrayMgr('content').getEntry('videos');
				const videos = course.video_labels.map(option => {
					const old_video = old_videos.find(video => video.video_id == option.value) || {};
					return {...old_video, course_id: course.course_id, video_id: option.value, label: option.label, ...course.videos[option.value]};
				});
				material?.set_value({content: videos});
			}
		}
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
		const course_id = parseInt(id.split(':').shift());
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
					const course_selection = <Et2Select>this.et2.getWidgetById('courses');
					// simulate selecting "manage" to navigate away, since the user was just kicked from this course
					course_selection.set_value('manage');
					this.courseSelection(<HTMLSelectElement>course_selection.getDOMNode(), course_selection);
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

		// check if we are in the student UI, if not we're done
		if (this.et2.getInstanceManager().name.match(/smallpart.start/))
		{
			this.changeNicknameStartpage(participants);
			return;
		}

		// do we need to update the comments (because student changed group)
		if (need_comment_update)
		{
			this.egw.request('smallpart.\\EGroupware\\SmallParT\\Student\\Ui.ajax_listComments', [
				this.student_getFilter()
			]);
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

			// If pushed comment is currently in the list, get its index
			let commentIndex : number;
			if(['delete', 'update', 'edit', 'retweet'].includes(type))
			{
				commentIndex = this.comments.findIndex((c) => c.comment_id == comment.comment_id);
			}

			// integrate pushed comment in own data and add/update it there
			switch(type)
			{
				case 'add':
					this.comments.push(comment);
					break;
				case 'delete':
					this.comments.splice(commentIndex, 1);
					break;
				case 'update':
				case 'edit':
				case 'retweet':
					this.comments[commentIndex] = comment;
					break;
			}

			if(['add', 'update', 'edit'].includes(type))
			{
				// Sort to properly place updated times & new comments
				// Sort is by start time, then end time, then comment ID
				this.comments.sort((a, b) =>
				{
					return a.comment_starttime - b.comment_starttime ||
						a.comment_stoptime - b.comment_stoptime ||
						a.comment_id - b.comment_id
				});
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
		// add class(es) regular added on server-side, without overwriting what's there
		if (this.is_staff || comment.account_id == this.user)
		{
			comment.class += ' commentOwner';
		}
		if (comment.comment_marked && comment.comment_marked.length)
		{
			comment.class += ' commentMarked';
		}
		if (comment.comment_cat)
		{
			comment.comment_cat.split(":").forEach(_cat=>{
				comment.class += ' cat-'+_cat;
			});
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
			// sprintf() is declared with no params in egw_action_common.ts (shared, out of scope) - cast to bypass the arg-count check
			return (<any>sprintf)('%d:%02d', secs / 60, secs % 60);
		}
		return (<any>sprintf)('%d:%02d:%02d', secs / 3600, (secs % 3600)/60, secs % 60);
	}

	/**
	 * Get the correct comment viewing grid, depending on preference
	 *
	 * @return {et2_grid}
	 */
	get commentGrid()
	{
		const comments = this.et2.querySelectorAll<Et2Template>("et2-template[id$='smallpart-student-comment']");
		comments.forEach(comment => {comment.hidden = true});
		const comment_on_top = this.et2.getWidgetById('comment_on_top')?.checked;
		const comment_template = comments[comment_on_top ? 1 : 0];
		if(comment_template)
		{
			comment_template.hidden = false;
		}

		return <et2_grid>comment_template?.getWidgetById('comment');
	}

	_student_resize()
	{
		let comments = this.et2?.getWidgetById('comments')?.getDOMNode();
	}

	student_saveAndCloseCollabora ()
	{
		const content = this.et2.getArrayMgr('content');
		const clml = <et2_smallpart_cl_measurement_L><unknown>this.et2.getDOMWidgetById('clm-l');
		const inTestMode = parseInt(content.getEntry('video')?.video_test_duration) > 0 && content.getEntry('timer') > 0;

		if ((content.getEntry('course_options') & et2_smallpart_videobar.course_options_cognitive_load_measurement)
			== et2_smallpart_videobar.course_options_cognitive_load_measurement &&
			inTestMode && clml?.get_mode() === et2_smallpart_cl_measurement_L.MODE_CALIBRATION)
		{
			return;
		}

		(<HTMLElement>document.getElementsByClassName('note_container')[0]).style.display = 'none';
		(<any>document.querySelector<HTMLIFrameElement>('iframe[id$="_note"]').contentWindow).app.collabora.WOPIPostMessage('Action_Save');
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
		let data = this.comments.filter(e => {if (e.comment_id == id) return e;})
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
		this.edited = Object.assign({}, _selected[0].data);
		this.edited.action = _action.id;
		this.edited.comment_cat_sub = this.edited?.comment_cat?.split(':')[1];
		let videobar = <et2_smallpart_videobar>this.et2.getWidgetById('video');
		const comments_slider = <et2_smallpart_videooverlay_slider_controller><unknown>this.et2.getDOMWidgetById('comments_slider');
		const videooverlay = <et2_smallpart_videooverlay><unknown>this.et2.getDOMWidgetById('videooverlay');
		const comment = this.commentGrid;
		let self = this;
		let content = videobar.getArrayMgr('content').data;

		// Do not seek for comments when we are in not seekable
		if (_action.id == 'open' && !content.is_staff && (content.video.video_test_options
			& et2_smallpart_videobar.video_test_option_not_seekable)) return;

		// record in case we're playing
		this.record_watched();
		videobar.seek_video(this.edited.comment_starttime);
		// start recording again, in case we're playing
		if (!videobar.paused()) this.start_watching();
		videobar.set_marking_enabled(true, function(){
			self._student_controlCommentAreaButtons(false);
		});
		videobar.set_marking_readonly(true);
		videobar.setMarks(this.edited.comment_marked);
		videobar.setMarksState(true);
		videobar.setMarkingMask(true);
		this.student_playVideo(true);
		this._student_setCommentArea(true);
		if (comment)
		{
			let action = _action.id;
			this.edited.save_label = this.egw.lang('Save');
			// readonly --> disable edit and retweet
			if (this.et2.getArrayMgr('content').getEntry('video').accessible === 'readonly')
			{
				action = 'open';
			}
			// Open as open, not edit if there are replies
			if(action == "edit" && _action.parent && this.edited["comment_added"].length > 1)
			{
				action = 'open';
			}
			// Give SmallPartComment widget what it needs
			this.edited["comment_added"] = {
				// @ts-ignore
				course_id: this.edited.course_id,
				video_id: this.edited.video_id,
				comment_id: this.edited.comment_id,
				comment: this.edited.comment_added
			};
			switch(action)
			{
				case 'retweet':
					this.edited.save_label = this.egw.lang('Retweet');
					// Hide videobar timepicker buttons
					['start-time-picker', 'stop-time-picker'].forEach(_w => {this.et2.getWidgetById(smallpartApp.playControlBar).getWidgetById(_w).hidden = true});

				// fall through
				case 'edit':
					if (_action.id == 'edit') videobar.set_marking_readonly(false);
					const content_cats = this.et2.getArrayMgr('content').getEntry('cats');
					this.edited = {...this.edited, ...{
							text: this.edited.comment_added['comment'][0],
						video_duration: videobar.duration(),
						attachments_list: this.edited['/apps/smallpart/'
							+this.edited.course_id+'/'+this.edited.video_id+'/' + this.edited.account_lid
							+'/comments/'+this.edited.comment_id+'/'],
						comment_cat: this.edited.comment_cat ?? (content_cats ? content_cats[0]['cat_id'] : null),
						free_comment_only: this.et2.getArrayMgr('content').getEntry('comment')?.free_comment_only,
					}};
					if(_action.id == 'edit')
					{
						this.edited["comment_added"] = undefined;
					}
					comment.set_value({content: this.edited});
					if(_action.id == 'edit')
					{
						// Need the widget's new value
						comment.getWidgetById("comment_cat")?.updateComplete.then(() =>
						{
							this.student_commentCatChanged(null, comment.getWidgetById("comment_cat"));
						});
					}
					comments_slider?.disableCallback(true);
					videooverlay.getElementSlider().disableCallback(true);
					break;

				case 'open':
					let hideMaskPlayArea = this.et2.getWidgetById('hideMaskPlayArea');
					hideMaskPlayArea.set_disabled(false);
					hideMaskPlayArea.value = '';
					document.getElementsByClassName('markingMask')[0].classList.remove('maskOn')
					const cats = this.edited?.comment_cat?.toString()?.split(":")||[];
					const free_comment_only = this.et2.getArrayMgr('content').getEntry('comment')?.free_comment_only;
					const accessible = this.et2.getArrayMgr('content').getEntry('video')?.accessible;
					comment.set_value({content:{
						comment_id: this.edited.comment_id,
						comment_added: this.edited.comment_added,
						comment_starttime: this.edited.comment_starttime,
						comment_stoptime: this.edited.comment_stoptime,
						comment_marked_message: !free_comment_only,
						free_comment_only: free_comment_only,
							comment_cat: this.edited.comment_cat ?? (content_cats ? content_cats[0]['cat_id'] : null),
							comment_cat_sub: this.edited.comment_cat_sub,
						accessible: accessible,
						action: action,
							attachments_list: this.edited['/apps/smallpart/'
							+ this.edited.course_id + '/' + this.edited.video_id + '/' + this.edited.account_lid
							+ '/comments/' + this.edited.comment_id + '/'],
						video_duration: videobar.duration()
					}});
					// Make sure we can show current category, regardless of type
					comment.getWidgetById("comment_cat").categoryType = "";
					comment.getWidgetById('comment_editBtn')?.set_disabled(!(this.is_staff || this.edited.account_id == egw.user('account_id')) || accessible === 'readonly');
					comment.getWidgetById("comment_added").editable = (this.is_staff || this.edited.account_id == egw.user('account_id'));
					if (comments_slider)
					{
						comments_slider.disableCallback(false);
						videooverlay.getElementSlider().disableCallback(false);
						const tag = comments_slider.getChildren().filter(_item=>{
							return _item.id === 'slider-tag-'+self.edited.comment_id;
						});
						comments_slider.set_selected(tag.length>0?tag[0]:null);
						// Hide videobar timepicker buttons
						['start-time-picker', 'stop-time-picker'].forEach(_w => {this.et2.getWidgetById(smallpartApp.playControlBar).getWidgetById(_w).hidden = true});
					}
			}

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

			// Show / hide attachment dropdown if there's already a file uploaded
			const attachment_key = Object.keys(this.edited).find(k => k.startsWith("/apps/smallpart/"));
			(<HTMLElement>comment.getWidgetById("attachment_list").querySelector("[slot='trigger']")).hidden =
				Object.values(comment.getArrayMgr("content").getEntry("attachments") ?? []).length == 0 &&
				!this.edited[attachment_key]?.length
		}
		this._student_controlCommentAreaButtons(true);
	}

	/**
	 * Re-evaluate starttime/stoptime max&min values
	 * @param _node
	 * @param _widget
	 */
	student_checkCommentStarttime(_node, _widget)
	{
		const stoptime = _widget.getInstanceManager()._widgetContainer.getWidgetById('comment_stoptime')
		const starttime = _widget.getInstanceManager()._widgetContainer.getWidgetById('comment_starttime');
		if (_widget.id == starttime.id)
		{
			starttime.set_max(stoptime.get_value());
			if (starttime.get_value() < stoptime.get_value()) stoptime.set_min(starttime.get_value());
		}
		else
		{
			stoptime.set_min(starttime.get_value());
			starttime.set_max(_widget.get_value());
		}
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
		if((content.getEntry('course_options') & et2_smallpart_videobar.course_options_cognitive_load_measurement)
			!= et2_smallpart_videobar.course_options_cognitive_load_measurement || !content.getEntry('clm')['post']['active'])
		{
			return;
		}


		let post = content.getEntry('clm')['post'];

		// Fix wrong data types / overlap between disabled & value
		post.qa1_disabled = !post.qa1;
		delete post.qa1;
		post.qa2_disabled = !post.qa2;
		delete post.qa2;

		if(typeof post.questions === 'object')
		{
			post.questions = Object.values(post.questions);
			// first index is reserved for grid and question index starts from 1
			if(post.questions[0]['q'])
			{
				post.questions.unshift({});
			}
		}

		const dialog = new Et2Dialog(this.egw);
		dialog.transformAttributes({
			callback: _callback,
			title: '',
			buttons: [
				{label: this.egw.lang("Continue"), id: "continue"},
			],
			width: 500,
			value: {content: post},
			class: 'questionnaire',
			hideOnEscape: false,
			noCloseButton: true,
			template: this.egw.webserverUrl + '/smallpart/templates/default/post_cl_questions.xet'
		});
		document.body.appendChild(dialog);
	}

	public student_CLM_L(mode)
	{
		//disable CLML feature for now.
		const clml = <et2_smallpart_cl_measurement_L><unknown>this.et2.getDOMWidgetById('clm-l');
		clml.set_mode(mode);
		return clml.start();
	}

	public student_testFinished(_widget)
	{
		const content = this.et2.getArrayMgr('content');
		const widget = _widget;
		// self is still needed below - the nested dialog callback's own "this" is rebound to the dialog by
		// Et2Dialog's transformAttributes({callback: ...}) contract, so it can't use "this" for the app instance
		let self = this;
		const callback = (_w) => {
			(<et2_smallpart_cl_measurement_L><unknown>this.et2.getDOMWidgetById('clm-l')).stop();
			this._student_noneTestAreaMasking(false);
			if ((content.getEntry('course_options') & et2_smallpart_videobar.course_options_cognitive_load_measurement)
				== et2_smallpart_videobar.course_options_cognitive_load_measurement && content.getEntry('clm')['post']['active'])
			{
				// record a stop time once before post questions and after user decided to finish the test
				this.egw.request('smallpart.\\EGroupware\\SmallParT\\Student\\Ui.ajax_recordCLMeasurement', [
					content.getEntry('video')?.course_id, content.getEntry('video')?.video_id,
					smallpartApp.CLM_TYPE_STOP, []
				]);

				let timer = <et2_countdown><unknown>this.et2.getDOMWidgetById('timer');
				// reset the alarms after the test is finished
				timer.options.alarm = [];

				this._student_setPostCLQuestions(function (_button, _value)
				{
					if(_button === "continue" && Object.keys(_value).length > 0)
					{
						self.egw.request('smallpart.\\EGroupware\\SmallParT\\Student\\Ui.ajax_recordCLMeasurement', [
							content.getEntry('video')?.course_id, content.getEntry('video')?.video_id,
							smallpartApp.CLM_TYPE_POST, _value
						]);
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
				Et2Dialog.show_dialog((_button) =>
				{
					if(_button == Et2Dialog.YES_BUTTON)
					{
						callback(_widget);
					}
				}, this.egw.lang('If you finish the test, you will not be able to enter it again!'), this.egw.lang('Finish test?'));
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
		const timer = <et2_countdown><unknown>this.et2.getDOMWidgetById('timer');

		// make sure timer is there before accessing it. the widget might not be present in some cases, eg. before test
		// get started.
		if (timer)
		{
			timer.options.alarm = alarms;
			// callback to be called for alarm; onAlarm is a dynamic "js"-type attribute, not a declared class field
			(<any>timer).onAlarm = () => {
				let d = dialog();
				replyTimeout = setTimeout(function(){
					this.querySelector("et2-button").click();
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

			const dialog = new Et2Dialog(this.egw);
			dialog.transformAttributes({
				callback: function(_button, _value)
				{
					if(_button === "continue" && Object.keys(_value).length > 0)
					{
						self.egw.request('smallpart.\\EGroupware\\SmallParT\\Student\\Ui.ajax_recordCLMeasurement', [
							content.getEntry('video')?.course_id, content.getEntry('video')?.video_id,
							smallpartApp.CLM_TYPE_PROCESS, _value
						]);
						clearTimeout(replyTimeout);
					}
				},
				buttons: [{label: this.egw.lang("Continue"), id: "continue"}],
				width: 400,
				value: {content: {questions: questions}},
				template: egw.webserverUrl + '/smallpart/templates/default/process_cl_questions.xet',
				class: 'questionnaire clm-process',
				isModal: true,
				hideOnEscape: false,
				noCloseButton: true,
			});
			document.body.appendChild(dialog);
			return dialog;
		};
	}

	private _student_clm_l_start()
	{
		const clml =  <et2_smallpart_cl_measurement_L><unknown>this.et2.getDOMWidgetById('clm-l');
		const timer = <et2_countdown><unknown>this.et2.getDOMWidgetById('timer');

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
				// reset the timer (accessing et2_countdown's private interval handle directly, no public reset API exists)
				clearInterval((<any>timer).timer);

				(<HTMLElement>document.getElementsByClassName('timerBox')[0]).style.display = 'none';
				document.querySelector<HTMLElement>('form[id^="smallpart-student-"]').style.visibility = 'hidden';
				(<HTMLElement>document.getElementsByClassName('commentBoxArea')[0]).style.display = 'block';

				const dialog = new Et2Dialog(this.egw);
				dialog.transformAttributes({
					callback: function()
					{
						document.querySelector<HTMLElement>('form[id^="smallpart-student-"]').style.visibility = '';
						// start the CLM "L" calibration process
						self.student_CLM_L(et2_smallpart_cl_measurement_L.MODE_CALIBRATION).then(_ =>
						{
							// set the timer again
							timer.set_value(content.getEntry('timer'));
							if(!content.getEntry('comments') || content.getEntry('comments').length <= 1)
							{
								(<HTMLElement>document.getElementsByClassName('commentBoxArea')[0]).style.display = 'none';
							}
							(<HTMLElement>document.getElementsByClassName('timerBox')[0]).style.display = 'block';
							self._student_setProcessCLQuestions();
							// run the CLM "L" in running mode
							self.student_CLM_L('running');
						});
					},
					buttons: Et2Dialog.BUTTONS_OK,
					title: this.egw.lang('Measurement by dual task (Calibration and measurement of cognitive load)'),
					closeOnEscape: false,
					noCloseButton: true,
					width: 400,
					template: egw.webserverUrl + '/smallpart/templates/default/clm_L_calibration_message.xet?'
				});
				document.body.appendChild(dialog);
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
		const content = this.et2.getArrayMgr('content');
		const forbidTocomment = (this.is_staff && content.getEntry('video')?.video_options == smallpartApp.COMMENTS_FORBIDDEN_BY_STUDENTS)
			|| content.getEntry('video')?.video_options == smallpartApp.COMMENTS_DISABLED;

		try {
			// Show / Hide videobar timepicker buttons
			['start-time-picker', 'stop-time-picker'].forEach(_w =>
			{
				const button = this.et2.getWidgetById(smallpartApp.playControlBar).getWidgetById(_w)
				button.disabled = !_state;
				button.hidden = false;
			});

			// Enable / Disable add note/comments buttons
			['add_comment', 'add_note'].forEach(_w => {this.et2.getWidgetById(smallpartApp.playControlBar).getWidgetById(_w).disabled = _state;});
			(<any>this.et2.getWidgetById('smallpart.student.comments_list').getWidgetById('add_comment')).disabled = _state;

			const comments = this.et2.querySelectorAll<Et2Template>("et2-template[id$='smallpart-student-comment']").forEach(comment => {(<any>comment).disabled = !_state});
			this.et2.setDisabledById('hideMaskPlayArea', true);
			this._student_resize();
		}
		catch (e) {}
	}
	public student_playControl(_status: string|Et2Select)
	{
		let videobar = <et2_smallpart_videobar>this.et2.getWidgetById('video');
		// volume is an et2-description label, playback an et2-select - not videobars (fixed mistyped casts)
		let volume = <Et2Description>this.et2.getWidgetById('volume');
		let playback = <Et2Select>this.et2.getWidgetById('playback');
		let videooverlay = <et2_smallpart_videooverlay>this.et2.getWidgetById('videooverlay');
		let selectedIndex = playback.select_options.findIndex(a=>a.value == playback.value);

		if (_status && typeof _status !== 'string' && _status.nodeName === 'ET2-SELECT')
		{
			videobar.set_playBackRate(parseFloat(String(_status.value)));
			return;
		}

		switch (_status)
		{
			case "playback_fast":
				if (selectedIndex < playback.select_options.length-1)
				{
					playback.set_value(playback.select_options[selectedIndex+1].value);
				}
				break;
			case "playback_slow":
				if (selectedIndex > 0)
				{
					playback.set_value(playback.select_options[selectedIndex-1].value);
				}
				break;
			case "start-time-picker":
			case "stop-time-picker":
				this.commentGrid.getWidgetById("comment_timespan").timePicker(_status == "start-time-picker" ? "starttime" : "stoptime");
				break;
			case "forward":
				if (videobar.currentTime()+10 <= videobar.duration())
				{
					videobar.seek_video(videobar.currentTime()+10);
					videooverlay.getElementSlider().set_seek_position(Math.round(videobar._vtimeToSliderPosition(videobar.currentTime())));
				}
				break;
			case "backward":
				if (videobar.currentTime()-10 >= 0)
				{
					videobar.seek_video(videobar.currentTime() - 10);
					videooverlay.getElementSlider().set_seek_position(Math.round(videobar._vtimeToSliderPosition(videobar.currentTime())));
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
				let fullwidth = <any>this.et2.getDOMWidgetById('fullwidth');
				let clml = <et2_smallpart_cl_measurement_L>this.et2.getWidgetById('clm-l');
				document.querySelector("#smallpart-student-index > div > et2-box").classList.toggle('fullscreen-video');
				if(fullwidth.image == 'fullscreen')
				{
					fullwidth.image = 'fullscreen-exit';
					fullwidth.requestUpdate("image");
					if(clml && clml.getDOMNode())
					{
						clml.getDOMNode().classList.add('fixed-l');
					}
				}
				else
				{
					fullwidth.image = 'fullscreen';
					fullwidth.requestUpdate("image");
					if(clml && clml.getDOMNode())
					{
						clml.getDOMNode().classList.remove('fixed-l');
					}
				}
				// resize resizable widgets
				[videobar, 'comments_slider'].forEach((_w) => {
					let w : any = (typeof _w === 'string') ? <et2_widget><unknown>this.et2.getDOMWidgetById(_w) : _w;
					w?.resize(0);
				});
				break;
			// pdf page controllers
			case "pgnxt":
				if (videobar.duration() > videobar.currentTime())
				{
					videobar.seek_video(videobar.currentTime() + 1);
					videooverlay.getElementSlider().set_seek_position(Math.round(videobar._vtimeToSliderPosition(videobar.currentTime())));
				}
				break;
			case "pgprv":
				if (videobar.currentTime() > 1)
				{
					videobar.seek_video(videobar.currentTime() - 1);
					videooverlay.getElementSlider().set_seek_position(Math.round(videobar._vtimeToSliderPosition(videobar.currentTime())));
				}
				break;
		}
	}

	public student_attachmentFinish()
	{
		(<any>this.et2.getDOMWidgetById('saveAndContinue')).set_disabled(false);
	}

	public student_attachmentStart(event)
	{
		(<any>this.et2.getDOMWidgetById('saveAndContinue')).set_disabled(true);

		// Open attachment list
		event.target.getParent().getWidgetById("attachment_list").show();

		// Show attachment dropdown trigger
		(<HTMLElement>event.target.getParent().getWidgetById("attachment_list").querySelector("[slot='trigger']")).hidden = false
		return true;
	}

	/**
	 * Highlights comment row based for the given comment id
	 * @param _comment_id
	 * @private
	 */
	private _student_highlightSelectedComment(_comment_id)
	{
		let commentsGrid : HTMLElement = this.et2.getWidgetById('comments').getDOMNode();
		let scrolledComment : HTMLElement = commentsGrid.querySelector('tr.commentID' + _comment_id);
		if (scrolledComment.className.indexOf('hideme')<0)
		{
			commentsGrid.querySelectorAll<HTMLElement>(smallpartApp.commentRowsQuery).forEach(row => row.classList.remove('highlight'));
			scrolledComment.classList.add('highlight');
			commentsGrid.scrollTop = scrolledComment.offsetTop;
		}
	}

	public student_playVideo(_pause: boolean)
	{
		let videobar = <et2_smallpart_videobar>this.et2.getWidgetById('video');
		let play = this.et2.getWidgetById('play');
		let content = this.et2.getArrayMgr('content');
		if(play.image == 'pause-fill' || _pause)
		{
			videobar.pause_video();
			play.image = "play-circle";
		}
		else {
			this.start_watching();

			videobar.set_marking_enabled(false);
			if (!content.data.video.video_src.match(/pdf/))
			{
				videobar.play_video(
					() => {
						play.image = "play-fill";
						if (!(videobar.getArrayMgr('content').getEntry('video')['video_test_options'] & et2_smallpart_videobar.video_test_option_not_seekable)) {
							play.image = 'arrow-clockwise';
						}
						else {
							play.image = "play-fill";
						}
						// record video watched
						this.record_watched();
					},
					(_id) => {
						this._student_highlightSelectedComment(_id);
						let comments_slider = this.et2.getWidgetById('comments_slider');
						if (comments_slider)
						{
							comments_slider.set_selected(false);
						}
					});
			}
			play.image = "pause-fill";
		}
	}

	public student_dateFilter(changeEvent, _widget)
	{
		this._student_dateFilterSearch();
	}

	private _student_dateFilterSearch()
	{
		let rows = (<HTMLElement>this.et2.getWidgetById('comments').getDOMNode()).querySelectorAll<HTMLElement>(smallpartApp.commentRowsQuery);
		let ids = [];
		const comments = this.et2.getArrayMgr('content').getEntry('comments');
		const date = (<any>this.et2.getDOMWidgetById('comment_date_filter')).getValue();
		const from = date?.from ? new Date(date.from) : 0;
		let to : number | Date = Number.MAX_SAFE_INTEGER;
		if(date?.to)
		{
			to = new Date(date.to);
			to.setUTCHours(23, 59, 59);
		}

		rows.forEach(row => {
			let id = (row.classList.value.match(/commentID.*[0-9]/) ?? " ")[0].replace('commentID', '');
			let comment = comments.filter(_item=>{return _item.comment_id == id;});
			if (comment && comment.length>0) {
				let date_updated = new Date(comment[0].comment_updated.date);
				if(from <= date_updated && to >= date_updated)
				{
					ids.push(id);
				}
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
					(<any>this.et2.getDOMWidgetById('comment_search_filter')).getDOMNode().classList.add('searchall');
				}
				else
				{
					(<any>this.et2.getDOMWidgetById('comment_search_filter')).getDOMNode().classList.remove('searchall');
				}
				break;
			case 'date':
				let date = <any>this.et2.getDOMWidgetById('comment_date_filter');
				if(!_action.checked)
				{
					date.set_value({from: null, to: null});
				}
				this._student_dateFilterSearch();
				break;
			case 'attachments':
				this._student_filterAttachments(_action.checked);
				break;
			case 'marked':
				this._student_filterMarked(_action.checked);
				break;
			case 'hide_question_bar':
			case 'hide_text_bar':
				const widgetName = _action.value ?? "";
				(<any>this.et2.getDOMWidgetById(widgetName))?.getDOMNode()?.classList?.toggle("hideme", _action.checked);
				break
		}
	}

	public student_top_tools_actions(_action, _selected)
	{
		let video_id=this.et2.getValueById('videos');
		const content = this.et2.getArrayMgr('content');
		switch (_action.id)
		{
			case 'question':
				if (video_id) egw.open_link(egw.link('/index.php','menuaction=smallpart.EGroupware\\SmallParT\\Questions.index&video_id='+video_id+'&ajax=true&cd=popup'));
				break;
			case 'score':
				if (video_id) egw.open_link(egw.link('/index.php','menuaction=smallpart.EGroupware\\SmallParT\\Questions.scores&video_id='+video_id+'&ajax=true&cd=popup'));
				break;
			case 'note':
				if (video_id)
				{
					const iframe = <et2_iframe><unknown>this.et2.getDOMWidgetById('note');
					egw.request('EGroupware\\smallpart\\Student\\Ui::ajax_createNote',
						[content.getEntry('courses'),video_id]).then(_data =>
						{
							if (_data.path)
							{
								const clm_l = <et2_smallpart_cl_measurement_L><unknown>this.et2.getDOMWidgetById('clm-l');
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
								(<HTMLElement>document.getElementsByClassName('note_container')[0]).style.display = 'block';
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
		let comment = this.commentGrid;
		let videobar = <et2_smallpart_videobar>this.et2.getWidgetById('video');
		let comments_slider = <et2_smallpart_videooverlay_slider_controller><unknown>this.et2.getDOMWidgetById('comments_slider');
		let videooverlay = <et2_smallpart_videooverlay><unknown>this.et2.getDOMWidgetById('videooverlay');
		let self = this;
		this.student_playVideo(true);

		this._student_setCommentArea(true);
		videobar.set_marking_enabled(true, function(){
			self._student_controlCommentAreaButtons(false);
		});
		videobar.set_marking_readonly(false);
		videobar.setMarks(null);
		const content_cats = this.et2.getArrayMgr('content').getEntry('cats');
		this.edited = <CommentType><unknown>Object.assign(this.student_getFilter(), {
			account_lid: this.egw.user('account_lid'),
			comment_added: [''],
			text: "",
			comment_color: smallpartApp.default_color,
			action: 'edit',
			save_label: this.egw.lang('Save'),
			video_duration: videobar.duration(),
			comment_cat: this.et2.getArrayMgr("content").getEntry('config[no_free_comment]') ? '' : 'free'
		});

		comment.set_value({
			content: {
				...comment.getArrayMgr("content").data,
				...this.edited,
				comment_id: "",
				comment_starttime: Math.round(videobar.currentTime()),
				comment_stoptime: Math.round(videobar.currentTime()),
				attachments: []
			}
		});
		comment.getWidgetById('deleteComment')?.set_disabled(true);
		comment.getWidgetById("comment_cat")?.updateComplete.then(() =>
		{
			this.student_commentCatChanged(null, comment.getWidgetById("comment_cat"));
		});
		this._student_controlCommentAreaButtons(true);
		comments_slider?.disableCallback(true);
		videooverlay.getElementSlider().disableCallback(true);

		// Hide attachment dropdown until there's a file uploaded
		(<HTMLElement>comment.getWidgetById("attachment_list").querySelector("[slot='trigger']")).hidden = true;
	}

	addSpecialComment(action : EgwAction)
	{
		this.student_addComment();

		const cat_id = action.id.replace('sc_add_', '');
		const cat = this.commentGrid.getWidgetById("comment_cat");
		cat.disabled = true;
		cat.categoryType = 'sc';
		cat.value = cat_id;

		const sub = cat.getParent().getWidgetById("comment_cat_sub");
		sub.updateComplete.then(() => { sub.disabled = true;});
	}

	/**
	 * Cancel edit and continue button callback
	 */
	public student_cancelAndContinue()
	{
		let videobar = <et2_smallpart_videobar>this.et2.getWidgetById('video');
		let filter_toolbar = this.et2.getDOMWidgetById('filter-toolbar');
		let comments_slider = <et2_smallpart_videooverlay_slider_controller><unknown>this.et2.getDOMWidgetById('comments_slider');
		let videooverlay = <et2_smallpart_videooverlay><unknown>this.et2.getDOMWidgetById('videooverlay');
		videobar.removeMarks();
		this.student_playVideo((<any>this.et2.getDOMWidgetById('pauseaftersubmit')).checked);
		delete this.edited;
		this._student_setCommentArea(false);

		// Update attachments in content, if they've added / removed a temp file
		let data = this.commentGrid.getArrayMgr("content");
		let attachments = data.getEntry("attachments", true);
		data.data.attachments = this.commentGrid.getWidgetById("attachments").value;

		this.et2.getWidgetById('smallpart.student.comment').set_disabled(true);
		comments_slider?.disableCallback(false);
		videooverlay.getElementSlider().disableCallback(false);
	}

	/**
	 * Save comment/retweet and continue button callback
	 */
	public student_saveAndContinue()
	{
		let comment = this.commentGrid;
		let videobar = <et2_smallpart_videobar>this.et2.getWidgetById('video');

		const mainCat = comment.getWidgetById("comment_cat")?.value;
		const attachments : any = comment.getWidgetById("attachments")?.getValue() ?? {};
		let text = this.edited.action === 'retweet' ? comment.getWidgetById('retweet')?.get_value() :
				   comment.getWidgetById('text')?.get_value();

		// If we have a comment and either a category or attachments, we can save
		if(text && (mainCat || Object.values(attachments).length > 0))	// ignore comments with neither an attachment nor main category
		{
			this.egw.request('smallpart.\\EGroupware\\SmallParT\\Student\\Ui.ajax_saveComment', [
				this.et2.getInstanceManager().etemplate_exec_id,
				Object.assign(this.edited, {
					// send action and text to server-side to be able to do a proper ACL checks
					action: this.edited.action,
					text: text,
					comment_color: comment.getWidgetById('comment_color')?.get_value() || this.edited.comment_color,
					comment_cat: comment.getWidgetById('comment_cat')?.value +
					(comment.getWidgetById('comment_cat_sub')?.value ? ':'+comment.getWidgetById('comment_cat_sub')?.value : '') || null,
					comment_starttime: comment.getWidgetById('comment_timespan')?.starttime || videobar.currentTime(),
					comment_stoptime: comment.getWidgetById('comment_timespan')?.stoptime || 1,
					comment_marked: videobar.getMarks(),
					attachments: Object.values(attachments).map((f:any) => f.name)
				}),
				this.student_getFilter()
			]);
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

		Et2Dialog.show_dialog(function(_button)
		{
			if(_button === Et2Dialog.YES_BUTTON)
			{
				self.egw.request('smallpart.\\EGroupware\\SmallParT\\Student\\Ui.ajax_deleteComment', [
					self.et2.getInstanceManager().etemplate_exec_id,
					comment_id,
					self.student_getFilter()
				]);

				// do we need to clean up the edit-area
				if (comment_id == self.edited?.comment_id) self.student_cancelAndContinue();
			}
		}, this.egw.lang('Delete this comment?'), this.egw.lang('Delete'), null, Et2Dialog.BUTTONS_YES_NO);
	}

	/**
	 * Get current active filter
	 */
	protected student_getFilter() : {course_id : number|undefined, video_id : number|undefined}
	{
		const courses: Et2Select|undefined = this.et2?.getWidgetById('courses');
		const videos: Et2Select|et2_grid|undefined = this.et2?.getWidgetById('videos');
		return {
			course_id: courses?.get_value() || this.filter?.course_id,
			video_id: (<any>videos)?.get_value ? (<any>videos).get_value() : this.filter?.video_id,
		}
	}

	/**
	 * apply group filter
	 * @param _node
	 * @param _widget
	 */
	public student_filterGroup(_node, _widget)
	{
		let rows = (<HTMLElement>this.et2.getWidgetById('comments').getDOMNode()).querySelectorAll<HTMLElement>(smallpartApp.commentRowsQuery);
		let ids = [];
		const accounts = this.et2.getArrayMgr('sel_options').getEntry('account_id');
		const comments = this.et2.getArrayMgr('content').getEntry('comments');
		const group = _widget.get_value();
		rows.forEach(row => {
			let id = row.classList.value.match(/commentID.*[0-9]/)[0].replace('commentID','');
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
		let rows = (<HTMLElement>this.et2.getWidgetById('comments').getDOMNode()).querySelectorAll<HTMLElement>(smallpartApp.commentRowsQuery + '.commentMarked');
		let ids = [];
		rows.forEach(item => {
			ids.push(item.classList.value.match(/commentID.*[0-9]/)?.[0].replace('commentID',''));
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
		let rows = (<HTMLElement>this.et2.getWidgetById('comments').getDOMNode()).querySelectorAll<HTMLElement>(smallpartApp.commentRowsQuery + '.commentAttachments');
		let ids = [];
		rows.forEach(item => {
			ids.push(item.classList.value.match(/commentID.*[0-9]/)?.[0].replace('commentID',''));
		});
		if(ids.length == 0 && _state)
		{
			ids = ['ALL'];
		}
		this._student_commentsFiltering('attachments', _state ? ids : []);
	}

	/**
	 * Apply cats filter
	 * @param _event
	 * @param _widget
	 */
	public student_catsFilter(_event, _widget)
	{
		let rows = [];
		let ids = [];
		_widget.value.forEach(_v => {
			rows = rows.concat([...this.et2.getWidgetById('comments').getDOMNode().getElementsByClassName('cat-'+_v)]);
		});

		rows.forEach((item) => {
			ids.push(item.classList.value.match(/commentID.*[0-9]/)?.[0].replace('commentID',''));
		});
		this._student_commentsFiltering('cats', (ids.length || !_widget.value.length ? ids :['ALL']));
	}

	/**
	 * Apply (changed) comment filter
	 *
	 * Filter is applied by hiding filtered rows client-side
	 */
	public student_filterComments()
	{
		let value = this.et2.getWidgetById('comment_color_filter')?.get_value();
		let rows : HTMLElement[] = [];
		if (!value) return;
		const commentsNode : HTMLElement = this.et2.getWidgetById('comments').getDOMNode();
		switch(value)
		{
			case 'ac':
				rows = Array.from(commentsNode.querySelectorAll<HTMLElement>(smallpartApp.commentRowsQuery + ':not(.cat-lf, .cat-lfc)'));
				break;
			case 'lf':
				rows = Array.from(commentsNode.querySelectorAll<HTMLElement>(smallpartApp.commentRowsQuery + '.cat-lf, ' + smallpartApp.commentRowsQuery + '.cat-lfc'));
				break;
			case 'all':
				rows = [];
				break;
			case 'new':
				const lastUpdated = new Date(this.et2.getArrayMgr("content").getEntry("video[last_updated][date]"));
				rows = Array.from(commentsNode.querySelectorAll<HTMLElement>(smallpartApp.commentRowsQuery)).filter(row =>
				{
					const commentID = row.classList.value.match(/commentID.*[0-9]/)?.[0].replace('commentID', '');
					const comment = (<smallpartApp>app.smallpart).comments.find(_item => _item.comment_id == <any>commentID) ?? null;
					if(!commentID || !comment)
					{
						return false;
					}
					return new Date(comment.comment_updated.date) > lastUpdated;
				});
				break;
		}
		let ids = [];
		rows.forEach(item => {
			ids.push(item.classList.value.match(/commentID.*[0-9]/)?.[0].replace('commentID',''));
		});
		this._student_commentsFiltering('color', (ids.length ? ids : (value!=="all" ? ['ALL'] : [])));
	}

	public specialCategoryFilter(event : Event, widget)
	{
		const toggle = widget?.value || false;
		const commentTemplate = this.et2.getWidgetById("smallpart.student.comments_list");

		// Show / hide all special category comments
		commentTemplate.classList.toggle('special_categories', toggle);

		// If we need to show only some comments from special categories, here are 2 different ways to do it:
		// Comment rows filtered by category - fast CSS only
		/*
		const cat_id = action.id.replace('sc_', '');
		const selector = '.special_categories table[id$="_comments"] tr.commentBox.cat-';
		if(commentTemplate.dataset.specialCategory)
		{
			this.egw.css(selector + commentTemplate.dataset.specialCategory);
		}
		if(toggle)
		{
			this.egw.css(selector + cat_id, 'display: table-row');
		}
		commentTemplate.dataset.specialCategory = toggle ? cat_id : null;

		 */
		// Comment rows filtered by category using filter
		/*
		commentTemplate.getWidgetById("comment_cats_filter").value = cat_id;
		commentTemplate.dispatchEvent(new Event('change'));

		 */

		// Set class on add buttons to force showing their icon + label
		commentTemplate.getWidgetById("special_category_toolbar").querySelectorAll("[data-group='add_buttons'] et2-button").forEach(button =>
		{
			button.classList.add("toolbar--needsCaption");
		});

		// Change filter options
		const filter = commentTemplate.getWidgetById("comment_color_filter");
		['ac' /* no live comments */, 'lf' /* only live comments */].forEach(v =>
		{
			const option = filter.select_options.find(option => option.value === v);
			option.disabled = toggle;
			(<HTMLOptionElement>filter.shadowRoot.querySelector("[value='" + option.value + "']")).disabled = option.disabled;
		});
		filter.select_options.find(option => option.value === 'lf').disabled = toggle; // Only live comments

		// change category filter options
		const catFilter = commentTemplate.getWidgetById("comment_cats_filter");
		catFilter.categoryType = toggle ? 'sc' : '!sc';
	}

	public student_clearFilter()
	{
		this.et2.getWidgetById('comment_color_filter').set_value("all");
		this.et2.getWidgetById('comment_search_filter').set_value("");
		this.et2.getWidgetById('activeParticipantsFilter').set_value("");
		this.et2.getWidgetById('group').set_value("");
		(<any>this.et2.getDOMWidgetById('comment_date_filter')).set_value({from: null, to: null});
		(<any>this.et2.getDOMWidgetById('comment_cats_filter')).value = [];
		for (let f in this.filters)
		{
			this._student_commentsFiltering(f,[]);
		}
	}

	public student_searchFilter(_event, _widget)
	{
		let query = _widget.get_value();
		let rows = (<HTMLElement>this.et2.getWidgetById('comments').getDOMNode()).querySelectorAll<HTMLElement>(smallpartApp.commentRowsQuery);
		let ids = [];
		let filter_toolbar : any = this.et2.getDOMWidgetById('filter-toolbar');
		const reg = new RegExp(query, 'i');
		rows.forEach(row => {
			// does this row contain at least one descendant whose text matches the query (case-insensitive),
			// restricted to .et2_smallpart_comment elements unless "search all" is checked
			const searchAll = !!filter_toolbar._actionManager.getActionById('searchall').checked;
			const found = Array.from(row.querySelectorAll<HTMLElement>('*')).some(a => {
				let t = a.textContent || a.innerText || "";
				return reg.test(t) && (searchAll || a.classList.contains('et2_smallpart_comment'));
			});

			if (query != '' && found)
			{
				ids.push(row.classList.value.match(/commentID.*[0-9]/)[0].replace('commentID',''));
			}
		});
		this._student_commentsFiltering('search', ids.length == 0 && query != ''? ['ALL']:ids);
	}

	// Stable references so addEventListener/removeEventListener target the same handler (student_onmouseoverFilter toggles them on/off)
	private _onmouseoverEnter = () =>
	{
		if(this.et2.getWidgetById('play').image == "pause-fill"
			&& (!this.edited || this.edited?.action != 'edit')) (<et2_smallpart_videobar>this.et2.getWidgetById('video')).pause_video();
	};
	private _onmouseoverLeave = () =>
	{
		if(this.et2.getWidgetById('play').image == "pause-fill"
			&& (!this.edited || this.edited?.action != 'edit')) (<et2_smallpart_videobar>this.et2.getWidgetById('video')).play();
	};

	public student_onmouseoverFilter(_state)
	{
		let comments : HTMLElement = this.et2.getDOMNode().querySelector(".rightBoxArea");
		if (_state)
		{
			comments.addEventListener('mouseenter', this._onmouseoverEnter);
			comments.addEventListener('mouseleave', this._onmouseoverLeave);
		}
		else
		{
			comments.removeEventListener('mouseenter', this._onmouseoverEnter);
			comments.removeEventListener('mouseleave', this._onmouseoverLeave);
		}
	}

	/**
	 *
	 *
	 * @param _comments
	 */
	setCommentsSlider(_comments)
	{
		const comments_slider = <et2_smallpart_videooverlay_slider_controller><unknown>this.et2.getDOMWidgetById('comments_slider');
		const account_ids = this.et2.getArrayMgr('sel_options').data.account_id;
		const cats = this.et2.getArrayMgr('sel_options').getEntry('catsOptions');
		comments_slider.set_value(_comments?.map(_item => {
			const cat = cats.filter(_cat => {return _cat.value == _item?.comment_cat?.split(":")[0]}) || [];
			return {
				id: _item.comment_id,
				starttime: _item.comment_starttime,
				duration: _item.comment_stoptime - _item.comment_starttime,
				color: cat.length>0 ? cat[0].color : '#FFFFFF',
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
		(<Et2Box>this.et2.getWidgetById('smallpart.student.comments_list')?.getParent())?.set_disabled(this.comments?.length<=1);

		// update grid
		let comments = <et2_grid>this.et2.getWidgetById('comments');
		comments?.set_value(_data);

		// Turn on tab
		if(comments)
		{
			(<any>comments.getDOMNode().closest("et2-tabbox").querySelector("[panel='comment']")).disabled = false;
			(<any>comments.getDOMNode().closest("et2-tab-panel")).disabled = false;
		}

		// update slider-tags
		let videobar = <et2_smallpart_videobar>this.et2.getWidgetById('video');
		videobar?.set_slider_tags(this.comments);

		// update comments slider
		this.setCommentsSlider(this.comments);

		// re-apply the filter, if not "all"
		let applyFilter = false;
		['comment_color_filter', 'comment_search_filter', 'group', 'comment_date_filter'].forEach((_id) => {
			if (this.et2.getWidgetById(_id)?.get_value()) applyFilter = true;
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
		let is_readonly = !_widget.getValue();
		videobar.setMarksState(!is_readonly);
		let ids = ['markedColorRadio', 'revertMarks' , 'deleteMarks', 'backgroundColorTransparency'];
		for(let i in ids)
		{
			let widget = (<Et2Template><unknown>this.commentGrid).getWidgetById(ids[i]);
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
					case 'backgroundColorTransparency':
						widget.disabled = state;
						widget.value = !state;
						videobar.setMarkingMask(!state);
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

		this.et2.getWidgetById('play').getDOMNode().classList.remove('bi-arrow-clockwise')
	}

	public student_comments_column_switch(_node, _widget)
	{
		const comments = <any>this.et2.getDOMWidgetById('comments');
		if(!_widget.getValue())
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
			// revertMarks/deleteMarks are <et2-button-icon>, a ButtonMixin(SlIconButton) sibling of Et2Button, not a subclass of it
			let widget = <Et2ButtonIcon><unknown>(<Et2Template><unknown>this.commentGrid).getWidgetById(readonlys[i]);
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
		let rows = this.et2.getWidgetById('comments').getDOMNode().querySelectorAll(smallpartApp.commentRowsQuery);
		let tags = Array.from(document.querySelectorAll<HTMLElement>('.videobar_slider span.commentOnSlider'))
			.concat(Array.from(document.querySelectorAll<HTMLElement>('.et2_smallpart-videooverlay-slider-controller et2-description')));

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
				if (!this.comments[c] || (<any>this.comments[c]).length == 0) continue;
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
			if (!this.comments[i] || (<any>this.comments[i]).length == 0) continue;
			if (this.comments[i].filtered.length > 0)
			{
				rows.forEach((_row)=> {if(_row.classList.contains(`commentID${this.comments[i].comment_id}`)){_row.classList.add('hideme');}});
				tags.forEach((_tag) => {if(_tag.dataset.id == this.comments[i]?.comment_id?.toString()){_tag.classList.add('hideme')}});
			}
			else
			{
				rows.forEach((_row)=> {if(_row.classList.contains(`commentID${this.comments[i].comment_id}`)){_row.classList.remove('hideme');}});
				tags.forEach((_tag) => {if(_tag.dataset.id == this.comments[i]?.comment_id?.toString()){_tag.classList.remove('hideme')}});
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
		egw.accountData(parseInt(_id), 'account_fullname', null, (_d) => {
			if (Object.keys(_d).length>0)
			{
				let id = parseInt(Object.keys(_d)[0]);
				_options[id].label = _d[id];
			}
			egw.accountData(_id, 'account_firstname', null, (_n) => {
				if (Object.keys(_n).length>0)
				{
					let id = parseInt(Object.keys(_n)[0]);
					_options[id].name = _n[id]+'['+id+']';
					let newId = _stack.pop();
					if (newId)
					{
						this._student_fetchAccountData(newId, _stack, _options, _resolved);
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
		let options : any = {};
		const participants: any = this.et2.getArrayMgr('sel_options').getEntry('account_id');
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
				if (!this.comments[i] || (<any>this.comments[i]).length == 0) continue;
				let comment = this.comments[i];
				if (typeof options[comment.account_id] === 'undefined')
				{
					options[comment.account_id] = _getNames(comment.account_id);
				}
				options[comment.account_id] = Object.assign(options[comment.account_id], {
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
		}
	}

	/**
	 * course- or video-selection changed
	 *
	 * @param _node
	 * @param _widget
	 */
	courseSelection(_node : HTMLSelectElement, _widget : Et2Select)
	{
		this.record_watched();

		// remove excessive dialogs left over from previous video selection
		(<any>this.et2.getDOMWidgetById('videooverlay'))?.questionDialogs.forEach(_o => {_o.dialog.destroy()});

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
		_senders.forEach(_sender =>
		{
			ids.push(_sender.id.replace('smallpart::', ''));
		});
		switch (_action.id)
		{
			case 'subscribe':
			case 'open':
				this.egw.open(ids[0], 'smallpart', 'view', {cd: "no"}, '_self');
				break;

			default:
				this.egw.request('smallpart.\\EGroupware\\SmallParT\\Courses.ajax_action',
					[_action.id, ids, false, _password]);
				break;
		}
	}

	/**
	 * Distribute course-groups
	 *
	 * @param _node
	 * @param _widget
	 */
	changeCourseGroups(_node : HTMLSelectElement, _widget : Et2Button)
	{
		const groups = (<Et2Textbox>_widget.getParent().getWidgetById('course_groups'))?.get_value();
		const mode = (<Et2Select>_widget.getParent().getWidgetById('groups_mode'))?.get_value();
		if (mode && !groups)
		{
			Et2Dialog.alert(this.egw.lang('You need to set a number or size first!'));
		}
		// show() comes from the SlTabGroup mixin base and isn't visible to TS on Et2Tabs (see Et2Tabs.ts's own @ts-ignore on the same call) -
		// use it anyway rather than the @deprecated setActiveTab(), which only exists to wrap it for legacy call signatures.
		(<any>_widget.getRoot().getWidgetById('tabs'))?.show("participants_tab");
		// unfortunately we can not getWidgetById widgets in an auto-repeated grid
		const content = _widget.getArrayMgr('content').getEntry('participants');
		const values = _widget.getInstanceManager().getValues(_widget.getRoot().getWidgetById('participants')).participants;
		for(let row = 2, student = 0; typeof content[row] === 'object' && content[row] !== null; ++row)
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
	changeRole(_node : HTMLSelectElement, _widget : Et2Select)
	{
		const grid = _widget.getParent();
		const group = <Et2Textbox>grid.getWidgetById(_widget.id.replace('role', 'group'));
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
		Et2Dialog.show_prompt((button, nickname) =>
		{
			if(button === Et2Dialog.OK_BUTTON && (nickname = nickname.trim()) && nickname !== user.label)
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
		}, this.egw.lang('How do you want to be called?'), this.egw.lang('Change nickname'), user.label, Et2Dialog.BUTTONS_OK_CANCEL);
	}

	/**
	 * Change nickname in startpage
	 *
	 * @param nickname
	 */
	changeNicknameStartpage(nicknames : Array<any>)
	{
		if (!this.et2.getInstanceManager().name.match(/smallpart.start/)) return;

		const account_id = <et2_selectbox_ro>this.et2.getWidgetById('account_id');
		(<any>this.et2.getArrayMgr('sel_options').data).account_id = nicknames;
		account_id?.set_select_options(nicknames);
		account_id?.set_value(this.user);
	}

	/**
	 * Subscribe or open a course (depending on already subscribed)
	 *
	 * @param _id
	 * @param _subscribed
	 */
	openCourse(_id, _subscribed)
	{
		this.egw.open(_id, 'smallpart', 'view', '', '_self')
	}

	/**
	 * Clickhandler to copy given text or widget content to clipboard
	 * @param _widget
	 * @param _text default widget content
	 */
	copyClipboard(_widget : et2_DOMWidget, _text? : string, _event? : Event)
	{
		let value = _text || (typeof (<any>_widget).get_value === 'function' ? (<any>_widget).get_value() : (<any>_widget).options.value);
		let node = _widget.getDOMNode() !== <any>_widget ? _widget.getDOMNode() : _widget;
		this.egw.copyTextToClipboard(value, node, _event).then((success) =>
		{
			if(success !== false)
			{
				this.egw.message(this.egw.lang("Copied '%1' to clipboard", value), 'success');
			}
		});
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
		const clmQuestions = <et2_grid><unknown>this.et2.getDOMWidgetById('clm['+_type+'][questions]');
		let data = [];
		(<any>clmQuestions).cells.forEach((cell,index)=>{
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

		clmQuestions.set_value({content:[...data]});
	}


	public course_enableLiveFeedBack(_node, _widget)
	{
		const checked = _widget.get_value() == 'true' ? true : false;
		(<any>this.et2.getDOMWidgetById('lfbUploadSection')).set_disabled(!checked);

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
		file.getParent().getParent().hidden = true
		if (url.getValue() !='')
		{
			let parts = url.getValue().split('/');
			name = parts[parts.length-1];
		}
		else if(file.getValue() && Object.values(file.getValue()).length > 0)
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
			Et2Dialog.confirm(_widget, "There's already a video with the same name, would you still like to upload it?", "Duplicate name", false);
		}
		else
		{
			_widget.getInstanceManager().submit();
		}
	}

	public course_addLivefeedback_btn(_event, _widget)
	{
		if(_widget.getRoot().getWidgetById('tabs'))
		{
			_widget.getRoot().getWidgetById('tabs').value = 'videos';
		}
		let url = this.et2.getWidgetById('video_url');
		let basePath = egw.webserverUrl.match(/http/) ? egw.webserverUrl : window.location.protocol + '//' + window.location.host + egw.webserverUrl;
		url.set_value(basePath+'/smallpart/setup/livefeedback.webm');
		_widget.getInstanceManager().submit();
	}

	public course_uploadFinished(event)
	{
		this.et2.getWidgetById("video").disabled = false;
	}

	/**
	 * User has selected a URL or file to replace an existing material
	 *
	 * @param event
	 */
	public course_replacementSelected(event)
	{
		debugger;
		// Figure out where this came from
		const source = (event.data ?? event.target).getParent().getParent() ?? this.et2;
		// Enable apply button
		const apply = source.getWidgetById("apply_replacement");
		apply.disabled = false;
	}

	/**
	 * Applies given action to cats grid
	 *
	 * @param _id
	 * @param _action
	 *
	 */
	public course_catsAction(_id, _action)
	{
		const cats = <et2_grid><unknown>this.et2.getDOMWidgetById('cats');
		let arrayMgrs = <{[key:string]: et2_arrayMgr}>this.et2.getArrayMgrs();
		let data = <Array<any>><any>cats.getArrayMgr('content').data || [];
		let extraData = null;

		switch(_action)
		{
			case 'delete':
				let ids = [_id];
				if (!data[_id].parent_id)
				{
					ids = ids.concat(smallpartApp.course_findSubCatsIndexes(data, data[_id]['cat_id']));
				}
				ids.reverse().forEach((_item)=>{
					data.splice(_item,1);
				});
				break;

			case 'add-special':
				extraData = {cat_name: this.egw.lang("special"), type: 'sc'};
			// Fall through
			case 'sub':
			case 'add':
				const addCat = (_id: number, _action: string, _extraData?: {}) : number => {
					let pos : number;
					if (_action === 'sub')
					{
						for(pos=_id+1; pos < data.length && data[pos].parent_id == data[_id].cat_id; ++pos) {}
					}
					else
					{
						pos = data.length+1;
					}
					const add : any = {
						...{
							cat_id: 'new-'+(new Date).valueOf(),
							parent_id: _action == 'sub' ? data[_id]['cat_id'] : null,
						},
						..._extraData
					};
					add.data = JSON.stringify(add);
					data.splice(pos, 0, add);
					return pos;
				};

				let pos = addCat(_id, _action, extraData);

				if (_action === 'add')
				{
					for(let i=0; i<=1; i++)
					{
						// add fixed predefined lf type cats
						addCat(pos-1, 'sub', {
							cat_id: 'new-'+(new Date).valueOf()+i,
							cat_name:i==0?'like':'dislike',
							value:i==0?'p':'n',
							type:'lf',
							cat_color: i ? '#FF0000' : '#00FF00'
						});
					}
				}
				break;
		}

		cats.set_value({content:[...data]});
		arrayMgrs.content.data['cats'] = data;
		this.et2.setArrayMgrs(arrayMgrs);
	}

	/**
	 * helper function to find index of given cat id
	 * @param _data
	 * @param _cat_id
	 */
	public static course_findCatIndex(_data, _cat_id=null)
	{
		return _data.findIndex((_item)=>{return _item.cat_id == _cat_id});
	}

	/**
	 * helper function to find out indexes of all sub cats of given parent cat id
	 * @param _data
	 * @param _cat_parent_id
	 */
	public static course_findSubCatsIndexes(_data, _cat_parent_id=null)
	{
		if (!_data || !_cat_parent_id) return [];
		let indexes = [];
		_data.forEach((_item,_i)=>{if(_item.parent_id == _cat_parent_id) indexes.push(_i)})
		return indexes;
	}

	/**
	 * User changed one of the available per-course preferences
	 *
	 * @param ev
	 */
	public handleCoursePreferenceChange(ev, widget)
	{
		const course_id = this.et2.getArrayMgr("content")?.getEntry("course_id") ?? "";
		if(course_id)
		{
			widget.select_options.forEach((item) =>
			{
				this.egw.set_preference('smallpart', 'course_' + course_id + "_" + item.value, widget.value.includes(item.value) ? true : 0);
			});
		}
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
		// kept as sendRequest('keepalive') - called from the beforeunload handler, needs the fetch keepalive flag
		// to survive page unload; egw.request() has no equivalent option, only plain async
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
		if(videobar && typeof videobar?.currentTime() == "number")
		{
			data.position = videobar?.currentTime();
		}

		if(data.video_id)
		{
			//console.log('set_video_position', data);
			// kept as sendRequest('keepalive') - called from the beforeunload handler, needs the fetch keepalive flag
			// to survive page unload; egw.request() has no equivalent option, only plain async
			this.egw.json('smallpart.EGroupware\\SmallParT\\Student\\Ui.ajax_setLastVideo', [data]).sendRequest('keepalive');
		}
	}

	/**
	 * Confirm import should overwrite whole course or just add videos
	 */
	public confirmOverwrite(_ev : Event, _widget : Et2Button, _node : HTMLButtonElement)
	{
		let widget = _widget;
		// if we have no course_id / add used, no need to confirm overwrite
		if (!widget.getArrayMgr('content').getEntry('course_id'))
		{
			widget.getInstanceManager().submit(widget, true, true);
			return;
		}
		const dialog = new Et2Dialog(this.egw);
		dialog.transformAttributes({
			callback: (_button) =>
			{
				if(_button !== "cancel")
				{
					widget.getRoot().setValueById('import_overwrite', _button === "overwrite");
					widget.getInstanceManager().submit(widget, true, true); // last true = no validation
				}
			},
			buttons: [
				{label: this.egw.lang("Add videos"), id: "add", class: "ui-priority-primary", default: true},
				{label: this.egw.lang("Overwrite course"), id: "overwrite", image: "delete"},
				{label: this.egw.lang("Cancel"), id: "cancel", class: "ui-state-error"},
			],
			title: this.egw.lang('Overwrite exiting course?'),
			message: this.egw.lang('Just add videos, or overwrite whole course?'),
			icon: Et2Dialog.QUESTION_MESSAGE
		});
		document.body.appendChild(dialog);
	}

	/**
	 * Number of checked siblings
	 */
	private childrenChecked(_widget : et2_widget) : number
	{
		let answered = 0;
		_widget.iterateOver((_checkbox) =>
		{
			if (_checkbox.get_value()) ++answered;
		}, this, Et2Checkbox);
		return answered;
	}

	/**
	 * OnChange for multiple choice checkboxes to implement max_answers / max. number of checked answers
	 */
	public checkMaxAnswers(_ev : Event, _widget : Et2Checkbox, _node : HTMLInputElement)
	{
		let max_answers = _widget.getRoot().getArrayMgr('content').getEntry('max_answers');
		try {
			max_answers = _widget.getRoot().getValueById('max_answers');
		}
		catch (e) {}

		if (max_answers)
		{
			let checked = this.childrenChecked(<et2_widget>_widget.getParent());
			// for dialog method is not called on load, therefore it can happen that already max_answers are checked
			if (checked > max_answers)
			{
				_widget.set_value(false);
				checked--;
			}
			_widget.getParent().iterateOver((_checkbox : Et2Checkbox) =>
			{
				if (!_checkbox.get_value())
				{
					_checkbox.set_readonly(checked >= max_answers);
				}
			}, this, Et2Checkbox);
		}
	}

	protected checkMinAnswer_error;

	/**
	 * Check min. number of multiplechoice answers are given, before allowing to submit
	 */
	public checkMinAnswers(_ev : Event, _widget : Et2Button, _node : HTMLInputElement) : false|null
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
	public defaultPoints(_ev? : Event, _widget? : et2_widget, _node? : HTMLInputElement)
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
			document.querySelectorAll<HTMLElement>('.scoreCol').forEach(el => el.style.display = 'none');
		}
		else
		{
			let explicit_points = 0,explicit_set = 0,num_answers = 0;
			for(let i=1,w=null; (w=this.et2.getWidgetById(''+i+'[score]')); ++i)
			{
				// ignore empty questions
				if (!this.et2.getValueById(''+i+'[answer]')) continue;

				let val = parseFloat((<Et2Textbox>w).getValue());
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

				// set_blur() was a legacy et2_textbox-only method (jQuery-based placeholder fallback for pre-HTML5 browsers) -
				// the real runtime widget here is the modern Et2Number, which has no set_blur() at all and would throw.
				// Native placeholder support has been universal for years, so setting the property directly is the correct equivalent.
				(<Et2Textbox>w).placeholder = default_points;
			}
			document.querySelectorAll<HTMLElement>('.scoreCol').forEach(el => el.style.display = '');
		}
	}

	/**
	 * Pause test by submitting it to server incl. current video position
	 *
	 * @param _ev
	 * @param _widget
	 * @param _node
	 */
	public pauseTest(_ev : Event, _widget : et2_widget, _node : HTMLInputElement)
	{
		let videobar = <et2_smallpart_videobar>this.et2.getWidgetById('video');
		let videotime = this.et2.getInputWidgetById('video_time');

		if (videotime) videotime.set_value(videobar.currentTime());

		//disable the masking
		this._student_noneTestAreaMasking(false);

		let timer = <et2_countdown><unknown>this.et2.getDOMWidgetById('timer');
		// reset the alarms while the test is paused
		timer.options.alarm = [];

		let clml = <et2_smallpart_cl_measurement_L><unknown>this.et2.getDOMWidgetById('clm-l');
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
	public questionTime(_ev? : Event, _widget? : Et2InputWidgetInterface, _node? : HTMLInputElement)
	{
		let start = this.et2.getInputWidgetById('overlay_start');
		let duration = this.et2.getInputWidgetById('overlay_duration');
		let end = this.et2.getInputWidgetById('overlay_end');
		let apply = <any>this.et2.getDOMWidgetById('button[apply]');
		let save = <any>this.et2.getDOMWidgetById('button[save]');

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
				end.set_validation_error(egw.lang('Lenght of question cannot exceed the lenght of video %1', (<any>end)._convert_to_display(end.get_value()).value));
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
		const marks = <Et2Textbox>this.et2.getWidgetById('marks');
		if (!videobar || !marks) return;	// eg. called from the list or no mark or mill-out question

		const mark_values : MarksWithArea = JSON.parse(marks.getValue() || '[]');
		videobar.setMarks(MarkArea.colorDisjunctiveAreas(mark_values, videobar.get_marking_colors()));
		videobar.set_marking_enabled(true, (mark) => console.log(mark));
		videobar.set_marking_readonly(true);
		videobar.setMarkingMask(mark_values.length > 0);

		// store marks before saving in hidden var again
		['button[save]', 'button[apply]'].forEach((name) =>
		{
			const button = <Et2Button>this.et2.getWidgetById(name);
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
	 */
	public markAnswer(_ev? : PointerEvent, _widget?)
	{
		const videobar = <et2_smallpart_videobar>window.opener?.app?.smallpart?.et2?.getWidgetById('video') ||
			<et2_smallpart_videobar>this.et2.getWidgetById('video');

		if (!videobar)
		{
			this.egw.message(this.egw.lang('You have to open the question from the video, to be able to mark answers!', 'error'));
			return;
		}
		videobar.set_marking_color(parseInt(_widget.dataset.markIndex ?? _widget.value));
		videobar.set_marking_readonly(false);
		videobar.set_marking_enabled(true, (mark) => {
			let mark_values = MarkArea.markDisjunctiveAreas(videobar.getMarks(true), videobar.video.width() / videobar.video.height());
			videobar.setMarks(MarkArea.colorDisjunctiveAreas(mark_values, videobar.get_marking_colors()));
		});
		videobar.setMarksState(true);
		videobar.setMarkingMask(true);

		// mark current row as active and unmark all others
		const tr = (<Element>_widget.parentNode).closest('tr');
		Array.from(tr.parentElement.children).filter(sibling => sibling !== tr).forEach(sibling => sibling.classList.remove('markActiveRow'));
		tr.classList.add('markActiveRow');
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
	public ltiVideoSelection(_node : HTMLSelectElement, _widget : Et2Select)
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

	public livefeedback_timerStart(_widget, _state)
	{
		let content = this.et2.getArrayMgr('content');
		let lf_recorder = <SmallPartMediaRecorder>this.et2.getWidgetById('lf_recorder');
		let lf_report = this.et2.getWidgetById('lf_report');
		let autoUpload = <any>this.et2.getDOMWidgetById('autoUpload');
		autoUpload.disabled = true;
		_widget.label = '';
		(<HTMLElement>document.getElementsByClassName('commentEditArea')[1]).hidden = false;
		lf_recorder.record().then(()=>{
			this.egw.request('smallpart.\\EGroupware\\SmallParT\\Student\\Ui.ajax_livefeedbackSession', [
				true, {'course_id':content.getEntry('video')?.course_id, 'video_id':content.getEntry('video')?.video_id}
			]).then((_data) => {
				if (_data?.session == "started")
				{
					lf_report.sessionStartTime = 0;
					(<any>lf_recorder).disableMediaSelectors = true;
					document.querySelector('.video_list').classList.add('disabled');
				}
			});
		});
	}

	public livefeedback_timerOnPulse(_widget)
	{
		let lf_report = this.et2.getWidgetById('lf_report');
		lf_report.sessionEndTime = _widget.timer;
		if (this.comments.length>1)
		{
			window.setTimeout(()=>{
				lf_report.comments = this.comments;
			}, 10000);
		}
	}

	public livefeedback_timerStop(_widget, _state)
	{
		let content = this.et2.getArrayMgr('content');
		// self is still needed below - the nested dialog callback's own "this" is rebound to the dialog by
		// Et2Dialog's transformAttributes({callback: ...}) contract, so it can't use "this" for the app instance
		let self = this;
		_widget._resetClick();
		let lf_recorder = <SmallPartMediaRecorder>this.et2.getWidgetById('lf_recorder');
		lf_recorder.stop().then(()=>{
			this.egw.request('smallpart.\\EGroupware\\SmallParT\\Student\\Ui.ajax_livefeedbackSession', [
				false, {'course_id':content.getEntry('video')?.course_id, 'video_id':content.getEntry('video')?.video_id}
			]).then((_data) => {
				this.egw.message(_data?.msg);
				if (_data?.session === 'ended')
				{
					const warning = this.egw.lang('PLEASE DO NOT RELOAD THE PAGE WHILE VIDEO CHUNKS ARE BEING UPLOADED ...');
					this.egw.loading_prompt(lf_recorder.id, true, warning, null);
					(<any>lf_recorder).uploadingIsfinished().then(()=>{
						this.egw.message(this.egw.lang("Video is successfully uploaded."));
						this.egw.loading_prompt(lf_recorder.id, false);
						const dialog = new Et2Dialog(this.egw);
						dialog.transformAttributes({
							callback: function(_button)
							{
								if(_button == "submit")
								{
									// re-initiate the recorded video
									self.et2.getWidgetById('video').video.load();
									self.et2.getInstanceManager().submit();
									return true;
								}
								lf_recorder.download();
								return false;
							},
							buttons: [
								{
									label: this.egw.lang("Close"),
									id: "submit",
									image: "check",
									class: "ui-priority-primary",
									default: true
								},
								{label: this.egw.lang("Download before closing"), id: "download", image: "download"}
							],
							title: this.egw.lang('Session Status'),
							message: this.egw.lang('Session has been successfully recorded.'),
							noCloseButton: true,
							hideOnEscape: false,
							isModal: true
						});
						document.body.appendChild(dialog);
						document.querySelector('.video_list').classList.remove('disabled');
						(<any>lf_recorder).disableMediaSelectors = false;
					});
				}
			});
		});
	}

	public livefeedback_autoUpload(_ev, _widget)
	{
		let recorder = this.et2.getWidgetById('lf_recorder');
		recorder.autoUpload = _widget.value;
	}

	public livefeedback_sessionRefreshed(_data)
	{
		// was "self" here, but no self/that was ever captured in this method - that's the global Window.self,
		// not the app instance; this method is always invoked as app.smallpart.livefeedback_sessionRefreshed(...),
		// so "this" is correctly bound
		this.egw.message(_data.msg);
		if (_data.session === 'ended')
		{
			this.et2.getInstanceManager().submit();
		}
	}

	/**
	 * Teachers take longer to submit feedback, so we store the start time
	 *
	 * @param _event
	 * @param _widget
	 */
	protected teacher_livefeedbackSubCatClick(_event, _widget)
	{
		const content = this.et2.getArrayMgr('content');
		const parentCatId = _widget.id.split(':')[0];
		const description = this.et2.getDOMWidgetById(parentCatId + ':comment');
		const timer = (<any>this.et2.getDOMWidgetById('lf_timer'))?.value ?? "";
		const mark = <Et2HBox><unknown>this.et2.getDOMWidgetById("mark_time");
		if(description && mark && !mark.dataset.time)
		{
			this.livefeedbackMarkTime(true);
		}
	}

	public livefeedbackMarkTime(force? : boolean, clearTimer? : boolean)
	{
		let content = this.et2.getArrayMgr('content');
		const time = (<any>this.et2.getDOMWidgetById("lf_timer"))?.value ?? "";
		const mark = <SmallPartFlagTime><unknown>this.et2.getDOMWidgetById("flag");

		if(force || typeof force == "undefined" && mark && !mark.value && time)
		{
			mark.markTime(parseInt(time) || 0, clearTimer ? content.getEntry('video')['livefeedback']['session_interval'] ?? 2 : 0);
		}
	}

	protected async teacher_livefeedbackCommentClick(_event, _widget)
	{
		// Set timestamp if not yet set
		const mark = <SmallPartFlagTime><unknown>this.et2.getDOMWidgetById("flag");
		const dialog = _widget.parentNode.querySelector('et2-dialog');
		const clearTimer = !!mark.value;

		// If timer is counting down to clear, stop
		mark.cancelClear();
		this.livefeedbackMarkTime(undefined, clearTimer);
		if(dialog)
		{
			await dialog.show();
			let [button, value] = await dialog.getComplete();

			// This is only used for the meta-comment, category comments don't set button but
			// use their own callback
			if(button)
			{
				this.livefeedbackCommentSubmit(
					dialog,
					dialog.querySelector("[id*=':comment']"),
					"free"
				)
			}
			else if(!clearTimer)
			{
				// If time was flagged automatically (no timer) clear it immediately
				mark.clearMark();
			}
		}
	}

	public livefeedbackCatDialogHandler(event)
	{
		const dialog = event.composedPath().find(t => t.tagName == "ET2-DIALOG");
		const originalCatButton = dialog?.parentElement?.getWidgetById(event.target.id.replace("dialog_", "")) ?? null;
		const cat_widget = typeof event.target._getOptions == "function" ? event.target : originalCatButton;
		const cat = cat_widget && cat_widget._getOptions ? cat_widget?._getOptions().find(o => o.value == cat_widget.value) ?? {} : {};
		const cat_string = [originalCatButton.id.split(":").shift()];
		const timer = this.et2.getDOMWidgetById(cat_string[0] + ':timer');
		if(cat?.parent_id != cat?.value)
		{
			cat_string.push(cat.value);
		}
		if(cat?.data?.type == "lf")
		{
			cat_string.push("lf");
		}
		this.livefeedbackCommentSubmit(
			originalCatButton.parentElement,
			dialog.querySelector("[id*=':comment']"),
			cat_string.join(":"), null,
			timer
		).finally(() =>
		{
			// Reset
			event.target.value = "";
		})
		if(dialog)
		{
			dialog.hide();
		}
	}

	public student_livefeedbackSubCatClick(_event, _widget)
	{
		let content = this.et2.getArrayMgr('content');
		const parentCatId = _widget.id.split(':')[0];
		let subs = <any>this.et2.getDOMWidgetById(parentCatId + ':subs');
		let ids = subs?.value ? [parentCatId, subs.value] : [parentCatId];
		const cat = subs?._getOptions().find(o => o.value == subs.value) ?? {};
		const mark = <SmallPartFlagTime><unknown>this.et2.getDOMWidgetById("flag");
		const main = <any>this.et2.getDOMWidgetById(ids[0]);
		let description = <Et2Textarea><unknown>(this.et2.getDOMWidgetById(ids[0] + ':comment') ??
			this.et2.getDOMWidgetById("flag:comment"));
		if(ids)
		{
			let timer = this.et2.getDOMWidgetById(ids[0]+':timer');
			this.livefeedbackCommentSubmit(
				main.parentElement,
				description,
				ids.join(":") + (cat?.data?.type == "lf" ? ":lf" : ""),
				mark?.value ?? null,
				timer,
				main?.value?.cat_color?.replace('#', '')
			).finally(() =>
			{
				subs.value = '';
			});
		}
	}

	/**
	 * Add a livefeedback comment
	 * @param cat_string <category ID > [: <sub-category ID>] [ : lf ] | 'free'
	 * @param time of the comment in seconds
	 * @param timer_widget Label for category that counts down cooldown
	 */
	livefeedbackCommentSubmit(main_cat_widget, comment_widget, cat_string, time?, timer_widget?, color?)
	{
		const mark = <SmallPartFlagTime><unknown>this.et2.getDOMWidgetById("flag");
		const content = this.et2.getArrayMgr('content');

		// Livefeedback comments are distinguished from other comments by the ":lf" or ":lfc" suffix, it's required
		if(!cat_string.endsWith(":lf") && !cat_string.endsWith(":lfc"))
		{
			cat_string += ":lfc";
		}

		return this.egw.request('smallpart.\\EGroupware\\SmallParT\\Student\\Ui.ajax_livefeedbackSaveComment', [
			this.et2.getInstanceManager().etemplate_exec_id,
			{
				// send action and text to server-side to be able to do a proper ACL checks
				action: 'add',
				course_id: content.data.video.livefeedback.course_id,
				video_id: content.data.video.livefeedback.video_id,
				text: comment_widget?.value ?? " ",
				comment_color: color,
				// a simulated session has no session timer to count from, the position in the video is the time
				comment_starttime: content.data.video.video_livefeedback_simulated ?
								   Math.round((<et2_smallpart_videobar><unknown>this.et2.getWidgetById('video')).currentTime()) :
								   time ?? mark?.value ?? (<any>this.et2.getDOMWidgetById("lf_timer"))?.value ?? "",
				comment_stoptime: null,
				comment_marked: '',
				comment_cat: cat_string
			}]).then((_data) =>
		{
			if(_data?.session === 'ended')
			{
				// was "self" here, but no self/that was captured in this method - that's the global Window.self;
				// this .then() callback is already an arrow function, so "this" closes over the right instance
				return this.et2.getInstanceManager().submit();
			}

			// Disable for a certain interval
			let interval = content.getEntry('video')['livefeedback']['session_interval'] ?
						   parseInt(content.getEntry('video')['livefeedback']['session_interval']) * 1000 : 2000;

			// Clear the comment widget
			if(comment_widget)
			{
				comment_widget.value = '';
				delete comment_widget.dataset.starttime;
			}

			// Clear all blocked categories when marked time is cleared
			if(mark?.value)
			{
				mark?.addEventListener("clear", (e) =>
				{
					// Clear blocked categories
					this.et2.getDOMNode().querySelectorAll('.commentRadioBoxArea et2-vbox').forEach(vbox =>
					{
						vbox.classList.remove("disabled");
					});
				}, {once: true});
			}

			// Clear the marked time (with default timer, set when we marked the time)
			mark?.clearMark();

			if(timer_widget)
			{
				main_cat_widget.classList.add('disabled');
				timer_widget.set_disabled(false);
				let c = interval / 1000;

				timer_widget.value = c;
				const counter = setInterval(_ =>
				{
					c--;
					timer_widget.value = `${c}`;
				}, 1000);

				setTimeout(_ =>
				{
					// Wait a bit to clear categories if time is marked
					if(!(mark?.value))
					{
						main_cat_widget.classList.remove('disabled');
					}
					clearInterval(counter);
					timer_widget.set_disabled(true);
				}, interval);
			}
		});
	}

	/**
	 * Set up a simulated live session
	 *
	 * The student watches an already uploaded video on their own, giving live-feedback as they would
	 * in a real session. The videobar's own play button stays hidden: the run is started once, from
	 * our own button, and must not be scrubbed. Autoplay is deliberately not used, browsers block it
	 * for an unmuted video, which used to leave the student with no way at all to start the video.
	 */
	public student_simulatedSession()
	{
		const bar = <any>this.et2.getDOMWidgetById(smallpartApp.playControlBar);
		// the videobar's own seekable only guards its slider, so the 10s buttons have to go as well
		smallpartApp.simulatedHiddenControls.forEach(id =>
		{
			const widget = bar?.getWidgetById(id);
			if(widget)
			{
				widget.hidden = true;
			}
		});
		// The feedback options belong beside the video, not under it. The template can not simply be
		// referenced a second time in the right column: two references share one server-side Template
		// object and therefore one disabled state, so it stays declared in the area under the video
		// and we move that node over. Only the DOM moves, the widget tree is untouched, so every
		// category id still resolves to exactly one widget.
		const target = <HTMLElement><unknown>this.et2.getDOMWidgetById('simulated_voting');
		const livefeedback = <HTMLElement><unknown>this.et2.getWidgetById('smallpart.student.livefeedback');
		if(target && livefeedback)
		{
			target.appendChild(livefeedback);
		}
		// Nobody hosts a simulated session: the publish button, the recorder and the report that go
		// with a real one have nothing to do here, and neither has the "not started yet" notice the
		// waiting students get. Both blocks already spend their one hidden= attribute on the
		// staff/student split (hidden="!@is_staff" and hidden="@is_staff"), which a simulated
		// session has to override in both directions, so they are hidden from here instead.
		const publish = this.et2.getWidgetById('publish_box');
		const status = this.et2.getDOMWidgetById('lf_recording');
		[publish?.getParent(), (<any>status)?.getParent()].forEach(block =>
		{
			if(block)
			{
				block.hidden = true;
			}
		});
		// the voting buttons only appear once the video is running, like they would in a live session
		this._simulatedRunning(false);
	}

	/**
	 * Show the controls of a simulated live session that match its current state
	 *
	 * @param _running video is playing: vote, but do not start again
	 */
	protected _simulatedRunning(_running : boolean)
	{
		const start = this.et2.getWidgetById('simulated_start');
		const stop = this.et2.getWidgetById('simulated_stop');
		const voting = this.et2.getWidgetById('simulated_voting');
		if(start)
		{
			start.hidden = _running;
		}
		if(stop)
		{
			stop.hidden = !_running;
		}
		if(voting)
		{
			voting.hidden = !_running;
		}
	}

	/**
	 * Start (or resume) a simulated live session
	 */
	public student_simulatedStart()
	{
		const content = this.et2.getArrayMgr('content');
		const video = <et2_smallpart_videobar><unknown>this.et2.getWidgetById('video');

		return this.egw.request('smallpart.\\EGroupware\\SmallParT\\Student\\Ui.ajax_simulatedStart', [
			this.et2.getInstanceManager().etemplate_exec_id,
			content.data.video.course_id, content.data.video.video_id
		]).then((_data) =>
		{
			// the server reports a refused start as a message, not a rejected request, so do not
			// start playing a session that is not actually running
			if(!_data?.started)
			{
				return;
			}
			this._simulatedRunning(true);
			// play_video() calls this back once the video reaches its end
			video.play_video(() => this.student_simulatedFinished());
		});
	}

	/**
	 * Interrupt a simulated live session, without finishing it
	 */
	public student_simulatedStop()
	{
		(<et2_smallpart_videobar><unknown>this.et2.getWidgetById('video')).pause_video();
		this._simulatedRunning(false);
	}

	/**
	 * The simulated live session has been watched through
	 *
	 * Records it server-side and reloads, which drops every restriction and gives the student the
	 * regular analysis interface, as they would get after a real live session.
	 */
	public student_simulatedFinished()
	{
		const content = this.et2.getArrayMgr('content');
		const video = <et2_smallpart_videobar><unknown>this.et2.getWidgetById('video');

		// the videobar calls this from its timeupdate handler, which keeps firing until the page
		// has actually submitted, and we only want to record and reload the run once
		if(this._simulatedFinishing)
		{
			return this._simulatedFinishing;
		}
		this._simulatedRunning(false);
		return this._simulatedFinishing = this.egw.request('smallpart.\\EGroupware\\SmallParT\\Student\\Ui.ajax_simulatedFinished', [
			this.et2.getInstanceManager().etemplate_exec_id,
			content.data.video.course_id, content.data.video.video_id, Math.round(video.currentTime())
		]).then(() => this.et2.getInstanceManager().submit());
	}

	public	student_livefeedbackSession()
	{
		let recorder = <any>this.et2.getDOMWidgetById('lf_recorder');
		let publish = this.et2.getWidgetById('publish_box');
		const isPublished = this.et2.getArrayMgr('content').getEntry('video').video_published == 1? true : false;
		publish.hidden = !this.is_staff || isPublished;
		recorder.disabled = !this.is_staff || egwIsMobile() || !isPublished;
	}

	public student_livefeedbackReport()
	{
		let lf_comments_slider = <SmallPartLiveFeedbackReport><unknown>this.et2.getDOMWidgetById('lf_comments_slider');
		lf_comments_slider.comments = this.comments;
	}

	public pushLivefeedback(_data)
	{
		let videos = this.et2.getWidgetById('video2');
		if (_data && _data.acl.data)
		{
			if (_data.acl.data['session_starttime'])
			{
				videos.value = _data.acl.data['video_id'];
				this.et2.getInstanceManager().submit();
			}
			else if (videos.value != _data.acl.data['video_id'])
			{
				egw.open(_data.acl.data['video_id'],'smallpart-video','view',{},'_self','smallpart');
			}
		}
	}

	public student_livefeedbackReportfullSize(event, button)
	{
		const details = document.getElementsByClassName('livefeedbackReport');
		details[0].addEventListener('sl-hide', _=>{
			details[0].classList.remove('fullscreen');
		});

		if (details[0].classList.contains('fullscreen'))
		{
			details[0].classList.remove('fullscreen');
			button.image = "fullscreen";
		}
		else
		{
			details[0].addEventListener('sl-hide', _=>{
				details[0].classList.remove('fullscreen');
			});
			details[0].classList.add('fullscreen');
			button.image = "fullscreen-exit";
		}
	}

	public student_commentCatChanged(_ev, _widget)
	{
		_widget.categoryType = "";
		if(_widget.select_options.find(o => o.value == _widget.value)?.data?.type == "sc")
		{
			// Special category
			_widget.categoryType = "sc";
			_widget.disabled = true;
			// This causes the displayed value to update
			_widget.updateComplete.then(() => {_widget.value = _widget.value;});
		}
		else
		{
			_widget.categoryType = "!sc";
		}
		let commentCatSub = _widget.getParent().getWidgetById('comment_cat_sub');
		if (commentCatSub)
		{
			commentCatSub.disabled = !_widget.value || _widget.value.trim() == "free" || _widget.categoryType == "sc"
			commentCatSub.onlySubs = _widget.value;
		}

		// Block saving of comments as long as no main category is selected
		const saveButton = this.commentGrid.getWidgetById("saveAndContinue");
		if(saveButton)
		{
			saveButton.disabled = !_widget.value
			_widget.set_validation_error(saveButton.disabled ? this.egw.lang("Select category") : false);
		}
	}

	public async livefeedback_publishBtn(_event, _widget)
	{
		let video = this.et2.getArrayMgr('content').getEntry('video');
		let counter = this.et2.getWidgetById('counter');
		let lf_timer = this.et2.getWidgetById('lf_timer');
		counter.value = 15;
		_widget.disabled = true;
		await _widget.updateComplete;
		await this.egw.request('smallpart.\\EGroupware\\SmallParT\\Student\\Ui.ajax_livefeedbackPublishVideo', video['video_id']);
		const timer = setInterval(() =>
		{
			counter.value = counter.value - 1;
			if(counter.value <= 0)
			{
				lf_timer.disabled = false;
				clearInterval(timer);
				_widget.getInstanceManager().submit(null, false, true);
			}
		}, 1000);
	}

	/**
	 * Install an onchange handler to save answers directly, when the user gives/changes them
	 */
	public installSaveAnswerOnChange()
	{
		const grid = this.et2.getWidgetById('questions');
		if (!grid) return;
		grid.iterateOver((widget : et2_inputWidget) =>
		{
			widget.onchange = this.saveAnswerOnChange;
		}, null,  et2_IInput);
	}

	/**
	 * Save answers directly, when the user gives/changes them
	 *
	 * @param ev
	 * @param widget
	 */
	public saveAnswerOnChange(ev : Event, widget: et2_inputWidget)
	{
		let tr : any = widget.getDOMNode();
		const overlay_id_match = /^smallpart:overlay:(\d+)$/;
		while (tr.nodeName !== 'TR' || !tr.id || !overlay_id_match.exec(tr.id)) {
			if (!(tr = tr.parentNode)) return;
		}
		let template : et2_widget = widget;
		while(template.getType() !== 'ET2-TEMPLATE') {
			if (!(template = template.getParent())) return;
		}
		const data : any = Object.values(widget.getInstanceManager().getValues(template)['questions']).shift();
		data.overlay_id = overlay_id_match.exec(tr.id)[1];
		data.video_id = widget.getRoot().getValueById('videos');
		egw.request('smallpart.\\EGroupware\\SmallParT\\Questions.ajax_answer', [data]).then((response => {
			if (response.error && (<any>widget).nodeName === 'ET2-CHECKBOX') {
				(<any>widget).value = !(<any>widget).value;
				return;
			}
			if (response.summary) {
				widget.getRoot().getWidgetById('question_summary')?.set_value(response.summary);
			}
			if (typeof response.answer_data?.answer_label !== 'undefined') {
				const description = widget.getParent().getParent().getWidgetById('answer_data[answer_label]');
				if (description) {
					description.value = response.answer_data.answer_label;
					description.style.backgroundColor = response.answer_data.color || '';
				}
			}
		}));
	}

	/**
	 * Change handler to filter videos/material on start-page
	 */
	public filterVideos(ev : Event, widget : Et2Select)
	{
		const reg_exp = new RegExp(String(widget.value), 'i');
		this.et2?.getWidgetById('videos').getDOMNode().querySelectorAll('tr').forEach(tr => {
			let match = true;
			if (widget.value === '' || String(widget.value).startsWith('videoStatus'))
			{
				match = widget.value === '' || tr.classList.contains(<string>widget.value);
			}
			else
			{
				match = Array.from<HTMLElement>(tr.querySelectorAll('et2-description')).some(description => description.innerText.match(reg_exp));
			}
			tr.style.display = match ? '' : 'none';
		});
	}

	/**
	 * Call document merge for given id or current video
	 *
	 * @param string _id "smallpart::$course_id:$video_id", default current video
	 */
	async mergeVideo(_id)
	{
		const that = <smallpartApp>app.smallpart;	// not sure why this is not defined/the window, binding it in the constructor also did not help :(
		if (!_id || typeof _id !== 'string' || !_id.startsWith('smallpart::'))
		{
			_id = 'smallpart::'+that.filter.course_id+':'+that.filter.video_id;
		}
		return that.mergeAction({
			data: {
				merge_data: {
					menuaction: 'smallpart.EGroupware\\Vidopro\\Merge.merge_entries',
					merge: 'EGroupware\\Vidopro\\Merge',
					directory: '/templates/smallpart'
				}
			}
		}, [{id: _id}]);
	}

	/**
	 * Edit video in new video-edit dialog
	 */
	editVideo()
	{
		egw.open(this.filter.video_id, 'smallpart-video', 'edit', '', 'smallpart');
	}
}

app.classes.smallpart = smallpartApp;