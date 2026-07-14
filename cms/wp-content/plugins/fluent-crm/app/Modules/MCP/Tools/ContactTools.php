<?php

namespace FluentCrm\App\Modules\MCP\Tools;

use FluentCrm\App\Models\Subscriber;
use FluentCrm\App\Modules\MCP\Helpers\MCPHelper;
use FluentCrm\App\Services\ContactsQuery;

/**
 * Contact-centric MCP tools.
 *
 * Read tools (Phase 2): listContacts, getContact.
 * Write tools (Phase 3): upsertContact, bulkUpsertContacts, deleteContact,
 * applySegmentsToContacts, addContactNote.
 *
 * Each method delegates to existing FluentCRM services (ContactsQuery,
 * Subscriber model, Helper::deleteContacts, etc.) — no business-logic
 * duplication — and shapes the result through MCPHelper formatters.
 */
class ContactTools
{
    // -----------------------------------------------------------------
    // Read: list-contacts
    // -----------------------------------------------------------------

    public static function listContacts($params)
    {
        $params = (array) $params;

        // Reject up front if the caller passed an unsupported advanced_filters
        // shape — round-2 review #3.
        $validation = MCPHelper::validateUniversalFilter($params);
        if (is_wp_error($validation)) {
            return $validation;
        }

        $pagination = MCPHelper::paginationFromInput($params);

        $args = MCPHelper::buildContactsQueryArgs($params);
        $args['with'] = ['tags', 'lists'];

        if (!empty($params['include_custom_fields'])) {
            $args['custom_fields'] = true;
        }

        $cq = new ContactsQuery($args);
        MCPHelper::applyDateFilters($cq, $params);
        $paginated = $cq->paginate();

        return MCPHelper::formatContactList($paginated, !empty($params['include_custom_fields']));
    }

    // -----------------------------------------------------------------
    // Read: get-contact
    // -----------------------------------------------------------------

    public static function getContact($params)
    {
        $params = (array) $params;

        $defaultIncludes = ['notes', 'email_history', 'automations'];
        $include = isset($params['include']) && is_array($params['include']) && $params['include']
            ? array_values(array_intersect(
                $params['include'],
                ['notes', 'email_history', 'automations', 'activity', 'purchase_history', 'support_tickets', 'ai_summary', 'info_widgets']
            ))
            : $defaultIncludes;

        $contactId = isset($params['contact_id']) ? (int) $params['contact_id'] : 0;
        $email     = isset($params['email']) ? sanitize_email($params['email']) : '';

        $with = ['tags', 'lists'];

        $subscriber = null;
        if ($contactId) {
            $subscriber = Subscriber::with($with)->find($contactId);
        } elseif ($email) {
            $subscriber = Subscriber::with($with)->where('email', $email)->first();
        }

        if (!$subscriber) {
            if (!$contactId && !$email) {
                return MCPHelper::error('invalid_param', __('Provide contact_id or email', 'fluent-crm'));
            }
            return MCPHelper::error('not_found', __('Contact not found', 'fluent-crm'), array_filter([
                'contact_id' => $contactId ?: null,
                'email'      => $email ?: null,
            ]));
        }

        $data = MCPHelper::formatContactForMCP($subscriber, ['include' => $include]);

        // Defaults already inlined by formatContactForMCP — fill the optional ones.
        if (in_array('activity', $include, true)) {
            $data['activity'] = self::buildActivityTimeline($subscriber);
        }

        if (in_array('purchase_history', $include, true)) {
            $data['purchase_history'] = self::buildPurchaseHistory($subscriber);
        }

        if (in_array('support_tickets', $include, true)) {
            $data['support_tickets'] = self::buildSupportTickets($subscriber);
        }

        if (in_array('info_widgets', $include, true)) {
            $data['info_widgets'] = self::buildInfoWidgets($subscriber);
        }

        if (in_array('ai_summary', $include, true)) {
            $data['ai_summary'] = self::buildAiSummary($subscriber, !empty($params['generate_ai_summary']));
        }

        // Status-related context — surfaced inline so the agent can see why a
        // contact is unsubscribed without an extra call.
        if (in_array($subscriber->status, ['unsubscribed', 'bounced', 'complained', 'spammed'], true)) {
            $data['unsubscribe_reason'] = method_exists($subscriber, 'unsubscribeReason')
                ? $subscriber->unsubscribeReason()
                : null;
        }

        return $data;
    }

