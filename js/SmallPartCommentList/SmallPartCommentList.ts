/**
 * SmallPart comment list widget
 *
 * @license https://opensource.org/licenses/gpl-license.php GPL - GNU General Public License
 * @package smallpart
 * @subpackage etemplate
 * @link https://www.egroupware.org
 */

import {css, html, LitElement, PropertyValues} from "lit";
import {customElement} from "lit/decorators/custom-element.js";
import {property} from "lit/decorators/property.js";
import {state} from "lit/decorators/state.js";
import {virtualizerRef} from "@lit-labs/virtualizer/virtualize.js";
import {egwMenu} from "../../../api/js/egw_action/egw_menu";
import {Et2Widget} from "../../../api/js/etemplate/Et2Widget/Et2Widget";
import {Et2Datagrid} from "../../../api/js/etemplate/Et2Datagrid/Et2Datagrid";
import "../../../api/js/etemplate/Et2Datagrid/Et2Datagrid";
import {Et2RowProvider} from "../../../api/js/etemplate/Et2Datagrid/Et2RowProvider";
import type {
	Et2DatagridColumn,
	Et2DatagridDataProvider,
	Et2DatagridRowCustomizeContext,
	Et2DatagridTemplateData
} from "../../../api/js/etemplate/Et2Datagrid/Et2Datagrid.types";
import {SmallPartCommentStore, StoreChangeDetail, StoreComment} from "./SmallPartCommentStore";
import {isActionEnabled} from "../CommentPolicy/CommentPolicy";
import styles from "./SmallPartCommentList.styles";
import "../SmallPartComment";

/** What the list tells its owner, see SmallPartCommentList */
export interface CommentListActionDetail
{
	action : "open" | "edit" | "retweet";
	id : string;
	comment : StoreComment;
}

/**
 * The comments of one video as a single column of tiles.
 *
 * ```xml
 * <smallpart-comment-list id="comments"/>
 * ```
 *
 * with the comments (as the server's `Student\Ui::_fixComments()` makes them) in `$content['comments']`.
 *
 * The widget keeps the comments in a SmallPartCommentStore (`list.store`): sorted, filtered, and followed along the
 * video.  Rows are drawn by `et2-datagrid`, so only the tiles in view exist; a comment pushed from the server goes
 * through `upsertComment()` / `removeComment()` and redraws what is on screen, not every comment.
 *
 * It does not talk to the video or the edit form.  What the user does is announced by a bubbling
 * `smallpart-comment-action` event whose detail is `{action: "open" | "edit" | "retweet", id, comment}`, and the
 * owner decides what to do.  There is ONE click listener for the whole list: the tiles contain no click handlers,
 * the controls in them are found by class (`commentEditBtn`, `commentRetweetBtn`).  A click on a tile anywhere
 * else - not on a link, button or dropdown - opens the comment.
 *
 * @summary The comments of a video as tiles
 * @event {CustomEvent<{action: "open" | "edit" | "retweet", id: string, comment: object}>} smallpart-comment-action - The user did something to a comment: clicked its edit bubble (`edit`), its reply button (`retweet`) or the tile itself (`open`). The detail names the comment by id and carries the comment as the list holds it. Bubbles and is composed; the owner of the list decides what happens. The context menu does not use it, its items run their actions directly.
 * @event smallpart-comment-list-ready - The list was connected to the page. Its store already holds the comments, so whatever shows them elsewhere (the video's slider) can subscribe to `list.store` now. Bubbles and is composed.
 * @category display
 * @app SmallParT
 */
@customElement("smallpart-comment-list")
export class SmallPartCommentList extends Et2Widget(LitElement)
{
	static get styles()
	{
		return [
			...(super.styles || []),
			css`
				:host {
					display: flex;
					flex-direction: column;
					flex: 1 1 auto;
					min-height: 0;
					min-width: 0;
				}

				et2-datagrid {
					flex: 1 1 auto;
					min-height: 0;
					/* the tiles stand out from it */
					background-color: var(--sl-color-neutral-100);
				}
			`
		];
	}

