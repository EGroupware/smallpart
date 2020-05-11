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
 */

import {EgwApp} from "../../api/js/jsapi/egw_app";
import {et2_smallpart_videobar} from "./et2_widget_videobar";
import {et2_grid} from "../../api/js/etemplate/et2_widget_grid";
import {et2_container} from "../../api/js/etemplate/et2_core_baseWidget";
import {et2_template} from "../../api/js/etemplate/et2_widget_template";

/**
 * Comment type and it's attributes
 */
interface CommentType {
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
		this.et2.getWidgetById('play').set_disabled(_action.id !== 'open');
		this.et2.getWidgetById('add_comment').set_disabled(true);
		this.et2.getWidgetById('smallpart.student.comment').set_disabled(false);
		videobar.seek_video(this.edited.comment_starttime);

		if (comment)
		{
			this.edited.save_label = this.egw.lang('Save and continue');
			switch (_action.id)
			{
				case 'retweet':
					this.edited.save_label = this.egw.lang('Retweet and continue');
					// fall through
				case 'edit':
					comment.set_value({content: this.edited});
					break;

				case 'open':
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
				return this.egw.lang('Neutral');
			case '00ff00':
				return this.egw.lang('Positive');
			case 'ff0000':
				return this.egw.lang('Negative');
		}
	}

	public student_playVideo()
	{
		let videobar = <et2_smallpart_videobar>this.et2.getWidgetById('video');
		let $play = jQuery(this.et2.getWidgetById('play').getDOMNode());
		this.et2.getWidgetById('add_comment').set_disabled(false);
		this.et2.getWidgetById('smallpart.student.comment').set_disabled(true);
		if ($play.hasClass('glyphicon-pause'))
		{
			videobar.pause_video();
			$play.removeClass('glyphicon-pause')
		}
		else
		{
			videobar.play_video();
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
		videobar.pause_video();
		(<et2_template>this.et2.getWidgetById('smallpart.student.comment')).set_disabled(false);
		this.et2.getWidgetById('play').set_disabled(true);
		this.et2.getWidgetById('add_comment').set_disabled(true);

		this.edited = {
			course_id: this.et2.getWidgetById('courses').get_value(),
			video_id: this.et2.getWidgetById('videos').get_value(),
			comment_starttime: videobar.currentTime(),
			comment_added: [''],
			comment_color: smallpartApp.default_color,
			action: 'edit',
			save_label: this.egw.lang('Save and continue')
		};

		comment.set_value({content: this.edited});
		comment.getWidgetById('deleteComment').set_disabled(true);
	}

	/**
	 * Cancel edit and continue button callback
	 */
	public student_cancelAndContinue()
	{
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
					// ToDo: server-side needs to calculate these
					comment_added: this.edited.action === 'retweet' ?
						jQuery.merge(this.edited.comment_added, [egw.user('account_id'), text]) :
						jQuery.merge([text], this.edited.comment_added.slice(1)),
					comment_history: !this.edited.comment_id ? null :
						// retweed seems NOT to be added to history
						(this.edited.action == 'retweet' ? this.edited.comment_history :
							jQuery.merge(this.edited.comment_added.slice(0, 1), this.edited.comment_history || [])),
					// ToDo: comment_marked:
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
				if (comment_id == self.edited.comment_id) self.student_cancelAndContinue();
			}
		}, this.egw.lang('Delete this comment?'), this.egw.lang('Delete'), et2_dialog.BUTTONS_YES_NO);
	}

	/**
	 * Get current active filter
	 */
	protected student_getFilter()
	{
		return {
			course_id: this.et2.getWidgetById('courses').get_value(),
			video_id: this.et2.getWidgetById('videos').get_value(),
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
		if (!color)
		{
			rows.show();
		}
		else
		{
			rows.hide();
			rows.filter('.commentColor'+color).show();
		}
	}

	/**
	 * Update comments
	 *
	 * @param _data see et2_grid.set_value
	 */
	public student_updateComments(_data)
	{
		// update grid
		let comments = <et2_grid>this.et2.getWidgetById('comments');
		comments.set_value(_data);

		// re-apply the filter, if not "all"
		let color = this.et2.getWidgetById('comment_color_filter').get_value();
		if (color) this.student_filterComments();

		// update our internal data
		this.comments = _data.content;

		// update slider-tags
		let videobar = <et2_smallpart_videobar>this.et2.getWidgetById('video');
		videobar.set_slider_tags(this.comments);
	}

	public student_revertMarks()
	{

	}

	public student_hideBackground(_node, _widget)
	{
		let videobar = this.et2.getWidgetById('video');
		videobar.setMarkingMask(_widget.getValue() !="" ? false : true);
	}

	public student_hideMarkedArea()
	{

	}

	public student_deleteMarks()
	{

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
}

app.classes.smallpart = smallpartApp;
