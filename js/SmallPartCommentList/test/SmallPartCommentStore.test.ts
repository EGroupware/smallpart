import {assert} from "@open-wc/testing";
import {SmallPartCommentStore, StoreChangeDetail, StoreComment} from "../SmallPartCommentStore";

/**
 * Contract under test: SmallPartCommentStore keeps one video's comments sorted, filtered and followed along the
 * video, and tells its subscribers what changed.  It is plain data, so every test builds comments by hand and
 * reads the result straight off the store - no DOM, no timers.
 *
 * Environment: none; dates are given with explicit UTC timestamps so the date filter does not depend on the
 * machine's time zone.
 */

let nextId = 1;

function comment(start : number, extra : Record<string, any> = {}) : StoreComment
{
	const id = extra.comment_id ?? nextId++;
	return {
		comment_id: id,
		comment_starttime: start,
		comment_stoptime: start,
		account_id: 10,
		comment_cat: "free",
		comment_cat_type: "",
		comment_added: ["text " + id],
		comment_updated: {date: "2026-10-09 12:00:00.000000"},
		class: "",
		...extra
	};
}

function ids(list : readonly StoreComment[]) : number[]
{
	return list.map(c => Number(c.comment_id));
}

function listen(store : SmallPartCommentStore) : StoreChangeDetail[]
{
	const events : StoreChangeDetail[] = [];
	store.addEventListener("change", (e : Event) => events.push((<CustomEvent<StoreChangeDetail>>e).detail));
	return events;
}

describe("SmallPartCommentStore ordering", () =>
{
	/**
	 * Sort order is start time, then stop time, then comment id.  Pass: the ids come back in exactly that order,
	 * whatever order they were given in.
	 */
	it("sorts by start, stop, then id", () =>
	{
		const store = new SmallPartCommentStore();
		store.setComments([
			comment(30, {comment_id: 1}),
			comment(10, {comment_id: 5, comment_stoptime: 20}),
			comment(10, {comment_id: 4, comment_stoptime: 20}),
			comment(10, {comment_id: 9, comment_stoptime: 12}),
			comment(20, {comment_id: 2})
		]);
		assert.deepEqual(ids(store.all), [9, 4, 5, 2, 1]);
	});

	/**
	 * The server's list starts with an empty entry (the grid header).  Pass: it, and any other entry without a
	 * comment id, is not counted as a comment.
	 */
	it("skips entries that are not comments", () =>
	{
		const store = new SmallPartCommentStore();
		store.setComments([[], null, undefined, {}, comment(5, {comment_id: 3})]);
		assert.equal(store.length, 1);
		assert.deepEqual(ids(store.all), [3]);
	});

	/**
	 * Numbers shown next to comments are positions among ALL comments, not only the visible ones.  Pass: the
	 * number follows the sort order and counts hidden comments; an unknown id is 0.
	 */
	it("numbers comments by their position among all comments", () =>
	{
		const store = new SmallPartCommentStore();
		store.setComments([comment(1, {comment_id: 1}), comment(2, {comment_id: 2, comment_cat: "7"}), comment(3, {comment_id: 3})]);
		store.setFilter("cats", {cats: ["free"]});
		assert.deepEqual(ids(store.visible), [1, 3]);
		assert.equal(store.numberOf(3), 3);
		assert.equal(store.numberOf(99), 0);
	});

	/**
	 * upsert() of a new comment lands at its sorted place, upsert() of an existing one replaces it and moves it
	 * when its start time changed.  Pass: order after each step, the return value, and that there is never a
	 * duplicate.
	 */
	it("inserts new comments in order and moves edited ones", () =>
	{
		const store = new SmallPartCommentStore();
		store.setComments([comment(10, {comment_id: 1}), comment(30, {comment_id: 3})]);
		assert.equal(store.upsert(comment(20, {comment_id: 2})), "add");
		assert.deepEqual(ids(store.all), [1, 2, 3]);
		assert.equal(store.upsert(comment(40, {comment_id: 1})), "update");
		assert.deepEqual(ids(store.all), [2, 3, 1]);
		assert.equal(store.length, 3);
		assert.equal(store.get(1)!.comment_starttime, 40);
	});

	/**
	 * A caller may change the stored object in place and upsert the same object.  Pass: it is moved, not
	 * duplicated or left at its old place.
	 */
	it("copes with the same object changed in place", () =>
	{
		const store = new SmallPartCommentStore();
		const moved = comment(10, {comment_id: 1});
		store.setComments([moved, comment(20, {comment_id: 2}), comment(30, {comment_id: 3})]);
		moved.comment_starttime = 25;
		store.upsert(moved);
		assert.deepEqual(ids(store.all), [2, 1, 3]);
	});

	/**
	 * remove() takes a comment out; an id we do not hold must change nothing.  This guards the old client
	 * behaviour where an unknown id removed the LAST comment (splice(-1, 1)).  Pass: the right comment is gone,
	 * unknown ids return false, leave the list as it was and emit nothing.
	 */
	it("removes only the named comment", () =>
	{
		const store = new SmallPartCommentStore();
		store.setComments([comment(1, {comment_id: 1}), comment(2, {comment_id: 2}), comment(3, {comment_id: 3})]);
		const events = listen(store);
		assert.isTrue(store.remove(2));
		assert.deepEqual(ids(store.all), [1, 3]);
		assert.isFalse(store.remove(99));
		assert.isFalse(store.remove(undefined as any));
		assert.deepEqual(ids(store.all), [1, 3]);
		assert.lengthOf(events, 1);
	});

	/**
	 * Ids arrive as numbers from the content and as strings from a push id ("course:video:comment").  Pass: both
	 * address the same comment.
	 */
	it("treats numeric and string ids alike", () =>
	{
		const store = new SmallPartCommentStore();
		store.setComments([comment(1, {comment_id: 7})]);
		assert.isTrue(store.has("7"));
		assert.equal(store.get("7")!.comment_id, 7);
		assert.isTrue(store.remove("7"));
	});
});