	/** The comments, as the server sends them (the first entry of that list is an empty one, it is ignored) */
	@property({type: Object})
	value : any[] = [];

	/** Name of the template (a .xet) giving the structure of a comment's tile */
	@property({type: String})
	template : string = "smallpart.comment.rows";

	/** Show the number and author of each comment */
	@property({type: Boolean, attribute: "extended-info"})
	extendedInfo : boolean = false;

	/** Shown when there is no comment to show */
	@property({type: String, attribute: "empty-state-text"})
	emptyStateText : string = "";

	/** Id of the comment drawn as highlighted, the one the video is at or the user opened */
	private _highlightId : string | null = null;

	readonly store = new SmallPartCommentStore();

	private _rowProvider : Et2RowProvider;
	private _nicks : Map<string, string> | null = null;

	@state()
	private _templateData : Et2DatagridTemplateData | null = null;

	@state()
	private _columns : Et2DatagridColumn[] = [];

	@state()
	private _templateLoading : boolean = true;

	/** Ids of the comments the datagrid was last given, in order */
	private _shown : string[] = [];
	private _pendingIds = new Set<string>();
	private _pendingKinds = new Set<string>();
	private _syncFrame : number = 0;

	constructor()
	{
		super();
		this._rowProvider = new Et2RowProvider(<any>this);
		this._onStoreChange = this._onStoreChange.bind(this);
		this._onClick = this._onClick.bind(this);
		this._onContextMenu = this._onContextMenu.bind(this);
		this._onPointerDown = this._onPointerDown.bind(this);
		this._cancelLongPress = this._cancelLongPress.bind(this);
	}

	private get _datagrid() : Et2Datagrid | null
	{
		return this.shadowRoot?.querySelector("et2-datagrid") as Et2Datagrid | null;
	}

	/** Pick the comments up out of the content under our id */
	transformAttributes(attrs)
	{
		if(typeof attrs.value === "undefined" || attrs.value === null || attrs.value === "")
		{
			const content = this.getArrayMgr("content")?.getEntry(attrs.id || "comments");
			if(Array.isArray(content))
			{
				attrs.value = content;
			}
		}
		super.transformAttributes(attrs);
		// The owner reads comments from the store as soon as the widget exists, not once it is drawn
		if(Array.isArray(attrs.value))
		{
			this._loadValue(attrs.value);
		}
	}

	/** Put the comments of `value` in the store, unless that is what it holds already */
	private _loadValue(value : any[])
	{
		if(value === this._loadedValue)
		{
			return;
		}
		this._loadedValue = value;
		this.setComments(value);
	}

	private _loadedValue : any[] | null = null;

	connectedCallback()
	{
		super.connectedCallback();
		this.store.addEventListener("change", this._onStoreChange);
		this.addEventListener("click", this._onClick);
		this.addEventListener("contextmenu", this._onContextMenu);
		this.addEventListener("pointerdown", this._onPointerDown);
		this.addEventListener("pointermove", this._onPointerMove);
		this.addEventListener("pointerup", this._cancelLongPress);
		this.addEventListener("pointercancel", this._cancelLongPress);
		// Whoever shows the comments elsewhere (the video's slider) wants to know the list is there
		this.dispatchEvent(new CustomEvent("smallpart-comment-list-ready", {bubbles: true, composed: true}));
	}

	willUpdate(changed : PropertyValues)
	{
		super.willUpdate?.(changed);
		if(changed.has("value") && Array.isArray(this.value))
		{
			this._loadValue(this.value);
		}
	}

	disconnectedCallback()
	{
		super.disconnectedCallback();
		this.store.removeEventListener("change", this._onStoreChange);
		this.removeEventListener("click", this._onClick);
		this.removeEventListener("contextmenu", this._onContextMenu);
		this.removeEventListener("pointerdown", this._onPointerDown);
		this.removeEventListener("pointermove", this._onPointerMove);
		this.removeEventListener("pointerup", this._cancelLongPress);
		this.removeEventListener("pointercancel", this._cancelLongPress);
		this._cancelLongPress();
		cancelAnimationFrame(this._syncFrame);
		this._syncFrame = 0;
	}

