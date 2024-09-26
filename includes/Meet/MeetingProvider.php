<?php

namespace Ganfi\GoogleMeetAndZoomIntegration\Meet;

abstract class MeetingProvider
{
	abstract public function getMeetingProvider(): MeetingProviderInterface;

	public function isExpired(array $data): bool
	{
		return $data['expire'] < time();
	}

	public function createMeeting(array $data): void
	{
		$provider = $this->getMeetingProvider();
		if ($this->isExpired($data)) {
			$provider->refreshToken($data);
		}
		$provider->create($data);
	}
}
