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
    /smallpart/js/et2_widget_videotime.js;
    /smallpart/js/et2_widget_comment.js;
    /smallpart/js/et2_widget_color_radiobox.js;
    /smallpart/js/et2_widget_filter_participants.js;
 */
var egw_app_1 = require("../../api/js/jsapi/egw_app");
var et2_widget_dialog_1 = require("../../api/js/etemplate/et2_widget_dialog");
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
        // call parent
        _super.prototype.et2_ready.call(this, _et2, _name);
        switch (true) {
            case (_name.match(/smallpart.student.index/) !== null):
                this.comments = this.et2.getArrayMgr('content').getEntry('comments');
                this._student_setCommentArea(false);
                this.filter = {
                    course_id: parseInt(this.et2.getArrayMgr('content').getEntry('courses')) || null,
                    video_id: parseInt(this.et2.getArrayMgr('content').getEntry('videos')) || null
                };
                this.course_options = parseInt(this.et2.getArrayMgr('content').getEntry('course_options')) || 0;
                this._student_setFilterParticipantsOptions();
                var self_1 = this;
                jQuery(window).on('resize', function (e) {
                    self_1._student_resize();
                });
                // record, in case of F5 or window closed
                window.addEventListener("beforeunload", function () {
                    self_1.record_watched();
                });
                break;
        }
    };
    smallpartApp.prototype._student_resize = function () {
        var comments = this.et2.getWidgetById('comments').getDOMNode();
        jQuery(comments).height(jQuery(comments).height() +
            jQuery('form[id^="smallpart-student-index"]').height()
            - jQuery('.rightBoxArea').height() - 40);
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
        // record in case we're playing
        this.record_watched();
        videobar.seek_video(this.edited.comment_starttime);
        // start recording again, in case we're playing
        if (!videobar.video[0].paused)
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
        }
    };
    smallpartApp.prototype._student_setCommentArea = function (_state) {
        this.et2.getWidgetById('add_comment').set_disabled(_state);
        this.et2.getWidgetById('smallpart.student.comment').set_disabled(!_state);
        this.et2.getWidgetById('hideMaskPlayArea').set_disabled(true);
        this._student_resize();
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
                $play.addClass('glyphicon-repeat');
                // record video watched
                self.record_watched();
            }, function (_id) {
                var commentsGrid = jQuery(self.et2.getWidgetById('comments').getDOMNode());
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
        }, this.egw.lang('Delete this comment?'), this.egw.lang('Delete'), et2_widget_dialog_1.et2_dialog.BUTTONS_YES_NO);
    };
    /**
     * Get current active filter
     */
    smallpartApp.prototype.student_getFilter = function () {
        var _a, _b;
        return {
            course_id: ((_a = this.et2.getWidgetById('courses')) === null || _a === void 0 ? void 0 : _a.get_value()) || this.filter.course_id,
            video_id: ((_b = this.et2.getWidgetById('videos')) === null || _b === void 0 ? void 0 : _b.get_value()) || this.filter.video_id,
        };
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
        this._student_commentsFiltering('color', ids);
    };
    smallpartApp.prototype.student_clearFilter = function () {
        this.et2.getWidgetById('comment_color_filter').set_value("");
        this.et2.getWidgetById('comment_search_filter').set_value("");
        this.et2.getWidgetById('activeParticipantsFilter').set_value("");
        for (var f in this.filters) {
            this._student_commentsFiltering(f, []);
        }
    };
    smallpartApp.prototype.student_searchFilter = function (_widget) {
        var query = _widget.get_value();
        var rows = jQuery('tr', this.et2.getWidgetById('comments').getDOMNode());
        var ids = [];
        rows.each(function () {
            jQuery.extend(jQuery.expr[':'].containsCaseInsensitive = function (a, i, m) {
                var t = (a.textContent || a.innerText || "");
                var reg = new RegExp(m[3], 'i');
                return reg.test(t);
            });
            if (query != '' && jQuery(this).find('*:containsCaseInsensitive("' + query + '")').length > 1) {
                ids.push(this.classList.value.match(/commentID.*[0-9]/)[0].replace('commentID', ''));
            }
        });
        this._student_commentsFiltering('search', ids.length == 0 && query != '' ? ['ALL'] : ids);
    };
    smallpartApp.prototype.student_onmouseoverFilter = function (_node, _widget) {
        var self = this;
        var videobar = this.et2.getWidgetById('video');
        var comments = jQuery(this.et2.getWidgetById('comments').getDOMNode());
        if (_widget.get_value()) {
            comments.on('mouseenter', function () {
                var _a;
                if (jQuery(self.et2.getWidgetById('play').getDOMNode()).hasClass('glyphicon-pause')
                    && (!self.edited || ((_a = self.edited) === null || _a === void 0 ? void 0 : _a.action) != 'edit'))
                    videobar.video[0].pause();
            })
                .on('mouseleave', function () {
                var _a;
                if (jQuery(self.et2.getWidgetById('play').getDOMNode()).hasClass('glyphicon-pause')
                    && (!self.edited || ((_a = self.edited) === null || _a === void 0 ? void 0 : _a.action) != 'edit'))
                    videobar.video[0].play();
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
        this._student_setFilterParticipantsOptions();
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
        this.record_watched(_video.previousTime);
        if (!_video.paused)
            this.start_watching();
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
     * filters comments
     *
     * @param string _filter filter name
     * @param array _value array comment ids to be filtered, given array of['ALL']
     * makes all rows hiden and empty array reset the filter.
     */
    smallpartApp.prototype._student_commentsFiltering = function (_filter, _value) {
        var _a;
        var rows = jQuery('tr', this.et2.getWidgetById('comments').getDOMNode());
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
                if (!this.comments[c])
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
            if (!this_1.comments[i])
                return "continue";
            if (this_1.comments[i].filtered.length > 0) {
                rows.filter('.commentID' + this_1.comments[i].comment_id).addClass('hideme');
                tags.filter(function () { return this.dataset.id == self.comments[i].comment_id.toString(); }).addClass('hideme');
            }
            else {
                rows.filter('.commentID' + this_1.comments[i].comment_id).removeClass('hideme');
                tags.filter(function () { return this.dataset.id == self.comments[i].comment_id.toString(); }).removeClass('hideme');
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
        var activeParticipants = this.et2.getWidgetById('activeParticipantsFilter');
        var passiveParticipantsList = this.et2.getWidgetById('passiveParticipantsList');
        var options = {};
        var self = this;
        var participants = this.et2.getArrayMgr('content').getEntry('participants');
        var commentHeaderMessage = this.et2.getWidgetById('commentHeaderMessage');
        var _foundInComments = function (_id) {
            for (var k in self.comments) {
                if (self.comments[k]['account_id'] == _id)
                    return true;
            }
        };
        var _setInfo = function (_options) {
            return new Promise(function (_resolved) {
                var stack = Object.keys(_options);
                self._student_fetchAccountData(stack.pop(), stack, _options, _resolved);
            });
        };
        var _countComments = function (_id) {
            var c = 0;
            for (var i in self.comments) {
                if (self.comments[i]['account_id'] == _id)
                    c++;
            }
            return c;
        };
        if (activeParticipants) {
            for (var i in this.comments) {
                if (!this.comments[i])
                    continue;
                var comment = this.comments[i];
                options[comment.account_id] = options[comment.account_id] || {};
                options[comment.account_id] = jQuery.extend(options[comment.account_id], {
                    value: options[comment.account_id] && typeof options[comment.account_id]['value'] != 'undefined' ?
                        (options[comment.account_id]['value'].indexOf(comment.comment_id)
                            ? options[comment.account_id]['value'].concat(comment.comment_id) : options[comment.account_id]['value'])
                        : [comment.comment_id],
                    name: '',
                    label: '',
                    comments: _countComments(comment.account_id),
                    icon: egw.link('/api/avatar.php', { account_id: comment.account_id })
                });
                if (comment.comment_added) {
                    for (var j in comment.comment_added) {
                        var comment_added = comment.comment_added[j];
                        if (Number.isInteger(comment_added)) {
                            if (typeof options[comment_added] == 'undefined'
                                && !_foundInComments(comment_added)) {
                                options[comment_added] = {
                                    value: [comment.comment_id],
                                    icon: egw.link('/api/avatar.php', { account_id: comment_added })
                                };
                            }
                            else if (typeof options[comment_added] == 'undefined') {
                                options[comment_added] = { value: [] };
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
            _setInfo(options).then(function (_options) {
                var _a, _b;
                for (var i in _options) {
                    if (((_b = (_a = _options[i]) === null || _a === void 0 ? void 0 : _a.value) === null || _b === void 0 ? void 0 : _b.length) > 0) {
                        _options[i].value = _options[i].value.join(',');
                    }
                }
                // set options after all accounts info are fetched
                activeParticipants.set_select_options(_options);
                var passiveParticipants = [{}];
                for (var i in participants) {
                    if (!_options[participants[i].account_id])
                        passiveParticipants.push({ account_id: participants[i].account_id });
                }
                passiveParticipantsList.set_value({ content: passiveParticipants });
                commentHeaderMessage.set_value(self.egw.lang("%1/%2 participants already answered", Object.keys(_options).length, Object.keys(_options).length + passiveParticipants.length - 1));
            });
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
        if (_widget.id === 'courses' && _widget.getValue() === 'manage') {
            this.egw.open(null, 'smallpart', 'list', '', '_self');
        }
        else {
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
                this.egw.open(ids[0], 'smallpart', 'view', '', '_self');
                break;
            default:
                this.egw.json('smallpart.\\EGroupware\\SmallParT\\Courses.ajax_action', [_action.id, ids, false, _password])
                    .sendRequest();
                break;
        }
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
     *
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
        var _a, _b;
        if (!(this.course_options & 1))
            return; // not recording watched videos for this course
        var videobar = (_a = this.et2) === null || _a === void 0 ? void 0 : _a.getWidgetById('video');
        if (typeof this.watching === 'undefined') // video not playing, nothing to record
         {
            return;
        }
        this.watching.endtime = new Date();
        this.watching.duration = (_time || ((_b = videobar) === null || _b === void 0 ? void 0 : _b.currentTime())) - this.watching.position;
        //console.log(this.watching);
        this.egw.json('smallpart.EGroupware\\SmallParT\\Student\\Ui.ajax_recordWatched', [this.watching]).sendRequest('keepalive');
        // reset recording
        delete this.watching;
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
        et2_widget_dialog_1.et2_dialog.show_dialog(function (_button) {
            if (_button !== et2_widget_dialog_1.et2_dialog.CANCEL_BUTTON) {
                widget.getRoot().setValueById('import_overwrite', _button === et2_widget_dialog_1.et2_dialog.YES_BUTTON);
                widget.getInstanceManager().submit(widget, true, true); // last true = no validation
            }
        }, this.egw.lang('Overwrite existing course, or just add videos?'), this.egw.lang('Overwrite exiting course?'), null, et2_widget_dialog_1.et2_dialog.BUTTONS_YES_NO_CANCEL, et2_widget_dialog_1.et2_dialog.QUESTION_MESSAGE);
    };
    smallpartApp.appname = 'smallpart';
    smallpartApp.default_color = 'ffffff'; // white = neutral
    return smallpartApp;
}(egw_app_1.EgwApp));
app.classes.smallpart = smallpartApp;
//# sourceMappingURL=app.js.map