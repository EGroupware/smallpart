import {assert} from "@open-wc/testing";
import * as sinon from "sinon";
import {SmallPartCommentList} from "../SmallPartCommentList";
import {egw_getAppObjectManager} from "../../../../api/js/egw_action/egw_action";
import {_egw_active_menu} from "../../../../api/js/egw_action/egw_menu";
import {restoreResizeObserverNoise, silenceResizeObserverNoise} from "../../../../api/js/etemplate/Et2Historylog/test/resizeObserverNoise";

/**
 * Contract under test: smallpart-comment-list shows the comments of a video in an et2-datagrid, keeps
 * them in step with its store, and tells its owner what the user did - with ONE click listener for the list.
 *
 * Setup strategy: a real list in a sized box, comments built by hand.  The row template is not loaded (that
 * needs a server); a small hand-made one with the same classes as comment.rows.xet stands in, since the
 * template's own parsing is Et2RowProvider's contract.  A row shows the comment's `text` and has the edit and
 * reply controls the real template has.
 *
 * Environment: real timers and real layout (the datagrid is virtualised), a 400x600 px box.
 */

const egwStub = {
	lang: (label : string) => label,
	tooltipBind: () => {},
	tooltipUnbind: () => {},
	image: () => "",
	preference: () => null,
	set_preference: () => {},
	app_name: () => "smallpart",
	link: (url : string) => url,
	debug: () => {},
	debug_level: () => 0,
	user: (key : string) => key === "account_id" ? 10 : null,
	lang_notranslate: (s : string) => s,
	accounts: () => Promise.resolve([]),
	accountData: () => {},
	accountInfo: () => null
};
window.egw = function() { return egwStub; } as any;
Object.assign(window.egw, egwStub);

const frame = () => new Promise<void>(resolve => requestAnimationFrame(() => resolve()));

/** Wait until a condition holds, a frame at a time - bounded, so a failure is an assertion not a mocha timeout */
async function until(condition : () => boolean, frames = 60)
{
	for(let i = 0; i < frames && !condition(); i++)
	{
		await frame();
	}
}

function makeComments(n : number, extra : (i : number) => Record<string, any> = () => ({})) : any[]
{
	return [[]].concat(Array.from({length: n}, (_v, i) => ({
		comment_id: i + 1,
		course_id: 1,
		video_id: 1,
		account_id: 10 + (i % 3),
		comment_starttime: i * 5,
		comment_stoptime: i * 5,
		comment_cat: "free",
		comment_cat_type: "",
		comment_color: "ffffff",
		comment_added: ["text " + (i + 1)],
		comment_updated: {date: "2026-10-09 12:00:00.000000"},
		class: "",
		// the stand-in template shows this; its length makes the tiles different heights
		text: "Comment " + (i + 1) + " " + "lorem ipsum dolor sit amet ".repeat(1 + ((i * 7) % 11)),
		...extra(i)
	})) as any[]);
}

interface Mounted
{
	list : SmallPartCommentList;
	box : HTMLElement;
	grid : any;
	rows : () => HTMLElement[];
	row : (id : number | string) => HTMLElement | null;
}

async function mount(comments : any[], height = 600) : Promise<Mounted>
{
	const box = document.createElement("div");
	box.style.cssText = `width:400px;height:${height}px;display:flex;flex-direction:column`;
	document.body.append(box);

	const list = new SmallPartCommentList();
	list.value = comments;
	// The template needs a server; give the list the structure of comment.rows.xet by hand instead
	const provider : any = (<any>list)._rowProvider;
	const column = [{key: "text", title: "Text", width: "1fr"}] as any;
	const tpl = document.createElement("tr");
	tpl.className = "commentBox $row_class";
	tpl.innerHTML = `<td><div class="commentTile"><span class="commentEditBtn">edit</span> <span class="text">$text</span> ` +
		`<button class="commentRetweetBtn">reply</button></div></td>`;
	const prepared = await provider._prepareRowTemplate(tpl, column);
	provider.fromTemplate = async() => ({
		columns: column,
		rowTemplate: prepared?.template ?? null,
		rowTemplateXml: prepared?.xml ?? null,
		rowTemplateAttrMap: prepared?.attrMap ?? {},
		loaderTemplate: null,
		noHeader: true
	});
	box.append(list);
	await list.updateComplete;

	const grid : any = (<any>list).shadowRoot.querySelector("et2-datagrid");
	const rows = () => <HTMLElement[]>Array.from(grid.shadowRoot.querySelectorAll("[data-row-id]"));
	await until(() => rows().length > 0 || comments.length <= 1);
	return {
		list, box, grid, rows,
		row: id => rows().find(r => r.dataset.rowId === String(id)) ?? null
	};
}

