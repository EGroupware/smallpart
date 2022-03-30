/**
 * EGroupware - smallPART - app
 *
 * @link https://www.egroupware.org
 * @package smallpart
 * @subpackage Ui
 * @license http://opensource.org/licenses/gpl-license.php GPL - GNU General Public License
 */

/*egw:uses
	egw_action.egw_action_common;
*/

//import {sprintf} from "../../api/js/egw_action/egw_action_common.js";

/**
 * Marks type for et2_smallpart_videobar::(get|set)Marks()
 */
export interface Mark {
	x: number;
	y: number;
	c: number|string;	// number: use colors lookup table, string: "rrggbb" lowercase hex values
	a?: number;	// disjunctive area: 0, 1, ...
}
export type CommentMarked = Array<Mark>;

export interface MarkWithArea {
	x: number;
	y: number;
	c: number;	// number: use colors lookup table
	a: number;	// disjunctive area: 0, 1, ...
}
export type MarksWithArea = Array<MarkWithArea>;

export type MarkAreas = Array<MarkArea>;

/**
 * Area of Marks keeping track of it's bounding box
 */
export class MarkArea
{
	marks: CommentMarked = [];
	/**
	 * Bounding box of marks
	 */
	private x_min : number;
	private x_max : number;
	private y_min : number;
	private y_max : number;

	/**
	 * Constructor
	 *
	 * @param mark optional mark(s) to initialise the area
	 */
	constructor(mark? : Mark|CommentMarked)
	{
		if (typeof mark !== 'undefined')
		{
			this.add(mark);
		}
	}

	/**
	 * Add mark(s) and update bounding box
	 *
	 * @param mark
	 */
	add(mark : Mark|CommentMarked)
	{
		if (Array.isArray(mark))
		{
			mark.forEach(mark => this.add(mark));
			return;
		}
		this.marks.push(mark);
		if (typeof this.x_min === 'undefined' || mark.x < this.x_min) this.x_min = mark.x;
		if (typeof this.x_max === 'undefined' || mark.x > this.x_max) this.x_max = mark.x;
		if (typeof this.y_min === 'undefined' || mark.y < this.y_min) this.y_min = mark.y;
		if (typeof this.y_max === 'undefined' || mark.y > this.y_max) this.y_max = mark.y;
	}

	// cache sqrt
	static sqrt = {
		0: 0,
		1: 1,
	};

	/**
	 * Check if given mark touches the area
	 *
	 * @param mark
	 * @param dist_square 0: contained in area, 1: touches top, bottom, left or right (default), 2: touches on corner too
	 * @return boolean
	 */
	touches(mark : Mark, dist_square? : number) : boolean
	{
		if (typeof dist_square === 'undefined')
		{
			dist_square = 1;
		}
		if (typeof MarkArea.sqrt[dist_square] === 'undefined')
		{
			MarkArea.sqrt[dist_square] = Math.sqrt(dist_square);
		}
		const dist = MarkArea.sqrt[dist_square];
		const dist_int = Math.floor(dist);

		// 1. check bounding box -> return if not in
		if (mark.x < this.x_min-dist_int || mark.x > this.x_max+dist_int || mark.y < this.y_min-dist_int || mark.y > this.y_max+dist_int)
		{
			return false;
		}

		// 2. check exact pixel coordinates
		for(let n=this.marks.length-1; n >= 0; n--)
		{
			if (MarkArea.distanceSquare(mark, this.marks[n]) <= dist_square)
			{
				return true;
			}
		}

		return false;
	}

	/**
	 * Square of distance between two marks
	 *
	 * @param a
	 * @param b
	 */
	static distanceSquare(a : Mark, b : Mark) : number
	{
		return Math.abs(a.x-b.x)**2 + Math.abs(a.y-b.y)**2;
	}

	/**
	 * Merge area with another
	 *
	 * @param area
	 */
	merge(area : MarkArea)
	{
		if (!area.marks.length)
		{
			return;
		}
		if (typeof this.x_min === 'undefined' || this.x_min > area.x_min) this.x_min = area.x_min;
		if (typeof this.x_max === 'undefined' || this.x_max < area.x_max) this.x_max = area.x_max;
		if (typeof this.y_min === 'undefined' || this.y_min > area.y_min) this.y_min = area.y_min;
		if (typeof this.y_max === 'undefined' || this.y_max < area.y_max) this.y_max = area.y_max;

		this.marks = this.marks.concat(area.marks);
	}

