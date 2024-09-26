<?php

namespace Ganfi\GoogleMeetAndZoomIntegration\Meet\Zoom;

use Ganfi\GoogleMeetAndZoomIntegration\Meet\MeetingProvider;
use Ganfi\GoogleMeetAndZoomIntegration\Meet\MeetingProviderInterface;

class ZoomMeetingProvider extends MeetingProvider {

	public function getMeetingProvider(): MeetingProviderInterface
	{
		return new ZoomMeeting();
	}
}
