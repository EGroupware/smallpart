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
	/smallpart/js/et2_widget_filter_participants.js;
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
	filtered?		  : Array<string>; // array filters applied to the comment
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
				this._student_setFilterParticipantsOptions();
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
		let rows = jQuery('table#smallpart-student-index_comments tr').filter('.commentColor'+color);
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
	}

	public student_searchFilter(_widget)
	{
		let query = _widget.get_value();
		let rows = jQuery('table#smallpart-student-index_comments tr');
		let ids = [];
		rows.each(function(){
			if (query != '' && jQuery(this).find('*:contains("'+query+'")').length>1)
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
	 * filters comments
	 *
	 * @param string _filter filter name
	 * @param array _value array comment ids to be filtered, given array of['ALL']
	 * makes all rows hiden and empty array reset the filter.
	 */
	private _student_commentsFiltering(_filter: string, _value: Array<string>)
	{
		let rows = jQuery('table#smallpart-student-index_comments tr');
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
		let activeParticipants = <et2_taglist><unknown>this.et2.getWidgetById('activeParticipantsFilter');
		let passiveParticipantsList = <et2_taglist><unknown>this.et2.getWidgetById('passiveParticipantsList');
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
				commentHeaderMessage.set_value(egw.lang("%1 (%2) participants already answered",
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
	 *
	 * @param _text default widget content
	 */
	copyClipboard(_widget : et2_DOMWidget, _text? : string)
	{
		let value = _text || (typeof _widget.get_value === 'function' ? _widget.get_value() : _widget.options.value);
		let input = jQuery(document.createElement('input'))
			.appendTo(_widget.getDOMNode())
			.val(value)
			.select();
		document.execCommand('copy');
		input.remove();
		this.egw.message(this.egw.lang("Copied '%1' to clipboard", value), 'success');
	}
}

app.classes.smallpart = smallpartApp;