    /**
     * Activity timeline = tracked events. The fc_event_tracking table is
     * created by the free plugin's migrations but may not exist on legacy
     * installs that never ran the migration. Probe with SHOW TABLES so we
     * never trigger wpdb's print_error (which leaks HTML into the response
     * body before the JSON envelope, even when the exception is caught).
     */
    private static function buildActivityTimeline($subscriber)
    {
        global $wpdb;
        $tableName = $wpdb->prefix . 'fc_event_tracking';
        $exists = $wpdb->get_var($wpdb->prepare('SHOW TABLES LIKE %s', $tableName)) === $tableName;
        if (!$exists) {
            return [];
        }

        try {
            $events = $subscriber->trackingEvents()
                ->orderBy('id', 'DESC')
                ->limit(50)
                ->get();
        } catch (\Throwable $e) {
            return [];
        }

        $out = [];
        foreach ($events as $event) {
            $out[] = [
                'id'         => (int) $event->id,
                'event_key'  => $event->event_key,
                'title'      => $event->title,
                'value'      => $event->value,
                'provider'   => $event->provider ?? null,
                'counter'    => isset($event->counter) ? (int) $event->counter : null,
                'created_at' => MCPHelper::toIso8601($event->created_at),
            ];
        }
        return $out;
    }

    private static function buildPurchaseHistory($subscriber)
    {
        /**
         * Resolved per the existing FluentCRM commerce-provider filter chain.
         */
        $provider = apply_filters('fluentcrm_commerce_provider', '');
        if (!$provider) {
            return [];
        }
        $stat = apply_filters('fluent_crm/contact_purchase_stat_' . $provider, [], $subscriber->id);
        return is_array($stat) ? $stat : [];
    }

    private static function buildSupportTickets($subscriber)
    {
        // FluentSupport hooks this filter when active. Empty otherwise.
        return apply_filters('fluentcrm_get_support_tickets', [], $subscriber);
    }

    private static function buildInfoWidgets($subscriber)
    {
        /**
         * Filter that integrators (Pro, FluentSupport, FluentCart, etc.) push
         * widget data into. Surface the raw filter result; ContextTools agents
         * can interpret what's there.
         */
        $widgets = apply_filters('fluent_crm/contact_info_widgets', [], $subscriber);
        return is_array($widgets) ? $widgets : [];
    }

    private static function buildAiSummary($subscriber, $generate = false)
    {
        $cached = fluentcrm_get_subscriber_meta($subscriber->id, '_ai_summary');

        if ($cached && !$generate) {
            return [
                'summary'      => is_array($cached) ? ($cached['summary'] ?? '') : (string) $cached,
                'generated_at' => is_array($cached) ? ($cached['generated_at'] ?? null) : null,
                'cached'       => true,
            ];
        }

        if (!$generate) {
            return null;
        }

        // Honor existing AI controller; if it's missing or disabled, return
        // a structured signal rather than throwing.
        if (!class_exists('FluentCrm\\App\\Http\\Controllers\\AiController')) {
            return ['summary' => null, 'cached' => false, 'error' => 'ai_unavailable'];
        }

        $aiSettings = fluentcrm_get_option('ai_settings', []);
        if (empty($aiSettings['active_provider'])) {
            return ['summary' => null, 'cached' => false, 'error' => 'ai_provider_not_configured'];
        }

        // Generation requires the existing controller's prompt + provider call;
        // surface a dependency_missing-style signal so the agent can prompt the
        // user to enable AI rather than blocking the read.
        return [
            'summary' => null,
            'cached'  => false,
            'error'   => 'generation_not_supported_in_mcp_v1',
            'note'    => 'Trigger AI summary from the contact profile UI; cached value will appear on subsequent get-contact calls.',
        ];
    }

    // -----------------------------------------------------------------
    // Write: upsert-contact
    // -----------------------------------------------------------------