describe("SmallPartCommentStore classes", () =>
{
	/**
	 * The store adds the classes the server adds for the comments it sends: commentOwner for staff and for the
	 * author, commentMarked, one cat-<id> per category.  Pass: classes are present once even when set again,
	 * and a comment pushed without a class gets them too.
	 */
	it("adds owner, marked and category classes without repeating them", () =>
	{
		const store = new SmallPartCommentStore();
		store.userId = 10;
		store.setComments([
			comment(1, {comment_id: 1, comment_cat: "4:9", comment_marked: [{x: 1, y: 2, c: "ff0000"}], class: "commentAttachments"}),
			comment(2, {comment_id: 2, account_id: 11, class: undefined})
		]);
		const first = store.get(1)!.class!.split(" ").sort();
		assert.deepEqual(first, ["cat-4", "cat-9", "commentAttachments", "commentMarked", "commentOwner"]);
		store.upsert(store.get(1)!);
		assert.deepEqual(store.get(1)!.class!.split(" ").sort(), first);
		assert.deepEqual(store.get(2)!.class!.split(" ").sort(), ["cat-free"]);
	});

	it("makes staff the owner of every comment", () =>
	{
		const store = new SmallPartCommentStore();
		store.isStaff = true;
		store.setComments([comment(1, {account_id: 99})]);
		assert.include(store.all[0].class!.split(" "), "commentOwner");
	});
});

