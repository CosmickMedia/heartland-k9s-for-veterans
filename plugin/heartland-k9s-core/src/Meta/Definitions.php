<?php
/**
 * Field definitions for the custom post types' "Details" meta boxes (§6).
 * Meta key = 'hk9_' + field key.
 *
 * @package HK9\Core
 */

declare(strict_types=1);

namespace HK9\Core\Meta;

use HK9\Core\Fields\Field;

defined( 'ABSPATH' ) || exit;

final class Definitions {

	public const PREFIX = 'hk9_';

	/** @var array<string, array>|null post_type => normalized field list */
	private static ?array $cache = null;

	/** Post types with a Details box. */
	public static function post_types(): array {
		return [ 'hk9_story', 'hk9_team', 'hk9_person', 'hk9_partner', 'hk9_campaign', 'hk9_event', 'hk9_barkode' ];
	}

	/** Normalized field list for a post type ([] when unknown). */
	public static function for( string $post_type ): array {
		if ( null === self::$cache ) {
			self::$cache = [];
			foreach ( self::raw() as $type => $fields ) {
				self::$cache[ $type ] = Field::normalize_list( $fields );
			}
			/**
			 * Filters the CPT field definitions (post_type => field list).
			 *
			 * @param array $definitions Normalized definitions.
			 */
			self::$cache = (array) apply_filters( 'hk9/meta/definitions', self::$cache );
		}
		return self::$cache[ $post_type ] ?? [];
	}

	/** One normalized field by post type + key (null when unknown). */
	public static function field( string $post_type, string $key ): ?array {
		foreach ( self::for( $post_type ) as $field ) {
			if ( $field['key'] === $key ) {
				return $field;
			}
		}
		return null;
	}

	/** Meta key for a field key. */
	public static function meta_key( string $key ): string {
		return self::PREFIX . $key;
	}

	/** Timezone options: site timezone first, then common US zones. */
	public static function timezone_options(): array {
		$site  = wp_timezone_string();
		$zones = [
			'America/New_York'    => 'Eastern (America/New_York)',
			'America/Chicago'     => 'Central (America/Chicago)',
			'America/Denver'      => 'Mountain (America/Denver)',
			'America/Phoenix'     => 'Arizona (America/Phoenix)',
			'America/Los_Angeles' => 'Pacific (America/Los_Angeles)',
			'America/Anchorage'   => 'Alaska (America/Anchorage)',
			'Pacific/Honolulu'    => 'Hawaii (Pacific/Honolulu)',
			'UTC'                 => 'UTC',
		];
		if ( '' !== $site && ! isset( $zones[ $site ] ) ) {
			$zones = [ $site => sprintf( /* translators: %s: timezone */ __( 'Site timezone (%s)', 'heartland-k9s-core' ), $site ) ] + $zones;
		}
		return $zones;
	}

	/** Validates an organization email (org domains only). */
	public static function org_email( mixed $value ): string {
		$email = strtolower( sanitize_email( (string) $value ) );
		if ( '' === $email || ! is_email( $email ) ) {
			return '';
		}
		/**
		 * Filters the domains allowed for public person emails.
		 *
		 * @param string[] $domains Allowed domains.
		 */
		$domains = (array) apply_filters( 'hk9/meta/person_email_domains', [ 'heartlandk9s.org' ] );
		$domain  = strtolower( substr( strrchr( $email, '@' ) ?: '', 1 ) );
		return in_array( $domain, array_map( 'strtolower', $domains ), true ) ? $email : '';
	}

	private static function text( string $key, string $label, array $extra = [] ): array {
		return array_merge(
			[
				'type'  => 'text',
				'key'   => $key,
				'label' => $label,
			],
			$extra
		);
	}

	private static function textarea( string $key, string $label, array $extra = [] ): array {
		return array_merge(
			[
				'type'  => 'textarea',
				'key'   => $key,
				'label' => $label,
				'rows'  => 3,
			],
			$extra
		);
	}

