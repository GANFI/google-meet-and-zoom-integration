<?php

namespace Ganfi\GoogleMeetAndZoomIntegration\Meet\Google;

use Ganfi\GoogleMeetAndZoomIntegration\Meet\MeetingProviderInterface;

class GoogleMeeting implements MeetingProviderInterface
{
	private string $client_id;
	private string $client_secret;
	private string $redirect_url;

	public function __construct(string $client_id, string $client_secret, string $redirect_url)
	{
		$this->client_id = $client_id;
		$this->client_secret = $client_secret;
		$this->redirect_url = $redirect_url;
	}

	public function auth(array $params): void
	{
		$authorization_code = $params['code'];
		$ch = curl_init();
		curl_setopt($ch, CURLOPT_URL, 'https://oauth2.googleapis.com/token');
		curl_setopt($ch, CURLOPT_POST, true);
		curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
		curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query([
			'code' => $authorization_code,
			'client_id' => $this->client_id,
			'client_secret' => $this->client_secret,
			'redirect_uri' => 'postmessage',
			'grant_type' => 'authorization_code'
		]));

		$google_res = curl_exec($ch);
		$http_status = curl_getinfo($ch, CURLINFO_HTTP_CODE);
		curl_close($ch);

		// Decode the response from Google
		$google_data = json_decode($google_res, true);

