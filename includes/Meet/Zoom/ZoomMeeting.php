<?php

namespace Ganfi\GoogleMeetAndZoomIntegration\Meet\Zoom;

use Exception;
use Ganfi\GoogleMeetAndZoomIntegration\Meet\MeetingProviderInterface;

/**
 *
 */
class ZoomMeeting implements MeetingProviderInterface
{
	/**
	 * @var string
	 */
	private string $api_key;
	/**
	 * @var string
	 */
	private string $api_secret;
	/**
	 * @var string
	 */
	private string $redirect_url;

	/**
	 * @param  string  $api_key
	 * @param  string  $api_secret
	 * @param  string  $redirect_url
	 */
	public function __construct( string $api_key, string $api_secret, string $redirect_url )
	{
		$this->api_key      = $api_key;
		$this->api_secret   = $api_secret;
		$this->redirect_url = $redirect_url;
	}

	/**
	 * @param  array  $params
	 *
	 * @return void
	 * @throws Exception
	 */
	public function auth( array $params ): void
	{

		$credentials = base64_encode( "$this->api_key:$this->api_secret" );

		$ch = curl_init();
		curl_setopt( $ch, CURLOPT_URL,
			"https://zoom.us/oauth/token?grant_type=authorization_code&code="
			. $params['code'] . "&redirect_uri=$this->redirect_url" );
		curl_setopt( $ch, CURLOPT_POST, true );
		curl_setopt( $ch, CURLOPT_RETURNTRANSFER, true );
		curl_setopt( $ch, CURLOPT_HTTPHEADER, array(
			'Authorization: Basic ' . $credentials,
		) );

		$zoom_res    = curl_exec( $ch );
		$http_status = curl_getinfo( $ch, CURLINFO_HTTP_CODE );
		curl_close( $ch );

		$zoom_data = json_decode( $zoom_res, true );

		if ( $http_status == 200 && empty( $zoom_data['error'] ) ) {
			$token_info = [
				'token'         => $zoom_data['access_token'],
				'refresh_token' => $zoom_data['refresh_token'],
				'expire'        => time() + $zoom_data['expires_in'],
			];
			update_user_meta( $params['user_id'], 'zoom_token',
				json_encode( $token_info ) );
		} else {
			throw new Exception( "Error during Zoom authentication: "  . $zoom_data['error_description'] );
		}
	}

	/**
	 * @param  array  $params
	 *
	 * @return void
	 * @throws Exception
	 */
	public function refreshToken( array $params ): void
	{
		$credentials = base64_encode( "$this->api_key:$this->api_secret" );

		$ch = curl_init();
		curl_setopt( $ch, CURLOPT_URL,
			"https://zoom.us/oauth/token?grant_type=refresh_token&refresh_token="
			. $params['refresh_token'] );
		curl_setopt( $ch, CURLOPT_POST, true );
		curl_setopt( $ch, CURLOPT_RETURNTRANSFER, true );
		curl_setopt( $ch, CURLOPT_HTTPHEADER, array(
			'Authorization: Basic ' . $credentials,
		) );

		$zoom_res    = curl_exec( $ch );
		$http_status = curl_getinfo( $ch, CURLINFO_HTTP_CODE );
		curl_close( $ch );

		$zoom_data = json_decode( $zoom_res, true );

		if ( $http_status == 200 && empty( $zoom_data['error'] ) ) {
			$token_info = [
				'token'         => $zoom_data['access_token'],
				'refresh_token' => $zoom_data['refresh_token'],
				'expire'        => time() + $zoom_data['expires_in'],
			];
			update_user_meta( $params['user_id'], 'zoom_token',
				json_encode( $token_info ) );
		} else {
			throw new Exception( "Error refreshing Zoom token: " . $zoom_data['error_description'] );
		}
	}