    public static function upsertContact($params)
    {
        $params = (array) $params;

        $contactId = isset($params['contact_id']) ? (int) $params['contact_id'] : 0;
        $email     = isset($params['email']) ? sanitize_email($params['email']) : '';
        $newEmail  = isset($params['new_email']) ? sanitize_email($params['new_email']) : '';

        if (!$contactId && !$email) {
            return MCPHelper::error('invalid_param', __('Provide contact_id or email', 'fluent-crm'));
        }

        $existing = null;
        if ($contactId) {
            $existing = Subscriber::find($contactId);
            if (!$existing) {
                return MCPHelper::error('not_found', __('Contact not found', 'fluent-crm'), ['contact_id' => $contactId]);
            }
            // Lookup-by-id with email mismatch is fine — id wins.
            $email = $existing->email;
        } else {
            $existing = Subscriber::where('email', $email)->first();
        }

        $ifExists = $params['if_exists'] ?? 'merge';
        if ($existing && $ifExists === 'skip') {
            return [
                'ok'      => true,
                'action'  => 'skipped',
                'contact' => MCPHelper::formatContactForMCP($existing, ['include' => ['notes', 'email_history', 'automations']]),
                'changes' => null,
            ];
        }
        if ($existing && $ifExists === 'error') {
            return MCPHelper::error('contact_exists', __('A contact with this email already exists', 'fluent-crm'), [
                'id' => (int) $existing->id,
            ]);
        }

        // Re-check the escalating capability if the agent asked us to create
        // missing tags/lists — defense in depth, even though the
        // permission_callback already enforced the base cap.
        $autoCreateTags  = !empty($params['auto_create_tags']);
        $autoCreateLists = !empty($params['auto_create_lists']);
        if (($autoCreateTags || $autoCreateLists)
            && !\FluentCrm\App\Services\PermissionManager::currentUserCan('fcrm_manage_contact_cats')) {
            return MCPHelper::error('forbidden', __('Creating new tags/lists requires fcrm_manage_contact_cats', 'fluent-crm'));
        }

        // Resolve add/remove segment payloads up-front so we can mention
        // resolution failures in the response without partially applying.
        $addTags    = MCPHelper::resolveTagIds($params['add_tags'] ?? [], $autoCreateTags);
        $removeTags = MCPHelper::resolveTagIds($params['remove_tags'] ?? [], false);
        $addLists   = MCPHelper::resolveListIds($params['add_lists'] ?? [], $autoCreateLists);
        $removeLists = MCPHelper::resolveListIds($params['remove_lists'] ?? [], false);

        // Capture the pre-rename / pre-update snapshot fields BEFORE any
        // mutation. The rename block below sets $existing->email to the new
        // value, so reading $existing->email after that point would return
        // the new email — operator-test report 2026-05-07 #9. The full
        // snapshot also feeds diffFields() so fields_updated correctly
        // reports 'email' on a rename.
        $previousStatus   = $existing ? $existing->status : null;
        $previousEmail    = $existing ? $existing->email : null;
        $previousSnapshot = $existing ? self::snapshotCompareFields($existing) : null;

        // Email rename: when an existing contact + new_email is provided, do
        // the rename in-place on the existing row BEFORE delegating to
        // createOrUpdate. createOrUpdate looks up by email — passing it the
        // new_email would not find a row and would create a new contact
        // (review B1 round 3). The save fires fluent_crm/contact_email_changed
        // through Subscriber::updateOrCreate's normal path because we then
        // call it with the new email as the lookup key.
        if ($existing && $newEmail && $newEmail !== $existing->email) {
            $oldEmail = $existing->email;
            // Make sure the new email isn't already used by another contact.
            $clash = Subscriber::where('email', $newEmail)->where('id', '!=', $existing->id)->first();
            if ($clash) {
                return MCPHelper::error('contact_exists', __('Another contact already uses the new_email — refusing to merge silently. Resolve manually or pick a different new_email.', 'fluent-crm'), [
                    'new_email'        => $newEmail,
                    'conflict_id'      => (int) $clash->id,
                    'subject_id'       => (int) $existing->id,
                ]);
            }
            $existing->email = $newEmail;
            $existing->save();
            do_action('fluent_crm/contact_email_changed', $existing, $oldEmail);
        }

        // Build the upsert payload — only fields actually provided. Lookup
        // email is the post-rename value (so createOrUpdate finds the same
        // row we just renamed).
        $payload = [
            'email' => $existing && $newEmail ? $newEmail : ($email ?: ($existing->email ?? null)),
        ];

        $passthru = ['first_name', 'last_name', 'prefix', 'phone', 'status', 'contact_type', 'date_of_birth', 'timezone', 'source'];
        foreach ($passthru as $field) {
            if (array_key_exists($field, $params) && $params[$field] !== null && $params[$field] !== '') {
                $payload[$field] = $params[$field];
            }
        }

        self::applyAddressShape($payload, $params['address'] ?? null);

        if (!empty($params['custom_fields']) && is_array($params['custom_fields'])) {
            // Validate against the registered schema. Unknown keys would
            // otherwise be silently dropped (operator-test report
            // 2026-05-07 #6) — fail closed so the agent can either
            // correct the slug or call get-crm-context for the schema.
            $diff = MCPHelper::diffCustomFields($params['custom_fields']);
            if (!empty($diff['unknown'])) {
                return MCPHelper::error('invalid_param', __('custom_fields contains slugs not in the contact custom-field schema. Refusing — silent-dropping makes the agent think the value persisted.', 'fluent-crm'), [
                    'unknown_custom_field_slugs' => $diff['unknown'],
                    'allowed_custom_field_slugs' => MCPHelper::knownContactCustomFieldSlugs(),
                    'tip' => 'Call get-crm-context and read enums.custom_fields_schema (or call options for the live registry) before retrying.',
                ]);
            }
            $payload['custom_values'] = $diff['known'];
        }

        // Only stamp source='mcp' on creation. On update, omit the field
        // entirely so the model preserves whatever signup source the contact
        // already has ("web", "checkout", "import", etc.). The agent can
        // still pass an explicit `source` to override this when needed.
        if (!$existing && (!isset($payload['source']) || $payload['source'] === '')) {
            $payload['source'] = 'mcp';
        } elseif ($existing && (!isset($payload['source']) || $payload['source'] === '')) {
            unset($payload['source']);
        }

        // The `Subscriber::updateOrCreate` path forwards through
        // FluentCrmApi('contacts')->createOrUpdate which fires the
        // contact-created/updated and status-change hooks we need.
        // ($previousStatus / $previousEmail were captured above, before
        // the rename block — see operator-test report 2026-05-07 #9.)
        $forceUpdate = true;
        $contact = FluentCrmApi('contacts')->createOrUpdate($payload, $forceUpdate, false);

        if (!$contact) {
            return MCPHelper::error('failed', __('Could not create or update the contact', 'fluent-crm'));
        }

        $action = !empty($contact->wasRecentlyCreated) ? 'created' : 'updated';

        // Apply delta segment changes.
        $tagsAdded = [];
        $tagsRemoved = [];
        $listsAdded = [];
        $listsRemoved = [];

        if (!empty($addTags['ids'])) {
            $contact->attachTags($addTags['ids']);
            foreach ($addTags['ids'] as $id) {
                $tagsAdded[] = ['id' => (int) $id];
            }
        }
        if (!empty($removeTags['ids'])) {
            $contact->detachTags($removeTags['ids']);
            foreach ($removeTags['ids'] as $id) {
                $tagsRemoved[] = ['id' => (int) $id];
            }
        }
        if (!empty($addLists['ids'])) {
            $contact->attachLists($addLists['ids']);
            foreach ($addLists['ids'] as $id) {
                $listsAdded[] = ['id' => (int) $id];
            }
        }
        if (!empty($removeLists['ids'])) {
            $contact->detachLists($removeLists['ids']);
            foreach ($removeLists['ids'] as $id) {
                $listsRemoved[] = ['id' => (int) $id];
            }
        }

        // Optional double opt-in trigger for newly-pending contacts.
        if ($contact->status === 'pending' && !empty($params['double_optin'])) {
            $contact->sendDoubleOptinEmail();
        }

        // Status-change reason: drop a system-style note for audit.
        if (!empty($params['status_change_reason']) && $previousStatus && $previousStatus !== $contact->status) {
            \FluentCrm\App\Models\SubscriberNote::create([
                'subscriber_id' => $contact->id,
                'type'          => 'note',
                'title'         => __('Status changed via MCP', 'fluent-crm'),
                'description'   => sanitize_text_field((string) $params['status_change_reason']),
            ]);
        }

        $contact = Subscriber::with(['tags', 'lists'])->find($contact->id);

        return [
            'ok'      => true,
            'action'  => $action,
            'contact' => MCPHelper::formatContactForMCP($contact, ['include' => ['notes', 'email_history', 'automations']]),
            'changes' => [
                'fields_updated'   => self::diffFields($previousSnapshot, $contact),
                'tags_added'       => $tagsAdded,
                'tags_removed'     => $tagsRemoved,
                'lists_added'      => $listsAdded,
                'lists_removed'    => $listsRemoved,
                'previous_status'  => $previousStatus,
                'current_status'   => $contact->status,
                'previous_email'   => $previousEmail,
                'current_email'    => $contact->email,
                'tags_created'     => $addTags['created'],
                'lists_created'    => $addLists['created'],
            ],
        ];
    }

