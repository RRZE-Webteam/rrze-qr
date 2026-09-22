<?php

namespace RRZE\QR;
defined( 'ABSPATH' ) || exit;

/**
 * Handles the Settings page, Options reading and Option init
 */
class Settings
{

    /**
     * GETTERS AND SETTERS
     */

    /**
     * Returns the user Preferences for QR Code generation
     *
     * @return array{foreground: string, background: string)}
     */
    public function getPreferences(): array
    {
        $storedUserPreferences = get_option( 'rrze_qr_defaults', [] );
        $storedUserPreferences = is_array( $storedUserPreferences ) ? $storedUserPreferences : [];

        $newUserPreferences = [
            'foreground' => Utilities::sanitizeForegroundHexColor( $storedUserPreferences[ 'foreground' ] ?? get_option( 'rrze_qr_foreground', 'black' ) ),
            'background' => Utilities::sanitizeBackgroundHexColor( $storedUserPreferences[ 'foreground' ] ?? get_option( 'rrze_qr_foreground', 'black' ) )
        ];

        if ( !Utilities::isValidColorPair( $newUserPreferences ) ) {
            $newUserPreferences = [ 'foreground' => '#000000', 'background' => '#ffffff' ];
        }

        $newUserPreferences[ 'size' ] => Settings::normalizeSize( $storedUserPreferences[ 'size' ] ?? null ) ?? 300;
        return $newUserPreferences;
    }

    /**
     * Saves Ajax Defaults
     *
     * @return void
     */
    public function saveAjaxDefaults(): void
    {
        check_ajax_referer( 'rrze-qr-nonce', 'nonce' );
        if ( !current_user_can( 'manage_options' ) ) {
            wp_send_json_error( __( 'Only administrators can save site defaults.', 'rrze-qr' ), 403 );
        }
        $foregroundColor = Utilities::normalizeColor( isset( $_POST[ 'foreground' ] ) ? wp_unslash( $_POST[ 'foreground' ] ) : null );
        $backgroundColor = Utilities::normalizeColor( isset( $_POST[ 'background' ] ) ? wp_unslash( $_POST[ 'background' ] ) : null, true );
        $size = Utilities::normalizeSize( $_POST[ 'size' ] ?? null );
        if ( $size === null ) {
            wp_send_json_error( __( 'Enter a whole number between 128 and 4096 pixels.', 'rrze-qr' ), 400 );
        }
        if ( $foregroundColor === null || $backgroundColor === null ) {
            wp_send_json_error( __( 'Choose valid colors and an export size.', 'rrze-qr' ), 400 );
        }
        $defaults = [ 'foreground' => $foregroundColor, 'background' => $backgroundColor, 'size' => (int)$size ];
        if ( Utilities::isValidColorPair( [ 'foreground' => $foregroundColor, 'background' => $backgroundColor ] ) ) {
            wp_send_json_error( __( 'Foreground and background must be different colors.', 'rrze-qr' ), 400 );
        }
        update_option( 'rrze_qr_defaults', $defaults );
        if ( get_option( 'rrze_qr_defaults' ) !== $defaults ) {
            wp_send_json_error( __( 'The defaults could not be saved. Please try again.', 'rrze-qr' ), 500 );
        }
        wp_send_json_success( $defaults );
    }

    /**
     * Migration of old Presets rrze_qr_color → rrze_qr_foreground / rrze_qr_background.
     */
    public function migrateLegacyColorOption(): void
    {
        if (get_option('rrze_qr_color_legacy_migrated')) {
            return;
        }

        $old = get_option('rrze_qr_color');
        if ($old === false || $old === '') {
            update_option('rrze_qr_color_legacy_migrated', '1');
            return;
        }

        $legacySingle = ['black' => 'black_on_white', '#036' => 'fau_on_white'];
        if (isset($legacySingle[$old])) {
            $old = $legacySingle[$old];
        }

        $schemeMap = [
            'black_on_white' => ['black', 'white'],
            'white_on_black' => ['white', 'black'],
            'fau_on_white' => ['fau', 'white'],
            'white_on_fau' => ['white', 'fau'],
            'black_on_transparent' => ['black', 'transparent'],
            'white_on_transparent' => ['white', 'transparent'],
            'fau_on_transparent' => ['fau', 'transparent'],
            'white_on_fau_transparent' => ['white', 'transparent'],
        ];

        $pair = isset($schemeMap[$old]) ? $schemeMap[$old] : $schemeMap['black_on_white'];
        update_option('rrze_qr_foreground', $pair[0]);
        update_option('rrze_qr_background', $pair[1]);
        delete_option('rrze_qr_color');
        update_option('rrze_qr_color_legacy_migrated', '1');
    }

    /**
     * Generates the Markup for the QRCode Generator Admin Page
     * @return void
     */
    public function CreateMarkupForAdminPageQRCodeGenerator()
    {
        if (!$this->rrze_qr_can_generate()) {
            wp_die(esc_html__('You do not have permission to generate QR codes.', 'rrze-qr'), '', ['response' => 403]);
        }
        $this->rrze_qr_workspace_context();

        $html = '<div class="wrap rrze-qr-workspace">';
        $html .= '<h1>' . esc_html_e('QR-Codes', 'rrze-qr') . '</h1>'
        $html .= '<p class="rrze-qr-intro">' . esc_html_e('Create a QR code, adjust its appearance, and download it as a PNG.', 'rrze-qr') . '</p>';
        $html .= '<div id="rrze-qr-app"></div>';
        $html .= '<noscript><p>' . esc_html_e('Enable JavaScript to use the QR code generator.', 'rrze-qr') . '</p></noscript>';
        $html .= '</div>';

        echo($html);
    }
}