	/**
	 * @param  array  $params
	 *
	 * @return array
	 * @throws Exception
	 */
	public function create( array $params ): array
	{
		$token_info = $this->checkAndRefreshToken( $params['user_id'] );
		$token      = $token_info['token'];
		$user_id    = $this->getUserId( $token );
		$topic      = $params['topic'];
		$start_time = $params['start_time'];
		$duration   = $params['duration'];
		$timezone   = $params['timezone'];
		$participants = $params['participants'];

		$meeting_data = [
			'topic'      => $topic,
			'type'       => 2, // Scheduled meeting
			'start_time' => $start_time,
			'duration'   => $duration,
			'timezone'   => $timezone,
			'settings'   => [
				'host_video'        => true,
				'participant_video' => true,
				'join_before_host'  => false,
				'mute_upon_entry'   => true,
				'waiting_room'      => true,
				'approval_type'     => 1, // Automatically approve
				'registration_type' => 1, // Register each participant once
			],
		];

		$ch = curl_init();
		curl_setopt( $ch, CURLOPT_URL,
			"https://api.zoom.us/v2/users/$user_id/meetings" );
		curl_setopt( $ch, CURLOPT_POST, true );
		curl_setopt( $ch, CURLOPT_RETURNTRANSFER, true );
		curl_setopt( $ch, CURLOPT_HTTPHEADER, array(
			'Authorization: Bearer ' . $token,
			'Content-Type: application/json',
		) );
		curl_setopt( $ch, CURLOPT_POSTFIELDS, json_encode( $meeting_data ) );

		$zoom_res    = curl_exec( $ch );
		$http_status = curl_getinfo( $ch, CURLINFO_HTTP_CODE );
		curl_close( $ch );

		$zoom_data = json_decode( $zoom_res, true );

		if ( $http_status == 201 ) {
			$meeting_id = $zoom_data['id'];

			//TODO: only for paid accounts
//			foreach ($participants as $email) {
//				$this->addRegistrant( $token, $meeting_id, $email );
//			}

			return [
				'meeting_id'   => $meeting_id,
				'meeting_link' => $zoom_data['join_url'],
			];
		}

		throw new Exception( "Error creating Zoom meeting: " . $zoom_data['message'] );
	}

	/**
	 * @param  string  $token
	 * @param  string  $meeting_id
	 * @param  string  $email
	 *
	 * @return void
	 * @throws Exception
	 */
	private function addRegistrant(string $token, string $meeting_id, string $email): void
	{
		$registrant_data = [
			'email' => $email,
			'first_name' => 'Test'
		];

		// Initialize cURL
		$ch = curl_init();
		curl_setopt($ch, CURLOPT_URL, "https://api.zoom.us/v2/meetings/$meeting_id/registrants");
		curl_setopt($ch, CURLOPT_POST, true);
		curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
		curl_setopt($ch, CURLOPT_HTTPHEADER, [
			'Authorization: Bearer ' . $token,
			'Content-Type: application/json',
		]);
		curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($registrant_data));

		// Execute the cURL request
		$zoom_res = curl_exec($ch);

		// Check for cURL errors
		if (curl_errno($ch)) {
			throw new Exception("cURL error: " . curl_error($ch));
		}

		$http_status = curl_getinfo($ch, CURLINFO_HTTP_CODE);
		curl_close($ch);

		// Decode the response from Zoom
		$zoom_data = json_decode($zoom_res, true);

		// Log the raw response for debugging
		error_log("Zoom response: " . print_r($zoom_res, true));
		error_log("Decoded Zoom data: " . print_r($zoom_data, true));

		// Check the HTTP status code
		if ($http_status !== 201) {
			// Check if the response contains an error message
			$error_message = isset($zoom_data['message']) ? $zoom_data['message'] : 'Unknown error';
			throw new Exception("Error adding registrant: HTTP $http_status - $error_message");
		}

