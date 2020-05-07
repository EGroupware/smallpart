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
 */

import {EgwApp} from "../../api/js/jsapi/egw_app";

class smallpartApp extends EgwApp
{
	static readonly appname = 'smallpart';


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

				break;
		}
	}

	student_openComment(_action, _selected)
	{
		if (!isNaN(_selected)) _selected = [{data:this.et2.getArrayMgr('content').getEntry('comments')[_selected]}];
		let data = _selected[0].data;
		let videobar = this.et2.getWidgetById('video');
		let comment = this.et2.getWidgetById('comment');
		this.et2.getWidgetById('play').set_disabled(_action.id=="edit");
		this.et2.getWidgetById('add_comment').set_disabled(true);
		this.et2.getWidgetById('smallpart.student.comment').set_disabled(false);
		videobar.seek_video(data.comment_starttime);


		if (comment)
		{
			if (_action.id=="edit")
			{
				comment.set_value({content:{
					comment_added: data.comment_added,
					comment_starttime: data.comment_starttime,
					markedColorRadio: smallpartApp._convertColorToString(data.comment_color),
					commentColorRadio: smallpartApp._convertColorToString(data.comment_color),
					isOpenOnly: false
				}});
			}
			else
			{
				comment.set_value({content:{
					comment_added: data.comment_added,
					comment_starttime: data.comment_starttime,
					comment_marked_message: egw.lang('Comment is marked as %1', smallpartApp._convertColorToString(data.comment_color)),
					isOpenOnly: true
				}});
			}


		}


	}

	private static _convertColorToString(_color)
	{
		switch(_color)
		{
			case 'ffffff':
				return egw.lang('white');
			case '00ff00':
				return egw.lang('green');
			case 'ff0000':
				return egw.lang('red');
		}
	}

	public student_radioCommentArea(_node, _widget)
	{
		let $radios = jQuery("[id^='smallpart-student-index_commentColorRadio']")
		if (_node.checked)
		{
			$radios.removeClass('checked');
			jQuery(_node).addClass('checked');
		}
	}

	public student_radioMarkedArea(_node, _widget)
	{
		let $radios = jQuery("[id^='smallpart-student-index_markedColorRadio']")
		if (_node.checked)
		{
			$radios.removeClass('checked');
			jQuery(_node).addClass('checked');
		}
	}

	public student_playVideo()
	{
		let videobar = this.et2.getWidgetById('video');
		let $play = jQuery(this.et2.getWidgetById('play').getDOMNode());
		this.et2.getWidgetById('add_comment').set_disabled(false);
		if ($play.hasClass('pause'))
		{
			videobar.pause_video();
			$play.removeClass('pause')
		}
		else
		{
			videobar.play_video();
			$play.addClass('pause');
		}
	}
}

app.classes.smallpart = smallpartApp;