		if ($http_status == 200 && empty($google_data['error'])) {
			// The access token and refresh token are received successfully
			$token_info = [
				'token' => $google_data['access_token'],
				'refresh_token' => $google_data['refresh_token'],  // Store the refresh token
				'expire' => time() + $google_data['expires_in'],
			];

			// Save tokens in the user's metadata
			update_user_meta($params['user_id'], 'google_token', json_encode($token_info));
		} else {
			// Handle the error if the token exchange fails
			throw new \Exception("Error during Google token exchange: " . ($google_data['error_description'] ?? 'Unknown error'));
		}
	}

	public function refreshToken(array $params): void
	{
		$ch = curl_init();
		curl_setopt($ch, CURLOPT_URL, "https://oauth2.googleapis.com/token");
		curl_setopt($ch, CURLOPT_POST, true);
		curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
		curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query([
			'client_id' => $this->client_id,
			'client_secret' => $this->client_secret,
			'refresh_token' => $params['refresh_token'],
			'grant_type' => 'refresh_token',
		]));
		curl_setopt($ch, CURLOPT_HTTPHEADER, array(
			'Content-Type: application/x-www-form-urlencoded',
		));

		$google_res = curl_exec($ch);
		$http_status = curl_getinfo($ch, CURLINFO_HTTP_CODE);
		curl_close($ch);

		$google_data = json_decode($google_res, true);

		if ($http_status == 200 && empty($google_data['error'])) {
			$token_info = [
				'token' => $google_data['access_token'],
				'refresh_token' => $google_data['refresh_token'],
				'expire' => time() + $google_data['expires_in'],
			];
			update_user_meta($params['user_id'], 'google_token', json_encode($token_info));
		} else {
			throw new \Exception("Error refreshing Google token: " . $google_data['error_description']);
		}
	}

	public function create(array $params): array
	{
		$token_info = $this->checkAndRefreshToken($params['user_id']);
		$token = $token_info['token'];

		$event_data = [
			'summary' => $params['topic'],
			'start' => [
				'dateTime' => $params['start_time'],
				'timeZone' => $params['timezone'],
			],
			'end' => [
				'dateTime' => $params['end_time'],
				'timeZone' => $params['timezone'],
			],
			'conferenceData' => [
				'createRequest' => [
					'conferenceSolutionKey' => [
						'type' => 'hangoutsMeet',
					],
					'requestId' => uniqid(),
				],
			],
			'attendees' => array_map(function($email) {
				return ['email' => $email];
			}, $params['participants']),
		];

		$ch = curl_init();
		curl_setopt($ch, CURLOPT_URL, "https://www.googleapis.com/calendar/v3/calendars/primary/events?conferenceDataVersion=1");
		curl_setopt($ch, CURLOPT_POST, true);
		curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
		curl_setopt($ch, CURLOPT_HTTPHEADER, array(
			'Authorization: Bearer ' . $token,
			'Content-Type: application/json',
		));
		curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($event_data));

		$google_res = curl_exec($ch);
		$http_status = curl_getinfo($ch, CURLINFO_HTTP_CODE);
		curl_close($ch);

		$google_data = json_decode($google_res, true);

		if ($http_status == 200) {
			return [
				'meeting_id' => $google_data['id'],
				'meeting_link' => $google_data['hangoutLink'],
			];
		}

		var_dump($google_data);die;

		throw new \Exception("Error creating Google Meet meeting: " . $google_data['message']);
	}

	private function checkAndRefreshToken(string $user_id): array
	{
		$token_info = json_decode(get_user_meta($user_id, 'google_token', true), true);

		// Check if the token is expired
		if ($token_info['expire'] <= time()) {
			// Token is expired, refresh it
			$this->refreshToken([
				'user_id' => $user_id,
				'refresh_token' => $token_info['refresh_token'],
			]);

			// Retrieve the updated token info
			$token_info = json_decode(get_user_meta($user_id, 'google_token', true), true);
		}

		return $token_info;
	}

	public function download(array $params): array
	{
		$token_info = $this->checkAndRefreshToken($params['user_id']);
		$token = $token_info['token'];

		$meeting_id = $params['meeting_id'];

		$ch = curl_init();
		curl_setopt($ch, CURLOPT_URL, "https://www.googleapis.com/calendar/v3/calendars/primary/events/$meeting_id");
		curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
		curl_setopt($ch, CURLOPT_HTTPHEADER, array(
			'Authorization: Bearer ' . $token,
		));

		$google_res = curl_exec($ch);
		$http_status = curl_getinfo($ch, CURLINFO_HTTP_CODE);
		curl_close($ch);

		$google_data = json_decode($google_res, true);

		var_dump($google_data);

		return $google_data;

		if ($http_status == 200 && isset($google_data['conferenceData']['entryPoints'])) {
			foreach ($google_data['conferenceData']['entryPoints'] as $entryPoint) {
				if ($entryPoint['entryPointType'] == 'video') {
					$recording_link = $entryPoint['uri'];

					// Download the recording
					$recording_content = file_get_contents($recording_link);
					file_put_contents("recordings/meeting_$meeting_id.mp4", $recording_content);
				}
			}
		}

		throw new \Exception("Error downloading Google Meet recording: " . $google_data['message']);
	}

	public function updateMeetingDate(array $params): void
	{
		$token_info = $this->checkAndRefreshToken($params['user_id']);
		$token = $token_info['token'];

		$eventData = [
			'start' => [
				'dateTime' => $params['start_time'],
				'timeZone' => 'Europe/Kiev',
			],
			'end' => [
				'dateTime' => $params['end_time'],
				'timeZone' => 'Europe/Kiev',
			]
		];

		$ch = curl_init();
		curl_setopt($ch, CURLOPT_URL, "https://www.googleapis.com/calendar/v3/calendars/primary/events/{$params['meeting_id']}");
		curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
		curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'PATCH');
		curl_setopt($ch, CURLOPT_HTTPHEADER, [
			'Authorization: Bearer ' . $token,
			'Content-Type: application/json'
		]);
		curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($eventData));

		$response = curl_exec($ch);
		$http_status = curl_getinfo($ch, CURLINFO_HTTP_CODE);
		curl_close($ch);

		$responseData = json_decode($response, true);

		if ($http_status !== 200) {
			$errorMessage = isset($responseData['error']['message']) ? $responseData['error']['message'] : 'Unknown error';
			throw new \Exception("Error updating meeting date: $errorMessage");
		}
	}

	public function cancel(array $params): void
	{
		$token_info = $this->checkAndRefreshToken($params['user_id']);
		$token = $token_info['token'];

		$event_id = $params['meeting_id'];

		$url = "https://www.googleapis.com/calendar/v3/calendars/primary/events/" . $event_id;

		$ch = curl_init($url);
		curl_setopt($ch, CURLOPT_CUSTOMREQUEST, "DELETE");
		curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
		curl_setopt($ch, CURLOPT_HTTPHEADER, [
			'Authorization: Bearer ' . $token,
			'Content-Type: application/json',
		]);

		$response = curl_exec($ch);
		$http_status = curl_getinfo($ch, CURLINFO_HTTP_CODE);
		curl_close($ch);

		if ($http_status !== 204) {
			throw new \Exception("Error cancelling the Google Meet: " . $response);
		}
	}

}