		// If successful, print or log the registrant data
		error_log("Registrant added successfully: " . print_r($zoom_data, true));
	}


	/**
	 * @param  string  $token
	 *
	 * @return string
	 * @throws Exception
	 */
	public function getUserId( string $token ): string
	{
		$ch = curl_init();
		curl_setopt( $ch, CURLOPT_URL, "https://api.zoom.us/v2/users/me" );
		curl_setopt( $ch, CURLOPT_RETURNTRANSFER, true );
		curl_setopt( $ch, CURLOPT_HTTPHEADER, array(
			'Authorization: Bearer ' . $token,
		) );

		$zoom_res    = curl_exec( $ch );
		$http_status = curl_getinfo( $ch, CURLINFO_HTTP_CODE );
		curl_close( $ch );

		$zoom_data = json_decode( $zoom_res, true );

		if ( $http_status == 200 && isset( $zoom_data['id'] ) ) {
			return $zoom_data['id'];
		}

		throw new Exception( "Error retrieving Zoom user ID: " . $zoom_data['message'] );
	}

	/**
	 * @param  string  $user_id
	 *
	 * @return array
	 * @throws Exception
	 */
	private function checkAndRefreshToken( string $user_id ): array {
		$token_info = json_decode( get_user_meta( $user_id, 'zoom_token',true ), true );

		if ( $token_info['expire'] <= time() ) {
			$this->refreshToken( [
				'user_id'       => $user_id,
				'refresh_token' => $token_info['refresh_token'],
			] );

			$token_info = json_decode( get_user_meta( $user_id, 'zoom_token',true ), true );
		}

		return $token_info;
	}

	/**
	 * Update an existing Zoom meeting's date and time.
	 *
	 * @param array $params
	 * @return void
	 * @throws Exception
	 */
	public function updateMeetingDate(array $params): void
	{
		$token_info = $this->checkAndRefreshToken($params['user_id']);
		$token = $token_info['token'];

		$meeting_id = $params['meeting_id'];
		$start_time = $params['start_time'];
		$duration = $params['duration'];
		$timezone = $params['timezone'];

		$update_data = [
			'start_time' => $start_time,
			'duration'   => $duration,
			'timezone'   => $timezone,
		];

		$ch = curl_init();
		curl_setopt($ch, CURLOPT_URL, "https://api.zoom.us/v2/meetings/$meeting_id");
		curl_setopt($ch, CURLOPT_CUSTOMREQUEST, "PATCH");
		curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
		curl_setopt($ch, CURLOPT_HTTPHEADER, [
			'Authorization: Bearer ' . $token,
			'Content-Type: application/json',
		]);
		curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($update_data));

		$zoom_res = curl_exec($ch);
		$http_status = curl_getinfo($ch, CURLINFO_HTTP_CODE);
		curl_close($ch);

		$zoom_data = json_decode($zoom_res, true);

		if ($http_status != 204) {
			throw new Exception("Error updating Zoom meeting: " . $zoom_data['message']);
		}
	}

	/**
	 * Cancel an existing Zoom meeting.
	 *
	 * @param array $params
	 * @return void
	 * @throws Exception
	 */
	public function cancel(array $params): void
	{
		$token_info = $this->checkAndRefreshToken($params['user_id']);
		$token = $token_info['token'];
		$meeting_id = $params['meeting_id'];

		$ch = curl_init();
		curl_setopt($ch, CURLOPT_URL, "https://api.zoom.us/v2/meetings/$meeting_id");
		curl_setopt($ch, CURLOPT_CUSTOMREQUEST, "DELETE");
		curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
		curl_setopt($ch, CURLOPT_HTTPHEADER, [
			'Authorization: Bearer ' . $token,
			'Content-Type: application/json',
		]);

		$zoom_res = curl_exec($ch);

		if (curl_errno($ch)) {
			throw new Exception("cURL error: " . curl_error($ch));
		}

		$http_status = curl_getinfo($ch, CURLINFO_HTTP_CODE);
		curl_close($ch);

		$zoom_data = json_decode($zoom_res, true);

		// Check the HTTP status code
		if ($http_status !== 204) {
			$error_message = isset($zoom_data['message']) ? $zoom_data['message'] : 'Unknown error';
			throw new Exception("Error canceling Zoom meeting: HTTP $http_status - $error_message");
		}
	}

	public function download( array $params ): array
	{
		return [];
	}
}
