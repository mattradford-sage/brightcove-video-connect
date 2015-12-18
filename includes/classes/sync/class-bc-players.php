<?php

class BC_Players {

	protected $cms_api;
	protected $players_api;

	public function __construct() {

		$this->cms_api     = new BC_CMS_API();
		$this->players_api = new BC_Player_Management_API();

	}

	function sort_api_response( $players ) {

		if ( ! is_array( $players ) || ! is_array( $players['items'] ) ) {
			return false;
		}

		$players = $players['items'];

		foreach ( $players as $key => $player ) {
			$id             = BC_Utility::sanitize_player_id( $player['id'] );
			$players[ $id ] = $player;
			unset( $players[ $key ] );
		}

		ksort( $players );

		return $players;

	}

	/**
	 * In the event player object data is stale in WordPress, or a player has never been generated,
	 * create/update option with Brightcove data.
	 *
	 * @param      $player
	 *
	 * @return bool success status
	 */
	public function add_or_update_wp_player( $player ) {

		global $bc_accounts;

		$force_sync = false;
		if ( defined( 'BRIGHTCOVE_FORCE_SYNC' ) && BRIGHTCOVE_FORCE_SYNC ) {
			$force_sync = true;
		}

		$hash      = BC_Utility::get_hash_for_object( $player );
		$player_id = $player['id'];

		$stored_hash = $this->get_player_hash_by_id( $player_id );

		// No change to existing player
		if ( ! $force_sync && $hash === $stored_hash ) {
			return true;
		}
		$is_playlist_enabled = ( isset( $player['branches']['master']['configuration']['playlist'] ) && true === $player['branches']['master']['configuration']['playlist'] ) ? true : false;

		$players = get_option( '_bc_player_playlist_ids_' . $bc_accounts->get_account_id() );
		// Sort out playlist-enabled players
		if ( $is_playlist_enabled ) {
			if( ! is_array( $players) || ! in_array( $player['id'], $players ) ) {
				$players[] = $player['id'];
			}
		}
		else {
			// Delete players that may be set but aren't playlist-enabled
			if( is_array( $players) && ! in_array( $player['id'], $players ) ) {
				$player_key = array_search( $player['id'], $players );
				if ( false === $player_key ) {

				} else {
					unset( $players[$player_key] );
				}
			}
		}

		update_option( '_bc_player_playlist_ids_' . $bc_accounts->get_account_id(), $players );

		$key = BC_Utility::get_player_key( $player_id );

		return update_option( $key, $player );
	}

	/**
	 * Accepts a player ID and checks to see if there is an option in WordPress. Returns the player object on success and false on failure.
	 *
	 * @param $player_id
	 *
	 * @return player_object|false
	 */
	public function get_player_by_id( $player_id ) {

		$key = BC_Utility::get_player_key( $player_id );

		return get_option( $key );
	}

	public function get_player_hash_by_id( $player_id ) {

		$player = $this->get_player_by_id( $player_id );

		if ( ! $player ) {
			return false;
		} else {
			return BC_Utility::get_hash_for_object( $player );
		}
	}
}