    // -----------------------------------------------------------------
    // Write: delete-contact-note (round 3 review #11)
    // -----------------------------------------------------------------

    public static function deleteContactNote($params)
    {
        $params = (array) $params;
        $noteId = (int) ($params['note_id'] ?? 0);
        if (!$noteId) {
            return MCPHelper::error('invalid_param', __('note_id is required', 'fluent-crm'));
        }

        $note = \FluentCrm\App\Models\SubscriberNote::find($noteId);
        if (!$note) {
            return MCPHelper::error('not_found', __('Note not found', 'fluent-crm'), ['note_id' => $noteId]);
        }

        $deletedId        = (int) $note->id;
        $subscriberId     = (int) $note->subscriber_id;
        $title            = (string) $note->title;
        $note->delete();

        do_action('fluent_crm/note_deleted', $deletedId, $subscriberId);

        return [
            'ok'             => true,
            'action'         => 'deleted',
            'deleted_id'     => $deletedId,
            'subscriber_id'  => $subscriberId,
            'deleted_title'  => $title,
            'note'           => __('Note row removed. The contact\'s other notes and email history are unaffected.', 'fluent-crm'),
        ];
    }

    /**
     * Compute the would-create list for a dry-run preview. Skips numeric
     * inputs (those are id lookups, not creation candidates — review B3
     * round 3) and only flags string names that have no existing match.
     */
    private static function wouldCreateNames($items, $kind = 'tag')
    {
        $out = [];
        foreach ((array) $items as $item) {
            if (is_numeric($item) || $item === '' || $item === null) {
                continue;
            }
            $name = sanitize_text_field((string) $item);
            $slug = sanitize_title($name);
            if ($kind === 'list') {
                $hit = \FluentCrm\App\Models\Lists::where('title', $name)->orWhere('slug', $slug)->first();
            } else {
                $hit = \FluentCrm\App\Models\Tag::where('title', $name)->orWhere('slug', $slug)->first();
            }
            if (!$hit) {
                $out[] = $name;
            }
        }
        return array_values(array_unique($out));
    }

