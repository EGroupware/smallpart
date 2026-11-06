/**
 * SmallPart comment store
 *
 * @license https://opensource.org/licenses/gpl-license.php GPL - GNU General Public License
 * @package smallpart
 * @subpackage etemplate
 * @link https://www.egroupware.org
 */

import {isCommentOwner} from "../CommentPolicy/CommentPolicy";

/**
 * The part of a comment the store needs.  Anything else on the object (text, replies, history, attachments, ...)
 * is carried along untouched.
 */
export interface StoreComment
{
	comment_id : number | string;
	comment_starttime : number | string;
	comment_stoptime? : number | string;
	account_id? : number | string;
	/** "main" or "main:sub" category id(s), or "free" */
	comment_cat? : string;
	comment_cat_type? : string;
	/** [text, user1, reply1, user2, reply2, ...] */
	comment_added? : Array<string | number>;
	comment_marked? : any[] | null;
	/** PHP DateTime as json: {date: "2026-10-09 12:00:00.000000", ...} */
	comment_updated? : { date : string } | string | null;
	/** space separated css classes: commentOwner, commentMarked, commentAttachments, cat-<id>, ... */
	class? : string;

	[key : string] : any;
}

export type StoreChangeKind = "reset" | "add" | "update" | "remove" | "filter";

export interface StoreChangeDetail
{
	kind : StoreChangeKind;
	/** ids of the comments added / updated / removed, empty for reset and filter */
	ids : Array<number | string>;
}

/** Specification of one filter, see SmallPartCommentStore.setFilter() */
export type StoreFilterSpec =
	{ mode : "all" | "ac" | "lf" } |
	{ mode : "new", since : Date | string | number } |
	{ accounts : Iterable<number | string> } |
	{ cats : string[] } |
	{ from? : Date | string | number | null, to? : Date | string | number | null } |
	{ query : string, all? : boolean } |
	{ enabled : boolean };

/** Names of the filters the store knows, each one narrows the visible comments further (AND) */
export type StoreFilterName =
	"color" | "participants" | "group" | "cats" | "date" | "search" | "marked" | "attachments";

const FILTER_NAMES : StoreFilterName[] = ["color", "participants", "group", "cats", "date", "search", "marked", "attachments"];

/** Category ids marking livestream ("live") comments */
const LIVE_CATS = ["lf", "lfc"];

function num(value : any) : number
{
	const n = Number(value);
	return isNaN(n) ? 0 : n;
}

function catTokens(comment : StoreComment) : string[]
{
	return comment.comment_cat ? String(comment.comment_cat).split(":").filter(Boolean) : [];
}

function updatedValue(comment : StoreComment) : string
{
	const updated : any = comment.comment_updated;
	return String((updated && typeof updated === "object" ? updated.date : updated) ?? "");
}

/**
 * Moment of a PHP date "2026-10-09 12:00:00.000000" (user time, no zone - read as local time like the video's
 * last_updated it is compared with), a Date, or a timestamp
 */
function momentOf(value : Date | string | number) : number
{
	return new Date(typeof value === "string" ? value.replace(" ", "T") : value).getTime();
}

/**
 * Calendar day, "YYYY-MM-DD", of a date string as written (no time zone shift) or of a Date picker value (UTC)
 */
function dayOf(value : Date | string | number) : string
{
	return typeof value === "string" && /^\d{4}-\d{2}-\d{2}/.test(value) ?
		value.slice(0, 10) : new Date(value).toISOString().slice(0, 10);
}

/**
 * The comments of one video: kept sorted, filtered, and followed as the video plays.
 *
 * The store is plain data - no DOM, no widgets.  Whatever shows comments (the list, the slider) subscribes to its
 * "change" event and reads what it needs, so a comment pushed from the server is merged here once instead of every
 * view rebuilding itself from scratch.
 *
 * - Order is start time, then stop time, then comment id, and is kept on every insert and update.
 * - Filters narrow the visible comments; all active filters have to match.  A filter that is not set does not
 *   narrow anything.  Special-category comments are hidden unless `specialCategories` is on, and then they are the
 *   only ones shown.
 * - `advanceTo()` follows video time and names the visible comments whose start second has just been reached.
 * - `batch()` merges the changes made inside it into a single "change" event.
 */
export class SmallPartCommentStore extends EventTarget
{
	/** Current user, used for the commentOwner class */
	public userId : number | string | null = null;
	/** Staff are the owner of every comment */
	public isStaff : boolean = false;

