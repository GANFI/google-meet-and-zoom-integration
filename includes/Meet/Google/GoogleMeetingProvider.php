<?php

namespace Ganfi\GoogleMeetAndZoomIntegration\Meet\Google;

use Ganfi\GoogleMeetAndZoomIntegration\Meet\MeetingProvider;
use Ganfi\GoogleMeetAndZoomIntegration\Meet\MeetingProviderInterface;

class GoogleMeetingProvider extends MeetingProvider {

	public function getMeetingProvider(): MeetingProviderInterface
	{
		return new GoogleMeeting();
	}
}
