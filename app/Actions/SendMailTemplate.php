<?php

namespace App\Actions;

use App\Mail\CustomMail;
use App\Models\Config\Template;
use App\Support\TemplateParser;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Mail;
use Illuminate\Validation\ValidationException;

class SendMailTemplate
{
    use TemplateParser;

    public function execute(?string $email = null, ?string $code = null, mixed $template = null, array $variables = [], array $cc = [], array $attachments = []): void
    {
        if (! $email && ! empty($cc)) {
            $email = Arr::first($cc);
        }

        if (! $email) {
            return;
        }

        $mailTemplate = $template ?? Template::query()
            ->whereType('mail')
            ->whereCode($code)
            ->whereNotNull('enabled_at')
            ->first();

        if (! $mailTemplate) {
            return;
        }

        $mailTemplate = $this->parseTemplate($mailTemplate, $variables);

        $mailTemplate->content = $this->parseMail($mailTemplate->content);

        try {
            Mail::to($email)->cc($cc)->send(new CustomMail($mailTemplate, $attachments));
        } catch (\Exception $e) {
            throw ValidationException::withMessages(['message' => trans('general.errors.mail_send_error')]);
        }
    }
}