	/**
	 * The server attaches the old grid's actions (open, add, edit, retweet, delete) to the widget with this id.
	 * Linking them would register one action object for the whole list, whose click handler runs the default
	 * action ("open") with no comment - there are no action objects for the tiles.  The list handles what a click
	 * on a tile means itself, see _onClick().
	 */
	protected _link_actions(_actions)
	{
	}

	/** A display widget: it never contributes to a submit */
	isDirty() : boolean
	{
		return false;
	}

	getValue() : null
	{
		return null;
	}

	getDOMNode(_sender? : any) : HTMLElement
	{
		return this;
	}

	// --- the comments ---

	/** Replace all comments, eg. with the list an ajax call returned */
	setComments(comments : any[] | null | undefined)
	{
		this._configureStore();
		this.store.setComments(comments);
	}

	upsertComment(comment : StoreComment)
	{
		this._configureStore();
		return this.store.upsert(comment);
	}

	removeComment(id : number | string)
	{
		return this.store.remove(id);
	}

	/** The comment as the store holds it, or undefined */
	getComment(id : number | string) : StoreComment | undefined
	{
		return this.store.get(id);
	}

	// --- showing and following ---

	/**
	 * Scroll a comment into view and highlight it.
	 *
	 * The comment may be far outside what is drawn: the first scroll makes the datagrid draw it, the second one,
	 * a frame later, puts it at the top.
	 *
	 * @return false if the comment is not in the list (not there, or filtered out)
	 */
	scrollToComment(id : number | string, options : { highlight? : boolean } = {}) : boolean
	{
		const key = String(id);
		const index = this._shown.indexOf(key);
		if(options.highlight !== false)
		{
			this.highlight(key);
		}
		if(index < 0)
		{
			return false;
		}
		const scroll = () => (<any>this._datagrid)?._rowsBody?.[virtualizerRef]?.element(index)?.scrollIntoView({block: "start"});
		scroll();
		requestAnimationFrame(scroll);
		return true;
	}

	/** Highlight one comment (or none, with null) */
	highlight(id : number | string | null)
	{
		this._highlightId = id === null ? null : String(id);
		this._datagrid?.shadowRoot?.querySelectorAll<HTMLElement>("[data-row-id]").forEach(row =>
		{
			row.classList.toggle("highlight", row.dataset.rowId === this._highlightId);
		});
	}

	/**
	 * The video is at `time` seconds: scroll to the comment starting there, if there is one.
	 *
	 * @return the comments that start in this second
	 */
	followTime(time : number) : StoreComment[]
	{
		const reached = this.store.advanceTo(time);
		if(reached.length)
		{
			this.scrollToComment(reached[reached.length - 1].comment_id);
		}
		return reached;
	}

	// --- datagrid plumbing ---

	/**
	 * What the datagrid draws for a comment: the comment, plus what the row template shows that is not in it.
	 */
	getRowData(rowId : string) : any
	{
		const comment = this.store.get(rowId);
		if(!comment)
		{
			return null;
		}
		const classes = String(comment.class ?? "").split(" ").filter(Boolean);
		classes.push("commentColor" + (comment.comment_color ?? ""), "commentID" + comment.comment_id,
			"smallpart-category-type-" + (comment.comment_cat_type ?? ""));
		if(this._highlightId === String(comment.comment_id))
		{
			classes.push("highlight");
		}
		return {
			...comment,
			id: String(comment.comment_id),
			row_class: classes.join(" "),
			number: this.store.numberOf(comment.comment_id),
			author: this._nick(comment.account_id),
			...this._category(comment.comment_cat),
			comment_marked_flag: classes.includes("commentMarked"),
			has_attachments: classes.includes("commentAttachments"),
			// the files of the comment, listed by the server under the comment's directory
			attachments_list: comment[`/apps/smallpart/${comment.course_id}/${comment.video_id}/${comment.account_lid}/comments/${comment.comment_id}/`],
			comment_value: {
				course_id: comment.course_id,
				video_id: comment.video_id,
				comment_id: comment.comment_id,
				comment: comment.comment_added
			}
		};
	}

