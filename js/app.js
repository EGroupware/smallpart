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
                this._student_setCommentArea(false);
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
        var self = this;
        this.et2.getWidgetById('play').set_disabled(_action.id !== 'open');
        videobar.seek_video(this.edited.comment_starttime);
        videobar.set_marking_enabled(true, function () {
            self._student_controlCommentAreaButtons(false);
        });
        videobar.setMarks(this.edited.comment_marked);
        videobar.setMarksState(true);
        videobar.setMarkingMask(true);
        this.student_playVideo(true);
        this._student_setCommentArea(true);
        if (comment) {
            this.edited.save_label = this.egw.lang('Save');
            switch (_action.id) {
                case 'retweet':
                    this.edited.save_label = this.egw.lang('Retweet');
                // fall through
                case 'edit':
                    if (_action.id == 'edit')
                        videobar.set_marking_readonly(false);
                    comment.set_value({ content: this.edited });
                    break;
                case 'open':
                    this.et2.getWidgetById('hideMaskPlayArea').set_disabled(false);
                    comment.set_value({ content: {
                            comment_id: this.edited.comment_id,
                            comment_added: this.edited.comment_added,
                            comment_starttime: this.edited.comment_starttime,
                            comment_marked_message: this.color2Label(this.edited.comment_color),
                            comment_marked_color: 'commentColor' + this.edited.comment_color,
                            action: _action.id
                        } });
            }
        }
        this._student_controlCommentAreaButtons(true);
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
    smallpartApp.prototype._student_setCommentArea = function (_state) {
        this.et2.getWidgetById('add_comment').set_disabled(_state);
        this.et2.getWidgetById('smallpart.student.comment').set_disabled(!_state);
        this.et2.getWidgetById('hideMaskPlayArea').set_disabled(true);
    };
    smallpartApp.prototype.student_playVideo = function (_pause) {
        var videobar = this.et2.getWidgetById('video');
        var $play = jQuery(this.et2.getWidgetById('play').getDOMNode());
        this._student_setCommentArea(false);
        if ($play.hasClass('glyphicon-pause') || _pause) {
            videobar.pause_video();
            $play.removeClass('glyphicon-pause glyphicon-repeat');
        }
        else {
            videobar.set_marking_enabled(false);
            videobar.play_video(function () {
                $play.removeClass('glyphicon-pause');
                $play.addClass('glyphicon-repeat');
            }, function (_id) {
                var commentsGrid = jQuery('#smallpart-student-index_comments');
                commentsGrid.find('tr.row.commentBox').removeClass('highlight');
                var scrolledComment = commentsGrid.find('tr.commentID' + _id);
                scrolledComment.addClass('highlight');
                commentsGrid[0].scrollTop = scrolledComment[0].offsetTop;
            });
            $play.removeClass('glyphicon-repeat');
            $play.addClass('glyphicon-pause');
        }
    };
    /**
     * Add new comment / edit button callback
     */
    smallpartApp.prototype.student_addComment = function () {
        var comment = this.et2.getWidgetById('comment');
        var videobar = this.et2.getWidgetById('video');
        var self = this;
        this.student_playVideo(true);
        this.et2.getWidgetById('play').set_disabled(true);
        this._student_setCommentArea(true);
        videobar.set_marking_enabled(true, function () {
            self._student_controlCommentAreaButtons(false);
        });
        videobar.set_marking_readonly(false);
        videobar.setMarks(null);
        this.edited = {
            course_id: this.et2.getWidgetById('courses').get_value(),
            video_id: this.et2.getWidgetById('videos').get_value(),
            comment_starttime: videobar.currentTime(),
            comment_added: [''],
            comment_color: smallpartApp.default_color,
            action: 'edit',
            save_label: this.egw.lang('Save')
        };
        comment.set_value({ content: this.edited });
        comment.getWidgetById('deleteComment').set_disabled(true);
        this._student_controlCommentAreaButtons(true);
    };
    /**
     * Cancel edit and continue button callback
     */
    smallpartApp.prototype.student_cancelAndContinue = function () {
        var videobar = this.et2.getWidgetById('video');
        videobar.removeMarks();
        this.student_playVideo(false);
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
                    comment_starttime: videobar.currentTime(),
                    // ToDo: server-side needs to calculate these
                    comment_added: this.edited.action === 'retweet' ?
                        jQuery.merge(this.edited.comment_added, [egw.user('account_id'), text]) :
                        jQuery.merge([text], this.edited.comment_added.slice(1)),
                    comment_history: !this.edited.comment_id ? null :
                        // retweed seems NOT to be added to history
                        (this.edited.action == 'retweet' ? this.edited.comment_history :
                            jQuery.merge(this.edited.comment_added.slice(0, 1), this.edited.comment_history || [])),
                    comment_marked: videobar.getMarks()
                }),
                this.student_getFilter()
            ]).sendRequest();
        }
        this.student_cancelAndContinue();
    };
    /**
     * Delete comment (either as action from list or by button for currently edited comment)
     *
     * @param _action
     * @param _selected
     */
    smallpartApp.prototype.student_deleteComment = function (_action, _selected) {
        var self = this;
        var comment_id = _action.id === 'delete' ? _selected[0].data.comment_id : self.edited.comment_id;
        et2_dialog.show_dialog(function (_button) {
            var _a;
            if (_button === et2_dialog.YES_BUTTON) {
                self.egw.json('smallpart.\\EGroupware\\SmallParT\\Student\\Ui.ajax_deleteComment', [
                    self.et2.getInstanceManager().etemplate_exec_id,
                    comment_id,
                    self.student_getFilter()
                ]).sendRequest();
                // do we need to clean up the edit-area
                if (comment_id == ((_a = self.edited) === null || _a === void 0 ? void 0 : _a.comment_id))
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
        };
    };
    /**
     * Apply (changed) comment filter
     *
     * Filter is applied by hiding filtered rows client-side
     */
    smallpartApp.prototype.student_filterComments = function () {
        var color = this.et2.getWidgetById('comment_color_filter').get_value();
        var rows = jQuery('table#smallpart-student-index_comments tr');
        var tags = jQuery('.videobar_slider span.commentOnSlider');
        if (!color) {
            rows.show();
            tags.show();
        }
        else {
            rows.hide();
            tags.hide();
            rows.filter('.commentColor' + color).show();
            tags.filter('.commentColor' + color).show();
        }
    };
    smallpartApp.prototype.student_clearFilter = function () {
        this.et2.getWidgetById('comment_color_filter').set_value("");
        this.et2.getWidgetById('comment_search_filter').set_value("");
    };
    smallpartApp.prototype.student_searchFilter = function (_widget) {
        var query = _widget.get_value();
        var rows = jQuery('table#smallpart-student-index_comments tr');
        rows.each(function () {
            if (jQuery(this).find('*:contains("' + query + '")').length > 1) {
                jQuery(this).show();
            }
            else {
                jQuery(this).hide();
            }
        });
    };
    smallpartApp.prototype.student_onmouseoverFilter = function (_node, _widget) {
        var self = this;
        var videobar = this.et2.getWidgetById('video');
        var comments = jQuery('#smallpart-student-index_comments');
        if (_widget.get_value()) {
            comments.on('mouseenter', function () {
                var _a;
                if (jQuery('#smallpart-student-index_play').hasClass('glyphicon-pause')
                    && (!self.edited || ((_a = self.edited) === null || _a === void 0 ? void 0 : _a.action) != 'edit'))
                    videobar.pause_video();
            })
                .on('mouseleave', function () {
                var _a;
                if (jQuery('#smallpart-student-index_play').hasClass('glyphicon-pause')
                    && (!self.edited || ((_a = self.edited) === null || _a === void 0 ? void 0 : _a.action) != 'edit'))
                    videobar.play_video();
            });
        }
        else {
            comments.off('mouseenter mouseleave');
        }
    };
    /**
     * Update comments
     *
     * @param _data see et2_grid.set_value
     */
    smallpartApp.prototype.student_updateComments = function (_data) {
        // update our internal data
        this.comments = _data.content;
        // update grid
        var comments = this.et2.getWidgetById('comments');
        comments.set_value(_data);
        // update slider-tags
        var videobar = this.et2.getWidgetById('video');
        videobar.set_slider_tags(this.comments);
        // re-apply the filter, if not "all"
        var color = this.et2.getWidgetById('comment_color_filter').get_value();
        if (color)
            this.student_filterComments();
        this.et2.getWidgetById('smallpart.student.comments_list').set_disabled(!this.comments.length);
    };
    smallpartApp.prototype.student_revertMarks = function (_event, _widget) {
        var videobar = this.et2.getWidgetById('video');
        videobar.setMarks(this.edited.comment_marked);
        this._student_controlCommentAreaButtons(true);
    };
    smallpartApp.prototype.student_hideBackground = function (_node, _widget) {
        var videobar = this.et2.getWidgetById('video');
        videobar.setMarkingMask(_widget.getValue() == "" ? false : true);
    };
    smallpartApp.prototype.student_hideMarkedArea = function (_node, _widget) {
        var videobar = this.et2.getWidgetById('video');
        var is_readonly = _widget.getValue() == "" ? true : false;
        videobar.setMarksState(!is_readonly);
        var ids = ['markedColorRadio', 'revertMarks', 'deleteMarks'];
        for (var i in ids) {
            var widget = this.et2.getWidgetById('comment').getWidgetById(ids[i]);
            var state = is_readonly;
            if (widget && typeof widget.set_readonly == "function") {
                switch (ids[i]) {
                    case 'revertMarks':
                        state = is_readonly ? is_readonly : !((!this.edited.comment_marked && videobar.getMarks().length > 0) ||
                            (this.edited.comment_marked && videobar.getMarks().length > 0
                                && this.edited.comment_marked.length != videobar.getMarks().length));
                        break;
                    case 'deleteMarks':
                        state = is_readonly ? is_readonly : !(this.edited.comment_marked || videobar.getMarks().length > 0);
                        break;
                }
                widget.set_readonly(state);
            }
        }
    };
    smallpartApp.prototype.student_deleteMarks = function () {
        var videobar = this.et2.getWidgetById('video');
        videobar.removeMarks();
        this._student_controlCommentAreaButtons(true);
    };
    smallpartApp.prototype.student_setMarkingColor = function (_input, _widget) {
        var videobar = this.et2.getWidgetById('video');
        videobar.set_marking_color(_widget.get_value());
    };
    smallpartApp.prototype.student_sliderOnClick = function (_video) {
        this.et2.getWidgetById('play').getDOMNode().classList.remove('glyphicon-repeat');
    };
    smallpartApp.prototype._student_controlCommentAreaButtons = function (_state) {
        var _a, _b;
        var readonlys = ['revertMarks', 'deleteMarks'];
        for (var i in readonlys) {
            var widget = this.et2.getWidgetById('comment').getWidgetById(readonlys[i]);
            if (readonlys[i] == 'deleteMarks') {
                _state = _state ? (_a = !this.et2.getWidgetById('video').getMarks().length, (_a !== null && _a !== void 0 ? _a : false)) : _state;
            }
            else if (this.edited.comment_marked) {
                _state = !_state ? false : true;
            }
            if ((_b = widget) === null || _b === void 0 ? void 0 : _b.set_readonly)
                widget.set_readonly(_state);
        }
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