describe("SmallPartCommentStore filters", () =>
{
	function fixture() : SmallPartCommentStore
	{
		const store = new SmallPartCommentStore();
		store.setComments([
			comment(1, {comment_id: 1, account_id: 10, comment_cat: "free", comment_added: ["Klarheit der Instruktion"]}),
			comment(2, {comment_id: 2, account_id: 11, comment_cat: "lf", comment_added: ["live one"]}),
			comment(3, {comment_id: 3, account_id: 12, comment_cat: "5:6", comment_added: ["Main text", 11, "reply with Klarheit"],
				comment_marked: [{x: 1, y: 1, c: "ffffff"}], class: "commentAttachments",
				comment_updated: {date: "2026-10-12 08:00:00.000000"}}),
			comment(4, {comment_id: 4, account_id: 10, comment_cat: "lfc", comment_added: ["live comment"],
				comment_updated: {date: "2026-10-20 23:30:00.000000"}})
		]);
		return store;
	}

	/**
	 * Filters start out not narrowing anything.  Pass: everything visible; visibleIds matches visible.
	 */
	it("shows everything without filters", () =>
	{
		const store = fixture();
		assert.deepEqual(ids(store.visible), [1, 2, 3, 4]);
		assert.sameMembers([...store.visibleIds], ["1", "2", "3", "4"]);
		assert.isFalse(store.filtered);
	});

	/**
	 * color: "ac" hides the live categories (lf, lfc), "lf" shows only them, "all" is no filter.
	 */
	it("filters live and non-live comments", () =>
	{
		const store = fixture();
		store.setFilter("color", {mode: "ac"});
		assert.deepEqual(ids(store.visible), [1, 3]);
		store.setFilter("color", {mode: "lf"});
		assert.deepEqual(ids(store.visible), [2, 4]);
		store.setFilter("color", {mode: "all"});
		assert.deepEqual(ids(store.visible), [1, 2, 3, 4]);
		assert.isFalse(store.filtered);
	});

	/**
	 * color "new": comments last updated after the given moment.  Pass: only the two updated after 10 Oct.
	 */
	it("filters comments updated since a moment", () =>
	{
		const store = fixture();
		store.setFilter("color", {mode: "new", since: "2026-10-10 00:00:00.000000".replace(" ", "T")});
		assert.deepEqual(ids(store.visible), [3, 4]);
	});

	/**
	 * participants / group: only comments of the given accounts, ids compared as strings.  An empty list
	 * matches nothing (a group nobody belongs to), which is different from no filter at all.
	 */
	it("filters by account, and an empty account list shows nothing", () =>
	{
		const store = fixture();
		store.setFilter("participants", {accounts: ["10"]});
		assert.deepEqual(ids(store.visible), [1, 4]);
		store.setFilter("group", {accounts: [10, 11]});
		assert.deepEqual(ids(store.visible), [1, 4]);
		store.setFilter("group", {accounts: []});
		assert.deepEqual(ids(store.visible), []);
		store.setFilter("group", null);
		store.setFilter("participants", null);
		assert.deepEqual(ids(store.visible), [1, 2, 3, 4]);
	});

	/**
	 * A participant is also involved in the comments they replied to ("retweeted"), a group member is only
	 * involved in what they wrote.  Setup: comment 3 is by account 12 with a reply of account 11.  Pass:
	 * participants 11 shows comments 2 (written) and 3 (replied to); group 11 shows only 2.
	 */
	it("counts replying as taking part, for participants but not for groups", () =>
	{
		const store = fixture();
		store.setFilter("participants", {accounts: [11]});
		assert.deepEqual(ids(store.visible), [2, 3]);
		store.setFilter("participants", null);
		store.setFilter("group", {accounts: [11]});
		assert.deepEqual(ids(store.visible), [2]);
	});

	/**
	 * matchesFilters() says whether the filters hide a comment, independent of the special-category view - the
	 * slider uses it to hide the tags of filtered comments only.
	 */
	it("tells whether the filters hide a comment, ignoring special categories", () =>
	{
		const store = fixture();
		store.upsert(comment(5, {comment_id: 5, comment_cat_type: "sc", comment_cat: "20", account_id: 10}));
		assert.isFalse(store.isVisible(5));
		assert.isTrue(store.matchesFilters(5));
		store.setFilter("participants", {accounts: [11]});
		assert.isFalse(store.matchesFilters(5));
		assert.isFalse(store.matchesFilters(1));
		assert.isTrue(store.matchesFilters(2));
		assert.isFalse(store.matchesFilters(999));
	});

	/**
	 * cats: a comment matches when its main or sub category is one of the selected ones.
	 */
	it("filters by main or sub category", () =>
	{
		const store = fixture();
		store.setFilter("cats", {cats: ["6"]});
		assert.deepEqual(ids(store.visible), [3]);
		store.setFilter("cats", {cats: ["free", "lfc"]});
		assert.deepEqual(ids(store.visible), [1, 4]);
		store.setFilter("cats", {cats: []});
		assert.isFalse(store.filtered);
	});

	/**
	 * date: last update inside the range, and "to" covers its whole day (the late-evening comment of 20 Oct
	 * is in a range ending on 20 Oct).
	 */
	it("filters by date range including the whole last day", () =>
	{
		const store = fixture();
		store.setFilter("date", {from: "2026-10-10", to: "2026-10-20"});
		assert.deepEqual(ids(store.visible), [3, 4]);
		store.setFilter("date", {from: "2026-10-10", to: "2026-10-19"});
		assert.deepEqual(ids(store.visible), [3]);
		store.setFilter("date", {from: null, to: null});
		assert.isFalse(store.filtered);
	});

	/**
	 * search: case-insensitive text in the comment and its replies - the user ids between replies are not text.
	 * A query with pattern characters is plain text, so it must not throw or match everything.
	 */
	it("searches text and replies, not user ids, and not as a pattern", () =>
	{
		const store = fixture();
		store.setFilter("search", {query: "klarheit"});
		assert.deepEqual(ids(store.visible), [1, 3]);
		store.setFilter("search", {query: "11"});
		assert.deepEqual(ids(store.visible), [], "the account id 11 between text and reply is not searchable text");
		store.setFilter("search", {query: "(.*["});
		assert.deepEqual(ids(store.visible), []);
		store.setFilter("search", {query: ""});
		assert.isFalse(store.filtered);
	});

	/**
	 * search with "all" also looks into what the extraSearchText hook returns (author, category label ...),
	 * a plain search does not.
	 */
	it("searches the extra text only when asked to search everything", () =>
	{
		const store = fixture();
		store.extraSearchText = c => c.account_id === 12 ? "Alice Example" : "";
		store.setFilter("search", {query: "alice"});
		assert.deepEqual(ids(store.visible), []);
		store.setFilter("search", {query: "alice", all: true});
		assert.deepEqual(ids(store.visible), [3]);
	});

	/**
	 * marked / attachments follow the commentMarked / commentAttachments classes.
	 */
	it("filters marked and attachment comments", () =>
	{
		const store = fixture();
		store.setFilter("marked", {enabled: true});
		assert.deepEqual(ids(store.visible), [3]);
		store.setFilter("marked", {enabled: false});
		store.setFilter("attachments", {enabled: true});
		assert.deepEqual(ids(store.visible), [3]);
	});

	/**
	 * All active filters have to match (AND).  Pass: live AND participant 10 leaves only comment 4.
	 */
	it("combines filters with AND", () =>
	{
		const store = fixture();
		store.setFilter("color", {mode: "lf"});
		store.setFilter("participants", {accounts: [10]});
		assert.deepEqual(ids(store.visible), [4]);
		store.clearFilters();
		assert.deepEqual(ids(store.visible), [1, 2, 3, 4]);
	});

	/**
	 * Special-category comments are hidden normally, and when special categories are shown they are the only
	 * ones.  Filters still apply on top.
	 */
	it("hides special-category comments unless they are switched on, and then shows only those", () =>
	{
		const store = fixture();
		store.upsert(comment(5, {comment_id: 5, comment_cat_type: "sc", comment_cat: "20", account_id: 10}));
		assert.deepEqual(ids(store.visible), [1, 2, 3, 4]);
		store.specialCategories = true;
		assert.deepEqual(ids(store.visible), [5]);
		store.setFilter("participants", {accounts: [11]});
		assert.deepEqual(ids(store.visible), []);
		store.specialCategories = false;
		store.setFilter("participants", null);
		assert.deepEqual(ids(store.visible), [1, 2, 3, 4]);
	});

	/**
	 * A new comment is subject to the active filters straight away.  Pass: a pushed comment from a hidden
	 * account is stored but not visible, one from a shown account is.
	 */
	it("applies the active filters to comments added later", () =>
	{
		const store = fixture();
		store.setFilter("participants", {accounts: [10]});
		store.upsert(comment(6, {comment_id: 6, account_id: 11}));
		store.upsert(comment(7, {comment_id: 7, account_id: 10}));
		assert.deepEqual(ids(store.visible), [1, 4, 7]);
		assert.equal(store.length, 6);
		assert.isFalse(store.isVisible(6));
	});

	it("rejects a filter name it does not know", () =>
	{
		assert.throws(() => new SmallPartCommentStore().setFilter(<any>"bogus", {enabled: true}));
	});
});