	private _comments : StoreComment[] = [];
	private _byId = new Map<string, StoreComment>();
	private _filters = new Map<StoreFilterName, StoreFilterSpec>();
	private _special : boolean = false;
	private _visible : StoreComment[] | null = null;
	private _visibleIds : Set<string> | null = null;
	/** Text to search per comment, lower case; dropped with the comment object */
	private _text = new WeakMap<StoreComment, string>();
	/** Hook returning extra text (author name, category label, ...) searched when the search covers everything */
	public extraSearchText : ((comment : StoreComment) => string) | null = null;

	private _batchDepth = 0;
	private _pending : StoreChangeDetail | null = null;

	private _fired = new Set<string>();
	private _lastSecond : number | null = null;

	/** Order by start time, then stop time, then comment id */
	static compare(a : StoreComment, b : StoreComment) : number
	{
		return num(a.comment_starttime) - num(b.comment_starttime) ||
			num(a.comment_stoptime) - num(b.comment_stoptime) ||
			num(a.comment_id) - num(b.comment_id);
	}

	/** Every comment, in order */
	get all() : readonly StoreComment[]
	{
		return this._comments;
	}

	get length() : number
	{
		return this._comments.length;
	}

	/** The comments passing all filters, in order.  Same array until something changes. */
	get visible() : readonly StoreComment[]
	{
		if(!this._visible)
		{
			this._visible = this._comments.filter(c => this._passes(c));
		}
		return this._visible;
	}

	/** Ids (as strings) of the comments passing all filters */
	get visibleIds() : ReadonlySet<string>
	{
		if(!this._visibleIds)
		{
			this._visibleIds = new Set(this.visible.map(c => String(c.comment_id)));
		}
		return this._visibleIds;
	}

	get(id : number | string) : StoreComment | undefined
	{
		return this._byId.get(String(id));
	}

	has(id : number | string) : boolean
	{
		return this._byId.has(String(id));
	}

	isVisible(id : number | string) : boolean
	{
		return this.visibleIds.has(String(id));
	}

	/**
	 * Position of a comment among all comments, 1-based, or 0 if it is not in the store.
	 */
	numberOf(id : number | string) : number
	{
		const comment = this.get(id);
		return comment ? this._positionOf(comment) + 1 : 0;
	}

	/**
	 * Replace everything.
	 *
	 * Entries without a comment id are skipped: the server's list starts with an empty entry for the grid header.
	 */
	setComments(comments : Array<StoreComment | any> | null | undefined) : void
	{
		this._comments = [];
		this._byId.clear();
		for(const comment of comments ?? [])
		{
			if(!comment || typeof comment !== "object" || !comment.comment_id)
			{
				continue;
			}
			this._normalize(comment);
			this._byId.set(String(comment.comment_id), comment);
			this._comments.push(comment);
		}
		this._comments.sort(SmallPartCommentStore.compare);
		this.resetCursor();
		this._invalidate();
		this._emit({kind: "reset", ids: []});
	}

	/**
	 * Add a comment, or replace the one with the same id.
	 *
	 * @return "add" or "update"
	 */
	upsert(comment : StoreComment) : "add" | "update" | null
	{
		if(!comment || typeof comment !== "object" || !comment.comment_id)
		{
			return null;
		}
		const key = String(comment.comment_id);
		const existing = this._byId.get(key);
		if(existing)
		{
			this._comments.splice(this._positionOf(existing), 1);
		}
		this._normalize(comment);
		this._byId.set(key, comment);
		this._comments.splice(this._insertionIndex(comment), 0, comment);
		this._invalidate();
		this._emit({kind: existing ? "update" : "add", ids: [comment.comment_id]});
		return existing ? "update" : "add";
	}

	/**
	 * Remove a comment.  An id the store does not hold changes nothing.
	 */
	remove(id : number | string) : boolean
	{
		const existing = this._byId.get(String(id));
		if(!existing)
		{
			return false;
		}
		this._comments.splice(this._positionOf(existing), 1);
		this._byId.delete(String(id));
		this._fired.delete(String(id));
		this._invalidate();
		this._emit({kind: "remove", ids: [id]});
		return true;
	}

	/**
	 * Run several changes, announcing them as one "change" event at the end.
	 */
	batch(fn : () => void) : void
	{
		this._batchDepth++;
		try
		{
			fn();
		}
		finally
		{
			this._batchDepth--;
			if(this._batchDepth === 0 && this._pending)
			{
				const pending = this._pending;
				this._pending = null;
				this.dispatchEvent(new CustomEvent<StoreChangeDetail>("change", {detail: pending}));
			}
		}
	}

	// --- filters ---

