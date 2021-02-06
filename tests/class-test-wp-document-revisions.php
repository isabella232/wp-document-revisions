<?php
/**
 * Verifies basic CRUD operations of WP Document Revisions
 *
 * @author Neil James <neil@familyjames.com> extended from Benjamin J. Balter <ben@balter.com>
 * @package WP Document Revisions
 */

/**
 * Main WP Document Revisions tests
 */
class Test_WP_Document_Revisions extends Test_Common_WPDR {

	/**
	 * List of users being tested.
	 *
	 * @var WP_User[] $users
	 */
	protected static $users = array(
		'editor'      => null,
		'author'      => null,
		'contributor' => null,
		'subscriber'  => null,
	);

	/**
	 * Author Public Post ID
	 *
	 * @var integer $editor_public_post
	 */
	private static $editor_public_post;

	/**
	 * Editor Private Post ID
	 *
	 * @var integer $editor_private_post
	 */
	private static $editor_private_post;

	// phpcs:disable
	/**
	 * Set up common data before tests.
	 *
	 * @param WP_UnitTest_Factory $factory.
	 * @return void.
	 */
	public static function wpSetUpBeforeClass( WP_UnitTest_Factory $factory ) {
	// phpcs:enable
		console_log( 'Test_Main' );

		// init user roles.
		global $wpdr;
		if ( ! $wpdr ) {
			$wpdr = new WP_Document_Revisions();
		}
		$wpdr->add_caps();

		// create users and assign role.
		// Note that editor can do everything admin can do.
		self::$users = array(
			'editor'      => $factory->user->create_and_get(
				array(
					'user_nicename' => 'Editor',
					'role'          => 'editor',
				)
			),
			'author'      => $factory->user->create_and_get(
				array(
					'user_nicename' => 'Author',
					'role'          => 'author',
				)
			),
			'contributor' => $factory->user->create_and_get(
				array(
					'user_nicename' => 'Contributor',
					'role'          => 'contributor',
				)
			),
			'subscriber'  => $factory->user->create_and_get(
				array(
					'user_nicename' => 'Subscriber',
					'role'          => 'subscriber',
				)
			),
		);

		// flush cache for good measure.
		wp_cache_flush();

		// create posts for scenarios.
		// Editor Public.
		self::$editor_public_post = $factory->post->create(
			array(
				'post_title'   => 'Editor Public - ' . time(),
				'post_status'  => 'publish',
				'post_author'  => self::$users['editor']->ID,
				'post_content' => '',
				'post_excerpt' => 'Test Upload',
				'post_type'    => 'document',
			)
		);

		self::assertFalse( is_wp_error( $factory, self::$editor_public_post ), 'Failed inserting document Editor Public' );

		// add attachment.
		self::add_document_attachment( $factory, self::$editor_public_post, self::$test_file );

		// add second attachment.
		self::add_document_attachment( $factory, self::$editor_public_post, self::$test_file2 );

		// Editor Private.
		self::$editor_private_post = $factory->post->create(
			array(
				'post_title'   => 'Editor Private - ' . time(),
				'post_status'  => 'private',
				'post_author'  => self::$users['editor']->ID,
				'post_content' => '',
				'post_excerpt' => 'Test Upload',
				'post_type'    => 'document',
			)
		);

		self::assertFalse( is_wp_error( self::$editor_private_post ), 'Failed inserting document Editor Private' );

		// add attachment.
		self::add_document_attachment( $factory, self::$editor_private_post, self::$test_file );
	}

	/**
	 * Get the roles data refreshed. (Taken from WP Test Suite).
	 */
	public function setUp() {
		parent::setUp();
		// Keep track of users we create.
		self::flush_roles();
	}

	/**
	 * Get the roles data refreshed.
	 */
	private function flush_roles() {
		// We want to make sure we're testing against the DB, not just in-memory data.
		// This will flush everything and reload it from the DB.
		// phpcs:disable WordPress.WP.GlobalVariablesOverride.Prohibited
		unset( $GLOBALS['wp_user_roles'] );
		global $wp_roles;
		$wp_roles = new WP_Roles();
		// phpcs:enable WordPress.WP.GlobalVariablesOverride.Prohibited

		// confirm user roles.
		global $wpdr;
		if ( ! $wpdr ) {
			$wpdr = new WP_Document_Revisions();
		}
		$wpdr->add_caps();
	}

	/**
	 * Delete the posts. (Taken from WP Test Suite).
	 */
	public static function wpTearDownAfterClass() {
		wp_delete_post( self::$editor_public_post, true );
		wp_delete_post( self::$editor_private_post, true );
	}

