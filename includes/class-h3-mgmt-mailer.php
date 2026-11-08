<?php
require_once __DIR__ . "/class-h3-mgmt-security.php";

/**
 * H3_MGMT_Mailer class.
 *
 * This class contains properties and methods to
 * send emails.
 *
 * It receives instructions from
 * @see class H3_MGMT_Admin_Emails
 * and
 * @see class H3_MGMT_Registrations
 *
 * @since 1.0
 */

if ( ! class_exists( 'H3_MGMT_Mailer' ) ) :

class H3_MGMT_Mailer {

	/**
	 * Compatibility entry point for retired context-free automatic responses.
	 *
	 * Automatic team mail now uses the event-scoped transactional queue;
	 * this method returns false and never uses global templates.
	 *
	 * @since 1.0
	 * @access public
	 */
	public function auto_response( $id, $action, $strings = array(), $type = 'id', $language = 'en' ) {
        // Automatic messages are created atomically by TeamManager with an explicit event.
        // Calls without that context must never fall back to a global template.
        return false;
    }

	/**
	 * Sends mails
	 *
	 * @todo add attachments and finalize html messages
	 *
	 * @since 1.0
	 * @access public
	 */
	public function send( $receipient, $subject, $message_pre, $from_name = NULL, $from_email = NULL, $content_type = NULL ) {
		if (preg_match('/[\r\n]/', (string) $subject . (string) $from_name . (string) $from_email)) { return false; }
        $subject = sanitize_text_field((string) $subject);
        $message = wordwrap($message_pre, 70);

		if( ! is_array( $receipient ) ) {
			$receipient = array( $receipient );
		}
                
		$headers = "From: ";
		if( $from_name == NULL ) {
			$headers .= "Tramprennen ";//$headers .= "Tramprennen ";
		} else {
			$headers .= $from_name . " ";
		}
		if( $from_email == NULL ) {
			$headers .= "<no-reply@tramprennen.org>" . "\r\n";
			$headers .= "X-Sender: <no-reply@tramprennen.org>" . "\r\n";
		} else {
			$headers .= $from_email . "\r\n";
			$headers .= "X-Sender: <" . $from_email . ">" . "\r\n";
		}
		$headers .= "X-Mailer: PHP" . "\r\n";
		$headers .= "X-Priority: 1" . "\r\n";
		$headers .= "Mime-Version: 1.0" . "\r\n";
		if( $content_type == 'html' ) {
			$headers .= "Content-Type: text/plain; charset=UTF-8" . "\r\n"; // change to html
		} else {
			$headers .= "Content-Type: text/plain; charset=UTF-8" . "\r\n";
		}

		foreach( $receipient as $to ) {
            if (!is_string($to) || !is_email($to)) { continue; }
			\Cor\Mgmt\WordPress\Services::get()->mail->send( $to, $subject, stripslashes( $message ), $headers );
		}
	}

} // class

endif; // class exists
