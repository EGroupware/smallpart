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
    /smallpart/js/et2_widget_videooverlay.js;
    /smallpart/js/et2_widget_videooverlay_slider_controller.js;
    /smallpart/js/et2_widget_videotime.js;
    /smallpart/js/et2_widget_comment.js;
    /smallpart/js/et2_widget_color_radiobox.js;
    /smallpart/js/et2_widget_filter_participants.js;
    /smallpart/js/mark_helpers.js;
 */
var egw_app_1 = require("../../api/js/jsapi/egw_app");
var et2_widget_videobar_1 = require("./et2_widget_videobar");
var mark_helpers_1 = require("./mark_helpers");
require("./et2_widget_videooverlay");
require("./et2_widget_color_radiobox");
require("./et2_widget_comment");
require("./et2_widget_filter_participants");
var et2_widget_dialog_1 = require("../../api/js/etemplate/et2_widget_dialog");
var et2_widget_checkbox_1 = require("../../api/js/etemplate/et2_widget_checkbox");
var et2_core_widget_1 = require("../../api/js/etemplate/et2_core_widget");
var smallpartApp = /** @class */ (function (_super) {
    __extends(smallpartApp, _super);
    /**
     * Constructor
     *
     * @memberOf app.status
     */
    function smallpartApp() {
        var _this = 
        // call parent
        _super.call(this, 'smallpart') || this;
        /**
         * Active filter classes
         */
        _this.filters = {};
        /**
         * Course options: &1 = record watched videos
         */
        _this.course_options = 0;
        _this.user = parseInt(_this.egw.user('account_id'));
        return _this;
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
        var _this = this;
        // call parent
        _super.prototype.et2_ready.call(this, _et2, _name);
        var content = this.et2.getArrayMgr('content');
        switch (true) {
            case (_name.match(/smallpart.student.index/) !== null):
                this.is_staff = content.getEntry('is_staff');
                this.comments = content.getEntry('comments');
                this._student_setCommentArea(false);
                if (content.getEntry('video')['video_options'] == smallpartApp.COMMENTS_FORBIDDEN_BY_STUDENTS &&
                    (content.getEntry('course_options') & et2_widget_videobar_1.et2_smallpart_videobar.course_options_cognitive_load_measurement)
                        == et2_widget_videobar_1.et2_smallpart_videobar.course_options_cognitive_load_measurement) {
                    this.et2.setDisabledById('add_comment', true);
                    this.et2.setDisabledById('add_note', false);
                }
                // set process CL Questionnaire when the test is running
                if (parseInt(content.getEntry('video')['video_test_duration']) > 0 && content.getEntry('timer') > 0) {
                    this._student_setProcessCLQuestions();
                }
                this.filter = {
                    course_id: parseInt(content.getEntry('courses')) || null,
                    video_id: parseInt(content.getEntry('videos')) || null
                };
                if (this.egw.preference('comments_column_state', 'smallpart') == 0 || !this.egw.preference('comments_column_state', 'smallpart')) {
                    this.egw.set_preference('smallpart', 'comments_column_state', 0);
                    this.et2.getDOMWidgetById('comments_column').set_value(true);
                    this.et2.getDOMWidgetById('comments').set_class('hide_column');
                }
                else {
                    this.et2.getDOMWidgetById('comments_column').set_value(false);
                    this.et2.getDOMWidgetById('comments').getDOMNode().classList.remove('hide_column');
                }
                this.course_options = parseInt(content.getEntry('course_options')) || 0;
                this._student_setFilterParticipantsOptions();
                var self_1 = this;
                jQuery(window).on('resize', function () {
                    self_1._student_resize();
                });
                // record, in case of F5 or window closed
                window.addEventListener("beforeunload", function () {
                    self_1.set_video_position();
                    self_1.record_watched();
                    // record unload time, if a CL measurement test is running, in case user did not stop it properly
                    if (parseInt(content.getEntry('video')['video_test_duration']) > 0 && content.getEntry('timer') > 0 &&
                        (content.getEntry('course_options') & et2_widget_videobar_1.et2_smallpart_videobar.course_options_cognitive_load_measurement)
                            == et2_widget_videobar_1.et2_smallpart_videobar.course_options_cognitive_load_measurement) {
                        self_1.egw.json('smallpart.\\EGroupware\\SmallParT\\Student\\Ui.ajax_recordCLMeasurement', [
                            content.getEntry('video')['course_id'], content.getEntry('video')['video_id'],
                            smallpartApp.CLM_TYPE_UNLOAD, []
                        ]).sendRequest('keepalive');
                    }
                });
                // video might not be loaded due to test having to be started first
                var voloff = this.et2.getWidgetById('voloff');
                if (voloff)
                    voloff.getDOMNode().style.opacity = '0.5';
                var videobar_1 = this.et2.getWidgetById('video');
                if (videobar_1) {
                    videobar_1.video[0].addEventListener('et2_video.onReady.' + videobar_1.id, function (_) {
                        _this.et2.getWidgetById('volume').set_value('50');
                        videobar_1.set_volume(50);
                    });
                    var notSeekable_1 = videobar_1.getArrayMgr('content').getEntry('video')['video_test_options'] & et2_widget_videobar_1.et2_smallpart_videobar.video_test_option_not_seekable;
                    ['forward', 'backward', 'playback', 'playback_slow', 'playback_fast'].forEach(function (_item) {
                        _this.et2.getDOMWidgetById(_item).set_disabled(notSeekable_1);
                    });
                }
                if (this.is_staff)
                    this.et2.getDOMWidgetById('activeParticipantsFilter').getDOMNode().style.width = "70%";
                break;
            case (_name === 'smallpart.question'):
                if (content.getEntry('max_answers')) {
                    this.et2.getWidgetById('answers').iterateOver(function (_widget) {
                        if (_widget.id === '1[checked]' || _widget.id === '1[correct]') {
                            this.checkMaxAnswers(undefined, _widget, undefined);
                        }
                    }, this, et2_widget_checkbox_1.et2_checkbox);
                }
                this.defaultPoints();
                var vdh = this.et2.getWidgetById("video_data_helper");
                vdh.video[0].addEventListener("et2_video.onReady." + vdh.id, function (_) { _this.questionTime(); });
                // set markings for mark or mill-out questions
                this.setMarkings();
                break;
            case (_name === 'smallpart.course'):
                // disable import button until a file is selected
                var import_button_1 = this.et2.getWidgetById('button[import]');
                import_button_1 === null || import_button_1 === void 0 ? void 0 : import_button_1.set_readonly(true);
                this.et2.getWidgetById('import').options.onFinish = function (_ev, _count) {
                    import_button_1.set_readonly(!_count);
                };
                // seem because set_value of the grid, we need to defer after, to work for updates/apply too
                window.setTimeout(function () { return _this.disableGroupByRole(); }, 0);
                break;
            case (_name === 'smallpart.lti-content-selection'):
                var video_id = this.et2.getWidgetById('video_id');
                if (video_id.getValue()) {
                    this.ltiVideoSelection(undefined, video_id);
                }
                break;
        }
    };
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
    smallpartApp.prototype.observer = function (_msg, _app, _id, _type, _msg_type, _links) {
        if (_app === 'smallpart-overlay') {
            var overlay = this.et2.getWidgetById('videooverlay');
            if (overlay) {
                overlay.renderElements(_id);
                return false;
            }
        }
    };
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
    smallpartApp.prototype.push = function (pushData) {
        // don't care about other apps data, reimplement if your app does care eg. calendar
        if (pushData.app !== this.appname)
            return;
        // check if a comment is pushed
        if (typeof pushData.id === 'string' && pushData.id.match(/^\d+:\d+:\d+$/)) {
            this.pushComment(pushData.id, pushData.type, pushData.acl, pushData.account_id);
        }
        // check if a participant-update is pushed
        else if (typeof pushData.id === 'string' && pushData.id.match(/^\d+:P$/)) {
            this.pushParticipants(pushData.id, pushData.type, pushData.acl);
        }
        // check if course-update is pushed
        else if (typeof pushData.id === 'number') {
            // update watched video / student UI
            if (pushData.id == this.student_getFilter().course_id &&
                (Object.keys(pushData.acl).length || pushData.type === 'delete')) {
                this.pushCourse(pushData.id, pushData.type, pushData.acl);
            }
            // call parent to handle course-list
            return _super.prototype.push.call(this, pushData);
        }
    };
    /**
     * Add or update pushed participants (we're currently not pushing deletes)
     *
     * @param course_id
     * @param type currently only "update"
     * @param course undefined for type==="delete"
     */
    smallpartApp.prototype.pushCourse = function (course_id, type, course) {
        var _a, _b, _c, _f;
        var filter = this.student_getFilter();
        var course_selection = this.et2.getWidgetById('courses');
        // if course got closed (for students) --> go to manage courses
        if ((course.course_closed == 1 || type === 'delete' || !Object.keys(course).length)) {
            course_selection.change(course_selection.getDOMNode(), course_selection, 'manage');
            console.log('unselecting no longer accessible or deleted course');
            return;
        }
        var sel_options = this.et2.getArrayMgr('sel_options');
        // update course-name, if changed
        var courses = sel_options.getEntry('courses');
        for (var n in courses) {
            if (courses[n].value == course_id) {
                courses[n].label = course.course_name;
                course_selection.set_select_options(courses);
                break;
            }
        }
        // update video-names
        var video_selection = this.et2.getWidgetById('videos');
        video_selection === null || video_selection === void 0 ? void 0 : video_selection.set_select_options(course.video_labels);
        // currently watched video no longer exist / accessible --> select no video (causing submit to server)
        if (video_selection && typeof course.videos[filter.video_id] === 'undefined') {
            video_selection.change(video_selection.getDOMNode(), video_selection, '');
            console.log('unselecting no longer accessible or deleted video');
            return;
        }
        // update currently watched video
        var video = course.videos[filter.video_id];
        var task = this.et2.getWidgetById('video[video_question]');
        task.set_value(video.video_question);
        task.getParent().set_statustext(video.video_question);
        // video.video_options or _published* changed --> reload
        var content = this.et2.getArrayMgr('content');
        var old_video = content.getEntry('video');
        if (video.video_options != old_video.video_options ||
            video.video_published != old_video.video_published ||
            ((_a = video.video_published_start) === null || _a === void 0 ? void 0 : _a.date) != ((_b = old_video === null || old_video === void 0 ? void 0 : old_video.video_published_start) === null || _b === void 0 ? void 0 : _b.date) ||
            ((_c = video.video_published_end) === null || _c === void 0 ? void 0 : _c.date) != ((_f = old_video === null || old_video === void 0 ? void 0 : old_video.video_published_end) === null || _f === void 0 ? void 0 : _f.date)) {
            video_selection === null || video_selection === void 0 ? void 0 : video_selection.change(video_selection.getDOMNode(), video_selection, undefined);
            console.log('reloading as video_options/_published changed', old_video, video);
            return;
        }
        // add video_test_* (and all other video attributes) to content, so we use them from there
        Object.assign(content.data.video, video);
        // course-options: &1 = record watched, &2 = display watermark
        this.course_options = course.course_options;
        // update groups
        var group = this.et2.getWidgetById('group');
        var group_options = Object.values(this.et2.getArrayMgr('sel_options').getEntry('group') || {}).slice(-2);
        for (var g = 1; g <= course.course_groups; ++g) {
            group_options.splice(g - 1, 0, { value: g, label: this.egw.lang('Group %1', g) });
        }
        group === null || group === void 0 ? void 0 : group.set_select_options(group_options);
    };
    /**
     * Add or update pushed participants (we're currently not pushing deletes)
     *
     * @param id "course_id:P"
     * @param type "add" or "update"
     * @param participants
     */
    smallpartApp.prototype.pushParticipants = function (id, type, participants) {
        var _this = this;
        var course_id = id.split(':').shift();
        var sel_options = this.et2.getArrayMgr('sel_options');
        if (this.student_getFilter().course_id != course_id || typeof sel_options === 'undefined') {
            return;
        }
        // check if current user is no longer subscribed / has been kicked from the course
        if (type === 'unsubscribe') {
            participants.forEach(function (participant) {
                if (participant.account_id == _this.user) {
                    var course_selection = _this.et2.getWidgetById('courses');
                    course_selection.change(course_selection.getDOMNode(), course_selection, 'manage');
                    console.log('unselecting no longer accessible course');
                    return;
                }
            });
            return;
        }
        var account_ids = sel_options.getEntry('account_id');
        var group = account_ids.filter(function (account) { return account.value == _this.user; })[0].group;
        var video = this.et2.getArrayMgr('content').getEntry('video');
        var need_comment_update = false;
        participants.forEach(function (participant) {
            for (var key in account_ids) {
                if (account_ids[key].value == participant.value) {
                    // current user is a student AND group of an other student changed AND
                    if (!need_comment_update && !_this.is_staff && participant.group !== account_ids[key].group &&
                        // groups matter for this video AND
                        (video.video_options == smallpartApp.COMMENTS_GROUP ||
                            video.video_options == smallpartApp.COMMENTS_GROUP_HIDE_TEACHERS) &&
                        // AND own student-group is affected (student added or removed from current group, other groups wont matter)
                        (group == account_ids[key].group || group == participant.group)) {
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
        sel_options.data.account_id = account_ids;
        // do we need to update the comments (because student changed group)
        if (need_comment_update) {
            this.egw.json('smallpart.\\EGroupware\\SmallParT\\Student\\Ui.ajax_listComments', [
                this.student_getFilter()
            ]).sendRequest();
        }
        // or just refresh them to show modified names (no need for new participants without comments yet)
        else if (type !== 'add') {
            this.student_updateComments({ content: this.comments });
            this._student_setFilterParticipantsOptions();
        }
    };
    /**
     * Add or update pushed comment
     *
     * @param id "course_id:video_id:comment_id"
     * @param type "add", "update" or "delete"
     * @param comment undefined for type==='delete'
     */
    smallpartApp.prototype.pushComment = function (id, type, comment, account_id) {
        var _a, _b;
        var _this = this;
        var course_id, video_id, comment_id;
        _a = id.split(':'), course_id = _a[0], video_id = _a[1], comment_id = _a[2];
        // show message only for re-tweets / edits on own comments
        if (account_id != this.user && comment.account_id == this.user) {
            switch (type) {
                case 'add':
                    this.egw.link_title('api-accounts', account_id, function (_nick) {
                        _this.egw.message(_this.egw.lang('%1 commented on %2: %3 at %4', _nick, comment.course_name, comment.video_name, _this.timeFormat(comment.comment_starttime)) +
                            "\n\n" + comment.comment_added[0]);
                    });
                    break;
                case 'edit':
                    this.egw.link_title('api-accounts', account_id, function (_nick) {
                        _this.egw.message(_this.egw.lang('%1 edited on %2: %3 at %4', _nick, comment.course_name, comment.video_name, _this.timeFormat(comment.comment_starttime)) +
                            "\n\n" + comment.comment_added[0]);
                    });
                    break;
                case 'retweet':
                    this.egw.link_title('api-accounts', account_id, function (_nick) {
                        _this.egw.message(_this.egw.lang('%1 retweeted on %2: %3 at %4 to', _nick, comment.course_name, comment.video_name, _this.timeFormat(comment.comment_starttime)) +
                            "\n\n" + comment.comment_added[0] + "\n\n" + comment.comment_added[comment.comment_added.length - 1]);
                    });
                    break;
            }
        }
        // if we show student UI the comment belongs to, update comments with it
        if (this.et2.getInstanceManager().name.match(/smallpart.student.index/) !== null &&
            this.student_getFilter().course_id == course_id && this.student_getFilter().video_id == video_id) {
            this.addCommentClass(comment);
            // integrate pushed comment in own data and add/update it there
            if (this.comments.length > 1) {
                for (var n = 0; n < this.comments.length; ++n) {
                    if (!this.comments[n] || this.comments[n].length == 0)
                        continue;
                    var comment_n = this.comments[n];
                    if (type === 'add' && comment_n.comment_starttime > comment.comment_starttime) {
                        this.comments.splice(n, 0, comment);
                        break;
                    }
                    if (type === 'add' && n == this.comments.length - 1) {
                        this.comments.push(comment);
                        break;
                    }
                    if (type !== 'add' && comment_n.comment_id == comment.comment_id) {
                        if (type === 'delete') {
                            this.comments.splice(n, 1);
                        }
                        else {
                            // with limited visibility of comments eg. student can see other students teacher updating
                            // their posts would remove retweets --> keep them
                            if (comment.comment_added.length === 1 && this.comments[n].comment_added.length > 1) {
                                (_b = comment.comment_added).push.apply(_b, this.comments[n].comment_added.slice(1));
                            }
                            this.comments[n] = comment;
                        }
                        break;
                    }
                }
            }
            else if (type === 'add') {
                this.comments.push(comment);
            }
        }
        this.student_updateComments({ content: this.comments });
    };
    /**
     * Client-side equivalent of Student\Ui::_fixComments()
     *
     * @param comment
     * @protected
     */
    smallpartApp.prototype.addCommentClass = function (comment) {
        // add class(es) regular added on server-side
        if (this.is_staff || comment.account_id == this.user) {
            comment.class = 'commentOwner';
        }
        else {
            comment.class = '';
        }
        if (comment.comment_marked && comment.comment_marked.length) {
            comment.class += ' commentMarked';
        }
    };
    /**
     * Format time in seconds as 0:00 or 0:00:00
     *
     * @param secs
     * @return string
     */
    smallpartApp.prototype.timeFormat = function (secs) {
        if (secs < 3600) {
            return sprintf('%d:%02d', secs / 60, secs % 60);
        }
        return sprintf('%d:%02d:%02d', secs / 3600, (secs % 3600) / 60, secs % 60);
    };
    smallpartApp.prototype._student_resize = function () {
        var comments = this.et2.getWidgetById('comments').getDOMNode();
        jQuery(comments).height(jQuery(comments).height() +
            jQuery('form[id^="smallpart-student-index"]').height()
            - jQuery('.rightBoxArea').height() - 40);
    };
    smallpartApp.prototype.student_saveAndCloseCollabora = function () {
        document.getElementsByClassName('note_container')[0].style.display = 'none';
        jQuery('#smallpart-student-index-theme1_note')[0].contentWindow.app.collabora.WOPIPostMessage('Action_Save');
    };
    /**
     * Opend a comment for editing
     *
     * @param _action
     * @param _selected
     */
    smallpartApp.prototype.student_openComment = function (_action, _selected) {
        var _this = this;
        if (!isNaN(_selected))
            _selected = [{ data: this.comments[_selected] }];
        this.edited = jQuery.extend({}, _selected[0].data);
        this.edited.action = _action.id;
        var videobar = this.et2.getWidgetById('video');
        var comment = this.et2.getWidgetById('comment');
        var self = this;
        var content = videobar.getArrayMgr('content').data;
        // Do not seek for comments when we are in not seekable
        if (_action.id == 'open' && !content.is_staff && (content.video.video_test_options
            & et2_widget_videobar_1.et2_smallpart_videobar.video_test_option_not_seekable))
            return;
        smallpartApp.playControllWidgets.forEach(function (w) {
            _this.et2.getWidgetById(w).set_disabled(_action.id !== 'open');
        });
        // record in case we're playing
        this.record_watched();
        videobar.seek_video(this.edited.comment_starttime);
        // start recording again, in case we're playing
        if (!videobar.paused())
            this.start_watching();
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
            // readonly --> disable edit and retweet
            if (this.et2.getArrayMgr('content').getEntry('video').accessible === 'readonly') {
                _action.id = 'open';
            }
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
                return this.egw.lang('White');
            case '00ff00':
                return this.egw.lang('Green');
            case 'ff0000':
                return this.egw.lang('Red');
            case 'ffff00':
                return this.egw.lang('Yellow');
        }
    };
    /**
     * set up post_cl_questions dialog
     * @private
     */
    smallpartApp.prototype._student_setPostCLQuestions = function (_callback) {
        var _this = this;
        var dialog = function () {
            return et2_core_widget_1.et2_createWidget("dialog", {
                callback: _callback,
                buttons: [
                    { text: _this.egw.lang("Continue"), id: "continue" },
                ],
                title: '',
                message: '',
                icon: et2_widget_dialog_1.et2_dialog.QUESTION_MESSAGE,
                value: {
                    content: {
                        value: '',
                    }
                },
                closeOnEscape: false,
                dialogClass: 'questionnaire',
                width: 500,
                template: egw.webserverUrl + '/smallpart/templates/default/post_cl_questions.xet'
            }, et2_widget_dialog_1.et2_dialog._create_parent('smallpart'));
        };
        dialog();
    };
    smallpartApp.prototype.student_testFinished = function (_widget) {
        var _this = this;
        var content = this.et2.getArrayMgr('content');
        var widget = _widget;
        var self = this;
        var callback = function (_w) {
            if ((content.getEntry('course_options') & et2_widget_videobar_1.et2_smallpart_videobar.course_options_cognitive_load_measurement)
                == et2_widget_videobar_1.et2_smallpart_videobar.course_options_cognitive_load_measurement) {
                // record a stop time once before post questions and after user decided to finish the test
                self.egw.json('smallpart.\\EGroupware\\SmallParT\\Student\\Ui.ajax_recordCLMeasurement', [
                    content.getEntry('video')['course_id'], content.getEntry('video')['video_id'],
                    smallpartApp.CLM_TYPE_STOP, []
                ]).sendRequest();
                var timer = self.et2.getDOMWidgetById('timer');
                // reset the alarms after the test is finished
                timer.options.alarm = [];
                _this._student_setPostCLQuestions(function (_button, _value) {
                    if (_button === "continue" && _value) {
                        self.egw.json('smallpart.\\EGroupware\\SmallParT\\Student\\Ui.ajax_recordCLMeasurement', [
                            content.getEntry('video')['course_id'], content.getEntry('video')['video_id'],
                            smallpartApp.CLM_TYPE_POST, _value
                        ]).sendRequest();
                    }
                    _w.getRoot().getInstanceManager().submit(_w.id);
                });
            }
            else {
                _w.getRoot().getInstanceManager().submit(_w.id);
            }
        };
        switch (_widget.id) {
            case 'stop':
                et2_widget_dialog_1.et2_dialog.show_dialog(function (_button) {
                    if (_button == et2_widget_dialog_1.et2_dialog.YES_BUTTON) {
                        callback(_widget);
                    }
                }, 'If you finish the test, you will not be able to enter it again!', 'Finish test?');
                break;
            case 'timer':
                callback(_widget);
                break;
        }
    };
    /**
     * set up process_cl_questions dialog
     * @private
     */
    smallpartApp.prototype._student_setProcessCLQuestions = function () {
        var _this = this;
        var content = this.et2.getArrayMgr('content');
        // only run this if we are in CLM mode
        if ((content.getEntry('course_options') & et2_widget_videobar_1.et2_smallpart_videobar.course_options_cognitive_load_measurement)
            != et2_widget_videobar_1.et2_smallpart_videobar.course_options_cognitive_load_measurement)
            return;
        var self = this;
        var video_test_duration = parseInt(content.getEntry('video')['video_test_duration']) * 60;
        var repeat = 4; //@todo: should be replaced with an option from course/video
        // first alarm should is set to 60 sec to popup up before the test ends
        var alarms = [60];
        // keeps the reply timeout id
        var replyTimeout = null;
        for (var i = 1; i < repeat; i++) {
            alarms[i] = i * (video_test_duration / repeat);
        }
        var timer = this.et2.getDOMWidgetById('timer');
        timer.options.alarm = alarms;
        // callback to be called for alarm
        timer.onAlarm = function () {
            var d = dialog();
            replyTimeout = setTimeout(function () {
                this.div.parent().find('.ui-dialog-buttonpane').find('button').click();
            }.bind(d), 60000);
        };
        var dialog = function () {
            _this.student_playVideo(true);
            return et2_core_widget_1.et2_createWidget("dialog", {
                callback: function (_button, _value) {
                    if (_button === "continue" && _value) {
                        self.egw.json('smallpart.\\EGroupware\\SmallParT\\Student\\Ui.ajax_recordCLMeasurement', [
                            content.getEntry('video')['course_id'], content.getEntry('video')['video_id'],
                            smallpartApp.CLM_TYPE_PROCESS, _value
                        ]).sendRequest();
                        clearTimeout(replyTimeout);
                    }
                },
                buttons: [{ text: _this.egw.lang("Continue"), id: "continue" }],
                title: '',
                message: '',
                icon: et2_widget_dialog_1.et2_dialog.QUESTION_MESSAGE,
                value: { content: { value: '' } },
                closeOnEscape: false,
                dialogClass: 'questionnaire clm-process',
                width: 400,
                template: egw.webserverUrl + '/smallpart/templates/default/process_cl_questions.xet'
            }, et2_widget_dialog_1.et2_dialog._create_parent('smallpart'));
        };
    };
    smallpartApp.prototype._student_setCommentArea = function (_state) {
        try {
            this.et2.setDisabledById('add_comment', _state);
            this.et2.setDisabledById('smallpart.student.comment', !_state);
            this.et2.setDisabledById('hideMaskPlayArea', true);
            this._student_resize();
        }
        catch (e) { }
    };
    smallpartApp.prototype.student_playControl = function (_status) {
        var _this = this;
        var videobar = this.et2.getWidgetById('video');
        var volume = this.et2.getWidgetById('volume');
        var playback = this.et2.getWidgetById('playback');
        var videooverlay = this.et2.getWidgetById('videooverlay');
        if (_status && _status._type === 'select') {
            videobar.set_playBackRate(parseFloat(_status.getValue()));
            return;
        }
        switch (_status) {
            case "playback_fast":
                if (playback.getDOMNode().selectedIndex < playback.getDOMNode().options.length - 1) {
                    playback.getDOMNode().selectedIndex++;
                    playback.set_value(playback.getDOMNode().selectedOptions[0].value);
                }
                break;
            case "playback_slow":
                if (playback.getDOMNode().selectedIndex > 0) {
                    playback.getDOMNode().selectedIndex--;
                    playback.set_value(playback.getDOMNode().selectedOptions[0].value);
                }
                break;
            case "forward":
                if (videobar.currentTime() + 10 <= videobar.duration()) {
                    videobar.seek_video(videobar.currentTime() + 10);
                    videooverlay._elementSlider.set_seek_position(Math.round(videobar._vtimeToSliderPosition(videobar.currentTime())));
                }
                break;
            case "backward":
                if (videobar.currentTime() - 10 >= 0) {
                    videobar.seek_video(videobar.currentTime() - 10);
                    videooverlay._elementSlider.set_seek_position(Math.round(videobar._vtimeToSliderPosition(videobar.currentTime())));
                }
                break;
            case "volup":
                videobar.set_volume(videobar.get_volume() + 10);
                setTimeout(function (_) { volume.set_value(videobar.get_volume()); }, 100);
                break;
            case "voldown":
                videobar.set_volume(videobar.get_volume() - 10);
                setTimeout(function (_) { volume.set_value(videobar.get_volume()); }, 100);
                break;
            case "voloff":
                videobar.set_mute(!videobar.get_mute());
                setTimeout(function (_) {
                    if (videobar.get_mute()) {
                        ['voldown', 'volup', 'voloff'].forEach(function (_w) {
                            var node = _this.et2.getWidgetById(_w).getDOMNode();
                            node.style.opacity = _w === 'voloff' ? '1' : '0.5';
                            node.style.pointerEvents = _w === 'voloff' ? 'auto' : 'none';
                        });
                    }
                    else {
                        ['voldown', 'volup', 'voloff'].forEach(function (_w) {
                            var node = _this.et2.getWidgetById(_w).getDOMNode();
                            node.style.opacity = _w == 'voloff' ? '0.5' : '1';
                            node.style.pointerEvents = 'auto';
                        });
                    }
                }, 100);
                break;
            case "fullwidth":
                var sidebox = document.getElementsByClassName('sidebox_mode_comments');
                var rightBoxArea = document.getElementsByClassName('rightBoxArea');
                var max_mode = document.getElementsByClassName('max_mode_comments');
                var fullwidth = this.et2.getDOMWidgetById('fullwidth');
                var leftBoxArea = document.getElementsByClassName('leftBoxArea');
                if (fullwidth.getDOMNode().classList.contains('glyphicon-fullscreen')) {
                    fullwidth.getDOMNode().classList.replace('glyphicon-fullscreen', 'glyphicon-resize-small');
                    max_mode[0].append(rightBoxArea[0]);
                    leftBoxArea[0].setAttribute('colspan', '2');
                    videobar.resize(0);
                }
                else {
                    fullwidth.getDOMNode().classList.replace('glyphicon-resize-small', 'glyphicon-fullscreen');
                    sidebox[0].append(rightBoxArea[0]);
                    leftBoxArea[0].removeAttribute('colspan');
                    videobar.resize(0);
                }
        }
    };
    smallpartApp.prototype.student_playVideo = function (_pause) {
        var videobar = this.et2.getWidgetById('video');
        var $play = jQuery(this.et2.getWidgetById('play').getDOMNode());
        var self = this;
        this._student_setCommentArea(false);
        if ($play.hasClass('glyphicon-pause') || _pause) {
            videobar.pause_video();
            $play.removeClass('glyphicon-pause glyphicon-repeat');
        }
        else {
            this.start_watching();
            videobar.set_marking_enabled(false);
            videobar.play_video(function () {
                $play.removeClass('glyphicon-pause');
                if (!(videobar.getArrayMgr('content').getEntry('video')['video_test_options'] & et2_widget_videobar_1.et2_smallpart_videobar.video_test_option_not_seekable)) {
                    $play.addClass('glyphicon-repeat');
                }
                // record video watched
                self.record_watched();
            }, function (_id) {
                var commentsGrid = jQuery(self.et2.getWidgetById('comments').getDOMNode());
                var scrolledComment = commentsGrid.find('tr.commentID' + _id);
                if (scrolledComment[0].className.indexOf('hideme') < 0) {
                    commentsGrid.find('tr.row.commentBox').removeClass('highlight');
                    scrolledComment.addClass('highlight');
                    commentsGrid[0].scrollTop = scrolledComment[0].offsetTop;
                }
            });
            $play.removeClass('glyphicon-repeat');
            $play.addClass('glyphicon-pause');
        }
    };
    smallpartApp.prototype.student_dateFilter = function (_subWidget, _widget) {
        var value = _widget.getValue();
        if (_subWidget.id === 'comment_date_filter[from]' || _subWidget.id === 'comment_date_filter[to]') {
            this._student_dateFilterSearch();
        }
    };
    smallpartApp.prototype._student_dateFilterSearch = function () {
        var rows = jQuery('tr:not(.th)', this.et2.getWidgetById('comments').getDOMNode());
        var ids = [];
        var comments = this.et2.getArrayMgr('content').getEntry('comments');
        var date = this.et2.getDOMWidgetById('comment_date_filter').getValue();
        var from = new Date(date.from);
        var to = new Date(date.to);
        rows.each(function () {
            var id = this.classList.value.match(/commentID.*[0-9]/)[0].replace('commentID', '');
            var comment = comments.filter(function (_item) { return _item.comment_id == id; });
            if (comment && comment.length > 0) {
                var date_updated = new Date(comment[0].comment_updated.date);
                if ((from <= date_updated && to >= date_updated)
                    || (date.from && !date.to && from <= date_updated)
                    || (date.to && !date.from && to >= date_updated))
                    ids.push(id);
            }
        });
        this._student_commentsFiltering('date', ids.length == 0 && (date.to || date.from) ? ['ALL'] : ids);
    };
    smallpartApp.prototype.student_filter_tools_actions = function (_action, _selected) {
        switch (_action.id) {
            case 'mouseover':
                this.student_onmouseoverFilter(_action.checked);
                break;
            case 'download':
                this.et2.getInstanceManager().postSubmit('download');
                break;
            case 'pauseaftersubmit':
                break;
            case 'searchall':
                if (_action.checked) {
                    this.et2.getDOMWidgetById('comment_search_filter').getDOMNode().classList.add('searchall');
                }
                else {
                    this.et2.getDOMWidgetById('comment_search_filter').getDOMNode().classList.remove('searchall');
                }
                break;
            case 'date':
                var date = this.et2.getDOMWidgetById('comment_date_filter');
                date.set_disabled(!_action.checked);
                if (!_action.checked)
                    date.set_value({ from: 'null', to: 'null' });
                break;
        }
    };
    smallpartApp.prototype.student_top_tools_actions = function (_action, _selected) {
        var video_id = this.et2.getValueById('videos');
        var content = this.et2.getArrayMgr('content');
        switch (_action.id) {
            case 'course':
                egw.open(this.et2.getValueById('courses'), 'smallpart', 'edit');
                break;
            case 'question':
                if (video_id)
                    egw.open_link(egw.link('/index.php', 'menuaction=smallpart.EGroupware\\SmallParT\\Questions.index&video_id=' + video_id + '&ajax=true&cd=popup'));
                break;
            case 'score':
                if (video_id)
                    egw.open_link(egw.link('/index.php', 'menuaction=smallpart.EGroupware\\SmallParT\\Questions.scores&video_id=' + video_id + '&ajax=true&cd=popup'));
                break;
            case 'note':
                if (video_id) {
                    var iframe_1 = this.et2.getDOMWidgetById('note');
                    egw.json('EGroupware\\smallpart\\Student\\Ui::ajax_createNote', [content.getEntry('courses'), video_id], function (_data) {
                        if (_data.path) {
                            iframe_1.set_value(egw.link('/index.php', {
                                'menuaction': 'collabora.EGroupware\\collabora\\Ui.editor',
                                'path': _data.path,
                                'cd': 'no' // needed to not reload framework in sharing
                            }));
                            document.getElementsByClassName('note_container')[0].style.display = 'block';
                        }
                        egw.message(_data.message);
                    }).sendRequest(true);
                }
        }
    };
    smallpartApp.prototype.student_addNote = function () {
        this.student_top_tools_actions({ id: 'note' }, null);
    };
    /**
     * Add new comment / edit button callback
     */
    smallpartApp.prototype.student_addComment = function () {
        var comment = this.et2.getWidgetById('comment');
        var videobar = this.et2.getWidgetById('video');
        var self = this;
        this.student_playVideo(true);
        smallpartApp.playControllWidgets.forEach(function (w) {
            self.et2.getWidgetById(w).set_disabled(true);
        });
        this._student_setCommentArea(true);
        videobar.set_marking_enabled(true, function () {
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
        comment.set_value({ content: this.edited });
        comment.getWidgetById('deleteComment').set_disabled(true);
        this._student_controlCommentAreaButtons(true);
    };
    /**
     * Cancel edit and continue button callback
     */
    smallpartApp.prototype.student_cancelAndContinue = function () {
        var _this = this;
        var videobar = this.et2.getWidgetById('video');
        var filter_toolbar = this.et2.getDOMWidgetById('filter-toolbar');
        videobar.removeMarks();
        this.student_playVideo(filter_toolbar._actionManager.getActionById('pauseaftersubmit').checked);
        delete this.edited;
        smallpartApp.playControllWidgets.forEach(function (w) {
            _this.et2.getWidgetById(w).set_disabled(false);
        });
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
        et2_widget_dialog_1.et2_dialog.show_dialog(function (_button) {
            var _a;
            if (_button === et2_widget_dialog_1.et2_dialog.YES_BUTTON) {
                self.egw.json('smallpart.\\EGroupware\\SmallParT\\Student\\Ui.ajax_deleteComment', [
                    self.et2.getInstanceManager().etemplate_exec_id,
                    comment_id,
                    self.student_getFilter()
                ]).sendRequest();
                // do we need to clean up the edit-area
                if (comment_id == ((_a = self.edited) === null || _a === void 0 ? void 0 : _a.comment_id))
                    self.student_cancelAndContinue();
            }
        }, this.egw.lang('Delete this comment?'), this.egw.lang('Delete'), null, et2_widget_dialog_1.et2_dialog.BUTTONS_YES_NO);
    };
    /**
     * Get current active filter
     */
    smallpartApp.prototype.student_getFilter = function () {
        var _a, _b, _c, _f;
        return {
            course_id: ((_a = this.et2.getWidgetById('courses')) === null || _a === void 0 ? void 0 : _a.get_value()) || ((_b = this.filter) === null || _b === void 0 ? void 0 : _b.course_id),
            video_id: ((_c = this.et2.getWidgetById('videos')) === null || _c === void 0 ? void 0 : _c.get_value()) || ((_f = this.filter) === null || _f === void 0 ? void 0 : _f.video_id),
        };
    };
    /**
     * apply group filter
     * @param _node
     * @param _widget
     */
    smallpartApp.prototype.student_filterGroup = function (_node, _widget) {
        var rows = jQuery('tr:not(.th)', this.et2.getWidgetById('comments').getDOMNode());
        var ids = [];
        var accounts = this.et2.getArrayMgr('sel_options').getEntry('account_id');
        var comments = this.et2.getArrayMgr('content').getEntry('comments');
        var group = _widget.get_value();
        rows.each(function () {
            var id = this.classList.value.match(/commentID.*[0-9]/)[0].replace('commentID', '');
            var found = [];
            var comment = comments.filter(function (_item) { return _item.comment_id == id; });
            if (comment && comment.length > 0) {
                var account_id_1 = comment[0]['account_id'];
                found = accounts.filter(function (_item) {
                    if (_item.value == account_id_1) {
                        switch (group) {
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
            if ((found === null || found === void 0 ? void 0 : found.length) > 0 || group == '')
                ids.push(id);
        });
        if ((group == 'unsub' || group == 'sub') && ids.length == 0)
            ids = ['ALL'];
        this._student_commentsFiltering('group', ids);
    };
    /**
     * Apply (changed) comment filter
     *
     * Filter is applied by hiding filtered rows client-side
     */
    smallpartApp.prototype.student_filterComments = function () {
        var color = this.et2.getWidgetById('comment_color_filter').get_value();
        var rows = jQuery('tr', this.et2.getWidgetById('comments').getDOMNode()).filter('.commentColor' + color);
        var ids = [];
        rows.each(function () {
            ids.push(this.classList.value.match(/commentID.*[0-9]/)[0].replace('commentID', ''));
        });
        this._student_commentsFiltering('color', (ids.length ? ids : (color != "" ? ['ALL'] : [])));
    };
    smallpartApp.prototype.student_clearFilter = function () {
        this.et2.getWidgetById('comment_color_filter').set_value("");
        this.et2.getWidgetById('comment_search_filter').set_value("");
        this.et2.getWidgetById('activeParticipantsFilter').set_value("");
        this.et2.getWidgetById('group').set_value("");
        this.et2.getDOMWidgetById('comment_date_filter').set_value({ from: 'null', to: 'null' });
        for (var f in this.filters) {
            this._student_commentsFiltering(f, []);
        }
    };
    smallpartApp.prototype.student_searchFilter = function (_widget) {
        var query = _widget.get_value();
        var rows = jQuery('tr:not(.th)', this.et2.getWidgetById('comments').getDOMNode());
        var ids = [];
        var filter_toolbar = this.et2.getDOMWidgetById('filter-toolbar');
        rows.each(function () {
            jQuery.extend(jQuery.expr[':'].containsCaseInsensitive = function (a, i, m) {
                var t = (a.textContent || a.innerText || "");
                var reg = new RegExp(m[3], 'i');
                return reg.test(t) && (!filter_toolbar._actionManager.getActionById('pauseaftersubmit').checked ? a.classList.contains('et2_smallpart_comment') : true);
            });
            if (query != '' && jQuery(this).find('*:containsCaseInsensitive("' + query + '")').length >= 1) {
                ids.push(this.classList.value.match(/commentID.*[0-9]/)[0].replace('commentID', ''));
            }
        });
        this._student_commentsFiltering('search', ids.length == 0 && query != '' ? ['ALL'] : ids);
    };
    smallpartApp.prototype.student_onmouseoverFilter = function (_state) {
        var self = this;
        var videobar = this.et2.getWidgetById('video');
        var comments = jQuery(this.et2.getWidgetById('comments').getDOMNode());
        if (_state) {
            comments.on('mouseenter', function () {
                var _a;
                if (jQuery(self.et2.getWidgetById('play').getDOMNode()).hasClass('glyphicon-pause')
                    && (!self.edited || ((_a = self.edited) === null || _a === void 0 ? void 0 : _a.action) != 'edit'))
                    videobar.pause_video();
            })
                .on('mouseleave', function () {
                var _a;
                if (jQuery(self.et2.getWidgetById('play').getDOMNode()).hasClass('glyphicon-pause')
                    && (!self.edited || ((_a = self.edited) === null || _a === void 0 ? void 0 : _a.action) != 'edit'))
                    videobar.play();
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
        var _this = this;
        // update our internal data
        this.comments = _data.content;
        // the first index (an empty array) in comments is reserved for action grid therefore ignore it.
        this.et2.getWidgetById('smallpart.student.comments_list').getParent().set_disabled(this.comments.length <= 1);
        // update grid
        var comments = this.et2.getWidgetById('comments');
        comments.set_value(_data);
        // update slider-tags
        var videobar = this.et2.getWidgetById('video');
        videobar.set_slider_tags(this.comments);
        // re-apply the filter, if not "all"
        var applyFilter = false;
        ['comment_color_filter', 'comment_search_filter', 'group', 'comment_date_filter'].forEach(function (_id) {
            if (_this.et2.getWidgetById(_id).get_value())
                applyFilter = true;
        });
        if (applyFilter)
            this.student_filterComments();
        this._student_setFilterParticipantsOptions();
    };
    smallpartApp.prototype.student_revertMarks = function (_event, _widget) {
        var videobar = this.et2.getWidgetById('video');
        videobar.setMarks(this.edited.comment_marked);
        this._student_controlCommentAreaButtons(true);
    };
    smallpartApp.prototype.student_hideBackground = function (_node, _widget) {
        var videobar = this.et2.getWidgetById('video');
        videobar.setMarkingMask(_widget.getValue() != "");
    };
    smallpartApp.prototype.student_hideMarkedArea = function (_node, _widget) {
        var videobar = this.et2.getWidgetById('video');
        var is_readonly = _widget.getValue() != "";
        videobar.setMarksState(is_readonly);
        var ids = ['markedColorRadio', 'revertMarks', 'deleteMarks', 'backgroundColorTransparency'];
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
        // record, in case we're playing
        this.record_watched(_video.previousTime());
        if (!_video.paused())
            this.start_watching();
        this.et2.getWidgetById('play').getDOMNode().classList.remove('glyphicon-repeat');
    };
    smallpartApp.prototype.student_comments_column_switch = function (_node, _widget) {
        var comments = this.et2.getDOMWidgetById('comments');
        if (_widget.getValue()) {
            comments.set_class('hide_column');
            this.egw.set_preference('smallpart', 'comments_column_state', 0);
        }
        else {
            this.egw.set_preference('smallpart', 'comments_column_state', 1);
            comments.set_class('');
            comments.getDOMNode().classList.remove('hide_column');
        }
    };
    smallpartApp.prototype._student_controlCommentAreaButtons = function (_state) {
        var _a;
        var readonlys = ['revertMarks', 'deleteMarks'];
        for (var i in readonlys) {
            var widget = this.et2.getWidgetById('comment').getWidgetById(readonlys[i]);
            if (readonlys[i] == 'deleteMarks') {
                _state = _state ? (_a = !this.et2.getWidgetById('video').getMarks().length) !== null && _a !== void 0 ? _a : false : _state;
            }
            else if (this.edited.comment_marked) {
                //
            }
            if (widget === null || widget === void 0 ? void 0 : widget.set_readonly)
                widget.set_readonly(_state);
        }
    };
    /**
     * filters comments
     *
     * @param _filter filter name
     * @param _value array comment ids to be filtered, given array of['ALL']
     * makes all rows hiden and empty array reset the filter.
     */
    smallpartApp.prototype._student_commentsFiltering = function (_filter, _value) {
        var _a, _b, _c;
        var rows = jQuery('tr:not(.th)', this.et2.getWidgetById('comments').getDOMNode());
        var tags = jQuery('.videobar_slider span.commentOnSlider');
        var self = this;
        if (_filter && _value) {
            this.filters[_filter] = _value;
        }
        else {
            delete (this.filters[_filter]);
        }
        for (var f in this.filters) {
            for (var c in this.comments) {
                if (!this.comments[c] || this.comments[c].length == 0)
                    continue;
                if (typeof this.comments[c].filtered == 'undefined')
                    this.comments[c].filtered = [];
                if (((_a = this.filters[f]) === null || _a === void 0 ? void 0 : _a.length) > 0) {
                    if (this.comments[c].filtered.indexOf(f) != -1)
                        this.comments[c].filtered.splice(this.comments[c].filtered.indexOf(f), 1);
                    if (this.filters[f].indexOf(this.comments[c].comment_id) == -1 || this.filters[f][0] === "ALL") {
                        this.comments[c].filtered.push(f);
                    }
                }
                else {
                    if (this.comments[c].filtered.indexOf(f) != -1)
                        this.comments[c].filtered.splice(this.comments[c].filtered.indexOf(f), 1);
                }
            }
        }
        var _loop_1 = function (i) {
            if (!this_1.comments[i] || this_1.comments[i].length == 0)
                return "continue";
            if (this_1.comments[i].filtered.length > 0) {
                rows.filter('.commentID' + ((_b = this_1.comments[i]) === null || _b === void 0 ? void 0 : _b.comment_id)).addClass('hideme');
                tags.filter(function () { var _a, _b; return this.dataset.id == ((_b = (_a = self.comments[i]) === null || _a === void 0 ? void 0 : _a.comment_id) === null || _b === void 0 ? void 0 : _b.toString()); }).addClass('hideme');
            }
            else {
                rows.filter('.commentID' + ((_c = this_1.comments[i]) === null || _c === void 0 ? void 0 : _c.comment_id)).removeClass('hideme');
                tags.filter(function () { var _a, _b; return this.dataset.id == ((_b = (_a = self.comments[i]) === null || _a === void 0 ? void 0 : _a.comment_id) === null || _b === void 0 ? void 0 : _b.toString()); }).removeClass('hideme');
            }
        };
        var this_1 = this;
        for (var i in this.comments) {
            _loop_1(i);
        }
    };
    smallpartApp.prototype.student_filterParticipants = function (_e, _widget) {
        var values = _widget.getValue();
        var data = [], value = [];
        for (var i in values) {
            value = values[i].split(',');
            if (value)
                data = data.concat(value.filter(function (x) { return data.every(function (y) { return y !== x; }); }));
        }
        this._student_commentsFiltering('participants', data);
    };
    smallpartApp.prototype._student_fetchAccountData = function (_id, _stack, _options, _resolved) {
        var self = this;
        egw.accountData(parseInt(_id), 'account_fullname', null, function (_d) {
            if (Object.keys(_d).length > 0) {
                var id = parseInt(Object.keys(_d)[0]);
                _options[id].label = _d[id];
            }
            egw.accountData(_id, 'account_firstname', null, function (_n) {
                if (Object.keys(_n).length > 0) {
                    var id = parseInt(Object.keys(_n)[0]);
                    _options[id].name = _n[id] + '[' + id + ']';
                    var newId = _stack.pop();
                    if (newId) {
                        self._student_fetchAccountData(newId, _stack, _options, _resolved);
                    }
                    else {
                        _resolved(_options);
                    }
                }
            }, egw(window));
        }, egw(window));
    };
    smallpartApp.prototype._student_setFilterParticipantsOptions = function () {
        var _this = this;
        var _a, _b;
        var activeParticipants = this.et2.getWidgetById('activeParticipantsFilter');
        var passiveParticipantsList = this.et2.getWidgetById('passiveParticipantsList');
        var options = {};
        var participants = this.et2.getArrayMgr('sel_options').getEntry('account_id');
        var commentHeaderMessage = this.et2.getWidgetById('commentHeaderMessage');
        var staff = this.et2.getArrayMgr('sel_options').getEntry('staff');
        var roles = {};
        staff.forEach(function (staff) { return roles[staff.value] = staff.label; });
        var _foundInComments = function (_id) {
            for (var k in _this.comments) {
                if (_this.comments[k]['account_id'] == _id)
                    return true;
            }
        };
        var _countComments = function (_id) {
            var c = 0;
            for (var i in _this.comments) {
                if (_this.comments[i]['account_id'] == _id)
                    c++;
            }
            return c;
        };
        var _getNames = function (_account_id) {
            for (var p in participants) {
                if (participants[p].value == _account_id) {
                    return {
                        label: participants[p].label,
                        name: roles[participants[p].role] || participants[p].title,
                        icon: egw.link('/api/avatar.php', { account_id: _account_id })
                    };
                }
            }
            return {
                name: '',
                label: '#' + _account_id,
                icon: egw.link('/api/avatar.php', { account_id: _account_id })
            };
        };
        if (activeParticipants) {
            for (var i in this.comments) {
                if (!this.comments[i])
                    continue;
                var comment = this.comments[i];
                if (typeof options[comment.account_id] === 'undefined') {
                    options[comment.account_id] = _getNames(comment.account_id);
                }
                options[comment.account_id] = jQuery.extend(options[comment.account_id], {
                    value: options[comment.account_id] && typeof options[comment.account_id]['value'] != 'undefined' ?
                        (options[comment.account_id]['value'].indexOf(comment.comment_id)
                            ? options[comment.account_id]['value'].concat(comment.comment_id) : options[comment.account_id]['value'])
                        : [comment.comment_id],
                    comments: _countComments(comment.account_id),
                });
                if (comment.comment_added) {
                    for (var j in comment.comment_added) {
                        var comment_added = comment.comment_added[j];
                        if (Number.isInteger(comment_added)) {
                            if (typeof options[comment_added] == 'undefined'
                                && !_foundInComments(comment_added)) {
                                options[comment_added] = _getNames(comment_added);
                                options[comment_added].value = [comment.comment_id];
                            }
                            else if (typeof options[comment_added] == 'undefined') {
                                options[comment_added] = _getNames(comment_added);
                                options[comment_added].value = [];
                            }
                            options[comment_added]['retweets'] =
                                options[comment_added]['retweets']
                                    ? options[comment_added]['retweets'] + 1 : 1;
                            options[comment_added]['value'] = options[comment_added]['value'].indexOf(comment.comment_id) == -1
                                ? options[comment_added]['value'].concat(comment.comment_id) : options[comment_added]['value'];
                        }
                    }
                }
            }
            for (var i in options) {
                if (((_b = (_a = options[i]) === null || _a === void 0 ? void 0 : _a.value) === null || _b === void 0 ? void 0 : _b.length) > 0) {
                    options[i].value = options[i].value.join(',');
                }
            }
            // set options after all accounts info are fetched
            activeParticipants.set_select_options(options);
            var passiveParticipants = [{}];
            for (var i in participants) {
                if (!options[participants[i].value])
                    passiveParticipants.push({ account_id: participants[i].value });
            }
            passiveParticipantsList.set_value({ content: passiveParticipants });
            commentHeaderMessage.set_value(this.egw.lang("%1/%2 participants already answered", Object.keys(options).length, Object.keys(options).length + passiveParticipants.length - 1));
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
        et2_widget_dialog_1.et2_dialog.show_prompt(function (_button_id, _password) {
            if (_button_id == et2_widget_dialog_1.et2_dialog.OK_BUTTON) {
                self.courseAction(_action, _senders, _password);
            }
        }, this.egw.lang("Please enter the course password"), this.egw.lang("Subscribe to course"), {}, et2_widget_dialog_1.et2_dialog.BUTTONS_OK_CANCEL, et2_widget_dialog_1.et2_dialog.QUESTION_MESSAGE);
    };
    /**
     * course- or video-selection changed
     *
     * @param _node
     * @param _widget
     */
    smallpartApp.prototype.courseSelection = function (_node, _widget) {
        this.record_watched();
        // remove excessive dialogs left over from previous video selection
        this.et2.getDOMWidgetById('videooverlay').questionDialogs.forEach(function (_o) { _o.dialog.destroy(); });
        if (_widget.id === 'courses' && _widget.getValue() === 'manage') {
            this.egw.open(null, 'smallpart', 'list', '', '_self');
        }
        else {
            // sent video_id via hidden text field, in case new video was added on client-side via push (will be ignored by eT2 validation)
            if (_widget.id === 'videos') {
                _widget.getRoot().setValueById('video2', _widget.getValue());
            }
            // submit to server-side
            _widget.getInstanceManager().submit(null, false, true);
        }
        return false;
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
        switch (_action.id) {
            case 'open':
                this.egw.open(ids[0], 'smallpart', 'view', { cd: "no" }, '_self');
                break;
            default:
                this.egw.json('smallpart.\\EGroupware\\SmallParT\\Courses.ajax_action', [_action.id, ids, false, _password])
                    .sendRequest();
                break;
        }
    };
    /**
     * Distribute course-groups
     *
     * @param _node
     * @param _widget
     */
    smallpartApp.prototype.changeCourseGroups = function (_node, _widget) {
        var _a, _b, _c;
        var groups = (_a = _widget.getParent().getWidgetById('course_groups')) === null || _a === void 0 ? void 0 : _a.get_value();
        var mode = (_b = _widget.getParent().getWidgetById('groups_mode')) === null || _b === void 0 ? void 0 : _b.get_value();
        if (mode && !groups) {
            et2_widget_dialog_1.et2_dialog.alert(this.egw.lang('You need to set a number or size first!'));
        }
        (_c = _widget.getRoot().getWidgetById('tabs')) === null || _c === void 0 ? void 0 : _c.setActiveTab(1);
        // unfortunately we can not getWidgetById widgets in an auto-repeated grid
        var content = _widget.getArrayMgr('content').getEntry('participants');
        var values = _widget.getInstanceManager().getValues(_widget.getRoot().getWidgetById('participants')).participants;
        for (var row = 1, student = 0; typeof content[row] === 'object' && content[row] !== null; ++row) {
            content[row] = Object.assign(content[row], values[row] || {});
            var participant = content[row];
            if (participant && participant.participant_unsubscribed !== null) {
                // do not modify unsubscribed participants
            }
            else if (participant && !parseInt(participant.participant_role) && mode) {
                if (mode.substr(0, 6) === 'number') {
                    content[row].participant_group = 1 + (student % groups);
                }
                else {
                    content[row].participant_group = 1 + (student / groups | 0);
                }
                ++student;
            }
            else {
                content[row].participant_group = '';
            }
        }
        _widget.getRoot().getWidgetById('participants').set_value({ content: content });
        // need to run it again, after above set_value, recreating all the widgets
        this.disableGroupByRole();
    };
    /**
     * Disable group selection if a staff-role is selected
     *
     * @param _node
     * @param _widget
     */
    smallpartApp.prototype.changeRole = function (_node, _widget) {
        var grid = _widget.getParent();
        var group = grid.getWidgetById(_widget.id.replace('role', 'group'));
        var role = _widget.get_value();
        if (group) {
            if (role !== '0')
                group.set_value('');
            group.set_disabled(role !== '0');
        }
    };
    /**
     * Disable all group inputs, if a role is set
     */
    smallpartApp.prototype.disableGroupByRole = function () {
        var grid = this.et2.getWidgetById('participants');
        for (var role = void 0, row = 1; role = grid.getWidgetById('' + row + '[participant_role]'); ++row) {
            if (role.get_value() !== '0') {
                this.changeRole(undefined, role);
            }
        }
    };
    /**
     * Set nickname for user
     */
    smallpartApp.prototype.changeNickname = function () {
        var _this = this;
        var course_id = this.student_getFilter().course_id;
        if (!course_id)
            return;
        var participants = this.et2.getArrayMgr('sel_options').getEntry('account_id');
        var user = participants.filter(function (participant) { return participant.value == _this.user; }).pop();
        et2_widget_dialog_1.et2_dialog.show_prompt(function (button, nickname) {
            var _this = this;
            if (button === et2_widget_dialog_1.et2_dialog.OK_BUTTON && (nickname = nickname.trim()) && nickname !== user.label) {
                var nickname_lc_1 = nickname.toLowerCase();
                if (nickname.match(/\[\d+\]$]/) || participants.filter(function (participant) {
                    return participant.label.toLowerCase() === nickname_lc_1 && participant.value != _this.user;
                }).length) {
                    this.egw.message(this.egw.lang('Nickname is already been taken, choose an other one'));
                    return this.changeNickname();
                }
                this.egw.request('EGroupware\\SmallPART\\Student\\Ui::ajax_changeNickname', [course_id, nickname]);
            }
        }.bind(this), this.egw.lang('How do you want to be called?'), this.egw.lang('Change nickname'), user.label, et2_widget_dialog_1.et2_dialog.BUTTONS_OK_CANCEL);
    };
    /**
     * Subscribe or open a course (depending on already subscribed)
     *
     * @param _id
     * @param _subscribed
     */
    smallpartApp.prototype.openCourse = function (_id, _subscribed) {
        if (!_subscribed) {
            this.subscribe({ id: 'subscribe' }, [{ id: 'smallpart::' + _id }]);
        }
        else {
            this.egw.open(_id, 'smallpart', 'view', '', '_self');
        }
    };
    /**
     * Clickhandler to copy given text or widget content to clipboard
     * @param _widget
     * @param _text default widget content
     */
    smallpartApp.prototype.copyClipboard = function (_widget, _text) {
        var _a, _b;
        var value = _text || (typeof _widget.get_value === 'function' ? _widget.get_value() : _widget.options.value);
        if (((_a = _widget.getDOMNode()) === null || _a === void 0 ? void 0 : _a.nodeName) === 'INPUT') {
            jQuery(_widget.getDOMNode()).val(value).select();
            document.execCommand('copy');
        }
        else {
            var input = jQuery(((_b = _widget.getDOMNode()) === null || _b === void 0 ? void 0 : _b.nodeName) === 'INPUT' ? _widget.getDOMNode() : document.createElement('input'))
                .appendTo(_widget.getDOMNode())
                .val(value)
                .select();
            document.execCommand('copy');
            input.remove();
        }
        this.egw.message(this.egw.lang("Copied '%1' to clipboard", value), 'success');
    };
    /**
     * onclick callback used in course.xet
     * @param _event
     * @param _widget
     */
    smallpartApp.prototype.course_addVideo_btn = function (_event, _widget) {
        var url = this.et2.getWidgetById('video_url');
        var file = this.et2.getWidgetById('upload');
        var name = '';
        var videos = this.et2.getArrayMgr('content').getEntry('videos');
        var warning = false;
        if (url.getValue() != '') {
            var parts = url.getValue().split('/');
            name = parts[parts.length - 1];
        }
        else if (file.getValue()) {
            name = Object.values(file.getValue())[0]['name'];
        }
        for (var i in videos) {
            if (videos[i] && videos[i]['video_name'] == name) {
                warning = true;
            }
        }
        if (warning) {
            et2_widget_dialog_1.et2_dialog.confirm(_widget, "There's already a video with the same name, would you still like to upload it?", "Duplicate name", false);
        }
        else {
            _widget.getInstanceManager().submit();
        }
    };
    /**
     * Called when student started watching a video
     */
    smallpartApp.prototype.start_watching = function () {
        if (!(this.course_options & 1))
            return; // not recording watched videos for this course
        var videobar = this.et2.getWidgetById('video');
        if (typeof this.watching === 'undefined' && videobar) {
            this.watching = this.student_getFilter();
            this.watching.starttime = new Date();
            this.watching.position = videobar.currentTime();
            this.watching.paused = 0;
        }
        else {
            this.watching.paused++;
        }
    };
    /**
     * Called when student finished watching a video
     *
     * @param _time optional video-time, default videobar.currentTime()
     */
    smallpartApp.prototype.record_watched = function (_time) {
        var _a;
        if (!(this.course_options & 1))
            return; // not recording watched videos for this course
        var videobar = (_a = this.et2) === null || _a === void 0 ? void 0 : _a.getWidgetById('video');
        if (typeof this.watching === 'undefined') // video not playing, nothing to record
         {
            return;
        }
        this.watching.endtime = new Date();
        this.watching.duration = (_time || (videobar === null || videobar === void 0 ? void 0 : videobar.currentTime())) - this.watching.position;
        //console.log(this.watching);
        this.egw.json('smallpart.EGroupware\\SmallParT\\Student\\Ui.ajax_recordWatched', [this.watching]).sendRequest('keepalive');
        // reset recording
        delete this.watching;
    };
    /**
     * Record video & position to restore it
     */
    smallpartApp.prototype.set_video_position = function () {
        var _a;
        var videobar = (_a = this.et2) === null || _a === void 0 ? void 0 : _a.getWidgetById('video');
        var data = this.student_getFilter();
        data.position = videobar === null || videobar === void 0 ? void 0 : videobar.currentTime();
        if (data.video_id && typeof data.position !== 'undefined') {
            //console.log('set_video_position', data);
            this.egw.json('smallpart.EGroupware\\SmallParT\\Student\\Ui.ajax_setLastVideo', [data]).sendRequest('keepalive');
        }
    };
    /**
     * Confirm import should overwrite whole course or just add videos
     */
    smallpartApp.prototype.confirmOverwrite = function (_ev, _widget, _node) {
        var widget = _widget;
        // if we have no course_id / add used, no need to confirm overwrite
        if (!widget.getArrayMgr('content').getEntry('course_id')) {
            widget.getInstanceManager().submit(widget, true, true);
            return;
        }
        et2_core_widget_1.et2_createWidget("dialog", {
            callback: function (_button) {
                if (_button !== "cancel") {
                    widget.getRoot().setValueById('import_overwrite', _button === "overwrite");
                    widget.getInstanceManager().submit(widget, true, true); // last true = no validation
                }
            },
            buttons: [
                { text: this.egw.lang("Add videos"), id: "add", class: "ui-priority-primary", default: true },
                { text: this.egw.lang("Overwrite course"), id: "overwrite", image: "delete" },
                { text: this.egw.lang("Cancel"), id: "cancel", class: "ui-state-error" },
            ],
            title: this.egw.lang('Overwrite exiting course?'),
            message: this.egw.lang('Just add videos, or overwrite whole course?'),
            icon: et2_widget_dialog_1.et2_dialog.QUESTION_MESSAGE
        });
    };
    /**
     * Number of checked siblings
     */
    smallpartApp.prototype.childrenChecked = function (_widget) {
        var answered = 0;
        _widget.iterateOver(function (_checkbox) {
            if (_checkbox.get_value())
                ++answered;
        }, this, et2_widget_checkbox_1.et2_checkbox);
        return answered;
    };
    /**
     * OnChange for multiple choice checkboxes to implement max_answers / max. number of checked answers
     */
    smallpartApp.prototype.checkMaxAnswers = function (_ev, _widget, _node) {
        var max_answers = _widget.getRoot().getArrayMgr('content').getEntry('max_answers');
        try {
            max_answers = _widget.getRoot().getValueById('max_answers');
        }
        catch (e) { }
        if (max_answers) {
            var checked_1 = this.childrenChecked(_widget.getParent());
            // for dialog method is not called on load, therefore it can happen that already max_answers are checked
            if (checked_1 > max_answers) {
                _widget.set_value(false);
                checked_1--;
            }
            _widget.getParent().iterateOver(function (_checkbox) {
                if (!_checkbox.get_value()) {
                    _checkbox.set_readonly(checked_1 >= max_answers);
                }
            }, this, et2_widget_checkbox_1.et2_checkbox);
        }
    };
    /**
     * Check min. number of multiplechoice answers are given, before allowing to submit
     */
    smallpartApp.prototype.checkMinAnswers = function (_ev, _widget, _node) {
        var contentMgr = _widget.getRoot().getArrayMgr('content');
        var min_answers = contentMgr.getEntry('min_answers');
        if (this.checkMinAnswer_error) {
            this.checkMinAnswer_error.close();
            this.checkMinAnswer_error = null;
        }
        if (min_answers && contentMgr.getEntry('overlay_type') === 'smallpart-question-multiplechoice') {
            var checked = this.childrenChecked(this.et2.getWidgetById('answers'));
            if (checked < min_answers) {
                this.checkMinAnswer_error = this.egw.message(this.egw.lang('A minimum of %1 answers need to be checked!', min_answers), 'error');
                return false;
            }
        }
        _widget.getInstanceManager().submit(_widget);
    };
    /**
     * Calculate blur-text for answer specific points of multiple choice questions
     *
     * For assessment-method score per answer, and no explicit points given, the max_points are equally divided on the answers.
     *
     * @param _ev
     * @param _widget
     * @param _node
     */
    smallpartApp.prototype.defaultPoints = function (_ev, _widget, _node) {
        var method;
        try {
            method = this.et2.getValueById('assessment_method');
        }
        catch (e) {
            return; // eg. text question
        }
        var max_score = parseFloat(this.et2.getValueById('max_score'));
        var question_type = this.et2.getValueById('overlay_type');
        if (method === 'all_correct' || question_type !== 'smallpart-question-multiplechoice') {
            jQuery('.scoreCol').hide();
        }
        else {
            var explicit_points = 0, explicit_set = 0, num_answers = 0;
            for (var i = 1, w = null; (w = this.et2.getWidgetById('' + i + '[score]')); ++i) {
                // ignore empty questions
                if (!this.et2.getValueById('' + i + '[answer]'))
                    continue;
                var val = parseFloat(w.getValue());
                if (!isNaN(val)) {
                    ++explicit_set;
                    if (val > 0)
                        explicit_points += val;
                }
                ++num_answers;
            }
            var default_points = ((max_score - explicit_points) / (num_answers - explicit_set)).toFixed(2);
            for (var i = 1, w = null; (w = this.et2.getWidgetById('' + i + '[score]')); ++i) {
                // ignore empty questions
                if (!this.et2.getValueById('' + i + '[answer]'))
                    continue;
                w.set_blur(default_points);
            }
            jQuery('.scoreCol').show();
        }
    };
    /**
     * Pause test by submitting it to server incl. current video position
     *
     * @param _ev
     * @param _widget
     * @param _node
     */
    smallpartApp.prototype.pauseTest = function (_ev, _widget, _node) {
        var videobar = this.et2.getWidgetById('video');
        var videotime = this.et2.getInputWidgetById('video_time');
        if (videotime)
            videotime.set_value(videobar.currentTime());
        _widget.getInstanceManager().submit(_widget);
    };
    /**
     * Link question start-time, duration and end-time
     *
     * @param _ev
     * @param _widget
     * @param _node
     */
    smallpartApp.prototype.questionTime = function (_ev, _widget, _node) {
        var start = this.et2.getInputWidgetById('overlay_start');
        var duration = this.et2.getInputWidgetById('overlay_duration');
        var end = this.et2.getInputWidgetById('overlay_end');
        var apply = this.et2.getDOMWidgetById('button[apply]');
        var save = this.et2.getDOMWidgetById('button[save]');
        if (!start || !duration || !end)
            return; // eg. not editable
        if (_widget === end) {
            duration.set_value(parseInt(end.get_value()) - parseInt(start.get_value()));
        }
        else {
            var video = this.et2.getWidgetById("video_data_helper");
            if (video && video.duration() < parseInt(start.get_value()) + parseInt(duration.get_value())) {
                end.set_value(Math.floor(video.duration()));
                end.set_validation_error(egw.lang('Lenght of question cannot exceed the lenght of video %1', end._convert_to_display(end.get_value()).value));
                save === null || save === void 0 ? void 0 : save.set_readonly(true);
                apply === null || apply === void 0 ? void 0 : apply.set_readonly(true);
            }
            else {
                end.set_validation_error(false);
                save === null || save === void 0 ? void 0 : save.set_readonly(false);
                apply === null || apply === void 0 ? void 0 : apply.set_readonly(false);
            }
            end.set_value(parseInt(start.get_value()) + parseInt(duration.get_value()));
        }
    };
    /**
     * Show individual questions and answers of an account_id eg. to assess them
     */
    smallpartApp.prototype.showQuestions = function (_action, _senders) {
        this.egw.open('', 'smallpart-overlay', 'list', {
            course_id: this.et2.getArrayMgr('content').getEntry('nm[col_filter][course_id]'),
            video_id: this.et2.getValueById('filter'),
            account_id: _senders[0].id.split('::')[1],
        }, '_self');
    };
    /**
     * Check if we edit a mark or mill-out questions and load the existing markings
     */
    smallpartApp.prototype.setMarkings = function () {
        var _this = this;
        var _a, _b, _c, _f;
        var videobar = (_f = (_c = (_b = (_a = window.opener) === null || _a === void 0 ? void 0 : _a.app) === null || _b === void 0 ? void 0 : _b.smallpart) === null || _c === void 0 ? void 0 : _c.et2) === null || _f === void 0 ? void 0 : _f.getWidgetById('video');
        var marks = this.et2.getWidgetById('marks');
        if (!videobar || !marks)
            return; // eg. called from the list or no mark or mill-out question
        var mark_values = JSON.parse(marks.getValue() || '[]');
        videobar.setMarks(mark_helpers_1.MarkArea.colorDisjunctiveAreas(mark_values, videobar.get_marking_colors()));
        videobar.set_marking_enabled(true, function (mark) { return console.log(mark); });
        videobar.set_marking_readonly(true);
        videobar.setMarkingMask(mark_values.length > 0);
        // store marks before saving in hidden var again
        ['button[save]', 'button[apply]'].forEach(function (name) {
            var button = _this.et2.getWidgetById(name);
            if (button) {
                button.onclick = function (e) {
                    marks.set_value(JSON.stringify(mark_helpers_1.MarkArea.markDisjunctiveAreas(videobar.getMarks(true), videobar.video.width() / videobar.video.height())));
                    return true;
                };
            }
        });
        // clear marks before unloading
        window.addEventListener("beforeunload", function () {
            videobar.setMarks([]);
            videobar.set_marking_readonly(true);
            videobar.setMarkingMask(false);
        });
    };
    /**
     * Mark the answer area of a question
     *
     * @param _ev
     * @param _widget
     * @param _node
     */
    smallpartApp.prototype.markAnswer = function (_ev, _widget, _node) {
        var _a, _b, _c, _f;
        var videobar = ((_f = (_c = (_b = (_a = window.opener) === null || _a === void 0 ? void 0 : _a.app) === null || _b === void 0 ? void 0 : _b.smallpart) === null || _c === void 0 ? void 0 : _c.et2) === null || _f === void 0 ? void 0 : _f.getWidgetById('video')) ||
            this.et2.getWidgetById('video');
        if (!videobar) {
            this.egw.message(this.egw.lang('You have to open the question from the video, to be able to mark answers!', 'error'));
            return;
        }
        videobar.set_marking_color(parseInt(_widget.options.set_value));
        videobar.set_marking_readonly(false);
        videobar.set_marking_enabled(true, function (mark) {
            var mark_values = mark_helpers_1.MarkArea.markDisjunctiveAreas(videobar.getMarks(true), videobar.video.width() / videobar.video.height());
            videobar.setMarks(mark_helpers_1.MarkArea.colorDisjunctiveAreas(mark_values, videobar.get_marking_colors()));
        });
        videobar.setMarksState(true);
        videobar.setMarkingMask(true);
        // mark current row as active and unmark all others
        var tr = jQuery(_widget.parentNode).closest('tr');
        tr.siblings().removeClass('markActiveRow');
        tr.addClass('markActiveRow');
    };
    /**
     * Close LTI platform iframe
     */
    smallpartApp.prototype.ltiClose = function () {
        (window.opener || window.parent).postMessage({ subject: 'org.imsglobal.lti.close' }, '*');
        return true;
    };
    /**
     * Show video in LTI content selection
     *
     * @param _node
     * @param _widget
     */
    smallpartApp.prototype.ltiVideoSelection = function (_node, _widget) {
        var video = this.et2.getWidgetById('video');
        var video_id = _widget.getValue();
        if (video_id) {
            var videos = this.et2.getArrayMgr('content').getEntry('videos');
            if (typeof videos[video_id] !== 'undefined') {
                video.set_src_type('video/' + videos[video_id].video_type);
                video.set_src(videos[video_id].video_src);
                video.set_disabled(false);
            }
        }
        else {
            video.set_disabled(true);
        }
    };
    smallpartApp.appname = 'smallpart';
    smallpartApp.default_color = 'ffffff'; // white = neutral
    smallpartApp.playControllWidgets = ['play_control_bar'];
    /**
     * Forbid students to comment
     */
    smallpartApp.COMMENTS_FORBIDDEN_BY_STUDENTS = 4;
    /**
     * Show everything withing the group plus staff
     */
    smallpartApp.COMMENTS_GROUP = 6;
    /**
     * Show comments within the group, but hide teachers
     */
    smallpartApp.COMMENTS_GROUP_HIDE_TEACHERS = 7;
    /**
     * Post Cognitive Load Measurement Type
     */
    smallpartApp.CLM_TYPE_POST = 'post';
    /**
     * Process Cognitive Load Measurement Type
     */
    smallpartApp.CLM_TYPE_PROCESS = 'process';
    /**
     * stop time type for Cognitive Load Measurement
     */
    smallpartApp.CLM_TYPE_STOP = 'stop';
    /**
     * stop time type for Cognitive Load Measurement
     */
    smallpartApp.CLM_TYPE_UNLOAD = 'unload';
    return smallpartApp;
}(egw_app_1.EgwApp));
app.classes.smallpart = smallpartApp;
//# sourceMappingURL=app.js.map