	/**
	 * Tests that the test Document stuctures are correct.
	 */
	public function test_structure() {
		self::verify_structure( self::$editor_public_post, 2, 2 );
		self::verify_structure( self::$editor_private_post, 1, 1 );
	}

	/**
	 * Make sure plugin is activated.
	 */
	public function test_class_exists() {
		console_log( ' class_exists' );

		self::assertTrue( class_exists( 'WP_Document_Revisions' ), 'Document_Revisions class not defined' );
	}


	/**
	 * Post type is properly registered.
	 */
	public function test_post_type_exists() {
		console_log( ' post_type_exists' );

		self::assertTrue( post_type_exists( 'document' ), 'Document post type does not exist' );
	}

	/**
	 * Workflow states exists and are initialized.
	 */
	public function test_workflow_states_exist() {
		console_log( ' workflow_states_exist' );

		// add terms.
		global $wpdr;
		$wpdr->initialize_workflow_states();
		$ws_terms = get_terms(
			array(
				'taxonomy'   => 'workflow_state',
				'hide_empty' => false,
			)
		);

		self::assertFalse( is_wp_error( $ws_terms ), 'Workflow State taxonomy does not exist' );
		self::assertCount( 4, $ws_terms, 'Initial Workflow States not properly registered' );
	}

	/**
	 * Check capabilities are correct for Non-logged-on User.
	 */
	public function test_non_logged_on_caps() {
		console_log( ' non_logged_on_caps' );

		global $current_user;
		unset( $current_user );
		$usr = wp_set_current_user( 0 );
		wp_cache_flush();

		self::assertFalse( $usr->has_cap( 'edit_documents' ), 'Can edit_documents' );
		self::assertFalse( $usr->has_cap( 'edit_others_documents' ), 'Can edit_others_documents' );
		self::assertFalse( $usr->has_cap( 'edit_private_documents' ), 'Can edit_private_documents' );
		self::assertFalse( $usr->has_cap( 'edit_published_documents' ), 'Can edit_published_documents' );
		self::assertFalse( $usr->has_cap( 'read_documents' ), 'Can read_documents' );
		self::assertFalse( $usr->has_cap( 'read_document_revisions' ), 'Can read_document_revisions' );
		self::assertFalse( $usr->has_cap( 'read_private_documents' ), 'Can read_private_documents' );
		self::assertFalse( $usr->has_cap( 'delete_documents' ), 'Can delete_documents' );
		self::assertFalse( $usr->has_cap( 'delete_others_documents' ), 'Can delete_others_documents' );
		self::assertFalse( $usr->has_cap( 'delete_private_documents' ), 'Can delete_private_documents' );
		self::assertFalse( $usr->has_cap( 'delete_published_documents' ), 'Can delete_published_documents' );
		self::assertFalse( $usr->has_cap( 'publish_documents' ), 'Can publish_documents' );
		self::assertFalse( $usr->has_cap( 'override_document_lock' ), 'Can override_document_lock' );
	}

	/**
	 * Check capabilities are correct subscriber User.
	 */
	public function test_subscriber_caps() {
		console_log( ' subscriber_caps' );

		global $current_user;
		unset( $current_user );
		$usr = wp_set_current_user( self::$users['subscriber']->ID );
		wp_cache_flush();

		self::assertTrue( current_user_can( 'subscriber' ), 'Not author role' );
		self::assertFalse( $usr->has_cap( 'edit_documents' ), 'Can edit_documents' );
		self::assertFalse( $usr->has_cap( 'edit_others_documents' ), 'Can edit_others_documents' );
		self::assertFalse( $usr->has_cap( 'edit_private_documents' ), 'Can edit_private_documents' );
		self::assertFalse( $usr->has_cap( 'edit_published_documents' ), 'Can edit_published_documents' );
		self::assertTrue( $usr->has_cap( 'read_documents' ), 'Cannot read_documents' );
		self::assertFalse( $usr->has_cap( 'read_document_revisions' ), 'Can read_document_revisions' );
		self::assertFalse( $usr->has_cap( 'read_private_documents' ), 'Can read_private_documents' );
		self::assertFalse( $usr->has_cap( 'delete_documents' ), 'Can delete_documents' );
		self::assertFalse( $usr->has_cap( 'delete_others_documents' ), 'Can delete_others_documents' );
		self::assertFalse( $usr->has_cap( 'delete_private_documents' ), 'Can delete_private_documents' );
		self::assertFalse( $usr->has_cap( 'delete_published_documents' ), 'Can delete_published_documents' );
		self::assertFalse( $usr->has_cap( 'publish_documents' ), 'Can publish_documents' );
		self::assertFalse( $usr->has_cap( 'override_document_lock' ), 'Can override_document_lock' );
	}