describe("SmallPartCommentStore change events", () =>
{
	/**
	 * Each kind of change is announced once, with the ids concerned.  Pass: kinds and ids in order; setting a
	 * filter that was not set and clearing nothing emit nothing extra.
	 */
	it("announces reset, add, update, remove and filter", () =>
	{
		const store = new SmallPartCommentStore();
		const events = listen(store);
		store.setComments([comment(1, {comment_id: 1})]);
		store.upsert(comment(2, {comment_id: 2}));
		store.upsert(comment(3, {comment_id: 2}));
		store.remove(2);
		store.setFilter("marked", {enabled: true});
		store.setFilter("marked", null);
		store.setFilter("marked", null);
		store.clearFilters();
		assert.deepEqual(events.map(e => e.kind), ["reset", "add", "update", "remove", "filter", "filter"]);
		assert.deepEqual(events[1].ids, [2]);
	});

	/**
	 * batch() merges what happens inside it into one event so a burst of pushes renders once.  Pass: one event;
	 * the kind is that of the changes if they agree, "reset" ("look at everything") if they do not; the ids are
	 * all of them.
	 */
	it("batches changes into one event", () =>
	{
		const store = new SmallPartCommentStore();
		store.setComments([comment(1, {comment_id: 1})]);
		const events = listen(store);
		store.batch(() =>
		{
			store.upsert(comment(2, {comment_id: 2}));
			store.upsert(comment(3, {comment_id: 3}));
		});
		assert.lengthOf(events, 1);
		assert.equal(events[0].kind, "add");
		assert.deepEqual(events[0].ids, [2, 3]);

		store.batch(() =>
		{
			store.upsert(comment(4, {comment_id: 4}));
			store.remove(1);
		});
		assert.lengthOf(events, 2);
		assert.equal(events[1].kind, "reset");
		assert.sameMembers(events[1].ids.map(Number), [4, 1]);

		store.batch(() => {});
		assert.lengthOf(events, 2, "an empty batch announces nothing");
	});

	/**
	 * The visible list is reused until something changes (views compare by identity), and is a new array after.
	 */
	it("returns the same visible array until a change", () =>
	{
		const store = new SmallPartCommentStore();
		store.setComments([comment(1, {comment_id: 1})]);
		const first = store.visible;
		assert.strictEqual(store.visible, first);
		store.upsert(comment(2, {comment_id: 2}));
		assert.notStrictEqual(store.visible, first);
	});
});