describe("SmallPartCommentList", () =>
{
	// A connected datagrid runs a virtualizer that sometimes trips a harmless ResizeObserver-loop error
	before(silenceResizeObserverNoise);
	after(restoreResizeObserverNoise);

	let mounted : Mounted[] = [];
	const track = (m : Mounted) =>
	{
		mounted.push(m);
		return m;
	};
	afterEach(() =>
	{
		mounted.forEach(m => m.box.remove());
		mounted = [];
		sinon.restore();
	});

	/**
	 * Only the tiles in view exist.  Setup: 2000 comments.  Pass: some rows are drawn but far fewer than 2000,
	 * the first one is the first comment, and the datagrid knows the full count.
	 */
	it("draws only the tiles in view", async() =>
	{
		const m = track(await mount(makeComments(2000)));
		assert.isAbove(m.rows().length, 3);
		assert.isBelow(m.rows().length, 120);
		assert.equal(m.rows()[0].dataset.rowId, "1");
		assert.equal(m.grid.total, 2000);
	});

	/**
	 * The list skips the empty entry the server puts first.  Pass: 3 comments in -> total 3.
	 */
	it("ignores the empty header entry of the server's list", async() =>
	{
		const m = track(await mount(makeComments(3)));
		assert.equal(m.grid.total, 3);
		assert.equal(m.rows().length, 3);
	});

	/**
	 * A row is the comment's data: row classes made by the list (commentID, colour, category type) are on the row.
	 */
	it("gives each row its classes", async() =>
	{
		const m = track(await mount(makeComments(3, i => ({comment_color: "ff0000", class: "commentOwner"}))));
		const row = m.row(2)!;
		for(const name of ["commentBox", "commentOwner", "commentID2", "commentColorff0000", "cat-free"])
		{
			assert.isTrue(row.classList.contains(name), "row has class " + name + ": " + row.className);
		}
	});

	/**
	 * Editing a comment (a push of the same comment id) redraws that tile.  Setup: change comment 2's text and
	 * upsert it.  Pass: its row shows the new text; the others still show their own.
	 */
	it("redraws an edited comment", async() =>
	{
		const m = track(await mount(makeComments(5)));
		const edited = {...m.list.getComment(2)!, text: "EDITED text"};
		m.list.upsertComment(edited);
		await until(() => m.row(2)?.textContent?.includes("EDITED") ?? false);
		assert.include(m.row(2)!.textContent, "EDITED text");
		assert.include(m.row(3)!.textContent, "Comment 3");
	});

	/**
	 * A new comment appears at its sorted place, a removed one disappears.  Pass: a comment starting before all
	 * others is the first row; removing it again restores the old first row.
	 */
	it("adds and removes comments in order", async() =>
	{
		const m = track(await mount(makeComments(5)));
		m.list.upsertComment({...m.list.getComment(1)!, comment_id: 99, comment_starttime: -1, text: "NEW first"});
		await until(() => m.rows()[0]?.dataset.rowId === "99");
		assert.equal(m.rows()[0].dataset.rowId, "99");
		assert.equal(m.grid.total, 6);
		m.list.removeComment(99);
		await until(() => m.rows()[0]?.dataset.rowId === "1");
		assert.equal(m.rows()[0].dataset.rowId, "1");
		assert.equal(m.grid.total, 5);
	});

	/**
	 * Removing an id the list does not hold changes nothing - the old client removed the LAST comment for it.
	 */
	it("ignores removal of an unknown comment", async() =>
	{
		const m = track(await mount(makeComments(5)));
		const before = m.rows().map(r => r.dataset.rowId);
		assert.isFalse(m.list.removeComment(12345));
		await frame();
		await frame();
		assert.deepEqual(m.rows().map(r => r.dataset.rowId), before);
		assert.equal(m.grid.total, 5);
	});

	/**
	 * A burst of pushes is drawn once.  Setup: spy on the datagrid's setInitialRows, add 10 comments in the same
	 * tick.  Pass: exactly one call, and all of them are in the list afterwards.
	 */
	it("draws a burst of changes once", async() =>
	{
		const m = track(await mount(makeComments(5)));
		const spy = sinon.spy(m.grid, "setInitialRows");
		for(let i = 0; i < 10; i++)
		{
			m.list.upsertComment({...m.list.getComment(1)!, comment_id: 100 + i, comment_starttime: 1000 + i});
		}
		await until(() => m.grid.total === 15);
		assert.equal(spy.callCount, 1);
		assert.equal(m.grid.total, 15);
	});

	/**
	 * A filter shrinks what is drawn, and clearing it brings everything back.  Pass: only comments of account 11
	 * (every third) remain, then all again.
	 */
	it("shows only comments passing the filters", async() =>
	{
		const m = track(await mount(makeComments(30)));
		m.list.store.setFilter("participants", {accounts: [11]});
		await until(() => m.grid.total === 10);
		assert.equal(m.grid.total, 10);
		assert.isTrue(m.rows().every(r => (Number(r.dataset.rowId) - 1) % 3 === 1));
		m.list.store.clearFilters();
		await until(() => m.grid.total === 30);
		assert.equal(m.grid.total, 30);
	});

	/**
	 * Clicking the reply control or the edit bubble announces it, once, with the comment of that row.  Clicking
	 * anywhere else on a tile opens the comment, as the old list did; clicking something with a meaning of its
	 * own inside a tile (a link, a button, a dropdown) announces nothing.
	 */
	it("announces clicks on the controls of a row, and opens on a click elsewhere", async() =>
	{
		const m = track(await mount(makeComments(5)));
		const events : any[] = [];
		m.box.addEventListener("smallpart-comment-action", (e : any) => events.push(e.detail));

		(<HTMLElement>m.row(3)!.querySelector(".commentRetweetBtn")).click();
		(<HTMLElement>m.row(4)!.querySelector(".commentEditBtn")).click();
		(<HTMLElement>m.row(2)!.querySelector(".text")).click();
		assert.equal(events.length, 3, JSON.stringify(events.map(e => e.action)));
		assert.deepEqual(events.map(e => [e.action, e.id]), [["retweet", "3"], ["edit", "4"], ["open", "2"]]);
		assert.equal(events[0].comment.comment_id, 3);

		// something with a meaning of its own: a link inside the tile
		const link = document.createElement("a");
		link.href = "#";
		link.addEventListener("click", e => e.preventDefault());
		m.row(5)!.querySelector(".commentTile")!.append(link);
		link.click();
		assert.equal(events.length, 3, "a click on a link inside the tile does not open the comment");
	});

	/**
	 * The server attaches the old grid's actions to the widget.  Linking them registers one action object for
	 * the whole list, whose click handler runs "open" with no comment - that sent the video to an undefined time
	 * and collapsed it.  Pass: after the list is handed an action set, no action object exists for it and a click
	 * on a tile makes exactly one announcement, from the list itself.
	 */
	it("does not link the server's actions to the whole list", async() =>
	{
		const m = track(await mount(makeComments(3)));
		m.list.id = "comments";
		(<any>m.list).actions = {open: {id: "open", caption: "Open", default: true, singleClick: true}};
		// compared as a boolean: a failing assertion on the object itself makes chai try to print all of it
		assert.isTrue(egw_getAppObjectManager(true, "smallpart").getObjectById("comments") === null,
			"no action object registered for the list");

		const events : any[] = [];
		m.box.addEventListener("smallpart-comment-action", (e : any) => events.push(e.detail.action));
		(<HTMLElement>m.row(1)!.querySelector(".text")).click();
		assert.deepEqual(events, ["open"]);
	});

	/**
	 * Right click on a tile shows the server's actions as a menu: Open, Add, Edit, Retweet, Delete, in their
	 * groups, with Edit and Delete only enabled for comments with the commentOwner class (the author, staff).
	 * Choosing an item runs the action with `[{id, data: comment}]`, what the old grid's rows handed over.
	 *
	 * Setup: the list gets the action set `Ui::get_actions()` sends (onExecute is a function here; the server
	 * sends the name of one); comment 1 is by the user (account 10 in these tests), comment 2 by somebody else.
	 * Pass: the menu has the five items; Edit and Delete are enabled on comment 1 and disabled on comment 2;
	 * choosing Retweet on comment 1 calls the action once with that comment; a right click outside any tile
	 * shows no menu.
	 */
	it("shows the actions as a context menu on a right click", async() =>
	{
		const m = track(await mount(makeComments(3)));
		const calls : any[] = [];
		const onExecute = (action : any, selected : any[]) => calls.push([action.id, selected[0].data.comment_id]);
		m.list.id = "comments";
		(<any>m.list).actions = {
			open: {id: "open", caption: "Open", onExecute, group: 1, default: true, singleClick: true},
			add: {id: "add", caption: "Add", onExecute, group: 1},
			edit: {id: "edit", caption: "Edit", onExecute, group: 2, enableClass: "commentOwner"},
			retweet: {id: "retweet", caption: "Retweet", onExecute, group: 2},
			delete: {id: "delete", caption: "Delete", onExecute, group: 3, enableClass: "commentOwner"}
		};
		const rightClick = (id : number) => m.row(id)!.querySelector(".text")!.dispatchEvent(
			new MouseEvent("contextmenu", {bubbles: true, composed: true, cancelable: true, clientX: 50, clientY: 50}));
		const items = () => (<any>_egw_active_menu)?.children.filter((i : any) => i.id !== "");

		assert.isFalse(rightClick(2), "the browser's own menu is replaced");
		assert.deepEqual(items().map((i : any) => i.id), ["open", "add", "edit", "retweet", "delete"]);
		assert.deepEqual(items().map((i : any) => i.enabled), [true, true, false, true, false], "somebody else's comment");

		rightClick(1);
		assert.deepEqual(items().map((i : any) => i.enabled), [true, true, true, true, true], "own comment");
		items().find((i : any) => i.id === "retweet").onClick(items().find((i : any) => i.id === "retweet"));
		assert.deepEqual(calls, [["retweet", 1]]);

		(<any>_egw_active_menu)?.hide();
		assert.isTrue(m.box.dispatchEvent(new MouseEvent("contextmenu", {bubbles: true, cancelable: true})),
			"outside the list nothing is touched");
	});

	/**
	 * Touch screens without a native long-press `contextmenu` (iOS) get the menu from holding a finger on a tile
	 * for half a second.  Pass:
	 * - holding still for 600 ms shows the menu of that comment (own comment: Edit enabled);
	 * - the click that ends the press does NOT also open the comment, the next one does;
	 * - moving the finger more than a few pixels, or lifting it early, shows nothing (that is a scroll or a tap);
	 * - a mouse button held down is not a long press.
	 */
	it("shows the context menu on a long press with a finger", async() =>
	{
		const m = track(await mount(makeComments(3)));
		const onExecute = () => {};
		// its own id: an action manager outlives the list, and another test gave "comments" five actions
		m.list.id = "comments_longpress";
		(<any>m.list).actions = {
			open: {id: "open", caption: "Open", onExecute, group: 1, default: true},
			edit: {id: "edit", caption: "Edit", onExecute, group: 2, enableClass: "commentOwner"}
		};
		const events : string[] = [];
		m.box.addEventListener("smallpart-comment-action", (e : any) => events.push(e.detail.action));
		const target = () => m.row(1)!.querySelector(".text")!;
		const pointer = (type : string, extra : Record<string, any> = {}) => target().dispatchEvent(new PointerEvent(type,
			{bubbles: true, composed: true, pointerType: "touch", clientX: 100, clientY: 100, ...extra}));
		const items = () => (<any>_egw_active_menu)?.children.filter((i : any) => i.id !== "");
		const wait = (ms : number) => new Promise<void>(resolve => setTimeout(resolve, ms));
		const reset = () => { (<any>_egw_active_menu)?.hide(); };

		reset();
		pointer("pointerdown");
		await wait(650);
		assert.deepEqual(items()?.map((i : any) => [i.id, i.enabled]), [["open", true], ["edit", true]], "held still: menu");
		pointer("pointerup");
		target().dispatchEvent(new MouseEvent("click", {bubbles: true, composed: true}));
		assert.deepEqual(events, [], "the click ending a long press opens nothing");
		target().dispatchEvent(new MouseEvent("click", {bubbles: true, composed: true}));
		assert.deepEqual(events, ["open"], "the next click does");

		reset();
		pointer("pointerdown");
		pointer("pointermove", {clientX: 100, clientY: 140});
		await wait(650);
		assert.isTrue(items() === undefined, "moved: scrolling, no menu");

		pointer("pointerdown");
		await wait(100);
		pointer("pointerup");
		await wait(600);
		assert.isTrue(items() === undefined, "lifted early: a tap, no menu");

		pointer("pointerdown", {pointerType: "mouse"});
		await wait(650);
		assert.isTrue(items() === undefined, "a mouse is not a finger");
	});

	/**
	 * Nothing inside a row listens for clicks: the list does, once.  Setup: record every addEventListener call
	 * while the rows are drawn again from scratch.  Pass: not a single one was added to an edit or reply control
	 * (or to a row), however many rows there are.
	 */
	it("adds no listener to the rows or their controls", async() =>
	{
		const m = track(await mount(makeComments(200)));
		const calls : EventTarget[] = [];
		const original = EventTarget.prototype.addEventListener;
		EventTarget.prototype.addEventListener = function(this : EventTarget, ...args : any[])
		{
			calls.push(this);
			return (<any>original).apply(this, args);
		};
		try
		{
			m.grid.setInitialRows(m.list.store.visible);
			await until(() => m.rows().length > 0);
			await frame();
			await frame();
		}
		finally
		{
			EventTarget.prototype.addEventListener = original;
		}
		const controlOrRow = calls.filter(target => target instanceof HTMLElement &&
			(target.matches(".commentEditBtn, .commentRetweetBtn, [data-row-id]") ||
				target.closest(".commentEditBtn, .commentRetweetBtn")));
		assert.lengthOf(controlOrRow, 0, "listeners added to rows or their controls");
	});

	/**
	 * Scrolling to a comment far outside the drawn rows brings it to the top and highlights it.  Setup: 1000
	 * comments, jump to 700.  Pass: the row exists, is highlighted, and sits within a few px of the top.
	 * Filtered-out comments are not in the list: false.
	 */
	it("scrolls a far comment to the top and highlights it", async() =>
	{
		const m = track(await mount(makeComments(1000)));
		assert.isTrue(m.list.scrollToComment(700));
		await until(() => !!m.row(700) && Math.abs(m.row(700)!.getBoundingClientRect().top - m.grid.shadowRoot.querySelector("#body, [part=body]")?.getBoundingClientRect().top) < 40, 90);
		const row = m.row(700);
		assert.isNotNull(row, "row 700 is drawn");
		assert.isTrue(row!.classList.contains("highlight"));
		const top = row!.getBoundingClientRect().top - m.box.getBoundingClientRect().top;
		assert.isBelow(Math.abs(top), 60, "tile at the top of the list, was " + top + "px");

		m.list.store.setFilter("participants", {accounts: [99]});
		await until(() => m.grid.total === 0);
		assert.isFalse(m.list.scrollToComment(700));
	});

	/**
	 * Following the video scrolls to the comment starting in the current second, once.  Pass: reaching second
	 * 3000 (comment 601) returns it and it is highlighted; the next report in the same second returns nothing.
	 */
	it("follows the video time", async() =>
	{
		const m = track(await mount(makeComments(1000)));
		const reached = m.list.followTime(3000.2);
		assert.deepEqual(reached.map(c => c.comment_id), [601]);
		assert.deepEqual(m.list.followTime(3000.7), []);
		await until(() => !!m.row(601));
		assert.isTrue(m.row(601)!.classList.contains("highlight"));
	});

	/**
	 * The comments of the old list's data shape: the author's name comes from the participants' options, so
	 * a nickname and not the account id is shown with extended info.
	 */
	it("gives rows the number and author the template shows", async() =>
	{
		const m = track(await mount(makeComments(3)));
		(<any>m.list)._nicks = new Map([["11", "Nick Name"]]);
		const data = m.list.getRowData("2");
		assert.equal(data.number, 2);
		assert.equal(data.author, "Nick Name");
		assert.equal(data.comment_value.comment_id, 2);
		assert.deepEqual(data.comment_value.comment, ["text 2"]);
		assert.isNull(m.list.getRowData("404"));
	});

	/**
	 * The category chip shows the label of the comment's main category, then of its sub category, in the
	 * main category's colour.  "free" is the free comment, no category is an empty label.  Setup: the
	 * course's category options are handed in directly (they come from sel_options in the real widget).
	 */
	it("gives rows the label and colour of their category", async() =>
	{
		const m = track(await mount(makeComments(3, i => ({comment_cat: ["5:6", "free", ""][i]}))));
		(<any>m.list)._cats = new Map<string, any>([["5", {label: "Main", color: "#ff0000"}], ["6", {label: "Sub"}]]);
		const withSub = m.list.getRowData("1");
		assert.equal(withSub.cat_label, "Main \u203a Sub");
		assert.equal(withSub.cat_color, "#ff0000");
		assert.equal(m.list.getRowData("2").cat_label, "Free comment");
		assert.equal(m.list.getRowData("3").cat_label, "");
		assert.equal(m.list.getRowData("3").cat_color, "transparent");
	});
});