	private static function toggle( string $key, string $label, array $extra = [] ): array {
		return array_merge(
			[
				'type'  => 'toggle',
				'key'   => $key,
				'label' => $label,
			],
			$extra
		);
	}

	private static function select( string $key, string $label, array $options, string $default, array $extra = [] ): array {
		return array_merge(
			[
				'type'    => 'select',
				'key'     => $key,
				'label'   => $label,
				'options' => $options,
				'default' => $default,
			],
			$extra
		);
	}

	private static function link( string $key, string $label, array $extra = [] ): array {
		return array_merge(
			[
				'type'  => 'link',
				'key'   => $key,
				'label' => $label,
			],
			$extra
		);
	}

	/** Raw definitions per post type. */
	private static function raw(): array {
		$contact_line = '';
		if ( function_exists( 'hk9_option' ) ) {
			$phone        = (string) hk9_option( 'contact.phone_main', '' );
			$contact_line = '' !== $phone ? sprintf( 'Heartland Canines for Veterans · %s', $phone ) : '';
		}

		return [
			'hk9_story'    => [
				self::text( 'veteran_name', __( 'Veteran display name', 'heartland-k9s-core' ), [ 'help' => __( 'Only the name the veteran approved for publication.', 'heartland-k9s-core' ) ] ),
				self::text( 'branch', __( 'Branch of service', 'heartland-k9s-core' ) ),
				self::text( 'canine_name', __( 'Canine name', 'heartland-k9s-core' ) ),
				self::select(
					'relationship',
					__( 'Relationship', 'heartland-k9s-core' ),
					[
						'service'     => __( 'Service dog', 'heartland-k9s-core' ),
						'therapy'     => __( 'Therapy dog', 'heartland-k9s-core' ),
						'in-training' => __( 'In training', 'heartland-k9s-core' ),
					],
					'service'
				),
				self::text( 'pairing_year', __( 'Pairing year', 'heartland-k9s-core' ), [ 'help' => __( 'Only if verified.', 'heartland-k9s-core' ) ] ),
				self::textarea( 'quote', __( 'Quote', 'heartland-k9s-core' ), [ 'rows' => 4 ] ),
				self::toggle( 'featured', __( 'Featured story', 'heartland-k9s-core' ) ),
				[
					'type'  => 'gallery',
					'key'   => 'gallery',
					'label' => __( 'Gallery', 'heartland-k9s-core' ),
				],
				[
					'type'      => 'relationship',
					'key'       => 'team',
					'label'     => __( 'Related team', 'heartland-k9s-core' ),
					'post_type' => 'hk9_team',
				],
				self::text(
					'source_note',
					__( 'Source note (internal)', 'heartland-k9s-core' ),
					[
						'help'    => __( 'Where this story was sourced from. Not shown on the site.', 'heartland-k9s-core' ),
						'private' => true,
					]
				),
			],
			'hk9_team'     => [
				self::text( 'canine_name', __( 'Canine name', 'heartland-k9s-core' ) ),
				self::text( 'handler_name', __( 'Handler display name', 'heartland-k9s-core' ), [ 'help' => __( 'Only the name approved for publication.', 'heartland-k9s-core' ) ] ),
				self::select(
					'status',
					__( 'Status', 'heartland-k9s-core' ),
					[
						'in-training' => __( 'In training', 'heartland-k9s-core' ),
						'graduated'   => __( 'Graduated', 'heartland-k9s-core' ),
						'therapy'     => __( 'Therapy', 'heartland-k9s-core' ),
					],
					'in-training'
				),
				self::text( 'year', __( 'Year', 'heartland-k9s-core' ) ),
				self::toggle( 'featured', __( 'Featured / highlighted team', 'heartland-k9s-core' ) ),
				[
					'type'  => 'gallery',
					'key'   => 'gallery',
					'label' => __( 'Gallery', 'heartland-k9s-core' ),
				],
				self::link( 'donate_link', __( 'Donate link', 'heartland-k9s-core' ) ),
				[
					'type'      => 'relationship',
					'key'       => 'barkode',
					'label'     => __( 'BarKode record', 'heartland-k9s-core' ),
					'post_type' => 'hk9_barkode',
				],
				self::textarea( 'summary', __( 'Summary', 'heartland-k9s-core' ) ),
			],
			'hk9_person'   => [
				self::text( 'role', __( 'Role / title', 'heartland-k9s-core' ) ),
				self::text(
					'email',
					__( 'Email (organization domain only)', 'heartland-k9s-core' ),
					[
						'help'              => __( 'Only @heartlandk9s.org addresses are published; anything else is discarded.', 'heartland-k9s-core' ),
						'sanitize_callback' => [ self::class, 'org_email' ],
					]
				),
				[
					'type'       => 'repeater',
					'key'        => 'links',
					'label'      => __( 'Links', 'heartland-k9s-core' ),
					'item_label' => 'link',
					'add_label'  => __( 'Add link', 'heartland-k9s-core' ),
					'fields'     => [
						self::link( 'link', __( 'Link', 'heartland-k9s-core' ) ),
					],
				],
				self::textarea( 'quote', __( 'Quote', 'heartland-k9s-core' ), [ 'rows' => 4 ] ),
			],
			'hk9_partner'  => [
				self::link( 'website', __( 'Website', 'heartland-k9s-core' ), [ 'allow_internal' => false ] ),
				self::text( 'tier', __( 'Tier', 'heartland-k9s-core' ) ),
				self::text( 'since', __( 'Partner since', 'heartland-k9s-core' ) ),
			],
			'hk9_campaign' => [
				self::textarea( 'summary', __( 'Summary', 'heartland-k9s-core' ) ),
				self::select(
					'status',
					__( 'Status', 'heartland-k9s-core' ),
					[
						'active'    => __( 'Active', 'heartland-k9s-core' ),
						'completed' => __( 'Completed', 'heartland-k9s-core' ),
						'paused'    => __( 'Paused', 'heartland-k9s-core' ),
					],
					'active'
				),
				[
					'type'  => 'date',
					'key'   => 'start',
					'label' => __( 'Start date', 'heartland-k9s-core' ),
				],
				[
					'type'  => 'date',
					'key'   => 'end',
					'label' => __( 'End date', 'heartland-k9s-core' ),
				],
				self::link( 'cta', __( 'Primary call to action', 'heartland-k9s-core' ) ),
				self::link( 'secondary_cta', __( 'Secondary call to action', 'heartland-k9s-core' ) ),
				[
					'type'      => 'relationship',
					'key'       => 'sponsors',
					'label'     => __( 'Sponsors', 'heartland-k9s-core' ),
					'post_type' => 'hk9_partner',
					'multiple'  => true,
					'orderable' => true,
				],
				[
					'type'  => 'gallery',
					'key'   => 'gallery',
					'label' => __( 'Gallery', 'heartland-k9s-core' ),
				],
				self::text( 'goal_text', __( 'Goal text', 'heartland-k9s-core' ), [ 'help' => __( 'Only when sourced from the organization (e.g. "Goal: 10 sponsors").', 'heartland-k9s-core' ) ] ),
				self::toggle( 'featured', __( 'Featured campaign', 'heartland-k9s-core' ) ),
			],
			'hk9_event'    => [
				[
					'type'          => 'datetime',
					'key'           => 'start',
					'label'         => __( 'Start', 'heartland-k9s-core' ),
					'time_optional' => true,
					'required'      => true,
				],
				[
					'type'          => 'datetime',
					'key'           => 'end',
					'label'         => __( 'End', 'heartland-k9s-core' ),
					'time_optional' => true,
				],
				self::toggle( 'all_day', __( 'All-day event', 'heartland-k9s-core' ) ),
				self::toggle( 'time_tbd', __( 'Time to be announced', 'heartland-k9s-core' ) ),
				self::select( 'timezone', __( 'Timezone', 'heartland-k9s-core' ), self::timezone_options(), wp_timezone_string() ),
				self::text( 'venue', __( 'Venue', 'heartland-k9s-core' ) ),
				self::textarea( 'address', __( 'Address', 'heartland-k9s-core' ) ),
				self::link( 'registration', __( 'Registration / tickets link', 'heartland-k9s-core' ) ),
				self::textarea( 'ticket_info', __( 'Ticket information', 'heartland-k9s-core' ) ),
				self::text( 'organizer_name', __( 'Organizer name', 'heartland-k9s-core' ) ),
				self::text( 'organizer_contact', __( 'Organizer contact', 'heartland-k9s-core' ) ),
				self::select(
					'status',
					__( 'Status', 'heartland-k9s-core' ),
					[
						'scheduled' => __( 'Scheduled', 'heartland-k9s-core' ),
						'cancelled' => __( 'Cancelled', 'heartland-k9s-core' ),
						'postponed' => __( 'Postponed', 'heartland-k9s-core' ),
					],
					'scheduled'
				),
				self::toggle( 'featured', __( 'Featured event', 'heartland-k9s-core' ) ),
				[
					'type'  => 'image',
					'key'   => 'flyer',
					'label' => __( 'Flyer', 'heartland-k9s-core' ),
				],
			],
			'hk9_barkode'  => [
				self::text( 'dog_name', __( 'Dog name', 'heartland-k9s-core' ), [ 'required' => true ] ),
				self::select(
					'program_type',
					__( 'Program type', 'heartland-k9s-core' ),
					[
						'service'     => __( 'Service dog', 'heartland-k9s-core' ),
						'therapy'     => __( 'Therapy dog', 'heartland-k9s-core' ),
						'in-training' => __( 'In training', 'heartland-k9s-core' ),
					],
					'service'
				),
				self::text( 'registry_id', __( 'Registry ID', 'heartland-k9s-core' ), [ 'help' => __( 'The team number printed on the BarKode patch.', 'heartland-k9s-core' ) ] ),
				self::text( 'legacy_path', __( 'Legacy path', 'heartland-k9s-core' ), [ 'help' => __( 'The original root-level path, e.g. /hk923-005/. Seeds a redirect to this record.', 'heartland-k9s-core' ) ] ),
				self::text( 'breed', __( 'Breed', 'heartland-k9s-core' ) ),
				self::text( 'task_description', __( 'Task description', 'heartland-k9s-core' ) ),
				self::textarea( 'tasks', __( 'Tasks', 'heartland-k9s-core' ), [ 'rows' => 4 ] ),
				self::text( 'handler_name', __( 'Handler name', 'heartland-k9s-core' ) ),
				self::textarea( 'emergency_contact', __( 'Emergency contact', 'heartland-k9s-core' ) ),
				self::textarea( 'vet_contact', __( 'Veterinary contact', 'heartland-k9s-core' ) ),
				self::text( 'certification', __( 'Certification', 'heartland-k9s-core' ) ),
				self::toggle( 'do_not_separate', __( 'Do not separate dog and handler', 'heartland-k9s-core' ) ),
				self::textarea( 'notice', __( 'Notice', 'heartland-k9s-core' ) ),
				self::text( 'contact_line', __( 'Contact line', 'heartland-k9s-core' ), [ 'default' => $contact_line ] ),
				[
					'type'  => 'gallery',
					'key'   => 'id_card_images',
					'label' => __( 'ID card images', 'heartland-k9s-core' ),
				],
				self::textarea(
					'review_notes',
					__( 'Review notes (never published)', 'heartland-k9s-core' ),
					[
						'rows'    => 4,
						'help'    => __( 'Restricted details pending client review. Never rendered and never exposed through the REST API.', 'heartland-k9s-core' ),
						'private' => true,
						'no_rest' => true,
					]
				),
				self::text( 'status_note', __( 'Status note', 'heartland-k9s-core' ) ),
			],
		];
	}
}
