import {css} from "lit";

/**
 * Row styles for smallpart-comment-list.  Adopted into the Et2Datagrid shadowRoot, where the rows live - the
 * page's own stylesheet cannot reach them.
 *
 * A comment keeps the layout of the grid it replaced: an info column on the left (narrow, or wide with "Extended
 * info", which is the `extended` attribute of the datagrid), separated by a line from the text on the right.
 */
export default css`
	tr.commentBox {
		/* a long press shows our menu, not the system's */
		-webkit-touch-callout: none;
		box-sizing: border-box;
		margin: 0 var(--sl-spacing-x-small) var(--sl-spacing-x-small) 0;
		border: 1px solid var(--sl-panel-border-color);
		border-left-width: 0.4em;
		border-radius: var(--sl-border-radius-medium);
		background-color: var(--sl-color-neutral-0);
		box-shadow: var(--sl-shadow-medium);
		overflow: hidden;
	}

	tr.commentBox.commentColorff0000 { border-left-color: #ff7972; }
	tr.commentBox.commentColor00ff00 { border-left-color: #61ff61; }
	tr.commentBox.commentColorffff00 { border-left-color: #ffc909; }
	tr.commentBox.highlight { background-color: var(--sl-color-primary-100); }

	.commentTile {
		width: 100%;
		min-width: 0;
		align-items: stretch;
	}

	/* Info column: edit bubble, time, and with extended info number and author */
	.commentInfo {
		/* as wide as the times in it need */
		flex: 0 0 auto;
		box-sizing: border-box;
		padding: var(--sl-spacing-x-small);
		margin-right: var(--sl-spacing-3x-small);
		border-right: var(--sl-panel-border-width) solid var(--sl-panel-border-color);
		font-size: 90%;
	}
	:host([extended]) .commentInfo {
		flex-basis: 12.5em;
		width: 12.5em;
	}

	/* Number and author are only shown with "Extended info" */
	.commentNumberLine, .commentAuthor {
		display: none;
	}
	:host([extended]) .commentNumberLine {
		display: block;
	}
	:host([extended]) .commentAuthor {
		display: inline-block;
		font-weight: bold;
		overflow: hidden;
		text-overflow: ellipsis;
		white-space: nowrap;
	}
	.commentNumber {
		font-weight: bold;
	}
	.commentNumber::after {
		content: ":";
	}

	/* The bubble that opens a comment for editing is for its author and for staff */
	.commentEditBtn {
		display: none;
		cursor: pointer;
		color: var(--sl-color-primary-600);
	}
	tr.commentOwner .commentEditBtn {
		display: inline-block;
	}

	/* Author, edit bubble and staff tag share the last line of the column; the tag and bubble stay when the
	   author is hidden */
	.commentNameLabel {
		align-items: center;
		justify-content: center;
		flex-wrap: wrap;
		gap: var(--sl-spacing-x-small);
	}
	.commentStaffTag {
		color: var(--sl-color-primary-600);
	}

	/* Text column: category and marking on top, the text, then attachments and reply */
	.commentTextArea {
		flex: 1 1 0;
		min-width: 0;
		padding-left: var(--sl-spacing-x-small);
	}

	.catsHeader {
		align-items: center;
		width: calc(100% - 1em);
		gap: var(--sl-spacing-x-small);
	}
	.commentMarkedTag {
		margin-left: auto;
	}
	.commentCat {
		padding: 0 var(--sl-spacing-x-small);
		border-left: 0.3em solid var(--cat-color, transparent);
		border-radius: var(--sl-border-radius-small);
		background-color: var(--sl-color-neutral-100);
	}

	/* The comment's time is in the info column */
	.commentText::part(time) {
		display: none;
	}
	.commentText {
		display: block;
		flex: 2 0 auto;
		min-height: 1.5em;
		overflow-wrap: anywhere;
	}

	.commentBottomIndicators {
		margin: var(--sl-spacing-large);
		margin-left: auto;
		margin-bottom: var(--sl-spacing-medium);
		gap: var(--sl-spacing-x-small);
	}
	.commentBottomIndicators et2-button-icon::part(base) {
		border: var(--sl-input-border-width) solid var(--sl-input-border-color);
	}
`;