	/**
	 * Label and colour of a comment's category, "main" or "main:sub", from the course's category options
	 */
	private _category(comment_cat : string | undefined | null) : { cat_label : string, cat_color : string }
	{
		if(!this._cats)
		{
			this._cats = new Map();
			for(const option of this.getArrayMgr("sel_options")?.getEntry("catsOptions") ?? [])
			{
				this._cats.set(String(option.value), option);
			}
		}
		const tokens = comment_cat ? String(comment_cat).split(":").filter(Boolean) : [];
		const options = tokens.map(token => this._cats!.get(token));
		return {
			cat_label: tokens.map((token, i) => options[i]?.label ?? (token === "free" ? this.egw().lang("Free comment") : token)).join(" \u203a "),
			cat_color: options[0]?.color ?? "transparent"
		};
	}

	private _cats : Map<string, any> | null = null;

	private _nick(account_id : any) : string
	{
		if(!this._nicks)
		{
			this._nicks = new Map();
			for(const option of this.getArrayMgr("sel_options")?.getEntry("account_id") ?? [])
			{
				this._nicks.set(String(option.value), option.label);
			}
		}
		return this._nicks.get(String(account_id)) ?? "#" + account_id;
	}

	private readonly _provider : Et2DatagridDataProvider = {
		fetchPage: async(start : number, pageSize : number) => ({
			rows: this._shown.slice(start, start + pageSize).map(id => ({id})),
			total: this._shown.length
		}),
		getRowData: (rowId : string) => this.getRowData(rowId),
		getQuerySignature: () => "smallpart-comments",
		normalizeRowId: (rowId : string | number) => String(rowId),
		toProviderRowId: (rowId : string) => rowId,
		refresh: async(rowIds : string[]) => ({
			rows: rowIds.filter(id => this.store.has(id)).map(id => ({id})),
			removedRowIds: rowIds.filter(id => !this.store.has(id))
		}),
		// @ts-ignore not in the interface, read by Et2Datagrid._rowIdFor()
		rowIdForData: (row : any) => String(row.comment_id ?? row.id)
	};

	/** The store needs to know who is looking, to give a user's own comments their commentOwner class */
	private _configureStore()
	{
		this.store.userId = this.egw().user("account_id");
		this.store.isStaff = !!this.getArrayMgr("content")?.getEntry("is_staff");
		this.store.extraSearchText = (comment) => this._nick(comment.account_id);
	}

	/**
	 * Something in the store changed.  Several changes arriving together (a burst of pushes) are drawn once.
	 */
	private _onStoreChange(event : Event)
	{
		const detail = (<CustomEvent<StoreChangeDetail>>event).detail;
		this._pendingKinds.add(detail.kind);
		detail.ids.forEach(id => this._pendingIds.add(String(id)));
		if(!this._syncFrame)
		{
			this._syncFrame = requestAnimationFrame(() =>
			{
				this._syncFrame = 0;
				this._syncDatagrid();
			});
		}
	}

	/**
	 * Bring the datagrid in line with the store.
	 *
	 * Same comments in the same places and only some of them edited: just those rows are redrawn.  Anything else
	 * (a comment came or went, the order or the filters changed) gives the datagrid the new list.
	 */
	private _syncDatagrid()
	{
		const kinds = this._pendingKinds, changed = [...this._pendingIds];
		this._pendingKinds = new Set();
		this._pendingIds = new Set();

		const grid = this._datagrid;
		if(!grid || !this._templateData || typeof grid.setInitialRows !== "function")
		{
			return;
		}
		const visible = this.store.visible;
		const ids = visible.map(c => String(c.comment_id));
		const same = ids.length === this._shown.length && ids.every((id, i) => id === this._shown[i]);
		this._shown = ids;
		if(same)
		{
			if(kinds.size === 1 && kinds.has("update"))
			{
				void grid.refresh(changed.filter(id => ids.includes(id)), "update");
			}
			else if(kinds.has("reset"))
			{
				this._setRows(grid, visible);
			}
			return;
		}
		this._setRows(grid, visible);
	}

