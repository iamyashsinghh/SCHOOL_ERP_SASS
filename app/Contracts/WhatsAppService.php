<?php

namespace App\Contracts;

interface WhatsAppService
{
    // send single whatsapp message
    public function sendWhatsApp(array $recipient, array $params = []): mixed;

    // send same whatsapp message to multiple recipients
    public function sendBulkWhatsApp(array $recipients, array $params = []): mixed;

    // send customized whatsapp message to multiple recipients
    public function sendCustomizedWhatsApp(array $recipients, array $params = []): mixed;
}
