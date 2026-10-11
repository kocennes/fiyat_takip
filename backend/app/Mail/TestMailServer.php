<?php

/**
 * Invoice Ninja (https://invoiceninja.com).
 *
 * @link https://github.com/invoiceninja/invoiceninja source repository
 *
 * @copyright Copyright (c) 2026. Invoice Ninja LLC (https://invoiceninja.com)
 *
 * @license https://www.elastic.co/licensing/elastic-license
 */

namespace App\Mail;

use Illuminate\Mail\Mailable;

class TestMailServer extends Mailable
{
    public $support_messages;

    public $from_email;

    public function __construct($support_messages, $from_email)
    {
        $this->support_messages = $support_messages;
        $this->from_email = $from_email;
    }

    /**
     * Test Server mail.
     *
     * @return $this
     */
    public function build()
    {
        $settings = new \stdClass();
        $settings->primary_color = '#4caf50';
        $settings->email_style = 'dark';
        $settings->email_alignment = 'left';

        // Callers pass a fixed English sentence; show it in the user's language.
        $support_message = $this->support_messages === 'Email Server Works!'
            ? ctrans('texts.bis_email_server_works')
            : $this->support_messages;

        return $this->from(config('mail.from.address'), config('mail.from.name'))
            ->subject(ctrans('texts.bis_test_email_subject'))
            ->markdown('email.support.message', [
                'support_message' => $support_message,
                'system_info' => '',
                'laravel_log' => [],
                'settings' => $settings,
            ]);
    }
}