    /**
     * Map the agent-facing {line_1, line_2, city, state, postal_code,
     * country} shape onto the column-named payload that Subscriber
     * createOrUpdate consumes. Mutates $payload by reference. Shared
     * between upsert-contact and bulk-upsert-contacts so both stay in
     * lock-step (operator-test report 2026-05-07 #5).
     */
    private static function applyAddressShape(array &$payload, $address)
    {
        if (empty($address) || !is_array($address)) {
            return;
        }
        $map = [
            'line_1'      => 'address_line_1',
            'line_2'      => 'address_line_2',
            'city'        => 'city',
            'state'       => 'state',
            'postal_code' => 'postal_code',
            'country'     => 'country',
        ];
        foreach ($map as $key => $col) {
            if (isset($address[$key]) && $address[$key] !== '') {
                $payload[$col] = $address[$key];
            }
        }
    }

    /**
     * Snapshot the diff-relevant columns of a Subscriber before any
     * in-place mutation (rename, save). diffFields() compares against
     * this snapshot so fields_updated stays correct even after the row
     * has been written.
     *
     * @return array<string,string>
     */
    private static function snapshotCompareFields($subscriber)
    {
        $snapshot = [];
        foreach (self::compareFieldNames() as $field) {
            $snapshot[$field] = (string) ($subscriber->{$field} ?? '');
        }
        return $snapshot;
    }

    private static function compareFieldNames()
    {
        return ['email', 'first_name', 'last_name', 'prefix', 'phone', 'status', 'contact_type', 'address_line_1', 'address_line_2', 'city', 'state', 'postal_code', 'country', 'date_of_birth', 'timezone', 'source'];
    }

    /**
     * @param array<string,string>|null $before  Snapshot from snapshotCompareFields()
     * @param object                    $after   Subscriber model post-save
     */
    private static function diffFields($before, $after)
    {
        if (!$before) {
            return ['*'];
        }
        $changed = [];
        foreach (self::compareFieldNames() as $field) {
            if (($before[$field] ?? '') !== (string) ($after->{$field} ?? '')) {
                $changed[] = $field;
            }
        }
        return $changed;
    }

    // -----------------------------------------------------------------
    // Write: bulk-upsert-contacts
    // -----------------------------------------------------------------

