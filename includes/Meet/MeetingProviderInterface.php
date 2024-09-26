<?php

namespace Ganfi\GoogleMeetAndZoomIntegration\Meet;

interface MeetingProviderInterface
{
	public function auth(array $params): void;

	public function refreshToken(array $params): void;

	public function create(array $params): array;

	public function updateMeetingDate(array $params): void;

	public function cancel(array $params): void;

	public function download(array $params): array;
}
