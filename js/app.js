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
                break;
        }
    };
    smallpartApp.prototype.student_openComment = function (_action, _selected) {
        if (!isNaN(_selected))
            _selected = [{ data: this.et2.getArrayMgr('content').getEntry('comments')[_selected] }];
        var data = _selected[0].data;
        var videobar = this.et2.getWidgetById('video');
        var comment = this.et2.getWidgetById('comment');
        this.et2.getWidgetById('play').set_disabled(_action.id == "edit");
        this.et2.getWidgetById('add_comment').set_disabled(true);
        this.et2.getWidgetById('smallpart.student.comment').set_disabled(false);
        videobar.seek_video(data.comment_starttime);
        if (comment) {
            if (_action.id == "edit") {
                comment.set_value({ content: {
                        comment_added: data.comment_added,
                        comment_starttime: data.comment_starttime,
                        markedColorRadio: smallpartApp._convertColorToString(data.comment_color),
                        commentColorRadio: smallpartApp._convertColorToString(data.comment_color),
                        isOpenOnly: false
                    } });
                this.et2.getWidgetById('commentColorRadio').set_value(smallpartApp._convertColorToString(data.comment_color));
            }
            else {
                comment.set_value({ content: {
                        comment_added: data.comment_added,
                        comment_starttime: data.comment_starttime,
                        comment_marked_message: egw.lang('Comment is marked as %1', smallpartApp._convertColorToString(data.comment_color)),
                        isOpenOnly: true
                    } });
            }
        }
    };
    smallpartApp._convertColorToString = function (_color) {
        switch (_color) {
            case 'ffffff':
                return egw.lang('white');
            case '00ff00':
                return egw.lang('green');
            case 'ff0000':
                return egw.lang('red');
        }
    };
    smallpartApp.prototype.student_radioCommentArea = function (_node, _widget) {
        var $radios = jQuery("[id^='smallpart-student-index_commentColorRadio']");
        if (_node.checked) {
            $radios.removeClass('checked');
            jQuery(_node).addClass('checked');
        }
    };
    smallpartApp.prototype.student_radioMarkedArea = function (_node, _widget) {
        var $radios = jQuery("[id^='smallpart-student-index_markedColorRadio']");
        if (_node.checked) {
            $radios.removeClass('checked');
            jQuery(_node).addClass('checked');
        }
    };
    smallpartApp.prototype.student_playVideo = function () {
        var videobar = this.et2.getWidgetById('video');
        var $play = jQuery(this.et2.getWidgetById('play').getDOMNode());
        this.et2.getWidgetById('add_comment').set_disabled(false);
        this.et2.getWidgetById('smallpart.student.comment').set_disabled(true);
        if ($play.hasClass('pause')) {
            videobar.pause_video();
            $play.removeClass('pause');
        }
        else {
            videobar.play_video();
            $play.addClass('pause');
        }
    };
    smallpartApp.prototype.student_cancelAndContinue = function () {
        this.et2.getWidgetById('add_comment').set_disabled(false);
        this.et2.getWidgetById('play').set_disabled(false);
        this.et2.getWidgetById('smallpart.student.comment').set_disabled(true);
    };
    smallpartApp.prototype.student_editCommentAndContinue = function () {
    };
    smallpartApp.prototype.student_revertMarks = function () {
    };
    smallpartApp.prototype.student_hideBackground = function () {
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
    return smallpartApp;
}(egw_app_1.EgwApp));
app.classes.smallpart = smallpartApp;
//# sourceMappingURL=app.js.map