    public static function bulkUpsertContacts($params)
    {
        $params = (array) $params;
        $contacts = (array) ($params['contacts'] ?? []);
        if (!$contacts) {
            return MCPHelper::error('invalid_param', __('contacts is required', 'fluent-crm'));
        }

        $maxBatch = (int) apply_filters('fluent_crm/mcp_bulk_cap', 500, 'bulk-upsert-contacts');
        if (count($contacts) > $maxBatch) {
            return MCPHelper::error('cap_reached', __('Too many contacts in a single call', 'fluent-crm'), [
                'max'    => $maxBatch,
                'matched' => count($contacts),
            ]);
        }

        $autoCreateTags  = isset($params['auto_create_tags']) ? (bool) $params['auto_create_tags'] : true;
        $autoCreateLists = isset($params['auto_create_lists']) ? (bool) $params['auto_create_lists'] : true;
        $ifExists        = $params['if_exists'] ?? 'merge';
        $doubleOptin     = !empty($params['double_optin']);

        if (($autoCreateTags || $autoCreateLists)
            && !\FluentCrm\App\Services\PermissionManager::currentUserCan('fcrm_manage_contact_cats')) {
            return MCPHelper::error('forbidden', __('Creating new tags/lists requires fcrm_manage_contact_cats', 'fluent-crm'));
        }

        $created = $updated = $skipped = $invalid = $warnings = [];

        foreach ($contacts as $row) {
            if (!is_array($row) || empty($row['email']) || !is_email($row['email'])) {
                $invalid[] = ['email' => $row['email'] ?? null, 'reason' => 'invalid_email'];
                continue;
            }

            $existing = Subscriber::where('email', sanitize_email($row['email']))->first();
            if ($existing && $ifExists === 'skip') {
                $skipped[] = ['id' => (int) $existing->id, 'email' => $existing->email];
                continue;
            }
            if ($existing && $ifExists === 'error') {
                $invalid[] = ['email' => $row['email'], 'reason' => 'contact_exists', 'id' => (int) $existing->id];
                continue;
            }

            // Resolve segments per-row.
            $tagIds  = MCPHelper::resolveTagIds((array) ($row['tags'] ?? []), $autoCreateTags);
            $listIds = MCPHelper::resolveListIds((array) ($row['lists'] ?? []), $autoCreateLists);

            $payload = $row;
            $payload['tags']  = $tagIds['ids'];
            $payload['lists'] = $listIds['ids'];

            // Same address-shape mapping as single upsert. Without this,
            // bulk silently dropped the {line_1,...,country} object —
            // operator-test report 2026-05-07 #5.
            self::applyAddressShape($payload, $row['address'] ?? null);

            // Same rule as upsert-contact: stamp source='mcp_bulk' only on
            // creation. On update, preserve the original source unless the
            // caller passed one explicitly.
            if (!$existing && (!isset($payload['source']) || $payload['source'] === '')) {
                $payload['source'] = 'mcp_bulk';
            } elseif ($existing && (!isset($payload['source']) || $payload['source'] === '')) {
                unset($payload['source']);
            }

            if (!empty($row['custom_fields']) && is_array($row['custom_fields'])) {
                // Same diff-against-schema gate as single upsert, but
                // surface unknown slugs as a per-row warning so one bad
                // row doesn't fail the whole batch (operator-test report
                // 2026-05-07 #6). Known keys still persist.
                $diff = MCPHelper::diffCustomFields($row['custom_fields']);
                if (!empty($diff['unknown'])) {
                    $warnings[] = [
                        'email'                       => $row['email'],
                        'reason'                      => 'unknown_custom_field_slugs',
                        'unknown_custom_field_slugs'  => $diff['unknown'],
                    ];
                }
                $payload['custom_values'] = $diff['known'];
            }

            $contact = FluentCrmApi('contacts')->createOrUpdate($payload, true, false);
            if (!$contact) {
                $invalid[] = ['email' => $row['email'], 'reason' => 'failed_to_save'];
                continue;
            }

            if ($doubleOptin && $contact->status === 'pending') {
                $contact->sendDoubleOptinEmail();
            }

            $entry = [
                'id'     => (int) $contact->id,
                'email'  => $contact->email,
                'status' => $contact->status,
            ];
            if (!empty($contact->wasRecentlyCreated)) {
                $created[] = $entry;
            } else {
                $updated[] = $entry;
            }
        }

        return [
            'ok'      => true,
            'summary' => [
                'created'  => count($created),
                'updated'  => count($updated),
                'skipped'  => count($skipped),
                'invalid'  => count($invalid),
                'warnings' => count($warnings),
            ],
            'created'  => $created,
            'updated'  => $updated,
            'skipped'  => $skipped,
            'invalid'  => $invalid,
            'warnings' => $warnings,
        ];
    }

    // -----------------------------------------------------------------
    // Write: delete-contact
    // -----------------------------------------------------------------

    public static function deleteContact($params)
    {
        $resolved = MCPHelper::resolveContact((array) $params);
        if (is_wp_error($resolved)) {
            return $resolved;
        }
        $contact = $resolved;
        $deletedId    = (int) $contact->id;
        $deletedEmail = (string) $contact->email;
        $deleteEmails = !isset($params['delete_emails']) ? true : (bool) $params['delete_emails'];

        if ($deleteEmails) {
            \FluentCrm\App\Models\CampaignEmail::where('subscriber_id', $deletedId)->delete();
        }

        $ok = \FluentCrm\App\Services\Helper::deleteContacts([$deletedId]);
        if (!$ok) {
            return MCPHelper::error('failed', __('Could not delete the contact', 'fluent-crm'));
        }

        return [
            'ok'             => true,
            'deleted_id'     => $deletedId,
            'deleted_email'  => $deletedEmail,
            'emails_purged'  => (bool) $deleteEmails,
        ];
    }

