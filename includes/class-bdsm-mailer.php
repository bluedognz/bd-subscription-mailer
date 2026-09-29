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
	 * @param string $bcc     Optional BCC address (hidden from the recipient).
	 * @return bool Whether wp_mail() accepted the message.
	 */
	public static function send( $to, $subject, $body, array $tags = array(), $bcc = '' ) {
		if ( ! is_email( $to ) ) {
			return false;
		}

		$subject = self::replace_tags( $subject, $tags );
		$body    = self::replace_tags( $body, $tags );
		$html    = self::wrap( wpautop( $body ) );

		$headers   = array( 'Content-Type: text/html; charset=UTF-8' );
		$from      = bdsm_from_header();
		if ( '' !== $from ) {
			$headers[] = $from;
		}
		// BCC (not CC): the monitoring copy must stay hidden from the customer
		// so it never appears in headers or gets caught by Reply-All.
		if ( is_email( $bcc ) && 0 !== strcasecmp( $bcc, $to ) ) {
			$headers[] = 'Bcc: ' . $bcc;
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
