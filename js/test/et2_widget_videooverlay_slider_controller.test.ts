import {assert} from "@open-wc/testing";
import {et2_smallpart_videooverlay_slider_controller} from "../et2_widget_videooverlay_slider_controller";

/**
 * Contract under test: the slider controller draws one plain element per element given to set_value(), places
 * overlapping ones in different rows, hands a click on one to the click callback through ONE listener on the
 * bar, and replaces its marks - not adds to them - when set_value() runs again.
 *
 * It used to create an et2 widget per mark: rebuilding 889 staff comments blocked the page for 5.5 s, and every
 * rebuild left the old widgets in getChildren() (8,913 children after ten rebuilds).
 *
 * Setup: the controller is built without a parent and given a stand-in for the video bar - it only needs a
 * duration and the pixel position of a time (10 px per second here).
 *
 * Environment: none, the marks are laid out from numbers, not measured.
 */

function controller(options : Record<string, any> = {}) : any
{
	const ctrl : any = new (<any>et2_smallpart_videooverlay_slider_controller)(null, {id: "comments_slider"});
	ctrl.videobar = {
		duration: () => 100,
		_vtimeToSliderPosition: (time : number | string) => Number(time) * 10,
		video: [document.createElement("div")]
	};
	ctrl.options = {seekable: true, ...options};
	return ctrl;
}

const element = (id : number, starttime : number, duration : number = 1, extra : Record<string, any> = {}) =>
	({id, starttime, duration, color: "#ff0000", account_id: 1, ...extra});

const marks = (ctrl : any) : HTMLElement[] => Array.from(ctrl.div[0].querySelectorAll(".et2_label"));
const settle = () => new Promise<void>(resolve => setTimeout(resolve, 0));

describe("slider controller marks", () =>
{
	/**
	 * One element in, one plain element out, at the position of its time, with its colour and id.
	 */
	it("draws a plain element for each element given", async() =>
	{
		const ctrl = controller();
		ctrl.set_value([element(7, 12, 3), element(8, 40)]);
		await settle();
		const drawn = marks(ctrl);
		assert.equal(drawn.length, 2);
		assert.equal(drawn[0].dataset.id, "7");
		assert.equal(drawn[0].style.left, "120px");
		assert.equal(drawn[0].style.width, "30px", "a duration of 3 s is 30 px wide");
		assert.equal(drawn[0].style.backgroundColor, "rgb(255, 0, 0)");
		assert.equal(drawn[0].localName, "span");
	});

	/**
	 * Marks that touch go into different rows (5 px apart) and keep their order; marks that do not touch share
	 * row 0.  Setup: 1 and 2 overlap, 3 is later, 4 overlaps 3 and 2's row is taken.
	 */
	it("puts marks that overlap in different rows", async() =>
	{
		const ctrl = controller();
		ctrl.set_value([element(1, 10, 5), element(2, 12, 5), element(3, 50), element(4, 50)]);
		await settle();
		const top = (id : number) => marks(ctrl).find(m => m.dataset.id === String(id))!.style.top;
		assert.equal(top(1), "3px");
		assert.equal(top(2), "8px", "touches 1: next row");
		assert.equal(top(3), "3px", "far from the others: first row again");
		assert.equal(top(4), "8px", "touches 3: next row");
	});

	/**
	 * Running set_value() again replaces the marks.  Ten rebuilds of 100 elements must leave 100 elements in the bar
	 * and 100 marks to ask for - the old implementation kept a growing list.
	 */
	it("replaces its marks when set again", async() =>
	{
		const ctrl = controller();
		const elements = Array.from({length: 100}, (_v, i) => element(i + 1, i));
		for(let i = 0; i < 10; i++)
		{
			ctrl.set_value(elements);
			await settle();
		}
		assert.equal(marks(ctrl).length, 100);
		assert.equal(Object.keys(ctrl.marks).length, 100);
		assert.equal(ctrl.getChildren().length, 0, "no widget per mark");
		ctrl.set_value([element(5, 5)]);
		await settle();
		assert.equal(marks(ctrl).length, 1);
		assert.isUndefined(ctrl.getMark(6), "a mark that is gone cannot be asked for");
	});

	/**
	 * A click on a mark calls the callback once with a mark whose id is "slider-tag-<id>", selects it and only it,
	 * and does not reach the bar's own seek handler; a click on the bar elsewhere is left alone.
	 */
	it("reports a click on a mark through one listener on the bar", async() =>
	{
		const calls : string[] = [];
		const ctrl = controller();
		ctrl.onclick_callback = (_event : Event, mark : any) => { calls.push(mark.id); return true; };
		ctrl.options.onclick_callback = ctrl.onclick_callback;
		let barClicks = 0;
		ctrl.div[0].addEventListener("click", () => barClicks++);
		ctrl.set_value([element(1, 10), element(2, 30)]);
		await settle();

		marks(ctrl)[1].click();
		assert.deepEqual(calls, ["slider-tag-2"]);
		assert.isTrue(marks(ctrl)[1].classList.contains("selected"));
		assert.isFalse(marks(ctrl)[0].classList.contains("selected"));
		assert.equal(barClicks, 0, "the mark's click does not continue to the bar");
		assert.equal(ctrl.get_selected().id, "2");

		marks(ctrl)[0].click();
		assert.isFalse(marks(ctrl)[1].classList.contains("selected"), "only one selected");
		assert.equal(ctrl.get_selected().id, "1");

		ctrl.div[0].click();
		assert.equal(calls.length, 2, "a click on the bar is not a mark click");
		assert.equal(barClicks, 1);
	});

	/**
	 * With callbacks disabled (while a comment is being edited) a click on a mark does nothing and does not seek; set_selected(null)
	 * clears the selection; a bar that is not seekable ignores marks and leaves the click to the bar.
	 */
	it("ignores clicks while disabled and when not seekable, and clears the selection", async() =>
	{
		const calls : string[] = [];
		const ctrl = controller();
		ctrl.onclick_callback = (_e : Event, mark : any) => { calls.push(mark.id); return true; };
		ctrl.options.onclick_callback = ctrl.onclick_callback;
		let barClicks = 0;
		ctrl.div[0].addEventListener("click", () => barClicks++);
		ctrl.set_value([element(1, 10)]);
		await settle();

		ctrl.disableCallback(true);
		marks(ctrl)[0].click();
		assert.deepEqual(calls, []);
		assert.equal(barClicks, 0, "swallowed, like the old per-mark handler did");
		ctrl.disableCallback(false);

		marks(ctrl)[0].click();
		assert.equal(calls.length, 1);
		ctrl.set_selected(null);
		assert.isFalse(marks(ctrl)[0].classList.contains("selected"));

		ctrl.options.seekable = false;
		marks(ctrl)[0].click();
		assert.equal(calls.length, 1, "not seekable: marks do nothing");
		assert.equal(barClicks, 1, "and the click reaches the bar");
	});
});