	/**
	 * Set (or, with `null`, clear) one filter.
	 *
	 * - color:        `{mode: "all"|"ac"|"lf"}` all / no live comments / only live comments, or
	 *                 `{mode: "new", since}` comments updated after `since`
	 * - participants: `{accounts: [...]}`, only comments of these accounts
	 * - group:        `{accounts: [...]}`, same, kept separate so both can be active.  An empty list matches nothing.
	 * - cats:         `{cats: ["12", "free", ...]}`, comments having any of these main or sub categories
	 * - date:         `{from, to}` comments last updated on a day in the range, both days included
	 * - search:       `{query, all}` case-insensitive text (not a pattern) in the comment and its replies, or in
	 *                 everything `extraSearchText` adds if `all`.  An empty query is no filter.
	 * - marked / attachments: `{enabled: true}`
	 */
	setFilter(name : StoreFilterName, spec : StoreFilterSpec | null) : void
	{
		if(!FILTER_NAMES.includes(name))
		{
			throw new Error("Unknown comment filter '" + name + "'");
		}
		if(spec === null || !this._active(name, spec))
		{
			if(!this._filters.delete(name))
			{
				return;
			}
		}
		else
		{
			this._filters.set(name, spec);
		}
		this._invalidate();
		this._emit({kind: "filter", ids: []});
	}

	getFilter(name : StoreFilterName) : StoreFilterSpec | undefined
	{
		return this._filters.get(name);
	}

	/** Whether any filter narrows the list (special categories are a view, not a filter) */
	get filtered() : boolean
	{
		return this._filters.size > 0;
	}

	clearFilters() : void
	{
		if(!this._filters.size)
		{
			return;
		}
		this._filters.clear();
		this._invalidate();
		this._emit({kind: "filter", ids: []});
	}

	/**
	 * Show the special-category comments instead of the regular ones.
	 */
	get specialCategories() : boolean
	{
		return this._special;
	}

	set specialCategories(on : boolean)
	{
		on = !!on;
		if(on === this._special)
		{
			return;
		}
		this._special = on;
		this._invalidate();
		this._emit({kind: "filter", ids: []});
	}

	// --- following the video ---

	/**
	 * The video is at `time` seconds.
	 *
	 * @return the visible comments starting in this second which have not been returned since the last
	 *         resetCursor(), in order.  Jumping back to earlier than the last call re-arms the comments after it.
	 */
	advanceTo(time : number) : StoreComment[]
	{
		const second = Math.floor(time);
		if(this._lastSecond !== null && second < this._lastSecond)
		{
			// Seeked backwards: what lies ahead of the new position can be reached again
			for(const comment of this._comments)
			{
				if(Math.floor(num(comment.comment_starttime)) >= second)
				{
					this._fired.delete(String(comment.comment_id));
				}
			}
		}
		this._lastSecond = second;

		const reached : StoreComment[] = [];
		// First comment starting at or after this second
		let low = 0, high = this._comments.length;
		while(low < high)
		{
			const mid = (low + high) >> 1;
			if(Math.floor(num(this._comments[mid].comment_starttime)) < second)
			{
				low = mid + 1;
			}
			else
			{
				high = mid;
			}
		}
		for(let i = low; i < this._comments.length && Math.floor(num(this._comments[i].comment_starttime)) === second; i++)
		{
			const comment = this._comments[i];
			const key = String(comment.comment_id);
			if(!this._fired.has(key) && this._passes(comment))
			{
				this._fired.add(key);
				reached.push(comment);
			}
		}
		return reached;
	}

	/** Forget which comments advanceTo() has returned, eg. when playback starts */
	resetCursor() : void
	{
		this._fired.clear();
		this._lastSecond = null;
	}

	// --- internals ---

	private _emit(detail : StoreChangeDetail)
	{
		if(this._batchDepth > 0)
		{
			if(!this._pending)
			{
				this._pending = {kind: detail.kind, ids: [...detail.ids]};
			}
			else
			{
				// Different kinds in one batch: the receiver has to look at everything
				this._pending.kind = this._pending.kind === detail.kind ? detail.kind : "reset";
				this._pending.ids.push(...detail.ids);
			}
			return;
		}
		this.dispatchEvent(new CustomEvent<StoreChangeDetail>("change", {detail}));
	}

	private _invalidate()
	{
		this._visible = null;
		this._visibleIds = null;
	}

	/** Index of a comment we hold, by binary search on the sort order */
	private _indexOf(comment : StoreComment) : number
	{
		let low = 0, high = this._comments.length;
		while(low < high)
		{
			const mid = (low + high) >> 1;
			if(SmallPartCommentStore.compare(this._comments[mid], comment) < 0)
			{
				low = mid + 1;
			}
			else
			{
				high = mid;
			}
		}
		return low;
	}

