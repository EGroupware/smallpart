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
	/smallpart/js/et2_widget_videotime.js;
	/smallpart/js/et2_widget_comment.js;
	/smallpart/js/et2_widget_color_radiobox.js;
	/smallpart/js/et2_widget_filter_participants.js;
 */

import {EgwApp} from "../../api/js/jsapi/egw_app";
import {et2_smallpart_videobar} from "./et2_widget_videobar";
import './et2_widget_videooverlay';
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
import PseudoFunction = Sizzle.Selectors.PseudoFunction;
import {et2_taglist} from "../../api/js/etemplate/et2_widget_taglist";
import {et2_DOMWidget} from "../../api/js/etemplate/et2_core_DOMWidget";
import {et2_file} from "../../api/js/etemplate/et2_widget_file";
import {et2_video} from "../../api/js/etemplate/et2_widget_video";
import {egw} from "../../api/js/jsapi/egw_global";

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

class smallpartApp extends EgwApp
{
	static readonly appname = 'smallpart';
	static readonly default_color = 'ffffff';	// white = neutral

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
	 * Constructor
	 *
	 * @memberOf app.status
	 */
	constructor()
	{
		// call parent
		super('smallpart');
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

		switch(true)
		{
			case (_name.match(/smallpart.student.index/) !== null):
				this.comments = <Array<CommentType>>this.et2.getArrayMgr('content').getEntry('comments');
				this._student_setCommentArea(false);
				this.filter = {
					course_id: parseInt(<string>this.et2.getArrayMgr('content').getEntry('courses')) || null,
					video_id:  parseInt(<string>this.et2.getArrayMgr('content').getEntry('videos')) || null
				}
				this.course_options = parseInt(<string>this.et2.getArrayMgr('content').getEntry('course_options')) || 0;
				this._student_setFilterParticipantsOptions();
				let self = this;
				jQuery(window).on('resize', function(){
					self._student_resize();
				});
				// record, in case of F5 or window closed
				window.addEventListener("beforeunload", function() {
					self.set_video_position();
					self.record_watched();
				})
				break;

			case (_name === 'smallpart.question'):
				if (this.et2.getArrayMgr('content').getEntry('max_answers'))
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
				this.questionTime();
				break;

			case (_name === 'smallpart.course'):
				// disable import button until a file is selected
				const import_button : et2_button = <et2_button>this.et2.getWidgetById('button[import]');
				import_button?.set_readonly(true);
				(<et2_file>this.et2.getWidgetById('import')).options.onFinish = function(_ev, _count) {
					import_button.set_readonly(!_count);
				};
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

	_student_resize()
	{
		let comments = this.et2.getWidgetById('comments').getDOMNode();
		jQuery(comments).height(jQuery(comments).height()+
		jQuery('form[id^="smallpart-student-index"]').height()
		- jQuery('.rightBoxArea').height() - 40
		);
	}

	/**
	 * Opend a comment for editing
	 *
	 * @param _action
	 * @param _selected
	 */
	student_openComment(_action, _selected)
	{
		if (!isNaN(_selected)) _selected = [{data: this.comments[_selected]}];
		this.edited = jQuery.extend({}, _selected[0].data);
		this.edited.action = _action.id;
		let videobar = <et2_smallpart_videobar>this.et2.getWidgetById('video');
		let comment = <et2_grid>this.et2.getWidgetById('comment');
		let self = this;
		(<et2_button><unknown>this.et2.getWidgetById('play')).set_disabled(_action.id !== 'open');
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
					comment.set_value({content: this.edited});
					break;

				case 'open':
					this.et2.getWidgetById('hideMaskPlayArea').set_disabled(false);
					comment.set_value({content:{
						comment_id: this.edited.comment_id,
						comment_added: this.edited.comment_added,
						comment_starttime: this.edited.comment_starttime,
						comment_marked_message: this.color2Label(this.edited.comment_color),
						comment_marked_color: 'commentColor'+this.edited.comment_color,
						action: _action.id
					}});
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
		}
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

	public student_playVideo(_pause: boolean)
	{
		let videobar = <et2_smallpart_videobar>this.et2.getWidgetById('video');
		let $play = jQuery(this.et2.getWidgetById('play').getDOMNode());
		let self = this;
		this._student_setCommentArea(false);
		if ($play.hasClass('glyphicon-pause') || _pause)
		{
			videobar.pause_video();
			$play.removeClass('glyphicon-pause glyphicon-repeat');
		}
		else
		{
			this.start_watching();

			videobar.set_marking_enabled(false);
			videobar.play_video(
				function(){
					$play.removeClass('glyphicon-pause');
					if (!(videobar.getArrayMgr('content').getEntry('video')['video_test_options'] & et2_smallpart_videobar.video_test_option_not_seekable))
					{
						$play.addClass('glyphicon-repeat');
					}
					// record video watched
					self.record_watched();
				},
				function(_id){
					let commentsGrid = jQuery(self.et2.getWidgetById('comments').getDOMNode());
					commentsGrid.find('tr.row.commentBox').removeClass('highlight');
					let scrolledComment = commentsGrid.find('tr.commentID' + _id);
					scrolledComment.addClass('highlight');
					commentsGrid[0].scrollTop = scrolledComment[0].offsetTop;
			});
			$play.removeClass('glyphicon-repeat');
			$play.addClass('glyphicon-pause');
		}
	}

	/**
	 * Add new comment / edit button callback
	 */
	public student_addComment()
	{
		let comment = <et2_grid>this.et2.getWidgetById('comment');
		let videobar = <et2_smallpart_videobar>this.et2.getWidgetById('video');
		let self = this;
		this.student_playVideo(true);
		(<et2_button><unknown>this.et2.getWidgetById('play')).set_disabled(true);
		this._student_setCommentArea(true);
		videobar.set_marking_enabled(true, function(){
			self._student_controlCommentAreaButtons(false);
		});
		videobar.set_marking_readonly(false);
		videobar.setMarks(null);
		this.edited = jQuery.extend(this.student_getFilter(), {
			comment_starttime: videobar.currentTime(),
			comment_added: [''],
			comment_color: smallpartApp.default_color,
			action: 'edit',
			save_label: this.egw.lang('Save')
		});

		comment.set_value({content: this.edited});
		comment.getWidgetById('deleteComment').set_disabled(true);
		this._student_controlCommentAreaButtons(true);
	}

	/**
	 * Cancel edit and continue button callback
	 */
	public student_cancelAndContinue()
	{
		let videobar = <et2_smallpart_videobar>this.et2.getWidgetById('video');
		videobar.removeMarks();
		this.student_playVideo(false);
		delete this.edited;
		this.et2.getWidgetById('add_comment').set_disabled(false);
		this.et2.getWidgetById('play').set_disabled(false);
		this.et2.getWidgetById('smallpart.student.comment').set_disabled(true);
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
					comment_starttime: videobar.currentTime(),
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
			course_id: this.et2.getWidgetById('courses')?.get_value() || this.filter.course_id,
			video_id: this.et2.getWidgetById('videos')?.get_value() || this.filter.video_id,
		}
	}

	/**
	 * Apply (changed) comment filter
	 *
	 * Filter is applied by hiding filtered rows client-side
	 */
	public student_filterComments()
	{
		let color = this.et2.getWidgetById('comment_color_filter').get_value();
		let rows = jQuery( 'tr', this.et2.getWidgetById('comments').getDOMNode()).filter('.commentColor'+color);
		let ids = [];
		rows.each(function(){
			ids.push(this.classList.value.match(/commentID.*[0-9]/)[0].replace('commentID',''));
		});
		this._student_commentsFiltering('color', ids);
	}

	public student_clearFilter()
	{
		this.et2.getWidgetById('comment_color_filter').set_value("");
		this.et2.getWidgetById('comment_search_filter').set_value("");
		this.et2.getWidgetById('activeParticipantsFilter').set_value("");
		for (let f in this.filters)
		{
			this._student_commentsFiltering(f,[]);
		}
	}

	public student_searchFilter(_widget)
	{
		let query = _widget.get_value();
		let rows = jQuery('tr', this.et2.getWidgetById('comments').getDOMNode());
		let ids = [];
		rows.each(function(){
			jQuery.extend (
				jQuery.expr[':'].containsCaseInsensitive = <PseudoFunction>function (a, i, m) {
					let t   = (a.textContent || a.innerText || "");
					let reg = new RegExp (m[3], 'i');
					return reg.test (t);
				}
			);

			if (query != '' && jQuery(this).find('*:containsCaseInsensitive("'+query+'")').length>1)
			{
				ids.push(this.classList.value.match(/commentID.*[0-9]/)[0].replace('commentID',''));
			}
		});
		this._student_commentsFiltering('search', ids.length == 0 && query != ''? ['ALL']:ids);
	}

	public student_onmouseoverFilter(_node, _widget)
	{
		let self = this;
		let videobar = <et2_smallpart_videobar>this.et2.getWidgetById('video');
		let comments = jQuery(this.et2.getWidgetById('comments').getDOMNode());
		if (_widget.get_value())
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
	 * Update comments
	 *
	 * @param _data see et2_grid.set_value
	 */
	public student_updateComments(_data)
	{
		// update our internal data
		this.comments = _data.content;

		// update grid
		let comments = <et2_grid>this.et2.getWidgetById('comments');
		comments.set_value(_data);

		// update slider-tags
		let videobar = <et2_smallpart_videobar>this.et2.getWidgetById('video');
		videobar.set_slider_tags(this.comments);

		// re-apply the filter, if not "all"
		let color = this.et2.getWidgetById('comment_color_filter').get_value();
		if (color) this.student_filterComments();

		this.et2.getWidgetById('smallpart.student.comments_list').set_disabled(!this.comments.length);

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
		videobar.setMarksState(!is_readonly);
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
		let rows = jQuery('tr', this.et2.getWidgetById('comments').getDOMNode());
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
				if (!this.comments[c]) continue;
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
			if (!this.comments[i]) continue;
			if (this.comments[i].filtered.length > 0)
			{
				rows.filter('.commentID' + this.comments[i].comment_id).addClass('hideme');
				tags.filter(function () {return this.dataset.id == self.comments[i].comment_id.toString();}).addClass('hideme');
			}
			else
			{
				rows.filter('.commentID' + this.comments[i].comment_id).removeClass('hideme');
				tags.filter(function () {return this.dataset.id == self.comments[i].comment_id.toString();}).removeClass('hideme');
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
		let activeParticipants = <et2_taglist>this.et2.getWidgetById('activeParticipantsFilter');
		let passiveParticipantsList = <et2_taglist>this.et2.getWidgetById('passiveParticipantsList');
		let options = {};
		let self = this;
		let participants: any = this.et2.getArrayMgr('content').getEntry('participants');
		let commentHeaderMessage = this.et2.getWidgetById('commentHeaderMessage');

		let _foundInComments = function(_id){
			for (let k in self.comments)
			{
				if (self.comments[k]['account_id'] == _id) return true;
			}
		};

		let _setInfo = function (_options){
			return new Promise(function(_resolved){
				let stack = Object.keys(_options);
				self._student_fetchAccountData(stack.pop(), stack, _options, _resolved);
			});
		};

		let _countComments = function(_id)
		{
			let c = 0;
			for (let i in self.comments)
			{
				if (self.comments[i]['account_id'] == _id) c++;
			}
			return c;
		};

		if (activeParticipants)
		{
			for (let i in this.comments)
			{
				if (!this.comments[i]) continue;
				let comment = this.comments[i];
				options[comment.account_id] = options[comment.account_id] || {};
				options[comment.account_id] = jQuery.extend(options[comment.account_id], {
					value: options[comment.account_id] && typeof options[comment.account_id]['value'] != 'undefined' ?
						(options[comment.account_id]['value'].indexOf(comment.comment_id)
						? options[comment.account_id]['value'].concat(comment.comment_id) : options[comment.account_id]['value'])
							: [comment.comment_id],
					name: '',
					label: '',
					comments: _countComments(comment.account_id),
					icon: egw.link('/api/avatar.php',{account_id: comment.account_id})
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
								options[comment_added] = {
									value: [comment.comment_id],
									icon: egw.link('/api/avatar.php',{account_id: comment_added})
								}
							}
							else if (typeof options[comment_added] == 'undefined')
							{
								options[comment_added] = {value:[]};
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

			_setInfo(options).then(function (_options: any){
				for(let i in _options)
				{
					if (_options[i]?.value?.length>0)
					{
						_options[i].value = _options[i].value.join(',');
					}
				}
				// set options after all accounts info are fetched
				activeParticipants.set_select_options(_options);

				let passiveParticipants = [{}];
				for (let i in participants)
				{
					if (!_options[participants[i].account_id]) passiveParticipants.push({account_id:participants[i].account_id});
				}
				passiveParticipantsList.set_value({content:passiveParticipants});
				commentHeaderMessage.set_value(self.egw.lang("%1/%2 participants already answered",
				 Object.keys(_options).length, Object.keys(_options).length+passiveParticipants.length-1));
			});
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

		if (_widget.id === 'courses' && _widget.getValue() === 'manage')
		{
			this.egw.open(null, 'smallpart', 'list', '', '_self');
		}
		else
		{
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

		if (!start || !duration || !end) return;	// eg. not editable
		if (_widget === end)
		{
			duration.set_value(parseInt(end.get_value())-parseInt(start.get_value()));
		}
		else
		{
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
}

app.classes.smallpart = smallpartApp;
