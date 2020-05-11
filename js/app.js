"use strict";
/**
 * EGroupware - SmallParT - app
 *
 * @link https://www.egroupware.org
 * @package smallpart
 * @subpackage Ui
 * @license http://opensource.org/licenses/gpl-license.php GPL - GNU General Public License
 */
var __extends = (this && this.__extends) || (function () {
    var extendStatics = function (d, b) {
        extendStatics = Object.setPrototypeOf ||
            ({ __proto__: [] } instanceof Array && function (d, b) { d.__proto__ = b; }) ||
            function (d, b) { for (var p in b) if (b.hasOwnProperty(p)) d[p] = b[p]; };
        return extendStatics(d, b);
    };
    return function (d, b) {
        extendStatics(d, b);
        function __() { this.constructor = d; }
        d.prototype = b === null ? Object.create(b) : (__.prototype = b.prototype, new __());
    };
})();
Object.defineProperty(exports, "__esModule", { value: true });
/*egw:uses
    /api/js/jsapi/egw_app.js;
    /smallpart/js/et2_widget_videobar.js;
    /smallpart/js/et2_widget_videotime.js;
    /smallpart/js/et2_widget_comment.js;
 */
var egw_app_1 = require("../../api/js/jsapi/egw_app");
var smallpartApp = /** @class */ (function (_super) {
    __extends(smallpartApp, _super);
    /**
     * Constructor
     *
     * @memberOf app.status
     */
    function smallpartApp() {
        // call parent
        return _super.call(this, 'smallpart') || this;
    }
    /**
     * Destructor
     */
    smallpartApp.prototype.destroy = function (_app) {
        // call parent
        _super.prototype.destroy.call(this, _app);
    };
    /**
     * This function is called when the etemplate2 object is loaded
     * and ready.  If you must store a reference to the et2 object,
     * make sure to clean it up in destroy().
     *
     * @param {etemplate2} _et2 newly ready object
     * @param {string} _name template name
     */
    smallpartApp.prototype.et2_ready = function (_et2, _name) {
        // call parent
        _super.prototype.et2_ready.call(this, _et2, _name);
        switch (_name) {
            case 'smallpart.student.index':
                this.comments = this.et2.getArrayMgr('content').getEntry('comments');
                break;
        }
    };
    /**
     * Opend a comment for editing
     *
     * @param _action
     * @param _selected
     */
    smallpartApp.prototype.student_openComment = function (_action, _selected) {
        if (!isNaN(_selected))
            _selected = [{ data: this.comments[_selected] }];
        this.edited = jQuery.extend({}, _selected[0].data);
        this.edited.action = _action.id;
        var videobar = this.et2.getWidgetById('video');
        var comment = this.et2.getWidgetById('comment');
        this.et2.getWidgetById('play').set_disabled(_action.id !== 'open');
        this.et2.getWidgetById('add_comment').set_disabled(true);
        this.et2.getWidgetById('smallpart.student.comment').set_disabled(false);
        videobar.seek_video(this.edited.comment_starttime);
        if (comment) {
            this.edited.save_label = this.egw.lang('Save and continue');
            switch (_action.id) {
                case 'retweet':
                    this.edited.save_label = this.egw.lang('Retweet and continue');
                // fall through
                case 'edit':
                    comment.set_value({ content: this.edited });
                    break;
                case 'open':
                    comment.set_value({ content: {
                            comment_added: this.edited.comment_added,
                            comment_starttime: this.edited.comment_starttime,
                            comment_marked_message: this.color2Label(this.edited.comment_color),
                            comment_marked_color: 'commentColor' + this.edited.comment_color,
                            action: _action.id
                        } });
            }
        }
    };
    /**
     * Get a label for the used colors: Neutral (white), Positiv (green), Negative (red)
     *
     * @param _color
     * @return string
     */
    smallpartApp.prototype.color2Label = function (_color) {
        switch (_color) {
            case 'ffffff':
                return this.egw.lang('Neutral');
            case '00ff00':
                return this.egw.lang('Positive');
            case 'ff0000':
                return this.egw.lang('Negative');
        }
    };
    smallpartApp.prototype.student_playVideo = function () {
        var videobar = this.et2.getWidgetById('video');
        var $play = jQuery(this.et2.getWidgetById('play').getDOMNode());
        this.et2.getWidgetById('add_comment').set_disabled(false);
        this.et2.getWidgetById('smallpart.student.comment').set_disabled(true);
        if ($play.hasClass('glyphicon-pause')) {
            videobar.pause_video();
            $play.removeClass('glyphicon-pause');
        }
        else {
            videobar.play_video();
            $play.addClass('glyphicon-pause');
        }
    };
    /**
     * Add new comment / edit button callback
     */
    smallpartApp.prototype.student_addComment = function () {
        var comment = this.et2.getWidgetById('comment');
        var videobar = this.et2.getWidgetById('video');
        videobar.pause_video();
        this.et2.getWidgetById('smallpart.student.comment').set_disabled(false);
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
        comment.set_value({ content: this.edited });
        comment.getWidgetById('deleteComment').set_disabled(true);
    };
    /**
     * Cancel edit and continue button callback
     */
    smallpartApp.prototype.student_cancelAndContinue = function () {
        delete this.edited;
        this.et2.getWidgetById('add_comment').set_disabled(false);
        this.et2.getWidgetById('play').set_disabled(false);
        this.et2.getWidgetById('smallpart.student.comment').set_disabled(true);
    };
    /**
     * Save comment/retweet and continue button callback
     */
    smallpartApp.prototype.student_saveAndContinue = function () {
        var _a, _b, _c;
        var comment = this.et2.getWidgetById('comment');
        var videobar = this.et2.getWidgetById('video');
        var text = this.edited.action === 'retweet' ? (_a = comment.getWidgetById('retweet')) === null || _a === void 0 ? void 0 : _a.get_value() : (_b = comment.getWidgetById('comment_added[0]')) === null || _b === void 0 ? void 0 : _b.get_value();
        if (text) // ignore empty comments
         {
            this.egw.json('smallpart.\\EGroupware\\SmallParT\\Student\\Ui.ajax_saveComment', [
                this.et2.getInstanceManager().etemplate_exec_id,
                jQuery.extend(this.edited, {
                    // send action and text to server-side to be able to do a proper ACL checks
                    action: this.edited.action,
                    text: text,
                    comment_color: ((_c = comment.getWidgetById('comment_color')) === null || _c === void 0 ? void 0 : _c.get_value()) || this.edited.comment_color,
                    // ToDo: server-side needs to calculate these
                    comment_added: this.edited.action === 'retweet' ?
                        jQuery.merge(this.edited.comment_added, [egw.user('account_id'), text]) :
                        jQuery.merge([text], this.edited.comment_added.slice(1)),
                    comment_history: !this.edited.comment_id ? null :
                        // retweed seems NOT to be added to history
                        (this.edited.action == 'retweet' ? this.edited.comment_history :
                            jQuery.merge(this.edited.comment_added.slice(0, 1), this.edited.comment_history || [])),
                }),
                this.student_getFilter()
            ]).sendRequest();
        }
        this.student_cancelAndContinue();
    };
    /**
     * Delete edited comment
     */
    smallpartApp.prototype.student_deleteComment = function () {
        var self = this;
        et2_dialog.show_dialog(function (_button) {
            if (_button === et2_dialog.YES_BUTTON) {
                self.egw.json('smallpart.\\EGroupware\\SmallParT\\Student\\Ui.ajax_deleteComment', [
                    self.et2.getInstanceManager().etemplate_exec_id,
                    self.edited.comment_id,
                    self.student_getFilter()
                ]).sendRequest();
                self.student_cancelAndContinue();
            }
        }, this.egw.lang('Delete this comment?'), this.egw.lang('Delete'), et2_dialog.BUTTONS_YES_NO);
    };
    /**
     * Get current active filter
     */
    smallpartApp.prototype.student_getFilter = function () {
        return {
            course_id: this.et2.getWidgetById('courses').get_value(),
            video_id: this.et2.getWidgetById('videos').get_value(),
            comment_color: this.et2.getWidgetById('comment_color_filter').get_value()
        };
    };
    /**
     * Apply changed comment filter
     *
     * ToDo: could be done client-side by backing up this.comments and filtering or restoring them
     *
     * @param _widget
     */
    smallpartApp.prototype.student_filterComments = function (_widget) {
        this.egw.json('smallpart.\\EGroupware\\SmallParT\\Student\\Ui.ajax_filterComments', [
            this.student_getFilter()
        ]).sendRequest();
    };
    /**
     * Update comments
     *
     * @param _data see et2_grid.set_value
     */
    smallpartApp.prototype.student_updateComments = function (_data) {
        // update grid
        var comments = this.et2.getWidgetById('comments');
        comments.set_value(_data);
        // update our internal data
        this.comments = _data.content;
    };
    smallpartApp.prototype.student_revertMarks = function () {
    };
    smallpartApp.prototype.student_hideBackground = function (_node, _widget) {
        var videobar = this.et2.getWidgetById('video');
        videobar.setMarkingMask(_widget.getValue() != "" ? false : true);
    };
    smallpartApp.prototype.student_hideMarkedArea = function () {
    };
    smallpartApp.prototype.student_deleteMarks = function () {
    };
    /**
     * Subscribe to a course / ask course password
     *
     * @param _action
     * @param _senders
     */
    smallpartApp.prototype.subscribe = function (_action, _senders) {
        var self = this;
        et2_dialog.show_prompt(function (_button_id, _password) {
            if (_button_id == et2_dialog.OK_BUTTON) {
                self.courseAction(_action, _senders, _password);
            }
        }, this.egw.lang("Please enter the course password"), this.egw.lang("Subscribe to course"), {}, et2_dialog.BUTTONS_OK_CANCEL, et2_dialog.QUESTION_MESSAGE);
    };
    /**
     * Execute a server-side action on a course
     *
     * @param _action
     * @param _senders
     * @param _password
     */
    smallpartApp.prototype.courseAction = function (_action, _senders, _password) {
        var ids = [];
        _senders.forEach(function (_sender) {
            ids.push(_sender.id.replace('smallpart::', ''));
        });
        this.egw.json('smallpart.\\EGroupware\\SmallParT\\Courses.ajax_action', [_action.id, ids, false, _password])
            .sendRequest();
    };
    smallpartApp.appname = 'smallpart';
    smallpartApp.default_color = 'ffffff'; // white = neutral
    return smallpartApp;
}(egw_app_1.EgwApp));
app.classes.smallpart = smallpartApp;
//# sourceMappingURL=app.js.map