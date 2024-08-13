import {smallpartApp} from "./app";
import {et2_smallpart_videooverlay} from "./et2_widget_videooverlay";

/**
 * Methods used when editing a video
 */
export class VideoEdit
{
	private app : smallpartApp;

	protected get overlay() : et2_smallpart_videooverlay {return this.app?.et2?.getInstanceManager()?.DOMContainer?.querySelector("smallpart-videooverlay") ?? null};

	constructor(app : smallpartApp)
	{
		this.app = app;
	}

	/**
	 * Add text to the video
	 */
	addText()
	{
		this.overlay?.addText();
	}

	addQuestion()
	{
		this.overlay?.addQuestion();
	}
}