	private _setRows(grid : Et2Datagrid, visible : readonly StoreComment[])
	{
		grid.setInitialRows(<any[]>visible);
		grid.total = visible.length;
	}

	// --- user actions ---

	/**
	 * The one click listener of the list.  The click came through the datagrid's shadow DOM, so the path says
	 * which control and which row it hit.
	 */
	private _onClick(event : MouseEvent)
	{
		if(performance.now() < this._swallowClickUntil)
		{
			// The finger lifted after a long press, which showed the menu
			this._swallowClickUntil = 0;
			return;
		}
		let action : "open" | "edit" | "retweet" | null = null;
		let interactive = false;
		let id : string | null = null;
		for(const node of event.composedPath())
		{
			if(!(node instanceof HTMLElement))
			{
				continue;
			}
			if(!action)
			{
				if(node.classList.contains("commentEditBtn"))
				{
					action = "edit";
				}
				else if(node.classList.contains("commentRetweetBtn"))
				{
					action = "retweet";
				}
				else if(node.matches("a, button, et2-dropdown, et2-button-icon, et2-vfs-upload"))
				{
					// Something with a meaning of its own: attachments, links
					interactive = true;
				}
			}
			if(node.dataset?.rowId !== undefined)
			{
				id = node.dataset.rowId;
				break;
			}
		}
		if(!action && !interactive)
		{
			action = "open";
		}
		const comment = action && id !== null ? this.store.get(id) : undefined;
		if(!action || !comment)
		{
			return;
		}
		this.dispatchEvent(new CustomEvent<CommentListActionDetail>("smallpart-comment-action", {
			bubbles: true,
			composed: true,
			detail: {action, id: String(id), comment}
		}));
	}

	/**
	 * Right click on a tile: the menu of its comment, see _showContextMenu()
	 */
	private _onContextMenu(event : MouseEvent)
	{
		const id = this._rowIdFromEvent(event);
		// The browser's own long press on a touch screen ends here too, it must not show a second menu
		this._cancelLongPress();
		if(id !== null && this._showContextMenu(id, event.pageX, event.pageY))
		{
			event.preventDefault();
			event.stopPropagation();
		}
	}

	/**
	 * Touch screens: holding a finger on a tile for half a second shows the same menu, as the old rows did.
	 *
	 * Browsers that send a `contextmenu` event for a long press (Android) get the menu from _onContextMenu(); this
	 * is for the ones that do not (iOS).  Moving the finger - scrolling - or lifting it first cancels the press.
	 * The tap that ends a long press must not also open the comment, see _onClick().
	 */
	private _onPointerDown(event : PointerEvent)
	{
		this._cancelLongPress();
		if(event.pointerType !== "touch")
		{
			return;
		}
		const id = this._rowIdFromEvent(event);
		if(id === null)
		{
			return;
		}
		this._longPress = {
			x: event.clientX,
			y: event.clientY,
			timer: window.setTimeout(() =>
			{
				const press = this._longPress;
				this._longPress = null;
				if(press && this._showContextMenu(id, event.pageX, event.pageY))
				{
					this._swallowClickUntil = performance.now() + 700;
				}
			}, SmallPartCommentList.LONG_PRESS_MS)
		};
	}

	private _onPointerMove = (event : PointerEvent) =>
	{
		const press = this._longPress;
		if(press && Math.hypot(event.clientX - press.x, event.clientY - press.y) > SmallPartCommentList.LONG_PRESS_SLOP)
		{
			this._cancelLongPress();
		}
	};

	private _cancelLongPress()
	{
		if(this._longPress)
		{
			window.clearTimeout(this._longPress.timer);
			this._longPress = null;
		}
	}

