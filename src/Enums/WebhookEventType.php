<?php

declare(strict_types=1);

namespace KobaltDigital\AhaSend\Enums;

use KobaltDigital\AhaSend\Events\ContactAddedToList;
use KobaltDigital\AhaSend\Events\ContactComplained;
use KobaltDigital\AhaSend\Events\ContactCreated;
use KobaltDigital\AhaSend\Events\ContactDeleted;
use KobaltDigital\AhaSend\Events\ContactRemovedFromList;
use KobaltDigital\AhaSend\Events\ContactSubscribed;
use KobaltDigital\AhaSend\Events\ContactUnsubscribed;
use KobaltDigital\AhaSend\Events\ContactUpdated;
use KobaltDigital\AhaSend\Events\DomainDnsError;
use KobaltDigital\AhaSend\Events\DomainSendingTypeChanged;
use KobaltDigital\AhaSend\Events\MessageBounced;
use KobaltDigital\AhaSend\Events\MessageClicked;
use KobaltDigital\AhaSend\Events\MessageComplained;
use KobaltDigital\AhaSend\Events\MessageDelivered;
use KobaltDigital\AhaSend\Events\MessageFailed;
use KobaltDigital\AhaSend\Events\MessageOpened;
use KobaltDigital\AhaSend\Events\MessageReception;
use KobaltDigital\AhaSend\Events\MessageRouting;
use KobaltDigital\AhaSend\Events\MessageSuppressed;
use KobaltDigital\AhaSend\Events\MessageTransientError;
use KobaltDigital\AhaSend\Events\MessageUnsubscribed;
use KobaltDigital\AhaSend\Events\SuppressionCreated;
use KobaltDigital\AhaSend\Events\SuppressionDeleted;
use KobaltDigital\AhaSend\Events\WebhookEvent;

/**
 * Every webhook event type AhaSend can deliver.
 *
 * @see https://ahasend.com/docs/api-reference/webhooks
 */
enum WebhookEventType: string
{
    case MessageReception = 'message.reception';
    case MessageDelivered = 'message.delivered';
    case MessageTransientError = 'message.transient_error';
    case MessageFailed = 'message.failed';
    case MessageBounced = 'message.bounced';
    case MessageSuppressed = 'message.suppressed';
    case MessageOpened = 'message.opened';
    case MessageClicked = 'message.clicked';
    case MessageUnsubscribed = 'message.unsubscribed';
    case MessageComplained = 'message.complained';
    case MessageRouting = 'message.routing';
    case SuppressionCreated = 'suppression.created';
    case SuppressionDeleted = 'suppression.deleted';
    case DomainDnsError = 'domain.dns_error';
    case DomainSendingTypeChanged = 'domain.sending_type_changed';
    case ContactCreated = 'contact.created';
    case ContactUpdated = 'contact.updated';
    case ContactDeleted = 'contact.deleted';
    case ContactAddedToList = 'contact.added_to_list';
    case ContactRemovedFromList = 'contact.removed_from_list';
    case ContactSubscribed = 'contact.subscribed';
    case ContactUnsubscribed = 'contact.unsubscribed';
    case ContactComplained = 'contact.complained';

    /**
     * The Laravel event that is dispatched for this webhook event type.
     *
     * @return class-string<WebhookEvent>
     */
    public function eventClass(): string
    {
        return match ($this) {
            self::MessageReception => MessageReception::class,
            self::MessageDelivered => MessageDelivered::class,
            self::MessageTransientError => MessageTransientError::class,
            self::MessageFailed => MessageFailed::class,
            self::MessageBounced => MessageBounced::class,
            self::MessageSuppressed => MessageSuppressed::class,
            self::MessageOpened => MessageOpened::class,
            self::MessageClicked => MessageClicked::class,
            self::MessageUnsubscribed => MessageUnsubscribed::class,
            self::MessageComplained => MessageComplained::class,
            self::MessageRouting => MessageRouting::class,
            self::SuppressionCreated => SuppressionCreated::class,
            self::SuppressionDeleted => SuppressionDeleted::class,
            self::DomainDnsError => DomainDnsError::class,
            self::DomainSendingTypeChanged => DomainSendingTypeChanged::class,
            self::ContactCreated => ContactCreated::class,
            self::ContactUpdated => ContactUpdated::class,
            self::ContactDeleted => ContactDeleted::class,
            self::ContactAddedToList => ContactAddedToList::class,
            self::ContactRemovedFromList => ContactRemovedFromList::class,
            self::ContactSubscribed => ContactSubscribed::class,
            self::ContactUnsubscribed => ContactUnsubscribed::class,
            self::ContactComplained => ContactComplained::class,
        };
    }
}