	/**
	 * Videobar pixels are in percent of video with 80 pixel in width direction
	 *
	 * To ease calculation disjunctive areas and pixel touching them, we need to convert to square pixel of size 1 * 1
	 *
	 * @param marks
	 * @param aspect_ratio
	 */
	static squarePixels(marks : CommentMarked, aspect_ratio : number) : CommentMarked
	{
		const squared : CommentMarked = [];
		marks.forEach((mark) =>
		{
			mark.x = Math.round(mark.x / 1.25);
			mark.y = Math.round(mark.y / 1.25 / aspect_ratio);
			squared.push(mark);
		});
		return squared;
	}

	/**
	 * Videobar pixels are in percent of video with 80 pixel in width direction
	 *
	 * Videobar uses precision of 4, so we need to that round here too!
	 *
	 * @param marks
	 * @param aspect_ratio
	 */
	static percentPixels(marks : CommentMarked, aspect_ratio : number) : CommentMarked
	{
		const percent : CommentMarked = [];
		marks.forEach((mark) =>
		{
			mark.x = parseFloat((mark.x * 1.25).toPrecision(4));
			mark.y = parseFloat((mark.y * 1.25 * aspect_ratio).toPrecision(4));
			percent.push(mark);
		});
		return percent;
	}

	/**
	 * Get disjunctive areas
	 *
	 * Areas are disjunctive, if they do NOT touch each other: all their pixel have a distance bigger dist_square
	 *
	 * @param marks
	 * @param color
	 * @param dist_square see touches
	 */
	static disjunctiveAreas(marks : CommentMarked, color? : number, dist_square? : number) : MarkAreas
	{
		// filter by color, if specified
		if (typeof color !== 'undefined')
		{
			marks = marks.filter(mark => {
				return mark.c === color;
			})
		}
		let areas : MarkAreas = [];
		marks.forEach(mark =>
		{
			if (!areas.length)
			{
				areas.push(new MarkArea(mark));
				return;
			}
			let touchedArea : MarkArea = undefined;
			for (let n=0; n < areas.length; n++)
			{
				const area = areas[n];
				if (area.touches(mark, dist_square))
				{
					// mark touches first (existing) area
					if (typeof touchedArea === 'undefined')
					{
						area.add(mark);
						touchedArea = area;
					}
					// second area touched the new mark, merge both
					else
					{
						touchedArea.merge(area);
						areas.splice(n, 1);
						n--;
					}
				}
			}
			if (typeof touchedArea === 'undefined')
			{
				areas.push(new MarkArea(mark));
			}
		});
		return areas;
	}

	/**
	 * Color disjunctive areas with their color and different transparency
	 *
	 * @param marks
	 * @param aspect_ratio width/height of video
	 * @param dist_square see touches
	 */
	static markDisjunctiveAreas(marks : CommentMarked, aspect_ratio : number, dist_square? : number) : MarksWithArea
	{
		// get used colors
		let colors : Array<number> = [];
		marks.forEach(mark =>
		{
			if (colors.indexOf(<number>mark.c) === -1) colors.push(<number>mark.c)
		});
		// convert to square pixels with integer coordinates
		const squared = MarkArea.squarePixels(marks, aspect_ratio);
		const marksWA : MarksWithArea = [];
		colors.forEach(color =>
		{
			// calculate disjunctive areas by color
			const areas = MarkArea.disjunctiveAreas(squared, color, dist_square);
			// and store area index in marks
			areas.forEach((area, idx) =>
			{
				area.marks.forEach((mark) =>
				{
					mark.a = idx;
					marksWA.push(<MarkWithArea>mark);
				});
			});

		});
		// convert pixel back to percentage values used by videobar
		return <MarksWithArea>MarkArea.percentPixels(marksWA, aspect_ratio);
	}

	/**
	 * Color disjunctive areas with their color and different transparency
	 *
	 * @param marks
	 * @param marking_colors
	 */
	static colorDisjunctiveAreas(marks: MarksWithArea, marking_colors : Array<string>): CommentMarked
	{
		// get number of used colors
		let colors = {};
		marks.forEach(mark =>
		{
			if (typeof colors[mark.c] === 'undefined' || colors[mark.c] < mark.a)
			{
				colors[mark.c] = mark.a || 0;
			}
		});
		const coloredMarks : CommentMarked = [];
		// and color marks with different transparency
		marks.forEach(mark =>
		{
			coloredMarks.push({...mark, c: marking_colors[mark.c]+sprintf('%02X',255-Math.round(230/Math.max(4, colors[mark.c]+1))*(mark.a||0))})
		});
		return coloredMarks;
	}
}