import {assert} from "@open-wc/testing";
import {et2_smallpart_videobar} from "../et2_widget_videobar";

/**
 * Contract under test: et2_smallpart_videobar.set_slider_tags() puts one tag on the video's slider for each comment,
 * at the comment's start time, in the colour of its category, carrying the comment's classes and id.
 *
 * It used to position each tag with jQuery's offset() and ask the slider's width again for every tag, so the browser
 * laid the page out once per comment: 2.5 s for 889 comments.  Now the width is read once and the tags are built
 * off-screen and added together (10 ms).
 *
 * Setup: the method is called with a stand-in for the widget that has only what it reads - the category options, a
 * video width, the slider element, its width (1000 px) and the video's duration (100 s), so 1 s is 10 px.
 *
 * Environment: none, positions come from numbers.
 */

function standIn(overrides : Record<string, any> = {}) : any
{
	const slider = document.createElement("div");
	const progress = document.createElement("span");
	progress.className = "videobar_slider_progressbar";
	return {
		slider_el: slider,
		slider: {
			empty: () => slider.replaceChildren(),
			append: (node : Node) => slider.append(node),
			width: () => 1000,
			0: slider
		},
		slider_progressbar: progress,
		video: {width: () => 800},
		duration: () => 100,
		getArrayMgr: () => ({getEntry: () => [{value: "5", color: "#00ff00"}, {value: "free", color: "#ffffff"}]}),
		...overrides
	};
}

const setTags = (widget : any, comments : any[]) => (<any>et2_smallpart_videobar.prototype).set_slider_tags.call(widget, comments);
const tags = (widget : any) : HTMLElement[] => Array.from(widget.slider_el.querySelectorAll(".commentOnSlider"));

describe("video bar slider tags", () =>
{
	/**
	 * One tag per comment: left is the start time in pixels, the background the colour of the comment's main
	 * category (a "main:sub" category uses the main one), a category the course does not know falls back to
	 * white, and the classes of the comment come along with commentHasDuration for comments longer than a second.
	 */
	it("places a tag per comment at its start time, in its category's colour", () =>
	{
		const widget = standIn();
		setTags(widget, [
			{comment_id: 1, comment_starttime: 12, comment_stoptime: 12, comment_cat: "5:9", class: "commentOwner cat-5"},
			{comment_id: 2, comment_starttime: "30", comment_stoptime: 35, comment_cat: "unknown", class: "cat-unknown"},
			{comment_id: 3, comment_starttime: 50, comment_stoptime: 50, comment_cat: "free", class: ""}
		]);
		const drawn = tags(widget);
		assert.equal(drawn.length, 3);
		assert.deepEqual(drawn.map(t => t.dataset.id), ["1", "2", "3"]);
		assert.deepEqual(drawn.map(t => t.style.left), ["120px", "300px", "500px"]);
		assert.equal(drawn[0].style.backgroundColor, "rgb(0, 255, 0)");
		assert.equal(drawn[1].style.backgroundColor, "rgb(255, 255, 255)", "unknown category: white");
		assert.isTrue(drawn[0].classList.contains("commentOwner") && drawn[0].classList.contains("cat-5"));
		assert.isTrue(drawn[1].classList.contains("commentHasDuration"), "5 s long");
		assert.isFalse(drawn[0].classList.contains("commentHasDuration"));
	});

	/**
	 * Setting the tags again replaces them, keeps the progress bar, and skips entries that are not comments (the
	 * server's list starts with an empty one).  Before the video has a width nothing is touched.
	 */
	it("replaces the tags, keeps the progress bar, skips non-comments and waits for the video", () =>
	{
		const widget = standIn();
		setTags(widget, [{comment_id: 1, comment_starttime: 1, comment_stoptime: 1, comment_cat: "free", class: ""}]);
		setTags(widget, [[], {comment_id: 2, comment_starttime: 2, comment_stoptime: 2, comment_cat: "free", class: ""}]);
		assert.deepEqual(tags(widget).map(t => t.dataset.id), ["2"]);
		assert.isNotNull(widget.slider_el.querySelector(".videobar_slider_progressbar"));

		const unloaded = standIn({video: {width: () => 0}});
		setTags(unloaded, [{comment_id: 1, comment_starttime: 1, comment_stoptime: 1, comment_cat: "free", class: ""}]);
		assert.equal(tags(unloaded).length, 0, "nothing is drawn before the video has a width");
	});

	/**
	 * The point of the rewrite: the slider's width is measured once, not once per tag.  Pass: 500 comments cause
	 * exactly one width read.
	 */
	it("measures the slider once, not once per tag", () =>
	{
		let widthReads = 0;
		const widget = standIn();
		widget.slider.width = () => { widthReads++; return 1000; };
		setTags(widget, Array.from({length: 500}, (_v, i) =>
			({comment_id: i + 1, comment_starttime: i % 100, comment_stoptime: i % 100, comment_cat: "free", class: ""})));
		assert.equal(tags(widget).length, 500);
		assert.equal(widthReads, 1);
	});
});
