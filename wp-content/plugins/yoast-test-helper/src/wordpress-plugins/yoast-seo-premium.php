<?php

namespace Yoast\WP\Test_Helper\WordPress_Plugins;

use WPSEO_Premium;

/**
 * Class to represent Local SEO.
 */
class Yoast_SEO_Premium implements WordPress_Plugin {

	/**
	 * Retrieves the plugin identifier.
	 *
	 * @return string The plugin identifier.
	 */
	public function get_identifier() {
		return 'wordpress-seo-premium';
	}

	/**
	 * Retrieves the plugin name.
	 *
	 * @return string The name of the plugin.
	 */
	public function get_name() {
		return 'Yoast SEO Premium';
	}

	/**
	 * Retrieves the version option name.
	 *
	 * @return string The name that holds the version.
	 */
	public function get_version_option_name() {
		return 'wpseo_premium_version';
	}

	/**
	 * Retrieves the version key.
	 *
	 * @return string The version key.
	 */
	public function get_version_key() {
		return '';
	}

	/**
	 * Retrieves the options.
	 *
	 * @return array The options.
	 */
	public function get_options() {
		return [ $this->get_version_option_name() ];
	}

	/**
	 * Resets a feature.
	 *
	 * @param string $feature Feature to reset.
	 *
	 * @return bool True on success.
	 */
	public function reset_feature( $feature ) {
		return false;
	}

	/**
	 * Retrieves the list of features.
	 *
	 * @return string[] List of features.
	 */
	public function get_features() {
		return [];
	}

	/**
	 * Retrieves the active version of the plugin.
	 *
	 * @return string The current version of the plugin.
	 */
	public function get_version_constant() {
		return \defined( 'WPSEO_Premium::PLUGIN_VERSION_NAME' ) ? WPSEO_Premium::PLUGIN_VERSION_NAME : \__( 'not active', 'yoast-test-helper' );
	}
}