    // -----------------------------------------------------------------
    // Write: apply-segments-to-contacts
    // -----------------------------------------------------------------

    public static function applySegmentsToContacts($params)
    {
        $params = (array) $params;

        $autoCreateTags  = !empty($params['auto_create_tags']);
        $autoCreateLists = !empty($params['auto_create_lists']);
        if (($autoCreateTags || $autoCreateLists)
            && !\FluentCrm\App\Services\PermissionManager::currentUserCan('fcrm_manage_contact_cats')) {
            return MCPHelper::error('forbidden', __('Creating new tags/lists requires fcrm_manage_contact_cats', 'fluent-crm'));
        }

        $contactIds = isset($params['contact_ids']) ? array_filter(array_map('intval', (array) $params['contact_ids'])) : [];
        $filter     = $params['filter'] ?? null;
        $dryRun     = !empty($params['dry_run']);

        if (!$contactIds && empty($filter)) {
            return MCPHelper::error('invalid_param', __('Provide contact_ids or filter', 'fluent-crm'));
        }

        if ($contactIds && !empty($filter)) {
            return MCPHelper::error('invalid_param', __('Provide contact_ids OR filter, not both', 'fluent-crm'));
        }

        $cap = (int) apply_filters('fluent_crm/mcp_bulk_cap', 5000, 'apply-segments-to-contacts');

        if (!$contactIds) {
            $validation = MCPHelper::validateUniversalFilter((array) $filter);
            if (is_wp_error($validation)) {
                return $validation;
            }
            $args = MCPHelper::buildContactsQueryArgs((array) $filter);
            $args['with'] = []; // we just need ids
            $cq = new ContactsQuery($args);
            MCPHelper::applyDateFilters($cq, (array) $filter);
            $query = $cq->getModel();

            $matched = (int) $query->count();
            // During a dry run, expose the matched count even when it
            // exceeds the cap — knowing the size is the whole point of a
            // preview. The agent can then batch.
            if ($matched > $cap && !$dryRun) {
                return MCPHelper::error('cap_reached', __('Too many contacts match the filter', 'fluent-crm'), [
                    'max'     => $cap,
                    'matched' => $matched,
                ]);
            }

            $contactIds = array_map('intval', $query->limit($cap)->pluck('id')->toArray());
            // Stash the true matched count so dry_run can echo it (the
            // pluck call above only returns up to $cap rows).
            $matchedTotal = $matched;
        } else {
            if (count($contactIds) > $cap && !$dryRun) {
                return MCPHelper::error('cap_reached', __('Too many contact_ids in a single call', 'fluent-crm'), [
                    'max'     => $cap,
                    'matched' => count($contactIds),
                ]);
            }
            $matchedTotal = count($contactIds);
        }

        // Resolve segment refs. Auto-create is suppressed during dry runs so
        // a preview never leaves orphan tags/lists behind.
        $addTags    = MCPHelper::resolveTagIds((array) ($params['add_tags'] ?? []), $autoCreateTags && !$dryRun);
        $removeTags = MCPHelper::resolveTagIds((array) ($params['remove_tags'] ?? []), false);
        $addLists   = MCPHelper::resolveListIds((array) ($params['add_lists'] ?? []), $autoCreateLists && !$dryRun);
        $removeLists = MCPHelper::resolveListIds((array) ($params['remove_lists'] ?? []), false);

        // Compute the would-create set: name strings the agent supplied that
        // don't resolve to an existing tag/list. Numeric inputs are id
        // lookups, never creation candidates (review B3 round 3).
        $tagsWouldCreate  = self::wouldCreateNames((array) ($params['add_tags'] ?? []), 'tag');
        $listsWouldCreate = self::wouldCreateNames((array) ($params['add_lists'] ?? []), 'list');

        // The "at least one" guard considers what would actually happen — if
        // dry_run with names that would create, that IS work, so don't bail.
        $hasAnyWork = $addTags['ids'] || $removeTags['ids']
            || $addLists['ids'] || $removeLists['ids']
            || ($dryRun && ($tagsWouldCreate || $listsWouldCreate));
        if (!$hasAnyWork) {
            return MCPHelper::error('invalid_param', __('Provide at least one of add_tags, remove_tags, add_lists, remove_lists', 'fluent-crm'));
        }

        if ($dryRun) {
            $formatRefs = function ($ids) {
                $out = [];
                foreach ($ids as $id) {
                    $out[] = ['id' => (int) $id];
                }
                return $out;
            };
            $exceedsCap = $matchedTotal > $cap;
            return [
                'ok'                  => true,
                'dry_run'             => true,
                'matched_contacts'    => $matchedTotal,
                'cap'                 => $cap,
                'exceeds_cap'         => $exceedsCap,
                'batches_required'    => $exceedsCap ? (int) ceil($matchedTotal / max(1, $cap)) : 1,
                'applied_to_contacts' => 0,
                'tags_added'          => $formatRefs($addTags['ids']),
                'tags_removed'        => $formatRefs($removeTags['ids']),
                'lists_added'         => $formatRefs($addLists['ids']),
                'lists_removed'       => $formatRefs($removeLists['ids']),
                'tags_would_create'   => $tagsWouldCreate,
                'lists_would_create'  => $listsWouldCreate,
                'note'                => $exceedsCap
                    ? __('Dry run — match exceeds the per-call cap. Apply by passing contact_ids in batches.', 'fluent-crm')
                    : __('Dry run — nothing was applied. Re-run without dry_run=true to commit.', 'fluent-crm'),
            ];
        }

        // Process in chunks so attach/detach don't load thousands of rows at
        // once. Each Subscriber attach/detach already de-dupes internally.
        // Track the actual touched ids (review P2 #10) so an agent can
        // reverse precisely without re-running the original filter — which
        // may match a different set after time passes.
        $chunkSize = 200;
        $applied = 0;
        $appliedIds = [];
        foreach (array_chunk($contactIds, $chunkSize) as $batchIds) {
            $subscribers = Subscriber::whereIn('id', $batchIds)->get();
            foreach ($subscribers as $sub) {
                if ($addTags['ids']) {
                    $sub->attachTags($addTags['ids']);
                }
                if ($removeTags['ids']) {
                    $sub->detachTags($removeTags['ids']);
                }
                if ($addLists['ids']) {
                    $sub->attachLists($addLists['ids']);
                }
                if ($removeLists['ids']) {
                    $sub->detachLists($removeLists['ids']);
                }
                $applied++;
                $appliedIds[] = (int) $sub->id;
            }
        }

        $formatRefs = function ($ids) {
            $out = [];
            foreach ($ids as $id) {
                $out[] = ['id' => (int) $id];
            }
            return $out;
        };

        return [
            'ok'                  => true,
            'matched_contacts'    => count($contactIds),
            'applied_to_contacts' => $applied,
            'applied_contact_ids' => $appliedIds,
            'tags_added'          => $formatRefs($addTags['ids']),
            'tags_removed'        => $formatRefs($removeTags['ids']),
            'lists_added'         => $formatRefs($addLists['ids']),
            'lists_removed'       => $formatRefs($removeLists['ids']),
            'tags_created'        => $addTags['created'],
            'lists_created'       => $addLists['created'],
            'reverse_with'        => __('To reverse: re-call apply-segments-to-contacts with contact_ids=applied_contact_ids and add_*/remove_* swapped.', 'fluent-crm'),
        ];
    }

