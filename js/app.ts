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
	/smallpart/js/et2_widget_videotime.js;
	/smallpart/js/et2_widget_comment.js;
	/smallpart/js/et2_widget_color_radiobox.js;
 */

import {EgwApp} from "../../api/js/jsapi/egw_app";
import {et2_smallpart_videobar} from "./et2_widget_videobar";
import {et2_grid} from "../../api/js/etemplate/et2_widget_grid";
import {et2_container} from "../../api/js/etemplate/et2_core_baseWidget";
import {et2_template} from "../../api/js/etemplate/et2_widget_template";
import {et2_textbox_ro} from "../../api/js/etemplate/et2_widget_textbox";

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

		switch(_name)
		{
			case 'smallpart.student.index':
				this.comments = <Array<CommentType>>this.et2.getArrayMgr('content').getEntry('comments');
				this._student_setCommentArea(false);
				this.filter = {
					course_id: parseInt(<string>this.et2.getArrayMgr('content').getEntry('courses')) || null,
					video_id:  parseInt(<string>this.et2.getArrayMgr('content').getEntry('videos')) || null
				}
				break;

		}
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
		videobar.seek_video(this.edited.comment_starttime);
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
		(<et2_button><unknown>this.et2.getWidgetById('add_comment')).set_disabled(_state);
		(<et2_template>this.et2.getWidgetById('smallpart.student.comment')).set_disabled(!_state);
		this.et2.getWidgetById('hideMaskPlayArea').set_disabled(true);
	}

	public student_playVideo(_pause: boolean)
	{
		let videobar = <et2_smallpart_videobar>this.et2.getWidgetById('video');
		let $play = jQuery(this.et2.getWidgetById('play').getDOMNode());
		this._student_setCommentArea(false);
		if ($play.hasClass('glyphicon-pause') || _pause)
		{
			videobar.pause_video();
			$play.removeClass('glyphicon-pause glyphicon-repeat');
		}
		else
		{
			videobar.set_marking_enabled(false);
			videobar.play_video(
				function(){
					$play.removeClass('glyphicon-pause');
					$play.addClass('glyphicon-repeat');
				},
				function(_id){
					let commentsGrid = jQuery('#smallpart-student-index_comments');
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
		}, this.egw.lang('Delete this comment?'), this.egw.lang('Delete'), et2_dialog.BUTTONS_YES_NO);
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
		let rows = jQuery('table#smallpart-student-index_comments tr');
		let tags = jQuery('.videobar_slider span.commentOnSlider');
		if (!color)
		{
			rows.show();
			tags.show();
		}
		else
		{
			rows.hide();
			tags.hide();
			rows.filter('.commentColor'+color).show();
			tags.filter('.commentColor'+color).show();
		}
	}

	public student_clearFilter()
	{
		this.et2.getWidgetById('comment_color_filter').set_value("");
		this.et2.getWidgetById('comment_search_filter').set_value("");
	}

	public student_searchFilter(_widget)
	{
		let query = _widget.get_value();
		let rows = jQuery('table#smallpart-student-index_comments tr');
		rows.each(function(){
			if (jQuery(this).find('*:contains("'+query+'")').length>1)
			{
				jQuery(this).show();
			}
			else
			{
				jQuery(this).hide();
			}
		})
	}

	public student_onmouseoverFilter(_node, _widget)
	{
		let self = this;
		let videobar = <et2_smallpart_videobar>this.et2.getWidgetById('video');
		let comments = jQuery('#smallpart-student-index_comments');
		if (_widget.get_value())
		{
			comments.on('mouseenter', function(){
				if (jQuery('#smallpart-student-index_play').hasClass('glyphicon-pause')
					&& (!self.edited || self.edited?.action != 'edit')) videobar.pause_video();
			})
			.on('mouseleave', function(){
				if (jQuery('#smallpart-student-index_play').hasClass('glyphicon-pause')
					&& (!self.edited || self.edited?.action != 'edit')) videobar.play_video();
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
		videobar.setMarkingMask(_widget.getValue() =="" ? false : true);
	}

	public student_hideMarkedArea(_node: HTMLElement, _widget)
	{
		let videobar = <et2_smallpart_videobar>this.et2.getWidgetById('video');
		let is_readonly = _widget.getValue() =="" ? true : false;
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

	public student_sliderOnClick(_video: HTMLVideoElement)
	{
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
				_state = !_state? false: true;
			}
			if (widget?.set_readonly) widget.set_readonly(_state);
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
		this.egw.json('smallpart.\\EGroupware\\SmallParT\\Courses.ajax_action',
			[_action.id, ids, false, _password])
			.sendRequest();
	}

	/**
	 * Clickhandler to copy given text or widget content to clipboard
	 *
	 * @param _text default widget content
	 */
	copyClipboard(_widget : et2_textbox_ro, _text : string)
	{
		let backup = _widget.getValue();
		if (_text) {
			_widget.set_value(_text);
		}
		_widget.getDOMNode().select();
		if (_text) {
			_widget.set_value(backup);
		}
		this.egw.message(this.egw.lang("Copied '%1' to clipboard", _text || backup), 'success');
	}
}

app.classes.smallpart = smallpartApp;
