"use strict";
/**
 * EGroupware SmallPART - Video Recorder
 *
 * @license http://opensource.org/licenses/gpl-license.php GPL - GNU General Public License
 * @package smallpart
 * @subpackage ui
 * @link https://www.egroupware.org
 * @author Hadi Nategh<hn@egroupware.org>
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
exports.et2_smallpart_video_recorder = void 0;
/*egw:uses
    et2_core_baseWidget;
*/
var et2_core_baseWidget_1 = require("../../api/js/etemplate/et2_core_baseWidget");
var et2_core_widget_1 = require("../../api/js/etemplate/et2_core_widget");
var et2_core_inheritance_1 = require("../../api/js/etemplate/et2_core_inheritance");
var et2_smallpart_video_recorder = /** @class */ (function (_super) {
    __extends(et2_smallpart_video_recorder, _super);
    /**
     * Constructor
     */
    function et2_smallpart_video_recorder(_parent, _attrs, _child) {
        var _this = 
        // Call the inherited constructor
        _super.call(this, _parent, _attrs, et2_core_inheritance_1.ClassWithAttributes.extendAttributes(et2_smallpart_video_recorder._attributes, _child || {})) || this;
        _this._recorder = null;
        _this._content = [];
        _this.div = document.createElement("div");
        _this.div.classList.add("et2_" + _this.getType());
        _this._video = document.createElement("video");
        _this._video.classList.add('video-media');
        _this._video.setAttribute('autoplay', true);
        _this._video.setAttribute('muted', true);
        _this.div.append(_this._video);
        _this._content = _this.getInstanceManager()._widgetContainer.getArrayMgr('content');
        _this.setDOMNode(_this.div);
        return _this;
    }
    et2_smallpart_video_recorder.prototype.destroy = function () {
        this.stopMedia();
    };
    /**
     * stop media stream
     */
    et2_smallpart_video_recorder.prototype.stopMedia = function () {
        if (this._video.srcObject) {
            this._video.srcObject.getTracks().forEach(function (track) { return track.stop(); });
            this._video.srcObject = null;
        }
    };
    /**
     * Initialize media stream
     */
    et2_smallpart_video_recorder.prototype.initMedia = function () {
        var _this = this;
        return new Promise(function (resolve) {
            if (_this._video.srcObject) {
                resolve(_this._video.captureStream());
                return;
            }
            navigator.mediaDevices.getUserMedia({
                video: true,
                audio: true
            }).then(function (stream) {
                _this._video.srcObject = stream;
                _this._video.captureStream = _this._video.captureStream || _this._video.mozCaptureStream;
                return new Promise(function (_resolve) {
                    _this._video.addEventListener('loadedmetadata', _resolve);
                });
            }).then(function () {
                resolve(_this._video.captureStream());
            });
        });
    };
    /**
     *
     */
    et2_smallpart_video_recorder.prototype.startMedia = function () {
        this.initMedia().then(function () { });
    };
    /**
     * start recording
     * @return returns a promise to make sure the media is established and recording started
     */
    et2_smallpart_video_recorder.prototype.record = function () {
        var _this = this;
        return new Promise(function (_resolve) {
            _this.initMedia().then(function (_stream) {
                _this._recorder = new MediaRecorder(_stream);
                _this._recorder.start();
                _resolve();
            });
        });
    };
    /**
     * stop recording
     * @return returns a promise, to make sure the recording has stopped
     */
    et2_smallpart_video_recorder.prototype.stop = function () {
        var _this = this;
        return new Promise(function (_resolved) {
            if (_this._video && _this._recorder) {
                _this._recorder.ondataavailable = function (event) {
                    var _a, _b, _c, _d;
                    var a = document.createElement('a');
                    a.download = (_d = (_c = (_b = (_a = _this._content) === null || _a === void 0 ? void 0 : _a.data) === null || _b === void 0 ? void 0 : _b.video) === null || _c === void 0 ? void 0 : _c.video_name) !== null && _d !== void 0 ? _d : ['livefeedback_', (new Date() + '').slice(4, 33), '.webm'].join('');
                    a.href = URL.createObjectURL(event.data);
                    a.click();
                };
                _this._recorder.onstop = _resolved;
                if (_this._recorder.state === 'recording')
                    _this._recorder.stop();
            }
        });
    };
    et2_smallpart_video_recorder._attributes = {};
    return et2_smallpart_video_recorder;
}(et2_core_baseWidget_1.et2_baseWidget));
exports.et2_smallpart_video_recorder = et2_smallpart_video_recorder;
et2_core_widget_1.et2_register_widget(et2_smallpart_video_recorder, ["smallpart-video-recorder"]);
//# sourceMappingURL=et2_widget_video_recorder.js.map