	/**
	 * Check capabilities are correct contributor User.
	 */
	public function test_contributor_caps() {
		console_log( ' contributor_caps' );

		global $current_user;
		unset( $current_user );
		$usr = wp_set_current_user( self::$users['contributor']->ID );
		wp_cache_flush();

		self::assertTrue( current_user_can( 'contributor' ), 'Not author role' );
		self::assertTrue( $usr->has_cap( 'edit_documents' ), 'Cannot edit_documents' );
		self::assertFalse( $usr->has_cap( 'edit_others_documents' ), 'Can edit_others_documents' );
		self::assertFalse( $usr->has_cap( 'edit_private_documents' ), 'Can edit_private_documents' );
		self::assertFalse( $usr->has_cap( 'edit_published_documents' ), 'Can edit_published_documents' );
		self::assertTrue( $usr->has_cap( 'read_documents' ), 'Cannot read_documents' );
		self::assertTrue( $usr->has_cap( 'read_document_revisions' ), 'Cannot read_document_revisions' );
		self::assertFalse( $usr->has_cap( 'read_private_documents' ), 'Can read_private_documents' );
		self::assertTrue( $usr->has_cap( 'delete_documents' ), 'Cannot delete_documents' );
		self::assertFalse( $usr->has_cap( 'delete_others_documents' ), 'Can delete_others_documents' );
		self::assertFalse( $usr->has_cap( 'delete_private_documents' ), 'Can delete_private_documents' );
		self::assertFalse( $usr->has_cap( 'delete_published_documents' ), 'Can delete_published_documents' );
		self::assertFalse( $usr->has_cap( 'publish_documents' ), 'Can publish_documents' );
		self::assertFalse( $usr->has_cap( 'override_document_lock' ), 'Can override_document_lock' );
	}

	/**
	 * Check capabilities are correct for Author.
	 */
	public function test_author_caps() {
		console_log( ' author_caps' );

		global $current_user;
		unset( $current_user );
		$usr = wp_set_current_user( self::$users['author']->ID );
		wp_cache_flush();

		self::assertTrue( current_user_can( 'author' ), 'Not author role' );
		self::assertTrue( $usr->has_cap( 'edit_documents' ), 'Cannot edit_documents' );
		self::assertFalse( $usr->has_cap( 'edit_others_documents' ), 'Can edit_others_documents' );
		self::assertFalse( $usr->has_cap( 'edit_private_documents' ), 'Can edit_private_documents' );
		self::assertTrue( $usr->has_cap( 'edit_published_documents' ), 'Cannot edit_published_documents' );
		self::assertTrue( $usr->has_cap( 'read_documents' ), 'Cannot read_documents' );
		self::assertTrue( $usr->has_cap( 'read_document_revisions' ), 'Cannot read_document_revisions' );
		self::assertFalse( $usr->has_cap( 'read_private_documents' ), 'Can read_private_documents' );
		self::assertTrue( $usr->has_cap( 'delete_documents' ), 'Cannot delete_documents' );
		self::assertFalse( $usr->has_cap( 'delete_others_documents' ), 'Can delete_others_documents' );
		self::assertFalse( $usr->has_cap( 'delete_private_documents' ), 'Can delete_private_documents' );
		self::assertTrue( $usr->has_cap( 'delete_published_documents' ), 'Cannot delete_published_documents' );
		self::assertTrue( $usr->has_cap( 'publish_documents' ), 'Cannot publish_documents' );
		self::assertFalse( $usr->has_cap( 'override_document_lock' ), 'Can override_document_lock' );
	}

	/**
	 * Check capabilities are correct for Editor.
	 */
	public function test_editor_caps() {
		console_log( ' editor_caps' );

		global $current_user;
		unset( $current_user );
		$usr = wp_set_current_user( self::$users['editor']->ID );
		wp_cache_flush();

		self::assertTrue( current_user_can( 'editor' ), 'Not editor role' );
		self::assertTrue( $usr->has_cap( 'edit_documents' ), 'Cannot edit_documents' );
		self::assertTrue( $usr->has_cap( 'edit_others_documents' ), 'Cannot edit_others_documents' );
		self::assertTrue( $usr->has_cap( 'edit_private_documents' ), 'Cannot edit_private_documents' );
		self::assertTrue( $usr->has_cap( 'edit_published_documents' ), 'Cannot edit_published_documents' );
		self::assertTrue( $usr->has_cap( 'read_documents' ), 'Cannot read_documents' );
		self::assertTrue( $usr->has_cap( 'read_document_revisions' ), 'Cannot read_document_revisions' );
		self::assertTrue( $usr->has_cap( 'read_private_documents' ), 'Cannot read_private_documents' );
		self::assertTrue( $usr->has_cap( 'delete_documents' ), 'Cannot delete_documents' );
		self::assertTrue( $usr->has_cap( 'delete_others_documents' ), 'Cannot delete_others_documents' );
		self::assertTrue( $usr->has_cap( 'delete_private_documents' ), 'Cannot delete_private_documents' );
		self::assertTrue( $usr->has_cap( 'delete_published_documents' ), 'Cannot delete_published_documents' );
		self::assertTrue( $usr->has_cap( 'publish_documents' ), 'Cannot publish_documents' );
		self::assertTrue( $usr->has_cap( 'override_document_lock' ), 'Cannot override_document_lock' );
	}