describe("SmallPartCommentStore following the video", () =>
{
	function fixture() : SmallPartCommentStore
	{
		const store = new SmallPartCommentStore();
		store.setComments([
			comment(5, {comment_id: 1}),
			comment(5, {comment_id: 2}),
			comment(9, {comment_id: 3}),
			comment(12, {comment_id: 4})
		]);
		return store;
	}

	/**
	 * Playing steadily across 5 s returns the comments of that second once, even though the video reports its
	 * time several times per second.  Pass: first report of second 5 returns both comments, repeated reports
	 * (5.25, 5.5, 5.75) return nothing, second 9 returns the next one.
	 */
	it("returns the comments of a second once", () =>
	{
		const store = fixture();
		assert.deepEqual(ids(store.advanceTo(4.9)), []);
		assert.deepEqual(ids(store.advanceTo(5.0)), [1, 2]);
		for(const t of [5.25, 5.5, 5.75])
		{
			assert.deepEqual(ids(store.advanceTo(t)), []);
		}
		assert.deepEqual(ids(store.advanceTo(9.1)), [3]);
	});

	/**
	 * Seeking backwards re-arms the comments from the new position on, so playing over them again returns them;
	 * seeking forward past comments does not return the skipped ones.
	 */
	it("re-arms after seeking backwards, not after seeking forward", () =>
	{
		const store = fixture();
		store.advanceTo(5.0);
		assert.deepEqual(ids(store.advanceTo(20)), [], "jumping over 9 and 12 returns nothing");
		assert.deepEqual(ids(store.advanceTo(5.0)), [1, 2], "back at 5 s the comments can be reached again");
	});

	/**
	 * resetCursor() starts over, as when playback is started.
	 */
	it("starts over after resetCursor()", () =>
	{
		const store = fixture();
		assert.deepEqual(ids(store.advanceTo(5.0)), [1, 2]);
		store.resetCursor();
		assert.deepEqual(ids(store.advanceTo(5.0)), [1, 2]);
	});

	/**
	 * Comments hidden by a filter are not followed (the old list skipped hidden rows).
	 */
	it("does not return filtered-out comments", () =>
	{
		const store = fixture();
		store.get(1)!.account_id = 77;
		store.setFilter("participants", {accounts: [10]});
		assert.deepEqual(ids(store.advanceTo(5.0)), [2]);
	});

	/**
	 * A comment added while playing is picked up when its second comes; a removed one is forgotten.
	 */
	it("follows comments added and removed during playback", () =>
	{
		const store = fixture();
		store.advanceTo(3);
		store.upsert(comment(6, {comment_id: 10}));
		store.remove(1);
		assert.deepEqual(ids(store.advanceTo(5.0)), [2]);
		assert.deepEqual(ids(store.advanceTo(6.0)), [10]);
	});
});