	/** How long a finger has to stay on a tile, and how far it may wander, to count as a long press */
	static readonly LONG_PRESS_MS = 500;
	static readonly LONG_PRESS_SLOP = 10;
	private _longPress : { x : number, y : number, timer : number } | null = null;
	private _swallowClickUntil : number = 0;

	/**
	 * The server's actions (open, add, edit, retweet, delete) as a menu for one comment.
	 *
	 * Built on demand from the widget's action definitions - no action object per tile.  The actions get what
	 * they always got: `[{id, data: comment}]`.  An action with an `enableClass` is only enabled for comments that
	 * have one of those classes (commentOwner: the author, and staff).
	 *
	 * @return false if there is no such comment or the server sent no actions
	 */
	private _showContextMenu(id : string, pageX : number, pageY : number) : boolean
	{
		const actions : any[] = (<any>this)._actionManager?.children ?? [];
		const comment = this.store.get(id);
		if(!actions.length || !comment)
		{
			return false;
		}

		const selected = [{id: String(id), data: comment}];
		const classes = String(comment.class ?? "").split(" ");
		const menu = new egwMenu();
		let group : number | null = null;
		for(const action of actions)
		{
			const enableClass = action.data?.enableClass;
			const enabled = isActionEnabled(enableClass, classes);
			if(!enabled && action.hideOnDisabled)
			{
				continue;
			}
			if(group !== null && action.group !== group)
			{
				menu.addItem("", "-", "", undefined, undefined);
			}
			group = action.group;
			const item = menu.addItem(action.id, action.caption, action.iconUrl, undefined, action.color);
			item.default = !!action.default;
			if(enabled)
			{
				item.set_onClick(() =>
				{
					action.execute(selected);
				});
			}
			else
			{
				item.set_enabled(false);
			}
		}
		menu.showAt(pageX, pageY, true);
		return true;
	}

	/** Id of the comment of the row an event came through, or null */
	private _rowIdFromEvent(event : Event) : string | null
	{
		for(const node of event.composedPath())
		{
			if(node instanceof HTMLElement && node.dataset?.rowId !== undefined)
			{
				return node.dataset.rowId;
			}
		}
		return null;
	}

	// --- setup ---

	async firstUpdated(changed : PropertyValues)
	{
		super.firstUpdated(changed);
		this._configureStore();

		try
		{
			const templateData = await this._rowProvider.fromTemplate(this.template);
			this._templateData = templateData;
			this._columns = templateData?.columns || [];
		}
		catch(e)
		{
			this.egw().debug("error", "SmallPartCommentList: could not load its row template", e);
		}
		finally
		{
			this._templateLoading = false;
		}
		if(!this._templateData)
		{
			return;
		}
		await this.updateComplete;
		const visible = this.store.visible;
		this._shown = visible.map(c => String(c.comment_id));
		if(this._datagrid)
		{
			this._setRows(this._datagrid, visible);
		}
	}

	/** Every realized row: the highlighted one is drawn as such */
	private _customizeRow = (context : Et2DatagridRowCustomizeContext) =>
	{
		context.rowElement.classList.toggle("highlight", context.rowElement.dataset.rowId === this._highlightId);
	};

	render()
	{
		return html`
            <et2-datagrid
                    part="grid"
                    ._parent=${this}
                    .columns=${this._columns}
                    .templateData=${this._templateData}
                    .dataProvider=${this._provider}
                    .rowStylesheets=${[styles.styleSheet!]}
                    .rowCustomizer=${this._customizeRow}
                    .configurationLoading=${this._templateLoading}
                    .noColumnSelection=${true}
                    .noColumnPersistence=${true}
                    .noVisibleHeader=${true}
                    ?extended=${this.extendedInfo}
                    empty-state-text=${this.emptyStateText || this.egw().lang("No comments")}
                    row-id-field="comment_id"
                    selection-mode="none"
                    auto-activate-first-row="false"
            ></et2-datagrid>
		`;
	}
}
