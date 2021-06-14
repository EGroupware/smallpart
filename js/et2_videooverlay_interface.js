import { implements_methods } from "../../api/js/etemplate/et2_core_interfaces";
export var PlayerMode;
(function (PlayerMode) {
    PlayerMode[PlayerMode["Unchanged"] = 0] = "Unchanged";
    PlayerMode[PlayerMode["Pause"] = 1] = "Pause";
    PlayerMode[PlayerMode["Disable"] = 2] = "Disable";
})(PlayerMode || (PlayerMode = {}));
export var et2_IOverlayElement = "et2_IOverlayElement";
export function implements_et2_IOverlayElement(obj) {
    return implements_methods(obj, ["keepRunning"]);
}
export var et2_IOverlayElementEditor = "et2_IOverlayElementEditor";
export function implements_et2_IOverlayElementEditor(obj) {
    return implements_methods(obj, ["onSaveCallback"]);
}
//# sourceMappingURL=et2_videooverlay_interface.js.map