	private _insertionIndex(comment : StoreComment) : number
	{
		return this._indexOf(comment);
	}

	/**
	 * Index of exactly this comment object.  The binary search finds it by its sort key; if the caller changed
	 * the object in place since it was stored the key no longer matches and it has to be looked for.
	 */
	private _positionOf(comment : StoreComment) : number
	{
		const index = this._indexOf(comment);
		return this._comments[index] === comment ? index : this._comments.indexOf(comment);
	}

	/**
	 * Make sure the class list has what the server puts there for comments it sends itself: commentOwner,
	 * commentMarked and one cat-<id> per category.  Safe to repeat.
	 */
	private _normalize(comment : StoreComment)
	{
		const classes = new Set((comment.class ? String(comment.class) : "").split(/\s+/).filter(Boolean));
		if(isCommentOwner(this.isStaff, comment.account_id, this.userId))
		{
			classes.add("commentOwner");
		}
		if(Array.isArray(comment.comment_marked) ? comment.comment_marked.length : comment.comment_marked)
		{
			classes.add("commentMarked");
		}
		for(const cat of catTokens(comment))
		{
			classes.add("cat-" + cat);
		}
		comment.class = [...classes].join(" ");
	}

	private _active(name : StoreFilterName, spec : StoreFilterSpec) : boolean
	{
		const s : any = spec;
		switch(name)
		{
			case "color":
				return s.mode !== undefined && s.mode !== "all";
			case "participants":
			case "group":
				return s.accounts !== undefined && s.accounts !== null;
			case "cats":
				return Array.isArray(s.cats) && s.cats.length > 0;
			case "date":
				return !!(s.from || s.to);
			case "search":
				return typeof s.query === "string" && s.query !== "";
			case "marked":
			case "attachments":
				return !!s.enabled;
		}
		return false;
	}

	private _passes(comment : StoreComment) : boolean
	{
		const special = comment.comment_cat_type === "sc";
		return special === this._special && this._passesFilters(comment);
	}

	/**
	 * Whether the comment passes the active filters, whether or not the special categories are shown
	 *
	 * @return false if a filter hides it
	 */
	matchesFilters(id : number | string) : boolean
	{
		const comment = this.get(id);
		return !!comment && this._passesFilters(comment);
	}

	private _passesFilters(comment : StoreComment) : boolean
	{
		for(const [name, spec] of this._filters)
		{
			if(!this._matches(name, <any>spec, comment))
			{
				return false;
			}
		}
		return true;
	}

	private _matches(name : StoreFilterName, spec : any, comment : StoreComment) : boolean
	{
		switch(name)
		{
			case "color":
			{
				const live = catTokens(comment).some(cat => LIVE_CATS.includes(cat));
				switch(spec.mode)
				{
					case "ac":
						return !live;
					case "lf":
						return live;
					case "new":
						return momentOf(updatedValue(comment)) > momentOf(spec.since);
				}
				return true;
			}
			case "participants":
			case "group":
			{
				if(!spec._set)
				{
					spec._set = new Set(Array.from(spec.accounts, String));
				}
				if(spec._set.has(String(comment.account_id)))
				{
					return true;
				}
				// A participant is also involved in the comments they replied to: [text, user, reply, user, reply]
				return name === "participants" && (comment.comment_added ?? []).some((v, i) => i % 2 === 1 && spec._set.has(String(v)));
			}
			case "cats":
			{
				const tokens = catTokens(comment);
				return spec.cats.some((cat : string) => tokens.includes(String(cat)));
			}
			case "date":
			{
				// Whole days, compared as written: the server's times have no zone, the pickers' days are UTC
				const day = updatedValue(comment).slice(0, 10);
				return day !== "" && (!spec.from || dayOf(spec.from) <= day) && (!spec.to || day <= dayOf(spec.to));
			}
			case "search":
				return this._searchText(comment, !!spec.all).includes(spec.query.toLowerCase());
			case "marked":
				return String(comment.class).split(" ").includes("commentMarked");
			case "attachments":
				return String(comment.class).split(" ").includes("commentAttachments");
		}
		return true;
	}

	/** Comment text and replies - [text, user, reply, user, reply] has its texts at the even places */
	private _searchText(comment : StoreComment, all : boolean) : string
	{
		let text = this._text.get(comment);
		if(text === undefined)
		{
			text = (comment.comment_added ?? []).filter((_v, i) => i % 2 === 0).join("\n").toLowerCase();
			this._text.set(comment, text);
		}
		return all && this.extraSearchText ? text + "\n" + this.extraSearchText(comment).toLowerCase() : text;
	}
}
