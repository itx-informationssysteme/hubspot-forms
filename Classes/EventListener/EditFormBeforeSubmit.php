<?php 

declare(strict_types=1);

namespace Itx\HubspotForms\EventListener;

use Itx\HubspotForms\Event\EditFormBeforeSubmitEvent;
use Exception;

final class EditFormBeforeSubmit
{
    public function __invoke(EditFormBeforeSubmitEvent $event): void {
        // Replace this with whatever
    }
}