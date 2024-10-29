<?php

declare(strict_types=1);

namespace Itx\HubspotForms\Event;

final class EditFormBeforeSubmitEvent
{
    public function __construct(
        private array $form,
        private array $message,
    ) {}

    public function getForm(): array
    {
        return $this->form;
    }

    public function setForm(array $form): void
    {
        $this->form = $form;
    }

    public function getMessage(): array
    {
        return $this->message;
    }

    public function setMessage(array $message): void
    {
        $this->message = $message;
    }
}
