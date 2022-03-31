"use strict";
/**
 * EGroupware - smallPART - app
 *
 * @link https://www.egroupware.org
 * @package smallpart
 * @subpackage Ui
 * @license http://opensource.org/licenses/gpl-license.php GPL - GNU General Public License
 */
var __assign = (this && this.__assign) || function () {
    __assign = Object.assign || function(t) {
        for (var s, i = 1, n = arguments.length; i < n; i++) {
            s = arguments[i];
            for (var p in s) if (Object.prototype.hasOwnProperty.call(s, p))
                t[p] = s[p];
        }
        return t;
    };
    return __assign.apply(this, arguments);
};
Object.defineProperty(exports, "__esModule", { value: true });
exports.MarkArea = void 0;
/**
 * Area of Marks keeping track of it's bounding box
 */
var MarkArea = /** @class */ (function () {
    /**
     * Constructor
     *
     * @param mark optional mark(s) to initialise the area
     */
    function MarkArea(mark) {
        this.marks = [];
        if (typeof mark !== 'undefined') {
            this.add(mark);
        }
    }
    /**
     * Add mark(s) and update bounding box
     *
     * @param mark
     */
    MarkArea.prototype.add = function (mark) {
        var _this = this;
        if (Array.isArray(mark)) {
            mark.forEach(function (mark) { return _this.add(mark); });
            return;
        }
        this.marks.push(mark);
        if (typeof this.x_min === 'undefined' || mark.x < this.x_min)
            this.x_min = mark.x;
        if (typeof this.x_max === 'undefined' || mark.x > this.x_max)
            this.x_max = mark.x;
        if (typeof this.y_min === 'undefined' || mark.y < this.y_min)
            this.y_min = mark.y;
        if (typeof this.y_max === 'undefined' || mark.y > this.y_max)
            this.y_max = mark.y;
    };
    /**
     * Check if given mark touches the area
     *
     * @param mark
     * @param dist_square 0: contained in area, 1: touches top, bottom, left or right (default), 2: touches on corner too
     * @return boolean
     */
    MarkArea.prototype.touches = function (mark, dist_square) {
        if (typeof dist_square === 'undefined') {
            dist_square = 1;
        }
        if (typeof MarkArea.sqrt[dist_square] === 'undefined') {
            MarkArea.sqrt[dist_square] = Math.sqrt(dist_square);
        }
        var dist = MarkArea.sqrt[dist_square];
        var dist_int = Math.floor(dist);
        // 1. check bounding box -> return if not in
        if (mark.x < this.x_min - dist_int || mark.x > this.x_max + dist_int || mark.y < this.y_min - dist_int || mark.y > this.y_max + dist_int) {
            return false;
        }
        // 2. check exact pixel coordinates
        for (var n = this.marks.length - 1; n >= 0; n--) {
            if (MarkArea.distanceSquare(mark, this.marks[n]) <= dist_square) {
                return true;
            }
        }
        return false;
    };
    /**
     * Square of distance between two marks
     *
     * @param a
     * @param b
     */
    MarkArea.distanceSquare = function (a, b) {
        return Math.pow(Math.abs(a.x - b.x), 2) + Math.pow(Math.abs(a.y - b.y), 2);
    };
    /**
     * Merge area with another
     *
     * @param area
     */
    MarkArea.prototype.merge = function (area) {
        if (!area.marks.length) {
            return;
        }
        if (typeof this.x_min === 'undefined' || this.x_min > area.x_min)
            this.x_min = area.x_min;
        if (typeof this.x_max === 'undefined' || this.x_max < area.x_max)
            this.x_max = area.x_max;
        if (typeof this.y_min === 'undefined' || this.y_min > area.y_min)
            this.y_min = area.y_min;
        if (typeof this.y_max === 'undefined' || this.y_max < area.y_max)
            this.y_max = area.y_max;
        this.marks = this.marks.concat(area.marks);
    };
    /**
     * Videobar pixels are in percent of video with 80 pixel in width direction
     *
     * To ease calculation disjunctive areas and pixel touching them, we need to convert to square pixel of size 1 * 1
     *
     * @param marks
     * @param aspect_ratio
     */
    MarkArea.squarePixels = function (marks, aspect_ratio) {
        var squared = [];
        marks.forEach(function (mark) {
            mark.x = Math.round(mark.x / 1.25);
            mark.y = Math.round(mark.y / 1.25 / aspect_ratio);
            squared.push(mark);
        });
        return squared;
    };
    /**
     * Videobar pixels are in percent of video with 80 pixel in width direction
     *
     * Videobar uses precision of 4, so we need to that round here too!
     *
     * @param marks
     * @param aspect_ratio
     */
    MarkArea.percentPixels = function (marks, aspect_ratio) {
        var percent = [];
        marks.forEach(function (mark) {
            mark.x = parseFloat((mark.x * 1.25).toPrecision(4));
            mark.y = parseFloat((mark.y * 1.25 * aspect_ratio).toPrecision(4));
            percent.push(mark);
        });
        return percent;
    };
    /**
     * Get disjunctive areas
     *
     * Areas are disjunctive, if they do NOT touch each other: all their pixel have a distance bigger dist_square
     *
     * @param marks
     * @param color
     * @param dist_square see touches
     */
    MarkArea.disjunctiveAreas = function (marks, color, dist_square) {
        // filter by color, if specified
        if (typeof color !== 'undefined') {
            marks = marks.filter(function (mark) {
                return mark.c === color;
            });
        }
        var areas = [];
        marks.forEach(function (mark) {
            if (!areas.length) {
                areas.push(new MarkArea(mark));
                return;
            }
            var touchedArea = undefined;
            for (var n = 0; n < areas.length; n++) {
                var area = areas[n];
                if (area.touches(mark, dist_square)) {
                    // mark touches first (existing) area
                    if (typeof touchedArea === 'undefined') {
                        area.add(mark);
                        touchedArea = area;
                    }
                    // second area touched the new mark, merge both
                    else {
                        touchedArea.merge(area);
                        areas.splice(n, 1);
                        n--;
                    }
                }
            }
            if (typeof touchedArea === 'undefined') {
                areas.push(new MarkArea(mark));
            }
        });
        return areas;
    };
    /**
     * Color disjunctive areas with their color and different transparency
     *
     * @param marks
     * @param aspect_ratio width/height of video
     * @param dist_square see touches
     */
    MarkArea.markDisjunctiveAreas = function (marks, aspect_ratio, dist_square) {
        // get used colors
        var colors = [];
        marks.forEach(function (mark) {
            if (colors.indexOf(mark.c) === -1)
                colors.push(mark.c);
        });
        // convert to square pixels with integer coordinates
        var squared = MarkArea.squarePixels(marks, aspect_ratio);
        var marksWA = [];
        colors.forEach(function (color) {
            // calculate disjunctive areas by color
            var areas = MarkArea.disjunctiveAreas(squared, color, dist_square);
            // and store area index in marks
            areas.forEach(function (area, idx) {
                area.marks.forEach(function (mark) {
                    mark.a = idx;
                    marksWA.push(mark);
                });
            });
        });
        // convert pixel back to percentage values used by videobar
        return MarkArea.percentPixels(marksWA, aspect_ratio);
    };
    /**
     * Color disjunctive areas with their color and different transparency
     *
     * @param marks
     * @param marking_colors
     */
    MarkArea.colorDisjunctiveAreas = function (marks, marking_colors) {
        // get number of used colors
        var colors = {};
        marks.forEach(function (mark) {
            if (typeof colors[mark.c] === 'undefined' || colors[mark.c] < mark.a) {
                colors[mark.c] = mark.a || 0;
            }
        });
        var coloredMarks = [];
        // and color marks with different transparency
        marks.forEach(function (mark) {
            coloredMarks.push(__assign(__assign({}, mark), { c: marking_colors[mark.c] + sprintf('%02X', 255 - Math.round(230 / Math.max(4, colors[mark.c] + 1)) * (mark.a || 0)) }));
        });
        return coloredMarks;
    };
    // cache sqrt
    MarkArea.sqrt = {
        0: 0,
        1: 1,
    };
    return MarkArea;
}());
exports.MarkArea = MarkArea;
//# sourceMappingURL=mark_helpers.js.map