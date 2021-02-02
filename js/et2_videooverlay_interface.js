"use strict";
Object.defineProperty(exports, "__esModule", { value: true });
exports.PlayerMode = void 0;
var PlayerMode;
(function (PlayerMode) {
    PlayerMode[PlayerMode["Unchanged"] = 0] = "Unchanged";
    PlayerMode[PlayerMode["Pause"] = 1] = "Pause";
    PlayerMode[PlayerMode["Disable"] = 2] = "Disable";
})(PlayerMode = exports.PlayerMode || (exports.PlayerMode = {}));
var et2_IOverlayElement = "et2_IOverlayElement";
function implements_et2_IOverlayElement(obj) {
    return implements_methods(obj, ["keepRunning"]);
}
var et2_IOverlayElementEditor = "et2_IOverlayElementEditor";
function implements_et2_IOverlayElementEditor(obj) {
    return implements_methods(obj, ["onSaveCallback"]);
}
//# sourceMappingURL=et2_videooverlay_interface.js.map