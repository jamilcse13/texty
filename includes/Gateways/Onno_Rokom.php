<?php

namespace Texty\Gateways;

use Exception;
use SoapClient;
use WP_Error;

/**
 * Onno_Rokom Class
 *
 * @see https://www.onnorokomsms.com/Features/DeveloperApi
 */
class Onno_Rokom implements GatewayInterface {

    /**
     * API Endpoint
     */
    const ENDPOINT = 'https://api2.onnorokomsms.com/sendsms.asmx?wsdl';

    /**
     * Get the name
     *
     * @return string
     */
    public function name() {
        return __( 'Onno Rokom SMS', 'texty' );
    }

    /**
     * Get the name
     *
     * @return string
     */
    public function description() {
        return sprintf(
            // translators: URL to Twilio settings and help docs
            __(
                'Send SMS with Onno Rokom SMS Service. Follow <a href="%1$s" target="_blank">this link</a> to get the username and password. Make sure your server has installed <a href="%2$s" target="_blank">SOAP extension</a>.',
                'texty'
            ),
            'https://www.onnorokomsms.com/',
            'https://sourceforge.net/projects/phpsoaptoolkit/'
        );
    }

    /**
     * Get the logo
     *
     * @return string
     */
    public function logo() {
        return TEXTY_URL . '/assets/images/onnorokom.png';
    }

    /**
     * Get the settings
     *
     * @return array
     */
    public function get_settings() {
        $creds = texty()->settings()->get( 'onno_rokom' );

        return [
            'username' => [
                'name'  => __( 'Username', 'texty' ),
                'type'  => 'text',
                'value' => isset( $creds['username'] ) ? $creds['username'] : '',
                'help'  => '',
            ],
            'password' => [
                'name'  => __( 'Password', 'texty' ),
                'type'  => 'password',
                'value' => isset( $creds['password'] ) ? $creds['password'] : '',
                'help'  => '',
            ],
            'mask_name' => [
                'name'  => __( 'Mask Name', 'texty' ),
                'type'  => 'text',
                'value' => isset( $creds['mask_name'] ) ? $creds['mask_name'] : '',
                'help'  => __( 'Mask Name which is allowed to your client panel', 'texty' ),
            ],
        ];
    }

    /**
     * Send SMS
     *
     * @param string $to
     * @param string $message
     *
     * @return WP_Error|true
     */
    public function send( $to, $message ) {
        $creds = texty()->settings()->get( 'onno_rokom' );

        try {
            $soapClient = new SoapClient( self::ENDPOINT );

            $paramArray = [
                'userName'     => $creds['username'],
                'userPassword' => $creds['password'],
                'mobileNumber' => $to,
                'smsText'      => $message,
                'type'         => '1',
                'maskName'     => $creds['mask_name'],
                'campaignName' => '',
            ];

            $resp  = $soapClient->__call( 'OneToOne', [$paramArray] );
            $value = $resp->OneToOneResult;

            if ( $error = $this->parseResponseForError( $value ) ) {
                return new WP_Error(
                    $error['code'],
                    $error['message']
                );
            }
        } catch ( Exception $e ) {
        }

        return true;
    }

    /**
     * Validate a REST API request
     *
     * @param WP_REST_Request $request
     *
     * @return WP_Error|true
     */
    public function validate( $request ) {
        $creds = $request->get_param( 'onno_rokom' );

        $args = [
            'userName'           => $creds['username'],
            'userPassword'       => $creds['password'],
        ];

        try {
            $soapClient   = new SoapClient( self::ENDPOINT );
            $resp         = $soapClient->__call( 'GetBalance', [ $args ] );
            $value        = $resp->GetBalanceResult;

            if ( $error = $this->parseResponseForError( $value ) ) {
                return new WP_Error(
                    $error['code'],
                    $error['message']
                );
            }
        } catch ( Exception $e ) {
            return new WP_Error(
                $e->code,
                $e->getMessage()
            );
        }

        return [
            'username'    => $creds['username'],
            'password'    => $creds['password'],
        ];
    }

    private function parseResponseForError( $resp ) {
        $errors = [
            '1901' => 'Parameter content missing',
            '1902' => 'Invalid user/pass',
            '1903' => 'Not enough balance',
            '1905' => 'Invalid destination number',
            '1906' => 'Operator Not found',
            '1907' => 'Invalid mask Name',
            '1908' => 'Sms body too long',
            '1909' => 'Duplicate campaign Name',
            '1910' => 'Invalid message',
            '1911' => 'Too many Sms Request Please try less then 10000 in one request',
        ];

        foreach ( $errors as $code => $msg ) {
            if ( strpos( $resp, "$code||" ) !== false ) {
                return [
                    'code'    => $code,
                    'message' => $msg,
                ];
            }
        }
    }
}
