`smallpart-comment-list` shows the comments of one SmallParT video as a column of tiles. It sits on
`et2-datagrid`, so only the tiles in view exist, and it keeps the comments in a
[`SmallPartCommentStore`](#the-store) that sorts, filters and follows the video.

```xml
<smallpart-comment-list id="comments" template="smallpart.comment.rows"/>
```

```php
// Student\Ui::index() - the list takes its comments from $content['comments']
$content['comments'] = self::_fixComments($bo->listComments($video_id), $bo->isTeacher($course_id));
// ... and the actions of the old grid, which become its context menu
$tpl->setElementAttribute('comments', 'actions', self::get_actions());
```

[Overview](#overview)<br>
[Examples](#examples)<br>
[Content](#content)<br>
[Row template](#row-template)<br>
[What the user can do](#what-the-user-can-do)<br>
[Events](#events)<br>
[Methods](#methods)<br>
[The store](#the-store)<br>
[Filters](#filters)<br>
[Following the video](#following-the-video)<br>
[Live updates](#live-updates)<br>
[Attributes](#attributes)<br>
[Things to know](#things-to-know)

---

## Overview

The list replaced a legacy `<grid id="comments">`. That grid made about 23 widgets per comment and rebuilt all of
them whenever any comment was pushed: with 889 comments a single push blocked the page for 4.8 s and took 10.6 s to
paint. The list draws roughly the 25 tiles in view, and a pushed comment redraws only what changed.

Three rules keep it cheap, and any change to the list or its row template should keep them:

1. **No handler in a row.** The list has one `click`, one `contextmenu` and four pointer listeners for the whole
   list. Controls in a tile are found by class (`commentEditBtn`, `commentRetweetBtn`) and the comment by the row
   the event came through. Never add `onclick` to the row template.
2. **Filtering reads data, not the DOM.** A filter is a value in the store; the visible comments are computed from the
   comments themselves.
3. **Changes are applied once per frame.** Pushes arriving together (a livestream) are drawn together.

The widget does not talk to the video or the edit area. It announces what the user did
([events](#events)) and the owner, `smallpart/js/app.ts`, decides what happens.

## Examples

The list needs the SmallParT app (its JavaScript registers it and its server code supplies the content), so these are
code to copy, not live previews.

### Basic

Put the list in a template and give it the comments. Nothing else is needed to show them: the list loads its row
template, draws the tiles in view and keeps itself in step with its own store.

```xml
<smallpart-comment-list id="comments"/>
```

```php
// the id of the widget is the key of the content
$content['comments'] = self::_fixComments($bo->listComments($video_id), $bo->isTeacher($course_id));
```

Give it a height: it fills the space its parent offers and scrolls inside it. In a flex column that is
`flex: 1 1 auto; min-height: 0` on the parent's child, which the list already sets on itself.

### React to what the user does

The list never opens anything itself. It announces the click and the owner decides.

```ts
const list = this.et2.getWidgetById("comments");
list.addEventListener("smallpart-comment-action", (e : CustomEvent) =>
{
	// action is "open", "edit" or "retweet"
	const {action, comment} = e.detail;
	app.smallpart.student_openComment({id: action}, [{data: comment}]);
});
```

### Right-click menu

The menu is the action set the server attaches to the widget. Edit and Delete are only enabled for comments that have
the `commentOwner` class (the author and staff).

```php
$tpl->setElementAttribute('comments', 'actions', [
	'open' => [
		'caption' => 'Open', 'icon' => 'view', 'default' => true, 'group' => 1,
		'onExecute' => 'javaScript:app.smallpart.student_openComment',
	],
	'edit' => [
		'caption' => 'Edit', 'icon' => 'edit', 'group' => 2, 'enableClass' => 'commentOwner',
		'onExecute' => 'javaScript:app.smallpart.student_openComment',
	],
	'delete' => [
		'caption' => 'Delete', 'icon' => 'delete', 'group' => 3, 'enableClass' => 'commentOwner',
		'onExecute' => 'javaScript:app.smallpart.student_deleteComment',
	],
]);
```

The handler gets `(action, [{id, data: comment}])`.

### Add, change and remove a comment

A comment pushed from the server (or returned by an ajax call) goes in by calling the list, which redraws what is on
screen and nothing else. Several calls in the same moment are drawn together.

```ts
push(pushData)
{
	const list = this.et2.getWidgetById("comments");
	const [course_id, video_id, comment_id] = pushData.id.split(":");
	if (pushData.type === "delete")
	{
		// a delete carries no comment: the id names it
		list.removeComment(comment_id);
	}
	else
	{
		list.upsertComment(pushData.acl);
	}
}
```

### Filter

Filters are values in the store, so the list shows the result without looking at its own DOM.

```ts
const store = this.et2.getWidgetById("comments").store;

store.setFilter("search", {query: "Klarheit", all: true});    // text, not a pattern; `all` adds the author's name
store.setFilter("participants", {accounts: [11, 12]});       // written or replied to by these
store.setFilter("color", {mode: "lf"});                       // only livefeedback comments

store.clearFilters();
```

Filters combine: a comment has to pass all of them. See [Filters](#filters) for the others.

### Follow the video

Scroll to the comment the video has reached while it plays, and stop when it is paused.

```ts
const videobar = this.et2.getWidgetById("video");
videobar.video[0].addEventListener("et2_video.onTimeUpdate." + videobar.id, () =>
{
	if (!videobar.paused())
	{
		list.followTime(videobar.currentTime());
	}
});

// scroll to a comment on request, eg. a click on the video's slider
list.scrollToComment(comment_id);
```

### Another row template

The structure of a tile is a template. To change how a tile looks, make one with the same bindings and name it:

```xml
<smallpart-comment-list id="comments" template="myapp.compact.rows"/>
```

Start from `comment.rows.xet`, which shows every field a row can bind to.

### Show something else with the same comments

Anything that shows comments can read the store and follow its changes, so it does not need a copy of the data.

```ts
const store = this.et2.getWidgetById("comments").store;
const draw = () => myTimeline.draw(store.visible);   // myTimeline: any view of your own
store.addEventListener("change", draw);
draw();
```

## Content

`$content['comments']` is the list `Student\Ui::_fixComments()` makes: an empty first entry (the header row of the old
grid, ignored) followed by the comments. Per comment the list uses:

| field                                                  | used for                                                         |
|--------------------------------------------------------|------------------------------------------------------------------|
| `comment_id`, `comment_starttime`, `comment_stoptime`  | identity and order (start, stop, id)                             |
| `account_id`                                           | author; the name comes from `sel_options['account_id']`          |
| `comment_added`                                        | `[text, user1, reply1, user2, reply2, ...]`                      |
| `comment_cat`, `comment_cat_type`                      | `"main"` or `"main:sub"`, the label comes from `sel_options['catsOptions']`; type `sc` is a special category |
| `comment_color`                                        | colour bar on the tile                                           |
| `comment_marked`, `class`                              | marked-area icon; `commentOwner`, `commentMarked`, `commentAttachments`, `cat-<id>` |
| `comment_is_staff`                                     | staff tag                                                        |
| `comment_updated`                                      | the "new", date and search filters                               |
| `"/apps/smallpart/<c>/<v>/<lid>/comments/<id>/"`       | the attachments the server found                                 |

The store adds the classes the server adds (`commentOwner` for staff and the author, `commentMarked`, one `cat-<id>`
per category), so a comment pushed without a `class` looks like one that came with the page. `is_staff` is read from
the content, the user from `egw.user('account_id')`.

## Row template

The structure of a tile is an ordinary `.xet` file, `smallpart/templates/default/comment.rows.xet`, template id
`smallpart.comment.rows`; the `template` attribute names it and the datagrid reads it the way it reads a nextmatch row
template. The template says what a tile shows and which row fields it binds to - see it there. `getRowData()` is what
builds those fields from a comment.

Notes for editing the template:

- Use `$field` bindings, not `$row_cont[...]`.
- A widget that is only needed for some rows gets `disabled="!$flag"`, as the attachments dropdown does: the other rows
  do not instantiate it.
- `smallpart-cats-select` does not work inside a row (it loads its options when it is created from the template); the
  category is a plain chip for that reason.
- Row styles live in `SmallPartCommentList.styles.ts` and are adopted into the datagrid's shadow root. A stylesheet
  of the page cannot reach rows.
- After editing the `.xet` on a dev instance the browser keeps the old copy (the template URL carries a fixed
  `?download=` number): refetch it with `fetch(url, {cache: 'reload'})` and reload.

## What the user can do

| gesture                                 | result                                                                 |
|-----------------------------------------|------------------------------------------------------------------------|
| click the bubble (author and staff)     | `smallpart-comment-action` `edit`                                       |
| click the reply button                  | `retweet`                                                               |
| click anywhere else on a tile           | `open`, but not on a link, button, dropdown or the attachments          |
| right click                             | the server's actions as a menu                                          |
| long press (touch, 500 ms, no movement) | the same menu                                                           |

**The menu** is built when it is opened from the action definitions the server put on the widget
(`Student\Ui::get_actions()`): Open and Add, Edit and Retweet, Delete, in those groups, with the same menu class the
old popup used. An action with `enableClass` (Edit and Delete: `commentOwner`) is enabled for comments with that class.
Choosing an item runs the action's `onExecute` with `[{id, data: comment}]`, which is what the old rows handed over.
There is no action object per tile.

The widget does **not** link the server's actions to itself (`_link_actions()` is empty). Linking registers one action
object for the whole list whose click runs "open" with no comment; `student_openComment()` then sought the video to
`undefined` and collapsed it. The list handles clicks itself instead.

Browsers that send a `contextmenu` event for a long press (Android) use it; for the others (iOS) the list times the
press itself. The tap that ends a long press does not also open the comment.

## Events

All bubble and are composed.

| event                              | detail                                          | when                                   |
|------------------------------------|-------------------------------------------------|----------------------------------------|
| `smallpart-comment-action`         | `{action: "open" \| "edit" \| "retweet", id, comment}` | a click on a tile, see above           |
| `smallpart-comment-list-ready`     | none                                            | the list was connected; its store holds the comments already |

The context menu does not fire an event: its items run the server's actions directly.

## Methods

| method                                  | purpose                                                                          |
|-----------------------------------------|----------------------------------------------------------------------------------|
| `setComments(list)`                     | replace everything, eg. with the list an ajax call returned                     |
| `upsertComment(comment)`                | add a comment or replace the one with the same id                               |
| `removeComment(id)`                     | remove one; an unknown id changes nothing                                       |
| `getComment(id)`                        | the comment as the store holds it                                               |
| `scrollToComment(id, {highlight})`      | scroll to it (it may be far outside what is drawn) and highlight it; `false` if it is not in the list |
| `highlight(id \| null)`                 | highlight one tile or none                                                      |
| `followTime(seconds)`                   | scroll to the comment starting in this second, once; returns the comments reached |
| `getRowData(id)`                        | what a row binds to                                                              |

`upsertComment` and `removeComment` are not called `upsert` and `remove` on purpose: an element already has a
`remove()` that the framework calls when it tears a widget down.

`scrollToComment` asks the virtualizer for the tile twice, a frame apart: the first call makes it draw a tile far
away, the second puts it at the top. (`Et2Datagrid.focusRowById()` only scrolls tiles that are already drawn.)

## The store

`list.store` is a `SmallPartCommentStore`, plain data with no DOM that anything showing comments can subscribe to:

```ts
list.store.addEventListener("change", e => e.detail);   // {kind: "reset"|"add"|"update"|"remove"|"filter", ids}
list.store.all;          // every comment, in order
list.store.visible;      // the ones passing the filters; the same array until something changes
list.store.visibleIds;   // Set of ids as strings
list.store.numberOf(id); // 1-based position among all comments
list.store.batch(() => { ... });   // one "change" event for what happens inside
```

- Order is start time, then stop time, then comment id, kept on every insert and update.
- `userId` and `isStaff` decide who gets `commentOwner`. The list sets them from the page.
- `extraSearchText` is a hook returning more text (the author's name) searched when "search all" is on.
- `app.ts` listens to `change` to rebuild the slider tags, the staff slider and the participants filter once per frame.

## Filters

`store.setFilter(name, spec)` sets one filter, `null` (or an empty spec) clears it, `clearFilters()` clears all.
All active filters have to match (AND).

| name           | spec                              | shows                                                           |
|----------------|-----------------------------------|-----------------------------------------------------------------|
| `color`        | `{mode: "ac" \| "lf" \| "all"}`   | no live comments / only live comments (categories `lf`, `lfc`)  |
|                | `{mode: "new", since}`            | comments updated after `since`                                  |
| `participants` | `{accounts: [...]}`               | comments written **or replied to** by these accounts            |
| `group`        | `{accounts: [...]}`               | comments written by these accounts; an empty list shows nothing |
| `cats`         | `{cats: ["12", "free", ...]}`     | comments with one of these main or sub categories               |
| `date`         | `{from, to}`                      | last updated on a day in the range, both days included          |
| `search`       | `{query, all}`                    | text in the comment and its replies, case-insensitive, **not** a pattern; `all` adds `extraSearchText` |
| `marked`, `attachments` | `{enabled: true}`        | comments with marked areas / attachments                        |

`store.specialCategories = true` shows the special-category comments **instead of** the regular ones (they are hidden
otherwise). `store.matchesFilters(id)` says whether the filters (not the special view) hide a comment, which is what the
slider uses to hide tags. Dates are compared as written, as calendar days, so the time zone of the machine does not
matter.

## Following the video

`store.advanceTo(seconds)` returns the visible comments starting in that second that it has not returned yet, in order.
A seek backwards makes the comments from the new position on available again; `resetCursor()` starts over (the app calls
it when playback starts). `list.followTime()` is `advanceTo()` plus `scrollToComment()` of the last one. The app calls it
from the video's time-update event while the video is not paused.

## Live updates

`app.ts` `pushComment()` calls `upsertComment()` for `add`, `update`, `edit` and `retweet`, and `removeComment()` for
`delete`. A delete push carries no comment, so the id in the push id (`course:video:comment`) names it. The list
redraws once per frame: when only the content of comments changed it refreshes those rows, otherwise it gives the
datagrid the new list. `student_updateComments()` (the ajax replies) calls `setComments()`.

## Attributes

| attribute / property | type    | default                  | purpose                                                    |
|----------------------|---------|--------------------------|------------------------------------------------------------|
| `template`           | string  | `smallpart.comment.rows` | the row template                                           |
| `extendedInfo`       | boolean | `false`                  | wide info column with number, author and Start/End labels; the `extended` attribute of the datagrid, so it is CSS only and redraws nothing |
| `emptyStateText`     | string  | "No comments"            | shown when the list is empty                               |
| `value`              | array   | from the content         | the comments; setting it replaces them                     |

The widget is display-only: `getValue()` is `null` and it never makes a form dirty.

## Things to know

- The tests run with `npm run jstest -- --group smallpart`.
- Live checks need a tab that is actually rendering: a background tab runs no animation frames, so scrolling and
  drawing do not happen there and its timers are throttled.
- The datagrid re-renders all of its drawn rows when one is refreshed (about 40 rows, about 16 ms); that does not grow with
  the number of comments.
- The staff slider (`et2_widget_videooverlay_slider_controller.ts`) and the slider tags (`et2_widget_videobar.ts`), which `app.ts`
  rebuilds when the store changes, have their own tests in `smallpart/js/test/`. Not covered by automated tests: touch with a real
  finger, and one user's push reaching another user's session.
- The timeline strip (one canvas for all comments instead of a tag each) is not built; see
  `doc/ai/projects/smallpart-comment-list.md`.