    // -----------------------------------------------------------------
    // Write: add-contact-note
    // -----------------------------------------------------------------

    public static function addContactNote($params)
    {
        $params = (array) $params;

        $resolved = MCPHelper::resolveContact($params);
        if (is_wp_error($resolved)) {
            return $resolved;
        }
        $subscriber = $resolved;

        $title       = trim((string) ($params['title'] ?? ''));
        $description = (string) ($params['description'] ?? '');
        $type        = sanitize_key($params['type'] ?? 'note');
        $allowedTypes = ['note', 'call', 'email', 'meeting', 'quote'];
        if (!in_array($type, $allowedTypes, true)) {
            $type = 'note';
        }

        if ($title === '' || $description === '') {
            return MCPHelper::error('invalid_param', __('title and description are required', 'fluent-crm'));
        }

        $noteData = [
            'subscriber_id' => $subscriber->id,
            'type'          => $type,
            'title'         => $title,
            'description'   => $description,
            'created_at'    => !empty($params['created_at']) ? sanitize_text_field($params['created_at']) : current_time('mysql'),
        ];

        // Run through the same filter the controller does so smartcodes resolve.
        $noteData['description'] = apply_filters('fluent_crm/parse_campaign_email_text', $noteData['description'], $subscriber);
        $noteData = \FluentCrm\App\Services\Sanitize::contactNote($noteData);

        $note = \FluentCrm\App\Models\SubscriberNote::create(wp_unslash($noteData));

        do_action('fluent_crm/note_added', $note, $subscriber, $noteData);

        return [
            'ok'   => true,
            'note' => MCPHelper::formatNoteForMCP($note),
        ];
    }
}
