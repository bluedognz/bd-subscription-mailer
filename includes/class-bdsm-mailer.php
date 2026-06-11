<?php
/**
 * HTML email sending with template-tag replacement.
 *
 * @package BD_Subscription_Mailer
 */

defined( 'ABSPATH' ) || exit;

/**
 * Thin wrapper around wp_mail().
 */
class BDSM_Mailer {

	/**
	 * Replace {tags}, wrap in the HTML template and send.
	 *
	 * @param string $to      Recipient email.
	 * @param string $subject Subject (may contain tags).
	 * @param string $body    Body content (may contain tags).
	 * @param array  $tags    Tag => value map, keys without braces.
	 * @param string $cc      Optional CC address.
	 * @return bool Whether wp_mail() accepted the message.
	 */
	public static function send( $to, $subject, $body, array $tags = array(), $cc = '' ) {
		if ( ! is_email( $to ) ) {
			return false;
		}

		$subject = self::replace_tags( $subject, $tags );
		$body    = self::replace_tags( $body, $tags );
		$html    = self::wrap( wpautop( $body ) );

		$headers = array( 'Content-Type: text/html; charset=UTF-8' );
		if ( is_email( $cc ) && 0 !== strcasecmp( $cc, $to ) ) {
			$headers[] = 'Cc: ' . $cc;
		}

		return wp_mail( $to, $subject, $html, $headers );
	}

	/**
	 * Replace {tag} placeholders.
	 *
	 * @param string $content Content with placeholders.
	 * @param array  $tags    Tag => value map.
	 * @return string
	 */
	public static function replace_tags( $content, array $tags ) {
		$search  = array();
		$replace = array();
		foreach ( $tags as $tag => $value ) {
			$search[]  = '{' . $tag . '}';
			$replace[] = (string) $value;
		}
		return str_replace( $search, $replace, $content );
	}

	/**
	 * Wrap body HTML in the email template.
	 *
	 * @param string $body_html Inner HTML.
	 * @return string
	 */
	private static function wrap( $body_html ) {
		$bdsm_email_body = $body_html;
		$bdsm_site_name  = get_bloginfo( 'name' );

		ob_start();
		include BDSM_PLUGIN_DIR . 'templates/email-wrapper.php';
		return (string) ob_get_clean();
	}
}