	/**
	 * Validate the get_attachments function are few different ways.
	 */
	public function test_get_attachments() {
		console_log( ' get_attachments' );

		global $wpdr;

		global $current_user;
		unset( $current_user );
		wp_set_current_user( self::$users['editor']->ID );
		wp_cache_flush();

		// Use editor public post.

		// grab an attachment.
		$attachments = $wpdr->get_attachments( self::$editor_public_post );
		$attachment  = end( $attachments );

		// grab a revision.
		$revisions = $wpdr->get_revisions( self::$editor_public_post );
		$revision  = end( $revisions );

		// get as postID.
		self::assertCount( 2, $attachments, 'Bad attachment count via get_attachments as postID' );

		// get as Object.
		self::assertCount( 2, $wpdr->get_attachments( get_post( self::$editor_public_post ) ), 'Bad attachment count via get_attachments as Object' );

		// get as a revision.
		self::assertCount( 2, $wpdr->get_attachments( $revision->ID ), 'Bad attachment count via get_attachments as revisionID' );

		// get as attachment.
		self::assertCount( 2, $wpdr->get_attachments( $attachment->ID ), 'Bad attachment count via get_attachments as attachmentID' );
	}

	/**
	 * Verify the get_file_Type function works.
	 */
	public function test_file_type() {
		console_log( ' file_type' );

		global $wpdr;

		// grab an attachment.
		$attachments = $wpdr->get_attachments( self::$editor_private_post );
		$attachment  = end( $attachments );

		self::assertEquals( '.txt', $wpdr->get_file_type( self::$editor_private_post ), "Didn't detect filetype via document ID" );
		self::assertEquals( '.txt', $wpdr->get_file_type( $attachment->ID ), "Didn't detect filetype via attachment ID" );
	}

	/**
	 * Make sure get_revisions() works.
	 */
	public function test_get_revisions() {
		console_log( ' get_revisions' );

		global $wpdr;

		self::assertEquals( 3, count( $wpdr->get_revisions( self::$editor_public_post ) ) );
		self::assertEquals( 2, count( $wpdr->get_revisions( self::$editor_private_post ) ) );
	}

	/**
	 * Test get_revision_number().
	 */
	public function test_get_revision_number() {
		console_log( ' get_revision_number' );

		global $wpdr;

		$revisions = $wpdr->get_revisions( self::$editor_private_post );
		$last      = end( $revisions );
		self::assertEquals( 1, $wpdr->get_revision_number( $last->ID ) );
		self::assertEquals( $last->ID, $wpdr->get_revision_id( 1, self::$editor_private_post ) );
	}

	/**
	 * Tests verify_post_type() with the various ways used throughout
	 */
	public function test_verify_post_type() {
		console_log( ' verify_post_type' );

		global $wpdr;

		$_GET['post_type'] = 'document';
		self::assertTrue( $wpdr->verify_post_type(), 'verify post type via explicit' );
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended
		unset( $_GET['post_type'] );

		$_GET['post'] = self::$editor_public_post;
		self::assertTrue( $wpdr->verify_post_type(), 'verify post type via get' );
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended
		unset( $_GET['post'] );

		$_REQUEST['post_id'] = self::$editor_public_post;
		self::assertTrue( $wpdr->verify_post_type(), 'verify post type via request (post_id)' );
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended
		unset( $_REQUEST['post_id'] );

		// phpcs:disable WordPress.WP.GlobalVariablesOverride.Prohibited
		global $post;
		$post = get_post( self::$editor_public_post );
		// phpcs:enable WordPress.WP.GlobalVariablesOverride.Prohibited

		self::assertTrue( $wpdr->verify_post_type( $post ), 'verify post type via global ' . $post->ID );
		unset( $post );

		self::assertTrue( $wpdr->verify_post_type( self::$editor_public_post ) );
	}

}