describe("SmallPartCommentStore at scale", () =>
{
	/**
	 * Guard against an accidentally quadratic implementation: 5000 comments inserted one by one, filtered,
	 * and followed through the video.  Pass: result is right and the whole thing stays far below a second on
	 * any machine that can run the browser (the old list needed seconds for 900).  The limit is deliberately
	 * generous; the figure itself is printed.
	 */
	it("stays fast with thousands of comments", () =>
	{
		const store = new SmallPartCommentStore();
		const start = performance.now();
		store.batch(() =>
		{
			for(let i = 0; i < 5000; i++)
			{
				store.upsert(comment((i * 7919) % 3600, {comment_id: i + 1, account_id: i % 40, comment_cat: String(i % 9)}));
			}
		});
		store.setFilter("participants", {accounts: [1, 2, 3]});
		store.setFilter("cats", {cats: ["1", "2"]});
		const visible = store.visible.length;
		let reached = 0;
		for(let t = 0; t < 3600; t += 0.25)
		{
			reached += store.advanceTo(t).length;
		}
		const elapsed = performance.now() - start;
		console.log(`SmallPartCommentStore: 5000 comments inserted, filtered and followed in ${elapsed.toFixed(0)} ms`);
		assert.equal(reached, visible, "every visible comment is reached exactly once");
		assert.isBelow(elapsed, 1500);
	});
});
